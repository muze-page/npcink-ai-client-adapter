#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
NODE_BIN="${NODE_BIN:-node}"
PROFILE="${MAA_ADAPTER_ACCEPTANCE_PROFILE:-local}"
ABILITY_ID="${MAA_ADAPTER_ZHIHU_ATOMICS_ABILITY_ID:-npcink-toolbox/cloud-web-search}"
FIXTURE_DIR="$ROOT_DIR/tests/fixtures/openclaw-zhihu-atomics"
REQUEST_SLEEP_SECONDS="${MAA_ADAPTER_ZHIHU_ATOMICS_SLEEP_SECONDS:-2}"

CLI=( "$NODE_BIN" "$ROOT_DIR/packages/adapter-cli/bin/npcink-openclaw-adapter.mjs" )
COMMON_ARGS=( "--profile=$PROFILE" )
if [[ "${MAA_ADAPTER_ACCEPTANCE_INSECURE_LOCAL_TLS:-}" == "1" ]]; then
	COMMON_ARGS+=( "--insecure-local-tls" )
fi

fail() {
	echo "[fail] $*" >&2
	exit 1
}

assert_atom_response() {
	local output_file="$1"
	local label="$2"
	local expected_intent="$3"
	local expected_output="$4"

	"$NODE_BIN" - "$output_file" "$label" "$expected_intent" "$expected_output" "$ABILITY_ID" <<'NODE'
const fs = require('node:fs');
const [file, label, expectedIntent, expectedOutput, abilityId] = process.argv.slice(2);
const data = JSON.parse(fs.readFileSync(file, 'utf8'));
function fail(message) {
  console.error(`[fail] ${label}: ${message}`);
  process.exit(1);
}
function contractVersion(value) {
  return value && typeof value === 'object' ? String(value.contract_version || '') : '';
}
if (data.ability_id !== abilityId) fail(`expected ability_id ${abilityId}, got ${data.ability_id}`);
if (data.governance_mode !== 'direct_read') fail('expected direct_read governance mode');
if (data.execution_surface !== 'wp_abilities_rest') fail('expected wp_abilities_rest execution surface');
if (data.core_proxy_execute !== false) fail('expected core_proxy_execute=false');
if (data.commit_execution !== false) fail('expected commit_execution=false');
const result = data.result && typeof data.result === 'object' ? data.result : {};
if (result.code) {
  const cloudPayload = result.data && result.data.cloud_payload ? result.data.cloud_payload : {};
  const cloudCode = cloudPayload.error_code || '';
  const cloudMessage = cloudPayload.message || '';
  fail(`runtime returned ${result.code}: ${result.message || ''}${cloudCode ? `; cloud=${cloudCode}` : ''}${cloudMessage ? `; cloud_message=${cloudMessage}` : ''}`);
}
if (result.intent !== expectedIntent) fail(`expected result.intent ${expectedIntent}, got ${result.intent}`);
if (result.handoff && result.handoff.direct_wordpress_write !== false) fail('expected handoff.direct_wordpress_write=false');
if (result.handoff && result.handoff.final_writes !== 'core_proposal_required') fail('expected handoff.final_writes=core_proposal_required');
if (result.write_posture && result.write_posture !== 'suggestion_only') fail('expected suggestion_only write posture');
const atomicOutputs = result.atomic_outputs && typeof result.atomic_outputs === 'object' ? result.atomic_outputs : {};
if (expectedOutput === 'topic_candidate.v1') {
  const topicCandidates = atomicOutputs.topic_candidates || atomicOutputs.topic_candidate;
  if (contractVersion(topicCandidates) !== 'topic_candidate.v1') fail('expected topic_candidate.v1 atomic output');
} else if (expectedOutput === 'source_evidence.v1') {
  if (contractVersion(atomicOutputs.source_evidence) !== 'source_evidence.v1') fail('expected source_evidence.v1 atomic output');
} else if (expectedOutput === 'grounded_answer.v1') {
  if (result.output_contract !== 'grounded_answer.v1' && contractVersion(atomicOutputs.grounded_answer) !== 'grounded_answer.v1') {
    fail('expected grounded_answer.v1 output contract');
  }
  if (atomicOutputs.grounded_answer && atomicOutputs.grounded_answer.direct_wordpress_write !== false) fail('expected grounded_answer direct_wordpress_write=false');
}
NODE
}

tmp_dir="$(mktemp -d)"
cleanup() {
	rm -rf "$tmp_dir"
}
trap cleanup EXIT

echo "[zhihu-atomics] checking signed Adapter status"
"${CLI[@]}" status "${COMMON_ARGS[@]}" >/dev/null

declare -a labels=(
	"zhihu-hot-topics"
	"zhihu-search"
	"global-search"
	"zhida-simple"
	"zhida-deep"
	"zhida-deepsearch"
)
declare -a inputs=(
	"zhihu-hot-topics.input.json"
	"zhihu-search.input.json"
	"global-search.input.json"
	"zhida-simple.input.json"
	"zhida-deep.input.json"
	"zhida-deepsearch.input.json"
)
declare -a intents=(
	"zhihu_hot_topics"
	"zhihu_research"
	"zhihu_global_search"
	"zhida_simple"
	"zhida_deep"
	"zhida_deepsearch"
)
declare -a expected_outputs=(
	"topic_candidate.v1"
	"source_evidence.v1"
	"source_evidence.v1"
	"grounded_answer.v1"
	"grounded_answer.v1"
	"grounded_answer.v1"
)

for index in "${!labels[@]}"; do
	label="${labels[$index]}"
	input_file="$FIXTURE_DIR/${inputs[$index]}"
	output_file="$tmp_dir/$label.output.json"
	echo "[zhihu-atomics] running $label"
	"${CLI[@]}" read-ability "${COMMON_ARGS[@]}" --ability-id="$ABILITY_ID" --input-file="$input_file" >"$output_file"
	assert_atom_response "$output_file" "$label" "${intents[$index]}" "${expected_outputs[$index]}"
	if [[ "$index" -lt "$((${#labels[@]} - 1))" && "$REQUEST_SLEEP_SECONDS" != "0" ]]; then
		sleep "$REQUEST_SLEEP_SECONDS"
	fi
done

pack_file="$tmp_dir/article-research-pack.json"
"$NODE_BIN" - "$FIXTURE_DIR/article-research-pack.sequence.json" "$tmp_dir" "$pack_file" <<'NODE'
const fs = require('node:fs');
const [sequenceFile, outputDir, packFile] = process.argv.slice(2);
const sequence = JSON.parse(fs.readFileSync(sequenceFile, 'utf8'));
const atoms = sequence.atoms.map((step) => {
  const label = step.input_file.replace(/\.input\.json$/, '');
  const output = JSON.parse(fs.readFileSync(`${outputDir}/${label}.output.json`, 'utf8'));
  return {
    atom: step.atom,
    expected_output: step.expected_output,
    ability_id: output.ability_id,
    status: output.result && output.result.status ? output.result.status : 'unknown',
    output_contract: output.result && output.result.output_contract ? output.result.output_contract : '',
    result_count: output.result && Number.isFinite(Number(output.result.result_count)) ? Number(output.result.result_count) : 0,
    source_priority: output.result && output.result.source_priority ? output.result.source_priority : '',
  };
});
const pack = {
  artifact_type: sequence.artifact_type,
  write_posture: sequence.write_posture,
  direct_wordpress_write: sequence.direct_wordpress_write,
  final_write_path: sequence.final_write_path,
  atoms,
};
if (pack.artifact_type !== 'article_research_pack.v1') throw new Error('invalid pack artifact_type');
if (pack.write_posture !== 'suggestion_only') throw new Error('invalid pack write_posture');
if (pack.direct_wordpress_write !== false) throw new Error('invalid pack direct_wordpress_write');
if (pack.final_write_path !== 'core_proposal_required') throw new Error('invalid pack final_write_path');
fs.writeFileSync(packFile, JSON.stringify(pack, null, 2));
NODE

echo "[zhihu-atomics] article research pack smoke: $pack_file"
echo "openclaw zhihu atomics acceptance: ok"
