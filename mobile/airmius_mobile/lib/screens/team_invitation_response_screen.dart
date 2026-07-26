import 'package:flutter/material.dart';

import '../core/airmius_api_client.dart';
import '../core/airmius_api_models.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';
import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'team_detail_screen.dart';

class TeamInvitationResponseScreen extends StatefulWidget {
  const TeamInvitationResponseScreen({
    super.key,
    this.invitationId,
    this.token,
    this.notification,
  });

  final int? invitationId;
  final String? token;
  final AirmiusNotification? notification;

  @override
  State<TeamInvitationResponseScreen> createState() =>
      _TeamInvitationResponseScreenState();
}

class _TeamInvitationResponseScreenState
    extends State<TeamInvitationResponseScreen> {
  late Future<AirmiusTeamInvitation?> _invitationFuture;
  bool _busy = false;
  bool _didStartLoading = false;

  @override
  void initState() {
    super.initState();
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (_didStartLoading) return;
    _didStartLoading = true;
    _invitationFuture = _loadInvitation();
  }

  Future<AirmiusTeamInvitation?> _loadInvitation() async {
    final repository = AirmiusServicesScope.of(context).repositories.clubs;
    try {
      if (widget.token != null && widget.token!.trim().isNotEmpty) {
        return await repository.teamInvitationByToken(widget.token!.trim());
      }
      if (widget.invitationId != null && widget.invitationId! > 0) {
        return await repository.teamInvitation(widget.invitationId!);
      }

      final invitations = await repository.teamInvitations();
      return invitations.isEmpty ? _fallbackInvitation() : invitations.first;
    } catch (_) {
      final fallback = _fallbackInvitation();
      if (fallback != null) return fallback;
      rethrow;
    }
  }

  AirmiusTeamInvitation? _fallbackInvitation() {
    final notification = widget.notification;
    final invitationId = widget.invitationId;
    if (notification == null || invitationId == null || invitationId <= 0) {
      return null;
    }

    final data = notification.data;
    final title = notification.title.trim();
    final body = notification.body.trim();
    final t = AirmiusScope.of(context).t;
    final teamName =
        _stringFrom(data['team_name']) ??
        _teamNameFromTitle(title) ??
        t('teamInvitation.fallbackTeam');

    return AirmiusTeamInvitation(
      id: invitationId,
      role:
          _stringFrom(data['role']) ??
          _roleFromBody(body) ??
          t('teamInvitation.fallbackPlayer'),
      status:
          _stringFrom(data['invitation_status'] ?? data['status']) ?? 'pending',
      teamId: _intFrom(data['team_id']) ?? 0,
      clubId: _intFrom(data['club_id']),
      teamName: teamName,
      clubName: _stringFrom(data['club_name']),
      sportType: _stringFrom(data['sport_type']),
      inviterName: _stringFrom(data['inviter_name']),
      inviterEmail: _stringFrom(data['inviter_email']),
      createdAt: notification.timeLabel,
    );
  }

  Future<void> _accept(AirmiusTeamInvitation invitation) async {
    if (_busy) return;
    setState(() => _busy = true);
    try {
      final repository = AirmiusServicesScope.of(context).repositories.clubs;
      final team = widget.token != null && widget.token!.trim().isNotEmpty
          ? await repository.acceptTeamInvitationByToken(widget.token!.trim())
          : await repository.acceptTeamInvitation(invitation.id);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            AirmiusScope.of(context).t('teamInvitation.acceptedToast'),
          ),
        ),
      );
      Navigator.pushReplacement(
        context,
        MaterialPageRoute(
          builder: (_) => TeamDetailScreen(
            title: team.name,
            mode: 'team',
            teamId: team.id,
            team: team,
          ),
        ),
      );
    } catch (error) {
      if (!mounted) return;
      setState(() => _busy = false);
      _showError(
        error,
        AirmiusScope.of(context).t('teamInvitation.acceptFailed'),
      );
    }
  }

  Future<void> _decline(AirmiusTeamInvitation invitation) async {
    if (_busy) return;
    setState(() => _busy = true);
    try {
      final repository = AirmiusServicesScope.of(context).repositories.clubs;
      if (widget.token != null && widget.token!.trim().isNotEmpty) {
        await repository.declineTeamInvitationByToken(widget.token!.trim());
      } else {
        await repository.declineTeamInvitation(invitation.id);
      }
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            AirmiusScope.of(context).t('teamInvitation.declinedToast'),
          ),
        ),
      );
      Navigator.pop(context, true);
    } catch (error) {
      if (!mounted) return;
      setState(() => _busy = false);
      _showError(
        error,
        AirmiusScope.of(context).t('teamInvitation.declineFailed'),
      );
    }
  }

  void _showError(Object error, String fallback) {
    final message = error is AirmiusApiException
        ? error.userMessage
        : AirmiusScope.of(context).t('common.errorDetails');
    ScaffoldMessenger.of(
      context,
    ).showSnackBar(SnackBar(content: Text('$fallback: $message')));
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      appBar: AppBar(
        backgroundColor:
            (Theme.of(context).appBarTheme.backgroundColor ??
            airmiusSurfaceColor(context)),
        foregroundColor: airmiusTextColor(context),
        surfaceTintColor: Colors.transparent,
        title: Text(
          AirmiusScope.of(context).t('teamInvitation.title'),
          style: TextStyle(fontWeight: FontWeight.w900),
        ),
      ),
      body: PageFrame(
        title: AirmiusScope.of(context).t('teamInvitation.clubs'),
        subtitle: AirmiusScope.of(context).t('teamInvitation.subtitle'),
        showHeader: true,
        child: FutureBuilder<AirmiusTeamInvitation?>(
          future: _invitationFuture,
          builder: (context, snapshot) {
            if (snapshot.connectionState == ConnectionState.waiting) {
              return AirmiusPanel(
                child: Padding(
                  padding: EdgeInsets.symmetric(vertical: 24),
                  child: Center(
                    child: CircularProgressIndicator(
                      color: airmiusAccentColor(context),
                    ),
                  ),
                ),
              );
            }

            if (snapshot.hasError) {
              final fallback =
                  _fallbackInvitation() ??
                  _genericInvitation(
                    widget.invitationId,
                    widget.notification,
                    teamFallback: AirmiusScope.of(
                      context,
                    ).t('teamInvitation.fallbackTeam'),
                    roleFallback: AirmiusScope.of(
                      context,
                    ).t('teamInvitation.fallbackPlayer'),
                  );
              return _InvitationStatusPanel(
                invitation: fallback,
                busy: _busy,
                onAccept: fallback.status == 'pending'
                    ? () => _accept(fallback)
                    : null,
                onDecline: fallback.status == 'pending'
                    ? () => _decline(fallback)
                    : null,
                contextNote: AirmiusScope.of(
                  context,
                ).t('teamInvitation.notificationFallback'),
              );
            }

            final invitation = snapshot.data;
            if (invitation == null) {
              return EmptyPanel(
                AirmiusScope.of(context).t('teamInvitation.none'),
              );
            }
            return _InvitationStatusPanel(
              invitation: invitation,
              busy: _busy,
              onAccept: invitation.status == 'pending'
                  ? () => _accept(invitation)
                  : null,
              onDecline: invitation.status == 'pending'
                  ? () => _decline(invitation)
                  : null,
            );
          },
        ),
      ),
    );
  }
}

AirmiusTeamInvitation _genericInvitation(
  int? invitationId,
  AirmiusNotification? notification, {
  required String teamFallback,
  required String roleFallback,
}) {
  final status =
      _stringFrom(
        notification?.data['invitation_status'] ?? notification?.data['status'],
      ) ??
      _statusFromText(
        '${notification?.title ?? ''} ${notification?.body ?? ''}',
      ) ??
      'unknown';
  return AirmiusTeamInvitation(
    id: invitationId ?? 0,
    role: _stringFrom(notification?.data['role']) ?? roleFallback,
    status: status,
    teamId: _intFrom(notification?.data['team_id']) ?? 0,
    clubId: _intFrom(notification?.data['club_id']),
    teamName:
        _stringFrom(notification?.data['team_name']) ??
        _teamNameFromTitle(notification?.title ?? '') ??
        teamFallback,
    clubName: _stringFrom(notification?.data['club_name']),
    sportType: _stringFrom(notification?.data['sport_type']),
    inviterName: _stringFrom(notification?.data['inviter_name']),
    inviterEmail: _stringFrom(notification?.data['inviter_email']),
    createdAt: notification?.timeLabel,
  );
}

class _InvitationStatusPanel extends StatelessWidget {
  const _InvitationStatusPanel({
    required this.invitation,
    required this.busy,
    this.onAccept,
    this.onDecline,
    this.contextNote,
  });

  final AirmiusTeamInvitation invitation;
  final bool busy;
  final VoidCallback? onAccept;
  final VoidCallback? onDecline;
  final String? contextNote;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final answered =
        invitation.status == 'accepted' || invitation.status == 'declined';
    final accepted = invitation.status == 'accepted';
    final unavailable = invitation.status == 'unknown';

    return AirmiusPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Eyebrow(
            answered
                ? t('teamInvitation.answered')
                : unavailable
                ? t('teamInvitation.title')
                : t('teamInvitation.open'),
          ),
          const SizedBox(height: 8),
          Text(
            answered
                ? (accepted
                      ? t('teamInvitation.acceptedHeadline')
                      : t('teamInvitation.declinedHeadline'))
                : unavailable
                ? t('teamInvitation.unavailableHeadline')
                : t('teamInvitation.openHeadline'),
            style: TextStyle(
              color: Theme.of(context).colorScheme.onSurface,
              fontSize: 21,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 6),
          Text(
            answered
                ? (accepted
                      ? t('teamInvitation.acceptedBody')
                      : t('teamInvitation.declinedBody'))
                : unavailable
                ? t('teamInvitation.unavailableBody')
                : t('teamInvitation.openBody'),
            style: TextStyle(color: airmiusMutedColor(context), height: 1.35),
          ),
          if (contextNote != null) ...[
            const SizedBox(height: 8),
            Text(
              contextNote!,
              style: TextStyle(
                color: AirmiusColors.amber,
                height: 1.35,
                fontWeight: FontWeight.w800,
              ),
            ),
          ],
          const SizedBox(height: 14),
          _InvitationCard(invitation: invitation),
          const SizedBox(height: 14),
          if (answered)
            StatusPill(
              accepted
                  ? t('teamInvitation.statusAccepted')
                  : t('teamInvitation.statusDeclined'),
              color: accepted ? AirmiusColors.green : AirmiusColors.red,
            )
          else if (unavailable)
            StatusPill(
              t('teamInvitation.statusUnavailable'),
              color: AirmiusColors.amber,
            )
          else ...[
            AirmiusButton(
              label: busy
                  ? t('teamInvitation.accepting')
                  : t('teamInvitation.accept'),
              icon: Icons.check_circle_outline,
              onPressed: busy ? null : onAccept,
            ),
            const SizedBox(height: 10),
            AirmiusButton(
              label: busy
                  ? t('teamInvitation.waiting')
                  : t('teamInvitation.decline'),
              icon: Icons.cancel_outlined,
              secondary: true,
              onPressed: busy ? null : onDecline,
            ),
          ],
        ],
      ),
    );
  }
}

class _InvitationCard extends StatelessWidget {
  const _InvitationCard({required this.invitation});

  final AirmiusTeamInvitation invitation;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final initials = invitation.teamName.trim().isEmpty
        ? 'T'
        : invitation.teamName
              .trim()
              .split(RegExp(r'\s+'))
              .take(2)
              .map((part) => part.substring(0, 1).toUpperCase())
              .join();
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: airmiusSurfaceSoftColor(context),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: airmiusBorderColor(context)),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          CircleAvatar(
            backgroundColor: airmiusAccentColor(
              context,
            ).withValues(alpha: 0.14),
            foregroundColor: airmiusTextColor(context),
            child: Text(
              initials,
              style: TextStyle(fontWeight: FontWeight.w900),
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  invitation.teamName,
                  style: TextStyle(
                    color: airmiusTextColor(context),
                    fontWeight: FontWeight.w900,
                    fontSize: 16,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  [
                    invitation.clubName ?? t('teamInvitation.club'),
                    if ((invitation.sportType ?? '').isNotEmpty)
                      invitation.sportType!,
                    t(
                      'teamInvitation.role',
                    ).replaceFirst('{role}', _roleLabel(invitation.role, t)),
                  ].join(' - '),
                  style: TextStyle(
                    color: airmiusMutedColor(context),
                    height: 1.35,
                  ),
                ),
                if ((invitation.inviterName ?? '').isNotEmpty) ...[
                  const SizedBox(height: 4),
                  Text(
                    t(
                      'teamInvitation.invitedBy',
                    ).replaceFirst('{name}', invitation.inviterName!),
                    style: TextStyle(
                      color: airmiusMutedColor(context),
                      fontSize: 12,
                      height: 1.35,
                    ),
                  ),
                ] else ...[
                  const SizedBox(height: 4),
                  Text(
                    t('teamInvitation.inviterPending'),
                    style: TextStyle(
                      color: airmiusMutedColor(context),
                      fontSize: 12,
                      height: 1.35,
                    ),
                  ),
                ],
              ],
            ),
          ),
        ],
      ),
    );
  }

  static String _roleLabel(String role, String Function(String) t) {
    return switch (role.toLowerCase()) {
      'coach' => t('teamInvitation.roleCoach'),
      'captain' => t('teamInvitation.roleCaptain'),
      'player' => t('teamInvitation.rolePlayer'),
      _ => role,
    };
  }
}

String? _stringFrom(Object? value) {
  if (value == null) return null;
  final text = '$value'.trim();
  return text.isEmpty ? null : text;
}

int? _intFrom(Object? value) {
  if (value is int) return value;
  if (value is num) return value.toInt();
  return int.tryParse('${value ?? ''}');
}

String? _teamNameFromTitle(String title) {
  final match = RegExp(
    r'Einladung zu (.+)$',
    caseSensitive: false,
  ).firstMatch(title);
  return match?.group(1)?.trim();
}

String? _roleFromBody(String body) {
  final lower = body.toLowerCase();
  if (lower.contains('trainer')) return 'Coach';
  if (lower.contains('coach')) return 'Coach';
  if (lower.contains('spieler')) return 'Player';
  if (lower.contains('player')) return 'Player';
  return null;
}

String? _statusFromText(String text) {
  final lower = text.toLowerCase();
  if (lower.contains('angenommen') || lower.contains('accepted')) {
    return 'accepted';
  }
  if (lower.contains('abgelehnt') ||
      lower.contains('declined') ||
      lower.contains('refused')) {
    return 'declined';
  }
  return null;
}
