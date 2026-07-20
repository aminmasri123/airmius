#!/usr/bin/env bash
set -euo pipefail

api_base_url="https://app.airmius.com"
app_id="com.airmius.app"
club_id="26"
event_id="1"
message_id="1"
invitation_token="test-token"
build_release=false
skip_screenshots=false
preflight_only=false
output_dir=""

usage() {
    cat <<'USAGE'
Usage: scripts/run_android_real_device_smoke.sh [options]

Options:
  --api-base-url URL       API base URL passed to Flutter release build.
  --app-id ID              Android application id. Default: com.airmius.app
  --club-id ID             Club id for deep-link smoke. Default: 26
  --event-id ID            Event id for deep-link smoke. Default: 1
  --message-id ID          Message/chat id for deep-link smoke. Default: 1
  --invitation-token TOKEN Invitation token for deep-link smoke. Default: test-token
  --build-release          Build and install release APK before smoke capture.
  --skip-screenshots       Do not capture adb screenshots.
  --preflight-only         Only run prerequisite/device checks and create evidence notes.
  --output-dir DIR         Evidence output directory.
  -h, --help               Show this help.
USAGE
}

while [[ $# -gt 0 ]]; do
    case "$1" in
        --api-base-url)
            api_base_url="${2:?Missing value for --api-base-url}"
            shift 2
            ;;
        --app-id)
            app_id="${2:?Missing value for --app-id}"
            shift 2
            ;;
        --club-id)
            club_id="${2:?Missing value for --club-id}"
            shift 2
            ;;
        --event-id)
            event_id="${2:?Missing value for --event-id}"
            shift 2
            ;;
        --message-id)
            message_id="${2:?Missing value for --message-id}"
            shift 2
            ;;
        --invitation-token)
            invitation_token="${2:?Missing value for --invitation-token}"
            shift 2
            ;;
        --build-release)
            build_release=true
            shift
            ;;
        --skip-screenshots)
            skip_screenshots=true
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
    output_dir="$root/release_evidence/android-real-device-smoke-$timestamp"
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

capture_screenshot() {
    local name="$1"

    if [[ "$skip_screenshots" == true ]]; then
        return
    fi

    adb exec-out screencap -p > "$output_dir/$name.png"
}

write_manual_notes() {
    local notes_path="$output_dir/manual_result_template.md"

    cat > "$notes_path" <<NOTES
# Android Real Device Smoke Evidence

Date UTC: $timestamp
API base URL: $api_base_url
App id: $app_id
Evidence directory: $output_dir

## Generated files

- flutter-version.log
- flutter-devices.log
- adb-devices.log
- android-device-props.log
- prerequisite-check.log
- deep-link-*.log
- *.png screenshots when screenshot capture is enabled

## Manual pass/fail results

- [ ] Login fresh install works.
- [ ] Restart restores authenticated session.
- [ ] Logout clears session after restart.
- [ ] Push opt-in prompt appears.
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
- [ ] Evidence contains no private user data, secrets, tokens or payment data.

## Tester notes

Android device / OS:
Build number:
Tester:
Result: PASS / FAIL
Notes:

## Checklist closure rule

The AIRMIUS checklist item may be marked done only after this Android evidence and matching iOS/TestFlight evidence both pass.
NOTES
}

cd "$root"

set +e
scripts/assert_linux_android_release_prerequisites.sh --require-android-device > "$output_dir/prerequisite-check.log" 2>&1
prerequisite_exit=$?
set -e

if [[ "$prerequisite_exit" -ne 0 ]]; then
    cat "$output_dir/prerequisite-check.log"
    write_manual_notes
    echo "Android real-device smoke cannot continue; prerequisite evidence written to $output_dir"
    exit "$prerequisite_exit"
fi

run_logged "$output_dir/flutter-version.log" flutter --version
run_logged "$output_dir/flutter-devices.log" flutter devices
run_logged "$output_dir/adb-devices.log" adb devices -l

{
    echo "ro.product.manufacturer=$(adb shell getprop ro.product.manufacturer | tr -d '\r')"
    echo "ro.product.model=$(adb shell getprop ro.product.model | tr -d '\r')"
    echo "ro.build.version.release=$(adb shell getprop ro.build.version.release | tr -d '\r')"
    echo "ro.build.version.sdk=$(adb shell getprop ro.build.version.sdk | tr -d '\r')"
} | tee "$output_dir/android-device-props.log"

if [[ "$build_release" == true ]]; then
    run_logged "$output_dir/flutter-build-apk-release.log" \
        flutter build apk --release \
        "--dart-define=AIRMIUS_API_BASE_URL=$api_base_url" \
        "--dart-define=AIRMIUS_USE_HTTP=true"

    run_logged "$output_dir/adb-install-release.log" \
        adb install -r build/app/outputs/flutter-apk/app-release.apk
fi

write_manual_notes

if [[ "$preflight_only" == true ]]; then
    echo "Android preflight evidence written to $output_dir"
    exit 0
fi

run_logged "$output_dir/launch-app.log" adb shell monkey -p "$app_id" -c android.intent.category.LAUNCHER 1
sleep 2
capture_screenshot "01-launch"

declare -A links=(
    ["club"]="airmius://clubs/$club_id"
    ["event"]="airmius://events/$event_id"
    ["message"]="airmius://messages/$message_id"
    ["invitation"]="airmius://invitations/$invitation_token"
)

for key in club event message invitation; do
    link="${links[$key]}"
    run_logged "$output_dir/deep-link-$key.log" \
        adb shell am start -W -a android.intent.action.VIEW -d "$link" "$app_id"
    sleep 2
    capture_screenshot "deep-link-$key"
done

echo "Android real-device smoke evidence written to $output_dir"
echo "Fill manual_result_template.md after checking login, push and upload on the device."
