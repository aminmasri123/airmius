enum AirmiusDeepLinkTargetType {
  club,
  membershipApplication,
  event,
  message,
  notification,
  profile,
  unknown,
}

class AirmiusDeepLinkTarget {
  const AirmiusDeepLinkTarget({
    required this.type,
    required this.path,
    this.id,
    this.section,
    this.query = const {},
  });

  final AirmiusDeepLinkTargetType type;
  final String path;
  final int? id;
  final String? section;
  final Map<String, String> query;

  bool get requiresAuth => switch (type) {
        AirmiusDeepLinkTargetType.club => false,
        AirmiusDeepLinkTargetType.unknown => false,
        _ => true,
      };

  String get analyticsName => switch (type) {
        AirmiusDeepLinkTargetType.club => 'club',
        AirmiusDeepLinkTargetType.membershipApplication => 'membership_application',
        AirmiusDeepLinkTargetType.event => 'event',
        AirmiusDeepLinkTargetType.message => 'message',
        AirmiusDeepLinkTargetType.notification => 'notification',
        AirmiusDeepLinkTargetType.profile => 'profile',
        AirmiusDeepLinkTargetType.unknown => 'unknown',
      };
}

class AirmiusDeepLinkResolver {
  const AirmiusDeepLinkResolver();

  AirmiusDeepLinkTarget resolve(String rawLink) {
    final uri = Uri.tryParse(rawLink.trim());
    if (uri == null) {
      return const AirmiusDeepLinkTarget(type: AirmiusDeepLinkTargetType.unknown, path: '');
    }

    final pathSegments = _segments(uri);
    final path = '/${pathSegments.join('/')}';
    final query = uri.queryParameters;
    if (pathSegments.isEmpty) {
      return AirmiusDeepLinkTarget(type: AirmiusDeepLinkTargetType.unknown, path: path, query: query);
    }

    final root = pathSegments.first.toLowerCase();
    final id = pathSegments.length > 1 ? int.tryParse(pathSegments[1]) : null;

    if (root == 'clubs' && id != null) {
      return AirmiusDeepLinkTarget(type: AirmiusDeepLinkTargetType.club, path: path, id: id, query: query);
    }
    if (root == 'membership-applications' && id != null) {
      return AirmiusDeepLinkTarget(type: AirmiusDeepLinkTargetType.membershipApplication, path: path, id: id, query: query);
    }
    if (root == 'events' && id != null) {
      return AirmiusDeepLinkTarget(type: AirmiusDeepLinkTargetType.event, path: path, id: id, query: query);
    }
    if (root == 'messages' && id != null) {
      return AirmiusDeepLinkTarget(type: AirmiusDeepLinkTargetType.message, path: path, id: id, query: query);
    }
    if (root == 'notifications' && id != null) {
      return AirmiusDeepLinkTarget(type: AirmiusDeepLinkTargetType.notification, path: path, id: id, query: query);
    }
    if (root == 'profile') {
      return AirmiusDeepLinkTarget(type: AirmiusDeepLinkTargetType.profile, path: path, section: pathSegments.length > 1 ? pathSegments[1] : null, query: query);
    }

    return AirmiusDeepLinkTarget(type: AirmiusDeepLinkTargetType.unknown, path: path, query: query);
  }

  List<String> _segments(Uri uri) {
    if (uri.scheme == 'airmius' && uri.host.isNotEmpty) {
      return [uri.host, ...uri.pathSegments].where((segment) => segment.isNotEmpty).toList();
    }
    return uri.pathSegments.where((segment) => segment.isNotEmpty).toList();
  }
}
