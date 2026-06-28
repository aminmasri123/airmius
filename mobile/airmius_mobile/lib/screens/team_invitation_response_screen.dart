import 'package:flutter/material.dart';

import '../core/airmius_api_client.dart';
import '../core/airmius_api_models.dart';
import '../core/airmius_services_scope.dart';
import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'team_detail_screen.dart';

class TeamInvitationResponseScreen extends StatefulWidget {
  const TeamInvitationResponseScreen({super.key, this.invitationId, this.notification});

  final int? invitationId;
  final AirmiusNotification? notification;

  @override
  State<TeamInvitationResponseScreen> createState() => _TeamInvitationResponseScreenState();
}

class _TeamInvitationResponseScreenState extends State<TeamInvitationResponseScreen> {
  late Future<AirmiusTeamInvitation?> _invitationFuture;
  bool _busy = false;

  @override
  void initState() {
    super.initState();
    _invitationFuture = _loadInvitation();
  }

  Future<AirmiusTeamInvitation?> _loadInvitation() async {
    final repository = AirmiusServicesScope.of(context).repositories.clubs;
    try {
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
    if (notification == null || invitationId == null || invitationId <= 0) return null;

    final data = notification.data;
    final title = notification.title.trim();
    final body = notification.body.trim();
    final teamName = _stringFrom(data['team_name']) ?? _teamNameFromTitle(title) ?? 'Team';

    return AirmiusTeamInvitation(
      id: invitationId,
      role: _stringFrom(data['role']) ?? _roleFromBody(body) ?? 'Player',
      status: 'pending',
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
      final team = await AirmiusServicesScope.of(context).repositories.clubs.acceptTeamInvitation(invitation.id);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Team-Einladung wurde angenommen.')));
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
      _showError(error, 'Team-Einladung konnte nicht angenommen werden');
    }
  }

  Future<void> _decline(AirmiusTeamInvitation invitation) async {
    if (_busy) return;
    setState(() => _busy = true);
    try {
      await AirmiusServicesScope.of(context).repositories.clubs.declineTeamInvitation(invitation.id);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Team-Einladung wurde abgelehnt.')));
      Navigator.pop(context, true);
    } catch (error) {
      if (!mounted) return;
      setState(() => _busy = false);
      _showError(error, 'Team-Einladung konnte nicht abgelehnt werden');
    }
  }

  void _showError(Object error, String fallback) {
    final message = error is AirmiusApiException ? error.userMessage : '$error';
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('$fallback: $message')));
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      appBar: AppBar(
        backgroundColor: AirmiusColors.header,
        foregroundColor: AirmiusColors.text,
        surfaceTintColor: Colors.transparent,
        title: const Text('Team-Einladung', style: TextStyle(fontWeight: FontWeight.w900)),
      ),
      body: PageFrame(
        title: 'Clubs & Teams',
        subtitle: 'Offene Team-Einladungen annehmen oder ablehnen',
        showHeader: true,
        child: FutureBuilder<AirmiusTeamInvitation?>(
          future: _invitationFuture,
          builder: (context, snapshot) {
            if (snapshot.connectionState == ConnectionState.waiting) {
              return const AirmiusPanel(
                child: Padding(
                  padding: EdgeInsets.symmetric(vertical: 24),
                  child: Center(child: CircularProgressIndicator(color: AirmiusColors.blue)),
                ),
              );
            }

            if (snapshot.hasError) {
              return AirmiusPanel(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    const Icon(Icons.error_outline, color: AirmiusColors.red, size: 34),
                    const SizedBox(height: 10),
                    Text('Team-Einladung konnte nicht geladen werden.', textAlign: TextAlign.center, style: TextStyle(color: Theme.of(context).colorScheme.onSurface, fontWeight: FontWeight.w900)),
                    const SizedBox(height: 12),
                    AirmiusButton(
                      label: 'Erneut laden',
                      icon: Icons.refresh_outlined,
                      secondary: true,
                      onPressed: () => setState(() => _invitationFuture = _loadInvitation()),
                    ),
                  ],
                ),
              );
            }

            final invitation = snapshot.data;
            if (invitation == null) {
              return const EmptyPanel('Keine offene Team-Einladung gefunden.');
            }

            return AirmiusPanel(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Eyebrow('Offene Team-Einladung'),
                  const SizedBox(height: 8),
                  Text('Du wurdest zu einem Team eingeladen', style: TextStyle(color: Theme.of(context).colorScheme.onSurface, fontSize: 21, fontWeight: FontWeight.w900)),
                  const SizedBox(height: 6),
                  const Text('Nimm die Einladung an, um dem Team und dem zugehoerigen Verein beizutreten.', style: TextStyle(color: AirmiusColors.muted, height: 1.35)),
                  const SizedBox(height: 14),
                  _InvitationCard(invitation: invitation),
                  const SizedBox(height: 14),
                  AirmiusButton(
                    label: _busy ? 'Wird angenommen...' : 'Annehmen',
                    icon: Icons.check_circle_outline,
                    onPressed: _busy ? null : () => _accept(invitation),
                  ),
                  const SizedBox(height: 10),
                  AirmiusButton(
                    label: _busy ? 'Bitte warten...' : 'Ablehnen',
                    icon: Icons.cancel_outlined,
                    secondary: true,
                    onPressed: _busy ? null : () => _decline(invitation),
                  ),
                ],
              ),
            );
          },
        ),
      ),
    );
  }
}

class _InvitationCard extends StatelessWidget {
  const _InvitationCard({required this.invitation});

  final AirmiusTeamInvitation invitation;

  @override
  Widget build(BuildContext context) {
    final initials = invitation.teamName.trim().isEmpty
        ? 'T'
        : invitation.teamName.trim().split(RegExp(r'\s+')).take(2).map((part) => part.substring(0, 1).toUpperCase()).join();
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: AirmiusColors.cardSoft,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: AirmiusColors.border),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          CircleAvatar(
            backgroundColor: AirmiusColors.blue.withValues(alpha: 0.14),
            foregroundColor: AirmiusColors.text,
            child: Text(initials, style: const TextStyle(fontWeight: FontWeight.w900)),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(invitation.teamName, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900, fontSize: 16)),
                const SizedBox(height: 4),
                Text(
                  [
                    invitation.clubName ?? 'Verein',
                    if ((invitation.sportType ?? '').isNotEmpty) invitation.sportType!,
                    'Rolle: ${_roleLabel(invitation.role)}',
                  ].join(' - '),
                  style: const TextStyle(color: AirmiusColors.muted, height: 1.35),
                ),
                if ((invitation.inviterName ?? '').isNotEmpty) ...[
                  const SizedBox(height: 4),
                  Text('Eingeladen von ${invitation.inviterName}', style: const TextStyle(color: AirmiusColors.muted, fontSize: 12, height: 1.35)),
                ] else ...[
                  const SizedBox(height: 4),
                  const Text('Einladende Person wird nach dem Laden angezeigt.', style: TextStyle(color: AirmiusColors.muted, fontSize: 12, height: 1.35)),
                ],
              ],
            ),
          ),
        ],
      ),
    );
  }

  static String _roleLabel(String role) {
    return switch (role.toLowerCase()) {
      'coach' => 'Trainer',
      'captain' => 'Kapitän',
      'player' => 'Spieler',
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
  final match = RegExp(r'Einladung zu (.+)$', caseSensitive: false).firstMatch(title);
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
