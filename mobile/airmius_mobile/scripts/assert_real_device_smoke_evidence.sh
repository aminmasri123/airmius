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

The check passes only when both files use cross-device-experience.v1 for the
current backend/mobile release, contain one privacy-safe evidence reference,
contain Result: PASS, and close all 19 required workflow/accessibility items.
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
    local platform_key="$2"
    local path="$3"

    if [[ ! -f "$path" ]]; then
        add_failure "$platform evidence file missing: $path"
        return
    fi

    local required_metadata=(
        "Contract: cross-device-experience.v1"
        "Release: 2026-08-09"
        "Mobile build: 1.0.33+77"
        "Platform: $platform_key"
    )

    for metadata in "${required_metadata[@]}"; do
        if ! grep -Fqx "$metadata" "$path"; then
            add_failure "$platform evidence is missing version-bound metadata."
            break
        fi
    done

    if ! grep -Eq '^Evidence reference: [A-Za-z0-9][A-Za-z0-9._-]{2,119}$' "$path"; then
        add_failure "$platform evidence requires one short non-sensitive evidence reference."
    fi

    if ! grep -Eiq '^Result:[[:space:]]*PASS[[:space:]]*$' "$path"; then
        add_failure "$platform evidence must contain a line exactly matching 'Result: PASS'."
    fi

    if grep -Eq '^- \[ \]' "$path"; then
        add_failure "$platform evidence still contains unchecked checklist items."
    fi

    local required_keys=(
        "CDX-01-release-build"
        "CDX-02-login-secure-session"
        "CDX-03-push-delivery-target"
        "CDX-04-event-file-access"
        "CDX-05-deep-links"
        "CDX-06-route-training-event"
        "CDX-07-event-training-log"
        "CDX-08-recruiting-profile-consent"
        "CDX-09-recruiting-chat-handoff"
        "CDX-10-refund-duplicate-submit"
        "CDX-11-payout-reconciliation"
        "CDX-12-gps-ownership"
        "CDX-13-locale-de"
        "CDX-14-locale-en"
        "CDX-15-locale-fr"
        "CDX-16-locale-ar-rtl"
        "CDX-17-assistive-technology"
        "CDX-18-text-scale-200"
        "CDX-19-privacy-review"
    )

    for key in "${required_keys[@]}"; do
        if ! grep -Eq "^- \\[[xX]\\] ${key}([[:space:]]|$)" "$path"; then
            add_failure "$platform evidence is missing a checked required item."
            break
        fi
    done

    if grep -Eiq '(https?://|airmius://|bearer[[:space:]]|token[[:space:]]*[=:]|password[[:space:]]*[=:]|[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,})' "$path"; then
        add_failure "$platform evidence contains a raw URL, credential/token marker, or email address."
    fi
}

check_file "Android" "android" "$android_evidence"
check_file "iOS" "ios" "$ios_evidence"

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
