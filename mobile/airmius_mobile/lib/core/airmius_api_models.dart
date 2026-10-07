typedef JsonMap = Map<String, dynamic>;

class AirmiusUser {
  const AirmiusUser({
    required this.id,
    required this.name,
    required this.email,
    required this.role,
    this.emailVerified = true,
    this.phone,
    this.twoFactorEnabled = false,
    this.firstName,
    this.lastName,
    this.birthDate,
    this.gender,
    this.guardianEmail,
    this.country,
    this.street,
    this.houseNumber,
    this.postalCode,
    this.city,
    this.state,
    this.avatarUrl,
    this.bio,
    this.profileVisibility,
    this.followersCount,
    this.followingCount,
    this.postsCount,
    this.clubs = const [],
    this.teams = const [],
    this.sportProfiles = const [],
    this.badges = const [],
    this.gamification,
    this.roles = const [],
    this.permissions = const [],
  });

  final int id;
  final String name;
  final String email;
  final String? phone;
  final String role;
  final bool emailVerified;
  final bool twoFactorEnabled;
  final String? firstName;
  final String? lastName;
  final DateTime? birthDate;
  final String? gender;
  final String? guardianEmail;
  final String? country;
  final String? street;
  final String? houseNumber;
  final String? postalCode;
  final String? city;
  final String? state;
  final String? avatarUrl;
  final String? bio;
  final String? profileVisibility;
  final int? followersCount;
  final int? followingCount;
  final int? postsCount;
  final List<AirmiusNamedItem> clubs;
  final List<AirmiusNamedItem> teams;
  final List<AirmiusUserSportProfile> sportProfiles;
  final List<AirmiusUserBadge> badges;
  final AirmiusGamification? gamification;
  final List<String> roles;
  final List<String> permissions;

  bool hasRole(String value) =>
      roles.any((role) => role.toLowerCase() == value.toLowerCase()) ||
      role.toLowerCase() == value.toLowerCase();

  bool hasAnyRole(Iterable<String> values) =>
      values.any((value) => hasRole(value));

  bool can(String value) =>
      permissions.contains(value) ||
      hasAnyRole(const ['super_admin', 'admin', 'system_admin']);

  bool get isProfileIncomplete =>
      _blank(firstName) ||
      _blank(lastName) ||
      birthDate == null ||
      _blank(gender) ||
      _blank(country) ||
      (isMinor && _blank(guardianEmail) && !requiresGuardianConsent);

  bool get isMinor {
    final date = birthDate;
    if (date == null) return false;
    final today = DateTime.now();
    var age = today.year - date.year;
    if (today.month < date.month ||
        (today.month == date.month && today.day < date.day)) {
      age--;
    }
    return age < 16;
  }

  bool get requiresGuardianConsent =>
      role.toLowerCase() == 'minor_pending_consent';

  factory AirmiusUser.fromJson(JsonMap json) => AirmiusUser(
    id: _int(json['id']),
    name: _string(json['name']),
    email: _string(json['email']),
    phone: _nullableString(json['phone']),
    role: _userRole(json),
    emailVerified: json.containsKey('email_verified')
        ? _bool(json['email_verified'])
        : true,
    twoFactorEnabled: _bool(json['two_factor_enabled']),
    firstName: _nullableString(json['first_name']),
    lastName: _nullableString(json['last_name']),
    birthDate: json['birth_date'] == null ? null : _date(json['birth_date']),
    gender: _nullableString(json['gender']),
    guardianEmail: _nullableString(json['guardian_email']),
    country: _nullableString(json['country']),
    street: _nullableString(json['street']),
    houseNumber: _nullableString(json['house_number']),
    postalCode: _nullableString(json['postal_code']),
    city: _nullableString(json['city']),
    state: _nullableString(json['state']),
    avatarUrl: _userAvatarUrl(json),
    bio: _nullableString(json['bio']),
    profileVisibility: _nullableString(json['profile_visibility']),
    followersCount: json.containsKey('followers_count')
        ? _int(json['followers_count'])
        : null,
    followingCount: json.containsKey('following_count')
        ? _int(json['following_count'])
        : null,
    postsCount: json.containsKey('posts_count')
        ? _int(json['posts_count'])
        : null,
    clubs: _jsonList(json['clubs']).map(AirmiusNamedItem.fromJson).toList(),
    teams: _jsonList(json['teams']).map(AirmiusNamedItem.fromJson).toList(),
    sportProfiles: _jsonList(
      json['sport_profiles'],
    ).map(AirmiusUserSportProfile.fromJson).toList(),
    badges: _jsonList(json['badges']).map(AirmiusUserBadge.fromJson).toList(),
    gamification: json['gamification'] is JsonMap
        ? AirmiusGamification.fromJson(json['gamification'] as JsonMap)
        : null,
    roles: _stringList(json['roles']),
    permissions: _stringList(json['permissions']),
  );
}

class AirmiusGuardianWorkspace {
  const AirmiusGuardianWorkspace({
    required this.guardianName,
    required this.guardianEmail,
    required this.canManage,
    required this.children,
  });

  final String guardianName;
  final String guardianEmail;
  final bool canManage;
  final List<AirmiusGuardianChild> children;

  int get approvedCount =>
      children.where((child) => child.status == 'approved').length;

  int get openCount =>
      children.where((child) => child.status != 'approved').length;

  AirmiusGuardianWorkspace copyWith({
    String? guardianName,
    String? guardianEmail,
    bool? canManage,
    List<AirmiusGuardianChild>? children,
  }) {
    return AirmiusGuardianWorkspace(
      guardianName: guardianName ?? this.guardianName,
      guardianEmail: guardianEmail ?? this.guardianEmail,
      canManage: canManage ?? this.canManage,
      children: children ?? this.children,
    );
  }

  factory AirmiusGuardianWorkspace.fromJson(JsonMap json) {
    final data = json['data'] is JsonMap ? json['data'] as JsonMap : json;
    final guardian = data['guardian'] is JsonMap
        ? data['guardian'] as JsonMap
        : const <String, dynamic>{};
    return AirmiusGuardianWorkspace(
      guardianName: _string(guardian['name']),
      guardianEmail: _string(guardian['email']),
      canManage: _bool(data['can_manage']),
      children: _jsonList(
        data['children'],
      ).map(AirmiusGuardianChild.fromJson).toList(),
    );
  }
}

class AirmiusGuardianChild {
  const AirmiusGuardianChild({
    required this.id,
    required this.name,
    required this.email,
    required this.status,
    required this.age,
    this.birthDate,
    this.requestedAt,
    this.approvedAt,
    this.rejectedAt,
    this.revokedAt,
    this.consentVersion,
    this.resendAvailableIn = 0,
    this.profileVisibility = 'private',
    this.directMessagePrivacy = 'friends',
    this.friendRequestPrivacy = 'friends',
    this.directMessagesEnabled = false,
  });

  final int id;
  final String name;
  final String email;
  final String status;
  final int age;
  final DateTime? birthDate;
  final DateTime? requestedAt;
  final DateTime? approvedAt;
  final DateTime? rejectedAt;
  final DateTime? revokedAt;
  final String? consentVersion;
  final int resendAvailableIn;
  final String profileVisibility;
  final String directMessagePrivacy;
  final String friendRequestPrivacy;
  final bool directMessagesEnabled;

  bool get isApproved => status == 'approved';

  factory AirmiusGuardianChild.fromJson(JsonMap json) {
    final privacy = json['privacy'] is JsonMap
        ? json['privacy'] as JsonMap
        : const <String, dynamic>{};
    return AirmiusGuardianChild(
      id: _int(json['id']),
      name: _string(json['name'], fallback: 'Kind'),
      email: _string(json['email']),
      status: _string(json['status'], fallback: 'pending'),
      age: _int(json['age']),
      birthDate: _optionalDate(json['birth_date']),
      requestedAt: _optionalDate(json['requested_at']),
      approvedAt: _optionalDate(json['approved_at']),
      rejectedAt: _optionalDate(json['rejected_at']),
      revokedAt: _optionalDate(json['revoked_at']),
      consentVersion: _nullableString(json['consent_version']),
      resendAvailableIn: _int(json['resend_available_in']),
      profileVisibility: _string(
        privacy['profile_visibility'],
        fallback: 'private',
      ),
      directMessagePrivacy: _string(
        privacy['direct_message_privacy'],
        fallback: 'friends',
      ),
      friendRequestPrivacy: _string(
        privacy['friend_request_privacy'],
        fallback: 'friends',
      ),
      directMessagesEnabled: _bool(privacy['direct_messages_enabled']),
    );
  }
}

class AirmiusGuardianInvitationPage {
  const AirmiusGuardianInvitationPage({
    required this.invitations,
    required this.summary,
    required this.pagination,
  });

  final List<AirmiusGuardianInvitation> invitations;
  final AirmiusGuardianInvitationSummary summary;
  final AirmiusPagination pagination;

  factory AirmiusGuardianInvitationPage.fromJson(JsonMap json) {
    final data = json['data'] is JsonMap ? json['data'] as JsonMap : json;
    final rawInvitations = data['invitations'];
    final invitations = rawInvitations is JsonMap
        ? _jsonList(rawInvitations['data'])
        : _jsonList(rawInvitations);
    final paginationSource = rawInvitations is JsonMap
        ? rawInvitations
        : (data['meta'] is JsonMap ? data['meta'] as JsonMap : json);

    return AirmiusGuardianInvitationPage(
      invitations: invitations
          .map(AirmiusGuardianInvitation.fromJson)
          .toList(growable: false),
      summary: AirmiusGuardianInvitationSummary.fromJson(data['summary']),
      pagination: AirmiusPagination.fromJson({
        'current_page':
            paginationSource['current_page'] ?? json['current_page'] ?? 1,
        'last_page': paginationSource['last_page'] ?? json['last_page'] ?? 1,
        'total': paginationSource['total'] ?? invitations.length,
        'from': paginationSource['from'],
        'to': paginationSource['to'],
      }),
    );
  }
}

class AirmiusGuardianInvitationSummary {
  const AirmiusGuardianInvitationSummary({
    required this.total,
    required this.open,
    required this.accepted,
    required this.needsReview,
  });

  final int total;
  final int open;
  final int accepted;
  final int needsReview;

  factory AirmiusGuardianInvitationSummary.fromJson(Object? value) {
    final json = value is JsonMap ? value : const <String, dynamic>{};
    return AirmiusGuardianInvitationSummary(
      total: _int(json['total']),
      open: _int(json['open']),
      accepted: _int(json['accepted']),
      needsReview: _int(json['needs_review']),
    );
  }
}

class AirmiusGuardianInvitation {
  const AirmiusGuardianInvitation({
    required this.id,
    required this.status,
    required this.statusLabel,
    required this.relationshipType,
    required this.isPrimary,
    required this.canAccept,
    required this.canDecline,
    this.club,
    this.child,
    this.guardianEmail,
    this.invitedAt,
    this.acceptedAt,
    this.declinedAt,
    this.revokedAt,
  });

  final int id;
  final String status;
  final String statusLabel;
  final String relationshipType;
  final bool isPrimary;
  final bool canAccept;
  final bool canDecline;
  final AirmiusNamedItem? club;
  final AirmiusGuardianInvitationChild? child;
  final String? guardianEmail;
  final DateTime? invitedAt;
  final DateTime? acceptedAt;
  final DateTime? declinedAt;
  final DateTime? revokedAt;

  factory AirmiusGuardianInvitation.fromJson(JsonMap json) {
    final club = json['club'] is JsonMap ? json['club'] as JsonMap : null;
    final child = json['child'] is JsonMap ? json['child'] as JsonMap : null;
    return AirmiusGuardianInvitation(
      id: _int(json['id']),
      status: _string(json['status'], fallback: 'invited'),
      statusLabel: _string(json['status_label'], fallback: 'Offen'),
      relationshipType: _string(
        json['relationship_type'],
        fallback: 'guardian',
      ),
      isPrimary: _bool(json['is_primary']),
      canAccept: _bool(json['can_accept']),
      canDecline: _bool(json['can_decline']),
      club: club == null ? null : AirmiusNamedItem.fromJson(club),
      child: child == null
          ? null
          : AirmiusGuardianInvitationChild.fromJson(child),
      guardianEmail: _nullableString(json['guardian_email']),
      invitedAt: _optionalDate(json['invited_at']),
      acceptedAt: _optionalDate(json['accepted_at']),
      declinedAt: _optionalDate(json['declined_at']),
      revokedAt: _optionalDate(json['revoked_at']),
    );
  }
}

class AirmiusGuardianInvitationChild {
  const AirmiusGuardianInvitationChild({
    required this.id,
    required this.name,
    required this.email,
    required this.age,
    this.birthDate,
  });

  final int id;
  final String name;
  final String email;
  final int age;
  final DateTime? birthDate;

  factory AirmiusGuardianInvitationChild.fromJson(JsonMap json) =>
      AirmiusGuardianInvitationChild(
        id: _int(json['id']),
        name: _string(json['name'], fallback: 'Kind'),
        email: _string(json['email']),
        age: _int(json['age']),
        birthDate: _optionalDate(json['birth_date']),
      );
}

class AirmiusGuardianChildOverview {
  const AirmiusGuardianChildOverview({
    required this.child,
    required this.approved,
    required this.scope,
    required this.privateDetailsHidden,
    required this.events,
    required this.training,
  });

  final AirmiusGuardianChild child;
  final bool approved;
  final String scope;
  final bool privateDetailsHidden;
  final List<AirmiusGuardianEventPreview> events;
  final AirmiusGuardianTrainingSummary training;

  factory AirmiusGuardianChildOverview.fromJson(JsonMap json) {
    final data = json['data'] is JsonMap ? json['data'] as JsonMap : json;
    final childData = data['child'] is JsonMap
        ? data['child'] as JsonMap
        : const <String, dynamic>{};
    final access = data['access'] is JsonMap
        ? data['access'] as JsonMap
        : const <String, dynamic>{};
    return AirmiusGuardianChildOverview(
      child: AirmiusGuardianChild.fromJson(childData),
      approved: _bool(access['approved']),
      scope: _string(access['scope'], fallback: 'consent_only'),
      privateDetailsHidden: access.containsKey('private_details_hidden')
          ? _bool(access['private_details_hidden'])
          : true,
      events: _jsonList(
        data['upcoming_events'],
      ).map(AirmiusGuardianEventPreview.fromJson).toList(),
      training: AirmiusGuardianTrainingSummary.fromJson(data['training']),
    );
  }
}

class AirmiusGuardianEventPreview {
  const AirmiusGuardianEventPreview({
    required this.id,
    required this.title,
    required this.type,
    this.startTime,
    this.endTime,
    this.locationName,
    this.locationCity,
    this.teamName,
    this.participationStatus,
  });

  final int id;
  final String title;
  final String type;
  final DateTime? startTime;
  final DateTime? endTime;
  final String? locationName;
  final String? locationCity;
  final String? teamName;
  final String? participationStatus;

  factory AirmiusGuardianEventPreview.fromJson(JsonMap json) {
    final team = json['team'] is JsonMap ? json['team'] as JsonMap : null;
    return AirmiusGuardianEventPreview(
      id: _int(json['id']),
      title: _string(json['title'], fallback: 'Termin'),
      type: _string(json['type'], fallback: 'public'),
      startTime: _optionalDate(json['start_time']),
      endTime: _optionalDate(json['end_time']),
      locationName: _nullableString(json['location_name']),
      locationCity: _nullableString(json['location_city']),
      teamName: team == null ? null : _nullableString(team['name']),
      participationStatus: _nullableString(json['participation_status']),
    );
  }
}

class AirmiusGuardianTrainingSummary {
  const AirmiusGuardianTrainingSummary({
    required this.periodDays,
    required this.sessions,
    required this.durationMinutes,
    required this.distanceMeters,
    required this.calories,
    this.lastPerformedAt,
    this.privateNotesHidden = true,
  });

  final int periodDays;
  final int sessions;
  final int durationMinutes;
  final int distanceMeters;
  final int calories;
  final DateTime? lastPerformedAt;
  final bool privateNotesHidden;

  factory AirmiusGuardianTrainingSummary.fromJson(Object? value) {
    final json = value is JsonMap ? value : const <String, dynamic>{};
    return AirmiusGuardianTrainingSummary(
      periodDays: _int(json['period_days']),
      sessions: _int(json['sessions']),
      durationMinutes: _int(json['duration_minutes']),
      distanceMeters: _int(json['distance_meters']),
      calories: _int(json['calories']),
      lastPerformedAt: _optionalDate(json['last_performed_at']),
      privateNotesHidden: json.containsKey('private_notes_hidden')
          ? _bool(json['private_notes_hidden'])
          : true,
    );
  }
}

class AirmiusGuardianConsentStatus {
  const AirmiusGuardianConsentStatus({
    required this.required,
    required this.status,
    required this.resendAvailableIn,
    this.guardianEmail,
    this.requestedAt,
    this.approvedAt,
    this.rejectedAt,
    this.revokedAt,
    this.consentVersion,
  });

  final bool required;
  final String status;
  final int resendAvailableIn;
  final String? guardianEmail;
  final DateTime? requestedAt;
  final DateTime? approvedAt;
  final DateTime? rejectedAt;
  final DateTime? revokedAt;
  final String? consentVersion;

  factory AirmiusGuardianConsentStatus.fromJson(JsonMap json) {
    final data = json['data'] is JsonMap ? json['data'] as JsonMap : json;
    return AirmiusGuardianConsentStatus(
      required: _bool(data['required']),
      status: _string(data['status'], fallback: 'not_required'),
      resendAvailableIn: _int(data['resend_available_in']),
      guardianEmail: _nullableString(data['guardian_email']),
      requestedAt: _optionalDate(data['requested_at']),
      approvedAt: _optionalDate(data['approved_at']),
      rejectedAt: _optionalDate(data['rejected_at']),
      revokedAt: _optionalDate(data['revoked_at']),
      consentVersion: _nullableString(data['consent_version']),
    );
  }
}

class AirmiusSportIntegrationBundle {
  const AirmiusSportIntegrationBundle({
    required this.providers,
    required this.activities,
    this.normalizedImport = const {},
    this.gpx = const {},
  });

  final List<AirmiusSportIntegrationProvider> providers;
  final List<AirmiusSportIntegrationActivity> activities;
  final JsonMap normalizedImport;
  final JsonMap gpx;

  factory AirmiusSportIntegrationBundle.fromJson(JsonMap json) {
    final data = json['data'] is JsonMap ? json['data'] as JsonMap : json;
    return AirmiusSportIntegrationBundle(
      providers: _jsonList(
        data['providers'],
      ).map(AirmiusSportIntegrationProvider.fromJson).toList(),
      activities: _jsonList(
        data['activities'],
      ).map(AirmiusSportIntegrationActivity.fromJson).toList(),
      normalizedImport: data['normalized_import'] is JsonMap
          ? data['normalized_import'] as JsonMap
          : const {},
      gpx: data['gpx'] is JsonMap ? data['gpx'] as JsonMap : const {},
    );
  }

  AirmiusSportIntegrationBundle copyWith({
    List<AirmiusSportIntegrationProvider>? providers,
    List<AirmiusSportIntegrationActivity>? activities,
  }) => AirmiusSportIntegrationBundle(
    providers: providers ?? this.providers,
    activities: activities ?? this.activities,
    normalizedImport: normalizedImport,
    gpx: gpx,
  );
}

class AirmiusSportIntegrationProvider {
  const AirmiusSportIntegrationProvider({
    required this.key,
    required this.label,
    required this.status,
    required this.connectionMode,
    required this.direction,
    required this.supportsGpsSamples,
    required this.supportsBackgroundSync,
    required this.supportsDirectSync,
    required this.scopes,
    this.nextAction,
    this.requestMessage,
    this.requestMessageKey,
    this.account,
  });

  final String key;
  final String label;
  final String status;
  final String connectionMode;
  final List<String> direction;
  final bool supportsGpsSamples;
  final bool supportsBackgroundSync;
  final bool supportsDirectSync;
  final List<String> scopes;
  final String? nextAction;
  final String? requestMessage;
  final String? requestMessageKey;
  final AirmiusSportIntegrationAccount? account;

  bool get isConnected => account?.status == 'connected';
  bool get isRequested => account?.status == 'requested';

  factory AirmiusSportIntegrationProvider.fromJson(JsonMap json) {
    return AirmiusSportIntegrationProvider(
      key: _string(json['key']),
      label: _string(json['label'], fallback: 'Sport-App'),
      status: _string(json['status'], fallback: 'planned'),
      connectionMode: _string(json['connection_mode']),
      direction: _stringList(json['direction']),
      supportsGpsSamples: _bool(json['supports_gps_samples']),
      supportsBackgroundSync: _bool(json['supports_background_sync']),
      supportsDirectSync: _bool(json['supports_direct_sync']),
      scopes: _stringList(json['scopes']),
      nextAction: _nullableString(json['next_action']),
      requestMessage: _nullableString(json['request_message']),
      requestMessageKey: _nullableString(json['request_message_key']),
      account: json['account'] is JsonMap
          ? AirmiusSportIntegrationAccount.fromJson(json['account'] as JsonMap)
          : null,
    );
  }

  AirmiusSportIntegrationProvider copyWith({
    AirmiusSportIntegrationAccount? account,
    bool clearAccount = false,
  }) => AirmiusSportIntegrationProvider(
    key: key,
    label: label,
    status: status,
    connectionMode: connectionMode,
    direction: direction,
    supportsGpsSamples: supportsGpsSamples,
    supportsBackgroundSync: supportsBackgroundSync,
    supportsDirectSync: supportsDirectSync,
    scopes: scopes,
    nextAction: nextAction,
    requestMessage: requestMessage,
    requestMessageKey: requestMessageKey,
    account: clearAccount ? null : account ?? this.account,
  );
}

class AirmiusSportIntegrationAccount {
  const AirmiusSportIntegrationAccount({
    required this.id,
    required this.status,
    required this.displayName,
    this.lastSyncedAt,
    this.syncSummary = const {},
  });

  final int id;
  final String status;
  final String displayName;
  final DateTime? lastSyncedAt;
  final JsonMap syncSummary;

  factory AirmiusSportIntegrationAccount.fromJson(JsonMap json) =>
      AirmiusSportIntegrationAccount(
        id: _int(json['id']),
        status: _string(json['status'], fallback: 'requested'),
        displayName: _string(json['display_name']),
        lastSyncedAt: _optionalDate(json['last_synced_at']),
        syncSummary: json['sync_summary'] is JsonMap
            ? json['sync_summary'] as JsonMap
            : const {},
      );
}

class AirmiusSportIntegrationActivity {
  const AirmiusSportIntegrationActivity({
    required this.id,
    required this.provider,
    required this.activityType,
    required this.title,
    this.startedAt,
    this.durationSeconds = 0,
    this.distanceMeters = 0,
    this.calories,
    this.metrics = const {},
  });

  final int id;
  final String provider;
  final String activityType;
  final String title;
  final DateTime? startedAt;
  final int durationSeconds;
  final int distanceMeters;
  final int? calories;
  final JsonMap metrics;

  factory AirmiusSportIntegrationActivity.fromJson(JsonMap json) =>
      AirmiusSportIntegrationActivity(
        id: _int(json['id']),
        provider: _string(json['provider'], fallback: 'manual'),
        activityType: _string(json['activity_type'], fallback: 'Activity'),
        title: _string(json['title'], fallback: 'Activity'),
        startedAt: _optionalDate(json['started_at']),
        durationSeconds: _int(json['duration_seconds']),
        distanceMeters: _int(json['distance_meters']),
        calories: json['calories'] == null ? null : _int(json['calories']),
        metrics: json['metrics'] is JsonMap
            ? json['metrics'] as JsonMap
            : const {},
      );

  AirmiusSportIntegrationActivity copyWith({String? title}) =>
      AirmiusSportIntegrationActivity(
        id: id,
        provider: provider,
        activityType: activityType,
        title: title ?? this.title,
        startedAt: startedAt,
        durationSeconds: durationSeconds,
        distanceMeters: distanceMeters,
        calories: calories,
        metrics: metrics,
      );
}

String _userRole(JsonMap json) {
  final pivot = json['pivot'];
  final membership = json['membership'];
  return _string(
    json['team_role'] ??
        (pivot is JsonMap ? pivot['role'] : null) ??
        (membership is JsonMap ? membership['role'] : null) ??
        json['role'],
    fallback: 'member',
  );
}

class AirmiusNamedItem {
  const AirmiusNamedItem({
    required this.id,
    required this.name,
    this.subtitle,
    this.membershipRole,
    this.canManage = false,
  });

  final int id;
  final String name;
  final String? subtitle;
  final String? membershipRole;
  final bool canManage;

  factory AirmiusNamedItem.fromJson(JsonMap json) {
    final membership = json['membership'];
    return AirmiusNamedItem(
      id: _int(json['id']),
      name: _string(json['name'] ?? json['title'], fallback: 'Eintrag'),
      subtitle: _nullableString(
        json['sport_type'] ?? json['city'] ?? json['description'],
      ),
      membershipRole: _nullableString(
        membership is JsonMap ? membership['role'] : json['team_role'],
      ),
      canManage: _bool(json['can_manage']),
    );
  }
}

class AirmiusUserSportProfile {
  const AirmiusUserSportProfile({
    required this.id,
    required this.sportName,
    this.status,
    this.experienceLevel,
    this.metrics = const {},
  });

  final int id;
  final String sportName;
  final String? status;
  final String? experienceLevel;
  final JsonMap metrics;

  factory AirmiusUserSportProfile.fromJson(JsonMap json) {
    final sport = json['sport'];
    return AirmiusUserSportProfile(
      id: _int(json['id']),
      sportName: sport is JsonMap
          ? _string(sport['name'] ?? sport['slug'], fallback: 'Sportart')
          : _string(json['sport'], fallback: 'Sportart'),
      status: _nullableString(json['status']),
      experienceLevel: _nullableString(json['experience_level']),
      metrics: json['performance_metrics'] is JsonMap
          ? json['performance_metrics'] as JsonMap
          : const {},
    );
  }
}

class AirmiusUserBadge {
  const AirmiusUserBadge({
    required this.id,
    required this.name,
    this.description,
    this.icon,
  });

  final int id;
  final String name;
  final String? description;
  final String? icon;

  factory AirmiusUserBadge.fromJson(JsonMap json) => AirmiusUserBadge(
    id: _int(json['id']),
    name: _string(json['name'] ?? json['key'], fallback: 'Badge'),
    description: _nullableString(json['description']),
    icon: _nullableString(json['icon']),
  );
}

class AirmiusGamification {
  const AirmiusGamification({
    required this.xp,
    required this.level,
    required this.nextLevelXp,
    required this.currentLevelXp,
    required this.progress,
    required this.xpToNextLevel,
    required this.earnedToday,
    this.trustScore,
    this.streakDays = 0,
    this.title,
    this.healthLabel,
  });

  final int xp;
  final int level;
  final int nextLevelXp;
  final int currentLevelXp;
  final int progress;
  final int xpToNextLevel;
  final int earnedToday;
  final int? trustScore;
  final int streakDays;
  final String? title;
  final String? healthLabel;

  factory AirmiusGamification.fromJson(JsonMap json) => AirmiusGamification(
    xp: _int(json['xp']),
    level: _int(json['level'], fallback: 1),
    nextLevelXp: _int(json['next_level_xp']),
    currentLevelXp: _int(json['current_level_xp']),
    progress: _int(json['progress']),
    xpToNextLevel: _int(json['xp_to_next_level']),
    earnedToday: _int(json['earned_today']),
    trustScore: json['trust_score'] == null ? null : _int(json['trust_score']),
    streakDays: _int(json['streak_days']),
    title: _nullableString(json['title'] ?? json['rank']),
    healthLabel: _nullableString(json['health_label']),
  );
}

class AirmiusClub {
  const AirmiusClub({
    required this.id,
    this.ownerId,
    required this.name,
    required this.city,
    required this.membersCount,
    required this.teamsCount,
    this.postsCount = 0,
    required this.acceptsMembershipApplications,
    required this.hasPendingMembershipRequest,
    required this.isMember,
    this.teams = const [],
    this.logoUrl,
    this.bannerUrl,
    this.sportType,
    this.postalCode,
    this.country,
    this.street,
    this.houseNumber,
    this.state,
    this.canManage = false,
    this.canEditClubProfile = false,
    this.canEditClubLegal = false,
    this.canEditClubContact = false,
    this.canEditClubBranding = false,
    this.canEditSponsors = false,
    this.canDeleteSponsors = false,
    this.canViewSubscriptions = false,
    this.canEditSubscriptions = false,
    this.canEditJobs = false,
    this.canPublishJobs = false,
    this.canDeleteJobs = false,
    this.canViewRecruiting = false,
    this.canViewCockpit = false,
    this.canEditTeams = false,
    this.canCreateTeamsGlobally = false,
    this.teamCreationDepartments = const [],
    this.canManageMembers = false,
    this.canViewFinance = false,
    this.canViewTrainingExercises = false,
    this.canCreateTrainingExercises = false,
    this.canEditTrainingExercises = false,
    this.canDeleteTrainingExercises = false,
    this.canViewMetadata = false,
    this.canEditMetadata = false,
    this.canEditAnnouncements = false,
    this.canPublishAnnouncements = false,
    this.canDeleteAnnouncements = false,
    this.canEditSurveys = false,
    this.canCloseSurveys = false,
    this.canDeleteSurveys = false,
    this.canDelete = false,
    this.deletionScheduledAt,
    this.management,
    this.membershipStatus,
    this.membershipRole,
    this.membershipEndsOn,
    this.membershipTypeId,
    this.membershipDepartmentId,
    this.membershipChangeRequested = false,
    this.membershipTerminationRequested = false,
    this.pauseRequested = false,
    this.pausedFrom,
    this.pausedUntil,
    this.memberPauseRequestsEnabled = false,
    this.verified = false,
    this.verificationStatus,
    this.description,
    this.admins = const [],
    this.posts = const [],
    this.gamification,
    this.badges = const [],
    this.social = const {},
    this.isListed = true,
    this.teamsAreListed = true,
    this.membersCanPostToClub = true,
    this.membersCanPostToTeams = true,
    this.registryAuthority,
    this.registryNumber,
    this.federationAffiliations = const [],
    this.taxAuthority,
    this.taxNumber,
    this.vatId,
    this.taxStatus,
    this.taxExemptionValidUntil,
    this.contactEmail,
    this.contactPhone,
    this.websiteUrl,
    this.contactDetailsPublic = false,
    this.contactPersons = const [],
    this.brandPrimaryColor,
    this.brandSecondaryColor,
    this.brandAccentColor,
    this.letterheadSettings = const {},
    this.documentTemplates = const [],
    this.openJobs = const [],
  });

  final int id;
  final int? ownerId;
  final String name;
  final String city;
  final int membersCount;
  final int teamsCount;
  final int postsCount;
  final bool acceptsMembershipApplications;
  final bool hasPendingMembershipRequest;
  final bool isMember;
  final List<AirmiusTeam> teams;
  final String? logoUrl;
  final String? bannerUrl;
  final String? sportType;
  final String? postalCode;
  final String? country;
  final String? street;
  final String? houseNumber;
  final String? state;
  final bool canManage;
  final bool canEditClubProfile;
  final bool canEditClubLegal;
  final bool canEditClubContact;
  final bool canEditClubBranding;
  final bool canEditSponsors;
  final bool canDeleteSponsors;
  final bool canViewSubscriptions;
  final bool canEditSubscriptions;
  final bool canEditJobs;
  final bool canPublishJobs;
  final bool canDeleteJobs;
  final bool canViewRecruiting;
  final bool canViewCockpit;
  final bool canEditTeams;
  final bool canCreateTeamsGlobally;
  final List<JsonMap> teamCreationDepartments;
  final bool canManageMembers;
  final bool canViewFinance;
  final bool canViewTrainingExercises;
  final bool canCreateTrainingExercises;
  final bool canEditTrainingExercises;
  final bool canDeleteTrainingExercises;
  final bool canViewMetadata;
  final bool canEditMetadata;
  final bool canEditAnnouncements;
  final bool canPublishAnnouncements;
  final bool canDeleteAnnouncements;
  final bool canEditSurveys;
  final bool canCloseSurveys;
  final bool canDeleteSurveys;
  final bool canDelete;
  final DateTime? deletionScheduledAt;
  final AirmiusClubManagement? management;
  final String? membershipStatus;
  final String? membershipRole;
  final String? membershipEndsOn;
  final int? membershipTypeId;
  final int? membershipDepartmentId;
  final bool membershipChangeRequested;
  final bool membershipTerminationRequested;
  final bool pauseRequested;
  final String? pausedFrom;
  final String? pausedUntil;
  final bool memberPauseRequestsEnabled;
  final bool verified;
  final String? verificationStatus;
  final String? description;
  final List<JsonMap> admins;
  final List<JsonMap> posts;
  final JsonMap? gamification;
  final List<JsonMap> badges;
  final JsonMap social;
  final bool isListed;
  final bool teamsAreListed;
  final bool membersCanPostToClub;
  final bool membersCanPostToTeams;
  final String? registryAuthority;
  final String? registryNumber;
  final List<JsonMap> federationAffiliations;
  final String? taxAuthority;
  final String? taxNumber;
  final String? vatId;
  final String? taxStatus;
  final String? taxExemptionValidUntil;
  final String? contactEmail;
  final String? contactPhone;
  final String? websiteUrl;
  final bool contactDetailsPublic;
  final List<JsonMap> contactPersons;
  final String? brandPrimaryColor;
  final String? brandSecondaryColor;
  final String? brandAccentColor;
  final JsonMap letterheadSettings;
  final List<JsonMap> documentTemplates;
  final List<JsonMap> openJobs;

  bool get canEditAnyClubData =>
      canEditClubProfile ||
      canEditClubLegal ||
      canEditClubContact ||
      canEditClubBranding;

  bool get canAccessMembershipWorkspace => canManageMembers || canViewFinance;

  factory AirmiusClub.fromJson(JsonMap json) {
    final profile = json['profile'] is JsonMap
        ? json['profile'] as JsonMap
        : const <String, dynamic>{};
    final viewer = json['viewer'] is JsonMap
        ? json['viewer'] as JsonMap
        : const <String, dynamic>{};
    final source = <String, dynamic>{...json, ...profile};
    final membership = json['membership'] is JsonMap
        ? json['membership'] as JsonMap
        : const <String, dynamic>{};
    final canManage = _bool(json['can_manage']) || _bool(json['can_update']);
    return AirmiusClub(
      id: _int(json['id']),
      ownerId: (json['owner_id'] ?? profile['owner_id']) == null
          ? null
          : _int(json['owner_id'] ?? profile['owner_id']),
      name: _string(json['name'] ?? json['title'], fallback: 'Verein'),
      city: _string(json['city'] ?? json['subtitle'] ?? json['description']),
      membersCount: _int(
        json['members_count'],
        fallback: _int(json['users_count']),
      ),
      teamsCount: _int(
        json['teams_count'],
        fallback: _clubTeams(json['teams']).length,
      ),
      postsCount: _int(
        json['posts_count'],
        fallback: _int(profile['posts_count']),
      ),
      acceptsMembershipApplications:
          _bool(json['accepts_membership_applications']) ||
          _bool(json['membership_requests_enabled']),
      hasPendingMembershipRequest: _bool(
        json['has_pending_membership_request'],
      ),
      isMember: _bool(json['is_member']),
      teams: _clubTeams(json['teams']),
      logoUrl: _mediaUrl(json['logo_url'] ?? json['logo']),
      bannerUrl: _mediaUrl(
        json['banner_url'] ??
            json['cover_image_url'] ??
            json['cover_image'] ??
            json['cover'],
      ),
      sportType: _nullableString(json['sport_type']),
      postalCode: _nullableString(json['postal_code']),
      country: _nullableString(json['country']),
      street: _nullableString(json['street']),
      houseNumber: _nullableString(json['house_number']),
      state: _nullableString(json['state']),
      canManage: canManage,
      canEditClubProfile: json.containsKey('can_edit_club_profile')
          ? _bool(json['can_edit_club_profile'])
          : canManage,
      canEditClubLegal: json.containsKey('can_edit_club_legal')
          ? _bool(json['can_edit_club_legal'])
          : canManage,
      canEditClubContact: json.containsKey('can_edit_club_contact')
          ? _bool(json['can_edit_club_contact'])
          : canManage,
      canEditClubBranding: json.containsKey('can_edit_club_branding')
          ? _bool(json['can_edit_club_branding'])
          : canManage,
      canEditSponsors: json.containsKey('can_edit_sponsors')
          ? _bool(json['can_edit_sponsors'])
          : canManage,
      canDeleteSponsors: json.containsKey('can_delete_sponsors')
          ? _bool(json['can_delete_sponsors'])
          : canManage,
      canViewSubscriptions: json.containsKey('can_view_subscriptions')
          ? _bool(json['can_view_subscriptions'])
          : canManage,
      canEditSubscriptions: json.containsKey('can_edit_subscriptions')
          ? _bool(json['can_edit_subscriptions'])
          : canManage,
      canEditJobs: json.containsKey('can_edit_jobs')
          ? _bool(json['can_edit_jobs'])
          : canManage,
      canPublishJobs: json.containsKey('can_publish_jobs')
          ? _bool(json['can_publish_jobs'])
          : canManage,
      canDeleteJobs: json.containsKey('can_delete_jobs')
          ? _bool(json['can_delete_jobs'])
          : canManage,
      canViewRecruiting: json.containsKey('can_view_recruiting')
          ? _bool(json['can_view_recruiting'])
          : canManage,
      canViewCockpit: json.containsKey('can_view_cockpit')
          ? _bool(json['can_view_cockpit'])
          : canManage,
      canEditTeams: json.containsKey('can_edit_teams')
          ? _bool(json['can_edit_teams'])
          : viewer.containsKey('can_edit_teams')
          ? _bool(viewer['can_edit_teams'])
          : canManage,
      canCreateTeamsGlobally: json.containsKey('can_create_teams_globally')
          ? _bool(json['can_create_teams_globally'])
          : canManage,
      teamCreationDepartments: _jsonList(json['team_creation_departments']),
      canManageMembers: json.containsKey('can_manage_members')
          ? _bool(json['can_manage_members'])
          : viewer.containsKey('can_manage_members')
          ? _bool(viewer['can_manage_members'])
          : canManage,
      canViewFinance: json.containsKey('can_view_finance')
          ? _bool(json['can_view_finance'])
          : viewer.containsKey('can_view_finance')
          ? _bool(viewer['can_view_finance'])
          : canManage,
      canViewTrainingExercises: json.containsKey('can_view_training_exercises')
          ? _bool(json['can_view_training_exercises'])
          : canManage,
      canCreateTrainingExercises:
          json.containsKey('can_create_training_exercises')
          ? _bool(json['can_create_training_exercises'])
          : canManage,
      canEditTrainingExercises: json.containsKey('can_edit_training_exercises')
          ? _bool(json['can_edit_training_exercises'])
          : canManage,
      canDeleteTrainingExercises:
          json.containsKey('can_delete_training_exercises')
          ? _bool(json['can_delete_training_exercises'])
          : canManage,
      canViewMetadata:
          _bool(json['can_view_metadata']) ||
          _bool(viewer['can_view_metadata']),
      canEditMetadata: json.containsKey('can_edit_metadata')
          ? _bool(json['can_edit_metadata'])
          : viewer.containsKey('can_edit_metadata')
          ? _bool(viewer['can_edit_metadata'])
          : canManage,
      canEditAnnouncements: json.containsKey('can_edit_announcements')
          ? _bool(json['can_edit_announcements'])
          : canManage,
      canPublishAnnouncements: json.containsKey('can_publish_announcements')
          ? _bool(json['can_publish_announcements'])
          : canManage,
      canDeleteAnnouncements: json.containsKey('can_delete_announcements')
          ? _bool(json['can_delete_announcements'])
          : canManage,
      canEditSurveys: json.containsKey('can_edit_surveys')
          ? _bool(json['can_edit_surveys'])
          : canManage,
      canCloseSurveys: json.containsKey('can_close_surveys')
          ? _bool(json['can_close_surveys'])
          : canManage,
      canDeleteSurveys: json.containsKey('can_delete_surveys')
          ? _bool(json['can_delete_surveys'])
          : canManage,
      canDelete: _bool(json['can_delete']) || _bool(json['can_destroy']),
      deletionScheduledAt: DateTime.tryParse(
        '${json['deletion_scheduled_at'] ?? ''}',
      ),
      management: json['management'] is JsonMap
          ? AirmiusClubManagement.fromJson(json['management'] as JsonMap)
          : null,
      membershipStatus: _nullableString(membership['status']),
      membershipRole: _nullableString(membership['role']),
      membershipEndsOn: _nullableString(membership['membership_ends_on']),
      membershipTypeId: _nullableInt(membership['club_membership_type_id']),
      membershipDepartmentId: _nullableInt(membership['club_department_id']),
      membershipChangeRequested: _bool(membership['change_requested']),
      membershipTerminationRequested: _bool(
        membership['termination_requested'],
      ),
      pauseRequested: _bool(membership['pause_requested']),
      pausedFrom: _nullableString(membership['paused_from']),
      pausedUntil: _nullableString(membership['paused_until']),
      memberPauseRequestsEnabled: _bool(json['member_pause_requests_enabled']),
      verified:
          _string(json['verification_status']) == 'verified' ||
          _bool(json['is_official']),
      verificationStatus: _nullableString(json['verification_status']),
      description: _nullableString(source['description']),
      admins: _jsonList(source['admins']),
      posts: _jsonList(json['posts']),
      gamification: source['gamification'] is JsonMap
          ? source['gamification'] as JsonMap
          : null,
      badges: _jsonList(source['badges']),
      social: viewer['social'] is JsonMap
          ? viewer['social'] as JsonMap
          : json['social'] is JsonMap
          ? json['social'] as JsonMap
          : const <String, dynamic>{},
      isListed: json['is_listed'] == null ? true : _bool(json['is_listed']),
      teamsAreListed: json['teams_are_listed'] == null
          ? true
          : _bool(json['teams_are_listed']),
      membersCanPostToClub: json['members_can_post_to_club'] == null
          ? true
          : _bool(json['members_can_post_to_club']),
      membersCanPostToTeams: json['members_can_post_to_teams'] == null
          ? true
          : _bool(json['members_can_post_to_teams']),
      registryAuthority: _nullableString(json['registry_authority']),
      registryNumber: _nullableString(json['registry_number']),
      federationAffiliations: _jsonList(json['federation_affiliations']),
      taxAuthority: _nullableString(json['tax_authority']),
      taxNumber: _nullableString(json['tax_number']),
      vatId: _nullableString(json['vat_id']),
      taxStatus: _nullableString(json['tax_status']),
      taxExemptionValidUntil: _nullableString(
        json['tax_exemption_valid_until'],
      ),
      contactEmail: _nullableString(json['contact_email']),
      contactPhone: _nullableString(json['contact_phone']),
      websiteUrl: _nullableString(json['website_url']),
      contactDetailsPublic: _bool(json['contact_details_public']),
      contactPersons: _jsonList(json['contact_persons']),
      brandPrimaryColor: _nullableString(json['brand_primary_color']),
      brandSecondaryColor: _nullableString(json['brand_secondary_color']),
      brandAccentColor: _nullableString(json['brand_accent_color']),
      letterheadSettings: json['letterhead_settings'] is JsonMap
          ? json['letterhead_settings'] as JsonMap
          : const {},
      documentTemplates: _jsonList(json['document_templates']),
      openJobs: _jsonList(json['open_jobs']),
    );
  }
}

class AirmiusClubSurveyOption {
  const AirmiusClubSurveyOption({
    required this.id,
    required this.label,
    this.sortOrder = 0,
    this.votes = 0,
  });

  final int id;
  final String label;
  final int sortOrder;
  final int votes;

  factory AirmiusClubSurveyOption.fromJson(JsonMap json) =>
      AirmiusClubSurveyOption(
        id: _int(json['id']),
        label: _string(json['label'], fallback: 'Option'),
        sortOrder: _int(json['sort_order']),
        votes: _int(json['votes']),
      );
}

class AirmiusClubSurvey {
  const AirmiusClubSurvey({
    required this.id,
    required this.clubId,
    required this.question,
    this.description,
    this.status = 'open',
    this.audienceType = 'all_members',
    this.teamId,
    this.teamName,
    this.quorum,
    this.eligibleVoters = 0,
    this.quorumReached = true,
    this.closesAt,
    this.myOptionId,
    this.votes = 0,
    this.canManage = false,
    this.canEdit = false,
    this.canClose = false,
    this.canDelete = false,
    this.options = const [],
  });

  final int id;
  final int clubId;
  final String question;
  final String? description;
  final String status;
  final String audienceType;
  final int? teamId;
  final String? teamName;
  final int? quorum;
  final int eligibleVoters;
  final bool quorumReached;
  final DateTime? closesAt;
  final int? myOptionId;
  final int votes;
  final bool canManage;
  final bool canEdit;
  final bool canClose;
  final bool canDelete;
  final List<AirmiusClubSurveyOption> options;

  bool get isOpen =>
      status == 'open' &&
      (closesAt == null || DateTime.now().isBefore(closesAt!));

  factory AirmiusClubSurvey.fromJson(JsonMap json) {
    final team = json['team'];
    final rawOptions = json['options'];
    final canManage = _bool(json['can_manage']);
    return AirmiusClubSurvey(
      id: _int(json['id']),
      clubId: _int(json['club_id']),
      question: _string(json['question']),
      description: _nullableString(json['description']),
      status: _string(json['status'], fallback: 'open'),
      audienceType: _string(json['audience_type'], fallback: 'all_members'),
      teamId: json['team_id'] == null ? null : _int(json['team_id']),
      teamName: team is JsonMap ? _nullableString(team['name']) : null,
      quorum: json['quorum'] == null ? null : _int(json['quorum']),
      eligibleVoters: _int(json['eligible_voters']),
      quorumReached: _bool(json['quorum_reached']),
      closesAt: _optionalDate(json['closes_at']),
      myOptionId: json['my_option_id'] == null
          ? null
          : _int(json['my_option_id']),
      votes: _int(json['votes']),
      canManage: canManage,
      canEdit: json.containsKey('can_edit')
          ? _bool(json['can_edit'])
          : canManage,
      canClose: json.containsKey('can_close')
          ? _bool(json['can_close'])
          : canManage,
      canDelete: json.containsKey('can_delete')
          ? _bool(json['can_delete'])
          : canManage,
      options: rawOptions is List
          ? rawOptions
                .whereType<JsonMap>()
                .map(AirmiusClubSurveyOption.fromJson)
                .toList()
          : const [],
    );
  }
}

class AirmiusClubAnnouncement {
  const AirmiusClubAnnouncement({
    required this.id,
    required this.clubId,
    required this.title,
    required this.body,
    required this.audienceType,
    this.teamId,
    this.teamName,
    this.authorName,
    this.publishedAt,
    this.readByMe = false,
    this.readCount = 0,
    this.canManage = false,
    this.canEdit = false,
    this.canPublish = false,
    this.canDelete = false,
    this.publicationStatus = 'published',
  });

  final int id;
  final int clubId;
  final String title;
  final String body;
  final String audienceType;
  final int? teamId;
  final String? teamName;
  final String? authorName;
  final DateTime? publishedAt;
  final bool readByMe;
  final int readCount;
  final bool canManage;
  final bool canEdit;
  final bool canPublish;
  final bool canDelete;
  final String publicationStatus;

  factory AirmiusClubAnnouncement.fromJson(JsonMap json) {
    final team = json['team'];
    final user = json['user'];
    final canManage = _bool(json['can_manage']);
    return AirmiusClubAnnouncement(
      id: _int(json['id']),
      clubId: _int(json['club_id']),
      title: _string(json['title']),
      body: _string(json['body']),
      audienceType: _string(json['audience_type'], fallback: 'all_members'),
      teamId: json['team_id'] == null ? null : _int(json['team_id']),
      teamName: team is JsonMap ? _nullableString(team['name']) : null,
      authorName: user is JsonMap ? _nullableString(user['name']) : null,
      publishedAt: _optionalDate(json['published_at']),
      readByMe: _bool(json['read_by_me']),
      readCount: _int(json['read_count']),
      canManage: canManage,
      canEdit: json.containsKey('can_edit')
          ? _bool(json['can_edit'])
          : canManage,
      canPublish: json.containsKey('can_publish')
          ? _bool(json['can_publish'])
          : canManage,
      canDelete: json.containsKey('can_delete')
          ? _bool(json['can_delete'])
          : canManage,
      publicationStatus: _string(
        json['publication_status'],
        fallback: 'published',
      ),
    );
  }
}

class AirmiusSupportTicket {
  const AirmiusSupportTicket({
    required this.id,
    required this.subject,
    required this.message,
    required this.category,
    required this.priority,
    required this.status,
    this.createdAt,
    this.updatedAt,
    this.lastReplyAt,
    this.responseDueAt,
    this.firstResponseAt,
    this.dueAt,
    this.escalatedAt,
    this.resolvedAt,
    this.adminNote,
    this.requesterName,
    this.requesterEmail,
    this.assigneeName,
    this.clubId,
    this.clubName,
    this.slaState = 'on_track',
    this.responseOverdue = false,
    this.resolutionOverdue = false,
    this.isOverdue = false,
  });

  final int id;
  final String subject;
  final String message;
  final String category;
  final String priority;
  final String status;
  final DateTime? createdAt;
  final DateTime? updatedAt;
  final DateTime? lastReplyAt;
  final DateTime? responseDueAt;
  final DateTime? firstResponseAt;
  final DateTime? dueAt;
  final DateTime? escalatedAt;
  final DateTime? resolvedAt;
  final String? adminNote;
  final String? requesterName;
  final String? requesterEmail;
  final String? assigneeName;
  final int? clubId;
  final String? clubName;
  final String slaState;
  final bool responseOverdue;
  final bool resolutionOverdue;
  final bool isOverdue;

  factory AirmiusSupportTicket.fromJson(JsonMap json) {
    final club = json['club'];
    final sla = json['sla'];

    return AirmiusSupportTicket(
      id: _int(json['id']),
      subject: _string(json['subject']),
      message: _string(json['message']),
      category: _string(json['category'], fallback: 'technical'),
      priority: _string(json['priority'], fallback: 'normal'),
      status: _string(json['status'], fallback: 'open'),
      createdAt: _optionalDate(json['created_at']),
      updatedAt: _optionalDate(json['updated_at']),
      lastReplyAt: _optionalDate(json['last_reply_at']),
      responseDueAt: _optionalDate(json['response_due_at']),
      firstResponseAt: _optionalDate(json['first_response_at']),
      dueAt: _optionalDate(json['due_at']),
      escalatedAt: _optionalDate(json['escalated_at']),
      resolvedAt: _optionalDate(json['resolved_at']),
      adminNote: _nullableString(json['admin_note']),
      requesterName: json['requester'] is JsonMap
          ? _nullableString((json['requester'] as JsonMap)['name'])
          : null,
      requesterEmail: json['requester'] is JsonMap
          ? _nullableString((json['requester'] as JsonMap)['email'])
          : null,
      assigneeName: json['assignee'] is JsonMap
          ? _nullableString((json['assignee'] as JsonMap)['name'])
          : null,
      clubId: club is JsonMap ? _nullableInt(club['id']) : null,
      clubName: club is JsonMap ? _nullableString(club['name']) : null,
      slaState: sla is JsonMap
          ? _string(sla['state'], fallback: 'on_track')
          : 'on_track',
      responseOverdue: sla is JsonMap && _bool(sla['response_overdue']),
      resolutionOverdue: sla is JsonMap && _bool(sla['resolution_overdue']),
      isOverdue:
          (sla is JsonMap && _bool(sla['is_overdue'])) ||
          _bool(json['is_overdue']),
    );
  }
}

List<AirmiusTeam> _clubTeams(Object? value) => value is List
    ? value.whereType<JsonMap>().map(AirmiusTeam.fromJson).toList()
    : const [];

class AirmiusClubManagement {
  const AirmiusClubManagement({
    required this.canManage,
    this.permissions = const {},
    this.summary = const {},
    this.settings = const {},
    this.subscription = const {},
    this.capabilities = const {},
    this.members = const [],
    this.externalMembers = const [],
    this.membershipRequests = const [],
    this.pendingTeamJoinRequests = const [],
    this.membershipTypes = const [],
    this.contributionRules = const [],
    this.contributionPolicyDocuments = const [],
    this.invoices = const [],
    this.payments = const [],
    this.financeEntries = const [],
    this.receiptUploads = const [],
    this.bankTransactions = const [],
    this.membershipStatuses = const [],
    this.contributionIntervals = const [],
    this.contributionRuleTypes = const [],
    this.contributionDiscountOperators = const [],
    this.contributionProrationPolicies = const [],
    this.teamRoles = const [],
    this.teams = const [],
    this.auditLogs = const [],
    this.memberTimelineEntries = const [],
    this.onboarding = const {},
  });

  final bool canManage;
  final JsonMap permissions;
  final JsonMap summary;
  final JsonMap settings;
  final JsonMap subscription;
  final JsonMap capabilities;
  final List<AirmiusClubMember> members;
  final List<JsonMap> externalMembers;
  final List<AirmiusClubMembershipRequest> membershipRequests;
  final List<JsonMap> pendingTeamJoinRequests;
  final List<JsonMap> membershipTypes;
  final List<JsonMap> contributionRules;
  final List<JsonMap> contributionPolicyDocuments;
  final List<JsonMap> invoices;
  final List<JsonMap> payments;
  final List<JsonMap> financeEntries;
  final List<JsonMap> receiptUploads;
  final List<JsonMap> bankTransactions;
  final List<String> membershipStatuses;
  final List<String> contributionIntervals;
  final List<JsonMap> contributionRuleTypes;
  final List<JsonMap> contributionDiscountOperators;
  final List<JsonMap> contributionProrationPolicies;
  final List<String> teamRoles;
  final List<AirmiusTeam> teams;
  final List<JsonMap> auditLogs;
  final List<JsonMap> memberTimelineEntries;
  final JsonMap onboarding;

  bool get canManageFinance {
    final explicit = permissions['can_manage_finance'];
    if (explicit != null) return _bool(explicit);
    final effective = permissions['effective'];
    return effective is JsonMap
        ? _bool(effective['finance.manage'])
        : canManage;
  }

  bool get canManageMembers {
    final explicit = permissions['can_manage_members'];
    if (explicit != null) return _bool(explicit);
    final effective = permissions['effective'];
    return effective is JsonMap
        ? _bool(effective['members.manage'])
        : canManage;
  }

  bool get canEditMemberPermissions =>
      permissions['can_edit_member_permissions'] == null
      ? canManage
      : _bool(permissions['can_edit_member_permissions']);

  int get pendingMembershipRequestsCount => _int(
    summary['pending_membership_requests_count'],
    fallback: membershipRequests
        .where((request) => request.status == 'pending')
        .length,
  );

  int get pendingTeamJoinRequestsCount => _int(
    summary['pending_team_join_requests_count'],
    fallback: pendingTeamJoinRequests.length,
  );

  int get activeMembersCount =>
      _int(summary['active_members_count'], fallback: members.length);

  int get linkedPeopleCount => _int(
    summary['linked_people_count'],
    fallback: members.length + externalMembers.length,
  );

  double get openInvoiceAmount => _double(summary['open_invoice_amount']);

  int get openInvoicesCount =>
      _int(summary['open_invoices_count'], fallback: invoices.length);

  int get unreadAnnouncementsCount =>
      _int(summary['unread_announcements_count']);

  int? get nextEventId =>
      summary['next_event_id'] == null ? null : _int(summary['next_event_id']);

  String? get nextEventTitle => _nullableString(summary['next_event_title']);

  DateTime? get nextEventStartsAt =>
      _optionalDate(summary['next_event_starts_at']);

  int get sepaReadyMembersCount => _int(summary['sepa_ready_members_count']);

  double get recurringContributionTotal =>
      _double(summary['recurring_contribution_total']);

  double get cashBalance => _double(summary['cash_balance']);

  double get bankBalance => _double(summary['bank_balance']);

  double get unassignedBalance => _double(summary['unassigned_balance']);

  double get totalBalance => _double(summary['total_balance']);

  double get incomeTotal => _double(summary['income_total']);

  double get expenseTotal => _double(summary['expense_total']);

  bool get hasFinancePeriodTotals =>
      summary.containsKey('income_period_total') &&
      summary.containsKey('expense_period_total');

  double get incomePeriodTotal =>
      _double(summary['income_period_total'], fallback: incomeTotal);

  double get expensePeriodTotal =>
      _double(summary['expense_period_total'], fallback: expenseTotal);

  String get financePeriodLabel =>
      _string(summary['finance_period_label'], fallback: 'Dieses Jahr');

  factory AirmiusClubManagement.fromJson(JsonMap json) => AirmiusClubManagement(
    canManage: _bool(json['can_manage']),
    permissions: json['permissions'] is JsonMap
        ? json['permissions'] as JsonMap
        : const {},
    summary: json['summary'] is JsonMap ? json['summary'] as JsonMap : const {},
    settings: json['settings'] is JsonMap
        ? json['settings'] as JsonMap
        : const {},
    subscription: json['subscription'] is JsonMap
        ? json['subscription'] as JsonMap
        : const {},
    capabilities: json['capabilities'] is JsonMap
        ? json['capabilities'] as JsonMap
        : const {},
    members: _jsonList(
      json['members'],
    ).map(AirmiusClubMember.fromJson).toList(),
    externalMembers: _jsonList(json['external_members']),
    membershipRequests: _jsonList(
      json['membership_requests'] ?? json['club_requests'],
    ).map(AirmiusClubMembershipRequest.fromJson).toList(),
    pendingTeamJoinRequests: _jsonList(
      json['pending_team_join_requests'] ?? json['pending_requests'],
    ),
    membershipTypes: _jsonList(json['membership_types']),
    contributionRules: _jsonList(json['contribution_rules']),
    contributionPolicyDocuments: _jsonList(
      json['contribution_policy_documents'],
    ),
    invoices: _jsonList(json['invoices']),
    payments: _jsonList(json['payments']),
    financeEntries: _jsonList(json['finance_entries']),
    receiptUploads: _jsonList(json['receipt_uploads']),
    bankTransactions: _jsonList(json['bank_transactions']),
    membershipStatuses: _stringList(json['membership_statuses']),
    contributionIntervals: _stringList(json['contribution_intervals']),
    contributionRuleTypes: _jsonList(json['contribution_rule_types']),
    contributionDiscountOperators: _jsonList(
      json['contribution_discount_operators'],
    ),
    contributionProrationPolicies: _jsonList(
      json['contribution_proration_policies'],
    ),
    teamRoles: _stringList(json['team_roles']),
    teams: _clubTeams(json['teams']),
    auditLogs: _jsonList(json['audit_logs']),
    memberTimelineEntries: _jsonList(json['member_timeline_entries']),
    onboarding: json['onboarding'] is JsonMap
        ? json['onboarding'] as JsonMap
        : const {},
  );
}

class AirmiusClubMember {
  const AirmiusClubMember({
    required this.id,
    required this.name,
    required this.email,
    this.phone,
    this.country,
    this.street,
    this.houseNumber,
    this.postalCode,
    this.city,
    this.licenseNumber,
    this.licenseValidUntil,
    this.avatarUrl,
    this.membership = const {},
    this.invoicesCount = 0,
    this.paymentsCount = 0,
  });

  final int id;
  final String name;
  final String email;
  final String? phone;
  final String? country;
  final String? street;
  final String? houseNumber;
  final String? postalCode;
  final String? city;
  final String? licenseNumber;
  final String? licenseValidUntil;
  final String? avatarUrl;
  final JsonMap membership;
  final int invoicesCount;
  final int paymentsCount;

  String? get role => _nullableString(membership['role']);

  String? get status => _nullableString(membership['status']);

  String? get memberNumber => _nullableString(membership['member_number']);

  factory AirmiusClubMember.fromJson(JsonMap json) => AirmiusClubMember(
    id: _int(json['id']),
    name: _string(json['name'], fallback: 'Mitglied'),
    email: _string(json['email']),
    phone: _nullableString(json['phone']),
    country: _nullableString(json['country']),
    street: _nullableString(json['street']),
    houseNumber: _nullableString(json['house_number']),
    postalCode: _nullableString(json['postal_code']),
    city: _nullableString(json['city']),
    licenseNumber: _nullableString(json['athlete_license_number']),
    licenseValidUntil: _nullableString(json['athlete_license_valid_until']),
    avatarUrl: _mediaUrl(
      json['profile_photo_url'] ?? json['profile_photo_thumb'],
    ),
    membership: json['membership'] is JsonMap
        ? json['membership'] as JsonMap
        : const {},
    invoicesCount: _int(json['invoices_count']),
    paymentsCount: _int(json['payments_count']),
  );
}

class AirmiusTeam {
  const AirmiusTeam({
    required this.id,
    required this.clubId,
    required this.name,
    this.clubName,
    this.clubOwnerId,
    this.clubIsMember = false,
    this.clubCanManage = false,
    this.clubMembershipTerminationRequested = false,
    this.description,
    this.sportType,
    this.sportYearPeriodId,
    this.sportYearPeriodName,
    this.ageGroup,
    this.visibility,
    this.logoUrl,
    this.attendanceStats,
    this.users = const [],
    this.canManage = false,
    this.canManageMetadata = false,
    this.canDelete = false,
    this.viewerIsMember = false,
    this.canRequestJoin = false,
    this.viewerPendingJoinRequestId,
    this.pendingJoinRequests = const [],
    this.usersCount,
    this.eventsCount,
    this.memberInvitationRemainingToday,
    this.memberInvitationDailyLimit,
  });

  final int id;
  final int clubId;
  final String name;
  final String? clubName;
  final int? clubOwnerId;
  final bool clubIsMember;
  final bool clubCanManage;
  final bool clubMembershipTerminationRequested;
  final String? description;
  final String? sportType;
  final int? sportYearPeriodId;
  final String? sportYearPeriodName;
  final String? ageGroup;
  final String? visibility;
  final String? logoUrl;
  final AirmiusTeamAttendanceStats? attendanceStats;
  final List<AirmiusUser> users;
  final bool canManage;
  final bool canManageMetadata;
  final bool canDelete;
  final bool viewerIsMember;
  final bool canRequestJoin;
  final int? viewerPendingJoinRequestId;
  final List<AirmiusTeamJoinRequest> pendingJoinRequests;
  final int? usersCount;
  final int? eventsCount;
  final int? memberInvitationRemainingToday;
  final int? memberInvitationDailyLimit;

  factory AirmiusTeam.fromJson(JsonMap json) {
    final club = json['club'];
    final clubCapabilities = club is JsonMap
        ? club['subscription_capabilities'] is JsonMap
              ? club['subscription_capabilities'] as JsonMap
              : const {}
        : const {};
    final clubMembership = club is JsonMap && club['membership'] is JsonMap
        ? club['membership'] as JsonMap
        : const {};
    return AirmiusTeam(
      id: _int(json['id']),
      clubId: _int(json['club_id']),
      name: _string(json['name'] ?? json['title'], fallback: 'Team'),
      clubName:
          (club is JsonMap ? _nullableString(club['name']) : null) ??
          _nullableString(json['club_name']),
      clubOwnerId: club is JsonMap ? _nullableInt(club['owner_id']) : null,
      clubIsMember: club is JsonMap ? _bool(club['is_member']) : false,
      clubCanManage: club is JsonMap ? _bool(club['can_manage']) : false,
      clubMembershipTerminationRequested: _bool(
        clubMembership['termination_requested'],
      ),
      description: _nullableString(json['description'] ?? json['subtitle']),
      sportType: _nullableString(json['sport_type']),
      sportYearPeriodId: _nullableInt(json['sport_year_period_id']),
      sportYearPeriodName: json['sport_year_period'] is JsonMap
          ? _nullableString((json['sport_year_period'] as JsonMap)['name'])
          : null,
      ageGroup: _nullableString(json['age_group']),
      visibility: _nullableString(json['visibility']),
      logoUrl: _mediaUrl(json['logo_url'] ?? json['logo']),
      attendanceStats: json['attendance_stats'] is JsonMap
          ? AirmiusTeamAttendanceStats.fromJson(
              json['attendance_stats'] as JsonMap,
            )
          : null,
      users: _jsonList(json['users']).map(AirmiusUser.fromJson).toList(),
      canManage: _bool(json['can_manage']) || _bool(json['can_update']),
      canManageMetadata: _bool(json['can_manage_metadata']),
      canDelete: _bool(json['can_delete']) || _bool(json['can_destroy']),
      viewerIsMember: _bool(json['viewer_is_member']),
      canRequestJoin: _bool(json['can_request_join']),
      viewerPendingJoinRequestId: _nullableInt(
        json['viewer_pending_join_request_id'],
      ),
      pendingJoinRequests: _jsonList(
        json['pending_join_requests'] ??
            json['pending_team_join_requests'] ??
            json['pending_requests'],
      ).map(AirmiusTeamJoinRequest.fromJson).toList(),
      usersCount: json.containsKey('users_count')
          ? _int(json['users_count'])
          : null,
      eventsCount: json.containsKey('events_count')
          ? _int(json['events_count'])
          : null,
      memberInvitationRemainingToday: _nullableIntFromJson(
        clubCapabilities['member_invitation_remaining_today'],
      ),
      memberInvitationDailyLimit: _nullableIntFromJson(
        clubCapabilities['member_invitation_daily_limit'],
      ),
    );
  }
}

int? _nullableIntFromJson(Object? value) {
  if (value is int) return value;
  if (value is num) return value.toInt();
  if (value is String) return int.tryParse(value);
  return null;
}

class AirmiusTeamJoinRequest {
  const AirmiusTeamJoinRequest({
    required this.id,
    required this.teamId,
    required this.userId,
    required this.name,
    required this.email,
    this.status = 'pending',
    this.roleHint,
    this.requestedAt,
  });

  final int id;
  final int teamId;
  final int userId;
  final String name;
  final String email;
  final String status;
  final String? roleHint;
  final String? requestedAt;

  factory AirmiusTeamJoinRequest.fromJson(JsonMap json) {
    final user = json['user'] is JsonMap
        ? json['user'] as JsonMap
        : const <String, dynamic>{};
    return AirmiusTeamJoinRequest(
      id: _int(json['id']),
      teamId: _int(json['team_id']),
      userId: _int(json['user_id'] ?? user['id']),
      name: _string(
        json['name'] ?? json['user_name'] ?? user['name'],
        fallback: 'Mitglied',
      ),
      email: _string(json['email'] ?? json['user_email'] ?? user['email']),
      status: _string(json['status'], fallback: 'pending'),
      roleHint: _nullableString(json['role'] ?? json['role_hint']),
      requestedAt: _nullableString(json['created_at'] ?? json['requested_at']),
    );
  }
}

class AirmiusTeamInvitation {
  const AirmiusTeamInvitation({
    required this.id,
    required this.role,
    required this.status,
    required this.teamId,
    required this.teamName,
    this.clubId,
    this.clubName,
    this.sportType,
    this.inviterName,
    this.inviterEmail,
    this.createdAt,
  });

  final int id;
  final String role;
  final String status;
  final int teamId;
  final int? clubId;
  final String teamName;
  final String? clubName;
  final String? sportType;
  final String? inviterName;
  final String? inviterEmail;
  final String? createdAt;

  factory AirmiusTeamInvitation.fromJson(JsonMap json) {
    final team = json['team'] is JsonMap
        ? json['team'] as JsonMap
        : const <String, dynamic>{};
    final club = team['club'] is JsonMap
        ? team['club'] as JsonMap
        : const <String, dynamic>{};
    final inviter = json['inviter'] is JsonMap
        ? json['inviter'] as JsonMap
        : const <String, dynamic>{};

    return AirmiusTeamInvitation(
      id: _int(json['id']),
      role: _string(json['role'], fallback: 'Player'),
      status: _string(json['status'], fallback: 'pending'),
      teamId: _int(json['team_id'] ?? team['id']),
      clubId: _nullableInt(json['club_id'] ?? team['club_id'] ?? club['id']),
      teamName: _string(json['team_name'] ?? team['name'], fallback: 'Team'),
      clubName: _nullableString(json['club_name'] ?? club['name']),
      sportType: _nullableString(json['sport_type'] ?? team['sport_type']),
      inviterName: _nullableString(json['inviter_name'] ?? inviter['name']),
      inviterEmail: _nullableString(json['inviter_email'] ?? inviter['email']),
      createdAt: _nullableString(json['created_at'] ?? json['invited_at']),
    );
  }
}

class AirmiusTeamAttendanceStats {
  const AirmiusTeamAttendanceStats({
    required this.trainingsTotal,
    required this.membersTotal,
    required this.members,
  });

  final int trainingsTotal;
  final int membersTotal;
  final List<AirmiusTeamAttendanceMember> members;

  factory AirmiusTeamAttendanceStats.fromJson(JsonMap json) =>
      AirmiusTeamAttendanceStats(
        trainingsTotal: _int(json['trainings_total']),
        membersTotal: _int(json['members_total']),
        members: _jsonList(
          json['members'],
        ).map(AirmiusTeamAttendanceMember.fromJson).toList(),
      );
}

class AirmiusTeamAttendanceMember {
  const AirmiusTeamAttendanceMember({
    required this.userId,
    required this.name,
    required this.trainingsTotal,
    required this.attended,
    required this.yes,
    required this.late,
    required this.maybe,
    required this.no,
    required this.noResponse,
    required this.attendanceRate,
    this.email,
    this.avatarUrl,
  });

  final int userId;
  final String name;
  final String? email;
  final String? avatarUrl;
  final int trainingsTotal;
  final int attended;
  final int yes;
  final int late;
  final int maybe;
  final int no;
  final int noResponse;
  final double attendanceRate;

  factory AirmiusTeamAttendanceMember.fromJson(JsonMap json) =>
      AirmiusTeamAttendanceMember(
        userId: _int(json['user_id'] ?? json['id']),
        name: _string(json['name'], fallback: 'Mitglied'),
        email: _nullableString(json['email']),
        avatarUrl: _mediaUrl(
          json['profile_photo_url'] ??
              json['avatar_url'] ??
              json['profile_photo_path'],
        ),
        trainingsTotal: _int(json['trainings_total']),
        attended: _int(json['attended']),
        yes: _int(json['yes']),
        late: _int(json['late']),
        maybe: _int(json['maybe']),
        no: _int(json['no']),
        noResponse: _int(json['no_response']),
        attendanceRate: _double(json['attendance_rate']),
      );
}

class AirmiusSport {
  const AirmiusSport({
    required this.id,
    required this.name,
    required this.slug,
    this.skills = const [],
  });

  final int id;
  final String name;
  final String slug;
  final List<AirmiusSportSkill> skills;

  factory AirmiusSport.fromJson(JsonMap json) {
    final skills = json['skills'];
    return AirmiusSport(
      id: _int(json['id']),
      name: _string(json['name'], fallback: 'Sportart'),
      slug: _string(json['slug']),
      skills: skills is List
          ? skills.whereType<JsonMap>().map(AirmiusSportSkill.fromJson).toList()
          : const [],
    );
  }
}

class AirmiusSportSkill {
  const AirmiusSportSkill({required this.id, required this.name});

  final int id;
  final String name;

  factory AirmiusSportSkill.fromJson(JsonMap json) => AirmiusSportSkill(
    id: _int(json['id']),
    name: _string(json['name'], fallback: 'Skill'),
  );
}

class AirmiusMembershipApplication {
  const AirmiusMembershipApplication({
    required this.id,
    required this.clubId,
    required this.status,
    required this.submittedAt,
    this.withdrawnAt,
  });

  final int id;
  final int clubId;
  final String status;
  final DateTime submittedAt;
  final DateTime? withdrawnAt;

  factory AirmiusMembershipApplication.fromJson(JsonMap json) =>
      AirmiusMembershipApplication(
        id: _int(json['id']),
        clubId: _int(json['club_id']),
        status: _string(json['status'], fallback: 'pending'),
        submittedAt: _date(json['submitted_at'] ?? json['created_at']),
        withdrawnAt: json['withdrawn_at'] == null
            ? null
            : _date(json['withdrawn_at']),
      );
}

class AirmiusClubMembershipRequest {
  const AirmiusClubMembershipRequest({
    required this.id,
    required this.clubId,
    required this.userId,
    required this.type,
    required this.status,
    required this.createdAt,
    this.message,
    this.informationRequestMessage,
    this.informationRequestedAt,
    this.applicantResponseMessage,
    this.applicantRespondedAt,
    this.waitlistedAt,
    this.reviewNote,
    this.applicantName,
    this.applicantEmail,
    this.clubName,
    this.membershipTypeName,
    this.preferredPaymentMethod,
    this.requestedBillingInterval,
    this.previewAmount,
    this.previewBaseAmount,
    this.previewDiscountAmount,
    this.previewRuleType,
    this.previewInterval,
    this.applicationData = const {},
    this.acceptedDocuments = const [],
    this.consent,
  });

  factory AirmiusClubMembershipRequest.fromJson(JsonMap json) {
    final user = json['user'];
    final club = json['club'];
    final membershipType = json['membership_type'];
    final applicationData = json['application_data'];
    final acceptedDocuments = json['accepted_documents'];
    final consent = json['consent'];

    return AirmiusClubMembershipRequest(
      id: _int(json['id']),
      clubId: _int(json['club_id']),
      userId: _int(json['user_id']),
      type: _string(json['type'], fallback: 'membership'),
      status: _string(json['status'], fallback: 'pending'),
      message: _nullableString(json['message']),
      informationRequestMessage: _nullableString(
        json['information_request_message'],
      ),
      informationRequestedAt: json['information_requested_at'] == null
          ? null
          : _date(json['information_requested_at']),
      applicantResponseMessage: _nullableString(
        json['applicant_response_message'],
      ),
      applicantRespondedAt: json['applicant_responded_at'] == null
          ? null
          : _date(json['applicant_responded_at']),
      waitlistedAt: json['waitlisted_at'] == null
          ? null
          : _date(json['waitlisted_at']),
      reviewNote: _nullableString(json['review_note']),
      applicantName:
          (user is JsonMap ? _nullableString(user['name']) : null) ??
          _nullableString(json['applicant_name']),
      applicantEmail:
          (user is JsonMap ? _nullableString(user['email']) : null) ??
          _nullableString(json['applicant_email']),
      clubName:
          (club is JsonMap ? _nullableString(club['name']) : null) ??
          _nullableString(json['club_name']),
      membershipTypeName: membershipType is JsonMap
          ? _nullableString(membershipType['name'])
          : _nullableString(json['membership_type_name']),
      preferredPaymentMethod: _nullableString(json['preferred_payment_method']),
      requestedBillingInterval: _nullableString(
        json['requested_billing_interval'],
      ),
      previewAmount: _nullableString(json['preview_amount']),
      previewBaseAmount: _nullableString(json['preview_base_amount']),
      previewDiscountAmount: _nullableString(json['preview_discount_amount']),
      previewRuleType: _nullableString(json['preview_rule_type']),
      previewInterval: _nullableString(json['preview_interval']),
      applicationData: applicationData is JsonMap ? applicationData : const {},
      acceptedDocuments: acceptedDocuments is List
          ? acceptedDocuments.map((item) {
              if (item is JsonMap) {
                return _string(
                  item['title'] ?? item['id'],
                  fallback: 'Dokument',
                );
              }
              return '$item';
            }).toList()
          : const [],
      consent: consent is JsonMap
          ? AirmiusMembershipConsent.fromJson(consent)
          : null,
      createdAt: _date(json['created_at']),
    );
  }

  final int id;
  final int clubId;
  final int userId;
  final String type;
  final String status;
  final String? message;
  final String? informationRequestMessage;
  final DateTime? informationRequestedAt;
  final String? applicantResponseMessage;
  final DateTime? applicantRespondedAt;
  final DateTime? waitlistedAt;
  final String? reviewNote;
  final String? applicantName;
  final String? applicantEmail;
  final String? clubName;
  final String? membershipTypeName;
  final String? preferredPaymentMethod;
  final String? requestedBillingInterval;
  final String? previewAmount;
  final String? previewBaseAmount;
  final String? previewDiscountAmount;
  final String? previewRuleType;
  final String? previewInterval;
  final JsonMap applicationData;
  final List<String> acceptedDocuments;
  final AirmiusMembershipConsent? consent;
  final DateTime createdAt;
}

class AirmiusClubMembershipProspect {
  const AirmiusClubMembershipProspect({
    required this.id,
    required this.clubId,
    required this.name,
    required this.status,
    required this.createdAt,
    this.email,
    this.phone,
    this.source,
    this.trialAt,
    this.trialOutcome,
    this.notes,
    this.teamId,
    this.teamName,
    this.membershipTypeId,
    this.membershipTypeName,
    this.membershipRequestId,
    this.convertedAt,
  });

  factory AirmiusClubMembershipProspect.fromJson(JsonMap json) {
    final team = json['team'];
    final membershipType = json['membership_type'];
    return AirmiusClubMembershipProspect(
      id: _int(json['id']),
      clubId: _int(json['club_id']),
      name: _string(json['name']),
      status: _string(json['status'], fallback: 'prospect'),
      email: _nullableString(json['email']),
      phone: _nullableString(json['phone']),
      source: _nullableString(json['source']),
      trialAt: json['trial_at'] == null ? null : _date(json['trial_at']),
      trialOutcome: _nullableString(json['trial_outcome']),
      notes: _nullableString(json['notes']),
      teamId: _nullableInt(json['team_id']),
      teamName: team is JsonMap ? _nullableString(team['name']) : null,
      membershipTypeId: _nullableInt(json['club_membership_type_id']),
      membershipTypeName: membershipType is JsonMap
          ? _nullableString(membershipType['name'])
          : null,
      membershipRequestId: _nullableInt(json['club_membership_request_id']),
      convertedAt: json['converted_at'] == null
          ? null
          : _date(json['converted_at']),
      createdAt: _date(json['created_at']),
    );
  }

  final int id;
  final int clubId;
  final String name;
  final String status;
  final String? email;
  final String? phone;
  final String? source;
  final DateTime? trialAt;
  final String? trialOutcome;
  final String? notes;
  final int? teamId;
  final String? teamName;
  final int? membershipTypeId;
  final String? membershipTypeName;
  final int? membershipRequestId;
  final DateTime? convertedAt;
  final DateTime createdAt;
}

class AirmiusMembershipConsent {
  const AirmiusMembershipConsent({
    required this.version,
    required this.method,
    this.signature,
    this.signedAt,
    this.documentVersions = const {},
  });

  final String version;
  final String method;
  final String? signature;
  final DateTime? signedAt;
  final JsonMap documentVersions;

  factory AirmiusMembershipConsent.fromJson(JsonMap json) =>
      AirmiusMembershipConsent(
        version: _string(json['version'], fallback: 'membership-v1'),
        method: _string(json['method'], fallback: 'checkbox_confirmation'),
        signature: _nullableString(json['signature']),
        signedAt: json['signed_at'] == null ? null : _date(json['signed_at']),
        documentVersions: json['document_versions'] is JsonMap
            ? json['document_versions'] as JsonMap
            : const {},
      );
}

class AirmiusFileAsset {
  const AirmiusFileAsset({
    required this.id,
    required this.name,
    required this.mimeType,
    required this.size,
    required this.url,
    this.purpose,
  });

  final int id;
  final String name;
  final String mimeType;
  final int size;
  final String url;
  final String? purpose;

  factory AirmiusFileAsset.fromJson(JsonMap json) => AirmiusFileAsset(
    id: _int(json['id']),
    name: _string(json['name']),
    mimeType: _string(json['mime_type']),
    size: _int(json['size']),
    url: _string(json['url']),
    purpose: json['purpose'] as String?,
  );
}

class AirmiusEvent {
  const AirmiusEvent({
    required this.id,
    required this.title,
    required this.startsAt,
    this.endsAt,
    required this.type,
    required this.status,
    required this.visibility,
    required this.participantsCount,
    required this.commentsCount,
    this.filesCount = 0,
    required this.yesCount,
    this.lateCount = 0,
    required this.maybeCount,
    required this.noCount,
    this.waitlistCount = 0,
    required this.canJoin,
    this.canUpdate = false,
    this.canManageMetadata = false,
    this.canDelete = false,
    this.canCancel = false,
    this.canManageAttendance = false,
    this.usesPenaltyCatalog = false,
    this.participants = const [],
    this.userId,
    this.conversationId,
    this.clubId,
    this.teamId,
    this.clubName,
    this.teamName,
    this.notes,
    this.location,
    this.locationName,
    this.locationCity,
    this.maxParticipants,
    this.myParticipationStatus,
    this.cancellationReason,
    this.sportRoute,
    this.sportYearPeriodName,
  });

  final int id;
  final String title;
  final DateTime startsAt;
  final DateTime? endsAt;
  final String type;
  final String status;
  final String visibility;
  final int? clubId;
  final int? teamId;
  final String? clubName;
  final String? teamName;
  final String? notes;
  final String? location;
  final String? locationName;
  final String? locationCity;
  final int? maxParticipants;
  final int participantsCount;
  final int commentsCount;
  final int filesCount;
  final int yesCount;
  final int lateCount;
  final int maybeCount;
  final int noCount;
  final int waitlistCount;
  final String? myParticipationStatus;
  final bool canJoin;
  final bool canUpdate;
  final bool canManageMetadata;
  final bool canDelete;
  final bool canCancel;
  final bool canManageAttendance;
  final bool usesPenaltyCatalog;
  final List<AirmiusEventParticipant> participants;
  final int? userId;
  final int? conversationId;
  final String? cancellationReason;
  final AirmiusSportRouteReference? sportRoute;
  final String? sportYearPeriodName;

  AirmiusEvent copyWith({
    int? id,
    String? title,
    DateTime? startsAt,
    DateTime? endsAt,
    String? type,
    String? status,
    String? visibility,
    int? clubId,
    int? teamId,
    String? clubName,
    String? teamName,
    String? notes,
    String? location,
    String? locationName,
    String? locationCity,
    int? maxParticipants,
    int? participantsCount,
    int? commentsCount,
    int? filesCount,
    int? yesCount,
    int? lateCount,
    int? maybeCount,
    int? noCount,
    int? waitlistCount,
    String? myParticipationStatus,
    bool? canJoin,
    bool? canUpdate,
    bool? canManageMetadata,
    bool? canDelete,
    bool? canCancel,
    bool? canManageAttendance,
    bool? usesPenaltyCatalog,
    List<AirmiusEventParticipant>? participants,
    int? userId,
    int? conversationId,
    String? cancellationReason,
    AirmiusSportRouteReference? sportRoute,
    String? sportYearPeriodName,
  }) => AirmiusEvent(
    id: id ?? this.id,
    title: title ?? this.title,
    startsAt: startsAt ?? this.startsAt,
    endsAt: endsAt ?? this.endsAt,
    type: type ?? this.type,
    status: status ?? this.status,
    visibility: visibility ?? this.visibility,
    clubId: clubId ?? this.clubId,
    teamId: teamId ?? this.teamId,
    clubName: clubName ?? this.clubName,
    teamName: teamName ?? this.teamName,
    notes: notes ?? this.notes,
    location: location ?? this.location,
    locationName: locationName ?? this.locationName,
    locationCity: locationCity ?? this.locationCity,
    maxParticipants: maxParticipants ?? this.maxParticipants,
    participantsCount: participantsCount ?? this.participantsCount,
    commentsCount: commentsCount ?? this.commentsCount,
    filesCount: filesCount ?? this.filesCount,
    yesCount: yesCount ?? this.yesCount,
    lateCount: lateCount ?? this.lateCount,
    maybeCount: maybeCount ?? this.maybeCount,
    noCount: noCount ?? this.noCount,
    waitlistCount: waitlistCount ?? this.waitlistCount,
    myParticipationStatus: myParticipationStatus ?? this.myParticipationStatus,
    canJoin: canJoin ?? this.canJoin,
    canUpdate: canUpdate ?? this.canUpdate,
    canManageMetadata: canManageMetadata ?? this.canManageMetadata,
    canDelete: canDelete ?? this.canDelete,
    canCancel: canCancel ?? this.canCancel,
    canManageAttendance: canManageAttendance ?? this.canManageAttendance,
    usesPenaltyCatalog: usesPenaltyCatalog ?? this.usesPenaltyCatalog,
    participants: participants ?? this.participants,
    userId: userId ?? this.userId,
    conversationId: conversationId ?? this.conversationId,
    cancellationReason: cancellationReason ?? this.cancellationReason,
    sportRoute: sportRoute ?? this.sportRoute,
    sportYearPeriodName: sportYearPeriodName ?? this.sportYearPeriodName,
  );

  factory AirmiusEvent.fromJson(JsonMap json) {
    final club = json['club'];
    final team = json['team'];
    final sportYearPeriod = json['sport_year_period'];
    final locationParts = [
      _nullableString(json['location_name']),
      _nullableString(json['location']),
      _nullableString(json['location_city']),
    ].whereType<String>().where((value) => value.isNotEmpty).toSet().toList();

    return AirmiusEvent(
      id: _int(json['id']),
      title: _string(json['title']),
      startsAt: _date(json['starts_at'] ?? json['start_time']),
      endsAt: json['ends_at'] == null && json['end_time'] == null
          ? null
          : _date(json['ends_at'] ?? json['end_time']),
      type: _string(json['type'], fallback: 'event'),
      status: _string(json['status'], fallback: 'open'),
      visibility: _string(json['visibility'], fallback: 'public'),
      userId: json['user_id'] == null ? null : _int(json['user_id']),
      conversationId: json['conversation_id'] == null
          ? null
          : _int(json['conversation_id']),
      clubId: json['club_id'] == null ? null : _int(json['club_id']),
      teamId: json['team_id'] == null ? null : _int(json['team_id']),
      clubName: club is JsonMap ? _nullableString(club['name']) : null,
      teamName: team is JsonMap ? _nullableString(team['name']) : null,
      notes: _nullableString(json['notes']),
      location: locationParts.isEmpty ? null : locationParts.join(' - '),
      locationName: _nullableString(json['location_name']),
      locationCity: _nullableString(json['location_city']),
      maxParticipants: json['max_participants'] == null
          ? null
          : _int(json['max_participants']),
      participantsCount: _int(json['participants_count']),
      commentsCount: _int(json['comments_count']),
      filesCount: _int(json['files_count']),
      yesCount: _int(json['yes_count']),
      lateCount: _int(json['late_count']),
      maybeCount: _int(json['maybe_count']),
      noCount: _int(json['no_count']),
      waitlistCount: _int(json['waitlist_count']),
      myParticipationStatus: _nullableString(json['my_participation_status']),
      canJoin: _bool(json['can_join']),
      canUpdate: _bool(json['can_update']),
      canManageMetadata: _bool(json['can_manage_metadata']),
      canDelete: _bool(json['can_delete']),
      canCancel: _bool(json['can_cancel']),
      canManageAttendance: _bool(json['can_manage_attendance']),
      usesPenaltyCatalog: _bool(json['uses_penalty_catalog']),
      participants: _eventParticipants(json['participants']),
      cancellationReason: _nullableString(json['cancellation_reason']),
      sportRoute: json['sport_route'] is JsonMap
          ? AirmiusSportRouteReference.fromJson(json['sport_route'] as JsonMap)
          : null,
      sportYearPeriodName: sportYearPeriod is JsonMap
          ? _nullableString(sportYearPeriod['name'])
          : null,
    );
  }
}

class AirmiusSportRouteReference {
  const AirmiusSportRouteReference({
    required this.id,
    required this.title,
    this.sportType,
    this.startName,
    this.endName,
    this.distanceMeters,
  });

  final int id;
  final String title;
  final String? sportType;
  final String? startName;
  final String? endName;
  final int? distanceMeters;

  factory AirmiusSportRouteReference.fromJson(JsonMap json) =>
      AirmiusSportRouteReference(
        id: _int(json['id']),
        title: _string(json['title']),
        sportType: _nullableString(json['sport_type']),
        startName: _nullableString(json['start_name']),
        endName: _nullableString(json['end_name']),
        distanceMeters: json['distance_meters'] == null
            ? null
            : _int(json['distance_meters']),
      );
}

class AirmiusEventParticipant {
  const AirmiusEventParticipant({
    required this.id,
    required this.name,
    required this.status,
    this.email,
    this.avatarUrl,
  });

  final int id;
  final String name;
  final String status;
  final String? email;
  final String? avatarUrl;

  factory AirmiusEventParticipant.fromJson(JsonMap json) {
    final pivot = json['pivot'];
    return AirmiusEventParticipant(
      id: _int(json['id']),
      name: _string(json['name'], fallback: 'Spieler'),
      email: _nullableString(json['email']),
      avatarUrl: _mediaUrl(
        json['profile_photo_url'] ??
            json['avatar_url'] ??
            json['profile_photo_path'],
      ),
      status: pivot is JsonMap
          ? _string(pivot['status'], fallback: 'yes')
          : _string(json['status'], fallback: 'yes'),
    );
  }
}

List<AirmiusEventParticipant> _eventParticipants(Object? value) => value is List
    ? value.whereType<JsonMap>().map(AirmiusEventParticipant.fromJson).toList()
    : const [];

class AirmiusEventDecisionOption {
  const AirmiusEventDecisionOption({
    required this.id,
    required this.label,
    this.sortOrder = 0,
    this.votes = 0,
  });

  final int id;
  final String label;
  final int sortOrder;
  final int votes;

  factory AirmiusEventDecisionOption.fromJson(JsonMap json) =>
      AirmiusEventDecisionOption(
        id: _int(json['id']),
        label: _string(json['label'], fallback: 'Option'),
        sortOrder: _int(json['sort_order']),
        votes: _int(json['votes']),
      );
}

class AirmiusEventDecision {
  const AirmiusEventDecision({
    required this.id,
    required this.question,
    this.description,
    this.status = 'open',
    this.closesAt,
    this.myOptionId,
    this.options = const [],
    this.votes = 0,
  });

  final int id;
  final String question;
  final String? description;
  final String status;
  final DateTime? closesAt;
  final int? myOptionId;
  final List<AirmiusEventDecisionOption> options;
  final int votes;

  bool get isOpen =>
      status == 'open' &&
      (closesAt == null || DateTime.now().isBefore(closesAt!));

  factory AirmiusEventDecision.fromJson(JsonMap json) {
    final rawOptions = json['options'];
    return AirmiusEventDecision(
      id: _int(json['id']),
      question: _string(json['question']),
      description: _nullableString(json['description']),
      status: _string(json['status'], fallback: 'open'),
      closesAt: _optionalDate(json['closes_at']),
      myOptionId: json['my_option_id'] == null
          ? null
          : _int(json['my_option_id']),
      options: rawOptions is List
          ? rawOptions
                .whereType<JsonMap>()
                .map(AirmiusEventDecisionOption.fromJson)
                .toList()
          : const [],
      votes: _int(json['votes']),
    );
  }
}

class AirmiusEventComment {
  const AirmiusEventComment({
    required this.id,
    required this.eventId,
    required this.content,
    required this.userId,
    required this.userName,
    required this.createdAt,
    this.mine = false,
    this.avatarUrl,
  });

  final int id;
  final int eventId;
  final String content;
  final int userId;
  final String userName;
  final DateTime createdAt;
  final bool mine;
  final String? avatarUrl;

  factory AirmiusEventComment.fromJson(JsonMap json) {
    final user = json['user'] is JsonMap
        ? json['user'] as JsonMap
        : const <String, dynamic>{};
    return AirmiusEventComment(
      id: _int(json['id']),
      eventId: _int(json['event_id']),
      content: _string(json['content']),
      userId: _int(user['id'] ?? json['user_id']),
      userName: _string(user['name'], fallback: 'Airmius'),
      createdAt: _date(json['created_at']),
      mine: _bool(json['mine']),
      avatarUrl: _mediaUrl(
        user['profile_photo_url'] ??
            user['avatar_url'] ??
            user['profile_photo_path'],
      ),
    );
  }
}

class AirmiusEventAttendanceMember {
  const AirmiusEventAttendanceMember({
    required this.id,
    required this.name,
    this.status,
    this.responseReason,
    this.avatarUrl,
  });

  final int id;
  final String name;
  final String? status;
  final String? responseReason;
  final String? avatarUrl;

  factory AirmiusEventAttendanceMember.fromJson(JsonMap json) =>
      AirmiusEventAttendanceMember(
        id: _int(json['id']),
        name: _string(json['name'], fallback: 'Airmius'),
        status: _nullableString(json['status']),
        responseReason: _nullableString(json['response_reason']),
        avatarUrl: _mediaUrl(
          json['profile_photo_url'] ??
              json['avatar_url'] ??
              json['profile_photo_path'],
        ),
      );
}

class AirmiusInvoice {
  const AirmiusInvoice({
    required this.id,
    required this.number,
    required this.status,
    required this.amountCents,
    required this.currency,
    this.clubId,
    this.dueAt,
  });

  final int id;
  final String number;
  final String status;
  final int amountCents;
  final String currency;
  final int? clubId;
  final DateTime? dueAt;

  factory AirmiusInvoice.fromJson(JsonMap json) => AirmiusInvoice(
    id: _int(json['id']),
    number: _string(json['number']),
    status: _string(json['status'], fallback: 'open'),
    amountCents: _int(json['amount_cents']),
    currency: _string(json['currency'], fallback: 'EUR'),
    clubId: json['club_id'] == null ? null : _int(json['club_id']),
    dueAt: _optionalDate(json['due_at']),
  );
}

class AirmiusNotification {
  const AirmiusNotification({
    required this.id,
    required this.type,
    required this.title,
    required this.body,
    required this.timeLabel,
    required this.unread,
    this.data = const {},
    this.actionUrl,
  });

  factory AirmiusNotification.fromJson(JsonMap json) {
    final data = json['data'];
    final dataMap = data is JsonMap ? data : const <String, dynamic>{};
    final type = '${json['type'] ?? json['category'] ?? 'System'}';
    return AirmiusNotification(
      id: json['id'] as int? ?? int.tryParse('${json['id'] ?? 0}') ?? 0,
      type: type,
      title:
          '${json['title'] ?? dataMap['title'] ?? json['subject'] ?? 'Benachrichtigung'}',
      body:
          '${json['body'] ?? dataMap['body'] ?? dataMap['message'] ?? json['message'] ?? json['description'] ?? ''}',
      timeLabel:
          '${json['time_label'] ?? json['time'] ?? json['created_at'] ?? 'Jetzt'}',
      unread:
          json['unread'] as bool? ??
          !(json['read'] as bool? ?? json['read_at'] != null),
      data: dataMap,
      actionUrl:
          _mobileNotificationActionUrl(type, dataMap) ??
          json['action_url'] as String? ??
          json['url'] as String? ??
          dataMap['url'] as String?,
    );
  }

  final int id;
  final String type;
  final String title;
  final String body;
  final String timeLabel;
  final bool unread;
  final JsonMap data;
  final String? actionUrl;

  AirmiusNotification copyWith({bool? unread}) => AirmiusNotification(
    id: id,
    type: type,
    title: title,
    body: body,
    timeLabel: timeLabel,
    unread: unread ?? this.unread,
    data: data,
    actionUrl: actionUrl,
  );
}

String? _mobileNotificationActionUrl(String type, JsonMap data) {
  final explicit = data['mobile_url']?.toString().trim();
  if (explicit != null && explicit.isNotEmpty) return explicit;

  if (type == 'sport_matching.application') {
    final id = int.tryParse('${data['matching_id'] ?? ''}');
    if (id != null && id > 0) {
      return 'airmius://sport-matching?matching_id=$id';
    }
  }

  if (type == 'post.like' || type == 'post.comment') {
    final postId = int.tryParse('${data['post_id'] ?? ''}');
    if (postId != null && postId > 0) return 'airmius://feed/$postId';
  }

  final trainingPlanId = int.tryParse('${data['training_plan_id'] ?? ''}');
  if (trainingPlanId != null && trainingPlanId > 0) {
    return 'airmius://training/plans/$trainingPlanId';
  }

  final trainingLogId = int.tryParse('${data['training_log_id'] ?? ''}');
  if (trainingLogId != null && trainingLogId > 0) {
    return 'airmius://training/logs/$trainingLogId';
  }

  if (type == 'club.membership_request_created' ||
      type == 'club.membership_request_withdrawn') {
    final clubId = int.tryParse('${data['club_id'] ?? ''}');
    if (clubId != null && clubId > 0) {
      return 'airmius://clubs/$clubId/membership-requests';
    }
  }

  if (type == 'club.membership_request_approved' ||
      type == 'club.membership_request_declined') {
    final requestId = int.tryParse(
      '${data['membership_request_id'] ?? data['request_id'] ?? ''}',
    );
    if (requestId != null && requestId > 0) {
      return 'airmius://membership-applications/$requestId';
    }
  }

  if (type == 'club.member_removed') {
    return 'airmius://notifications';
  }

  return null;
}

class AirmiusConversation {
  const AirmiusConversation({
    required this.id,
    required this.title,
    required this.kind,
    required this.lastMessage,
    required this.timeLabel,
    required this.unreadCount,
    this.ownerId,
    this.description,
    this.members = const [],
    this.membersCount,
    this.mutedUntil,
    this.teamId,
    this.clubId,
    this.directPeerHasBlocked = false,
    this.postingPolicy = 'all',
    this.viewerRole,
    this.canEditGroup = false,
    this.canManageMembers = false,
    this.canManageRoles = false,
    this.canDeleteGroup = false,
    this.canSendMessages = true,
  });

  factory AirmiusConversation.fromJson(JsonMap json) {
    final latest = json['latest_message'];
    final users = json['users'];
    final rawMembersCount =
        json['members_count'] ?? json['membersCount'] ?? json['member_count'];
    final fallbackUser =
        users is List && users.isNotEmpty && users.first is JsonMap
        ? _string((users.first as JsonMap)['name'])
        : '';
    return AirmiusConversation(
      id: _int(json['id']),
      title: _string(
        json['title'] ?? json['name'] ?? json['subject'],
        fallback: fallbackUser.isEmpty ? 'Konversation' : fallbackUser,
      ),
      kind: _string(
        json['kind'] ?? json['type'] ?? json['scope'],
        fallback: 'Chat',
      ),
      lastMessage: latest is JsonMap
          ? _string(latest['message'])
          : _string(
              json['last_message'] ?? json['lastMessage'] ?? json['preview'],
            ),
      timeLabel: _string(
        json['time_label'] ??
            json['time'] ??
            json['updated_at'] ??
            json['created_at'],
        fallback: 'Jetzt',
      ),
      unreadCount: _int(
        json['unread_messages_count'] ?? json['unread_count'] ?? json['unread'],
      ),
      ownerId: _nullableInt(json['owner_id']),
      teamId: _nullableInt(json['team_id']),
      clubId: _nullableInt(json['club_id']),
      description: _nullableString(json['description']),
      members: users is List ? users.whereType<JsonMap>().toList() : const [],
      membersCount: rawMembersCount == null
          ? (users is List ? users.length : null)
          : _int(rawMembersCount),
      mutedUntil: _nullableString(json['muted_until']),
      directPeerHasBlocked: json['direct_peer_has_blocked'] == true,
      postingPolicy: _string(json['posting_policy'], fallback: 'all'),
      viewerRole: _nullableString(json['viewer_role']),
      canEditGroup: json['permissions'] is JsonMap
          ? _bool((json['permissions'] as JsonMap)['can_edit_group'])
          : false,
      canManageMembers: json['permissions'] is JsonMap
          ? _bool((json['permissions'] as JsonMap)['can_manage_members'])
          : false,
      canManageRoles: json['permissions'] is JsonMap
          ? _bool((json['permissions'] as JsonMap)['can_manage_roles'])
          : false,
      canDeleteGroup: json['permissions'] is JsonMap
          ? _bool((json['permissions'] as JsonMap)['can_delete_group'])
          : false,
      canSendMessages: json['permissions'] is JsonMap
          ? (json['permissions'] as JsonMap).containsKey('can_send_messages')
                ? _bool((json['permissions'] as JsonMap)['can_send_messages'])
                : true
          : true,
    );
  }

  bool get _isDirectConversation {
    final normalizedKind = kind.toLowerCase();
    return !normalizedKind.contains('team') &&
        !normalizedKind.contains('group') &&
        !normalizedKind.contains('event') &&
        !normalizedKind.contains('training');
  }

  String titleForViewer(int? currentUserId) {
    if (currentUserId == null || !_isDirectConversation) return title;

    final otherMembers = members.where((member) {
      final id = _nullableInt(member['id']);
      return id != null && id != currentUserId;
    }).toList();

    final names = otherMembers
        .map((member) => _string(member['name'], fallback: ''))
        .where((name) => name.isNotEmpty)
        .toList();

    if (names.isNotEmpty) return names.join(', ');

    return title;
  }

  String? avatarUrlForViewer(int? currentUserId) {
    if (!_isDirectConversation) return null;

    final otherMembers = members.where((member) {
      final id = _nullableInt(member['id']);
      return currentUserId == null || id == null || id != currentUserId;
    });

    for (final member in otherMembers) {
      final avatarUrl = _userAvatarUrl(member);
      if (avatarUrl != null) return avatarUrl;
    }

    return null;
  }

  final int id;
  final String title;
  final String kind;
  final String lastMessage;
  final String timeLabel;
  final int unreadCount;
  final int? ownerId;
  final String? description;
  final List<JsonMap> members;
  final int? membersCount;
  final String? mutedUntil;
  final int? teamId;
  final int? clubId;
  final bool directPeerHasBlocked;
  final String postingPolicy;
  final String? viewerRole;
  final bool canEditGroup;
  final bool canManageMembers;
  final bool canManageRoles;
  final bool canDeleteGroup;
  final bool canSendMessages;
}

class AirmiusMessage {
  const AirmiusMessage({
    required this.id,
    required this.conversationId,
    required this.message,
    required this.senderName,
    required this.createdAt,
    required this.mine,
    required this.status,
    required this.read,
    this.senderAvatarUrl,
    this.attachments = const [],
    this.reactions = const [],
  });

  factory AirmiusMessage.fromJson(JsonMap json) {
    final sender = json['sender'];
    final senderId = _int(json['sender_id']);
    final currentUserId = _int(json['current_user_id'], fallback: -1);
    final reactions = json['reactions'];
    final receipts = json['receipts'];
    final read =
        _nullableString(json['read_at']) != null ||
        (receipts is List &&
            receipts.whereType<JsonMap>().any(
              (receipt) => _nullableString(receipt['read_at']) != null,
            ));
    return AirmiusMessage(
      id: _int(json['id']),
      conversationId: _int(json['conversation_id']),
      message: _string(json['message']),
      senderName: sender is JsonMap
          ? _string(sender['name'], fallback: 'Airmius')
          : 'Airmius',
      senderAvatarUrl: sender is JsonMap ? _userAvatarUrl(sender) : null,
      createdAt: _date(json['created_at']),
      mine: currentUserId >= 0
          ? senderId == currentUserId
          : _bool(json['mine'] ?? json['is_mine']),
      status: _string(
        json['status'] ?? json['delivery_status'],
        fallback: 'sent',
      ),
      read: read,
      attachments: _postAttachments(json['attachments']),
      reactions: reactions is List
          ? reactions
                .whereType<JsonMap>()
                .map(AirmiusMessageReaction.fromJson)
                .toList()
          : const [],
    );
  }

  AirmiusMessage copyWith({
    String? message,
    String? senderName,
    String? senderAvatarUrl,
    DateTime? createdAt,
    bool? mine,
    String? status,
    bool? read,
    List<AirmiusPostAttachment>? attachments,
    List<AirmiusMessageReaction>? reactions,
  }) => AirmiusMessage(
    id: id,
    conversationId: conversationId,
    message: message ?? this.message,
    senderName: senderName ?? this.senderName,
    senderAvatarUrl: senderAvatarUrl ?? this.senderAvatarUrl,
    createdAt: createdAt ?? this.createdAt,
    mine: mine ?? this.mine,
    status: status ?? this.status,
    read: read ?? this.read,
    attachments: attachments ?? this.attachments,
    reactions: reactions ?? this.reactions,
  );

  final int id;
  final int conversationId;
  final String message;
  final String senderName;
  final String? senderAvatarUrl;
  final DateTime createdAt;
  final bool mine;
  final String status;
  final bool read;
  final List<AirmiusPostAttachment> attachments;
  final List<AirmiusMessageReaction> reactions;
}

class AirmiusMessageReaction {
  const AirmiusMessageReaction({
    required this.id,
    required this.userId,
    required this.reaction,
  });

  factory AirmiusMessageReaction.fromJson(JsonMap json) =>
      AirmiusMessageReaction(
        id: _int(json['id']),
        userId: _int(json['user_id']),
        reaction: _string(json['reaction'] ?? json['emoji']),
      );

  final int id;
  final int userId;
  final String reaction;
}

class AirmiusPost {
  const AirmiusPost({
    required this.id,
    required this.userId,
    required this.content,
    required this.visibility,
    required this.moderationStatus,
    required this.postType,
    required this.contentOrigin,
    required this.clubId,
    required this.teamId,
    required this.sportId,
    required this.authorName,
    required this.createdAt,
    required this.commentsCount,
    required this.likesCount,
    required this.helpfulsCount,
    required this.likedByMe,
    required this.helpfulByMe,
    required this.canUpdate,
    required this.canDelete,
    this.authorAvatarUrl,
    this.clubName,
    this.teamName,
    this.sportName,
    this.sportSkills = const [],
    this.sportSkillIds = const [],
    this.imageUrl,
    this.imageUrls = const [],
    this.attachments = const [],
  });

  factory AirmiusPost.fromJson(JsonMap json) {
    final user = json['user'];
    final club = json['club'];
    final team = json['team'];
    final imageUrls = _postImageUrls(
      image: json['image'],
      imageUrl: json['image_url'],
      imageProxyUrl: json['image_proxy_url'],
      uploadsBaseUrl: json['uploads_base_url'],
    );
    final fallbackImageUrl =
        _firstImageAttachmentUrl(json['attachments']) ??
        _firstImageAttachmentUrl(json['files']);
    return AirmiusPost(
      id: _int(json['id']),
      userId: _int(
        json['user_id'],
        fallback: user is JsonMap ? _int(user['id']) : 0,
      ),
      content: _string(json['content']),
      visibility: _string(json['visibility'], fallback: 'public'),
      moderationStatus: _string(
        json['moderation_status'],
        fallback: 'approved',
      ),
      postType: _string(json['post_type'], fallback: 'normal'),
      contentOrigin: _string(json['content_origin'], fallback: 'self'),
      clubId: _nullableInt(json['club_id']),
      teamId: _nullableInt(json['team_id']),
      sportId: _nullableInt(json['sport_id']),
      authorName: user is JsonMap
          ? _string(user['name'], fallback: 'Airmius')
          : 'Airmius',
      authorAvatarUrl: user is JsonMap ? _userAvatarUrl(user) : null,
      clubName: club is JsonMap ? _nullableString(club['name']) : null,
      teamName: team is JsonMap ? _nullableString(team['name']) : null,
      sportName: _postSportName(json['sport']),
      sportSkills: _postSportSkills(json['sport_skills']),
      sportSkillIds: _postSportSkillIds(json['sport_skills']),
      imageUrl: imageUrls.isNotEmpty ? imageUrls.first : fallbackImageUrl,
      imageUrls: imageUrls,
      attachments: [
        ..._postAttachments(json['attachments']),
        ..._postAttachments(json['files']),
      ],
      createdAt: _date(json['created_at']),
      commentsCount: _int(
        json['comments_count'],
        fallback: _int(json['comments']),
      ),
      likesCount: _int(json['likes_count'], fallback: _int(json['likes'])),
      helpfulsCount: _int(
        json['helpfuls_count'],
        fallback: _int(json['helpful_count']),
      ),
      likedByMe: _bool(json['liked_by_me']) || _bool(json['is_liked']),
      helpfulByMe: _bool(json['helpful_by_me']) || _bool(json['is_helpful']),
      canUpdate: _bool(json['can_update']) || _bool(json['can_delete']),
      canDelete: _bool(json['can_delete']),
    );
  }

  final int id;
  final int userId;
  final String content;
  final String visibility;
  final String moderationStatus;
  final String postType;
  final String contentOrigin;
  final int? clubId;
  final int? teamId;
  final int? sportId;
  final String authorName;
  final String? authorAvatarUrl;
  final String? clubName;
  final String? teamName;
  final String? sportName;
  final List<String> sportSkills;
  final List<int> sportSkillIds;
  final String? imageUrl;
  final List<String> imageUrls;
  final List<AirmiusPostAttachment> attachments;
  final DateTime createdAt;
  final int commentsCount;
  final int likesCount;
  final int helpfulsCount;
  final bool likedByMe;
  final bool helpfulByMe;
  final bool canUpdate;
  final bool canDelete;

  AirmiusPost copyWith({
    int? commentsCount,
    int? likesCount,
    int? helpfulsCount,
    bool? likedByMe,
    bool? helpfulByMe,
  }) {
    return AirmiusPost(
      id: id,
      userId: userId,
      content: content,
      visibility: visibility,
      moderationStatus: moderationStatus,
      postType: postType,
      contentOrigin: contentOrigin,
      clubId: clubId,
      teamId: teamId,
      sportId: sportId,
      authorName: authorName,
      createdAt: createdAt,
      commentsCount: commentsCount ?? this.commentsCount,
      likesCount: likesCount ?? this.likesCount,
      helpfulsCount: helpfulsCount ?? this.helpfulsCount,
      likedByMe: likedByMe ?? this.likedByMe,
      helpfulByMe: helpfulByMe ?? this.helpfulByMe,
      canUpdate: canUpdate,
      canDelete: canDelete,
      authorAvatarUrl: authorAvatarUrl,
      clubName: clubName,
      teamName: teamName,
      sportName: sportName,
      sportSkills: sportSkills,
      sportSkillIds: sportSkillIds,
      imageUrl: imageUrl,
      imageUrls: imageUrls,
      attachments: attachments,
    );
  }
}

class AirmiusPostAttachment {
  const AirmiusPostAttachment({
    required this.name,
    required this.url,
    required this.kind,
    this.thumbnailUrl,
  });

  final String name;
  final String url;
  final String kind;
  final String? thumbnailUrl;

  bool get isImage => kind == 'image';
  bool get isVideo => kind == 'video';
}

class AirmiusComment {
  const AirmiusComment({
    required this.id,
    required this.postId,
    required this.content,
    required this.authorName,
    required this.likesCount,
    required this.mine,
    required this.canDelete,
    required this.createdAt,
    this.authorAvatarUrl,
  });

  factory AirmiusComment.fromJson(JsonMap json) {
    final user = json['user'];
    return AirmiusComment(
      id: _int(json['id']),
      postId: _int(json['post_id']),
      content: _string(json['content']),
      authorName: user is JsonMap
          ? _string(user['name'], fallback: 'Airmius')
          : 'Airmius',
      authorAvatarUrl: user is JsonMap ? _userAvatarUrl(user) : null,
      likesCount: _int(json['likes_count']),
      mine: _bool(json['mine']),
      canDelete: _bool(json['can_delete']) || _bool(json['mine']),
      createdAt: _date(json['created_at']),
    );
  }

  final int id;
  final int postId;
  final String content;
  final String authorName;
  final String? authorAvatarUrl;
  final int likesCount;
  final bool mine;
  final bool canDelete;
  final DateTime createdAt;
}

class AirmiusStory {
  const AirmiusStory({
    required this.id,
    required this.actorId,
    required this.actorType,
    required this.actorName,
    required this.visibility,
    required this.mediaUrl,
    required this.mediaKind,
    required this.viewedByMe,
    required this.canDelete,
    required this.viewsCount,
    required this.reactionsCount,
    this.actorAvatarUrl,
    this.caption,
    this.thumbnailUrl,
    this.myReaction,
  });

  factory AirmiusStory.fromJson(JsonMap json) {
    final actor = json['actor'];
    final mediaUrl =
        _mediaUrl(json['media_url']) ??
        _mediaUrl(json['media_path']) ??
        _mediaUrl(json['url']) ??
        _mediaUrl(json['path']) ??
        '';
    return AirmiusStory(
      id: _int(json['id']),
      actorId: actor is JsonMap
          ? _int(actor['id'])
          : _int(json['user_id'] ?? json['actor_id']),
      actorType: actor is JsonMap
          ? _string(actor['type'], fallback: 'user')
          : _string(
              json['actor_type'] ?? json['publisher_type'],
              fallback: 'user',
            ),
      actorName: actor is JsonMap
          ? _string(actor['name'], fallback: 'Airmius')
          : 'Airmius',
      actorAvatarUrl: actor is JsonMap ? _userAvatarUrl(actor) : null,
      visibility: _string(json['visibility'], fallback: 'public'),
      caption: _nullableString(json['caption']),
      mediaUrl: mediaUrl,
      thumbnailUrl:
          _mediaUrl(json['media_thumbnail_url']) ??
          _mediaUrl(json['media_thumbnail_path']) ??
          _mediaUrl(json['thumbnail_url']) ??
          _mediaUrl(json['thumbnail_path']),
      mediaKind: _storyMediaKind(
        json['media_kind'],
        json['media_type'],
        mediaUrl,
      ),
      viewedByMe: _bool(json['viewed_by_me']),
      canDelete: _bool(json['can_delete']),
      myReaction: _nullableString(json['my_reaction']),
      viewsCount: _int(json['views_count']),
      reactionsCount: _int(json['reactions_count']),
    );
  }

  final int id;
  final int actorId;
  final String actorType;
  final String actorName;
  final String? actorAvatarUrl;
  final String visibility;
  final String? caption;
  final String mediaUrl;
  final String? thumbnailUrl;
  final String mediaKind;
  final bool viewedByMe;
  final bool canDelete;
  final String? myReaction;
  final int viewsCount;
  final int reactionsCount;
}

class AirmiusSearchResult {
  const AirmiusSearchResult({
    required this.id,
    required this.type,
    required this.title,
    required this.subtitle,
    this.club,
    this.team,
    this.imageUrl,
    this.payload = const <String, dynamic>{},
  });

  factory AirmiusSearchResult.fromJson(JsonMap json) {
    final type = _string(
      json['type'] ?? json['result_type'],
      fallback: 'Person',
    );
    final clubJson = json['club'];
    final teamJson = json['team'];
    return AirmiusSearchResult(
      id: _int(json['id']),
      type: type,
      title: _string(json['title'] ?? json['name']),
      subtitle: _string(
        json['subtitle'] ?? json['description'] ?? json['city'],
      ),
      imageUrl: _mediaUrl(
        json['avatar_url'] ??
            json['profile_photo_url'] ??
            json['profile_photo_path'] ??
            json['image_url'] ??
            json['logo_url'] ??
            json['logo'],
      ),
      club: clubJson is JsonMap
          ? AirmiusClub.fromJson(clubJson)
          : _isClubType(type)
          ? AirmiusClub.fromJson(json)
          : null,
      team: teamJson is JsonMap
          ? AirmiusTeam.fromJson(teamJson)
          : _isTeamType(type)
          ? AirmiusTeam.fromJson(json)
          : null,
      payload: Map<String, dynamic>.from(json),
    );
  }

  final int id;
  final String type;
  final String title;
  final String subtitle;
  final AirmiusClub? club;
  final AirmiusTeam? team;
  final String? imageUrl;
  final JsonMap payload;
}

class AirmiusSavedView {
  const AirmiusSavedView({
    required this.id,
    required this.workspace,
    required this.name,
    required this.configuration,
    required this.isFavorite,
  });

  factory AirmiusSavedView.fromJson(JsonMap json) => AirmiusSavedView(
    id: _int(json['id']),
    workspace: _string(json['workspace']),
    name: _string(json['name']),
    configuration: json['configuration'] is JsonMap
        ? Map<String, dynamic>.from(json['configuration'] as JsonMap)
        : const <String, dynamic>{},
    isFavorite: _bool(json['is_favorite']),
  );

  final int id;
  final String workspace;
  final String name;
  final JsonMap configuration;
  final bool isFavorite;
}

class AirmiusPage<T> {
  const AirmiusPage({
    required this.items,
    required this.currentPage,
    required this.lastPage,
    this.unreadCount,
  });

  final List<T> items;
  final int currentPage;
  final int lastPage;
  final int? unreadCount;

  factory AirmiusPage.fromJson(JsonMap json, T Function(JsonMap json) map) {
    final rawItems = json['data'];
    final list = rawItems is List
        ? rawItems.whereType<JsonMap>().map(map).toList()
        : <T>[];
    final meta = json['meta'];
    return AirmiusPage<T>(
      items: list,
      currentPage: meta is JsonMap
          ? _int(meta['current_page'], fallback: 1)
          : _int(json['current_page'], fallback: 1),
      lastPage: meta is JsonMap
          ? _int(meta['last_page'], fallback: 1)
          : _int(json['last_page'], fallback: 1),
      unreadCount: meta is JsonMap && meta.containsKey('unread_count')
          ? _int(meta['unread_count'])
          : null,
    );
  }
}

class AirmiusEventStats {
  const AirmiusEventStats({
    required this.upcoming,
    required this.today,
    required this.cancelled,
  });

  final int upcoming;
  final int today;
  final int cancelled;

  factory AirmiusEventStats.fromJson(Object? value) {
    final json = value is JsonMap ? value : const <String, dynamic>{};
    return AirmiusEventStats(
      upcoming: _int(json['upcoming']),
      today: _int(json['today']),
      cancelled: _int(json['cancelled']),
    );
  }
}

class AirmiusEventWorkspace {
  const AirmiusEventWorkspace({
    required this.events,
    required this.calendarEvents,
    required this.stats,
    required this.eventTypes,
    required this.visibilities,
    required this.clubs,
    required this.teams,
    required this.sports,
    this.sportRoutes = const [],
    this.allowsRecurring = false,
    this.allowsRecurringGlobally = false,
    this.recurringClubIds = const [],
    this.recurringTeamIds = const [],
    this.nextEvent,
    this.currentPage = 1,
    this.lastPage = 1,
  });

  final List<AirmiusEvent> events;
  final List<AirmiusEvent> calendarEvents;
  final AirmiusEventStats stats;
  final AirmiusEvent? nextEvent;
  final List<String> eventTypes;
  final List<String> visibilities;
  final List<AirmiusClub> clubs;
  final List<AirmiusTeam> teams;
  final List<AirmiusSport> sports;
  final List<AirmiusSportRouteReference> sportRoutes;
  final bool allowsRecurring;
  final bool allowsRecurringGlobally;
  final List<int> recurringClubIds;
  final List<int> recurringTeamIds;
  final int currentPage;
  final int lastPage;

  factory AirmiusEventWorkspace.fromJson(JsonMap json) {
    final meta = json['meta'];
    return AirmiusEventWorkspace(
      events: _eventList(json['data']),
      calendarEvents: _eventList(json['calendar_events']),
      stats: AirmiusEventStats.fromJson(json['event_stats']),
      nextEvent: json['next_event'] is JsonMap
          ? AirmiusEvent.fromJson(json['next_event'] as JsonMap)
          : null,
      eventTypes: _stringList(json['event_types']),
      visibilities: _stringList(json['visibilities']),
      clubs: _clubList(json['clubs']),
      teams: _teamList(json['teams']),
      sports: _sportList(json['sports']),
      sportRoutes: _jsonList(
        json['sport_routes'],
      ).map(AirmiusSportRouteReference.fromJson).toList(),
      allowsRecurring:
          json['event_creation'] is JsonMap &&
          (json['event_creation'] as JsonMap)['allows_recurring'] == true,
      allowsRecurringGlobally:
          json['event_creation'] is JsonMap &&
          (json['event_creation'] as JsonMap)['allows_recurring_globally'] ==
              true,
      recurringClubIds: json['event_creation'] is JsonMap
          ? _intList((json['event_creation'] as JsonMap)['recurring_club_ids'])
          : const [],
      recurringTeamIds: json['event_creation'] is JsonMap
          ? _intList((json['event_creation'] as JsonMap)['recurring_team_ids'])
          : const [],
      currentPage: meta is JsonMap
          ? _int(meta['current_page'], fallback: 1)
          : 1,
      lastPage: meta is JsonMap ? _int(meta['last_page'], fallback: 1) : 1,
    );
  }

  AirmiusEventWorkspace copyWith({
    List<AirmiusEvent>? events,
    List<AirmiusEvent>? calendarEvents,
    AirmiusEventStats? stats,
    AirmiusEvent? nextEvent,
  }) => AirmiusEventWorkspace(
    events: events ?? this.events,
    calendarEvents: calendarEvents ?? this.calendarEvents,
    stats: stats ?? this.stats,
    nextEvent: nextEvent ?? this.nextEvent,
    eventTypes: eventTypes,
    visibilities: visibilities,
    clubs: clubs,
    teams: teams,
    sports: sports,
    sportRoutes: sportRoutes,
    allowsRecurring: allowsRecurring,
    allowsRecurringGlobally: allowsRecurringGlobally,
    recurringClubIds: recurringClubIds,
    recurringTeamIds: recurringTeamIds,
    currentPage: currentPage,
    lastPage: lastPage,
  );
}

List<AirmiusEvent> _eventList(Object? value) => value is List
    ? value.whereType<JsonMap>().map(AirmiusEvent.fromJson).toList()
    : const [];

List<AirmiusClub> _clubList(Object? value) => value is List
    ? value.whereType<JsonMap>().map(AirmiusClub.fromJson).toList()
    : const [];

List<AirmiusTeam> _teamList(Object? value) => value is List
    ? value.whereType<JsonMap>().map(AirmiusTeam.fromJson).toList()
    : const [];

List<AirmiusSport> _sportList(Object? value) => value is List
    ? value.whereType<JsonMap>().map(AirmiusSport.fromJson).toList()
    : const [];

List<String> _stringList(Object? value) => value is List
    ? value
          .map((item) => item.toString())
          .where((item) => item.isNotEmpty)
          .toList()
    : const [];

abstract class AirmiusAuthRepository {
  Future<AirmiusUser> currentUser();
  Future<AirmiusUser> login({required String email, required String password});
}

abstract class AirmiusClubRepository {
  Future<AirmiusPage<AirmiusClub>> searchClubs({
    String? query,
    int page = 1,
    bool mine = false,
  });
  Future<AirmiusClub> club(int id);
  Future<List<AirmiusClubSurvey>> surveys(int clubId);
  Future<AirmiusClubSurvey> createSurvey(int clubId, JsonMap payload);
  Future<AirmiusClubSurvey> updateSurvey(
    int clubId,
    int surveyId,
    JsonMap payload,
  );
  Future<void> deleteSurvey(int clubId, int surveyId);
  Future<JsonMap> voteSurvey(int clubId, int surveyId, int optionId);
  Future<void> closeSurvey(int clubId, int surveyId);
  Future<List<AirmiusClubAnnouncement>> announcements(int clubId);
  Future<AirmiusClubAnnouncement> createAnnouncement(
    int clubId,
    JsonMap payload,
  );
  Future<AirmiusClubAnnouncement> updateAnnouncement(
    int clubId,
    int announcementId,
    JsonMap payload,
  );
  Future<void> publishAnnouncement(int clubId, int announcementId);
  Future<void> deleteAnnouncement(int clubId, int announcementId);
  Future<void> acknowledgeAnnouncement(int clubId, int announcementId);
  Future<AirmiusClub> createClub(JsonMap payload);
  Future<AirmiusClub> updateClub(int id, JsonMap payload);
  Future<void> deleteClub(int id);
  Future<AirmiusClubManagement> updateMembershipSettings(
    int clubId,
    JsonMap payload,
  );
  Future<AirmiusClubManagement> createMembershipType(
    int clubId,
    JsonMap payload,
  );
  Future<AirmiusClubManagement> updateMembershipType(
    int clubId,
    int typeId,
    JsonMap payload,
  );
  Future<AirmiusClubManagement> createContributionRule(
    int clubId,
    JsonMap payload,
  );
  Future<AirmiusClubManagement> updateContributionRule(
    int clubId,
    int ruleId,
    JsonMap payload,
  );
  Future<AirmiusClubManagement> recordMembershipPayment(
    int clubId,
    int invoiceId,
    JsonMap payload,
  );
  Future<AirmiusClubManagement> recordDonation(int clubId, JsonMap payload);
  Future<AirmiusClubManagement> recordPrepayment(int clubId, JsonMap payload);
  Future<AirmiusClubManagement> updatePayment(
    int clubId,
    int paymentId,
    JsonMap payload,
  );
  Future<AirmiusClubManagement> createFinanceEntry(int clubId, JsonMap payload);
  Future<AirmiusClubManagement> updateFinanceEntry(
    int clubId,
    int entryId,
    JsonMap payload,
  );
  Future<AirmiusClubManagement> confirmClubReceiptUpload(
    int clubId,
    int receiptUploadId,
    JsonMap payload,
  );
  Future<AirmiusClubManagement> inviteClubMember(int clubId, JsonMap payload);
  Future<AirmiusClubManagement> updateClubExternalMember(
    int clubId,
    int externalMemberId,
    JsonMap payload,
  );
  Future<AirmiusClubManagement> inviteClubExternalMember(
    int clubId,
    int externalMemberId,
  );
  Future<AirmiusClubManagement> mergeClubExternalMember(
    int clubId,
    int externalMemberId,
    int targetUserId,
    JsonMap payload,
  );
  Future<JsonMap> createClubMemberTimelineEntry(int clubId, JsonMap payload);
  Future<void> deleteClubMemberTimelineEntry(int clubId, int entryId);
  Future<AirmiusClubManagement> removeClubExternalMember(
    int clubId,
    int externalMemberId, {
    required String reason,
  });
  Future<JsonMap> clubExternalInvitationByToken(String token);
  Future<JsonMap> acceptClubExternalInvitation(String token);
  Future<JsonMap> declineClubExternalInvitation(String token);
  Future<AirmiusClubManagement> updateClubMemberRole(
    int clubId,
    int userId,
    String role,
  );
  Future<AirmiusClubManagement> updateClubMember(
    int clubId,
    int userId,
    JsonMap payload,
  );
  Future<JsonMap> clubMemberPermissions(int clubId, int userId);
  Future<JsonMap> updateClubMemberPermissions(
    int clubId,
    int userId,
    JsonMap payload,
  );
  Future<JsonMap> clubRoleDefinitions(int clubId);
  Future<JsonMap> createClubRoleDefinition(int clubId, JsonMap payload);
  Future<JsonMap> updateClubRoleDefinition(
    int clubId,
    int roleId,
    JsonMap payload,
  );
  Future<void> deleteClubRoleDefinition(int clubId, int roleId);
  Future<JsonMap> clubMemberRoleDefinitions(int clubId, int userId);
  Future<JsonMap> updateClubMemberRoleDefinitions(
    int clubId,
    int userId,
    JsonMap payload,
  );
  Future<JsonMap> clubPermissionDelegations(int clubId);
  Future<JsonMap> createClubPermissionDelegation(int clubId, JsonMap payload);
  Future<JsonMap> revokeClubPermissionDelegation(int clubId, int delegationId);
  Future<JsonMap> clubOrganization(int clubId);
  Future<JsonMap> clubAccessHandoverReviews(int clubId);
  Future<JsonMap> proposeClubAccessHandover(
    int clubId,
    int reviewId,
    JsonMap payload,
  );
  Future<JsonMap> approveClubAccessHandover(int clubId, int reviewId);
  Future<AirmiusClubManagement> removeClubMember(
    int clubId,
    int userId, {
    required String reason,
  });
  Future<AirmiusClubManagement> generateClubMemberNumber(
    int clubId,
    int userId,
  );
  Future<AirmiusClubManagement> createClubMemberInvoice(
    int clubId,
    int userId,
    JsonMap payload,
  );
  Future<AirmiusClubManagement> createClubExternalMemberInvoice(
    int clubId,
    int externalMemberId,
    JsonMap payload,
  );
  Future<JsonMap> previewClubMembershipInvoiceRun(
    int clubId,
    JsonMap payload,
  );
  Future<AirmiusClubManagement> createClubMembershipInvoiceRun(
    int clubId,
    JsonMap payload,
  );
  Future<AirmiusClubManagement> updateClubInvoiceStatus(
    int clubId,
    int invoiceId,
    String status,
  );
  Future<AirmiusClubManagement> sendClubInvoiceReminder(
    int clubId,
    int invoiceId,
  );
  Future<AirmiusClubManagement> updateClubSepaSettings(
    int clubId,
    JsonMap payload,
  );
  Future<AirmiusClubManagement> updateClubDatevSettings(
    int clubId,
    JsonMap payload,
  );
  Future<AirmiusClubManagement> confirmClubBankTransaction(
    int clubId,
    int transactionId,
  );
  Future<AirmiusPage<AirmiusTeam>> teams({int page = 1, int? clubId});
  Future<AirmiusTeam> team(int id);
  Future<JsonMap> teamCompetitivenessInsights(int id);
  Future<AirmiusTeam> createTeam(JsonMap payload);
  Future<AirmiusTeam> updateTeam(int id, JsonMap payload);
  Future<void> deleteTeam(int id);
  Future<AirmiusTeam> requestTeamJoin(int id);
  Future<AirmiusTeam> approveTeamJoinRequest(
    int teamId,
    int requestId, {
    String role = 'Player',
  });
  Future<AirmiusTeam> declineTeamJoinRequest(int teamId, int requestId);
  Future<AirmiusTeamInvitation> inviteTeamMember(
    int teamId, {
    required String email,
    required String role,
  });
  Future<List<AirmiusTeamInvitation>> teamInvitations();
  Future<AirmiusTeamInvitation> teamInvitation(int invitationId);
  Future<AirmiusTeamInvitation> teamInvitationByToken(String token);
  Future<AirmiusTeam> acceptTeamInvitation(int invitationId);
  Future<AirmiusTeam> acceptTeamInvitationByToken(String token);
  Future<AirmiusTeamInvitation> declineTeamInvitation(int invitationId);
  Future<AirmiusTeamInvitation> declineTeamInvitationByToken(String token);
  Future<AirmiusTeam> updateTeamMemberRole(int teamId, int userId, String role);
}

abstract class AirmiusSportRepository {
  Future<AirmiusPage<AirmiusSport>> sports({int page = 1});
}

abstract class AirmiusMembershipRepository {
  Future<AirmiusClubMembershipRequest> applyToClub(int clubId, JsonMap payload);
  Future<AirmiusMembershipApplication> application(int applicationId);
  Future<AirmiusMembershipApplication> withdraw(int applicationId);
  Future<AirmiusPage<AirmiusClubMembershipRequest>> clubRequests(
    int clubId, {
    int page = 1,
  });
  Future<AirmiusClubMembershipRequest> withdrawClubRequest(int clubId);
  Future<AirmiusClubMembershipRequest> approveClubRequest(
    int clubId,
    int requestId, {
    String? reviewNote,
  });
  Future<AirmiusClubMembershipRequest> declineClubRequest(
    int clubId,
    int requestId, {
    String? reviewNote,
  });
  Future<AirmiusClubMembershipRequest> requestClubRequestInformation(
    int clubId,
    int requestId, {
    required String message,
  });
  Future<AirmiusClubMembershipRequest> respondToClubRequestInformation(
    int clubId,
    int requestId, {
    String? message,
    JsonMap applicationData = const {},
    JsonMap acceptedDocuments = const {},
  });
  Future<AirmiusClubMembershipRequest> waitlistClubRequest(
    int clubId,
    int requestId, {
    String? reviewNote,
  });
  Future<AirmiusPage<AirmiusClubMembershipProspect>> clubProspects(
    int clubId, {
    int page = 1,
    String? status,
  });
  Future<AirmiusClubMembershipProspect> createClubProspect(
    int clubId,
    JsonMap payload,
  );
  Future<AirmiusClubMembershipProspect> updateClubProspect(
    int clubId,
    int prospectId,
    JsonMap payload,
  );
  Future<AirmiusClubMembershipProspect> archiveClubProspect(
    int clubId,
    int prospectId,
  );
}

class AirmiusFileWorkspace {
  const AirmiusFileWorkspace({
    required this.scope,
    required this.currentFolder,
    required this.folders,
    required this.files,
    required this.storage,
    required this.filesPagination,
    required this.foldersPagination,
    required this.search,
    required this.sort,
    this.availableTeams = const [],
    this.canUpload = true,
    this.canCreateFolder = true,
  });

  final String scope;
  final AirmiusFolder? currentFolder;
  final List<AirmiusFolder> folders;
  final List<AirmiusManagedFile> files;
  final AirmiusStorageUsage? storage;
  final AirmiusPagination filesPagination;
  final AirmiusPagination foldersPagination;
  final String search;
  final String sort;
  final List<AirmiusNamedItem> availableTeams;
  final bool canUpload;
  final bool canCreateFolder;

  AirmiusFileWorkspace copyWith({
    List<AirmiusManagedFile>? files,
    AirmiusStorageUsage? storage,
    AirmiusPagination? filesPagination,
  }) {
    return AirmiusFileWorkspace(
      scope: scope,
      currentFolder: currentFolder,
      folders: folders,
      files: files ?? this.files,
      storage: storage ?? this.storage,
      filesPagination: filesPagination ?? this.filesPagination,
      foldersPagination: foldersPagination,
      search: search,
      sort: sort,
      availableTeams: availableTeams,
      canUpload: canUpload,
      canCreateFolder: canCreateFolder,
    );
  }

  factory AirmiusFileWorkspace.fromJson(JsonMap json) {
    final data = json['data'] is JsonMap ? json['data'] as JsonMap : json;
    final scope = data['scope'];
    final currentFolder = data['current_folder'];
    final capabilities = data['capabilities'];
    return AirmiusFileWorkspace(
      scope: scope is JsonMap
          ? _string(scope['type'], fallback: 'user')
          : 'user',
      currentFolder: currentFolder is JsonMap
          ? AirmiusFolder.fromJson(currentFolder)
          : null,
      folders: _jsonList(data['folders']).map(AirmiusFolder.fromJson).toList(),
      files: _jsonList(data['files']).map(AirmiusManagedFile.fromJson).toList(),
      storage: data['storage_usage'] is JsonMap
          ? AirmiusStorageUsage.fromJson(data['storage_usage'] as JsonMap)
          : null,
      filesPagination: AirmiusPagination.fromJson(
        data['files_pagination'] is JsonMap
            ? data['files_pagination'] as JsonMap
            : const {},
      ),
      foldersPagination: AirmiusPagination.fromJson(
        data['folders_pagination'] is JsonMap
            ? data['folders_pagination'] as JsonMap
            : const {},
      ),
      search: _string(data['search']),
      sort: _string(data['sort'], fallback: 'name-asc'),
      availableTeams: _jsonList(
        data['available_teams'],
      ).map(AirmiusNamedItem.fromJson).toList(),
      canUpload: capabilities is JsonMap ? _bool(capabilities['upload']) : true,
      canCreateFolder: capabilities is JsonMap
          ? _bool(capabilities['create_folder'])
          : true,
    );
  }
}

class AirmiusFolder {
  const AirmiusFolder({
    required this.id,
    required this.name,
    required this.filesCount,
    this.parentId,
  });

  final int id;
  final String name;
  final int filesCount;
  final int? parentId;

  factory AirmiusFolder.fromJson(JsonMap json) => AirmiusFolder(
    id: _int(json['id']),
    name: _string(json['name'], fallback: 'Ordner'),
    filesCount: _int(json['files_count']),
    parentId: _nullableInt(json['parent_id']),
  );
}

class AirmiusFileAccessRight {
  const AirmiusFileAccessRight({required this.audience, required this.allowed});

  final String audience;
  final bool allowed;

  factory AirmiusFileAccessRight.fromJson(JsonMap json) =>
      AirmiusFileAccessRight(
        audience: _string(json['audience'], fallback: 'owner'),
        allowed: _bool(json['allowed']),
      );
}

class AirmiusFileAccessRights {
  const AirmiusFileAccessRights({required this.scope, required this.rights});

  final String scope;
  final Map<String, AirmiusFileAccessRight> rights;

  AirmiusFileAccessRight? operator [](String key) => rights[key];

  factory AirmiusFileAccessRights.fromJson(JsonMap json) {
    final rightsJson = json['rights'] is JsonMap
        ? json['rights'] as JsonMap
        : const <String, dynamic>{};

    return AirmiusFileAccessRights(
      scope: _string(json['scope'], fallback: 'personal'),
      rights: Map<String, AirmiusFileAccessRight>.fromEntries(
        rightsJson.entries.map(
          (entry) => MapEntry(
            entry.key.toString(),
            entry.value is JsonMap
                ? AirmiusFileAccessRight.fromJson(entry.value as JsonMap)
                : const AirmiusFileAccessRight(
                    audience: 'owner',
                    allowed: false,
                  ),
          ),
        ),
      ),
    );
  }
}

class AirmiusManagedFile {
  const AirmiusManagedFile({
    required this.id,
    required this.name,
    required this.type,
    required this.size,
    required this.url,
    this.thumbnailUrl,
    this.previewUrl,
    this.createdAt,
    this.folderId,
    this.hasPublicShares = false,
    this.accessRights,
  });

  final int id;
  final String name;
  final String type;
  final int size;
  final String url;
  final String? thumbnailUrl;
  final String? previewUrl;
  final DateTime? createdAt;
  final int? folderId;
  final bool hasPublicShares;
  final AirmiusFileAccessRights? accessRights;

  factory AirmiusManagedFile.fromJson(JsonMap json) => AirmiusManagedFile(
    id: _int(json['id']),
    name: _string(json['display_name'] ?? json['name'], fallback: 'Datei'),
    type: _string(json['type'], fallback: 'Datei'),
    size: _int(json['size']),
    url: _string(json['url'] ?? json['path']),
    thumbnailUrl: _nullableString(json['thumbnail_url']),
    previewUrl: _nullableString(json['preview_url']),
    createdAt: _optionalDate(json['created_at']),
    folderId: _nullableInt(json['folder_id']),
    hasPublicShares: json['has_public_shares'] == true,
    accessRights: json['access_rights'] is JsonMap
        ? AirmiusFileAccessRights.fromJson(json['access_rights'] as JsonMap)
        : null,
  );
}

class AirmiusStorageUsage {
  const AirmiusStorageUsage({
    required this.limitGb,
    required this.usedBytes,
    required this.remainingBytes,
    required this.usedPercent,
    required this.isFull,
  });

  final int limitGb;
  final int usedBytes;
  final int remainingBytes;
  final double usedPercent;
  final bool isFull;

  AirmiusStorageUsage withAddedBytes(int bytes) {
    final nextUsed = usedBytes + bytes;
    final totalBytes = limitGb * 1024 * 1024 * 1024;
    return AirmiusStorageUsage(
      limitGb: limitGb,
      usedBytes: nextUsed,
      remainingBytes: (totalBytes - nextUsed).clamp(0, totalBytes).toInt(),
      usedPercent: totalBytes > 0 ? (nextUsed / totalBytes * 100) : 0.0,
      isFull: totalBytes > 0 && nextUsed >= totalBytes,
    );
  }

  factory AirmiusStorageUsage.fromJson(JsonMap json) => AirmiusStorageUsage(
    limitGb: _int(json['limit_gb'], fallback: 1),
    usedBytes: _int(json['used_bytes']),
    remainingBytes: _int(json['remaining_bytes'], fallback: 1024 * 1024 * 1024),
    usedPercent: _double(json['used_percent']),
    isFull: _bool(json['is_full']),
  );
}

class AirmiusPagination {
  const AirmiusPagination({
    required this.currentPage,
    required this.lastPage,
    required this.total,
    this.from,
    this.to,
  });

  final int currentPage;
  final int lastPage;
  final int total;
  final int? from;
  final int? to;

  AirmiusPagination withAddedItem() => AirmiusPagination(
    currentPage: currentPage,
    lastPage: lastPage,
    total: total + 1,
    from: from ?? 1,
    to: (to ?? 0) + 1,
  );

  factory AirmiusPagination.fromJson(JsonMap json) => AirmiusPagination(
    currentPage: _int(json['current_page'], fallback: 1),
    lastPage: _int(json['last_page'], fallback: 1),
    total: _int(json['total']),
    from: _nullableInt(json['from']),
    to: _nullableInt(json['to']),
  );
}

abstract class AirmiusFileRepository {
  Future<JsonMap> createUploadIntent({
    required String scope,
    required String fileName,
    required String mimeType,
  });
  Future<AirmiusFileWorkspace> workspace({
    String scope = 'user',
    int? folderId,
    int? clubId,
    int? teamId,
    int? eventId,
    String? search,
    String sort = 'name-asc',
    int page = 1,
  });
  Future<AirmiusFolder> createFolder({
    required String scope,
    required String name,
    int? parentId,
    int? clubId,
    int? teamId,
    int? eventId,
  });
  Future<AirmiusFolder> renameFolder(int folderId, String name);
  Future<void> deleteFolder(int folderId);
  Future<JsonMap> shareFolder(int folderId, int targetUserId);
  Future<AirmiusManagedFile> renameFile(int fileId, String name);
  Future<void> deleteFile(int fileId);
  Future<JsonMap> shareFile(int fileId, int targetUserId);
  Future<JsonMap> createPublicFileShare(int fileId);
  Future<JsonMap> revokePublicFileShares(int fileId);
}

abstract class AirmiusEventRepository {
  Future<AirmiusPage<AirmiusEvent>> events({
    int page = 1,
    DateTime? from,
    DateTime? to,
  });
  Future<AirmiusEventWorkspace> workspace({
    int page = 1,
    String? search,
    String? type,
    String? visibility,
    int? clubId,
    int? teamId,
    String? period,
    String? calendarMonth,
  });
  Future<AirmiusEvent> create(JsonMap payload);
  Future<AirmiusEvent> event(int eventId);
  Future<AirmiusPage<AirmiusEventComment>> comments(
    int eventId, {
    int page = 1,
  });
  Future<AirmiusEventComment> createComment(int eventId, String content);
  Future<AirmiusEvent> update(int eventId, JsonMap payload);
  Future<AirmiusEvent> cancel(int eventId, {String? reason});
  Future<void> delete(int eventId);
  Future<List<AirmiusEventAttendanceMember>> attendance(int eventId);
  Future<AirmiusEvent> recordAttendance(int eventId, List<JsonMap> attendance);
  Future<AirmiusEvent> respond(int eventId, String status);
  Future<AirmiusEvent> leave(int eventId);
  Future<List<AirmiusEventDecision>> decisions(int eventId);
  Future<AirmiusEventDecision> createDecision(int eventId, JsonMap payload);
  Future<JsonMap> castDecisionVote(int eventId, int decisionId, int optionId);
  Future<void> closeDecision(int eventId, int decisionId);
}

abstract class AirmiusBillingRepository {
  Future<AirmiusPage<AirmiusInvoice>> invoices({int page = 1});
  Future<AirmiusInvoice> invoice(int invoiceId);
}

abstract class AirmiusNotificationRepository {
  Future<AirmiusPage<AirmiusNotification>> notifications({int page = 1});
  Future<AirmiusNotification> notification(int notificationId);
  Future<AirmiusNotification> markAsRead(int notificationId);
  Future<AirmiusNotification> markAsUnread(int notificationId);
  Future<void> markAllAsRead();
  Future<void> delete(int notificationId);
}

abstract class AirmiusConversationRepository {
  Future<AirmiusPage<AirmiusConversation>> conversations({
    int page = 1,
    int? teamId,
  });
  Future<AirmiusConversation> createConversation({
    required String type,
    List<int> participantIds,
    int? teamId,
    String? name,
    String? description,
    String? message,
  });
  Future<AirmiusConversation> conversation(int conversationId);
  Future<AirmiusMessage> message(int messageId);
  Future<AirmiusPage<AirmiusMessage>> messages(
    int conversationId, {
    int page = 1,
  });
  Future<AirmiusMessage> sendMessage(int conversationId, String message);
  Future<void> markRead(int conversationId);
  Future<void> sendTyping(int conversationId, bool typing);
  Future<AirmiusConversation> updateConversation(
    int conversationId,
    JsonMap payload,
  );
  Future<AirmiusConversation> muteConversation(int conversationId, int minutes);
  Future<void> leaveConversation(int conversationId);
  Future<void> clearConversation(int conversationId);
  Future<AirmiusConversation> inviteConversationMembers(
    int conversationId,
    List<int> participantIds,
  );
  Future<AirmiusConversation> removeConversationMember(
    int conversationId,
    int userId,
  );
  Future<AirmiusConversation> transferConversationOwner(
    int conversationId,
    int userId,
  );
  Future<AirmiusConversation> updateConversationMemberRole(
    int conversationId,
    int userId,
    String role,
  );
  Future<void> deleteConversation(int conversationId);
}

abstract class AirmiusFeedRepository {
  Future<AirmiusPage<AirmiusPost>> feed({int page = 1});
  Future<AirmiusPost> post(int postId);
  Future<AirmiusPost> create({
    required String content,
    required String visibility,
  });
  Future<AirmiusPost> toggleLike(int postId);
  Future<AirmiusPost> toggleHelpful(int postId);
  Future<AirmiusPost> updatePost(
    int postId, {
    required String content,
    required String visibility,
    required String postType,
    required String contentOrigin,
    int? clubId,
    int? teamId,
    int? sportId,
    List<int> sportSkillIds = const [],
  });
  Future<void> deletePost(int postId);
  Future<void> reportContent({
    required String type,
    required int id,
    String reason = 'other',
    String? details,
  });
  Future<AirmiusPage<AirmiusComment>> comments(
    int postId, {
    int page = 1,
    int perPage = 20,
  });
  Future<AirmiusComment> createComment(int postId, String content);
  Future<AirmiusComment> updateComment(int commentId, String content);
  Future<void> deleteComment(int commentId);
  Future<List<AirmiusStory>> stories();
  Future<AirmiusStory> markStoryViewed(int storyId);
  Future<AirmiusStory> reactToStory(int storyId, String reaction);
  Future<void> deleteStory(int storyId);
}

abstract class AirmiusSearchRepository {
  Future<AirmiusPage<AirmiusSearchResult>> search({
    required String query,
    int page = 1,
  });
  Future<List<AirmiusSavedView>> savedViews({
    String workspace = 'global_search',
  });
  Future<AirmiusSavedView> createSavedView({
    required String name,
    required JsonMap configuration,
    String workspace = 'global_search',
  });
  Future<void> deleteSavedView(int savedViewId);
}

int _int(Object? value, {int fallback = 0}) {
  if (value is int) return value;
  if (value is num) return value.toInt();
  if (value is String) return int.tryParse(value) ?? fallback;
  return fallback;
}

int? _nullableInt(Object? value) {
  final parsed = _int(value);
  return parsed == 0 ? null : parsed;
}

double _double(Object? value, {double fallback = 0}) {
  if (value is double) return value;
  if (value is num) return value.toDouble();
  if (value is String) return double.tryParse(value) ?? fallback;
  return fallback;
}

List<JsonMap> _jsonList(Object? value) {
  if (value is List) return value.whereType<JsonMap>().toList();
  return const [];
}

String _string(Object? value, {String fallback = ''}) {
  if (value == null) return fallback;
  return value.toString();
}

String? _nullableString(Object? value) {
  final string = value?.toString().trim();
  return string == null || string.isEmpty ? null : string;
}

bool _blank(String? value) => value == null || value.trim().isEmpty;

String _storyMediaKind(Object? kind, Object? mediaType, String mediaUrl) {
  final explicit = (_nullableString(kind) ?? '').toLowerCase();
  final type = (_nullableString(mediaType) ?? '').toLowerCase();
  final path =
      Uri.tryParse(mediaUrl)?.path.toLowerCase() ?? mediaUrl.toLowerCase();

  if (explicit.contains('video') ||
      type.startsWith('video/') ||
      path.endsWith('.mp4') ||
      path.endsWith('.webm') ||
      path.endsWith('.ogg') ||
      path.endsWith('.mov')) {
    return 'video';
  }

  if (explicit.contains('image') || type.startsWith('image/')) {
    return 'image';
  }

  return explicit.isEmpty ? 'image' : explicit;
}

String? _firstImageAttachmentUrl(Object? attachments) {
  if (attachments is! List) return null;
  for (final attachment in attachments) {
    if (attachment is! JsonMap) continue;
    final nestedFile = attachment['file'];
    final file = nestedFile is JsonMap ? nestedFile : attachment;
    final type =
        _nullableString(file['mime_type']) ??
        _nullableString(file['type']) ??
        '';
    final candidate =
        _uploadFileUrl(file['url']) ??
        _uploadFileUrl(file['path']) ??
        _uploadFileUrl(file['thumbnail_url']) ??
        _uploadFileUrl(file['thumbnail_path']);
    if (candidate == null) continue;
    final lowerCandidate = candidate.toLowerCase();
    final lowerType = type.toLowerCase();
    if (lowerType.startsWith('image/') ||
        lowerCandidate.endsWith('.jpg') ||
        lowerCandidate.endsWith('.jpeg') ||
        lowerCandidate.endsWith('.png') ||
        lowerCandidate.endsWith('.webp') ||
        lowerCandidate.endsWith('.gif')) {
      return candidate;
    }
  }
  return null;
}

List<String> _postImageUrls({
  required Object? image,
  required Object? imageUrl,
  required Object? imageProxyUrl,
  required Object? uploadsBaseUrl,
}) {
  final urls = <String>[];
  void add(String? value) {
    final url = value?.trim();
    if (url == null || url.isEmpty || urls.contains(url)) return;
    urls.add(url);
  }

  final proxy = _mediaUrl(imageProxyUrl);
  if (proxy != null) {
    // The proxy enforces the post visibility policy. Do not add public storage
    // fallbacks when it is present, otherwise a private post could be bypassed.
    add(proxy);
    return urls;
  }

  final raw = _nullableString(image);
  if (raw != null) {
    if (raw.startsWith('data:image/') ||
        raw.startsWith('http://') ||
        raw.startsWith('https://')) {
      add(raw);
    } else {
      final base = _nullableString(uploadsBaseUrl);
      if (base != null && base.isNotEmpty) {
        add(
          '${base.replaceFirst(RegExp(r'/+$'), '')}/${raw.replaceFirst(RegExp(r'^/+'), '')}',
        );
      }

      add('https://cdn.airmius.com/${raw.replaceFirst(RegExp(r'^/+'), '')}');
      add(_mediaUrl(imageUrl));
      add(_mediaUrl(imageProxyUrl));

      if (raw.startsWith('/')) {
        add(_mediaUrl(raw));
      } else {
        final cleanPath = raw.replaceFirst(RegExp(r'^/+'), '');
        add(
          cleanPath.startsWith('storage/')
              ? '/$cleanPath'
              : '/storage/$cleanPath',
        );
        add(
          _mediaUrl(
            cleanPath.startsWith('storage/')
                ? '/$cleanPath'
                : '/storage/$cleanPath',
          ),
        );
      }
    }
  }

  add(_mediaUrl(imageUrl));
  add(_mediaUrl(imageProxyUrl));

  return urls;
}

List<AirmiusPostAttachment> _postAttachments(Object? attachments) {
  if (attachments is! List) return const [];
  final mapped = <AirmiusPostAttachment>[];
  for (final attachment in attachments) {
    if (attachment is! JsonMap) continue;
    final nestedFile = attachment['file'];
    final file = nestedFile is JsonMap ? nestedFile : attachment;
    final url = _uploadFileUrl(file['url']) ?? _uploadFileUrl(file['path']);
    if (url == null) continue;
    final type =
        (_nullableString(file['type']) ??
                _nullableString(file['mime_type']) ??
                '')
            .toLowerCase();
    final lowerUrl = url.toLowerCase();
    final kind =
        type.startsWith('image/') ||
            lowerUrl.endsWith('.jpg') ||
            lowerUrl.endsWith('.jpeg') ||
            lowerUrl.endsWith('.png') ||
            lowerUrl.endsWith('.webp') ||
            lowerUrl.endsWith('.gif')
        ? 'image'
        : type.startsWith('video/') ||
              lowerUrl.endsWith('.mp4') ||
              lowerUrl.endsWith('.mov') ||
              lowerUrl.endsWith('.webm') ||
              lowerUrl.endsWith('.ogg')
        ? 'video'
        : 'file';
    mapped.add(
      AirmiusPostAttachment(
        name: _string(file['display_name'] ?? file['name'], fallback: 'Datei'),
        url: url,
        kind: kind,
        thumbnailUrl:
            _uploadFileUrl(file['thumbnail_url']) ??
            _uploadFileUrl(file['thumbnail_path']),
      ),
    );
  }
  return mapped;
}

String? _postSportName(Object? sport) {
  if (sport is JsonMap) {
    return _nullableString(sport['name']) ??
        _nullableString(sport['title']) ??
        _nullableString(sport['slug']);
  }
  return _nullableString(sport);
}

List<String> _postSportSkills(Object? skills) {
  if (skills is! List) return const [];
  return skills
      .map((skill) {
        if (skill is JsonMap) {
          return _nullableString(skill['name']) ??
              _nullableString(skill['title']);
        }
        return _nullableString(skill);
      })
      .whereType<String>()
      .where((skill) => skill.trim().isNotEmpty)
      .toList();
}

List<int> _postSportSkillIds(Object? skills) {
  if (skills is! List) return const [];
  return skills
      .map((skill) {
        if (skill is JsonMap) return _int(skill['id']);
        return _int(skill);
      })
      .where((id) => id > 0)
      .toList();
}

List<int> _intList(Object? values) {
  if (values is! List) return const [];
  return values.map(_int).where((id) => id > 0).toList();
}

String? _mediaUrl(Object? value) {
  final string = _nullableString(value);
  if (string == null) return null;
  if (string.startsWith('data:image/') ||
      string.startsWith('http://') ||
      string.startsWith('https://')) {
    return string;
  }

  const origin = String.fromEnvironment(
    'AIRMIUS_API_BASE_URL',
    defaultValue: 'https://airmius.com',
  );
  final base = Uri.tryParse(origin);
  if (base == null || !base.hasScheme || base.host.isEmpty) return string;

  if (string.startsWith('/')) {
    return base
        .replace(path: _withBasePath(base, string), query: null, fragment: null)
        .toString();
  }

  final cleanPath = string.replaceFirst(RegExp(r'^/+'), '');
  final path =
      cleanPath.startsWith('storage/') ||
          cleanPath.startsWith('build/') ||
          cleanPath.startsWith('images/')
      ? '/$cleanPath'
      : '/storage/$cleanPath';
  return base
      .replace(path: _withBasePath(base, path), query: null, fragment: null)
      .toString();
}

String? _uploadFileUrl(Object? value) {
  final string = _nullableString(value);
  if (string == null) return null;
  if (string.startsWith('data:image/') ||
      string.startsWith('http://') ||
      string.startsWith('https://')) {
    return string;
  }

  final cleanPath = string.replaceFirst(RegExp(r'^/+'), '');
  if (cleanPath.startsWith('storage/')) {
    return _mediaUrl(cleanPath);
  }

  return 'https://cdn.airmius.com/$cleanPath';
}

String _withBasePath(Uri base, String path) {
  final cleanBase = base.path == '/'
      ? ''
      : base.path.replaceFirst(RegExp(r'/$'), '');
  if (cleanBase.isEmpty || path.startsWith('$cleanBase/')) return path;
  return '$cleanBase$path';
}

String? _userAvatarUrl(JsonMap json) {
  final userCard = json['user_card'];
  if (userCard is JsonMap) {
    return _mediaUrl(userCard['avatar_thumb']) ??
        _mediaUrl(userCard['avatar_url']) ??
        _mediaUrl(json['profile_photo_thumb']) ??
        _mediaUrl(json['profile_photo_url']) ??
        _mediaUrl(json['avatar_url']);
  }

  return _mediaUrl(json['profile_photo_thumb']) ??
      _mediaUrl(json['profile_photo_url']) ??
      _mediaUrl(json['avatar_url']);
}

bool _bool(Object? value) {
  if (value is bool) return value;
  if (value is num) return value != 0;
  if (value is String) return value == '1' || value.toLowerCase() == 'true';
  return false;
}

DateTime _date(Object? value) {
  if (value is DateTime) return value;
  if (value is String) {
    final normalized = value.trim();
    final parseValue = RegExp(r'[zZ]|[+-]\d\d:?\d\d$').hasMatch(normalized)
        ? normalized
        : '${normalized}Z';
    return DateTime.tryParse(parseValue) ??
        DateTime.fromMillisecondsSinceEpoch(0);
  }
  return DateTime.fromMillisecondsSinceEpoch(0);
}

DateTime? _optionalDate(Object? value) {
  if (value == null) return null;
  final parsed = _date(value);
  return parsed.millisecondsSinceEpoch == 0 ? null : parsed;
}

bool _isClubType(String type) {
  final normalized = type.toLowerCase();
  return normalized == 'verein' || normalized == 'club';
}

bool _isTeamType(String type) {
  return type.toLowerCase().contains('team');
}
