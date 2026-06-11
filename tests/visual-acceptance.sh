#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
MANIFEST_PATH="${MAA_ADAPTER_VISUAL_ACCEPTANCE_OUT:-$ROOT_DIR/build/visual-acceptance/manifest.json}"
REPORT_DIR="${MAA_ADAPTER_VISUAL_ACCEPTANCE_REPORT_DIR:-$ROOT_DIR/build/visual-acceptance}"
NODE_DEPS_DIR="${MAA_ADAPTER_VISUAL_ACCEPTANCE_NODE_DIR:-$ROOT_DIR/build/visual-acceptance-node}"
SKIP_SMOKE="${MAA_ADAPTER_VISUAL_ACCEPTANCE_SKIP_SMOKE:-}"
KEEP_FIXTURES_AFTER_RUN="${MAA_ADAPTER_VISUAL_ACCEPTANCE_KEEP_FIXTURES_AFTER_RUN:-}"
INSTALL_BROWSER="${MAA_ADAPTER_VISUAL_ACCEPTANCE_INSTALL_BROWSER:-}"
CREATE_TEMP_ADMIN="${MAA_ADAPTER_VISUAL_ACCEPTANCE_CREATE_TEMP_ADMIN:-}"
TEMP_ADMIN_USER_ID=""

mkdir -p "$REPORT_DIR" "$NODE_DEPS_DIR"

wp_args=()
if [[ -n "${WP_PATH:-}" ]]; then
	wp_args+=(--path="$WP_PATH")
else
	wp_args+=(--path="/Users/muze/Local Sites/magick-ai/app/public")
fi

run_wp() {
	if [[ -n "${WP_CLI:-}" && "$WP_CLI" == *.phar ]]; then
		php_bin="${WP_CLI_PHP:-}"
		if [[ -z "$php_bin" ]]; then
			for candidate in \
				"$HOME/Library/Application Support/Local/lightning-services/php-8.2.29+0/bin/darwin-arm64/bin/php" \
				"$HOME/Library/Application Support/Local/lightning-services/php-8.5.3+1/bin/darwin-arm64/bin/php" \
				"$(command -v php 2>/dev/null || true)"
			do
				if [[ -n "$candidate" && -x "$candidate" ]]; then
					php_bin="$candidate"
					break
				fi
			done
		fi
		php_args=()
		if [[ -n "${WP_CLI_MYSQL_SOCKET:-}" ]]; then
			php_args+=("-d" "mysqli.default_socket=$WP_CLI_MYSQL_SOCKET")
		fi
		"$php_bin" "${php_args[@]}" "$WP_CLI" "${wp_args[@]}" "$@"
		return
	fi

	wp_bin="${WP_CLI:-$(command -v wp 2>/dev/null || true)}"
	"$wp_bin" "${wp_args[@]}" "$@"
}

cleanup_visual_fixtures() {
	if [[ "$KEEP_FIXTURES_AFTER_RUN" == "1" || "$KEEP_FIXTURES_AFTER_RUN" == "true" ]]; then
		return
	fi
	if [[ -f "$MANIFEST_PATH" ]]; then
		MAA_ADAPTER_VISUAL_ACCEPTANCE_OUT="$MANIFEST_PATH" run_wp eval-file "$ROOT_DIR/tests/cleanup-visual-acceptance.php" >/dev/null || true
	fi
}

cleanup_temp_admin() {
	if [[ -n "$TEMP_ADMIN_USER_ID" ]]; then
		run_wp user delete "$TEMP_ADMIN_USER_ID" --yes --reassign=1 >/dev/null || true
	fi
}

cleanup_all() {
	cleanup_visual_fixtures
	cleanup_temp_admin
}

trap cleanup_all EXIT

if [[ "$CREATE_TEMP_ADMIN" == "1" || "$CREATE_TEMP_ADMIN" == "true" ]]; then
	if [[ -n "${WP_ADMIN_USER:-}" && -z "${WP_ADMIN_PASSWORD:-}" ]] || [[ -z "${WP_ADMIN_USER:-}" && -n "${WP_ADMIN_PASSWORD:-}" ]]; then
		echo "Set both WP_ADMIN_USER and WP_ADMIN_PASSWORD, or set neither when MAA_ADAPTER_VISUAL_ACCEPTANCE_CREATE_TEMP_ADMIN=1." >&2
		exit 2
	fi
	if [[ -z "${WP_ADMIN_USER:-}" && -z "${WP_ADMIN_PASSWORD:-}" ]]; then
		temp_admin_user="codex_visual_$(date +%s)_$$"
		temp_admin_email="$temp_admin_user@example.invalid"
		if command -v openssl >/dev/null 2>&1; then
			temp_admin_password="Cv$(openssl rand -hex 18)9"
		else
			temp_admin_password="Cv$(date +%s)$$${RANDOM:-0}9"
		fi
		temp_admin_create_output="$(run_wp user create "$temp_admin_user" "$temp_admin_email" --role=administrator --user_pass="$temp_admin_password" --porcelain 2>&1)"
		TEMP_ADMIN_USER_ID="$(printf '%s\n' "$temp_admin_create_output" | awk '/^[0-9]+$/ { id = $0 } END { print id }')"
		if [[ -z "$TEMP_ADMIN_USER_ID" ]]; then
			printf '%s\n' "$temp_admin_create_output" >&2
			echo "Failed to create temporary visual acceptance admin user." >&2
			exit 2
		fi
		export WP_ADMIN_USER="$temp_admin_user"
		export WP_ADMIN_PASSWORD="$temp_admin_password"
		echo "Created temporary visual acceptance admin user: $temp_admin_user ($TEMP_ADMIN_USER_ID)"
	fi
fi

if [[ "$SKIP_SMOKE" != "1" && "$SKIP_SMOKE" != "true" ]]; then
	MAA_ADAPTER_VISUAL_ACCEPTANCE_OUT="$MANIFEST_PATH" \
	MAA_ADAPTER_KEEP_VISUAL_ACCEPTANCE_FIXTURES=1 \
	composer smoke:wp
fi

if [[ ! -f "$MANIFEST_PATH" ]]; then
	echo "Missing visual acceptance manifest: $MANIFEST_PATH" >&2
	echo "Run composer smoke:wp with MAA_ADAPTER_KEEP_VISUAL_ACCEPTANCE_FIXTURES=1, or unset MAA_ADAPTER_VISUAL_ACCEPTANCE_SKIP_SMOKE." >&2
	exit 2
fi

if [[ ! -d "$NODE_DEPS_DIR/node_modules/playwright" ]]; then
	npm install --prefix "$NODE_DEPS_DIR" --no-save playwright >/dev/null
fi

if [[ "$INSTALL_BROWSER" == "1" || "$INSTALL_BROWSER" == "true" ]]; then
	"$NODE_DEPS_DIR/node_modules/.bin/playwright" install chromium
fi

NODE_PATH="$NODE_DEPS_DIR/node_modules" \
node "$ROOT_DIR/scripts/gutenberg-visual-acceptance.mjs" "$MANIFEST_PATH" "$REPORT_DIR"
