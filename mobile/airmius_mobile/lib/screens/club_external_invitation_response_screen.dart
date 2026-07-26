import 'package:flutter/material.dart';

import '../core/airmius_api_client.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';
import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

class ClubExternalInvitationResponseScreen extends StatefulWidget {
  const ClubExternalInvitationResponseScreen({super.key, required this.token});

  final String? token;

  @override
  State<ClubExternalInvitationResponseScreen> createState() =>
      _ClubExternalInvitationResponseScreenState();
}

class _ClubExternalInvitationResponseScreenState
    extends State<ClubExternalInvitationResponseScreen> {
  late Future<_ClubExternalInvitationData> _invitationFuture;
  bool _didStartLoading = false;
  bool _busy = false;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (_didStartLoading) return;
    _didStartLoading = true;
    _invitationFuture = _loadInvitation();
  }

  Future<_ClubExternalInvitationData> _loadInvitation() async {
    final token = widget.token?.trim() ?? '';
    if (token.isEmpty) throw StateError('missing club invitation token');
    final services = AirmiusServicesScope.of(context);
    final data = await services.repositories.clubs
        .clubExternalInvitationByToken(token);
    final club = data['club'];
    final clubMap = club is JsonMap ? club : const <String, dynamic>{};
    return _ClubExternalInvitationData(
      status: _string(data['status'], fallback: 'pending'),
      role: _string(data['role'], fallback: 'member'),
      clubName: _string(clubMap['name'], fallback: 'Airmius Verein'),
      expiresAt: _nullableString(data['expires_at']),
    );
  }

  Future<void> _respond({required bool accept}) async {
    if (_busy) return;
    final token = widget.token?.trim() ?? '';
    if (token.isEmpty) return;
    setState(() => _busy = true);
    try {
      final repository = AirmiusServicesScope.of(context).repositories.clubs;
      if (accept) {
        await repository.acceptClubExternalInvitation(token);
      } else {
        await repository.declineClubExternalInvitation(token);
      }
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            AirmiusScope.of(context).t(
              accept
                  ? 'clubInvitation.acceptedToast'
                  : 'clubInvitation.declinedToast',
            ),
          ),
        ),
      );
      Navigator.of(context).pop(true);
    } catch (error) {
      if (!mounted) return;
      setState(() => _busy = false);
      final message = error is AirmiusApiException
          ? error.userMessage
          : AirmiusScope.of(context).t('common.errorDetails');
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            '${AirmiusScope.of(context).t(accept ? 'clubInvitation.acceptFailed' : 'clubInvitation.declineFailed')}: $message',
          ),
          backgroundColor: AirmiusColors.red,
        ),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return Scaffold(
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      appBar: AppBar(
        backgroundColor:
            Theme.of(context).appBarTheme.backgroundColor ??
            airmiusSurfaceColor(context),
        foregroundColor: airmiusTextColor(context),
        surfaceTintColor: Colors.transparent,
        title: Text(
          t('clubInvitation.title'),
          style: const TextStyle(fontWeight: FontWeight.w900),
        ),
      ),
      body: PageFrame(
        title: t('clubInvitation.title'),
        subtitle: t('clubInvitation.subtitle'),
        child: FutureBuilder<_ClubExternalInvitationData>(
          future: _invitationFuture,
          builder: (context, snapshot) {
            if (snapshot.connectionState == ConnectionState.waiting) {
              return AirmiusPanel(
                child: Padding(
                  padding: const EdgeInsets.all(24),
                  child: Center(
                    child: CircularProgressIndicator(
                      color: airmiusAccentColor(context),
                    ),
                  ),
                ),
              );
            }
            if (snapshot.hasError || !snapshot.hasData) {
              return AirmiusPanel(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    IconBadge(
                      icon: Icons.link_off_outlined,
                      color: AirmiusColors.amber,
                    ),
                    const SizedBox(height: 12),
                    Text(
                      t('clubInvitation.unavailableHeadline'),
                      style: TextStyle(
                        color: airmiusTextColor(context),
                        fontSize: 20,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(height: 8),
                    Text(
                      t('clubInvitation.unavailableBody'),
                      style: TextStyle(
                        color: airmiusMutedColor(context),
                        height: 1.4,
                      ),
                    ),
                  ],
                ),
              );
            }

            final invitation = snapshot.data!;
            final answered = invitation.status != 'pending';
            final accepted = invitation.status == 'accepted';
            return AirmiusPanel(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Eyebrow(
                    answered
                        ? t('clubInvitation.answered')
                        : t('clubInvitation.open'),
                  ),
                  const SizedBox(height: 8),
                  Text(
                    answered
                        ? (accepted
                              ? t('clubInvitation.acceptedHeadline')
                              : t('clubInvitation.declinedHeadline'))
                        : t('clubInvitation.openHeadline'),
                    style: TextStyle(
                      color: airmiusTextColor(context),
                      fontSize: 21,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                  const SizedBox(height: 6),
                  Text(
                    answered
                        ? (accepted
                              ? t('clubInvitation.acceptedBody')
                              : t('clubInvitation.declinedBody'))
                        : t('clubInvitation.openBody'),
                    style: TextStyle(
                      color: airmiusMutedColor(context),
                      height: 1.4,
                    ),
                  ),
                  const SizedBox(height: 16),
                  ListTile(
                    contentPadding: EdgeInsets.zero,
                    leading: CircleAvatar(
                      backgroundColor: airmiusAccentColor(
                        context,
                      ).withValues(alpha: .14),
                      foregroundColor: airmiusTextColor(context),
                      child: const Icon(Icons.groups_outlined),
                    ),
                    title: Text(
                      invitation.clubName,
                      style: const TextStyle(fontWeight: FontWeight.w900),
                    ),
                    subtitle: Text(
                      t(
                        'clubInvitation.role',
                      ).replaceFirst('{role}', invitation.role),
                    ),
                  ),
                  if (invitation.expiresAt != null) ...[
                    const SizedBox(height: 8),
                    Text(
                      t(
                        'clubInvitation.expires',
                      ).replaceFirst('{date}', invitation.expiresAt!),
                      style: TextStyle(
                        color: airmiusMutedColor(context),
                        fontSize: 12,
                      ),
                    ),
                  ],
                  if (!answered) ...[
                    const SizedBox(height: 14),
                    AirmiusButton(
                      label: _busy
                          ? t('clubInvitation.waiting')
                          : t('clubInvitation.accept'),
                      icon: Icons.how_to_reg_outlined,
                      onPressed: _busy ? null : () => _respond(accept: true),
                    ),
                    const SizedBox(height: 10),
                    AirmiusButton(
                      label: _busy
                          ? t('clubInvitation.waiting')
                          : t('clubInvitation.decline'),
                      icon: Icons.block_outlined,
                      secondary: true,
                      onPressed: _busy ? null : () => _respond(accept: false),
                    ),
                  ],
                ],
              ),
            );
          },
        ),
      ),
    );
  }
}

class _ClubExternalInvitationData {
  const _ClubExternalInvitationData({
    required this.status,
    required this.role,
    required this.clubName,
    this.expiresAt,
  });

  final String status;
  final String role;
  final String clubName;
  final String? expiresAt;
}

String _string(Object? value, {required String fallback}) {
  final text = value?.toString().trim() ?? '';
  return text.isEmpty ? fallback : text;
}

String? _nullableString(Object? value) {
  final text = value?.toString().trim() ?? '';
  return text.isEmpty ? null : text;
}

typedef JsonMap = Map<String, dynamic>;
