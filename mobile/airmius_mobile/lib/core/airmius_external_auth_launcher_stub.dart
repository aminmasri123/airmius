class AirmiusExternalAuthLauncher {
  const AirmiusExternalAuthLauncher();

  Future<void> open(String url) async {
    throw UnsupportedError('External browser auth is not available on this platform.');
  }
}
