# Airmius Mobile Windows Android Setup Runbook

Use this runbook when the local release prerequisite check reports missing Android SDK components, `cmdline-tools`, `adb`, Android licenses or Java.

## Scope

This runbook is for Windows release operators before running the final Airmius Mobile evidence pipeline.

The expected local Flutter path used in the current project notes is:

```powershell
C:\flutter\bin\flutter.bat
```

## 1. Install or update Android Studio tools

Install Android Studio or open the Android Studio SDK Manager.

Required Android SDK components:

- Android SDK Platform-Tools
- Android SDK Command-line Tools
- Android SDK Build-Tools
- Android SDK Platform for the target API

If using Android Studio:

1. Open Android Studio.
2. Open `Settings`.
3. Open `Languages & Frameworks`.
4. Open `Android SDK`.
5. Open `SDK Tools`.
6. Enable `Android SDK Command-line Tools`.
7. Enable `Android SDK Platform-Tools`.
8. Apply changes.

## 2. Confirm environment variables

At least one of these should point to the Android SDK folder:

```powershell
$env:ANDROID_HOME
$env:ANDROID_SDK_ROOT
```

Common Windows SDK path:

```powershell
C:\Users\<USER>\AppData\Local\Android\Sdk
```

## 3. Accept Android licenses

After command-line tools are installed:

```powershell
C:\flutter\bin\flutter.bat doctor --android-licenses
```

Accept all required licenses.

## 4. Run Airmius local prerequisite check

```powershell
cd C:\xampp\htdocs\airmius\mobile\airmius_mobile
.\scripts\assert_local_release_prerequisites.ps1 -FlutterCommand "C:\flutter\bin\flutter.bat"
```

The check should confirm:

- Flutter command exists.
- Required release files and scripts exist.
- Android SDK path exists.
- `adb` exists.
- `sdkmanager` exists.
- Android SDK licenses exist.
- Java exists.

## 5. Continue with the evidence pipeline

```powershell
cd C:\xampp\htdocs\airmius\mobile\airmius_mobile
.\scripts\run_full_release_evidence_pipeline.ps1 -ApiBaseUrl "https://airmius.com" -FlutterCommand "C:\flutter\bin\flutter.bat"
```

## Troubleshooting

If `cmdline-tools` is still missing:

- Reopen Android Studio SDK Manager.
- Ensure `Android SDK Command-line Tools` is installed.
- Confirm `cmdline-tools\latest\bin\sdkmanager.bat` exists under the SDK folder.

If licenses are still missing:

- Run `C:\flutter\bin\flutter.bat doctor --android-licenses` again.
- Ensure `licenses` files exist under the Android SDK folder.

If Java is missing:

- Install the JDK bundled with Android Studio or a compatible JDK.
- Ensure `java` is available in the current terminal.
