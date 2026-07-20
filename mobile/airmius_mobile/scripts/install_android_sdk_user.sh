#!/usr/bin/env bash
set -euo pipefail

sdk_root="${ANDROID_SDK_ROOT:-${ANDROID_HOME:-$HOME/Android/Sdk}}"
cmdline_tools_url="${AIRMIUS_ANDROID_CMDLINE_TOOLS_URL:-https://dl.google.com/android/repository/commandlinetools-linux-11479570_latest.zip}"
android_api="${AIRMIUS_ANDROID_API:-34}"
build_tools_version="${AIRMIUS_ANDROID_BUILD_TOOLS_VERSION:-34.0.0}"
accept_licenses=false
dry_run=false

usage() {
    cat <<'USAGE'
Install a user-local Android SDK for Airmius without Ubuntu android-sdk package conflicts.

Usage:
  scripts/install_android_sdk_user.sh [options]

Options:
  --sdk-root PATH               SDK install path. Default: ANDROID_SDK_ROOT, ANDROID_HOME, or ~/Android/Sdk.
  --cmdline-tools-url URL       Android command-line tools zip URL.
  --android-api NUMBER          Android API platform to install. Default: 34.
  --build-tools-version VERSION Android build-tools version. Default: 34.0.0.
  --accept-licenses             Run sdkmanager --licenses after package install.
  --dry-run                     Print the planned actions without downloading or installing.
  --help                        Show this help.

Before running on Ubuntu, install only the generic prerequisites:
  sudo apt-get update
  sudo apt-get install -y default-jdk-headless curl unzip
USAGE
}

while [[ $# -gt 0 ]]; do
    case "$1" in
        --sdk-root)
            sdk_root="${2:-}"
            shift 2
            ;;
        --cmdline-tools-url)
            cmdline_tools_url="${2:-}"
            shift 2
            ;;
        --android-api)
            android_api="${2:-}"
            shift 2
            ;;
        --build-tools-version)
            build_tools_version="${2:-}"
            shift 2
            ;;
        --accept-licenses)
            accept_licenses=true
            shift
            ;;
        --dry-run)
            dry_run=true
            shift
            ;;
        --help)
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

if [[ -z "$sdk_root" ]]; then
    echo "SDK root is empty. Pass --sdk-root or set ANDROID_SDK_ROOT." >&2
    exit 2
fi

command_exists() {
    command -v "$1" >/dev/null 2>&1
}

quote_command() {
    local arg
    for arg in "$@"; do
        printf "%q " "$arg"
    done
    printf "\n"
}

run_command() {
    printf "+ "
    quote_command "$@"
    if [[ "$dry_run" == false ]]; then
        "$@"
    fi
}

require_command() {
    local command_name="$1"
    local install_hint="$2"

    if ! command_exists "$command_name"; then
        echo "Missing command: $command_name" >&2
        echo "Install hint: $install_hint" >&2
        exit 1
    fi
}

download_file() {
    local url="$1"
    local output_path="$2"

    if command_exists curl; then
        run_command curl -fL "$url" -o "$output_path"
    elif command_exists wget; then
        run_command wget -O "$output_path" "$url"
    else
        echo "Missing downloader: install curl or wget." >&2
        exit 1
    fi
}

sdkmanager_path="$sdk_root/cmdline-tools/latest/bin/sdkmanager"
archive_path="$sdk_root/downloads/commandlinetools-linux.zip"

echo "Airmius Android SDK user install"
echo "SDK root: $sdk_root"
echo "Android API: $android_api"
echo "Build tools: $build_tools_version"
echo "Command-line tools URL: $cmdline_tools_url"

if [[ "$dry_run" == true ]]; then
    echo "Dry run enabled. No files will be downloaded or installed."
else
    require_command java "sudo apt-get install -y default-jdk-headless"
    require_command unzip "sudo apt-get install -y unzip"
fi

if [[ ! -x "$sdkmanager_path" ]]; then
    run_command mkdir -p "$sdk_root/cmdline-tools" "$sdk_root/downloads"
    download_file "$cmdline_tools_url" "$archive_path"

    temp_dir="$(mktemp -d)"
    echo "Temporary unzip directory: $temp_dir"
    run_command unzip -q "$archive_path" -d "$temp_dir"
    run_command mkdir -p "$sdk_root/cmdline-tools/latest"

    if [[ "$dry_run" == false && ! -d "$temp_dir/cmdline-tools" ]]; then
        echo "Unexpected command-line tools zip layout: $temp_dir/cmdline-tools not found." >&2
        exit 1
    fi

    if [[ "$dry_run" == false ]]; then
        cp -R "$temp_dir/cmdline-tools/." "$sdk_root/cmdline-tools/latest/"
    else
        echo "+ cp -R \"$temp_dir/cmdline-tools/.\" \"$sdk_root/cmdline-tools/latest/\""
    fi
fi

if [[ "$dry_run" == false && ! -x "$sdkmanager_path" ]]; then
    echo "sdkmanager was not installed at expected path: $sdkmanager_path" >&2
    exit 1
fi

run_command "$sdkmanager_path" --sdk_root="$sdk_root" \
    "platform-tools" \
    "platforms;android-$android_api" \
    "build-tools;$build_tools_version"

if [[ "$accept_licenses" == true ]]; then
    if [[ "$dry_run" == true ]]; then
        echo "+ yes | \"$sdkmanager_path\" --sdk_root=\"$sdk_root\" --licenses"
    else
        yes | "$sdkmanager_path" --sdk_root="$sdk_root" --licenses
    fi
else
    echo "Licenses not accepted automatically. Run this after install:"
    echo "  yes | \"$sdkmanager_path\" --sdk_root=\"$sdk_root\" --licenses"
fi

echo
echo "Add this to your shell profile, then open a new terminal:"
echo "  export ANDROID_HOME=\"$sdk_root\""
echo "  export ANDROID_SDK_ROOT=\"$sdk_root\""
echo "  export PATH=\"\$ANDROID_HOME/platform-tools:\$ANDROID_HOME/cmdline-tools/latest/bin:\$PATH\""
echo
echo "Then verify:"
echo "  flutter doctor --android-licenses"
echo "  scripts/assert_linux_android_release_prerequisites.sh --require-android-device"
