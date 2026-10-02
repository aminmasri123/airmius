#!/usr/bin/env bash
set -euo pipefail

api_base_url="https://airmius.com"
bundle_id="com.airmius.app"
build_ipa=false
preflight_only=false
output_dir=""

usage() {
    cat <<'USAGE'
Usage: scripts/run_ios_real_device_smoke.sh [options]

Options:
  --api-base-url URL  API base URL passed to Flutter IPA build.
  --bundle-id ID      iOS bundle id. Default: com.airmius.app
  --build-ipa         Build release IPA before manual TestFlight/device smoke.
  --preflight-only    Only run macOS/Xcode/Flutter checks and create evidence notes.
  --output-dir DIR    Evidence output directory.
  -h, --help          Show this help.
USAGE
}

while [[ $# -gt 0 ]]; do
    case "$1" in
        --api-base-url)
            api_base_url="${2:?Missing value for --api-base-url}"
            shift 2
            ;;
        --bundle-id)
            bundle_id="${2:?Missing value for --bundle-id}"
            shift 2
            ;;
        --build-ipa)
            build_ipa=true
            shift
            ;;
        --preflight-only)
            preflight_only=true
            shift
            ;;
        --output-dir)
            output_dir="${2:?Missing value for --output-dir}"
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

script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
root="$(cd "$script_dir/.." && pwd)"
timestamp="$(date -u +"%Y%m%dT%H%M%SZ")"

if [[ -z "$output_dir" ]]; then
    output_dir="$root/release_evidence/ios-real-device-smoke-$timestamp"
fi

mkdir -p "$output_dir"

run_logged() {
    local log_file="$1"
    shift

    {
        printf '$'
        printf ' %q' "$@"
        printf '\n\n'
        "$@"
    } 2>&1 | tee "$log_file"
}

redact_output() {
    sed -E \
        -e 's#https?://[^[:space:]]+#https://[redacted]#g' \
        -e 's#airmius://[^[:space:]]+#airmius://[redacted]#g' \
        -e 's#(token|secret|password)[[:space:]]*[=:][[:space:]]*[^[:space:]]+#\1=[redacted]#gi'
}

run_redacted_logged() {
    local log_file="$1"
    local operation="$2"
    shift 2

    {
        printf '$ %s\n\n' "$operation"
        "$@" 2>&1 | redact_output
    } | tee "$log_file"
}

write_manual_notes() {
    local notes_path="$output_dir/manual_result_template.md"

    cat > "$notes_path" <<'NOTES'
# iOS Real Device / TestFlight Smoke Evidence

Contract: cross-device-experience.v1
Release: 2026-08-09
Mobile build: 1.0.71+157
Platform: ios
Environment alias: staging
Evidence reference:
Device class: iphone
OS version:
Install channel: Xcode-or-TestFlight
Result: PENDING

## Generated files

- flutter-version.log
- device identifiers intentionally omitted; device class/OS belong in this template
- xcode-version.log
- ios-build-ipa-release.log when --build-ipa is used

## Manual pass/fail results

- [ ] CDX-01-release-build — signed release/TestFlight build installed.
- [ ] CDX-02-login-secure-session — login, restart restore, logout and restart clearing pass.
- [ ] CDX-03-push-delivery-target — opt-in, delivery, target opening and logout invalidation pass.
- [ ] CDX-04-event-file-access — picker, upload, protected preview, retry and authorization pass.
- [ ] CDX-05-deep-links — club, event, chat, invitation and universal-link targets/fallbacks pass.
- [ ] CDX-06-route-training-event — route selection and navigation to training and event pass.
- [ ] CDX-07-event-training-log — event start/finish and prefilled training documentation pass.
- [ ] CDX-08-recruiting-profile-consent — field-scoped profile sharing and separate chat consent pass.
- [ ] CDX-09-recruiting-chat-handoff — recruiting chat and membership handoff pass.
- [ ] CDX-10-refund-duplicate-submit — duplicate submit cannot duplicate refund or restock.
- [ ] CDX-11-payout-reconciliation — preparation, payment, currency, adjustment and recovery pass.
- [ ] CDX-12-gps-ownership — own/foreign GPS-track ownership boundaries pass.
- [ ] CDX-13-locale-de — core journeys, states and values pass visually in German.
- [ ] CDX-14-locale-en — core journeys, states and values pass visually in English.
- [ ] CDX-15-locale-fr — long labels, wrapping and values pass visually in French.
- [ ] CDX-16-locale-ar-rtl — Arabic semantics, overflow and real RTL direction pass.
- [ ] CDX-17-assistive-technology — VoiceOver labels, order, actions and status announcements pass.
- [ ] CDX-18-text-scale-200 — 200 percent text, reflow, touch targets and keyboard insets pass.
- [ ] CDX-19-privacy-review — evidence contains no raw URLs, identifiers, contacts, tokens, secrets or payment data.

## Checklist closure rule

Set Result to PASS only after all 19 items are checked. Reviewer identity and approval remain exclusively in the authoritative release manifest.
NOTES
}

cd "$root"

write_manual_notes

if [[ "$(uname -s)" != "Darwin" ]]; then
    {
        echo "iOS real-device smoke requires macOS with Xcode."
        echo "Current host: $(uname -s)"
    } | tee "$output_dir/preflight-error.log"
    exit 1
fi

if ! command -v flutter >/dev/null 2>&1; then
    echo "Flutter command not found in PATH." | tee "$output_dir/preflight-error.log"
    exit 1
fi

if ! command -v xcodebuild >/dev/null 2>&1; then
    echo "xcodebuild command not found. Install Xcode and select it with xcode-select." | tee "$output_dir/preflight-error.log"
    exit 1
fi

run_logged "$output_dir/flutter-version.log" flutter --version
run_logged "$output_dir/xcode-version.log" xcodebuild -version

if [[ "$build_ipa" == true ]]; then
    run_redacted_logged "$output_dir/ios-build-ipa-release.log" "flutter build ipa --release [staging configuration redacted]" \
        flutter build ipa --release \
        "--dart-define=AIRMIUS_API_BASE_URL=$api_base_url" \
        "--dart-define=AIRMIUS_USE_HTTP=true"
fi

if [[ "$preflight_only" == true ]]; then
    echo "iOS preflight evidence written to $output_dir"
    exit 0
fi

echo "iOS evidence template written to $output_dir"
echo "Install the signed app on a real iPhone or through TestFlight, then fill manual_result_template.md."
