#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
NODE_BIN="${NODE_BIN:-node}"
PROFILE="${MAA_ADAPTER_ACCEPTANCE_PROFILE:-local}"
ALLOW_COMMIT="${MAA_ADAPTER_FIXTURE_ALLOW_COMMIT:-}"
CLEANUP_POST="${MAA_ADAPTER_FIXTURE_CLEANUP_POST:-1}"
WP_PATH="${WP_PATH:-/Users/muze/Local Sites/magick-ai/app/public}"
WP_CLI_BIN="${WP_CLI:-}"
WP_CLI_PHP="${WP_CLI_PHP:-}"
WP_CLI_ERROR_REPORTING="${WP_CLI_ERROR_REPORTING:-8191}"
WP_CLI_MYSQL_SOCKET="${WP_CLI_MYSQL_SOCKET:-${WP_DB_SOCKET:-}}"
CLI=( "$NODE_BIN" "$ROOT_DIR/packages/adapter-cli/bin/npcink-openclaw-adapter.mjs" )
COMMON_ARGS=( "--profile=$PROFILE" )
if [[ "${MAA_ADAPTER_ACCEPTANCE_INSECURE_LOCAL_TLS:-}" == "1" ]]; then
	COMMON_ARGS+=( "--insecure-local-tls" )
fi

fail() {
	echo "[fail] $*" >&2
	exit 1
}

json_field() {
	local file="$1"
	local expr="$2"
	"$NODE_BIN" -e '
const fs = require("node:fs");
const file = process.argv[1];
const expr = process.argv[2];
const data = JSON.parse(fs.readFileSync(file, "utf8"));
const value = expr.split(".").reduce((current, key) => current && current[key], data);
if (value === undefined || value === null || value === "") process.exit(1);
if (typeof value === "object") console.log(JSON.stringify(value));
else console.log(String(value));
' "$file" "$expr"
}

run_cli_json() {
	local output_file="$1"
	shift
	"${CLI[@]}" "$@" >"$output_file"
}

ensure_wp_cli() {
	if [[ -n "$WP_CLI_BIN" ]]; then
		return
	fi
	if [[ -f /tmp/wp-cli.phar ]]; then
		WP_CLI_BIN="/tmp/wp-cli.phar"
	elif command -v wp >/dev/null 2>&1; then
		WP_CLI_BIN="$(command -v wp)"
	else
		fail "Missing WP-CLI for fixture cleanup."
	fi
}

ensure_wp_php() {
	if [[ -n "$WP_CLI_PHP" ]]; then
		return
	fi
	for candidate in \
		"$HOME/Library/Application Support/Local/lightning-services/php-8.2.29+0/bin/darwin-arm64/bin/php" \
		"$HOME/Library/Application Support/Local/lightning-services/php-8.5.3+1/bin/darwin-arm64/bin/php" \
		"$(command -v php 2>/dev/null || true)"
	do
		if [[ -n "$candidate" && -x "$candidate" ]]; then
			WP_CLI_PHP="$candidate"
			return
		fi
	done
	fail "Missing PHP for WP-CLI fixture cleanup."
}

run_wp() {
	ensure_wp_cli
	ensure_wp_php
	if [[ -z "$WP_CLI_MYSQL_SOCKET" ]]; then
		local default_socket="$HOME/Library/Application Support/Local/run/NPb24Zg9g/mysql/mysqld.sock"
		if [[ -S "$default_socket" ]]; then
			WP_CLI_MYSQL_SOCKET="$default_socket"
		fi
	fi

	local php_args=("-d" "display_errors=0")
	if [[ -n "$WP_CLI_ERROR_REPORTING" ]]; then
		php_args+=("-d" "error_reporting=$WP_CLI_ERROR_REPORTING")
	fi
	if [[ -n "$WP_CLI_MYSQL_SOCKET" ]]; then
		php_args+=("-d" "mysqli.default_socket=$WP_CLI_MYSQL_SOCKET")
	fi
	"$WP_CLI_PHP" "${php_args[@]}" "$WP_CLI_BIN" --path="$WP_PATH" "$@"
}

tmp_dir="$(mktemp -d)"
cleanup() {
	rm -rf "$tmp_dir"
}
trap cleanup EXIT

proposal_body="$tmp_dir/create-draft-proposal.json"
proposal_out="$tmp_dir/create-draft-proposal.out.json"
status_out="$tmp_dir/proposal-status.out.json"
no_intent_out="$tmp_dir/no-intent.out"
no_intent_err="$tmp_dir/no-intent.err"
execute_out="$tmp_dir/execute.out.json"
duplicate_out="$tmp_dir/duplicate.out"
duplicate_err="$tmp_dir/duplicate.err"

cat >"$proposal_body" <<JSON
{
  "ability_id": "npcink-abilities-toolkit/create-draft",
  "title": "Adapter CLI fixture draft proposal",
  "summary": "Local AI client fixture proposal for Adapter release acceptance.",
  "input": {
    "title": "Adapter CLI fixture draft",
    "content": "Adapter CLI fixture draft created through the signed local client acceptance flow.",
    "content_format": "plain",
    "dry_run": true,
    "commit": false
  },
  "preview": {
    "action": "create_draft",
    "dry_run": true,
    "commit_execution": false
  },
  "caller": {
    "external_thread_id": "adapter-cli-fixture-acceptance"
  }
}
JSON

echo "[accept-fixture] checking signed Adapter status"
run_cli_json "$tmp_dir/status.out.json" status "${COMMON_ARGS[@]}"

echo "[accept-fixture] creating create-draft proposal through signed CLI"
run_cli_json "$proposal_out" request "${COMMON_ARGS[@]}" POST /proposals --body-file="$proposal_body"
proposal_id="$(json_field "$proposal_out" proposal_id)" || fail "Proposal creation did not return proposal_id."
ability_id="$(json_field "$proposal_out" ability_id)" || fail "Proposal creation did not return ability_id."
[[ "$ability_id" == "npcink-abilities-toolkit/create-draft" ]] || fail "Unexpected proposal ability id: $ability_id"

echo "[accept-fixture] reading proposal status through signed CLI"
run_cli_json "$status_out" request "${COMMON_ARGS[@]}" GET "/proposals/$proposal_id"
status_proposal_id="$(json_field "$status_out" proposal_id)" || fail "Proposal status did not return proposal_id."
[[ "$status_proposal_id" == "$proposal_id" ]] || fail "Proposal status id mismatch."

echo "[accept-fixture] verifying final route refuses missing commit intent"
set +e
"${CLI[@]}" request "${COMMON_ARGS[@]}" POST "/proposals/$proposal_id/approve-and-execute" >"$no_intent_out" 2>"$no_intent_err"
no_intent_code=$?
set -e
if [[ "$no_intent_code" -eq 0 ]]; then
	fail "Final route succeeded without --intent=commit."
fi
grep -q -- '--intent=commit' "$no_intent_err" || grep -q -- '--intent=commit' "$no_intent_out" || fail "Missing commit-intent refusal message."

if [[ "$ALLOW_COMMIT" != "1" ]]; then
	echo "[accept-fixture] stopping before final write. Set MAA_ADAPTER_FIXTURE_ALLOW_COMMIT=1 to run approve-and-execute."
	echo "local AI client fixture acceptance: ok"
	exit 0
fi

echo "[accept-fixture] executing approve-and-execute through signed CLI"
run_cli_json "$execute_out" request "${COMMON_ARGS[@]}" POST "/proposals/$proposal_id/approve-and-execute" --intent=commit
success="$(json_field "$execute_out" success)" || fail "Execution did not return success."
execute_ability_id="$(json_field "$execute_out" ability_id)" || fail "Execution did not return ability_id."
post_id="$(json_field "$execute_out" post_id)" || fail "Execution did not return post_id."
[[ "$success" == "true" ]] || fail "Execution did not report success."
[[ "$execute_ability_id" == "npcink-abilities-toolkit/create-draft" ]] || fail "Unexpected execution ability id: $execute_ability_id"

echo "[accept-fixture] verifying duplicate execution is rejected"
set +e
"${CLI[@]}" request "${COMMON_ARGS[@]}" POST "/proposals/$proposal_id/approve-and-execute" --intent=commit >"$duplicate_out" 2>"$duplicate_err"
duplicate_code=$?
set -e
if [[ "$duplicate_code" -eq 0 ]]; then
	fail "Duplicate approve-and-execute unexpectedly succeeded."
fi
grep -q 'npcink_openclaw_adapter_execution_already_completed' "$duplicate_out" || fail "Duplicate execution did not return completed execution code."

if [[ "$CLEANUP_POST" == "1" && "$post_id" =~ ^[0-9]+$ && "$post_id" -gt 0 ]]; then
	echo "[accept-fixture] cleaning created draft post $post_id with WP-CLI"
	run_wp post delete "$post_id" --force >/dev/null
fi

echo "local AI client fixture acceptance: ok"
