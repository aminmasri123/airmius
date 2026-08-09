#!/usr/bin/env bash
set -euo pipefail

script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
root="$(cd "$script_dir/.." && pwd)"
manifest_path="$root/store_listing/release/release_evidence_manifest.json"
evidence_path="$root/release_evidence"
output_path="$evidence_path/release_evidence_manifest.local.json"

while [[ $# -gt 0 ]]; do
    case "$1" in
        --manifest)
            manifest_path="$2"
            shift 2
            ;;
        --evidence-path)
            evidence_path="$2"
            shift 2
            ;;
        --output)
            output_path="$2"
            shift 2
            ;;
        *)
            echo "Unknown argument: $1" >&2
            exit 2
            ;;
    esac
done

for command_name in jq grep date readlink; do
    if ! command -v "$command_name" >/dev/null 2>&1; then
        echo "Required command not found: $command_name" >&2
        exit 1
    fi
done

if [[ ! -f "$manifest_path" ]]; then
    echo "Release evidence manifest not found: $manifest_path" >&2
    exit 1
fi

mkdir -p "$evidence_path" "$(dirname "$output_path")"
if [[ "$(readlink -f "$manifest_path")" == "$(readlink -m "$output_path")" ]]; then
    echo "Refusing to overwrite the authoritative release evidence manifest." >&2
    exit 1
fi

jq -e '.product == "Airmius Mobile" and (.gates | type == "array" and length > 0)' "$manifest_path" >/dev/null
jq . "$manifest_path" > "$output_path"

log_has_no_failure() {
    local log_path="$1"

    [[ -s "$log_path" ]] || return 1
    ! grep -Eiq '\b(error|failed|exception|fatal)\b' "$log_path"
}

log_has_markers() {
    local log_path="$1"
    shift

    log_has_no_failure "$log_path" || return 1
    for marker in "$@"; do
        grep -Fq "$marker" "$log_path" || return 1
    done
}

update_gate() {
    local gate_id="$1"
    local gate_status="$2"
    local evidence_note="$3"
    local updated_at
    local temporary_output

    updated_at="$(date -u +'%Y-%m-%dT%H:%M:%SZ')"
    temporary_output="$(mktemp "${output_path}.tmp.XXXXXX")"
    jq \
        --arg gate_id "$gate_id" \
        --arg gate_status "$gate_status" \
        --arg evidence_note "$evidence_note" \
        --arg updated_at "$updated_at" \
        '.gates |= map(
            if .id == $gate_id then
                .status = $gate_status
                | .evidence_note = $evidence_note
                | .last_updated_at = $updated_at
            else . end
        )' "$output_path" > "$temporary_output"
    mv "$temporary_output" "$output_path"
}

manifest_version="$(jq -er '.version' "$manifest_path")"
version_log="$evidence_path/release-version-consistency.log"
version_evidence_valid=false
if log_has_markers \
    "$version_log" \
    "Release version consistency check passed." \
    "Version: $manifest_version"; then
    version_evidence_valid=true
fi

prerequisite_log="$evidence_path/local-release-prerequisites.log"
if log_has_markers "$prerequisite_log" "Local release prerequisites check passed." ||
    log_has_markers "$prerequisite_log" "Linux Android release prerequisites passed."; then
    update_gate \
        "local_release_prerequisites" \
        "passed" \
        "The local prerequisite checker completed successfully."
fi

analyze_log="$evidence_path/flutter-analyze.log"
if [[ "$version_evidence_valid" == true ]] &&
    log_has_markers "$analyze_log" "No issues found!"; then
    update_gate \
        "flutter_analyze" \
        "passed" \
        "Version-bound Flutter analyze completed without findings."
fi

appbundle_log="$evidence_path/android-appbundle-build.log"
apk_log="$evidence_path/android-apk-build.log"
appbundle_artifact="$evidence_path/artifacts/app-release.aab"
apk_artifact="$evidence_path/artifacts/app-release.apk"
integrity_log="$evidence_path/android-artifact-integrity.log"

if [[ "$version_evidence_valid" == true ]] &&
    log_has_markers "$appbundle_log" "Built build/app/outputs/bundle/release/app-release.aab" &&
    log_has_markers "$apk_log" "Built build/app/outputs/flutter-apk/app-release.apk" &&
    [[ -s "$appbundle_artifact" ]] &&
    [[ -s "$apk_artifact" ]] &&
    log_has_markers "$integrity_log" \
        "Android release artifact integrity check passed." \
        "APK signature: verified" \
        "AAB JAR signature: verified" \
        "Android minSdk: 24" \
        "ZIP integrity: verified"; then
    update_gate \
        "android_release_build" \
        "passed" \
        "Version-bound AAB/APK builds are non-empty, signed, ZIP-valid, hashed, and use minSdk 24."
fi

jq -e '([.gates[].id] | length) == ([.gates[].id] | unique | length)' "$output_path" >/dev/null
jq -e 'all(.gates[]; .status | IN(
    "pending_execution",
    "pending_deployment",
    "pending_capture",
    "pending_signoff",
    "pending_packaging",
    "pending_setup",
    "partial_evidence",
    "passed",
    "blocked",
    "failed"
))' "$output_path" >/dev/null

passed_count="$(jq '[.gates[] | select(.status == "passed")] | length' "$output_path")"
gate_count="$(jq '.gates | length' "$output_path")"

echo "Linux local release evidence sync passed."
echo "Version: $manifest_version"
echo "Locally evidenced gates: $passed_count of $gate_count"
echo "Local manifest: $output_path"
echo "The authoritative manifest was not modified."
