// ignore_for_file: deprecated_member_use, avoid_web_libraries_in_flutter

import 'dart:html' as html;

class AirmiusExternalAuthLauncher {
  const AirmiusExternalAuthLauncher();

  Future<void> open(String url) async {
    html.window.location.href = url;
  }
}
