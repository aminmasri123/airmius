import '../core/airmius_api_models.dart';

class ClubSummary {
  const ClubSummary({
    required this.id,
    this.ownerId,
    required this.name,
    required this.city,
    required this.members,
    required this.teams,
    required this.posts,
    required this.acceptsMemberships,
    required this.hasPendingMembershipRequest,
    required this.isMember,
    required this.verified,
    this.verificationStatus,
    this.teamList = const [],
    this.logoUrl,
    this.bannerUrl,
    this.sportType,
    this.postalCode,
    this.country,
    this.canManage = false,
    this.canViewCockpit = false,
    this.canEditTeams = false,
    this.canCreateTeamsGlobally = false,
    this.teamCreationDepartments = const [],
    this.canManageMembers = false,
    this.canViewFinance = false,
    this.canEditClubProfile = false,
    this.canEditClubLegal = false,
    this.canEditClubContact = false,
    this.canEditClubBranding = false,
    this.canEditSponsors = false,
    this.canDeleteSponsors = false,
    this.canEditJobs = false,
    this.canViewRecruiting = false,
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
    this.description,
    this.admins = const [],
    this.postItems = const [],
    this.gamification,
    this.badges = const [],
    this.social = const {},
    this.contactEmail,
    this.contactPhone,
    this.websiteUrl,
    this.contactPersons = const [],
    this.brandPrimaryColor,
    this.brandSecondaryColor,
    this.brandAccentColor,
  });

  final int id;
  final int? ownerId;
  final String name;
  final String city;
  final int members;
  final int teams;
  final int posts;
  final String? description;
  final List<JsonMap> admins;
  final List<JsonMap> postItems;
  final JsonMap? gamification;
  final List<JsonMap> badges;
  final JsonMap social;
  final String? contactEmail;
  final String? contactPhone;
  final String? websiteUrl;
  final List<JsonMap> contactPersons;
  final String? brandPrimaryColor;
  final String? brandSecondaryColor;
  final String? brandAccentColor;
  final bool acceptsMemberships;
  final bool hasPendingMembershipRequest;
  final bool isMember;
  final bool verified;
  final String? verificationStatus;
  final List<TeamSummary> teamList;
  final String? logoUrl;
  final String? bannerUrl;
  final String? sportType;
  final String? postalCode;
  final String? country;
  final bool canManage;
  final bool canViewCockpit;
  final bool canEditTeams;
  final bool canCreateTeamsGlobally;
  final List<JsonMap> teamCreationDepartments;
  final bool canManageMembers;
  final bool canViewFinance;
  final bool canEditClubProfile;
  final bool canEditClubLegal;
  final bool canEditClubContact;
  final bool canEditClubBranding;
  final bool canEditSponsors;
  final bool canDeleteSponsors;
  final bool canEditJobs;
  final bool canViewRecruiting;
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

  bool get canEditAnyClubData =>
      canEditClubProfile ||
      canEditClubLegal ||
      canEditClubContact ||
      canEditClubBranding;

  bool get canAccessMembershipWorkspace => canManageMembers || canViewFinance;

  int get pendingMembershipRequests =>
      management?.pendingMembershipRequestsCount ?? 0;
  int get pendingTeamJoinRequests =>
      management?.pendingTeamJoinRequestsCount ?? 0;
  int get externalMembersCount => management?.externalMembers.length ?? 0;
  int get membershipTypesCount => management?.membershipTypes.length ?? 0;
  int get contributionRulesCount => management?.contributionRules.length ?? 0;
  int get invoicesCount => management?.invoices.length ?? 0;
  int get paymentsCount => management?.payments.length ?? 0;
  int get bankTransactionsCount => management?.bankTransactions.length ?? 0;

  ClubSummary copyWith({AirmiusClubManagement? management}) => ClubSummary(
    id: id,
    ownerId: ownerId,
    name: name,
    city: city,
    members: members,
    teams: teams,
    posts: posts,
    description: description,
    admins: admins,
    postItems: postItems,
    gamification: gamification,
    badges: badges,
    social: social,
    contactEmail: contactEmail,
    contactPhone: contactPhone,
    websiteUrl: websiteUrl,
    contactPersons: contactPersons,
    brandPrimaryColor: brandPrimaryColor,
    brandSecondaryColor: brandSecondaryColor,
    brandAccentColor: brandAccentColor,
    acceptsMemberships: acceptsMemberships,
    hasPendingMembershipRequest: hasPendingMembershipRequest,
    isMember: isMember,
    verified: verified,
    verificationStatus: verificationStatus,
    teamList: teamList,
    logoUrl: logoUrl,
    bannerUrl: bannerUrl,
    sportType: sportType,
    postalCode: postalCode,
    country: country,
    canManage: canManage,
    canViewCockpit: canViewCockpit,
    canEditTeams: canEditTeams,
    canCreateTeamsGlobally: canCreateTeamsGlobally,
    teamCreationDepartments: teamCreationDepartments,
    canManageMembers: canManageMembers,
    canViewFinance: canViewFinance,
    canEditClubProfile: canEditClubProfile,
    canEditClubLegal: canEditClubLegal,
    canEditClubContact: canEditClubContact,
    canEditClubBranding: canEditClubBranding,
    canEditSponsors: canEditSponsors,
    canDeleteSponsors: canDeleteSponsors,
    canEditJobs: canEditJobs,
    canViewRecruiting: canViewRecruiting,
    canViewMetadata: canViewMetadata,
    canEditMetadata: canEditMetadata,
    canEditAnnouncements: canEditAnnouncements,
    canPublishAnnouncements: canPublishAnnouncements,
    canDeleteAnnouncements: canDeleteAnnouncements,
    canEditSurveys: canEditSurveys,
    canCloseSurveys: canCloseSurveys,
    canDeleteSurveys: canDeleteSurveys,
    canDelete: canDelete,
    deletionScheduledAt: deletionScheduledAt,
    management: management ?? this.management,
    membershipStatus: membershipStatus,
    membershipRole: membershipRole,
    membershipEndsOn: membershipEndsOn,
    membershipTypeId: membershipTypeId,
    membershipDepartmentId: membershipDepartmentId,
    membershipChangeRequested: membershipChangeRequested,
    membershipTerminationRequested: membershipTerminationRequested,
    pauseRequested: pauseRequested,
    pausedFrom: pausedFrom,
    pausedUntil: pausedUntil,
    memberPauseRequestsEnabled: memberPauseRequestsEnabled,
  );

  factory ClubSummary.fromAirmiusClub(AirmiusClub club) => ClubSummary(
    id: club.id,
    ownerId: club.ownerId,
    name: club.name,
    city: club.city,
    members: club.membersCount,
    teams: club.teamsCount,
    posts: club.postsCount,
    acceptsMemberships: club.acceptsMembershipApplications,
    hasPendingMembershipRequest: club.hasPendingMembershipRequest,
    isMember: club.isMember,
    verified: club.verified,
    verificationStatus: club.verificationStatus,
    teamList: club.teams.map(TeamSummary.fromAirmiusTeam).toList(),
    logoUrl: club.logoUrl,
    bannerUrl: club.bannerUrl,
    sportType: club.sportType,
    postalCode: club.postalCode,
    country: club.country,
    canManage: club.canManage,
    canViewCockpit: club.canViewCockpit,
    canEditTeams: club.canEditTeams,
    canCreateTeamsGlobally: club.canCreateTeamsGlobally,
    teamCreationDepartments: club.teamCreationDepartments,
    canManageMembers: club.canManageMembers,
    canViewFinance: club.canViewFinance,
    canEditClubProfile: club.canEditClubProfile,
    canEditClubLegal: club.canEditClubLegal,
    canEditClubContact: club.canEditClubContact,
    canEditClubBranding: club.canEditClubBranding,
    canEditSponsors: club.canEditSponsors,
    canDeleteSponsors: club.canDeleteSponsors,
    canEditJobs: club.canEditJobs,
    canViewRecruiting: club.canViewRecruiting,
    canViewMetadata: club.canViewMetadata,
    canEditMetadata: club.canEditMetadata,
    canEditAnnouncements: club.canEditAnnouncements,
    canPublishAnnouncements: club.canPublishAnnouncements,
    canDeleteAnnouncements: club.canDeleteAnnouncements,
    canEditSurveys: club.canEditSurveys,
    canCloseSurveys: club.canCloseSurveys,
    canDeleteSurveys: club.canDeleteSurveys,
    canDelete: club.canDelete,
    deletionScheduledAt: club.deletionScheduledAt,
    management: club.management,
    membershipStatus: club.membershipStatus,
    membershipRole: club.membershipRole,
    membershipEndsOn: club.membershipEndsOn,
    membershipTypeId: club.membershipTypeId,
    membershipDepartmentId: club.membershipDepartmentId,
    membershipChangeRequested: club.membershipChangeRequested,
    membershipTerminationRequested: club.membershipTerminationRequested,
    pauseRequested: club.pauseRequested,
    pausedFrom: club.pausedFrom,
    pausedUntil: club.pausedUntil,
    memberPauseRequestsEnabled: club.memberPauseRequestsEnabled,
    description: club.description,
    admins: club.admins,
    postItems: club.posts,
    gamification: club.gamification,
    badges: club.badges,
    social: club.social,
    contactEmail: club.contactEmail,
    contactPhone: club.contactPhone,
    websiteUrl: club.websiteUrl,
    contactPersons: club.contactPersons,
    brandPrimaryColor: club.brandPrimaryColor,
    brandSecondaryColor: club.brandSecondaryColor,
    brandAccentColor: club.brandAccentColor,
  );
}

class TeamSummary {
  const TeamSummary({
    required this.id,
    required this.name,
    required this.meta,
    this.logoUrl,
  });

  final int id;
  final String name;
  final String meta;
  final String? logoUrl;

  factory TeamSummary.fromAirmiusTeam(AirmiusTeam team) => TeamSummary(
    id: team.id,
    name: team.name,
    meta: team.description == null || team.description!.isEmpty
        ? (team.clubName == null || team.clubName!.isEmpty
              ? 'Training & Spielbetrieb'
              : team.clubName!)
        : team.description!,
    logoUrl: team.logoUrl,
  );
}

const demoClubs = [
  ClubSummary(
    id: 26,
    name: 'ZBB',
    city: 'Kleinblittersdorf',
    members: 1,
    teams: 0,
    posts: 0,
    acceptsMemberships: true,
    hasPendingMembershipRequest: false,
    isMember: false,
    verified: false,
    sportType: 'Fu\u00dfball',
    postalCode: '66271',
    country: 'DE',
    canManage: true,
  ),
  ClubSummary(
    id: 2,
    name: 'Airmius Running Club',
    city: 'Saarbrücken',
    members: 42,
    teams: 4,
    posts: 8,
    acceptsMemberships: true,
    hasPendingMembershipRequest: false,
    isMember: false,
    verified: true,
    sportType: 'Running',
    country: 'DE',
    canManage: true,
  ),
  ClubSummary(
    id: 3,
    name: 'Tennis Zentrum West',
    city: 'Trier',
    members: 128,
    teams: 7,
    posts: 13,
    acceptsMemberships: false,
    hasPendingMembershipRequest: false,
    isMember: false,
    verified: true,
    sportType: 'Tennis',
    country: 'DE',
  ),
];
