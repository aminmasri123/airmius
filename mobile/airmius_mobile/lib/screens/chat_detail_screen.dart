import 'package:flutter/material.dart';

import '../core/airmius_api_models.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';
import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

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

  @override
  Widget build(BuildContext context) {
    final scope = AirmiusScope.of(context);
    return Scaffold(
      appBar: AppBar(
        backgroundColor: AirmiusColors.header,
        surfaceTintColor: Colors.transparent,
        title: Text(widget.title, style: const TextStyle(fontWeight: FontWeight.w900)),
      ),
      body: PageFrame(
        title: widget.title,
        subtitle: widget.kind,
        showHeader: true,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            FutureBuilder<AirmiusPage<AirmiusMessage>>(
              future: _messagesFuture,
              builder: (context, snapshot) {
                if (snapshot.connectionState == ConnectionState.waiting) {
                  return const _LoadingMessages();
                }
                if (snapshot.hasError) {
                  return _ErrorMessages(onRetry: _reload);
                }

                final messages = (snapshot.data?.items ?? const <AirmiusMessage>[]).reversed.toList();
                if (messages.isEmpty) {
                  return EmptyPanel(scope.t('messages.noMessages'));
                }

                return Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    for (final message in messages) ...[
                      _ChatBubble(message: message),
                      const SizedBox(height: 10),
                    ],
                  ],
                );
              },
            ),
            const SizedBox(height: 14),
            AirmiusPanel(
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.end,
                children: [
                  Expanded(
                    child: AirmiusTextField(
                      label: scope.t('messages.write'),
                      icon: Icons.chat_bubble_outline,
                      maxLines: 3,
                      controller: _messageController,
                    ),
                  ),
                  const SizedBox(width: 10),
                  AirmiusButton(
                    label: _sending ? scope.t('status.loading') : scope.t('messages.send'),
                    icon: Icons.send_outlined,
                    onPressed: _sending ? null : _send,
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _ChatBubble extends StatelessWidget {
  const _ChatBubble({required this.message});

  final AirmiusMessage message;

  @override
  Widget build(BuildContext context) {
    return Align(
      alignment: message.mine ? Alignment.centerRight : Alignment.centerLeft,
      child: ConstrainedBox(
        constraints: const BoxConstraints(maxWidth: 620),
        child: AirmiusPanel(
          borderColor: message.mine ? AirmiusColors.blue.withValues(alpha: 0.45) : AirmiusColors.border,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Expanded(child: Text(message.senderName, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900))),
                  Text(_timeLabel(message.createdAt), style: const TextStyle(color: AirmiusColors.mutedSoft, fontSize: 12)),
                ],
              ),
              const SizedBox(height: 6),
              Text(message.message, style: const TextStyle(color: AirmiusColors.muted, height: 1.35, fontWeight: FontWeight.w700)),
              const SizedBox(height: 10),
              StatusPill(message.status, color: message.mine ? AirmiusColors.blue : AirmiusColors.green),
            ],
          ),
        ),
      ),
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

String _timeLabel(DateTime value) {
  if (value.millisecondsSinceEpoch == 0) return '';
  final hour = value.hour.toString().padLeft(2, '0');
  final minute = value.minute.toString().padLeft(2, '0');
  return '$hour:$minute';
}
