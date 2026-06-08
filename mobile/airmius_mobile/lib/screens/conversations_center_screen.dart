import 'package:flutter/material.dart';

import '../core/airmius_api_models.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';
import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'chat_detail_screen.dart';

class ConversationsCenterScreen extends StatefulWidget {
  const ConversationsCenterScreen({super.key, this.embedded = false});

  final bool embedded;

  @override
  State<ConversationsCenterScreen> createState() => _ConversationsCenterScreenState();
}

class _ConversationsCenterScreenState extends State<ConversationsCenterScreen> {
  String _query = '';
  late Future<AirmiusPage<AirmiusConversation>> _conversationsFuture;

  @override
  void initState() {
    super.initState();
    _conversationsFuture = _loadConversations();
  }

  Future<AirmiusPage<AirmiusConversation>> _loadConversations() {
    return AirmiusServicesScope.of(context).repositories.conversations.conversations();
  }

  void _reload() {
    setState(() => _conversationsFuture = _loadConversations());
  }

  @override
  Widget build(BuildContext context) {
    final scope = AirmiusScope.of(context);
    final body = _buildInbox(context);
    if (widget.embedded) return body;

    return Scaffold(
      appBar: AppBar(
        backgroundColor: AirmiusColors.header,
        surfaceTintColor: Colors.transparent,
        title: Text(scope.t('messages.title'), style: const TextStyle(fontWeight: FontWeight.w900)),
      ),
      body: PageFrame(
        title: scope.t('messages.title'),
        subtitle: scope.t('messages.subtitle'),
        showHeader: true,
        child: body,
      ),
    );
  }

  Widget _buildInbox(BuildContext context) {
    final scope = AirmiusScope.of(context);
    return RefreshIndicator(
      color: AirmiusColors.blue,
      backgroundColor: AirmiusColors.card,
      onRefresh: () async {
        _reload();
        await _conversationsFuture;
      },
      child: FutureBuilder<AirmiusPage<AirmiusConversation>>(
        future: _conversationsFuture,
        builder: (context, snapshot) {
          if (snapshot.connectionState == ConnectionState.waiting) {
            return const _ScrollableInbox(child: _LoadingConversations());
          }
          if (snapshot.hasError) {
            return _ScrollableInbox(child: _ErrorConversations(onRetry: _reload));
          }

          final allConversations = snapshot.data?.items ?? const <AirmiusConversation>[];
          final normalized = _query.trim().toLowerCase();
          final conversations = allConversations.where((item) {
            return normalized.isEmpty ||
                item.title.toLowerCase().contains(normalized) ||
                item.lastMessage.toLowerCase().contains(normalized) ||
                item.kind.toLowerCase().contains(normalized);
          }).toList();
          final unread = allConversations.fold<int>(0, (sum, item) => sum + item.unreadCount);
          final clubChats = allConversations.where((item) => _kindLabel(scope, item.kind) == scope.t('messages.club')).length;

          return _ScrollableInbox(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Row(
                  children: [
                    Expanded(child: MetricCard(value: '${allConversations.length}', label: scope.t('messages.chat'))),
                    const SizedBox(width: 10),
                    Expanded(child: MetricCard(value: '$unread', label: scope.t('messages.unread'))),
                    const SizedBox(width: 10),
                    Expanded(child: MetricCard(value: '$clubChats', label: scope.t('messages.club'))),
                  ],
                ),
                const SizedBox(height: 14),
                SearchBox(hint: scope.t('messages.search'), onChanged: (value) => setState(() => _query = value)),
                const SizedBox(height: 14),
                if (conversations.isEmpty)
                  EmptyPanel(scope.t('messages.empty'))
                else
                  for (final conversation in conversations) ...[
                    _ConversationCard(conversation: conversation),
                    const SizedBox(height: 12),
                  ],
              ],
            ),
          );
        },
      ),
    );
  }
}

class _ScrollableInbox extends StatelessWidget {
  const _ScrollableInbox({required this.child});

  final Widget child;

  @override
  Widget build(BuildContext context) {
    return SingleChildScrollView(
      physics: const AlwaysScrollableScrollPhysics(),
      child: child,
    );
  }
}

class _ConversationCard extends StatelessWidget {
  const _ConversationCard({required this.conversation});

  final AirmiusConversation conversation;

  @override
  Widget build(BuildContext context) {
    final scope = AirmiusScope.of(context);
    return AirmiusPanel(
      onTap: () => Navigator.push(
        context,
        MaterialPageRoute(
          builder: (_) => ChatDetailScreen(
            conversationId: conversation.id,
            title: conversation.title,
            kind: _kindLabel(scope, conversation.kind),
          ),
        ),
      ),
      borderColor: conversation.unreadCount > 0 ? AirmiusColors.blue.withValues(alpha: 0.55) : AirmiusColors.border,
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          AirmiusAvatar(conversation.title),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Expanded(child: Text(conversation.title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900))),
                    Text(_shortTime(conversation.timeLabel), style: const TextStyle(color: AirmiusColors.mutedSoft, fontSize: 12)),
                  ],
                ),
                const SizedBox(height: 5),
                Text(conversation.lastMessage.isEmpty ? scope.t('messages.noMessages') : conversation.lastMessage, style: const TextStyle(color: AirmiusColors.muted, height: 1.3)),
                const SizedBox(height: 9),
                Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: [
                    StatusPill(_kindLabel(scope, conversation.kind)),
                    if (conversation.unreadCount > 0) StatusPill('${conversation.unreadCount} ${scope.t('messages.unread')}', color: AirmiusColors.green),
                  ],
                ),
              ],
            ),
          ),
          const Icon(Icons.chevron_right, color: AirmiusColors.muted),
        ],
      ),
    );
  }
}

class _LoadingConversations extends StatelessWidget {
  const _LoadingConversations();

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

class _ErrorConversations extends StatelessWidget {
  const _ErrorConversations({required this.onRetry});

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

String _kindLabel(AirmiusScope scope, String rawKind) {
  final kind = rawKind.toLowerCase();
  if (kind.contains('club') || kind.contains('verein') || kind.contains('admin')) return scope.t('messages.club');
  if (kind.contains('team') || kind.contains('training')) return 'Team';
  if (kind.contains('event')) return 'Event';
  if (kind.contains('support')) return 'Support';
  return scope.t('messages.chat');
}

String _shortTime(String value) {
  final date = DateTime.tryParse(value);
  if (date == null) return value;
  final hour = date.hour.toString().padLeft(2, '0');
  final minute = date.minute.toString().padLeft(2, '0');
  return '$hour:$minute';
}
