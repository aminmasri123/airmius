enum AirmiusDeepLinkTargetType {
  club,
  team,
  membershipApplication,
  trainingPlan,
  trainingLog,
  event,
  challenge,
  post,
  chat,
  invitation,
  message,
  notifications,
  notification,
  clubMembershipInvoice,
  billingInvoice,
  adminSettings,
  marketplaceOrder,
  profile,
  passwordReset,
  emailVerification,
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
    AirmiusDeepLinkTargetType.club => section == 'membership-requests',
    AirmiusDeepLinkTargetType.invitation => true,
    AirmiusDeepLinkTargetType.passwordReset => false,
    AirmiusDeepLinkTargetType.emailVerification => false,
    AirmiusDeepLinkTargetType.unknown => false,
    _ => true,
  };

  String get analyticsName => switch (type) {
    AirmiusDeepLinkTargetType.club => 'club',
    AirmiusDeepLinkTargetType.team => 'team',
    AirmiusDeepLinkTargetType.membershipApplication => 'membership_application',
    AirmiusDeepLinkTargetType.trainingPlan => 'training_plan',
    AirmiusDeepLinkTargetType.trainingLog => 'training_log',
    AirmiusDeepLinkTargetType.event => 'event',
    AirmiusDeepLinkTargetType.challenge => 'challenge',
    AirmiusDeepLinkTargetType.post => 'post',
    AirmiusDeepLinkTargetType.chat => 'chat',
    AirmiusDeepLinkTargetType.invitation => 'invitation',
    AirmiusDeepLinkTargetType.message => 'message',
    AirmiusDeepLinkTargetType.notifications => 'notifications',
    AirmiusDeepLinkTargetType.notification => 'notification',
    AirmiusDeepLinkTargetType.clubMembershipInvoice =>
      'club_membership_invoice',
    AirmiusDeepLinkTargetType.billingInvoice => 'billing_invoice',
    AirmiusDeepLinkTargetType.adminSettings => 'admin_settings',
    AirmiusDeepLinkTargetType.marketplaceOrder => 'marketplace_order',
    AirmiusDeepLinkTargetType.profile => 'profile',
    AirmiusDeepLinkTargetType.passwordReset => 'password_reset',
    AirmiusDeepLinkTargetType.emailVerification => 'email_verification',
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

    final isApiEmailVerification =
        pathSegments.length >= 6 &&
        root == 'api' &&
        pathSegments[1].toLowerCase() == 'v1' &&
        pathSegments[2].toLowerCase() == 'auth' &&
        pathSegments[3].toLowerCase() == 'verify-email';
    final isDirectEmailVerification =
        pathSegments.length >= 3 && root == 'verify-email';
    if (isApiEmailVerification || isDirectEmailVerification) {
      final idIndex = isApiEmailVerification ? 4 : 1;
      final hashIndex = isApiEmailVerification ? 5 : 2;
      return AirmiusDeepLinkTarget(
        type: AirmiusDeepLinkTargetType.emailVerification,
        path: path,
        id: int.tryParse(pathSegments[idIndex]),
        token: pathSegments[hashIndex],
        query: query,
      );
    }
    if (root == 'reset-password') {
      return AirmiusDeepLinkTarget(
        type: AirmiusDeepLinkTargetType.passwordReset,
        path: path,
        token:
            query['token'] ??
            (pathSegments.length > 1 ? pathSegments[1] : null),
        query: query,
      );
    }
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
    final trainingId = pathSegments.length > 2
        ? int.tryParse(pathSegments[2])
        : null;
    if (root == 'training' &&
        pathSegments.length > 2 &&
        pathSegments[1].toLowerCase() == 'plans' &&
        trainingId != null) {
      return AirmiusDeepLinkTarget(
        type: AirmiusDeepLinkTargetType.trainingPlan,
        path: path,
        id: trainingId,
        query: query,
      );
    }
    if (root == 'training' &&
        pathSegments.length > 2 &&
        pathSegments[1].toLowerCase() == 'logs' &&
        trainingId != null) {
      return AirmiusDeepLinkTarget(
        type: AirmiusDeepLinkTargetType.trainingLog,
        path: path,
        id: trainingId,
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
    if (root == 'challenges' && id != null) {
      return AirmiusDeepLinkTarget(
        type: AirmiusDeepLinkTargetType.challenge,
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
    if ((root == 'chat' || root == 'conversations') &&
        pathSegments.length > 1 &&
        pathSegments[1].toLowerCase() == 'invitations') {
      return AirmiusDeepLinkTarget(
        type: AirmiusDeepLinkTargetType.chat,
        path: path,
        section: 'invitations',
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
    if (root == 'notifications') {
      return AirmiusDeepLinkTarget(
        type: AirmiusDeepLinkTargetType.notifications,
        path: path,
        query: query,
      );
    }
    if (root == 'club-memberships') {
      final clubId = id ?? int.tryParse(query['club_id'] ?? '');
      final invoiceId = query['invoice_id'] == null
          ? null
          : int.tryParse(query['invoice_id']!);

      return AirmiusDeepLinkTarget(
        type: AirmiusDeepLinkTargetType.clubMembershipInvoice,
        path: path,
        id: clubId,
        section: invoiceId?.toString(),
        query: query,
      );
    }
    if (root == 'billing' &&
        pathSegments.length > 2 &&
        pathSegments[1].toLowerCase() == 'invoices' &&
        int.tryParse(pathSegments[2]) != null) {
      return AirmiusDeepLinkTarget(
        type: AirmiusDeepLinkTargetType.billingInvoice,
        path: path,
        id: int.tryParse(pathSegments[2]),
        query: query,
      );
    }
    if (root == 'admin' &&
        pathSegments.length > 1 &&
        pathSegments[1].toLowerCase() == 'settings') {
      return AirmiusDeepLinkTarget(
        type: AirmiusDeepLinkTargetType.adminSettings,
        path: path,
        query: query,
      );
    }
    if (root == 'marketplace' &&
        pathSegments.length >= 2 &&
        pathSegments[1] == 'orders') {
      return AirmiusDeepLinkTarget(
        type: AirmiusDeepLinkTargetType.marketplaceOrder,
        path: path,
        id: pathSegments.length > 2 ? int.tryParse(pathSegments[2]) : null,
        section: 'orders',
        query: query,
      );
    }
    if (root == 'profile') {
      final profileId = pathSegments.length > 1
          ? int.tryParse(pathSegments[1])
          : null;
      return AirmiusDeepLinkTarget(
        type: AirmiusDeepLinkTargetType.profile,
        path: path,
        id: profileId,
        section: profileId == null
            ? (pathSegments.length > 1 ? pathSegments[1] : null)
            : (pathSegments.length > 2 ? pathSegments[2] : null),
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
