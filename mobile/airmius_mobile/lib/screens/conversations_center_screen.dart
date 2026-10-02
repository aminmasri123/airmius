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
import 'profile_screen.dart';

class ConversationsCenterScreen extends StatefulWidget {
  const ConversationsCenterScreen({super.key, this.embedded = false});

  final bool embedded;

  @override
  State<ConversationsCenterScreen> createState() =>
      _ConversationsCenterScreenState();
}

class _ConversationsCenterScreenState extends State<ConversationsCenterScreen> {
  String _query = '';
  String _filter = 'all';
  bool _conversationsLoaded = false;
  late Future<AirmiusPage<AirmiusConversation>> _conversationsFuture;
  late Future<JsonMap> _invitationsFuture;

  String _t(String key) => AirmiusScope.of(context).t(key);

  void _openProfile() {
    Navigator.push(
      context,
      MaterialPageRoute(builder: (_) => const ProfileScreen()),
    );
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (_conversationsLoaded) return;
    _conversationsLoaded = true;
    _conversationsFuture = _loadConversations();
    _invitationsFuture = _loadInvitations();
  }

  Future<AirmiusPage<AirmiusConversation>> _loadConversations() {
    return AirmiusServicesScope.of(
      context,
    ).repositories.conversations.conversations();
  }

  Future<JsonMap> _loadInvitations() {
    final services = AirmiusServicesScope.of(context);
    return services
        .clientForSession(services.authState.session)
        .conversationInvitations();
  }

  void _reload() {
    if (!mounted) return;
    setState(() {
      _conversationsFuture = _loadConversations();
      _invitationsFuture = _loadInvitations();
    });
  }

  Future<void> _handleInvitation(int invitationId, bool accept) async {
    final services = AirmiusServicesScope.of(context);
    final client = services.clientForSession(services.authState.session);
    try {
      if (accept) {
        await client.acceptConversationInvitation(invitationId);
      } else {
        await client.declineConversationInvitation(invitationId);
      }
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            accept
                ? _t('chat.invitationAccepted')
                : _t('chat.invitationDeclined'),
          ),
        ),
      );
      _reload();
    } catch (_) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(_t('chat.invitationFailed'))));
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final scope = AirmiusScope.of(context);
    final authState = AirmiusServicesScope.of(context).authState;
    final userLabel = initialsFromName(
      [authState.user?.firstName, authState.user?.lastName]
          .whereType<String>()
          .map((part) => part.trim())
          .where((part) => part.isNotEmpty)
          .join(' '),
      fallback: initialsFromName(authState.user?.name, fallback: ''),
    );
    final body = _buildInbox(context);
    if (widget.embedded) return body;

    return Scaffold(
      appBar: AppBar(
        backgroundColor: airmiusSurfaceColor(context),
        surfaceTintColor: Colors.transparent,
        elevation: 0,
        leading: IconButton(
          tooltip: scope.t('chat.back'),
          icon: Icon(Icons.arrow_back, color: airmiusTextColor(context)),
          onPressed: () => Navigator.maybePop(context),
        ),
        titleSpacing: 0,
        title: Text(
          scope.t('chat.title'),
          style: TextStyle(
            color: airmiusTextColor(context),
            fontWeight: FontWeight.w900,
          ),
        ),
        actions: [
          IconButton(
            tooltip: scope.t('chat.search'),
            onPressed: () => Navigator.push(
              context,
              MaterialPageRoute(builder: (_) => GlobalSearchScreen()),
            ),
            icon: Icon(Icons.search, color: airmiusMutedColor(context)),
          ),
          IconButton(
            tooltip: scope.t('chat.refresh'),
            onPressed: _reload,
            icon: Icon(
              Icons.chat_bubble_outline,
              color: airmiusMutedColor(context),
            ),
          ),
          IconButton(
            tooltip: scope.t('chat.notifications'),
            onPressed: () => Navigator.push(
              context,
              MaterialPageRoute(builder: (_) => NotificationsCenterScreen()),
            ),
            icon: Icon(
              Icons.notifications_none,
              color: airmiusMutedColor(context),
            ),
          ),
          UserBubble(
            label: userLabel.isEmpty ? 'GK' : userLabel,
            imageUrl: authState.user?.avatarUrl,
            onTap: _openProfile,
          ),
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
    final currentUserId = AirmiusServicesScope.of(context).authState.user?.id;
    return RefreshIndicator(
      color: airmiusAccentColor(context),
      backgroundColor: airmiusSurfaceColor(context),
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
            return _ScrollableInbox(
              child: _ErrorConversations(onRetry: _reload),
            );
          }

          final allConversations =
              snapshot.data?.items ?? const <AirmiusConversation>[];
          final normalized = _query.trim().toLowerCase();
          final conversations = allConversations.where((item) {
            final matchesFilter = _filter == 'all' ||
                (_filter == 'unread' && item.unreadCount > 0) ||
                _typeKey(item.kind) == _filter;
            final title = item.titleForViewer(currentUserId).toLowerCase();
            final matchesSearch =
                normalized.isEmpty ||
                title.contains(normalized) ||
                item.lastMessage.toLowerCase().contains(normalized) ||
                item.kind.toLowerCase().contains(normalized);
            return matchesFilter && matchesSearch;
          }).toList();
          final counts = {
            'unread': allConversations
                .where((item) => item.unreadCount > 0)
                .length,
            'direct': allConversations
                .where((item) => _typeKey(item.kind) == 'direct')
                .length,
            'team': allConversations
                .where((item) => _typeKey(item.kind) == 'team')
                .length,
            'group': allConversations
                .where((item) => _typeKey(item.kind) == 'group')
                .length,
          };

          return _ScrollableInbox(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                FutureBuilder<JsonMap>(
                  future: _invitationsFuture,
                  builder: (context, invitationSnapshot) {
                    final raw = invitationSnapshot.data?['data'];
                    final invitations = raw is List
                        ? raw.whereType<JsonMap>().toList()
                        : const <JsonMap>[];
                    if (invitations.isEmpty) return const SizedBox.shrink();
                    return Padding(
                      padding: const EdgeInsets.only(bottom: 12),
                      child: _GroupInvitationsPanel(
                        invitations: invitations,
                        onAccept: (id) => _handleInvitation(id, true),
                        onDecline: (id) => _handleInvitation(id, false),
                      ),
                    );
                  },
                ),
                _ChatListPanel(
                  onRead: _reload,
                  conversations: conversations,
                  currentUserId: currentUserId,
                  allConversationsCount: allConversations.length,
                  counts: counts,
                  activeFilter: _filter,
                  onFilterChanged: (value) => setState(() => _filter = value),
                  onSearchChanged: (value) => setState(() => _query = value),
                  onNewConversation: () => Navigator.push(
                    context,
                    MaterialPageRoute(
                      builder: (_) => const NewConversationScreen(),
                    ),
                  ),
                  onBack: () => Navigator.maybePop(context),
                ),
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

class _GroupInvitationsPanel extends StatelessWidget {
  const _GroupInvitationsPanel({
    required this.invitations,
    required this.onAccept,
    required this.onDecline,
  });

  final List<JsonMap> invitations;
  final ValueChanged<int> onAccept;
  final ValueChanged<int> onDecline;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return AirmiusPanel(
      borderColor: airmiusAccentColor(context).withValues(alpha: .45),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Eyebrow(t('chat.groupInvitations')),
          const SizedBox(height: 8),
          for (final invitation in invitations) ...[
            Builder(
              builder: (context) {
                final conversation = invitation['conversation'] is JsonMap
                    ? invitation['conversation'] as JsonMap
                    : const <String, dynamic>{};
                final inviter = invitation['inviter'] is JsonMap
                    ? invitation['inviter'] as JsonMap
                    : const <String, dynamic>{};
                final id = _conversationInt(invitation['id']);
                return Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Text(
                      conversation['name']?.toString() ?? t('chat.newGroup'),
                      style: TextStyle(
                        color: airmiusTextColor(context),
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(height: 3),
                    Text(
                      '${t('chat.invitedBy')} '
                      '${inviter['name'] ?? t('chat.contact')}'
                      ' · ${conversation['members_count'] ?? 0} '
                      '${t('chat.members')}',
                      style: TextStyle(color: airmiusMutedColor(context)),
                    ),
                    const SizedBox(height: 9),
                    Wrap(
                      spacing: 8,
                      runSpacing: 8,
                      children: [
                        AirmiusButton(
                          label: t('chat.accept'),
                          icon: Icons.check_outlined,
                          onPressed: () => onAccept(id),
                        ),
                        AirmiusButton(
                          label: t('chat.decline'),
                          icon: Icons.close_outlined,
                          secondary: true,
                          onPressed: () => onDecline(id),
                        ),
                      ],
                    ),
                  ],
                );
              },
            ),
            if (invitation != invitations.last) const Divider(height: 24),
          ],
        ],
      ),
    );
  }
}

class _ChatListPanel extends StatelessWidget {
  const _ChatListPanel({
    required this.onRead,
    required this.conversations,
    required this.allConversationsCount,
    required this.counts,
    required this.activeFilter,
    required this.onFilterChanged,
    required this.onSearchChanged,
    required this.onNewConversation,
    required this.onBack,
    required this.currentUserId,
  });

  final List<AirmiusConversation> conversations;
  final VoidCallback onRead;
  final int allConversationsCount;
  final Map<String, int> counts;
  final String activeFilter;
  final ValueChanged<String> onFilterChanged;
  final ValueChanged<String> onSearchChanged;
  final VoidCallback onNewConversation;
  final VoidCallback onBack;
  final int? currentUserId;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return Container(
      decoration: BoxDecoration(
        color: airmiusSurfaceColor(context),
        borderRadius: BorderRadius.circular(8),
        border: Border.all(color: airmiusBorderColor(context)),
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
                    SizedBox(
                      width: 38,
                      height: 38,
                      child: IconButton(
                        tooltip: t('chat.back'),
                        onPressed: onBack,
                        padding: EdgeInsets.zero,
                        icon: Icon(
                          Icons.arrow_back,
                          color: airmiusTextColor(context),
                          size: 22,
                        ),
                      ),
                    ),
                    const SizedBox(width: 8),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            t('chat.title'),
                            style: TextStyle(
                              color: airmiusTextColor(context),
                              fontSize: 20,
                              fontWeight: FontWeight.w900,
                            ),
                          ),
                          SizedBox(height: 5),
                          Text(
                            t('chat.chooseFirst'),
                            style: TextStyle(
                              color: airmiusMutedColor(context),
                              fontSize: 13,
                              height: 1.25,
                            ),
                          ),
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
                          backgroundColor: airmiusTextColor(context),
                          foregroundColor: airmiusSurfaceColor(context),
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(8),
                          ),
                        ),
                        child: Icon(Icons.add, size: 22),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 18),
                SearchBox(
                  hint: t('chat.searchPeople'),
                  onChanged: onSearchChanged,
                ),
                const SizedBox(height: 10),
                SingleChildScrollView(
                  scrollDirection: Axis.horizontal,
                  child: Row(
                    children: [
                      _FilterButton(
                        icon: Icons.inbox_outlined,
                        label: t('chat.all'),
                        count: allConversationsCount,
                        active: activeFilter == 'all',
                        onTap: () => onFilterChanged('all'),
                      ),
                      const SizedBox(width: 8),
                      _FilterButton(
                        icon: Icons.mark_email_unread_outlined,
                        label: t('chat.unread'),
                        count: counts['unread'] ?? 0,
                        active: activeFilter == 'unread',
                        onTap: () => onFilterChanged('unread'),
                      ),
                      const SizedBox(width: 8),
                      _FilterButton(
                        icon: Icons.person_outline,
                        label: t('chat.people'),
                        count: counts['direct'] ?? allConversationsCount,
                        active: activeFilter == 'direct',
                        onTap: () => onFilterChanged('direct'),
                      ),
                      const SizedBox(width: 8),
                      _FilterButton(
                        icon: Icons.forum_outlined,
                        label: t('chat.groups'),
                        count: counts['group'] ?? 0,
                        active: activeFilter == 'group',
                        onTap: () => onFilterChanged('group'),
                      ),
                      const SizedBox(width: 8),
                      _FilterButton(
                        icon: Icons.groups_outlined,
                        label: t('chat.teams'),
                        count: counts['team'] ?? 0,
                        active: activeFilter == 'team',
                        onTap: () => onFilterChanged('team'),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
          Container(height: 1, color: airmiusBorderColor(context)),
          if (conversations.isEmpty)
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 36),
              child: Text(
                t('chat.noMatching'),
                textAlign: TextAlign.center,
                style: TextStyle(
                  color: airmiusMutedColor(context),
                  fontWeight: FontWeight.w700,
                ),
              ),
            )
          else
            Padding(
              padding: const EdgeInsets.fromLTRB(8, 8, 8, 10),
              child: Column(
                children: [
                  for (final conversation in conversations)
                    _ConversationCard(
                      onRead: onRead,
                      conversation: conversation,
                      currentUserId: currentUserId,
                    ),
                ],
              ),
            ),
        ],
      ),
    );
  }
}

int _conversationInt(Object? value) =>
    value is num ? value.toInt() : int.tryParse('$value') ?? 0;

class _FilterButton extends StatelessWidget {
  const _FilterButton({
    required this.icon,
    required this.label,
    required this.count,
    required this.active,
    required this.onTap,
  });

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
          color: active
              ? airmiusTextColor(context)
              : airmiusSurfaceColor(context),
          borderRadius: BorderRadius.circular(8),
          border: Border.all(
            color: active
                ? airmiusTextColor(context)
                : airmiusBorderColor(context),
          ),
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(
              icon,
              size: 15,
              color: active
                  ? airmiusSurfaceColor(context)
                  : airmiusMutedColor(context),
            ),
            const SizedBox(width: 7),
            Text(
              label,
              style: TextStyle(
                color: active
                    ? airmiusSurfaceColor(context)
                    : airmiusMutedColor(context),
                fontSize: 13,
                fontWeight: FontWeight.w800,
              ),
            ),
            const SizedBox(width: 6),
            Text(
              '$count',
              style: TextStyle(
                color: active
                    ? airmiusSurfaceColor(context).withValues(alpha: 0.72)
                    : airmiusMutedColor(context),
                fontSize: 12,
                fontWeight: FontWeight.w800,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _ConversationCard extends StatelessWidget {
  const _ConversationCard({required this.conversation, this.currentUserId, required this.onRead});

  final VoidCallback onRead;

  final AirmiusConversation conversation;
  final int? currentUserId;

  @override
  Widget build(BuildContext context) {
    final scope = AirmiusScope.of(context);
    final title = conversation.titleForViewer(currentUserId);
    final avatarUrl = conversation.avatarUrlForViewer(currentUserId);
    return InkWell(
      onTap: () async {
        final removed = await Navigator.push<bool>(
          context,
          MaterialPageRoute(
            builder: (_) => ChatDetailScreen(
              onRead: onRead,
              conversationId: conversation.id,
              title: title,
              kind: _kindLabel(scope, conversation.kind),
            ),
          ),
        );

        if (removed == true) onRead();
      },
      borderRadius: BorderRadius.circular(8),
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 10),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            SizedBox(
              width: 42,
              height: 42,
              child: ClipRRect(
                borderRadius: BorderRadius.circular(7),
                child: avatarUrl == null
                    ? ColoredBox(
                        color: airmiusTextColor(context),
                        child: Center(
                          child: Text(
                            initialsFromName(title, fallback: '??'),
                            style: TextStyle(
                              color: airmiusSurfaceColor(context),
                              fontSize: 13,
                              fontWeight: FontWeight.w900,
                            ),
                          ),
                        ),
                      )
                    : Image.network(
                        resolveAirmiusImageUrl(avatarUrl) ?? avatarUrl,
                        fit: BoxFit.cover,
                        errorBuilder: (_, _, _) => ColoredBox(
                          color: airmiusTextColor(context),
                          child: Center(
                            child: Text(
                              initialsFromName(title, fallback: '??'),
                              style: TextStyle(
                                color: airmiusSurfaceColor(context),
                                fontSize: 13,
                                fontWeight: FontWeight.w900,
                              ),
                            ),
                          ),
                        ),
                      ),
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Expanded(
                        child: Text(
                          title,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: TextStyle(
                            color: airmiusTextColor(context),
                            fontSize: 14,
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                      ),
                      if (conversation.unreadCount > 0) ...[
                        Container(
                          padding: const EdgeInsets.symmetric(
                            horizontal: 7,
                            vertical: 3,
                          ),
                          decoration: BoxDecoration(
                            color: Theme.of(context).colorScheme.error,
                            borderRadius: BorderRadius.circular(999),
                          ),
                          child: Text(
                            conversation.unreadCount > 99
                                ? '99+'
                                : '${conversation.unreadCount}',
                            style: TextStyle(
                              color: airmiusOnColor(
                                Theme.of(context).colorScheme.error,
                              ),
                              fontSize: 11,
                              fontWeight: FontWeight.w900,
                            ),
                          ),
                        ),
                        const SizedBox(width: 7),
                      ],
                      Text(
                        _shortTime(conversation.timeLabel),
                        style: TextStyle(
                          color: airmiusMutedColor(context),
                          fontSize: 12,
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 4),
                  Text(
                    conversation.lastMessage.isEmpty
                        ? scope.t('messages.noMessages')
                        : conversation.lastMessage,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(
                      color: conversation.unreadCount > 0
                          ? airmiusTextColor(context)
                          : airmiusMutedColor(context),
                      fontSize: 12,
                      height: 1.25,
                      fontWeight: conversation.unreadCount > 0
                          ? FontWeight.w900
                          : FontWeight.w700,
                    ),
                  ),
                  const SizedBox(height: 3),
                  Text(
                    [
                      _kindLabel(scope, conversation.kind),
                      if (conversation.membersCount != null)
                        '${conversation.membersCount} ${conversation.membersCount == 1 ? scope.t('chat.member') : scope.t('chat.members')}',
                    ].join(' · '),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(
                      color: airmiusAccentColor(context),
                      fontSize: 11,
                      fontWeight: FontWeight.w800,
                    ),
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
            SizedBox(
              width: 18,
              height: 18,
              child: CircularProgressIndicator(
                strokeWidth: 2,
                color: airmiusAccentColor(context),
              ),
            ),
            const SizedBox(width: 12),
            Text(
              scope.t('status.loading'),
              style: TextStyle(
                color: airmiusMutedColor(context),
                fontWeight: FontWeight.w800,
              ),
            ),
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
          Icon(
            Icons.error_outline,
            color: Theme.of(context).colorScheme.error,
            size: 34,
          ),
          const SizedBox(height: 10),
          Text(
            scope.t('messages.error'),
            textAlign: TextAlign.center,
            style: TextStyle(
              color: airmiusTextColor(context),
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 12),
          AirmiusButton(
            label: scope.t('messages.retry'),
            icon: Icons.refresh_outlined,
            onPressed: onRetry,
            secondary: true,
          ),
        ],
      ),
    );
  }
}

String _kindLabel(AirmiusScope scope, String rawKind) {
  final kind = rawKind.toLowerCase();
  if (kind.contains('club') ||
      kind.contains('verein') ||
      kind.contains('admin')) {
    return scope.t('messages.club');
  }
  if (kind.contains('team') || kind.contains('training')) {
    return scope.t('messages.team');
  }
  if (kind.contains('event')) return scope.t('messages.event');
  if (kind.contains('support')) return scope.t('messages.support');
  return scope.t('messages.chat');
}

String _shortTime(String value) {
  final date = DateTime.tryParse(value);
  if (date == null) return value;
  final hour = date.hour.toString().padLeft(2, '0');
  final minute = date.minute.toString().padLeft(2, '0');
  return '$hour:$minute';
}
