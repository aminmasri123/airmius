#!/usr/bin/env bash
set -euo pipefail

android_evidence="release_evidence/manual/real_device_smoke/android-real-device-smoke.md"
ios_evidence="release_evidence/manual/real_device_smoke/ios-real-device-smoke.md"

usage() {
    cat <<'USAGE'
Usage: scripts/assert_real_device_smoke_evidence.sh [options]

Options:
  --android PATH  Android real-device evidence markdown.
  --ios PATH      iOS/TestFlight evidence markdown.
  -h, --help      Show this help.

The check passes only when both files:
  - exist,
  - contain a line exactly matching "Result: PASS",
  - contain no unchecked "- [ ]" checklist item,
  - include checked evidence lines for login, push, upload, deep links and private-data review.
USAGE
}

while [[ $# -gt 0 ]]; do
    case "$1" in
        --android)
            android_evidence="${2:?Missing value for --android}"
            shift 2
            ;;
        --ios)
            ios_evidence="${2:?Missing value for --ios}"
            shift 2
            ;;
        -h|--help)
            usage
            exit 0
            ;;
        *)
            echo "Unknown argument: $1" >&2
            usage >&2
            exit 2
            ;;
    esac
done

failures=()

add_failure() {
    failures+=("$1")
}

check_file() {
    local platform="$1"
    local path="$2"

    if [[ ! -f "$path" ]]; then
        add_failure "$platform evidence file missing: $path"
        return
    fi

    if ! grep -Eiq '^Result:[[:space:]]*PASS[[:space:]]*$' "$path"; then
        add_failure "$platform evidence must contain a line exactly matching 'Result: PASS'."
    fi

    if grep -Eq '^- \[ \]' "$path"; then
        add_failure "$platform evidence still contains unchecked checklist items."
    fi

    local required_labels=(
        "login"
        "push"
        "upload"
        "deep link"
        "private"
    )

    for label in "${required_labels[@]}"; do
        if ! grep -Eiq "^- \\[[xX]\\].*${label}" "$path"; then
            add_failure "$platform evidence is missing a checked '$label' line."
        fi
    done
}

check_file "Android" "$android_evidence"
check_file "iOS" "$ios_evidence"

if [[ "${#failures[@]}" -gt 0 ]]; then
    echo "Real device smoke evidence check failed:"
    for failure in "${failures[@]}"; do
        echo " - $failure"
    done
    exit 1
fi

echo "Real device smoke evidence check passed."
echo "Android evidence: $android_evidence"
echo "iOS evidence: $ios_evidence"
