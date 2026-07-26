import 'package:flutter/material.dart';

import '../core/airmius_api_client.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';
import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

class FriendInvitationResponseScreen extends StatefulWidget {
  const FriendInvitationResponseScreen({super.key, required this.token});

  final String? token;

  @override
  State<FriendInvitationResponseScreen> createState() =>
      _FriendInvitationResponseScreenState();
}

class _FriendInvitationResponseScreenState
    extends State<FriendInvitationResponseScreen> {
  late Future<_FriendInvitationData> _invitationFuture;
  bool _didStartLoading = false;
  bool _busy = false;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (_didStartLoading) return;
    _didStartLoading = true;
    _invitationFuture = _loadInvitation();
  }

  Future<_FriendInvitationData> _loadInvitation() async {
    final token = widget.token?.trim() ?? '';
    if (token.isEmpty) throw StateError('missing friend invitation token');
    final client = AirmiusServicesScope.of(
      context,
    ).clientForSession(AirmiusServicesScope.of(context).authState.session);
    final response = await client.friendInvitationByToken(token);
    final data = response['data'];
    if (data is! JsonMap) throw StateError('invalid friend invitation payload');
    final sender = data['sender'];
    final senderMap = sender is JsonMap ? sender : const <String, dynamic>{};
    return _FriendInvitationData(
      id: _int(data['id']),
      status: _string(data['status'], fallback: 'pending'),
      senderName: _string(senderMap['name'], fallback: 'Airmius Mitglied'),
    );
  }

  Future<void> _accept() async {
    await _respond(accept: true);
  }

  Future<void> _decline() async {
    await _respond(accept: false);
  }

  Future<void> _respond({required bool accept}) async {
    if (_busy) return;
    final token = widget.token?.trim() ?? '';
    if (token.isEmpty) return;
    setState(() => _busy = true);
    try {
      final services = AirmiusServicesScope.of(context);
      final client = services.clientForSession(services.authState.session);
      if (accept) {
        await client.acceptFriendInvitationByToken(token);
      } else {
        await client.declineFriendInvitationByToken(token);
      }
      if (!mounted) return;
      setState(() => _busy = false);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            AirmiusScope.of(context).t(
              accept
                  ? 'friendInvitation.acceptedToast'
                  : 'friendInvitation.declinedToast',
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
            '${AirmiusScope.of(context).t(accept ? 'friendInvitation.acceptFailed' : 'friendInvitation.declineFailed')}: $message',
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
          t('friendInvitation.title'),
          style: const TextStyle(fontWeight: FontWeight.w900),
        ),
      ),
      body: PageFrame(
        title: t('friendInvitation.title'),
        subtitle: t('friendInvitation.subtitle'),
        child: FutureBuilder<_FriendInvitationData>(
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
                      t('friendInvitation.unavailableHeadline'),
                      style: TextStyle(
                        color: airmiusTextColor(context),
                        fontSize: 20,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(height: 8),
                    Text(
                      t('friendInvitation.unavailableBody'),
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
                        ? t('friendInvitation.answered')
                        : t('friendInvitation.open'),
                  ),
                  const SizedBox(height: 8),
                  Text(
                    answered
                        ? (accepted
                              ? t('friendInvitation.acceptedHeadline')
                              : t('friendInvitation.declinedHeadline'))
                        : t('friendInvitation.openHeadline'),
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
                              ? t('friendInvitation.acceptedBody')
                              : t('friendInvitation.declinedBody'))
                        : t('friendInvitation.openBody'),
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
                      child: Text(
                        invitation.initials,
                        style: const TextStyle(fontWeight: FontWeight.w900),
                      ),
                    ),
                    title: Text(
                      invitation.senderName,
                      style: const TextStyle(fontWeight: FontWeight.w900),
                    ),
                    subtitle: Text(t('friendInvitation.invitedBy')),
                  ),
                  if (!answered) ...[
                    const SizedBox(height: 14),
                    AirmiusButton(
                      label: _busy
                          ? t('friendInvitation.waiting')
                          : t('friendInvitation.accept'),
                      icon: Icons.person_add_alt_1_outlined,
                      onPressed: _busy ? null : _accept,
                    ),
                    const SizedBox(height: 10),
                    AirmiusButton(
                      label: _busy
                          ? t('friendInvitation.waiting')
                          : t('friendInvitation.decline'),
                      icon: Icons.person_remove_outlined,
                      secondary: true,
                      onPressed: _busy ? null : _decline,
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

class _FriendInvitationData {
  const _FriendInvitationData({
    required this.id,
    required this.status,
    required this.senderName,
  });

  final int id;
  final String status;
  final String senderName;

  String get initials {
    final parts = senderName.trim().split(RegExp(r'\s+'));
    if (parts.length == 1) return parts.first.characters.take(2).toString();
    return '${parts.first.characters.first}${parts.last.characters.first}'
        .toUpperCase();
  }
}

int _int(Object? value) =>
    value is num ? value.toInt() : int.tryParse('$value') ?? 0;

String _string(Object? value, {required String fallback}) {
  final text = value?.toString().trim() ?? '';
  return text.isEmpty ? fallback : text;
}

typedef JsonMap = Map<String, dynamic>;
