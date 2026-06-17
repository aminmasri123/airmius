import 'dart:html' as html;

class AirmiusExternalAuthLauncher {
  const AirmiusExternalAuthLauncher();

  Future<void> open(String url) async {
    html.window.location.href = url;
  }
}
