# Airmius Mobile Linux Android Setup Runbook

Use this runbook on Ubuntu/Linux when the Android real-device gate reports missing Java, Android SDK, `adb`, `sdkmanager`, Android licenses or connected devices.

## 1. Recommended: install Android SDK in the user directory

Use this path when Ubuntu reports package conflicts around `android-sdk`, `aapt`, `aidl`, `zipalign`, `adb` or Google Android installer packages. It avoids the Debian/Google package mix by installing the Android SDK into `~/Android/Sdk`.

First remove conflicting Ubuntu Android packages if they are installed:

```bash
sudo apt-get remove -y android-sdk android-sdk-build-tools android-sdk-platform-tools android-sdk-platform-tools-common aapt aidl apksigner dexdump split-select zipalign adb fastboot
```

Then install only generic prerequisites:

```bash
sudo apt-get update
sudo apt-get install -y default-jdk-headless curl unzip
```

Install the Android SDK tools in the current user account:

```bash
cd /var/www/airmius/mobile/airmius_mobile
scripts/install_android_sdk_user.sh --accept-licenses
```

For the current terminal:

```bash
export ANDROID_HOME="$HOME/Android/Sdk"
export ANDROID_SDK_ROOT="$HOME/Android/Sdk"
export PATH="$ANDROID_HOME/platform-tools:$ANDROID_HOME/cmdline-tools/latest/bin:$PATH"
```

Persist them in `~/.profile` or the shell profile used by the release operator:

```bash
echo 'export ANDROID_HOME="$HOME/Android/Sdk"' >> ~/.profile
echo 'export ANDROID_SDK_ROOT="$HOME/Android/Sdk"' >> ~/.profile
echo 'export PATH="$ANDROID_HOME/platform-tools:$ANDROID_HOME/cmdline-tools/latest/bin:$PATH"' >> ~/.profile
```

Open a new terminal after updating the profile.

If a newer Android SDK is required later, override the defaults:

```bash
scripts/install_android_sdk_user.sh --android-api 35 --build-tools-version 35.0.0 --accept-licenses
```

## 2. Alternative: install Google Android packages system-wide

Run these commands in a local terminal with sudo access:

```bash
sudo apt-get update
sudo apt-get remove -y android-sdk android-sdk-build-tools android-sdk-platform-tools android-sdk-platform-tools-common aapt aidl apksigner dexdump split-select zipalign adb fastboot
sudo apt-get install -y default-jdk-headless google-android-platform-tools-installer google-android-build-tools-34.0.0-installer google-android-platform-34-installer google-android-cmdline-tools-13.0-installer
```

If the command-line-tools installer is unavailable on the target machine, install Android Studio and enable:

- Android SDK Platform-Tools
- Android SDK Command-line Tools
- Android SDK Build-Tools
- Android SDK Platform for the selected release API

## 3. Set Android SDK environment variables for system packages

For the current terminal:

```bash
export ANDROID_HOME=/usr/lib/android-sdk
export ANDROID_SDK_ROOT=/usr/lib/android-sdk
export PATH="$ANDROID_HOME/platform-tools:$ANDROID_HOME/cmdline-tools/13.0/bin:$ANDROID_HOME/cmdline-tools/latest/bin:$PATH"
```

Persist them in `~/.profile` or the shell profile used by the release operator:

```bash
echo 'export ANDROID_HOME=/usr/lib/android-sdk' >> ~/.profile
echo 'export ANDROID_SDK_ROOT=/usr/lib/android-sdk' >> ~/.profile
echo 'export PATH="$ANDROID_HOME/platform-tools:$ANDROID_HOME/cmdline-tools/13.0/bin:$ANDROID_HOME/cmdline-tools/latest/bin:$PATH"' >> ~/.profile
```

Open a new terminal after updating the profile.

## 4. Accept licenses

```bash
flutter doctor --android-licenses
```

Accept every required license.

## 5. Connect Android device

On the Android phone:

1. Enable Developer Options.
2. Enable USB Debugging.
3. Connect the phone by USB.
4. Accept the RSA fingerprint prompt on the phone.

Verify:

```bash
adb devices
```

The device must be listed as `device`, not `unauthorized`.

## 6. Run Airmius checker

```bash
cd /var/www/airmius/mobile/airmius_mobile
scripts/assert_linux_android_release_prerequisites.sh --require-android-device
```

Expected result:

```text
Linux Android release prerequisites passed.
```

## 7. Run Android app smoke

```bash
flutter run -d <android-device-id> \
  --dart-define=AIRMIUS_API_BASE_URL=https://airmius.com \
  --dart-define=AIRMIUS_USE_HTTP=true
```

Then execute the Android column in `store_listing/release/real_device_smoke_test_runbook.md`.

## Current host status on 2026-07-17

This repository host could not install the Android toolchain automatically because `sudo apt-get update` required an interactive password prompt.

Observed blocker output:

- Java/JDK missing.
- `ANDROID_HOME` / `ANDROID_SDK_ROOT` not set.
- `adb` missing.
- `sdkmanager` missing.
- Android SDK licenses missing.
- No authorized Android device connected.
