#!/usr/bin/env bash
set -euo pipefail

script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
root="$(cd "$script_dir/.." && pwd)"
manifest_path="$root/store_listing/release/release_evidence_manifest.json"
pubspec_path="$root/pubspec.yaml"
android_gradle_path="$root/android/app/build.gradle.kts"
ios_project_path="$root/ios/Runner.xcodeproj/project.pbxproj"

for command_name in jq rg sed; do
    if ! command -v "$command_name" >/dev/null 2>&1; then
        echo "Required command not found: $command_name" >&2
        exit 1
    fi
done

for required_path in "$manifest_path" "$pubspec_path" "$android_gradle_path" "$ios_project_path"; do
    if [[ ! -f "$required_path" ]]; then
        echo "Required release file not found: $required_path" >&2
        exit 1
    fi
done

pubspec_version="$(sed -nE 's/^version:[[:space:]]*([^[:space:]#]+).*/\1/p' "$pubspec_path" | head -n 1)"
manifest_version="$(jq -er '.version | select(type == "string" and length > 0)' "$manifest_path")"
android_application_id="$(jq -er '.android_application_id | select(type == "string" and length > 0)' "$manifest_path")"
ios_bundle_id="$(jq -er '.ios_bundle_id | select(type == "string" and length > 0)' "$manifest_path")"

if [[ -z "$pubspec_version" ]]; then
    echo "pubspec.yaml does not contain a version line." >&2
    exit 1
fi

if [[ "$manifest_version" != "$pubspec_version" ]]; then
    echo "Manifest and pubspec versions do not match." >&2
    exit 1
fi

if [[ "$android_application_id" != "com.airmius.app" ]] ||
    ! rg -q 'applicationId\s*=\s*"com\.airmius\.app"' "$android_gradle_path"; then
    echo "Android application ID is inconsistent." >&2
    exit 1
fi

if [[ "$ios_bundle_id" != "com.airmius.app" ]] ||
    ! rg -q 'PRODUCT_BUNDLE_IDENTIFIER\s*=\s*com\.airmius\.app;' "$ios_project_path"; then
    echo "iOS bundle ID is inconsistent." >&2
    exit 1
fi

echo "Release version consistency check passed."
echo "Version: $pubspec_version"
echo "Android application ID: $android_application_id"
echo "iOS bundle ID: $ios_bundle_id"
