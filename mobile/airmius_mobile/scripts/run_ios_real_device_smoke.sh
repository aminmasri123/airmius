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

write_manual_notes() {
    local notes_path="$output_dir/manual_result_template.md"

    cat > "$notes_path" <<NOTES
# iOS Real Device / TestFlight Smoke Evidence

Date UTC: $timestamp
API base URL: $api_base_url
Bundle id: $bundle_id
Evidence directory: $output_dir

## Generated files

- flutter-version.log
- flutter-devices.log
- xcode-version.log
- ios-build-ipa-release.log when --build-ipa is used

## Manual pass/fail results

- [ ] Signed build installed through Xcode, Apple Configurator or TestFlight.
- [ ] Fresh install opens login.
- [ ] Test user logs in.
- [ ] Restart restores authenticated session.
- [ ] Logout clears session after restart.
- [ ] Push permission prompt appears.
- [ ] Push token is registered in backend.
- [ ] Test push notification arrives.
- [ ] Tapping notification opens intended target.
- [ ] Logout removes or invalidates push token.
- [ ] Native upload picker opens.
- [ ] Valid image/PDF upload succeeds.
- [ ] Failed upload can retry cleanly.
- [ ] Club deep link opens expected club target or safe fallback.
- [ ] Event deep link opens expected event target or safe fallback.
- [ ] Chat/message deep link opens expected target or safe fallback.
- [ ] Invitation deep link opens expected target or safe fallback.
- [ ] Universal links are verified after apple-app-site-association deployment.
- [ ] Evidence contains no private user data, secrets, tokens or payment data.

## iOS deep links to test on device

- airmius://clubs/26
- airmius://events/1
- airmius://messages/1
- airmius://invitations/test-token

## Tester notes

iPhone model / iOS:
Build number:
Install channel: Xcode / TestFlight
Tester:
Result: PASS / FAIL
Notes:

## Checklist closure rule

The AIRMIUS checklist item may be marked done only after this iOS evidence and matching Android evidence both pass.
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
run_logged "$output_dir/flutter-devices.log" flutter devices
run_logged "$output_dir/xcode-version.log" xcodebuild -version

if [[ "$build_ipa" == true ]]; then
    run_logged "$output_dir/ios-build-ipa-release.log" \
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
