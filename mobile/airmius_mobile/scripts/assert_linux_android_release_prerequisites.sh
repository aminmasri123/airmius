#!/usr/bin/env bash
set -euo pipefail

require_android_device=false

for arg in "$@"; do
    case "$arg" in
        --require-android-device)
            require_android_device=true
            ;;
        *)
            echo "Unknown argument: $arg" >&2
            exit 2
            ;;
    esac
done

failures=()

add_failure() {
    failures+=("$1")
}

command_exists() {
    command -v "$1" >/dev/null 2>&1
}

script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
root="$(cd "$script_dir/.." && pwd)"

if ! command_exists flutter; then
    add_failure "Flutter command not found in PATH."
fi

if ! command_exists java; then
    add_failure "Java command not found in PATH. Install a JDK before Android release builds."
fi

default_user_sdk_path="${HOME:-}/Android/Sdk"
android_sdk_path="${ANDROID_HOME:-${ANDROID_SDK_ROOT:-}}"

if [[ -z "$android_sdk_path" ]]; then
    if [[ -n "${HOME:-}" && -d "$default_user_sdk_path" ]]; then
        android_sdk_path="$default_user_sdk_path"
    else
        android_sdk_path="$default_user_sdk_path"
        add_failure "ANDROID_HOME or ANDROID_SDK_ROOT is not set, and the default user SDK path does not exist: $default_user_sdk_path"
    fi
elif [[ ! -d "$android_sdk_path" ]]; then
    add_failure "Android SDK path does not exist: $android_sdk_path"
fi

adb_path=""
if command_exists adb; then
    adb_path="$(command -v adb)"
elif [[ -n "$android_sdk_path" && -x "$android_sdk_path/platform-tools/adb" ]]; then
    adb_path="$android_sdk_path/platform-tools/adb"
else
    add_failure "adb not found. Install Android SDK Platform-Tools."
fi

sdkmanager_path=""
if command_exists sdkmanager; then
    sdkmanager_path="$(command -v sdkmanager)"
elif [[ -n "$android_sdk_path" && -x "$android_sdk_path/cmdline-tools/latest/bin/sdkmanager" ]]; then
    sdkmanager_path="$android_sdk_path/cmdline-tools/latest/bin/sdkmanager"
elif [[ -n "$android_sdk_path" && -x "$android_sdk_path/cmdline-tools/13.0/bin/sdkmanager" ]]; then
    sdkmanager_path="$android_sdk_path/cmdline-tools/13.0/bin/sdkmanager"
elif [[ -n "$android_sdk_path" && -x "$android_sdk_path/cmdline-tools/bin/sdkmanager" ]]; then
    sdkmanager_path="$android_sdk_path/cmdline-tools/bin/sdkmanager"
elif [[ -n "$android_sdk_path" && -x "$android_sdk_path/tools/bin/sdkmanager" ]]; then
    sdkmanager_path="$android_sdk_path/tools/bin/sdkmanager"
elif [[ -n "$android_sdk_path" && -d "$android_sdk_path/cmdline-tools" ]]; then
    while IFS= read -r candidate; do
        sdkmanager_path="$candidate"
        break
    done < <(find "$android_sdk_path/cmdline-tools" -mindepth 3 -maxdepth 3 -type f -name sdkmanager -perm -111 2>/dev/null | sort)

    if [[ -z "$sdkmanager_path" ]]; then
        add_failure "sdkmanager not found. Install Android SDK Command-line Tools."
    fi
else
    add_failure "sdkmanager not found. Install Android SDK Command-line Tools."
fi

license_path=""
if [[ -n "$android_sdk_path" && -d "$android_sdk_path/licenses" ]]; then
    if find "$android_sdk_path/licenses" -maxdepth 1 -type f | grep -q .; then
        license_path="$android_sdk_path/licenses"
    fi
fi

if [[ -z "$license_path" ]]; then
    add_failure "Android SDK licenses are missing. Run 'flutter doctor --android-licenses' after installing cmdline-tools."
fi

android_device_count=0
if [[ -n "$adb_path" ]]; then
    adb_devices_output=""
    if ! adb_devices_output="$("$adb_path" devices 2>&1)"; then
        compact_adb_error="$(printf "%s" "$adb_devices_output" | tr '\n' ' ' | cut -c 1-240)"
        add_failure "adb devices failed: $compact_adb_error"
    else
        android_device_count="$(printf "%s\n" "$adb_devices_output" | awk 'NR > 1 && $2 == "device" { count++ } END { print count + 0 }')"
    fi
fi

if [[ "$require_android_device" == true && "$android_device_count" -lt 1 ]]; then
    add_failure "No authorized Android device is connected. Enable USB debugging and accept the RSA prompt on the device."
fi

if [[ "${#failures[@]}" -gt 0 ]]; then
    echo "Linux Android release prerequisites failed:"
    for failure in "${failures[@]}"; do
        echo " - $failure"
    done
    echo
    echo "Recommended conflict-free user SDK install:"
    echo "  sudo apt-get update"
    echo "  sudo apt-get install -y default-jdk-headless curl unzip"
    echo "  cd $root"
    echo "  scripts/install_android_sdk_user.sh --accept-licenses"
    echo '  export ANDROID_HOME="$HOME/Android/Sdk"'
    echo '  export ANDROID_SDK_ROOT="$HOME/Android/Sdk"'
    echo '  export PATH="$ANDROID_HOME/platform-tools:$ANDROID_HOME/cmdline-tools/latest/bin:$PATH"'
    echo
    echo "Only use the Ubuntu/Google package baseline after removing conflicting Ubuntu Android packages:"
    echo "  sudo apt-get remove -y android-sdk android-sdk-build-tools android-sdk-platform-tools android-sdk-platform-tools-common aapt aidl apksigner dexdump split-select zipalign adb fastboot"
    echo "  sudo apt-get install -y default-jdk-headless google-android-platform-tools-installer google-android-build-tools-34.0.0-installer google-android-platform-34-installer google-android-cmdline-tools-13.0-installer"
    echo
    echo "After installing, rerun:"
    echo "  $root/scripts/assert_linux_android_release_prerequisites.sh --require-android-device"
    exit 1
fi

echo "Linux Android release prerequisites passed."
echo "Workspace: $root"
echo "ANDROID_HOME: ${ANDROID_HOME:-}"
echo "ANDROID_SDK_ROOT: ${ANDROID_SDK_ROOT:-}"
echo "Android SDK path: $android_sdk_path"
echo "adb: $adb_path"
echo "sdkmanager: $sdkmanager_path"
echo "Android licenses: $license_path"
echo "Authorized Android devices: $android_device_count"
