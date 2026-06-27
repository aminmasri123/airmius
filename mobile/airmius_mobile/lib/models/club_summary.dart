import '../core/airmius_api_models.dart';

class ClubSummary {
  const ClubSummary({
    required this.id,
    required this.name,
    required this.city,
    required this.members,
    required this.teams,
    required this.posts,
    required this.acceptsMemberships,
    required this.hasPendingMembershipRequest,
    required this.isMember,
    required this.verified,
    this.teamList = const [],
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
  final int members;
  final int teams;
  final int posts;
  final bool acceptsMemberships;
  final bool hasPendingMembershipRequest;
  final bool isMember;
  final bool verified;
  final List<TeamSummary> teamList;
  final String? logoUrl;
  final String? bannerUrl;
  final String? sportType;
  final String? postalCode;
  final String? country;
  final bool canManage;
  final bool canDelete;
  final AirmiusClubManagement? management;

  int get pendingMembershipRequests => management?.pendingMembershipRequestsCount ?? 0;
  int get pendingTeamJoinRequests => management?.pendingTeamJoinRequestsCount ?? 0;
  int get externalMembersCount => management?.externalMembers.length ?? 0;
  int get membershipTypesCount => management?.membershipTypes.length ?? 0;
  int get contributionRulesCount => management?.contributionRules.length ?? 0;
  int get invoicesCount => management?.invoices.length ?? 0;
  int get paymentsCount => management?.payments.length ?? 0;
  int get bankTransactionsCount => management?.bankTransactions.length ?? 0;

  factory ClubSummary.fromAirmiusClub(AirmiusClub club) => ClubSummary(
        id: club.id,
        name: club.name,
        city: club.city,
        members: club.membersCount,
        teams: club.teamsCount,
        posts: 0,
        acceptsMemberships: club.acceptsMembershipApplications,
        hasPendingMembershipRequest: club.hasPendingMembershipRequest,
        isMember: club.isMember,
        verified: false,
        teamList: club.teams.map(TeamSummary.fromAirmiusTeam).toList(),
        logoUrl: club.logoUrl,
        bannerUrl: club.bannerUrl,
        sportType: club.sportType,
        postalCode: club.postalCode,
        country: club.country,
        canManage: club.canManage,
        canDelete: club.canDelete,
        management: club.management,
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
        meta: team.description == null || team.description!.isEmpty ? (team.clubName == null || team.clubName!.isEmpty ? 'Training & Spielbetrieb' : team.clubName!) : team.description!,
        logoUrl: team.logoUrl,
      );
}

const demoClubs = [
  ClubSummary(id: 26, name: 'ZBB', city: 'Kleinblittersdorf', members: 1, teams: 0, posts: 0, acceptsMemberships: true, hasPendingMembershipRequest: false, isMember: false, verified: false, sportType: 'Fu\u00dfball', postalCode: '66271', country: 'DE', canManage: true),
  ClubSummary(id: 2, name: 'Airmius Running Club', city: 'Saarbruecken', members: 42, teams: 4, posts: 8, acceptsMemberships: true, hasPendingMembershipRequest: false, isMember: false, verified: true, sportType: 'Running', country: 'DE', canManage: true),
  ClubSummary(id: 3, name: 'Tennis Zentrum West', city: 'Trier', members: 128, teams: 7, posts: 13, acceptsMemberships: false, hasPendingMembershipRequest: false, isMember: false, verified: true, sportType: 'Tennis', country: 'DE'),
];
