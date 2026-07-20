enum AirmiusDeepLinkTargetType {
  club,
  team,
  membershipApplication,
  event,
  post,
  chat,
  invitation,
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
    this.token,
    this.section,
    this.query = const {},
  });

  final AirmiusDeepLinkTargetType type;
  final String path;
  final int? id;
  final String? token;
  final String? section;
  final Map<String, String> query;

  bool get requiresAuth => switch (type) {
    AirmiusDeepLinkTargetType.club => false,
    AirmiusDeepLinkTargetType.invitation => false,
    AirmiusDeepLinkTargetType.unknown => false,
    _ => true,
  };

  String get analyticsName => switch (type) {
    AirmiusDeepLinkTargetType.club => 'club',
    AirmiusDeepLinkTargetType.team => 'team',
    AirmiusDeepLinkTargetType.membershipApplication => 'membership_application',
    AirmiusDeepLinkTargetType.event => 'event',
    AirmiusDeepLinkTargetType.post => 'post',
    AirmiusDeepLinkTargetType.chat => 'chat',
    AirmiusDeepLinkTargetType.invitation => 'invitation',
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
      return const AirmiusDeepLinkTarget(
        type: AirmiusDeepLinkTargetType.unknown,
        path: '',
      );
    }

    final pathSegments = _segments(uri);
    final path = '/${pathSegments.join('/')}';
    final query = uri.queryParameters;
    if (pathSegments.isEmpty) {
      return AirmiusDeepLinkTarget(
        type: AirmiusDeepLinkTargetType.unknown,
        path: path,
        query: query,
      );
    }

    final root = pathSegments.first.toLowerCase();
    final id = pathSegments.length > 1 ? int.tryParse(pathSegments[1]) : null;

    if (root == 'clubs' && id != null) {
      return AirmiusDeepLinkTarget(
        type: AirmiusDeepLinkTargetType.club,
        path: path,
        id: id,
        section: pathSegments.length > 2 ? pathSegments[2] : null,
        query: query,
      );
    }
    if (root == 'teams' && id != null) {
      return AirmiusDeepLinkTarget(
        type: AirmiusDeepLinkTargetType.team,
        path: path,
        id: id,
        section: pathSegments.length > 2 ? pathSegments[2] : null,
        query: query,
      );
    }
    if (root == 'membership-applications' && id != null) {
      return AirmiusDeepLinkTarget(
        type: AirmiusDeepLinkTargetType.membershipApplication,
        path: path,
        id: id,
        query: query,
      );
    }
    if (root == 'events' && id != null) {
      return AirmiusDeepLinkTarget(
        type: AirmiusDeepLinkTargetType.event,
        path: path,
        id: id,
        query: query,
      );
    }
    if ((root == 'feed' || root == 'posts') && id != null) {
      return AirmiusDeepLinkTarget(
        type: AirmiusDeepLinkTargetType.post,
        path: path,
        id: id,
        query: query,
      );
    }
    if ((root == 'chat' || root == 'conversations') && id != null) {
      return AirmiusDeepLinkTarget(
        type: AirmiusDeepLinkTargetType.chat,
        path: path,
        id: id,
        query: query,
      );
    }
    if (root == 'messages' && id != null) {
      return AirmiusDeepLinkTarget(
        type: AirmiusDeepLinkTargetType.message,
        path: path,
        id: id,
        query: query,
      );
    }
    if (root == 'notifications' && id != null) {
      return AirmiusDeepLinkTarget(
        type: AirmiusDeepLinkTargetType.notification,
        path: path,
        id: id,
        query: query,
      );
    }
    if (root == 'profile') {
      return AirmiusDeepLinkTarget(
        type: AirmiusDeepLinkTargetType.profile,
        path: path,
        section: pathSegments.length > 1 ? pathSegments[1] : null,
        query: query,
      );
    }
    final invitationToken = _invitationToken(root, pathSegments);
    if (invitationToken != null) {
      return AirmiusDeepLinkTarget(
        type: AirmiusDeepLinkTargetType.invitation,
        path: path,
        token: invitationToken,
        query: query,
      );
    }

    return AirmiusDeepLinkTarget(
      type: AirmiusDeepLinkTargetType.unknown,
      path: path,
      query: query,
    );
  }

  String? _invitationToken(String root, List<String> segments) {
    if (root == 'invitations' && segments.length > 1) return segments[1];
    if (root == 'team-invitations' && segments.length > 1) {
      if (segments[1] == 'token' && segments.length > 2) return segments[2];
      return segments[1];
    }
    if (root == 'club-member-invitations' && segments.length > 1) {
      if (segments[1] == 'token' && segments.length > 2) return segments[2];
      return segments[1];
    }
    if (root == 'friends' &&
        segments.length > 2 &&
        segments[1] == 'invitations') {
      if (segments[2] == 'token' && segments.length > 3) return segments[3];
      return segments[2];
    }
    return null;
  }

  List<String> _segments(Uri uri) {
    if (uri.scheme == 'airmius' && uri.host.isNotEmpty) {
      return [
        uri.host,
        ...uri.pathSegments,
      ].where((segment) => segment.isNotEmpty).toList();
    }
    return uri.pathSegments.where((segment) => segment.isNotEmpty).toList();
  }
}
