#!/usr/bin/env bash
set -euo pipefail

script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
adapter_dir="$(cd "${script_dir}/.." && pwd)"
gitee_dir="$(cd "${adapter_dir}/.." && pwd)"

core_dir="${NPCINK_GOVERNANCE_CORE_DIR:-${gitee_dir}/npcink-governance-core}"
toolkit_dir="${NPCINK_ABILITIES_TOOLKIT_DIR:-${gitee_dir}/npcink-abilities-toolkit}"
suite_slug="${NPCINK_SUITE_SLUG:-npcink-ai-suite}"

suite_build_dir="${adapter_dir}/build/${suite_slug}"
suite_zip="${adapter_dir}/build/${suite_slug}.zip"

require_dir() {
	local dir="$1"
	local label="$2"
	if [[ ! -d "${dir}" ]]; then
		printf 'Missing %s directory: %s\n' "${label}" "${dir}" >&2
		exit 1
	fi
}

composer_package_release() {
	local dir="$1"
	local slug="$2"
	(
		cd "${dir}"
		composer package:release >/dev/null
	)
	local zip_path="${dir}/build/${slug}.zip"
	if [[ ! -f "${zip_path}" ]]; then
		printf 'Expected package was not created: %s\n' "${zip_path}" >&2
		exit 1
	fi
	cp "${zip_path}" "${suite_build_dir}/packages/${slug}.zip"
}

fallback_package_release() {
	local dir="$1"
	local slug="$2"
	local build_dir="${dir}/build/${slug}"
	local zip_path="${dir}/build/${slug}.zip"
	(
		cd "${dir}"
		rm -rf "${build_dir}" "${zip_path}"
		mkdir -p build
		if [[ -f .distignore ]]; then
			rsync -a --delete --exclude-from=.distignore ./ "${build_dir}/"
		else
			rsync -a --delete --exclude=.git --exclude=build ./ "${build_dir}/"
		fi
		cd build
		zip -qr "${slug}.zip" "${slug}"
	)
	cp "${zip_path}" "${suite_build_dir}/packages/${slug}.zip"
}

require_dir "${adapter_dir}" "Adapter"
require_dir "${core_dir}" "Core"
require_dir "${toolkit_dir}" "Abilities Toolkit"

rm -rf "${suite_build_dir}" "${suite_zip}"
mkdir -p "${suite_build_dir}/packages"

composer_package_release "${adapter_dir}" "npcink-ai-client-adapter"
composer_package_release "${core_dir}" "npcink-governance-core"
fallback_package_release "${toolkit_dir}" "npcink-abilities-toolkit"

cat > "${suite_build_dir}/VERSION_MATRIX.md" <<EOF
# Npcink AI Suite Version Matrix

- suite: ${suite_slug}
- adapter: npcink-ai-client-adapter ${NPCINK_OPENCLAW_ADAPTER_VERSION:-0.1.0}
- core: npcink-governance-core ${NPCINK_GOVERNANCE_CORE_VERSION:-0.1.0}
- abilities toolkit: npcink-abilities-toolkit ${NPCINK_ABILITIES_TOOLKIT_VERSION:-0.4.x}
- WordPress: 7.0+
- PHP: 8.0+

## Install Order

1. Install and activate npcink-abilities-toolkit.
2. Install and activate npcink-governance-core.
3. Install and activate npcink-ai-client-adapter.

Adapter is the productized entry plugin. Core and Toolkit remain separate
plugins with separate ownership boundaries.
EOF

cat > "${suite_build_dir}/README.md" <<EOF
# Npcink AI Suite

This archive is a distribution bundle, not a merged WordPress plugin. Install
the plugin zips from the packages directory.

- packages/npcink-abilities-toolkit.zip
- packages/npcink-governance-core.zip
- packages/npcink-ai-client-adapter.zip

See VERSION_MATRIX.md for install order and supported versions.
EOF

(
	cd "${adapter_dir}/build"
	zip -qr "${suite_slug}.zip" "${suite_slug}"
)

printf 'Created %s\n' "${suite_zip}"
