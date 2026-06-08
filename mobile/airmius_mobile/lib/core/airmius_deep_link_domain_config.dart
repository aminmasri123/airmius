class AirmiusDeepLinkDomainConfig {
  const AirmiusDeepLinkDomainConfig._();

  static const productionHost = 'app.airmius.com';
  static const customScheme = 'airmius';
  static const androidPackageName = 'com.airmius.app';
  static const iosBundleId = 'com.airmius.app';
  static const androidAssetLinksPath = '/.well-known/assetlinks.json';
  static const iosAssociationPath = '/.well-known/apple-app-site-association';

  static const supportedRoutes = [
    '/clubs/{id}',
    '/membership-applications/{id}',
    '/events/{id}',
    '/messages/{id}',
    '/notifications/{id}',
    '/profile/{section}',
  ];

  static Uri androidAssetLinksUri() => Uri.https(productionHost, androidAssetLinksPath);

  static Uri iosAssociationUri() => Uri.https(productionHost, iosAssociationPath);

  static Uri productionLink(String path) => Uri.https(productionHost, path);

  static Uri customSchemeLink(String path) {
    final segments = path.split('/').where((part) => part.isNotEmpty).toList();
    if (segments.isEmpty) {
      return Uri(scheme: customScheme);
    }
    return Uri(scheme: customScheme, host: segments.first, pathSegments: segments.skip(1));
  }
}
