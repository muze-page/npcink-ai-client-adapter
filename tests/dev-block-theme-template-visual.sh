#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
WP_PATH="${WP_PATH:-/Users/muze/Local Sites/npcink/app/public}"
WP_CLI_BIN="${WP_CLI:-}"
WP_CLI_PHP="${WP_CLI_PHP:-}"
WP_CLI_PHP_ARGS="${WP_CLI_PHP_ARGS:-}"
WP_CLI_ERROR_REPORTING="${WP_CLI_ERROR_REPORTING:-8191}"
WP_CLI_MYSQL_SOCKET="${WP_CLI_MYSQL_SOCKET:-}"
REPORT_DIR="${MAA_ADAPTER_BLOCK_THEME_VISUAL_REPORT_DIR:-$ROOT_DIR/build/dev-block-theme-template-visual}"
MANIFEST_PATH="${MAA_ADAPTER_VISUAL_ACCEPTANCE_OUT:-$REPORT_DIR/manifest.json}"
BACKUP_PATH="${MAA_ADAPTER_BLOCK_THEME_VISUAL_BACKUP:-$REPORT_DIR/template-backup.json}"
PLAN_REPORT_PATH="${MAA_ADAPTER_BLOCK_THEME_VISUAL_PLAN_REPORT:-$REPORT_DIR/plan-report.json}"
PROFILE="${MAA_ADAPTER_BLOCK_THEME_VISUAL_PROFILE:-article_standard}"
KEEP_TEMPLATE="${MAA_ADAPTER_BLOCK_THEME_VISUAL_KEEP_TEMPLATE:-}"

if [[ -z "$WP_CLI_BIN" ]]; then
	if command -v wp >/dev/null 2>&1; then
		WP_CLI_BIN="$(command -v wp)"
	elif [[ -f /tmp/wp-cli.phar ]]; then
		WP_CLI_BIN="/tmp/wp-cli.phar"
	else
		echo "Missing WP-CLI. Set WP_CLI=/path/to/wp-cli.phar or install wp on PATH." >&2
		exit 2
	fi
fi

if [[ -z "$WP_CLI_PHP" ]]; then
	for candidate in \
		"$HOME/Library/Application Support/Local/lightning-services/php-8.5.3+1/bin/darwin-arm64/bin/php" \
		"$HOME/Library/Application Support/Local/lightning-services/php-8.2.29+0/bin/darwin-arm64/bin/php" \
		"$(command -v php 2>/dev/null || true)"
	do
		if [[ -n "$candidate" && -x "$candidate" ]]; then
			WP_CLI_PHP="$candidate"
			break
		fi
	done
fi

if [[ -z "$WP_CLI_PHP" ]]; then
	echo "Missing PHP for WP-CLI. Set WP_CLI_PHP=/path/to/php." >&2
	exit 2
fi

if [[ -z "$WP_CLI_MYSQL_SOCKET" ]]; then
	default_socket="$HOME/Library/Application Support/Local/run/NPb24Zg9g/mysql/mysqld.sock"
	if [[ -S "$default_socket" ]]; then
		WP_CLI_MYSQL_SOCKET="$default_socket"
	fi
fi

wp_args=()
if [[ -n "$WP_PATH" ]]; then
	wp_args+=(--path="$WP_PATH")
fi

run_wp() {
	if [[ "$WP_CLI_BIN" == *.phar || -n "$WP_CLI_MYSQL_SOCKET" || -n "$WP_CLI_PHP_ARGS" ]]; then
		php_args=()
		php_args+=("-d" "display_errors=0")
		if [[ -n "$WP_CLI_ERROR_REPORTING" ]]; then
			php_args+=("-d" "error_reporting=$WP_CLI_ERROR_REPORTING")
		fi
		if [[ -n "$WP_CLI_MYSQL_SOCKET" ]]; then
			php_args+=("-d" "mysqli.default_socket=$WP_CLI_MYSQL_SOCKET")
		fi
		if [[ -n "$WP_CLI_PHP_ARGS" ]]; then
			extra_php_args=()
			read -r -a extra_php_args <<< "$WP_CLI_PHP_ARGS"
			php_args+=("${extra_php_args[@]}")
		fi
		"$WP_CLI_PHP" "${php_args[@]}" "$WP_CLI_BIN" "${wp_args[@]}" "$@"
		return
	fi

	"$WP_CLI_BIN" "${wp_args[@]}" "$@"
}

restore_template() {
	if [[ "$KEEP_TEMPLATE" == "1" || "$KEEP_TEMPLATE" == "true" ]]; then
		echo "Keeping local block theme template visual candidate in place because MAA_ADAPTER_BLOCK_THEME_VISUAL_KEEP_TEMPLATE=$KEEP_TEMPLATE"
		return
	fi
	if [[ -f "$BACKUP_PATH" ]]; then
		MAA_ADAPTER_BLOCK_THEME_VISUAL_MODE=restore \
		MAA_ADAPTER_BLOCK_THEME_VISUAL_BACKUP="$BACKUP_PATH" \
		run_wp eval-file "$ROOT_DIR/tests/dev-block-theme-template-visual.php" >/dev/null || true
	fi
}

trap restore_template EXIT

run_wp core is-installed >/dev/null

plugin_dir="$WP_PATH/wp-content/plugins/npcink-ai-client-adapter"
if [[ ! -e "$plugin_dir" ]]; then
	ln -s "$ROOT_DIR" "$plugin_dir"
fi

if [[ "$(run_wp plugin status npcink-ai-client-adapter --field=status 2>/dev/null || true)" != "active" ]]; then
	run_wp plugin activate npcink-ai-client-adapter >/dev/null
fi

mkdir -p "$REPORT_DIR"

MAA_ADAPTER_VISUAL_ACCEPTANCE_OUT="$MANIFEST_PATH" \
MAA_ADAPTER_BLOCK_THEME_VISUAL_BACKUP="$BACKUP_PATH" \
MAA_ADAPTER_BLOCK_THEME_VISUAL_PLAN_REPORT="$PLAN_REPORT_PATH" \
MAA_ADAPTER_BLOCK_THEME_VISUAL_PROFILE="$PROFILE" \
run_wp eval-file "$ROOT_DIR/tests/dev-block-theme-template-visual.php"

MAA_ADAPTER_VISUAL_ACCEPTANCE_SKIP_SMOKE=1 \
MAA_ADAPTER_VISUAL_ACCEPTANCE_OUT="$MANIFEST_PATH" \
MAA_ADAPTER_VISUAL_ACCEPTANCE_REPORT_DIR="$REPORT_DIR" \
WP_CLI="$WP_CLI_BIN" \
WP_CLI_PHP="$WP_CLI_PHP" \
WP_CLI_MYSQL_SOCKET="$WP_CLI_MYSQL_SOCKET" \
composer visual:wp
