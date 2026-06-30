typedef JsonMap = Map<String, dynamic>;

class AirmiusUser {
  const AirmiusUser({
    required this.id,
    required this.name,
    required this.email,
    required this.role,
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
  });

  final int id;
  final String name;
  final String email;
  final String role;
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
    if (today.month < date.month || (today.month == date.month && today.day < date.day)) {
      age--;
    }
    return age < 16;
  }

  bool get requiresGuardianConsent => role.toLowerCase() == 'minor_pending_consent';

  factory AirmiusUser.fromJson(JsonMap json) => AirmiusUser(
        id: _int(json['id']),
        name: _string(json['name']),
        email: _string(json['email']),
        role: _userRole(json),
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
        followersCount: json.containsKey('followers_count') ? _int(json['followers_count']) : null,
        followingCount: json.containsKey('following_count') ? _int(json['following_count']) : null,
        postsCount: json.containsKey('posts_count') ? _int(json['posts_count']) : null,
        clubs: _jsonList(json['clubs']).map(AirmiusNamedItem.fromJson).toList(),
        teams: _jsonList(json['teams']).map(AirmiusNamedItem.fromJson).toList(),
        sportProfiles: _jsonList(json['sport_profiles']).map(AirmiusUserSportProfile.fromJson).toList(),
        badges: _jsonList(json['badges']).map(AirmiusUserBadge.fromJson).toList(),
        gamification: json['gamification'] is JsonMap ? AirmiusGamification.fromJson(json['gamification'] as JsonMap) : null,
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
  const AirmiusNamedItem({required this.id, required this.name, this.subtitle});

  final int id;
  final String name;
  final String? subtitle;

  factory AirmiusNamedItem.fromJson(JsonMap json) => AirmiusNamedItem(
        id: _int(json['id']),
        name: _string(json['name'] ?? json['title'], fallback: 'Eintrag'),
        subtitle: _nullableString(json['sport_type'] ?? json['city'] ?? json['description']),
      );
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
      sportName: sport is JsonMap ? _string(sport['name'] ?? sport['slug'], fallback: 'Sportart') : _string(json['sport'], fallback: 'Sportart'),
      status: _nullableString(json['status']),
      experienceLevel: _nullableString(json['experience_level']),
      metrics: json['performance_metrics'] is JsonMap ? json['performance_metrics'] as JsonMap : const {},
    );
  }
}

class AirmiusUserBadge {
  const AirmiusUserBadge({required this.id, required this.name, this.description, this.icon});

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
    required this.name,
    required this.city,
    required this.membersCount,
    required this.teamsCount,
    required this.acceptsMembershipApplications,
    required this.hasPendingMembershipRequest,
    required this.isMember,
    this.teams = const [],
    this.logoUrl,
    this.bannerUrl,
    this.sportType,
    this.postalCode,
    this.country,
    this.canManage = false,
    this.canDelete = false,
    this.management,
  });

  final int id;
  final String name;
  final String city;
  final int membersCount;
  final int teamsCount;
  final bool acceptsMembershipApplications;
  final bool hasPendingMembershipRequest;
  final bool isMember;
  final List<AirmiusTeam> teams;
  final String? logoUrl;
  final String? bannerUrl;
  final String? sportType;
  final String? postalCode;
  final String? country;
  final bool canManage;
  final bool canDelete;
  final AirmiusClubManagement? management;

  factory AirmiusClub.fromJson(JsonMap json) => AirmiusClub(
        id: _int(json['id']),
        name: _string(json['name'] ?? json['title'], fallback: 'Verein'),
        city: _string(json['city'] ?? json['subtitle'] ?? json['description']),
        membersCount: _int(json['members_count'], fallback: _int(json['users_count'])),
        teamsCount: _int(json['teams_count'], fallback: _clubTeams(json['teams']).length),
        acceptsMembershipApplications: _bool(json['accepts_membership_applications']) || _bool(json['membership_requests_enabled']),
        hasPendingMembershipRequest: _bool(json['has_pending_membership_request']),
        isMember: _bool(json['is_member']),
        teams: _clubTeams(json['teams']),
        logoUrl: _mediaUrl(json['logo_url'] ?? json['logo']),
        bannerUrl: _mediaUrl(json['banner_url'] ?? json['cover_image_url'] ?? json['cover_image'] ?? json['cover']),
        sportType: _nullableString(json['sport_type']),
        postalCode: _nullableString(json['postal_code']),
        country: _nullableString(json['country']),
        canManage: _bool(json['can_manage']) || _bool(json['can_update']),
        canDelete: _bool(json['can_delete']) || _bool(json['can_destroy']),
        management: json['management'] is JsonMap ? AirmiusClubManagement.fromJson(json['management'] as JsonMap) : null,
      );
}

List<AirmiusTeam> _clubTeams(Object? value) =>
    value is List ? value.whereType<JsonMap>().map(AirmiusTeam.fromJson).toList() : const [];

class AirmiusClubManagement {
  const AirmiusClubManagement({
    required this.canManage,
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
    this.invoices = const [],
    this.payments = const [],
    this.financeEntries = const [],
    this.bankTransactions = const [],
    this.membershipStatuses = const [],
    this.contributionIntervals = const [],
    this.teamRoles = const [],
    this.teams = const [],
  });

  final bool canManage;
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
  final List<JsonMap> invoices;
  final List<JsonMap> payments;
  final List<JsonMap> financeEntries;
  final List<JsonMap> bankTransactions;
  final List<String> membershipStatuses;
  final List<String> contributionIntervals;
  final List<String> teamRoles;
  final List<AirmiusTeam> teams;

  int get pendingMembershipRequestsCount =>
      _int(summary['pending_membership_requests_count'], fallback: membershipRequests.where((request) => request.status == 'pending').length);

  int get pendingTeamJoinRequestsCount => _int(summary['pending_team_join_requests_count'], fallback: pendingTeamJoinRequests.length);

  int get activeMembersCount => _int(summary['active_members_count'], fallback: members.length);

  int get linkedPeopleCount => _int(summary['linked_people_count'], fallback: members.length + externalMembers.length);

  double get openInvoiceAmount => _double(summary['open_invoice_amount']);

  int get openInvoicesCount => _int(summary['open_invoices_count'], fallback: invoices.length);

  int get sepaReadyMembersCount => _int(summary['sepa_ready_members_count']);

  double get recurringContributionTotal => _double(summary['recurring_contribution_total']);

  double get cashBalance => _double(summary['cash_balance']);

  double get bankBalance => _double(summary['bank_balance']);

  double get unassignedBalance => _double(summary['unassigned_balance']);

  double get totalBalance => _double(summary['total_balance']);

  double get incomeTotal => _double(summary['income_total']);

  double get expenseTotal => _double(summary['expense_total']);

  factory AirmiusClubManagement.fromJson(JsonMap json) => AirmiusClubManagement(
        canManage: _bool(json['can_manage']),
        summary: json['summary'] is JsonMap ? json['summary'] as JsonMap : const {},
        settings: json['settings'] is JsonMap ? json['settings'] as JsonMap : const {},
        subscription: json['subscription'] is JsonMap ? json['subscription'] as JsonMap : const {},
        capabilities: json['capabilities'] is JsonMap ? json['capabilities'] as JsonMap : const {},
        members: _jsonList(json['members']).map(AirmiusClubMember.fromJson).toList(),
        externalMembers: _jsonList(json['external_members']),
        membershipRequests: _jsonList(json['membership_requests'] ?? json['club_requests']).map(AirmiusClubMembershipRequest.fromJson).toList(),
        pendingTeamJoinRequests: _jsonList(json['pending_team_join_requests'] ?? json['pending_requests']),
        membershipTypes: _jsonList(json['membership_types']),
        contributionRules: _jsonList(json['contribution_rules']),
        invoices: _jsonList(json['invoices']),
        payments: _jsonList(json['payments']),
        financeEntries: _jsonList(json['finance_entries']),
        bankTransactions: _jsonList(json['bank_transactions']),
        membershipStatuses: _stringList(json['membership_statuses']),
        contributionIntervals: _stringList(json['contribution_intervals']),
        teamRoles: _stringList(json['team_roles']),
        teams: _clubTeams(json['teams']),
      );
}

class AirmiusClubMember {
  const AirmiusClubMember({
    required this.id,
    required this.name,
    required this.email,
    this.licenseNumber,
    this.avatarUrl,
    this.membership = const {},
    this.invoicesCount = 0,
    this.paymentsCount = 0,
  });

  final int id;
  final String name;
  final String email;
  final String? licenseNumber;
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
        licenseNumber: _nullableString(json['athlete_license_number']),
        avatarUrl: _mediaUrl(json['profile_photo_url'] ?? json['profile_photo_thumb']),
        membership: json['membership'] is JsonMap ? json['membership'] as JsonMap : const {},
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
    this.description,
    this.sportType,
    this.ageGroup,
    this.visibility,
    this.logoUrl,
    this.attendanceStats,
    this.users = const [],
    this.canManage = false,
    this.canDelete = false,
    this.viewerIsMember = false,
    this.canRequestJoin = false,
    this.viewerPendingJoinRequestId,
    this.pendingJoinRequests = const [],
    this.usersCount,
    this.eventsCount,
  });

  final int id;
  final int clubId;
  final String name;
  final String? clubName;
  final String? description;
  final String? sportType;
  final String? ageGroup;
  final String? visibility;
  final String? logoUrl;
  final AirmiusTeamAttendanceStats? attendanceStats;
  final List<AirmiusUser> users;
  final bool canManage;
  final bool canDelete;
  final bool viewerIsMember;
  final bool canRequestJoin;
  final int? viewerPendingJoinRequestId;
  final List<AirmiusTeamJoinRequest> pendingJoinRequests;
  final int? usersCount;
  final int? eventsCount;

  factory AirmiusTeam.fromJson(JsonMap json) {
    final club = json['club'];
    return AirmiusTeam(
      id: _int(json['id']),
      clubId: _int(json['club_id']),
      name: _string(json['name'] ?? json['title'], fallback: 'Team'),
      clubName: (club is JsonMap ? _nullableString(club['name']) : null) ?? _nullableString(json['club_name']),
      description: _nullableString(json['description'] ?? json['subtitle']),
      sportType: _nullableString(json['sport_type']),
      ageGroup: _nullableString(json['age_group']),
      visibility: _nullableString(json['visibility']),
      logoUrl: _mediaUrl(json['logo_url'] ?? json['logo']),
      attendanceStats: json['attendance_stats'] is JsonMap ? AirmiusTeamAttendanceStats.fromJson(json['attendance_stats'] as JsonMap) : null,
      users: _jsonList(json['users']).map(AirmiusUser.fromJson).toList(),
      canManage: _bool(json['can_manage']) || _bool(json['can_update']),
      canDelete: _bool(json['can_delete']) || _bool(json['can_destroy']),
      viewerIsMember: _bool(json['viewer_is_member']),
      canRequestJoin: _bool(json['can_request_join']),
      viewerPendingJoinRequestId: _nullableInt(json['viewer_pending_join_request_id']),
      pendingJoinRequests: _jsonList(json['pending_join_requests'] ?? json['pending_team_join_requests'] ?? json['pending_requests']).map(AirmiusTeamJoinRequest.fromJson).toList(),
      usersCount: json.containsKey('users_count') ? _int(json['users_count']) : null,
      eventsCount: json.containsKey('events_count') ? _int(json['events_count']) : null,
    );
  }

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
    final user = json['user'] is JsonMap ? json['user'] as JsonMap : const <String, dynamic>{};
    return AirmiusTeamJoinRequest(
      id: _int(json['id']),
      teamId: _int(json['team_id']),
      userId: _int(json['user_id'] ?? user['id']),
      name: _string(json['name'] ?? json['user_name'] ?? user['name'], fallback: 'Mitglied'),
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
    final team = json['team'] is JsonMap ? json['team'] as JsonMap : const <String, dynamic>{};
    final club = team['club'] is JsonMap ? team['club'] as JsonMap : const <String, dynamic>{};
    final inviter = json['inviter'] is JsonMap ? json['inviter'] as JsonMap : const <String, dynamic>{};

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

  factory AirmiusTeamAttendanceStats.fromJson(JsonMap json) => AirmiusTeamAttendanceStats(
        trainingsTotal: _int(json['trainings_total']),
        membersTotal: _int(json['members_total']),
        members: _jsonList(json['members']).map(AirmiusTeamAttendanceMember.fromJson).toList(),
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

  factory AirmiusTeamAttendanceMember.fromJson(JsonMap json) => AirmiusTeamAttendanceMember(
        userId: _int(json['user_id'] ?? json['id']),
        name: _string(json['name'], fallback: 'Mitglied'),
        email: _nullableString(json['email']),
        avatarUrl: _mediaUrl(json['profile_photo_url'] ?? json['avatar_url'] ?? json['profile_photo_path']),
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
      skills: skills is List ? skills.whereType<JsonMap>().map(AirmiusSportSkill.fromJson).toList() : const [],
    );
  }
}

class AirmiusSportSkill {
  const AirmiusSportSkill({
    required this.id,
    required this.name,
  });

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

  factory AirmiusMembershipApplication.fromJson(JsonMap json) => AirmiusMembershipApplication(
        id: _int(json['id']),
        clubId: _int(json['club_id']),
        status: _string(json['status'], fallback: 'pending'),
        submittedAt: _date(json['submitted_at'] ?? json['created_at']),
        withdrawnAt: json['withdrawn_at'] == null ? null : _date(json['withdrawn_at']),
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
    this.reviewNote,
    this.applicantName,
    this.applicantEmail,
    this.clubName,
    this.membershipTypeName,
    this.preferredPaymentMethod,
    this.requestedBillingInterval,
    this.previewAmount,
    this.previewInterval,
    this.applicationData = const {},
    this.acceptedDocuments = const [],
  });

  factory AirmiusClubMembershipRequest.fromJson(JsonMap json) {
    final user = json['user'];
    final club = json['club'];
    final membershipType = json['membership_type'];
    final applicationData = json['application_data'];
    final acceptedDocuments = json['accepted_documents'];

    return AirmiusClubMembershipRequest(
      id: _int(json['id']),
      clubId: _int(json['club_id']),
      userId: _int(json['user_id']),
      type: _string(json['type'], fallback: 'membership'),
      status: _string(json['status'], fallback: 'pending'),
      message: _nullableString(json['message']),
      reviewNote: _nullableString(json['review_note']),
      applicantName: (user is JsonMap ? _nullableString(user['name']) : null) ?? _nullableString(json['applicant_name']),
      applicantEmail: (user is JsonMap ? _nullableString(user['email']) : null) ?? _nullableString(json['applicant_email']),
      clubName: (club is JsonMap ? _nullableString(club['name']) : null) ?? _nullableString(json['club_name']),
      membershipTypeName: membershipType is JsonMap ? _nullableString(membershipType['name']) : _nullableString(json['membership_type_name']),
      preferredPaymentMethod: _nullableString(json['preferred_payment_method']),
      requestedBillingInterval: _nullableString(json['requested_billing_interval']),
      previewAmount: _nullableString(json['preview_amount']),
      previewInterval: _nullableString(json['preview_interval']),
      applicationData: applicationData is JsonMap ? applicationData : const {},
      acceptedDocuments: acceptedDocuments is List ? acceptedDocuments.map((item) => '$item').toList() : const [],
      createdAt: _date(json['created_at']),
    );
  }

  final int id;
  final int clubId;
  final int userId;
  final String type;
  final String status;
  final String? message;
  final String? reviewNote;
  final String? applicantName;
  final String? applicantEmail;
  final String? clubName;
  final String? membershipTypeName;
  final String? preferredPaymentMethod;
  final String? requestedBillingInterval;
  final String? previewAmount;
  final String? previewInterval;
  final JsonMap applicationData;
  final List<String> acceptedDocuments;
  final DateTime createdAt;
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
    required this.yesCount,
    required this.maybeCount,
    required this.noCount,
    required this.canJoin,
    this.usesPenaltyCatalog = false,
    this.participants = const [],
    this.clubId,
    this.teamId,
    this.clubName,
    this.teamName,
    this.notes,
    this.location,
    this.maxParticipants,
    this.myParticipationStatus,
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
  final int? maxParticipants;
  final int participantsCount;
  final int commentsCount;
  final int yesCount;
  final int maybeCount;
  final int noCount;
  final String? myParticipationStatus;
  final bool canJoin;
  final bool usesPenaltyCatalog;
  final List<AirmiusEventParticipant> participants;

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
    int? maxParticipants,
    int? participantsCount,
    int? commentsCount,
    int? yesCount,
    int? maybeCount,
    int? noCount,
    String? myParticipationStatus,
    bool? canJoin,
    bool? usesPenaltyCatalog,
    List<AirmiusEventParticipant>? participants,
  }) =>
      AirmiusEvent(
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
        maxParticipants: maxParticipants ?? this.maxParticipants,
        participantsCount: participantsCount ?? this.participantsCount,
        commentsCount: commentsCount ?? this.commentsCount,
        yesCount: yesCount ?? this.yesCount,
        maybeCount: maybeCount ?? this.maybeCount,
        noCount: noCount ?? this.noCount,
        myParticipationStatus: myParticipationStatus ?? this.myParticipationStatus,
        canJoin: canJoin ?? this.canJoin,
        usesPenaltyCatalog: usesPenaltyCatalog ?? this.usesPenaltyCatalog,
        participants: participants ?? this.participants,
      );

  factory AirmiusEvent.fromJson(JsonMap json) {
    final club = json['club'];
    final team = json['team'];
    final locationParts = [
      _nullableString(json['location_name']),
      _nullableString(json['location']),
      _nullableString(json['location_city']),
    ].whereType<String>().where((value) => value.isNotEmpty).toList();

    return AirmiusEvent(
      id: _int(json['id']),
      title: _string(json['title']),
      startsAt: _date(json['starts_at'] ?? json['start_time']),
      endsAt: json['ends_at'] == null && json['end_time'] == null ? null : _date(json['ends_at'] ?? json['end_time']),
      type: _string(json['type'], fallback: 'event'),
      status: _string(json['status'], fallback: 'open'),
      visibility: _string(json['visibility'], fallback: 'public'),
      clubId: json['club_id'] == null ? null : _int(json['club_id']),
      teamId: json['team_id'] == null ? null : _int(json['team_id']),
      clubName: club is JsonMap ? _nullableString(club['name']) : null,
      teamName: team is JsonMap ? _nullableString(team['name']) : null,
      notes: _nullableString(json['notes']),
      location: locationParts.isEmpty ? null : locationParts.join(' - '),
      maxParticipants: json['max_participants'] == null ? null : _int(json['max_participants']),
      participantsCount: _int(json['participants_count']),
      commentsCount: _int(json['comments_count']),
      yesCount: _int(json['yes_count']),
      maybeCount: _int(json['maybe_count']),
      noCount: _int(json['no_count']),
      myParticipationStatus: _nullableString(json['my_participation_status']),
      canJoin: _bool(json['can_join']),
      usesPenaltyCatalog: _bool(json['uses_penalty_catalog']),
      participants: _eventParticipants(json['participants']),
    );
  }
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
      avatarUrl: _mediaUrl(json['profile_photo_url'] ?? json['avatar_url'] ?? json['profile_photo_path']),
      status: pivot is JsonMap ? _string(pivot['status'], fallback: 'yes') : _string(json['status'], fallback: 'yes'),
    );
  }
}

List<AirmiusEventParticipant> _eventParticipants(Object? value) =>
    value is List ? value.whereType<JsonMap>().map(AirmiusEventParticipant.fromJson).toList() : const [];

class AirmiusInvoice {
  const AirmiusInvoice({
    required this.id,
    required this.number,
    required this.status,
    required this.amountCents,
    required this.currency,
  });

  final int id;
  final String number;
  final String status;
  final int amountCents;
  final String currency;

  factory AirmiusInvoice.fromJson(JsonMap json) => AirmiusInvoice(
        id: _int(json['id']),
        number: _string(json['number']),
        status: _string(json['status'], fallback: 'open'),
        amountCents: _int(json['amount_cents']),
        currency: _string(json['currency'], fallback: 'EUR'),
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
    return AirmiusNotification(
      id: json['id'] as int? ?? int.tryParse('${json['id'] ?? 0}') ?? 0,
      type: '${json['type'] ?? json['category'] ?? 'System'}',
      title: '${json['title'] ?? dataMap['title'] ?? json['subject'] ?? 'Benachrichtigung'}',
      body: '${json['body'] ?? dataMap['body'] ?? dataMap['message'] ?? json['message'] ?? json['description'] ?? ''}',
      timeLabel: '${json['time_label'] ?? json['time'] ?? json['created_at'] ?? 'Jetzt'}',
      unread: json['unread'] as bool? ?? !(json['read'] as bool? ?? json['read_at'] != null),
      data: dataMap,
      actionUrl: json['action_url'] as String? ?? json['url'] as String? ?? dataMap['url'] as String?,
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

class AirmiusConversation {
  const AirmiusConversation({
    required this.id,
    required this.title,
    required this.kind,
    required this.lastMessage,
    required this.timeLabel,
    required this.unreadCount,
  });

  factory AirmiusConversation.fromJson(JsonMap json) {
    final latest = json['latest_message'];
    final users = json['users'];
    final fallbackUser = users is List && users.isNotEmpty && users.first is JsonMap ? _string((users.first as JsonMap)['name']) : '';
    return AirmiusConversation(
      id: _int(json['id']),
      title: _string(json['title'] ?? json['name'] ?? json['subject'], fallback: fallbackUser.isEmpty ? 'Konversation' : fallbackUser),
      kind: _string(json['kind'] ?? json['type'] ?? json['scope'], fallback: 'Chat'),
      lastMessage: latest is JsonMap ? _string(latest['message']) : _string(json['last_message'] ?? json['lastMessage'] ?? json['preview']),
      timeLabel: _string(json['time_label'] ?? json['time'] ?? json['updated_at'] ?? json['created_at'], fallback: 'Jetzt'),
      unreadCount: _int(json['unread_messages_count'] ?? json['unread_count'] ?? json['unread']),
    );
  }

  final int id;
  final String title;
  final String kind;
  final String lastMessage;
  final String timeLabel;
  final int unreadCount;
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
    this.reactions = const [],
  });

  factory AirmiusMessage.fromJson(JsonMap json) {
    final sender = json['sender'];
    final senderId = _int(json['sender_id']);
    final currentUserId = _int(json['current_user_id'], fallback: -1);
    final reactions = json['reactions'];
    return AirmiusMessage(
      id: _int(json['id']),
      conversationId: _int(json['conversation_id']),
      message: _string(json['message']),
      senderName: sender is JsonMap ? _string(sender['name'], fallback: 'Airmius') : 'Airmius',
      createdAt: _date(json['created_at']),
      mine: currentUserId >= 0 ? senderId == currentUserId : _bool(json['mine'] ?? json['is_mine']),
      status: _string(json['status'] ?? json['delivery_status'], fallback: 'sent'),
      reactions: reactions is List ? reactions.whereType<JsonMap>().map(AirmiusMessageReaction.fromJson).toList() : const [],
    );
  }

  AirmiusMessage copyWith({
    String? message,
    String? senderName,
    DateTime? createdAt,
    bool? mine,
    String? status,
    List<AirmiusMessageReaction>? reactions,
  }) => AirmiusMessage(
    id: id,
    conversationId: conversationId,
    message: message ?? this.message,
    senderName: senderName ?? this.senderName,
    createdAt: createdAt ?? this.createdAt,
    mine: mine ?? this.mine,
    status: status ?? this.status,
    reactions: reactions ?? this.reactions,
  );

  final int id;
  final int conversationId;
  final String message;
  final String senderName;
  final DateTime createdAt;
  final bool mine;
  final String status;
  final List<AirmiusMessageReaction> reactions;
}

class AirmiusMessageReaction {
  const AirmiusMessageReaction({
    required this.id,
    required this.userId,
    required this.reaction,
  });

  factory AirmiusMessageReaction.fromJson(JsonMap json) => AirmiusMessageReaction(
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
    final fallbackImageUrl = _firstImageAttachmentUrl(json['attachments']) ?? _firstImageAttachmentUrl(json['files']);
    return AirmiusPost(
      id: _int(json['id']),
      userId: _int(json['user_id'], fallback: user is JsonMap ? _int(user['id']) : 0),
      content: _string(json['content']),
      visibility: _string(json['visibility'], fallback: 'public'),
      moderationStatus: _string(json['moderation_status'], fallback: 'approved'),
      postType: _string(json['post_type'], fallback: 'normal'),
      contentOrigin: _string(json['content_origin'], fallback: 'self'),
      clubId: _nullableInt(json['club_id']),
      teamId: _nullableInt(json['team_id']),
      sportId: _nullableInt(json['sport_id']),
      authorName: user is JsonMap ? _string(user['name'], fallback: 'Airmius') : 'Airmius',
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
      commentsCount: _int(json['comments_count'], fallback: _int(json['comments'])),
      likesCount: _int(json['likes_count'], fallback: _int(json['likes'])),
      helpfulsCount: _int(json['helpfuls_count'], fallback: _int(json['helpful_count'])),
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
      authorName: user is JsonMap ? _string(user['name'], fallback: 'Airmius') : 'Airmius',
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
    final mediaUrl = _mediaUrl(json['media_url']) ?? _mediaUrl(json['media_path']) ?? _mediaUrl(json['url']) ?? _mediaUrl(json['path']) ?? '';
    return AirmiusStory(
      id: _int(json['id']),
      actorId: actor is JsonMap ? _int(actor['id']) : _int(json['user_id'] ?? json['actor_id']),
      actorType: actor is JsonMap ? _string(actor['type'], fallback: 'user') : _string(json['actor_type'] ?? json['publisher_type'], fallback: 'user'),
      actorName: actor is JsonMap ? _string(actor['name'], fallback: 'Airmius') : 'Airmius',
      actorAvatarUrl: actor is JsonMap ? _userAvatarUrl(actor) : null,
      visibility: _string(json['visibility'], fallback: 'public'),
      caption: _nullableString(json['caption']),
      mediaUrl: mediaUrl,
      thumbnailUrl: _mediaUrl(json['media_thumbnail_url']) ?? _mediaUrl(json['media_thumbnail_path']) ?? _mediaUrl(json['thumbnail_url']) ?? _mediaUrl(json['thumbnail_path']),
      mediaKind: _storyMediaKind(json['media_kind'], json['media_type'], mediaUrl),
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
  });

  factory AirmiusSearchResult.fromJson(JsonMap json) {
    final type = _string(json['type'] ?? json['result_type'], fallback: 'Person');
    final clubJson = json['club'];
    final teamJson = json['team'];
    return AirmiusSearchResult(
      id: _int(json['id']),
      type: type,
      title: _string(json['title'] ?? json['name']),
      subtitle: _string(json['subtitle'] ?? json['description'] ?? json['city']),
      imageUrl: _mediaUrl(json['avatar_url'] ?? json['profile_photo_url'] ?? json['profile_photo_path'] ?? json['image_url'] ?? json['logo_url'] ?? json['logo']),
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
    );
  }

  final int id;
  final String type;
  final String title;
  final String subtitle;
  final AirmiusClub? club;
  final AirmiusTeam? team;
  final String? imageUrl;
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
    final list = rawItems is List ? rawItems.whereType<JsonMap>().map(map).toList() : <T>[];
    final meta = json['meta'];
    return AirmiusPage<T>(
      items: list,
      currentPage: meta is JsonMap ? _int(meta['current_page'], fallback: 1) : _int(json['current_page'], fallback: 1),
      lastPage: meta is JsonMap ? _int(meta['last_page'], fallback: 1) : _int(json['last_page'], fallback: 1),
      unreadCount: meta is JsonMap && meta.containsKey('unread_count') ? _int(meta['unread_count']) : null,
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
  final int currentPage;
  final int lastPage;

  factory AirmiusEventWorkspace.fromJson(JsonMap json) {
    final meta = json['meta'];
    return AirmiusEventWorkspace(
      events: _eventList(json['data']),
      calendarEvents: _eventList(json['calendar_events']),
      stats: AirmiusEventStats.fromJson(json['event_stats']),
      nextEvent: json['next_event'] is JsonMap ? AirmiusEvent.fromJson(json['next_event'] as JsonMap) : null,
      eventTypes: _stringList(json['event_types']),
      visibilities: _stringList(json['visibilities']),
      clubs: _clubList(json['clubs']),
      teams: _teamList(json['teams']),
      sports: _sportList(json['sports']),
      currentPage: meta is JsonMap ? _int(meta['current_page'], fallback: 1) : 1,
      lastPage: meta is JsonMap ? _int(meta['last_page'], fallback: 1) : 1,
    );
  }

  AirmiusEventWorkspace copyWith({
    List<AirmiusEvent>? events,
    List<AirmiusEvent>? calendarEvents,
    AirmiusEventStats? stats,
    AirmiusEvent? nextEvent,
  }) =>
      AirmiusEventWorkspace(
        events: events ?? this.events,
        calendarEvents: calendarEvents ?? this.calendarEvents,
        stats: stats ?? this.stats,
        nextEvent: nextEvent ?? this.nextEvent,
        eventTypes: eventTypes,
        visibilities: visibilities,
        clubs: clubs,
        teams: teams,
        sports: sports,
        currentPage: currentPage,
        lastPage: lastPage,
      );
}

List<AirmiusEvent> _eventList(Object? value) => value is List ? value.whereType<JsonMap>().map(AirmiusEvent.fromJson).toList() : const [];

List<AirmiusClub> _clubList(Object? value) => value is List ? value.whereType<JsonMap>().map(AirmiusClub.fromJson).toList() : const [];

List<AirmiusTeam> _teamList(Object? value) => value is List ? value.whereType<JsonMap>().map(AirmiusTeam.fromJson).toList() : const [];

List<AirmiusSport> _sportList(Object? value) => value is List ? value.whereType<JsonMap>().map(AirmiusSport.fromJson).toList() : const [];

List<String> _stringList(Object? value) => value is List ? value.map((item) => item.toString()).where((item) => item.isNotEmpty).toList() : const [];

abstract class AirmiusAuthRepository {
  Future<AirmiusUser> currentUser();
  Future<AirmiusUser> login({required String email, required String password});
}

abstract class AirmiusClubRepository {
  Future<AirmiusPage<AirmiusClub>> searchClubs({String? query, int page = 1, bool mine = false});
  Future<AirmiusClub> club(int id);
  Future<AirmiusClub> createClub(JsonMap payload);
  Future<AirmiusClubManagement> updateMembershipSettings(int clubId, JsonMap payload);
  Future<AirmiusClubManagement> createMembershipType(int clubId, JsonMap payload);
  Future<AirmiusClubManagement> updateMembershipType(int clubId, int typeId, JsonMap payload);
  Future<AirmiusClubManagement> createContributionRule(int clubId, JsonMap payload);
  Future<AirmiusClubManagement> updateContributionRule(int clubId, int ruleId, JsonMap payload);
  Future<AirmiusClubManagement> recordMembershipPayment(int clubId, int invoiceId, JsonMap payload);
  Future<AirmiusClubManagement> recordDonation(int clubId, JsonMap payload);
  Future<AirmiusClubManagement> recordPrepayment(int clubId, JsonMap payload);
  Future<AirmiusClubManagement> updatePayment(int clubId, int paymentId, JsonMap payload);
  Future<AirmiusClubManagement> createFinanceEntry(int clubId, JsonMap payload);
  Future<AirmiusClubManagement> updateFinanceEntry(int clubId, int entryId, JsonMap payload);
  Future<AirmiusClubManagement> inviteClubMember(int clubId, JsonMap payload);
  Future<AirmiusClubManagement> updateClubMemberRole(int clubId, int userId, String role);
  Future<AirmiusPage<AirmiusTeam>> teams({int page = 1});
  Future<AirmiusTeam> team(int id);
  Future<AirmiusTeam> createTeam(JsonMap payload);
  Future<AirmiusTeam> updateTeam(int id, JsonMap payload);
  Future<void> deleteTeam(int id);
  Future<AirmiusTeam> requestTeamJoin(int id);
  Future<AirmiusTeam> approveTeamJoinRequest(int teamId, int requestId, {String role = 'Player'});
  Future<AirmiusTeam> declineTeamJoinRequest(int teamId, int requestId);
  Future<AirmiusTeamInvitation> inviteTeamMember(int teamId, {required String email, required String role});
  Future<List<AirmiusTeamInvitation>> teamInvitations();
  Future<AirmiusTeamInvitation> teamInvitation(int invitationId);
  Future<AirmiusTeam> acceptTeamInvitation(int invitationId);
  Future<AirmiusTeamInvitation> declineTeamInvitation(int invitationId);
  Future<AirmiusTeam> updateTeamMemberRole(int teamId, int userId, String role);
}

abstract class AirmiusSportRepository {
  Future<AirmiusPage<AirmiusSport>> sports({int page = 1});
}

abstract class AirmiusMembershipRepository {
  Future<AirmiusClubMembershipRequest> applyToClub(int clubId, JsonMap payload);
  Future<AirmiusMembershipApplication> application(int applicationId);
  Future<AirmiusMembershipApplication> withdraw(int applicationId);
  Future<AirmiusPage<AirmiusClubMembershipRequest>> clubRequests(int clubId, {int page = 1});
  Future<AirmiusClubMembershipRequest> withdrawClubRequest(int clubId);
  Future<AirmiusClubMembershipRequest> approveClubRequest(int clubId, int requestId, {String? reviewNote});
  Future<AirmiusClubMembershipRequest> declineClubRequest(int clubId, int requestId, {String? reviewNote});
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
  });

  final String scope;
  final AirmiusFolder? currentFolder;
  final List<AirmiusFolder> folders;
  final List<AirmiusManagedFile> files;
  final AirmiusStorageUsage storage;
  final AirmiusPagination filesPagination;
  final AirmiusPagination foldersPagination;
  final String search;
  final String sort;

  factory AirmiusFileWorkspace.fromJson(JsonMap json) {
    final data = json['data'] is JsonMap ? json['data'] as JsonMap : json;
    final scope = data['scope'];
    final currentFolder = data['current_folder'];
    return AirmiusFileWorkspace(
      scope: scope is JsonMap ? _string(scope['type'], fallback: 'user') : 'user',
      currentFolder: currentFolder is JsonMap ? AirmiusFolder.fromJson(currentFolder) : null,
      folders: _jsonList(data['folders']).map(AirmiusFolder.fromJson).toList(),
      files: _jsonList(data['files']).map(AirmiusManagedFile.fromJson).toList(),
      storage: AirmiusStorageUsage.fromJson(data['storage_usage'] is JsonMap ? data['storage_usage'] as JsonMap : const {}),
      filesPagination: AirmiusPagination.fromJson(data['files_pagination'] is JsonMap ? data['files_pagination'] as JsonMap : const {}),
      foldersPagination: AirmiusPagination.fromJson(data['folders_pagination'] is JsonMap ? data['folders_pagination'] as JsonMap : const {}),
      search: _string(data['search']),
      sort: _string(data['sort'], fallback: 'name-asc'),
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

class AirmiusManagedFile {
  const AirmiusManagedFile({
    required this.id,
    required this.name,
    required this.type,
    required this.size,
    required this.url,
    this.folderId,
  });

  final int id;
  final String name;
  final String type;
  final int size;
  final String url;
  final int? folderId;

  factory AirmiusManagedFile.fromJson(JsonMap json) => AirmiusManagedFile(
        id: _int(json['id']),
        name: _string(json['display_name'] ?? json['name'], fallback: 'Datei'),
        type: _string(json['type'], fallback: 'Datei'),
        size: _int(json['size']),
        url: _string(json['url'] ?? json['path']),
        folderId: _nullableInt(json['folder_id']),
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

  factory AirmiusPagination.fromJson(JsonMap json) => AirmiusPagination(
        currentPage: _int(json['current_page'], fallback: 1),
        lastPage: _int(json['last_page'], fallback: 1),
        total: _int(json['total']),
        from: _nullableInt(json['from']),
        to: _nullableInt(json['to']),
      );
}

abstract class AirmiusFileRepository {
  Future<JsonMap> createUploadIntent({required String scope, required String fileName, required String mimeType});
  Future<AirmiusFileWorkspace> workspace({String scope = 'user', int? folderId, String? search, String sort = 'name-asc', int page = 1});
  Future<AirmiusFolder> createFolder({required String scope, required String name, int? parentId});
  Future<AirmiusFolder> renameFolder(int folderId, String name);
  Future<void> deleteFolder(int folderId);
  Future<AirmiusManagedFile> renameFile(int fileId, String name);
  Future<void> deleteFile(int fileId);
}

abstract class AirmiusEventRepository {
  Future<AirmiusPage<AirmiusEvent>> events({int page = 1, DateTime? from, DateTime? to});
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
  Future<AirmiusEvent> respond(int eventId, String status);
  Future<AirmiusEvent> leave(int eventId);
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
  Future<AirmiusPage<AirmiusConversation>> conversations({int page = 1});
  Future<AirmiusConversation> conversation(int conversationId);
  Future<AirmiusPage<AirmiusMessage>> messages(int conversationId, {int page = 1});
  Future<AirmiusMessage> sendMessage(int conversationId, String message);
}

abstract class AirmiusFeedRepository {
  Future<AirmiusPage<AirmiusPost>> feed({int page = 1});
  Future<AirmiusPost> create({required String content, required String visibility});
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
  Future<void> reportContent({required String type, required int id, String reason = 'other', String? details});
  Future<AirmiusPage<AirmiusComment>> comments(int postId, {int page = 1, int perPage = 20});
  Future<AirmiusComment> createComment(int postId, String content);
  Future<AirmiusComment> updateComment(int commentId, String content);
  Future<void> deleteComment(int commentId);
  Future<List<AirmiusStory>> stories();
  Future<AirmiusStory> markStoryViewed(int storyId);
  Future<AirmiusStory> reactToStory(int storyId, String reaction);
  Future<void> deleteStory(int storyId);
}

abstract class AirmiusSearchRepository {
  Future<AirmiusPage<AirmiusSearchResult>> search({required String query, int page = 1});
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
  final path = Uri.tryParse(mediaUrl)?.path.toLowerCase() ?? mediaUrl.toLowerCase();

  if (explicit.contains('video') || type.startsWith('video/') || path.endsWith('.mp4') || path.endsWith('.webm') || path.endsWith('.ogg') || path.endsWith('.mov')) {
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
    final type = _nullableString(file['mime_type']) ?? _nullableString(file['type']) ?? '';
    final candidate = _uploadFileUrl(file['url']) ?? _uploadFileUrl(file['path']) ?? _uploadFileUrl(file['thumbnail_url']) ?? _uploadFileUrl(file['thumbnail_path']);
    if (candidate == null) continue;
    final lowerCandidate = candidate.toLowerCase();
    final lowerType = type.toLowerCase();
    if (lowerType.startsWith('image/') || lowerCandidate.endsWith('.jpg') || lowerCandidate.endsWith('.jpeg') || lowerCandidate.endsWith('.png') || lowerCandidate.endsWith('.webp') || lowerCandidate.endsWith('.gif')) {
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

  final raw = _nullableString(image);
  if (raw != null) {
    if (raw.startsWith('data:image/') || raw.startsWith('http://') || raw.startsWith('https://')) {
      add(raw);
    } else {
      final base = _nullableString(uploadsBaseUrl);
      if (base != null && base.isNotEmpty) {
        add('${base.replaceFirst(RegExp(r'/+$'), '')}/${raw.replaceFirst(RegExp(r'^/+'), '')}');
      }

      add('https://cdn.airmius.com/${raw.replaceFirst(RegExp(r'^/+'), '')}');
      add(_mediaUrl(imageUrl));
      add(_mediaUrl(imageProxyUrl));

      if (raw.startsWith('/')) {
        add(_mediaUrl(raw));
      } else {
        final cleanPath = raw.replaceFirst(RegExp(r'^/+'), '');
        add(cleanPath.startsWith('storage/') ? '/$cleanPath' : '/storage/$cleanPath');
        add(_mediaUrl(cleanPath.startsWith('storage/') ? '/$cleanPath' : '/storage/$cleanPath'));
      }
    }
  }

  add(_mediaUrl(imageUrl));
  add(_mediaUrl(imageProxyUrl));

  return urls;
}

String? _postImageUrl(Object? image, Object? imageUrl, Object? uploadsBaseUrl) {
  final urls = _postImageUrls(image: image, imageUrl: imageUrl, imageProxyUrl: null, uploadsBaseUrl: uploadsBaseUrl);
  return urls.isEmpty ? null : urls.first;
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
    final type = (_nullableString(file['type']) ?? _nullableString(file['mime_type']) ?? '').toLowerCase();
    final lowerUrl = url.toLowerCase();
    final kind = type.startsWith('image/') || lowerUrl.endsWith('.jpg') || lowerUrl.endsWith('.jpeg') || lowerUrl.endsWith('.png') || lowerUrl.endsWith('.webp') || lowerUrl.endsWith('.gif')
        ? 'image'
        : type.startsWith('video/') || lowerUrl.endsWith('.mp4') || lowerUrl.endsWith('.mov') || lowerUrl.endsWith('.webm') || lowerUrl.endsWith('.ogg')
            ? 'video'
            : 'file';
    mapped.add(AirmiusPostAttachment(
      name: _string(file['display_name'] ?? file['name'], fallback: 'Datei'),
      url: url,
      kind: kind,
      thumbnailUrl: _uploadFileUrl(file['thumbnail_url']) ?? _uploadFileUrl(file['thumbnail_path']),
    ));
  }
  return mapped;
}

String? _postSportName(Object? sport) {
  if (sport is JsonMap) {
    return _nullableString(sport['name']) ?? _nullableString(sport['title']) ?? _nullableString(sport['slug']);
  }
  return _nullableString(sport);
}

List<String> _postSportSkills(Object? skills) {
  if (skills is! List) return const [];
  return skills
      .map((skill) {
        if (skill is JsonMap) return _nullableString(skill['name']) ?? _nullableString(skill['title']);
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

String? _mediaUrl(Object? value) {
  final string = _nullableString(value);
  if (string == null) return null;
  if (string.startsWith('data:image/') || string.startsWith('http://') || string.startsWith('https://')) {
    return string;
  }

  const origin = String.fromEnvironment('AIRMIUS_API_BASE_URL', defaultValue: 'https://airmius.com');
  final base = Uri.tryParse(origin);
  if (base == null || !base.hasScheme || base.host.isEmpty) return string;

  if (string.startsWith('/')) {
    return base.replace(path: _withBasePath(base, string), query: null, fragment: null).toString();
  }

  final cleanPath = string.replaceFirst(RegExp(r'^/+'), '');
  final path = cleanPath.startsWith('storage/') || cleanPath.startsWith('build/') || cleanPath.startsWith('images/') ? '/$cleanPath' : '/storage/$cleanPath';
  return base.replace(path: _withBasePath(base, path), query: null, fragment: null).toString();
}

String? _uploadFileUrl(Object? value) {
  final string = _nullableString(value);
  if (string == null) return null;
  if (string.startsWith('data:image/') || string.startsWith('http://') || string.startsWith('https://')) {
    return string;
  }

  final cleanPath = string.replaceFirst(RegExp(r'^/+'), '');
  if (cleanPath.startsWith('storage/')) {
    return _mediaUrl(cleanPath);
  }

  return 'https://cdn.airmius.com/$cleanPath';
}

String _withBasePath(Uri base, String path) {
  final cleanBase = base.path == '/' ? '' : base.path.replaceFirst(RegExp(r'/$'), '');
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
  if (value is String) return DateTime.tryParse(value) ?? DateTime.fromMillisecondsSinceEpoch(0);
  return DateTime.fromMillisecondsSinceEpoch(0);
}

bool _isClubType(String type) {
  final normalized = type.toLowerCase();
  return normalized == 'verein' || normalized == 'club';
}

bool _isTeamType(String type) {
  return type.toLowerCase().contains('team');
}
