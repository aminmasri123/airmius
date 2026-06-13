import 'package:flutter/material.dart';

import '../core/airmius_api_models.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';
import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'global_search_screen.dart';
import 'notifications_center_screen.dart';

class ChatDetailScreen extends StatefulWidget {
  const ChatDetailScreen({
    super.key,
    required this.conversationId,
    required this.title,
    required this.kind,
  });

  final int conversationId;
  final String title;
  final String kind;

  @override
  State<ChatDetailScreen> createState() => _ChatDetailScreenState();
}

class _ChatDetailScreenState extends State<ChatDetailScreen> {
  final TextEditingController _messageController = TextEditingController();
  late Future<AirmiusPage<AirmiusMessage>> _messagesFuture;
  bool _messagesLoaded = false;
  bool _sending = false;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (_messagesLoaded) return;
    _messagesLoaded = true;
    _messagesFuture = _loadMessages();
  }

  @override
  void dispose() {
    _messageController.dispose();
    super.dispose();
  }

  Future<AirmiusPage<AirmiusMessage>> _loadMessages() {
    return AirmiusServicesScope.of(context).repositories.conversations.messages(widget.conversationId);
  }

  void _reload() {
    setState(() => _messagesFuture = _loadMessages());
  }

  Future<void> _send() async {
    final message = _messageController.text.trim();
    if (message.isEmpty || _sending) return;

    setState(() => _sending = true);
    try {
      await AirmiusServicesScope.of(context).repositories.conversations.sendMessage(widget.conversationId, message);
      if (!mounted) return;
      _messageController.clear();
      setState(() {
        _sending = false;
        _messagesFuture = _loadMessages();
      });
    } catch (_) {
      if (!mounted) return;
      setState(() => _sending = false);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(AirmiusScope.of(context).t('messages.error'))),
      );
    }
  }

  void _showActionResult(String message) {
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(message)));
  }

  Future<void> _reactToMessage(AirmiusMessage message, String reaction) async {
    try {
      await AirmiusServicesScope.of(context).clientForSession(AirmiusServicesScope.of(context).authState.session).reactToMessage(message.id, reaction);
      if (!mounted) return;
      _showActionResult('Reaktion gespeichert.');
      _reload();
    } catch (_) {
      if (!mounted) return;
      _showActionResult('Reaktion konnte nicht gespeichert werden.');
    }
  }

  Future<void> _hideMessage(AirmiusMessage message) async {
    try {
      await AirmiusServicesScope.of(context).clientForSession(AirmiusServicesScope.of(context).authState.session).hideMessage(message.id);
      if (!mounted) return;
      _showActionResult('Nachricht ausgeblendet.');
      _reload();
    } catch (_) {
      if (!mounted) return;
      _showActionResult('Nachricht konnte nicht ausgeblendet werden.');
    }
  }

  Future<void> _deleteMessage(AirmiusMessage message) async {
    try {
      await AirmiusServicesScope.of(context).clientForSession(AirmiusServicesScope.of(context).authState.session).deleteMessage(message.id);
      if (!mounted) return;
      _showActionResult('Nachricht geloescht.');
      _reload();
    } catch (_) {
      if (!mounted) return;
      _showActionResult('Nachricht konnte nicht geloescht werden.');
    }
  }

  void _reportMessage(AirmiusMessage message) {
    _showActionResult('Meldung vorbereitet.');
  }

  @override
  Widget build(BuildContext context) {
    final scope = AirmiusScope.of(context);
    final authState = AirmiusServicesScope.of(context).authState;
    final userLabel = initialsFromName(
      [
        authState.user?.firstName,
        authState.user?.lastName,
      ].whereType<String>().map((part) => part.trim()).where((part) => part.isNotEmpty).join(' '),
      fallback: initialsFromName(authState.user?.name, fallback: ''),
    );

    return Scaffold(
      appBar: AppBar(
        backgroundColor: AirmiusColors.header,
        surfaceTintColor: Colors.transparent,
        elevation: 0,
        leading: IconButton(
          tooltip: 'Menue',
          icon: const Icon(Icons.menu, color: AirmiusColors.text),
          onPressed: () => Navigator.maybePop(context),
        ),
        titleSpacing: 0,
        title: const Text('Chat', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
        actions: [
          IconButton(
            tooltip: 'Suche',
            onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => GlobalSearchScreen())),
            icon: const Icon(Icons.search, color: AirmiusColors.text),
          ),
          IconButton(
            tooltip: 'Chats',
            onPressed: () => Navigator.pop(context),
            icon: const Icon(Icons.chat_bubble_outline, color: AirmiusColors.text),
          ),
          IconButton(
            tooltip: 'Benachrichtigungen',
            onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => NotificationsCenterScreen())),
            icon: const Icon(Icons.notifications_none, color: AirmiusColors.text),
          ),
          UserBubble(label: userLabel.isEmpty ? 'ZK' : userLabel, imageUrl: authState.user?.avatarUrl),
          const Icon(Icons.keyboard_arrow_down, color: AirmiusColors.muted),
          const SizedBox(width: 8),
        ],
      ),
      body: Center(
        child: ConstrainedBox(
          constraints: const BoxConstraints(maxWidth: 420),
          child: Container(
            margin: const EdgeInsets.all(12),
            decoration: BoxDecoration(
              color: AirmiusColors.card,
              borderRadius: BorderRadius.circular(8),
              border: Border.all(color: AirmiusColors.border),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                _ConversationHeader(title: widget.title, subtitle: '${widget.kind} - 2 Mitglieder'),
                Container(height: 1, color: AirmiusColors.border),
                const Padding(
                  padding: EdgeInsets.fromLTRB(12, 12, 12, 0),
                  child: _ChatSearchField(),
                ),
                Expanded(
                  child: FutureBuilder<AirmiusPage<AirmiusMessage>>(
                    future: _messagesFuture,
                    builder: (context, snapshot) {
                      if (snapshot.connectionState == ConnectionState.waiting) {
                        return const Padding(padding: EdgeInsets.all(12), child: _LoadingMessages());
                      }
                      if (snapshot.hasError) {
                        return Padding(padding: const EdgeInsets.all(12), child: _ErrorMessages(onRetry: _reload));
                      }

                      final messages = (snapshot.data?.items ?? const <AirmiusMessage>[]).reversed.toList();
                      if (messages.isEmpty) {
                        return Center(
                          child: Padding(
                            padding: const EdgeInsets.all(24),
                            child: Text(scope.t('messages.noMessages'), textAlign: TextAlign.center, style: const TextStyle(color: AirmiusColors.muted, fontWeight: FontWeight.w800)),
                          ),
                        );
                      }

                      return ListView.separated(
                        padding: const EdgeInsets.fromLTRB(12, 12, 12, 14),
                        itemCount: messages.length,
                        separatorBuilder: (_, __) => const SizedBox(height: 10),
                        itemBuilder: (context, index) => _ChatBubble(
                          message: messages[index],
                          onReact: _reactToMessage,
                          onHide: _hideMessage,
                          onDelete: _deleteMessage,
                          onReport: _reportMessage,
                        ),
                      );
                    },
                  ),
                ),
                Container(height: 1, color: AirmiusColors.border),
                _MessageComposer(
                  controller: _messageController,
                  sending: _sending,
                  onSend: _send,
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _ConversationHeader extends StatelessWidget {
  const _ConversationHeader({required this.title, required this.subtitle});

  final String title;
  final String subtitle;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(12, 14, 12, 14),
      child: Row(
        children: [
          IconButton(
            tooltip: 'Zurueck',
            onPressed: () => Navigator.pop(context),
            icon: const Icon(Icons.arrow_back, color: AirmiusColors.text),
          ),
          const SizedBox(width: 4),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(title, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(color: AirmiusColors.text, fontSize: 20, fontWeight: FontWeight.w900)),
                const SizedBox(height: 3),
                Text(subtitle, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(color: AirmiusColors.muted, fontSize: 13, fontWeight: FontWeight.w700)),
              ],
            ),
          ),
          Container(
            width: 40,
            height: 40,
            decoration: BoxDecoration(color: AirmiusColors.input, borderRadius: BorderRadius.circular(8)),
            child: const Icon(Icons.notifications_none, color: AirmiusColors.text, size: 22),
          ),
        ],
      ),
    );
  }
}

class _ChatSearchField extends StatelessWidget {
  const _ChatSearchField();

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 62,
      child: TextField(
        style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w700),
        decoration: InputDecoration(
          hintText: 'Nachrichten in diesem Chat suchen',
          prefixIcon: const Icon(Icons.search, color: AirmiusColors.muted),
          filled: true,
          fillColor: AirmiusColors.input,
          contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 18),
          enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(8), borderSide: const BorderSide(color: AirmiusColors.border)),
          focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(8), borderSide: const BorderSide(color: AirmiusColors.blue)),
        ),
      ),
    );
  }
}

class _MessageComposer extends StatelessWidget {
  const _MessageComposer({required this.controller, required this.sending, required this.onSend});

  final TextEditingController controller;
  final bool sending;
  final VoidCallback onSend;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.all(12),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.end,
        children: [
          Expanded(
            child: TextField(
              controller: controller,
              minLines: 1,
              maxLines: 4,
              style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w800),
              decoration: InputDecoration(
                hintText: 'Nachricht schreiben...',
                filled: true,
                fillColor: AirmiusColors.input,
                contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 16),
                enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(8), borderSide: const BorderSide(color: AirmiusColors.border)),
                focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(8), borderSide: const BorderSide(color: AirmiusColors.blue)),
              ),
            ),
          ),
          const SizedBox(width: 8),
          _ComposerIconButton(icon: Icons.attach_file, onPressed: () {}),
          const SizedBox(width: 8),
          SizedBox(
            width: 56,
            height: 56,
            child: FilledButton(
              onPressed: sending ? null : onSend,
              style: FilledButton.styleFrom(
                padding: EdgeInsets.zero,
                backgroundColor: sending ? AirmiusColors.mutedSoft : AirmiusColors.blue,
                foregroundColor: AirmiusColors.header,
                disabledBackgroundColor: AirmiusColors.mutedSoft,
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
              ),
              child: sending ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2, color: AirmiusColors.header)) : const Icon(Icons.send_outlined),
            ),
          ),
        ],
      ),
    );
  }
}

class _ComposerIconButton extends StatelessWidget {
  const _ComposerIconButton({required this.icon, required this.onPressed});

  final IconData icon;
  final VoidCallback onPressed;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: 48,
      height: 56,
      child: OutlinedButton(
        onPressed: onPressed,
        style: OutlinedButton.styleFrom(
          padding: EdgeInsets.zero,
          foregroundColor: AirmiusColors.text,
          side: const BorderSide(color: AirmiusColors.border),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
        ),
        child: Icon(icon, size: 22),
      ),
    );
  }
}
class _ChatBubble extends StatelessWidget {
  const _ChatBubble({
    required this.message,
    required this.onReact,
    required this.onHide,
    required this.onDelete,
    required this.onReport,
  });

  final AirmiusMessage message;
  final Future<void> Function(AirmiusMessage message, String reaction) onReact;
  final Future<void> Function(AirmiusMessage message) onHide;
  final Future<void> Function(AirmiusMessage message) onDelete;
  final void Function(AirmiusMessage message) onReport;

  @override
  Widget build(BuildContext context) {
    final isMine = message.mine;
    final bubbleColor = isMine ? AirmiusColors.lightCard : AirmiusColors.input;
    final primaryText = isMine ? AirmiusColors.lightText : AirmiusColors.text;
    final secondaryText = isMine ? AirmiusColors.lightMuted : AirmiusColors.muted;
    final reactionCounts = _reactionCounts();
    return Align(
      alignment: isMine ? Alignment.centerRight : Alignment.centerLeft,
      child: ConstrainedBox(
        constraints: const BoxConstraints(maxWidth: 260),
        child: GestureDetector(
          onTap: () => _openReactionPicker(context),
          onLongPress: () => _openMessageActions(context, isMine),
          onDoubleTap: () => onReact(message, 'heart'),
          child: Container(
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(
              color: bubbleColor,
              borderRadius: BorderRadius.only(
                topLeft: const Radius.circular(14),
                topRight: const Radius.circular(14),
                bottomLeft: Radius.circular(isMine ? 14 : 4),
                bottomRight: Radius.circular(isMine ? 4 : 14),
              ),
              border: Border.all(color: isMine ? AirmiusColors.lightBorder : AirmiusColors.input),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Expanded(child: Text(message.senderName, maxLines: 1, overflow: TextOverflow.ellipsis, style: TextStyle(color: primaryText, fontSize: 12, fontWeight: FontWeight.w800))),
                    Text(_dateTimeLabel(message.createdAt), style: TextStyle(color: secondaryText, fontSize: 12)),
                  ],
                ),
                const SizedBox(height: 6),
                Text(message.message, style: TextStyle(color: primaryText, height: 1.35, fontWeight: FontWeight.w900)),
                if (reactionCounts.isNotEmpty) ...[
                  const SizedBox(height: 8),
                  Wrap(
                    spacing: 5,
                    runSpacing: 5,
                    children: [
                      for (final entry in reactionCounts.entries)
                        Container(
                          height: 26,
                          padding: const EdgeInsets.symmetric(horizontal: 8),
                          decoration: BoxDecoration(
                            color: isMine ? AirmiusColors.lightInput : AirmiusColors.card,
                            borderRadius: BorderRadius.circular(999),
                            border: Border.all(color: isMine ? AirmiusColors.lightBorder : AirmiusColors.border),
                          ),
                          child: Row(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              Icon(_reactionIcon(entry.key), size: 14, color: secondaryText),
                              const SizedBox(width: 4),
                              Text('${entry.value}', style: TextStyle(color: secondaryText, fontSize: 12, fontWeight: FontWeight.w900)),
                            ],
                          ),
                        ),
                    ],
                  ),
                ],
                if (isMine) ...[
                  const SizedBox(height: 4),
                  Align(alignment: Alignment.centerRight, child: Icon(Icons.done_all, size: 14, color: secondaryText)),
                ],
              ],
            ),
          ),
        ),
      ),
    );
  }

  Map<String, int> _reactionCounts() {
    final counts = <String, int>{};
    for (final reaction in message.reactions) {
      if (reaction.reaction.isEmpty) continue;
      counts[reaction.reaction] = (counts[reaction.reaction] ?? 0) + 1;
    }
    return counts;
  }

  IconData _reactionIcon(String reaction) {
    if (reaction == 'heart') return Icons.favorite_border;
    if (reaction == 'ok') return Icons.check_circle_outline;
    return Icons.thumb_up_alt_outlined;
  }

  void _openReactionPicker(BuildContext context) {
    showModalBottomSheet<void>(
      context: context,
      backgroundColor: AirmiusColors.card,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(18))),
      builder: (context) {
        return SafeArea(
          child: Padding(
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 18),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Container(width: 38, height: 4, decoration: BoxDecoration(color: AirmiusColors.borderStrong, borderRadius: BorderRadius.circular(99))),
                const SizedBox(height: 16),
                const Text('Reaktion auswaehlen', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
                const SizedBox(height: 14),
                Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    _QuickReaction(icon: Icons.thumb_up_alt_outlined, label: 'Like', onTap: () {
                      Navigator.pop(context);
                      onReact(message, 'like');
                    }),
                    _QuickReaction(icon: Icons.favorite_border, label: 'Herz', onTap: () {
                      Navigator.pop(context);
                      onReact(message, 'heart');
                    }),
                    _QuickReaction(icon: Icons.check_circle_outline, label: 'OK', onTap: () {
                      Navigator.pop(context);
                      onReact(message, 'ok');
                    }),
                  ],
                ),
              ],
            ),
          ),
        );
      },
    );
  }

  void _openMessageActions(BuildContext context, bool isMine) {
    showModalBottomSheet<void>(
      context: context,
      backgroundColor: AirmiusColors.card,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(18))),
      builder: (context) {
        return SafeArea(
          child: Padding(
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 18),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Container(width: 38, height: 4, decoration: BoxDecoration(color: AirmiusColors.borderStrong, borderRadius: BorderRadius.circular(99))),
                const SizedBox(height: 16),
                Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    _QuickReaction(icon: Icons.thumb_up_alt_outlined, label: 'Like', onTap: () {
                      Navigator.pop(context);
                      onReact(message, 'like');
                    }),
                    _QuickReaction(icon: Icons.favorite_border, label: 'Herz', onTap: () {
                      Navigator.pop(context);
                      onReact(message, 'heart');
                    }),
                    _QuickReaction(icon: Icons.check_circle_outline, label: 'OK', onTap: () {
                      Navigator.pop(context);
                      onReact(message, 'ok');
                    }),
                  ],
                ),
                const SizedBox(height: 14),
                _SheetAction(icon: Icons.visibility_off_outlined, label: 'Nur fuer mich ausblenden', onTap: () {
                  Navigator.pop(context);
                  onHide(message);
                }),
                if (isMine)
                  _SheetAction(icon: Icons.delete_outline, label: 'Nachricht loeschen', danger: true, onTap: () {
                    Navigator.pop(context);
                    onDelete(message);
                  })
                else
                  _SheetAction(icon: Icons.flag_outlined, label: 'Nachricht melden', danger: true, onTap: () {
                    Navigator.pop(context);
                    onReport(message);
                  }),
              ],
            ),
          ),
        );
      },
    );
  }
}

class _QuickReaction extends StatelessWidget {
  const _QuickReaction({required this.icon, required this.label, required this.onTap});

  final IconData icon;
  final String label;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 4),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(999),
        child: Container(
          height: 40,
          padding: const EdgeInsets.symmetric(horizontal: 14),
          decoration: BoxDecoration(color: AirmiusColors.input, borderRadius: BorderRadius.circular(999), border: Border.all(color: AirmiusColors.border)),
          alignment: Alignment.center,
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(icon, size: 17, color: AirmiusColors.text),
              const SizedBox(width: 6),
              Text(label, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
            ],
          ),
        ),
      ),
    );
  }
}

class _SheetAction extends StatelessWidget {
  const _SheetAction({required this.icon, required this.label, required this.onTap, this.danger = false});

  final IconData icon;
  final String label;
  final VoidCallback onTap;
  final bool danger;

  @override
  Widget build(BuildContext context) {
    final color = danger ? AirmiusColors.red : AirmiusColors.text;
    return ListTile(
      onTap: onTap,
      leading: Icon(icon, color: color),
      title: Text(label, style: TextStyle(color: color, fontWeight: FontWeight.w800)),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
    );
  }
}

class _LoadingMessages extends StatelessWidget {
  const _LoadingMessages();

  @override
  Widget build(BuildContext context) {
    final scope = AirmiusScope.of(context);
    return AirmiusPanel(
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 20),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2, color: AirmiusColors.blue)),
            const SizedBox(width: 12),
            Text(scope.t('status.loading'), style: const TextStyle(color: AirmiusColors.muted, fontWeight: FontWeight.w800)),
          ],
        ),
      ),
    );
  }
}

class _ErrorMessages extends StatelessWidget {
  const _ErrorMessages({required this.onRetry});

  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    final scope = AirmiusScope.of(context);
    return AirmiusPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const Icon(Icons.error_outline, color: AirmiusColors.red, size: 34),
          const SizedBox(height: 10),
          Text(scope.t('messages.error'), textAlign: TextAlign.center, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
          const SizedBox(height: 12),
          AirmiusButton(label: scope.t('messages.retry'), icon: Icons.refresh_outlined, onPressed: onRetry, secondary: true),
        ],
      ),
    );
  }
}

String _dateTimeLabel(DateTime value) {
  if (value.millisecondsSinceEpoch == 0) return '';
  final day = value.day.toString().padLeft(2, '0');
  final month = value.month.toString().padLeft(2, '0');
  final hour = value.hour.toString().padLeft(2, '0');
  final minute = value.minute.toString().padLeft(2, '0');
  return '$day.$month., $hour:$minute';
}
