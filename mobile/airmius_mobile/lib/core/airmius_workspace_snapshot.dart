import 'airmius_api_models.dart';

/// Server-derived membership and role context used by workspace surfaces.
/// Keeping this model separate prevents the center and detail pages from
/// falling back to different local/demo values.
class AirmiusWorkspaceSnapshot {
  const AirmiusWorkspaceSnapshot({
    required this.clubs,
    required this.teams,
    required this.invitations,
    required this.roles,
    this.permissions = const [],
  });

  const AirmiusWorkspaceSnapshot.empty()
    : clubs = const [],
      teams = const [],
      invitations = const [],
      roles = const [],
      permissions = const [];

  final List<AirmiusClub> clubs;
  final List<AirmiusTeam> teams;
  final List<AirmiusTeamInvitation> invitations;
  final List<String> roles;
  final List<String> permissions;

  int get activeAreas => clubs.length + teams.length;
}
