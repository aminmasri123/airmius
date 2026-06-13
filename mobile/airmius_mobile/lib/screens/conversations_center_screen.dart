import 'package:flutter/material.dart';

import '../core/airmius_api_models.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';
import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'chat_detail_screen.dart';
import 'global_search_screen.dart';
import 'new_conversation_screen.dart';
import 'notifications_center_screen.dart';

class ConversationsCenterScreen extends StatefulWidget {
  const ConversationsCenterScreen({super.key, this.embedded = false});

  final bool embedded;

  @override
  State<ConversationsCenterScreen> createState() => _ConversationsCenterScreenState();
}

class _ConversationsCenterScreenState extends State<ConversationsCenterScreen> {
  String _query = '';
  String _filter = 'direct';
  bool _conversationsLoaded = false;
  late Future<AirmiusPage<AirmiusConversation>> _conversationsFuture;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (_conversationsLoaded) return;
    _conversationsLoaded = true;
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
    final authState = AirmiusServicesScope.of(context).authState;
    final userLabel = initialsFromName(
      [
        authState.user?.firstName,
        authState.user?.lastName,
      ].whereType<String>().map((part) => part.trim()).where((part) => part.isNotEmpty).join(' '),
      fallback: initialsFromName(authState.user?.name, fallback: ''),
    );
    final body = _buildInbox(context);
    if (widget.embedded) return body;

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
            icon: const Icon(Icons.search, color: AirmiusColors.muted),
          ),
          IconButton(
            tooltip: 'Nachrichten aktualisieren',
            onPressed: _reload,
            icon: const Icon(Icons.chat_bubble_outline, color: AirmiusColors.muted),
          ),
          IconButton(
            tooltip: 'Benachrichtigungen',
            onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => NotificationsCenterScreen())),
            icon: const Icon(Icons.notifications_none, color: AirmiusColors.muted),
          ),
          UserBubble(label: userLabel.isEmpty ? 'GK' : userLabel, imageUrl: authState.user?.avatarUrl),
          const SizedBox(width: 12),
        ],
      ),
      body: PageFrame(
        title: scope.t('messages.title'),
        subtitle: scope.t('messages.subtitle'),
        showHeader: false,
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
            final matchesFilter = _typeKey(item.kind) == _filter;
            final matchesSearch = normalized.isEmpty ||
                item.title.toLowerCase().contains(normalized) ||
                item.lastMessage.toLowerCase().contains(normalized) ||
                item.kind.toLowerCase().contains(normalized);
            return matchesFilter && matchesSearch;
          }).toList();
          final counts = {
            'direct': allConversations.where((item) => _typeKey(item.kind) == 'direct').length,
            'team': allConversations.where((item) => _typeKey(item.kind) == 'team').length,
            'group': allConversations.where((item) => _typeKey(item.kind) == 'group').length,
          };

          return _ScrollableInbox(
            child: _ChatListPanel(
              conversations: conversations,
              allConversationsCount: allConversations.length,
              counts: counts,
              activeFilter: _filter,
              onFilterChanged: (value) => setState(() => _filter = value),
              onSearchChanged: (value) => setState(() => _query = value),
              onNewConversation: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const NewConversationScreen())),
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

class _ChatListPanel extends StatelessWidget {
  const _ChatListPanel({
    required this.conversations,
    required this.allConversationsCount,
    required this.counts,
    required this.activeFilter,
    required this.onFilterChanged,
    required this.onSearchChanged,
    required this.onNewConversation,
  });

  final List<AirmiusConversation> conversations;
  final int allConversationsCount;
  final Map<String, int> counts;
  final String activeFilter;
  final ValueChanged<String> onFilterChanged;
  final ValueChanged<String> onSearchChanged;
  final VoidCallback onNewConversation;

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
        color: AirmiusColors.card,
        borderRadius: BorderRadius.circular(8),
        border: Border.all(color: AirmiusColors.border),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(14, 14, 14, 12),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Row(
                  children: [
                    const Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text('Chat', style: TextStyle(color: AirmiusColors.text, fontSize: 20, fontWeight: FontWeight.w900)),
                          SizedBox(height: 5),
                          Text('Erst Person oder Gruppe waehlen, dann oeffnen.', style: TextStyle(color: AirmiusColors.muted, fontSize: 13, height: 1.25)),
                        ],
                      ),
                    ),
                    SizedBox(
                      width: 40,
                      height: 40,
                      child: FilledButton(
                        onPressed: onNewConversation,
                        style: FilledButton.styleFrom(
                          padding: EdgeInsets.zero,
                          backgroundColor: AirmiusColors.text,
                          foregroundColor: AirmiusColors.header,
                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                        ),
                        child: const Icon(Icons.add, size: 22),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 18),
                SearchBox(hint: 'Person, Team oder Training suchen', onChanged: onSearchChanged),
                const SizedBox(height: 10),
                SingleChildScrollView(
                  scrollDirection: Axis.horizontal,
                  child: Row(
                    children: [
                      _FilterButton(icon: Icons.person_outline, label: 'Personen', count: counts['direct'] ?? allConversationsCount, active: activeFilter == 'direct', onTap: () => onFilterChanged('direct')),
                      const SizedBox(width: 8),
                      _FilterButton(icon: Icons.groups_outlined, label: 'Teams', count: counts['team'] ?? 0, active: activeFilter == 'team', onTap: () => onFilterChanged('team')),
                      const SizedBox(width: 8),
                      _FilterButton(icon: Icons.forum_outlined, label: 'Gruppen', count: counts['group'] ?? 0, active: activeFilter == 'group', onTap: () => onFilterChanged('group')),
                    ],
                  ),
                ),
              ],
            ),
          ),
          Container(height: 1, color: AirmiusColors.border),
          if (conversations.isEmpty)
            const Padding(
              padding: EdgeInsets.symmetric(horizontal: 20, vertical: 36),
              child: Text('Keine passenden Chats fuer diesen Filter.', textAlign: TextAlign.center, style: TextStyle(color: AirmiusColors.muted, fontWeight: FontWeight.w700)),
            )
          else
            Padding(
              padding: const EdgeInsets.fromLTRB(8, 8, 8, 10),
              child: Column(
                children: [
                  for (final conversation in conversations) _ConversationCard(conversation: conversation),
                ],
              ),
            ),
        ],
      ),
    );
  }
}

class _FilterButton extends StatelessWidget {
  const _FilterButton({required this.icon, required this.label, required this.count, required this.active, required this.onTap});

  final IconData icon;
  final String label;
  final int count;
  final bool active;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(8),
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
        decoration: BoxDecoration(
          color: active ? AirmiusColors.text : AirmiusColors.card,
          borderRadius: BorderRadius.circular(8),
          border: Border.all(color: active ? AirmiusColors.text : AirmiusColors.border),
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(icon, size: 15, color: active ? AirmiusColors.header : AirmiusColors.muted),
            const SizedBox(width: 7),
            Text(label, style: TextStyle(color: active ? AirmiusColors.header : AirmiusColors.muted, fontSize: 13, fontWeight: FontWeight.w800)),
            const SizedBox(width: 6),
            Text('$count', style: TextStyle(color: active ? AirmiusColors.header.withValues(alpha: 0.72) : AirmiusColors.mutedSoft, fontSize: 12, fontWeight: FontWeight.w800)),
          ],
        ),
      ),
    );
  }
}

class _ConversationCard extends StatelessWidget {
  const _ConversationCard({required this.conversation});

  final AirmiusConversation conversation;

  @override
  Widget build(BuildContext context) {
    final scope = AirmiusScope.of(context);
    return InkWell(
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
      borderRadius: BorderRadius.circular(8),
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 10),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Container(
              width: 42,
              height: 42,
              alignment: Alignment.center,
              decoration: BoxDecoration(
                color: AirmiusColors.text,
                borderRadius: BorderRadius.circular(7),
              ),
              child: Text(initialsFromName(conversation.title, fallback: '??'), style: const TextStyle(color: AirmiusColors.header, fontSize: 13, fontWeight: FontWeight.w900)),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Expanded(child: Text(conversation.title, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(color: AirmiusColors.text, fontSize: 14, fontWeight: FontWeight.w900))),
                      if (conversation.unreadCount > 0) ...[
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 3),
                          decoration: BoxDecoration(color: AirmiusColors.red, borderRadius: BorderRadius.circular(999)),
                          child: Text(conversation.unreadCount > 99 ? '99+' : '${conversation.unreadCount}', style: const TextStyle(color: Colors.white, fontSize: 11, fontWeight: FontWeight.w900)),
                        ),
                        const SizedBox(width: 7),
                      ],
                      Text(_shortTime(conversation.timeLabel), style: const TextStyle(color: AirmiusColors.mutedSoft, fontSize: 12)),
                    ],
                  ),
                  const SizedBox(height: 4),
                  Text(
                    conversation.lastMessage.isEmpty ? scope.t('messages.noMessages') : conversation.lastMessage,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(color: conversation.unreadCount > 0 ? AirmiusColors.text : AirmiusColors.muted, fontSize: 12, height: 1.25, fontWeight: conversation.unreadCount > 0 ? FontWeight.w900 : FontWeight.w700),
                  ),
                  const SizedBox(height: 3),
                  Text('${_kindLabel(scope, conversation.kind)} - 2 Mitglieder', maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(color: AirmiusColors.blue, fontSize: 11, fontWeight: FontWeight.w800)),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

String _typeKey(String rawKind) {
  final kind = rawKind.toLowerCase();
  if (kind.contains('team') || kind.contains('training')) return 'team';
  if (kind.contains('group') || kind.contains('gruppe')) return 'group';
  return 'direct';
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
