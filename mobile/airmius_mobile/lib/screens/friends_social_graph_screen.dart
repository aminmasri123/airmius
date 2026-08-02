import 'package:flutter/material.dart';

import '../core/airmius_api_client.dart';
import '../core/airmius_api_models.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';
import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'chat_detail_screen.dart';

class FriendsSocialGraphScreen extends StatefulWidget {
  const FriendsSocialGraphScreen({super.key});

  @override
  State<FriendsSocialGraphScreen> createState() =>
      _FriendsSocialGraphScreenState();
}

class _FriendsSocialGraphScreenState extends State<FriendsSocialGraphScreen> {
  String _section = 'friends';
  Future<JsonMap>? _friendsFuture;
  bool _busy = false;

  AirmiusApiClient get _client {
    final services = AirmiusServicesScope.of(context);
    return services.clientForSession(services.authState.session);
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _friendsFuture ??= _load();
  }

  Future<JsonMap> _load() async {
    final response = await _client.friends();
    return _friendMap(response['data']);
  }

  void _reload() {
    setState(() => _friendsFuture = _load());
  }

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return Scaffold(
      appBar: AppBar(
        backgroundColor:
            Theme.of(context).appBarTheme.backgroundColor ??
            Theme.of(context).colorScheme.surface,
        surfaceTintColor: Colors.transparent,
        title: Text(
          t('friends.title'),
          style: const TextStyle(fontWeight: FontWeight.w900),
        ),
        actions: [
          IconButton(
            tooltip: t('friends.reload'),
            onPressed: _busy ? null : _reload,
            icon: const Icon(Icons.refresh_outlined),
          ),
        ],
      ),
      body: PageFrame(
        title: t('friends.title'),
        subtitle: t('friends.subtitle'),
        trailing: AirmiusButton(
          label: t('friends.invite'),
          icon: Icons.person_add_outlined,
          onPressed: _busy ? null : _invite,
        ),
        child: FutureBuilder<JsonMap>(
          future: _friendsFuture,
          builder: (context, snapshot) {
            if (snapshot.connectionState == ConnectionState.waiting) {
              return const _FriendsLoading();
            }
            if (snapshot.hasError) {
              return _FriendsError(error: snapshot.error, onRetry: _reload);
            }
            return _buildContent(snapshot.data ?? const {});
          },
        ),
      ),
    );
  }

  Widget _buildContent(JsonMap data) {
    final t = AirmiusScope.of(context).t;
    final friends = _friendMaps(data['friends']);
    final received = _friendMaps(data['receivedInvitations']);
    final sent = _friendMaps(data['sentInvitations']);
    final items = switch (_section) {
      'received' => received,
      'sent' => sent,
      _ => friends,
    };
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        AirmiusPanel(
          gradient: true,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Eyebrow(t('friends.overview')),
              const SizedBox(height: 8),
              Text(
                t('friends.overviewHint'),
                style: TextStyle(
                  color: airmiusMutedColor(context),
                  height: 1.35,
                ),
              ),
              const SizedBox(height: 14),
              Row(
                children: [
                  Expanded(
                    child: MetricCard(
                      value: '${friends.length}',
                      label: t('friends.friends'),
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: MetricCard(
                      value: '${received.length}',
                      label: t('friends.received'),
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: MetricCard(
                      value: '${sent.length}',
                      label: t('friends.sent'),
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),
        const SizedBox(height: 14),
        SegmentedButton<String>(
          segments: [
            ButtonSegment(
              value: 'friends',
              icon: const Icon(Icons.people_outline),
              label: Text(t('friends.friends')),
            ),
            ButtonSegment(
              value: 'received',
              icon: const Icon(Icons.mark_email_unread_outlined),
              label: Text(t('friends.received')),
            ),
            ButtonSegment(
              value: 'sent',
              icon: const Icon(Icons.outgoing_mail),
              label: Text(t('friends.sent')),
            ),
          ],
          selected: {_section},
          showSelectedIcon: false,
          onSelectionChanged: (selection) =>
              setState(() => _section = selection.first),
        ),
        const SizedBox(height: 14),
        if (items.isEmpty)
          _FriendsEmpty(
            text: t(switch (_section) {
              'received' => 'friends.emptyReceived',
              'sent' => 'friends.emptySent',
              _ => 'friends.emptyFriends',
            }),
          )
        else if (_section == 'friends')
          for (final friend in items) ...[
            _FriendCard(
              friend: friend,
              busy: _busy,
              onMessage: () => _message(friend),
              onRemove: () => _remove(friend),
            ),
            const SizedBox(height: 10),
          ]
        else if (_section == 'received')
          for (final invitation in items) ...[
            _ReceivedInvitationCard(
              invitation: invitation,
              busy: _busy,
              onAccept: () => _respond(invitation, accept: true),
              onDecline: () => _respond(invitation, accept: false),
            ),
            const SizedBox(height: 10),
          ]
        else
          for (final invitation in items) ...[
            _SentInvitationCard(
              invitation: invitation,
              busy: _busy,
              onWithdraw: () => _withdraw(invitation),
            ),
            const SizedBox(height: 10),
          ],
      ],
    );
  }

  Future<void> _invite() async {
    final email = await showDialog<String>(
      context: context,
      builder: (_) => const _FriendInviteDialog(),
    );
    if (email == null || !mounted) return;
    await _run(() async {
      await _client.inviteFriend(email: email);
    }, successKey: 'friends.inviteSent');
  }

  Future<void> _respond(JsonMap invitation, {required bool accept}) async {
    await _run(() async {
      final id = _friendInt(invitation['id']);
      if (accept) {
        await _client.acceptFriendInvitation(id);
      } else {
        await _client.declineFriendInvitation(id);
      }
    }, successKey: accept ? 'friends.accepted' : 'friends.declined');
  }

  Future<void> _withdraw(JsonMap invitation) async {
    final t = AirmiusScope.of(context).t;
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        backgroundColor: airmiusSurfaceColor(context),
        title: Text(t('friends.withdrawTitle')),
        content: Text(t('friends.withdrawQuestion')),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext, false),
            child: Text(t('cancel')),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(dialogContext, true),
            child: Text(t('friends.withdraw')),
          ),
        ],
      ),
    );
    if (confirmed != true || !mounted) return;
    await _run(() async {
      await _client.withdrawFriendInvitation(_friendInt(invitation['id']));
    }, successKey: 'friends.withdrawn');
  }

  Future<void> _remove(JsonMap friend) async {
    final t = AirmiusScope.of(context).t;
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        backgroundColor: airmiusSurfaceColor(context),
        title: Text(t('friends.removeTitle')),
        content: Text(t('friends.removeQuestion')),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext, false),
            child: Text(t('cancel')),
          ),
          FilledButton(
            style: FilledButton.styleFrom(
              backgroundColor: Theme.of(context).colorScheme.error,
            ),
            onPressed: () => Navigator.pop(dialogContext, true),
            child: Text(t('friends.remove')),
          ),
        ],
      ),
    );
    if (confirmed != true || !mounted) return;
    await _run(() async {
      await _client.removeFriend(_friendInt(friend['id']));
    }, successKey: 'friends.removed');
  }

  Future<void> _message(JsonMap friend) async {
    final t = AirmiusScope.of(context).t;
    setState(() => _busy = true);
    try {
      final response = await _client.createConversation(
        type: 'direct',
        participantIds: [_friendInt(friend['id'])],
      );
      final data = _friendMap(response['data']);
      final conversation = AirmiusConversation.fromJson(data);
      if (!mounted) return;
      await Navigator.of(context).push(
        MaterialPageRoute<void>(
          builder: (_) => ChatDetailScreen(
            conversationId: conversation.id,
            title: _friendText(friend['name'], fallback: t('friends.friend')),
            kind: conversation.kind,
          ),
        ),
      );
    } on AirmiusApiException catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(error.userMessage)));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _run(
    Future<void> Function() operation, {
    required String successKey,
  }) async {
    final t = AirmiusScope.of(context).t;
    setState(() => _busy = true);
    try {
      await operation();
      if (!mounted) return;
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(t(successKey))));
      _reload();
    } on AirmiusApiException catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(error.userMessage)));
    } catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            '${t('friends.actionError')} ${error is AirmiusApiException ? error.userMessage : t('common.errorDetails')}',
          ),
        ),
      );
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }
}

class _FriendCard extends StatelessWidget {
  const _FriendCard({
    required this.friend,
    required this.busy,
    required this.onMessage,
    required this.onRemove,
  });

  final JsonMap friend;
  final bool busy;
  final VoidCallback onMessage;
  final VoidCallback onRemove;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final name = _friendText(friend['name'], fallback: t('friends.friend'));
    return AirmiusPanel(
      child: Row(
        children: [
          AirmiusAvatar(
            name,
            imageUrl: _friendNullableText(friend['profile_photo_url']),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  name,
                  style: TextStyle(
                    color: airmiusTextColor(context),
                    fontSize: 16,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  _friendText(friend['email']),
                  style: TextStyle(color: airmiusMutedColor(context)),
                ),
                const SizedBox(height: 7),
                StatusPill(
                  t('friends.connected'),
                  color: Theme.of(context).colorScheme.secondary,
                ),
              ],
            ),
          ),
          PopupMenuButton<String>(
            enabled: !busy,
            tooltip: t('friends.actions'),
            onSelected: (value) =>
                value == 'message' ? onMessage() : onRemove(),
            itemBuilder: (_) => [
              PopupMenuItem(
                value: 'message',
                child: ListTile(
                  contentPadding: EdgeInsets.zero,
                  leading: const Icon(Icons.chat_bubble_outline),
                  title: Text(t('friends.message')),
                ),
              ),
              PopupMenuItem(
                value: 'remove',
                child: ListTile(
                  contentPadding: EdgeInsets.zero,
                  leading: Icon(
                    Icons.person_remove_outlined,
                    color: Theme.of(context).colorScheme.error,
                  ),
                  title: Text(t('friends.remove')),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _ReceivedInvitationCard extends StatelessWidget {
  const _ReceivedInvitationCard({
    required this.invitation,
    required this.busy,
    required this.onAccept,
    required this.onDecline,
  });

  final JsonMap invitation;
  final bool busy;
  final VoidCallback onAccept;
  final VoidCallback onDecline;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final sender = _friendMap(invitation['sender']);
    final name = _friendText(sender['name'], fallback: t('friends.friend'));
    return AirmiusPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              AirmiusAvatar(
                name,
                imageUrl: _friendNullableText(sender['profile_photo_url']),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      name,
                      style: TextStyle(
                        color: airmiusTextColor(context),
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    Text(
                      _friendText(sender['email']),
                      style: TextStyle(color: airmiusMutedColor(context)),
                    ),
                  ],
                ),
              ),
              StatusPill(
                t('friends.pending'),
                color: Theme.of(context).colorScheme.tertiary,
              ),
            ],
          ),
          const SizedBox(height: 12),
          Row(
            children: [
              Expanded(
                child: AirmiusButton(
                  label: t('friends.accept'),
                  icon: Icons.check_outlined,
                  onPressed: busy ? null : onAccept,
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: AirmiusButton(
                  label: t('friends.decline'),
                  icon: Icons.close_outlined,
                  secondary: true,
                  onPressed: busy ? null : onDecline,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _SentInvitationCard extends StatelessWidget {
  const _SentInvitationCard({
    required this.invitation,
    required this.busy,
    required this.onWithdraw,
  });

  final JsonMap invitation;
  final bool busy;
  final VoidCallback onWithdraw;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final recipient = _friendMap(invitation['recipient']);
    final name = _friendText(
      recipient['name'],
      fallback: _friendText(recipient['email'], fallback: t('friends.friend')),
    );
    return AirmiusPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              const _FriendIcon(icon: Icons.outgoing_mail),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      name,
                      style: TextStyle(
                        color: airmiusTextColor(context),
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    if (_friendText(recipient['email']).isNotEmpty)
                      Text(
                        _friendText(recipient['email']),
                        style: TextStyle(color: airmiusMutedColor(context)),
                      ),
                  ],
                ),
              ),
              StatusPill(
                t('friends.pending'),
                color: Theme.of(context).colorScheme.tertiary,
              ),
            ],
          ),
          const SizedBox(height: 12),
          AirmiusButton(
            label: t('friends.withdraw'),
            icon: Icons.undo_outlined,
            secondary: true,
            onPressed: busy ? null : onWithdraw,
          ),
        ],
      ),
    );
  }
}

class _FriendInviteDialog extends StatefulWidget {
  const _FriendInviteDialog();

  @override
  State<_FriendInviteDialog> createState() => _FriendInviteDialogState();
}

class _FriendInviteDialogState extends State<_FriendInviteDialog> {
  final _email = TextEditingController();
  String? _error;

  @override
  void dispose() {
    _email.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return AlertDialog(
      backgroundColor: airmiusSurfaceColor(context),
      title: Text(t('friends.invite')),
      content: SizedBox(
        width: 440,
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text(
              t('friends.inviteHint'),
              style: TextStyle(color: airmiusMutedColor(context), height: 1.35),
            ),
            const SizedBox(height: 14),
            TextField(
              controller: _email,
              autofocus: true,
              keyboardType: TextInputType.emailAddress,
              textInputAction: TextInputAction.done,
              onSubmitted: (_) => _submit(),
              decoration: InputDecoration(
                labelText: t('friends.email'),
                errorText: _error,
              ),
              onChanged: (_) {
                if (_error != null) setState(() => _error = null);
              },
            ),
          ],
        ),
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.pop(context),
          child: Text(t('cancel')),
        ),
        FilledButton.icon(
          onPressed: _submit,
          icon: const Icon(Icons.send_outlined),
          label: Text(t('friends.sendInvite')),
        ),
      ],
    );
  }

  void _submit() {
    final value = _email.text.trim();
    if (!RegExp(r'^[^@\s]+@[^@\s]+\.[^@\s]+$').hasMatch(value)) {
      setState(
        () => _error = AirmiusScope.of(context).t('friends.emailInvalid'),
      );
      return;
    }
    Navigator.pop(context, value);
  }
}

class _FriendIcon extends StatelessWidget {
  const _FriendIcon({required this.icon});

  final IconData icon;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 48,
      height: 48,
      decoration: BoxDecoration(
        color: airmiusAccentColor(context).withValues(alpha: .16),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(
          color: airmiusAccentColor(context).withValues(alpha: .4),
        ),
      ),
      child: Icon(icon, color: airmiusAccentColor(context)),
    );
  }
}

class _FriendsEmpty extends StatelessWidget {
  const _FriendsEmpty({required this.text});

  final String text;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 28),
        child: Column(
          children: [
            Icon(
              Icons.people_outline,
              size: 40,
              color: airmiusMutedColor(context),
            ),
            const SizedBox(height: 10),
            Text(
              text,
              textAlign: TextAlign.center,
              style: TextStyle(color: airmiusMutedColor(context)),
            ),
          ],
        ),
      ),
    );
  }
}

class _FriendsLoading extends StatelessWidget {
  const _FriendsLoading();

  @override
  Widget build(BuildContext context) {
    return const AirmiusPanel(
      child: Padding(
        padding: EdgeInsets.all(28),
        child: Center(child: CircularProgressIndicator()),
      ),
    );
  }
}

class _FriendsError extends StatelessWidget {
  const _FriendsError({required this.error, required this.onRetry});

  final Object? error;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final message = error is AirmiusApiException
        ? (error as AirmiusApiException).userMessage
        : t('common.errorDetails');
    return AirmiusPanel(
      borderColor: Theme.of(context).colorScheme.error.withValues(alpha: .5),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(
            t('friends.loadError'),
            style: TextStyle(
              color: airmiusTextColor(context),
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 8),
          Text(message, style: TextStyle(color: airmiusMutedColor(context))),
          const SizedBox(height: 14),
          AirmiusButton(
            label: t('friends.reload'),
            icon: Icons.refresh_outlined,
            secondary: true,
            onPressed: onRetry,
          ),
        ],
      ),
    );
  }
}

JsonMap _friendMap(Object? value) {
  if (value is JsonMap) return value;
  if (value is Map) {
    return value.map((key, item) => MapEntry('$key', item));
  }
  return const {};
}

List<JsonMap> _friendMaps(Object? value) => value is List
    ? value.map(_friendMap).where((item) => item.isNotEmpty).toList()
    : const [];

String _friendText(Object? value, {String fallback = ''}) {
  final text = value?.toString().trim() ?? '';
  return text.isEmpty || text == 'null' ? fallback : text;
}

String? _friendNullableText(Object? value) {
  final text = _friendText(value);
  return text.isEmpty ? null : text;
}

int _friendInt(Object? value) =>
    value is num ? value.round() : int.tryParse('$value') ?? 0;
