#!/usr/bin/env bash
set -euo pipefail

api_base_url="https://airmius.com"
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

for numeric_target in "$club_id" "$event_id" "$message_id"; do
    if [[ ! "$numeric_target" =~ ^[1-9][0-9]{0,8}$ ]]; then
        echo "Deep-link test identifiers must be positive bounded integers." >&2
        exit 2
    fi
done

if [[ ! "$invitation_token" =~ ^test-[A-Za-z0-9_-]{1,64}$ ]]; then
    echo "Invitation smoke accepts only an explicit non-production test-* token." >&2
    exit 2
fi

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

capture_screenshot() {
    local name="$1"

    if [[ "$skip_screenshots" == true ]]; then
        return
    fi

    adb exec-out screencap -p > "$output_dir/$name.png"
}

write_manual_notes() {
    local notes_path="$output_dir/manual_result_template.md"

    cat > "$notes_path" <<'NOTES'
# Android Real Device Smoke Evidence

Contract: cross-device-experience.v1
Release: 2026-08-09
Mobile build: 1.0.37+122
Platform: android
Environment alias: staging
Evidence reference:
Device class: phone-or-tablet
OS version:
Result: PENDING

## Generated files

- flutter-version.log
- android-device-summary.log
- android-device-props.log
- prerequisite-check.log
- deep-link-*.log
- *.png screenshots when screenshot capture is enabled

## Manual pass/fail results

- [ ] CDX-01-release-build — release-equivalent build installed.
- [ ] CDX-02-login-secure-session — login, restart restore, logout and restart clearing pass.
- [ ] CDX-03-push-delivery-target — opt-in, delivery, target opening and logout invalidation pass.
- [ ] CDX-04-event-file-access — picker, upload, protected preview, retry and authorization pass.
- [ ] CDX-05-deep-links — club, event, chat and invitation targets/fallbacks pass.
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
- [ ] CDX-17-assistive-technology — TalkBack labels, order, actions and status announcements pass.
- [ ] CDX-18-text-scale-200 — 200 percent text, reflow, touch targets and keyboard insets pass.
- [ ] CDX-19-privacy-review — evidence contains no raw URLs, identifiers, contacts, tokens, secrets or payment data.

## Checklist closure rule

Set Result to PASS only after all 19 items are checked. Reviewer identity and approval remain exclusively in the authoritative release manifest.
NOTES
}

cd "$root"

prerequisite_details="$(mktemp)"
trap 'rm -f "$prerequisite_details"' EXIT
set +e
scripts/assert_linux_android_release_prerequisites.sh --require-android-device > "$prerequisite_details" 2>&1
prerequisite_exit=$?
set -e

if [[ "$prerequisite_exit" -ne 0 ]]; then
    echo "Android real-device prerequisites failed; no environment paths or device data were copied into evidence." | tee "$output_dir/prerequisite-check.log"
    write_manual_notes
    echo "Android real-device smoke cannot continue; prerequisite evidence written to $output_dir"
    exit "$prerequisite_exit"
fi

echo "Android real-device prerequisites passed; sensitive environment details were intentionally omitted." > "$output_dir/prerequisite-check.log"

run_logged "$output_dir/flutter-version.log" flutter --version
authorized_device_count="$(adb devices | awk 'NR > 1 && $2 == "device" { count++ } END { print count + 0 }')"
printf 'Authorized Android devices: %s\nDevice identifiers intentionally omitted.\n' "$authorized_device_count" > "$output_dir/android-device-summary.log"

{
    echo "ro.product.manufacturer=$(adb shell getprop ro.product.manufacturer | tr -d '\r')"
    echo "ro.product.model=$(adb shell getprop ro.product.model | tr -d '\r')"
    echo "ro.build.version.release=$(adb shell getprop ro.build.version.release | tr -d '\r')"
    echo "ro.build.version.sdk=$(adb shell getprop ro.build.version.sdk | tr -d '\r')"
} | tee "$output_dir/android-device-props.log"

if [[ "$build_release" == true ]]; then
    run_redacted_logged "$output_dir/flutter-build-apk-release.log" "flutter build apk --release [staging configuration redacted]" \
        flutter build apk --release \
        "--dart-define=AIRMIUS_API_BASE_URL=$api_base_url" \
        "--dart-define=AIRMIUS_USE_HTTP=true"

    run_redacted_logged "$output_dir/adb-install-release.log" "adb install release artifact" \
        adb install -r build/app/outputs/flutter-apk/app-release.apk
fi

write_manual_notes

if [[ "$preflight_only" == true ]]; then
    echo "Android preflight evidence written to $output_dir"
    exit 0
fi

run_redacted_logged "$output_dir/launch-app.log" "launch release application" adb shell monkey -p "$app_id" -c android.intent.category.LAUNCHER 1
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
    run_redacted_logged "$output_dir/deep-link-$key.log" "open redacted $key test deep link" \
        adb shell am start -W -a android.intent.action.VIEW -d "$link" "$app_id"
    sleep 2
    capture_screenshot "deep-link-$key"
done

echo "Android real-device smoke evidence written to $output_dir"
echo "Fill manual_result_template.md after checking login, push and upload on the device."
