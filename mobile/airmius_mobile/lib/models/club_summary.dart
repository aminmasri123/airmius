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
  ClubSummary(id: 26, name: 'ZBB', city: 'Kleinblittersdorf', members: 1, teams: 0, posts: 0, acceptsMemberships: true, hasPendingMembershipRequest: false, isMember: false, verified: false),
  ClubSummary(id: 2, name: 'Airmius Running Club', city: 'Saarbruecken', members: 42, teams: 4, posts: 8, acceptsMemberships: true, hasPendingMembershipRequest: false, isMember: false, verified: true),
  ClubSummary(id: 3, name: 'Tennis Zentrum West', city: 'Trier', members: 128, teams: 7, posts: 13, acceptsMemberships: false, hasPendingMembershipRequest: false, isMember: false, verified: true),
];
