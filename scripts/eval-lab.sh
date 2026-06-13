#!/usr/bin/env sh
set -eu

ROOT="$(CDPATH= cd -- "$(dirname "$0")/.." && pwd)"
EVAL_LAB_PATH="${MAGICK_AI_EVAL_LAB_PATH:-$(dirname "$ROOT")/magick-ai-eval-lab}"

if [ ! -d "$EVAL_LAB_PATH" ]; then
	echo "Magick AI Eval Lab not found: $EVAL_LAB_PATH" >&2
	echo "Set MAGICK_AI_EVAL_LAB_PATH=/path/to/magick-ai-eval-lab." >&2
	exit 1
fi

if [ "$#" -lt 1 ]; then
	echo "Usage: scripts/eval-lab.sh task=<eval-lab-task> [args...]" >&2
	exit 1
fi

cd "$EVAL_LAB_PATH"
case "$1" in
	task=*|--list|--help|-h|help|list|tasks)
		composer eval:task -- "$@"
		;;
	*)
		SCRIPT="$1"
		shift
		composer "$SCRIPT" -- "$@"
		;;
esac
