import 'dart:async';

import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';

import '../core/airmius_chat_attachment_service.dart';
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
  List<AirmiusMessage>? _messages;
  List<String> _typingUsers = const [];
  Timer? _refreshTimer;
  Timer? _typingStopTimer;
  bool _messagesLoaded = false;
  bool _sending = false;
  bool _markingRead = false;
  bool _refreshingRealtime = false;
  bool _typing = false;
  List<PlatformFile> _attachments = const [];

  String _t(String key) => AirmiusScope.of(context).t(key);

  @override
  void initState() {
    super.initState();
    _messageController.addListener(_onComposerChanged);
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (_messagesLoaded) return;
    _messagesLoaded = true;
    _messagesFuture = _loadMessages();
    _startRealtimePolling();
  }

  @override
  void dispose() {
    _refreshTimer?.cancel();
    _typingStopTimer?.cancel();
    _messageController.removeListener(_onComposerChanged);
    _messageController.dispose();
    super.dispose();
  }

  Future<AirmiusPage<AirmiusMessage>> _loadMessages() async {
    final snapshot = await _fetchMessages();
    _messages = snapshot.page.items;
    _typingUsers = snapshot.typingUsers;
    unawaited(_markRead());
    return snapshot.page;
  }

  Future<_MessagesSnapshot> _fetchMessages() async {
    final services = AirmiusServicesScope.of(context);
    final json = await services
        .clientForSession(services.authState.session)
        .conversationMessages(widget.conversationId);
    final page = AirmiusPage<AirmiusMessage>.fromJson(
      json,
      AirmiusMessage.fromJson,
    );
    return _MessagesSnapshot(page: page, typingUsers: _typingUsersFrom(json));
  }

  void _reload() {
    setState(() {
      _messagesFuture = _loadMessages();
    });
  }

  void _startRealtimePolling() {
    _refreshTimer ??= Timer.periodic(const Duration(seconds: 4), (_) {
      if (!mounted) return;
      unawaited(_refreshRealtime());
    });
  }

  Future<void> _refreshRealtime() async {
    if (_refreshingRealtime) return;
    _refreshingRealtime = true;
    try {
      final snapshot = await _fetchMessages();
      if (!mounted) return;
      final messagesChanged = !_sameMessages(_messages, snapshot.page.items);
      final typingChanged = !_sameStrings(_typingUsers, snapshot.typingUsers);
      if (!messagesChanged && !typingChanged) return;
      setState(() {
        _messages = snapshot.page.items;
        _typingUsers = snapshot.typingUsers;
      });
      if (messagesChanged) unawaited(_markRead());
    } catch (_) {
      // Keep the current chat view during transient realtime refresh failures.
    } finally {
      _refreshingRealtime = false;
    }
  }

  Future<void> _markRead() async {
    if (!mounted) return;
    if (_markingRead) return;
    _markingRead = true;
    try {
      await AirmiusServicesScope.of(
        context,
      ).repositories.conversations.markRead(widget.conversationId);
    } catch (_) {
      // Read receipts are retried by the next polling cycle.
    } finally {
      _markingRead = false;
    }
  }

  List<String> _typingUsersFrom(JsonMap json) {
    final chat = json['chat'];
    final typingUsers = chat is JsonMap ? chat['typing_users'] : null;
    return typingUsers is List
        ? typingUsers
              .whereType<JsonMap>()
              .map((user) => user['name']?.toString().trim() ?? '')
              .where((name) => name.isNotEmpty)
              .toList()
        : const <String>[];
  }

  bool _sameStrings(List<String> first, List<String> second) {
    if (first.length != second.length) return false;
    for (var index = 0; index < first.length; index++) {
      if (first[index] != second[index]) return false;
    }
    return true;
  }

  bool _sameMessages(List<AirmiusMessage>? first, List<AirmiusMessage> second) {
    if (first == null || first.length != second.length) return false;
    for (var index = 0; index < first.length; index++) {
      final left = first[index];
      final right = second[index];
      if (left.id != right.id ||
          left.conversationId != right.conversationId ||
          left.message != right.message ||
          left.senderName != right.senderName ||
          left.createdAt != right.createdAt ||
          left.mine != right.mine ||
          left.status != right.status ||
          left.read != right.read ||
          left.reactions.length != right.reactions.length) {
        return false;
      }
      for (
        var reactionIndex = 0;
        reactionIndex < left.reactions.length;
        reactionIndex++
      ) {
        final leftReaction = left.reactions[reactionIndex];
        final rightReaction = right.reactions[reactionIndex];
        if (leftReaction.id != rightReaction.id ||
            leftReaction.userId != rightReaction.userId ||
            leftReaction.reaction != rightReaction.reaction) {
          return false;
        }
      }
    }
    return true;
  }

  void _onComposerChanged() {
    final hasText = _messageController.text.trim().isNotEmpty;
    _typingStopTimer?.cancel();

    if (hasText) {
      if (!_typing) unawaited(_sendTyping(true));
      _typingStopTimer = Timer(
        const Duration(seconds: 2),
        () => unawaited(_sendTyping(false)),
      );
      return;
    }

    if (_typing) unawaited(_sendTyping(false));
  }

  Future<void> _sendTyping(bool typing) async {
    if (_typing == typing) return;
    _typing = typing;
    try {
      await AirmiusServicesScope.of(
        context,
      ).repositories.conversations.sendTyping(widget.conversationId, typing);
    } catch (_) {
      // Typing is best-effort presence, not a blocking chat action.
    }
  }

  Future<void> _send() async {
    final message = _messageController.text.trim();
    if ((message.isEmpty && _attachments.isEmpty) || _sending) return;

    setState(() => _sending = true);
    try {
      final services = AirmiusServicesScope.of(context);
      if (_attachments.isEmpty) {
        await services.repositories.conversations.sendMessage(
          widget.conversationId,
          message,
        );
      } else {
        await AirmiusChatAttachmentService(
          baseUrl: services.environment.apiBaseUrl,
          token: services.authState.session?.token,
          locale:
              services.authState.session?.locale ?? services.environment.locale,
        ).send(
          conversationId: widget.conversationId,
          message: message.isEmpty ? null : message,
          attachments: _attachments,
        );
      }
      if (!mounted) return;
      _messageController.clear();
      setState(() {
        _attachments = const [];
        _sending = false;
      });
      unawaited(_sendTyping(false));
      unawaited(_refreshRealtime());
    } catch (_) {
      if (!mounted) return;
      setState(() => _sending = false);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(AirmiusScope.of(context).t('messages.error'))),
      );
    }
  }

  Future<void> _pickAttachments() async {
    if (_sending) return;
    final result = await FilePicker.platform.pickFiles(
      allowMultiple: true,
      withData: true,
      type: FileType.any,
    );
    if (result == null || !mounted) return;
    final accepted = result.files
        .where((file) => file.size <= 10 * 1024 * 1024)
        .take(5)
        .toList();
    if (accepted.length != result.files.length) {
      _showActionResult(_t('chat.attachmentTooLarge'));
    }
    if (accepted.isEmpty) return;
    setState(
      () => _attachments = [..._attachments, ...accepted].take(5).toList(),
    );
  }

  void _removeAttachment(PlatformFile file) {
    setState(
      () => _attachments = _attachments.where((item) => item != file).toList(),
    );
  }

  void _showActionResult(String message) {
    ScaffoldMessenger.of(
      context,
    ).showSnackBar(SnackBar(content: Text(message)));
  }

  Future<void> _reactToMessage(AirmiusMessage message, String reaction) async {
    final authState = AirmiusServicesScope.of(context).authState;
    final userId = authState.user?.id;
    if (userId == null) return;

    final previousMessages = _messages == null
        ? null
        : List<AirmiusMessage>.from(_messages!);
    _applyLocalReaction(message.id, userId, reaction);

    try {
      final response = await AirmiusServicesScope.of(context)
          .clientForSession(authState.session)
          .reactToMessage(message.id, reaction);
      if (!mounted) return;
      final serverReactions = response['reactions'];
      if (serverReactions is List) {
        _replaceMessage(
          message.id,
          (current) => current.copyWith(
            reactions: serverReactions
                .whereType<JsonMap>()
                .map(AirmiusMessageReaction.fromJson)
                .toList(),
          ),
        );
      }
    } catch (_) {
      if (!mounted) return;
      setState(() => _messages = previousMessages);
      _showActionResult('Reaktion konnte nicht gespeichert werden.');
    }
  }

  void _applyLocalReaction(int messageId, int userId, String reaction) {
    _replaceMessage(messageId, (current) {
      final reactions = List<AirmiusMessageReaction>.from(current.reactions);
      final existingIndex = reactions.indexWhere(
        (item) => item.userId == userId,
      );
      if (existingIndex >= 0 && reactions[existingIndex].reaction == reaction) {
        reactions.removeAt(existingIndex);
      } else if (existingIndex >= 0) {
        reactions[existingIndex] = AirmiusMessageReaction(
          id: reactions[existingIndex].id,
          userId: userId,
          reaction: reaction,
        );
      } else {
        reactions.add(
          AirmiusMessageReaction(
            id: -userId,
            userId: userId,
            reaction: reaction,
          ),
        );
      }
      return current.copyWith(reactions: reactions);
    });
  }

  void _replaceMessage(
    int messageId,
    AirmiusMessage Function(AirmiusMessage current) update,
  ) {
    final messages = _messages;
    if (messages == null) return;
    final index = messages.indexWhere((item) => item.id == messageId);
    if (index < 0) return;
    final nextMessages = List<AirmiusMessage>.from(messages);
    nextMessages[index] = update(nextMessages[index]);
    setState(() => _messages = nextMessages);
  }

  Future<void> _hideMessage(AirmiusMessage message) async {
    try {
      await AirmiusServicesScope.of(context)
          .clientForSession(AirmiusServicesScope.of(context).authState.session)
          .hideMessage(message.id);
      if (!mounted) return;
      _showActionResult(_t('chat.messageHidden'));
      _reload();
    } catch (_) {
      if (!mounted) return;
      _showActionResult(_t('chat.messageHideFailed'));
    }
  }

  Future<void> _deleteMessage(AirmiusMessage message) async {
    try {
      await AirmiusServicesScope.of(context)
          .clientForSession(AirmiusServicesScope.of(context).authState.session)
          .deleteMessage(message.id);
      if (!mounted) return;
      _showActionResult(_t('chat.messageDeleted'));
      _reload();
    } catch (_) {
      if (!mounted) return;
      _showActionResult(_t('chat.messageDeleteFailed'));
    }
  }

  Future<void> _reportMessage(AirmiusMessage message) async {
    try {
      await AirmiusServicesScope.of(context)
          .clientForSession(AirmiusServicesScope.of(context).authState.session)
          .reportContent(type: 'message', id: message.id, reason: 'other');
      if (mounted) {
        _showActionResult(_t('chat.reportSent'));
      }
    } catch (_) {
      if (mounted) _showActionResult(_t('chat.reportFailed'));
    }
  }

  Future<void> _openConversationSettings() async {
    final repo = AirmiusServicesScope.of(context).repositories.conversations;
    try {
      final conversation = await repo.conversation(widget.conversationId);
      if (!mounted) return;
      final currentUserId = AirmiusServicesScope.of(context).authState.user?.id;
      final isGroup = conversation.kind.toLowerCase() == 'group';
      final isOwner = conversation.ownerId == currentUserId;
      final action = await showModalBottomSheet<String>(
        context: context,
        showDragHandle: true,
        builder: (context) => SafeArea(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              if (isGroup && isOwner)
                ListTile(
                  leading: Icon(Icons.edit_outlined),
                  title: Text(_t('chat.editGroupProfile')),
                  onTap: () => Navigator.pop(context, 'edit'),
                ),
              if (isGroup && isOwner)
                ListTile(
                  leading: Icon(Icons.person_add_alt_outlined),
                  title: Text(_t('chat.inviteMembers')),
                  onTap: () => Navigator.pop(context, 'invite'),
                ),
              if (isGroup && isOwner)
                ListTile(
                  leading: Icon(Icons.manage_accounts_outlined),
                  title: Text(_t('chat.manageMembers')),
                  onTap: () => Navigator.pop(context, 'members'),
                ),
              ListTile(
                leading: Icon(Icons.notifications_off_outlined),
                title: Text(_t('chat.notificationSettings')),
                onTap: () => Navigator.pop(context, 'mute'),
              ),
              if (isGroup)
                ListTile(
                  leading: Icon(Icons.logout_outlined),
                  title: Text(_t('chat.leaveGroup')),
                  textColor: AirmiusColors.red,
                  iconColor: AirmiusColors.red,
                  onTap: () => Navigator.pop(context, 'leave'),
                ),
            ],
          ),
        ),
      );
      if (!mounted || action == null) return;
      if (action == 'edit') {
        await _editConversation(conversation);
      } else if (action == 'invite') {
        await _inviteMembers(conversation);
      } else if (action == 'members') {
        await _manageMembers(conversation);
      } else if (action == 'mute') {
        await _muteConversation(conversation);
      } else if (action == 'leave') {
        final confirmed = await showDialog<bool>(
          context: context,
          builder: (context) => AlertDialog(
            title: Text(_t('chat.leaveGroupTitle')),
            content: Text(_t('chat.leaveGroupBody')),
            actions: [
              TextButton(
                onPressed: () => Navigator.pop(context, false),
                child: Text(_t('common.cancel')),
              ),
              FilledButton(
                onPressed: () => Navigator.pop(context, true),
                child: Text(_t('chat.leave')),
              ),
            ],
          ),
        );
        if (confirmed == true && mounted) {
          await repo.leaveConversation(widget.conversationId);
          if (mounted) Navigator.pop(context);
        }
      }
    } catch (error) {
      if (mounted) {
        _showActionResult(_t('chat.settingsLoadFailed'));
      }
    }
  }

  Future<void> _editConversation(AirmiusConversation conversation) async {
    final name = TextEditingController(text: conversation.title);
    final description = TextEditingController(text: conversation.description);
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(_t('chat.groupProfile')),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            TextField(
              controller: name,
              decoration: InputDecoration(labelText: _t('chat.groupName')),
            ),
            TextField(
              controller: description,
              maxLines: 3,
              decoration: InputDecoration(labelText: _t('chat.description')),
            ),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: Text(_t('common.cancel')),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, true),
            child: Text(_t('common.save')),
          ),
        ],
      ),
    );
    if (confirmed == true && mounted) {
      await AirmiusServicesScope.of(
        context,
      ).repositories.conversations.updateConversation(widget.conversationId, {
        'name': name.text.trim(),
        'description': description.text.trim(),
      });
      if (mounted) _showActionResult(_t('chat.groupProfileSaved'));
    }
    name.dispose();
    description.dispose();
  }

  Future<void> _inviteMembers(AirmiusConversation conversation) async {
    final services = AirmiusServicesScope.of(context);
    final client = services.clientForSession(services.authState.session);
    try {
      final response = await client.friends();
      if (!mounted) return;
      final data = response['data'];
      final friends = data is JsonMap && data['friends'] is List
          ? (data['friends'] as List).whereType<JsonMap>().toList()
          : const <JsonMap>[];
      final memberIds = conversation.members
          .map((member) => _chatInt(member['id']))
          .toSet();
      final choices = friends
          .where((friend) => !memberIds.contains(_chatInt(friend['id'])))
          .toList();
      if (choices.isEmpty) {
        _showActionResult(_t('chat.allFriendsMembers'));
        return;
      }
      final selected = <int>{};
      final userIds = await showDialog<List<int>>(
        context: context,
        builder: (dialogContext) => StatefulBuilder(
          builder: (context, setDialogState) => AlertDialog(
            title: Text(_t('chat.inviteMembers')),
            content: SizedBox(
              width: double.maxFinite,
              child: ListView(
                shrinkWrap: true,
                children: [
                  for (final friend in choices)
                    CheckboxListTile(
                      value: selected.contains(_chatInt(friend['id'])),
                      title: Text(
                        friend['name']?.toString() ?? _t('chat.contact'),
                      ),
                      onChanged: (checked) => setDialogState(() {
                        final id = _chatInt(friend['id']);
                        if (checked == true) {
                          selected.add(id);
                        } else {
                          selected.remove(id);
                        }
                      }),
                    ),
                ],
              ),
            ),
            actions: [
              TextButton(
                onPressed: () => Navigator.pop(dialogContext),
                child: Text(_t('common.cancel')),
              ),
              FilledButton(
                onPressed: selected.isEmpty
                    ? null
                    : () => Navigator.pop(dialogContext, selected.toList()),
                child: Text(_t('chat.invite')),
              ),
            ],
          ),
        ),
      );
      if (userIds == null || !mounted) return;
      await services.repositories.conversations.inviteConversationMembers(
        widget.conversationId,
        userIds,
      );
      if (mounted) _showActionResult(_t('chat.invitationsSent'));
    } catch (error) {
      if (mounted) {
        _showActionResult(_t('chat.invitationsFailed'));
      }
    }
  }

  Future<void> _manageMembers(AirmiusConversation conversation) async {
    final currentUserId = AirmiusServicesScope.of(context).authState.user?.id;
    final manageable = conversation.members
        .where((member) => _chatInt(member['id']) != currentUserId)
        .toList();
    if (manageable.isEmpty) {
      _showActionResult(_t('chat.noOtherMembers'));
      return;
    }
    final action = await showModalBottomSheet<(String, int)>(
      context: context,
      showDragHandle: true,
      builder: (sheetContext) => SafeArea(
        child: ListView(
          shrinkWrap: true,
          children: [
            ListTile(
              title: Text(
                _t('chat.manageMembers'),
                style: TextStyle(fontWeight: FontWeight.w900),
              ),
            ),
            for (final member in manageable)
              ListTile(
                leading: const CircleAvatar(child: Icon(Icons.person_outline)),
                title: Text(member['name']?.toString() ?? _t('chat.member')),
                subtitle: Text(member['email']?.toString() ?? ''),
                trailing: PopupMenuButton<String>(
                  onSelected: (selected) => Navigator.pop(sheetContext, (
                    selected,
                    _chatInt(member['id']),
                  )),
                  itemBuilder: (_) => [
                    PopupMenuItem(
                      value: 'transfer',
                      child: Text(_t('chat.transferOwner')),
                    ),
                    PopupMenuItem(
                      value: 'remove',
                      child: Text(_t('chat.removeFromGroup')),
                    ),
                  ],
                ),
              ),
          ],
        ),
      ),
    );
    if (action == null || !mounted) return;
    final member = manageable.firstWhere(
      (entry) => _chatInt(entry['id']) == action.$2,
    );
    final name = member['name']?.toString() ?? _t('chat.member');
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: Text(
          action.$1 == 'transfer'
              ? _t('chat.transferOwnerTitle')
              : _t('chat.removeMemberTitle'),
        ),
        content: Text(
          action.$1 == 'transfer'
              ? '$name ${_t('chat.ownerAfter')}'
              : '$name ${_t('chat.accessLostAfter')}',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext, false),
            child: Text(_t('common.cancel')),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(dialogContext, true),
            child: Text(_t('common.confirm')),
          ),
        ],
      ),
    );
    if (confirmed != true || !mounted) return;
    final repo = AirmiusServicesScope.of(context).repositories.conversations;
    try {
      if (action.$1 == 'transfer') {
        await repo.transferConversationOwner(widget.conversationId, action.$2);
        if (mounted) _showActionResult(_t('chat.ownerTransferred'));
      } else {
        await repo.removeConversationMember(widget.conversationId, action.$2);
        if (mounted) {
          _showActionResult('$name ${_t('chat.removedFromGroupAfter')}');
        }
      }
    } catch (error) {
      if (mounted) {
        _showActionResult(_t('chat.memberActionFailed'));
      }
    }
  }

  Future<void> _muteConversation(AirmiusConversation conversation) async {
    final minutes = await showModalBottomSheet<int>(
      context: context,
      showDragHandle: true,
      builder: (context) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            for (final option in const [
              (0, 'chat.mute.enable'),
              (60, 'chat.mute.hour'),
              (480, 'chat.mute.eightHours'),
              (1440, 'chat.mute.day'),
              (10080, 'chat.mute.week'),
            ])
              ListTile(
                title: Text(_t(option.$2)),
                onTap: () => Navigator.pop(context, option.$1),
              ),
          ],
        ),
      ),
    );
    if (minutes == null || !mounted) return;
    await AirmiusServicesScope.of(context).repositories.conversations
        .muteConversation(widget.conversationId, minutes);
    if (mounted) {
      _showActionResult(
        minutes == 0 ? _t('chat.notificationsActive') : _t('chat.muted'),
      );
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

    return Scaffold(
      appBar: AppBar(
        backgroundColor:
            Theme.of(context).appBarTheme.backgroundColor ??
            airmiusSurfaceColor(context),
        surfaceTintColor: Colors.transparent,
        elevation: 0,
        leading: IconButton(
          tooltip: scope.t('chat.menu'),
          icon: Icon(Icons.menu, color: airmiusTextColor(context)),
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
            icon: Icon(Icons.search, color: airmiusTextColor(context)),
          ),
          IconButton(
            tooltip: scope.t('chat.chats'),
            onPressed: () => Navigator.pop(context),
            icon: Icon(
              Icons.chat_bubble_outline,
              color: airmiusTextColor(context),
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
              color: airmiusTextColor(context),
            ),
          ),
          UserBubble(
            label: userLabel.isEmpty ? 'ZK' : userLabel,
            imageUrl: authState.user?.avatarUrl,
          ),
          Icon(Icons.keyboard_arrow_down, color: airmiusMutedColor(context)),
          const SizedBox(width: 8),
        ],
      ),
      body: Center(
        child: ConstrainedBox(
          constraints: const BoxConstraints(maxWidth: 420),
          child: Container(
            margin: const EdgeInsets.all(12),
            decoration: BoxDecoration(
              color: airmiusSurfaceColor(context),
              borderRadius: BorderRadius.circular(8),
              border: Border.all(color: airmiusBorderColor(context)),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                _ConversationHeader(
                  title: widget.title,
                  subtitle: _conversationSubtitle(),
                  onSettings: _openConversationSettings,
                ),
                Container(height: 1, color: airmiusBorderColor(context)),
                const Padding(
                  padding: EdgeInsets.fromLTRB(12, 12, 12, 0),
                  child: _ChatSearchField(),
                ),
                Expanded(
                  child: FutureBuilder<AirmiusPage<AirmiusMessage>>(
                    future: _messagesFuture,
                    builder: (context, snapshot) {
                      if (snapshot.connectionState == ConnectionState.waiting) {
                        return const Padding(
                          padding: EdgeInsets.all(12),
                          child: _LoadingMessages(),
                        );
                      }
                      if (snapshot.hasError) {
                        return Padding(
                          padding: const EdgeInsets.all(12),
                          child: _ErrorMessages(onRetry: _reload),
                        );
                      }

                      final messages =
                          (_messages ??
                                  snapshot.data?.items ??
                                  const <AirmiusMessage>[])
                              .reversed
                              .toList();
                      if (messages.isEmpty) {
                        return Center(
                          child: Padding(
                            padding: const EdgeInsets.all(24),
                            child: Text(
                              scope.t('messages.noMessages'),
                              textAlign: TextAlign.center,
                              style: TextStyle(
                                color: airmiusMutedColor(context),
                                fontWeight: FontWeight.w800,
                              ),
                            ),
                          ),
                        );
                      }

                      return ListView.separated(
                        padding: const EdgeInsets.fromLTRB(12, 12, 12, 14),
                        itemCount: messages.length,
                        separatorBuilder: (_, _) => const SizedBox(height: 10),
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
                Container(height: 1, color: airmiusBorderColor(context)),
                _MessageComposer(
                  controller: _messageController,
                  sending: _sending,
                  onSend: _send,
                  attachments: _attachments,
                  onAttach: _pickAttachments,
                  onRemoveAttachment: _removeAttachment,
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  String _conversationSubtitle() {
    if (_typingUsers.isEmpty) {
      return '${widget.kind} · 2 ${_t('chat.members')}';
    }
    if (_typingUsers.length == 1) {
      return '${_typingUsers.first} ${_t('chat.typingAfter')}';
    }
    return '${_typingUsers.length} ${_t('chat.peopleTypingAfter')}';
  }
}

class _MessagesSnapshot {
  const _MessagesSnapshot({required this.page, required this.typingUsers});

  final AirmiusPage<AirmiusMessage> page;
  final List<String> typingUsers;
}

class _ConversationHeader extends StatelessWidget {
  const _ConversationHeader({
    required this.title,
    required this.subtitle,
    required this.onSettings,
  });

  final String title;
  final String subtitle;
  final VoidCallback onSettings;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return Padding(
      padding: const EdgeInsets.fromLTRB(12, 14, 12, 14),
      child: Row(
        children: [
          IconButton(
            tooltip: t('chat.back'),
            onPressed: () => Navigator.pop(context),
            icon: Icon(Icons.arrow_back, color: airmiusTextColor(context)),
          ),
          const SizedBox(width: 4),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(
                    color: airmiusTextColor(context),
                    fontSize: 20,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 3),
                Text(
                  subtitle,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(
                    color: airmiusMutedColor(context),
                    fontSize: 13,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ],
            ),
          ),
          IconButton.filledTonal(
            tooltip: t('chat.settings'),
            onPressed: onSettings,
            icon: Icon(Icons.more_horiz),
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
    final t = AirmiusScope.of(context).t;
    return SizedBox(
      height: 62,
      child: TextField(
        style: TextStyle(
          color: airmiusTextColor(context),
          fontWeight: FontWeight.w700,
        ),
        decoration: InputDecoration(
          hintText: t('chat.searchMessages'),
          prefixIcon: Icon(Icons.search, color: airmiusMutedColor(context)),
          filled: true,
          fillColor: airmiusInputColor(context),
          contentPadding: const EdgeInsets.symmetric(
            horizontal: 14,
            vertical: 18,
          ),
          enabledBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(8),
            borderSide: BorderSide(color: airmiusBorderColor(context)),
          ),
          focusedBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(8),
            borderSide: BorderSide(color: airmiusAccentColor(context)),
          ),
        ),
      ),
    );
  }
}

class _MessageComposer extends StatelessWidget {
  const _MessageComposer({
    required this.controller,
    required this.sending,
    required this.onSend,
    required this.attachments,
    required this.onAttach,
    required this.onRemoveAttachment,
  });

  final TextEditingController controller;
  final bool sending;
  final VoidCallback onSend;
  final List<PlatformFile> attachments;
  final VoidCallback onAttach;
  final ValueChanged<PlatformFile> onRemoveAttachment;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return Padding(
      padding: const EdgeInsets.all(12),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          if (attachments.isNotEmpty) ...[
            Wrap(
              spacing: 6,
              runSpacing: 6,
              children: [
                for (final file in attachments)
                  InputChip(
                    avatar: Icon(Icons.attach_file, size: 16),
                    label: Text(file.name, overflow: TextOverflow.ellipsis),
                    onDeleted: sending ? null : () => onRemoveAttachment(file),
                  ),
              ],
            ),
            const SizedBox(height: 8),
          ],
          Row(
            crossAxisAlignment: CrossAxisAlignment.end,
            children: [
              Expanded(
                child: TextField(
                  controller: controller,
                  minLines: 1,
                  maxLines: 4,
                  style: TextStyle(
                    color: airmiusTextColor(context),
                    fontWeight: FontWeight.w800,
                  ),
                  decoration: InputDecoration(
                    hintText: t('chat.writeMessage'),
                    filled: true,
                    fillColor: airmiusInputColor(context),
                    contentPadding: const EdgeInsets.symmetric(
                      horizontal: 14,
                      vertical: 16,
                    ),
                    enabledBorder: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(8),
                      borderSide: BorderSide(
                        color: airmiusBorderColor(context),
                      ),
                    ),
                    focusedBorder: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(8),
                      borderSide: BorderSide(
                        color: airmiusAccentColor(context),
                      ),
                    ),
                  ),
                ),
              ),
              const SizedBox(width: 8),
              _ComposerIconButton(
                icon: Icons.attach_file,
                tooltip: t('chat.attach'),
                onPressed: onAttach,
              ),
              const SizedBox(width: 8),
              SizedBox(
                width: 56,
                height: 56,
                child: FilledButton(
                  onPressed: sending ? null : onSend,
                  style: FilledButton.styleFrom(
                    padding: EdgeInsets.zero,
                    backgroundColor: sending
                        ? airmiusMutedColor(context)
                        : airmiusAccentColor(context),
                    foregroundColor:
                        Theme.of(context).appBarTheme.backgroundColor ??
                        airmiusSurfaceColor(context),
                    disabledBackgroundColor: airmiusMutedColor(context),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(8),
                    ),
                  ),
                  child: sending
                      ? SizedBox(
                          width: 18,
                          height: 18,
                          child: CircularProgressIndicator(
                            strokeWidth: 2,
                            color:
                                Theme.of(context).appBarTheme.backgroundColor ??
                                airmiusSurfaceColor(context),
                          ),
                        )
                      : Icon(Icons.send_outlined),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _ComposerIconButton extends StatelessWidget {
  const _ComposerIconButton({
    required this.icon,
    required this.onPressed,
    required this.tooltip,
  });

  final IconData icon;
  final VoidCallback onPressed;
  final String tooltip;

  @override
  Widget build(BuildContext context) {
    return Semantics(
      button: true,
      label: tooltip,
      child: Tooltip(
        message: tooltip,
        child: SizedBox(
          width: 48,
          height: 56,
          child: OutlinedButton(
            onPressed: onPressed,
            style: OutlinedButton.styleFrom(
              padding: EdgeInsets.zero,
              foregroundColor: airmiusTextColor(context),
              side: BorderSide(color: airmiusBorderColor(context)),
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(8),
              ),
            ),
            child: Icon(icon, size: 22),
          ),
        ),
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
    final colors = Theme.of(context).colorScheme;
    final bubbleColor = isMine
        ? colors.primaryContainer
        : airmiusInputColor(context);
    final primaryText = isMine
        ? colors.onPrimaryContainer
        : airmiusTextColor(context);
    final secondaryText = isMine
        ? colors.onPrimaryContainer.withValues(alpha: .78)
        : airmiusMutedColor(context);
    final currentUserId = AirmiusServicesScope.of(context).authState.user?.id;
    final reactionCounts = _reactionCounts();
    final myReaction = _userReaction(currentUserId);
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
              border: Border.all(
                color: isMine
                    ? colors.primary.withValues(alpha: .46)
                    : airmiusInputColor(context),
              ),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Expanded(
                      child: Text(
                        message.senderName,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: TextStyle(
                          color: primaryText,
                          fontSize: 12,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                    ),
                    Text(
                      _dateTimeLabel(message.createdAt),
                      style: TextStyle(color: secondaryText, fontSize: 12),
                    ),
                  ],
                ),
                const SizedBox(height: 6),
                Text(
                  message.message,
                  style: TextStyle(
                    color: primaryText,
                    height: 1.35,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                if (reactionCounts.isNotEmpty) ...[
                  const SizedBox(height: 8),
                  Wrap(
                    spacing: 5,
                    runSpacing: 5,
                    children: [
                      for (final entry in reactionCounts.entries)
                        Builder(
                          builder: (context) {
                            final selected = myReaction == entry.key;
                            final badgeColor = selected
                                ? airmiusAccentColor(context)
                                : secondaryText;
                            return Container(
                              height: 26,
                              padding: const EdgeInsets.symmetric(
                                horizontal: 8,
                              ),
                              decoration: BoxDecoration(
                                color: selected
                                    ? airmiusAccentColor(
                                        context,
                                      ).withValues(alpha: isMine ? 0.18 : 0.16)
                                    : (isMine
                                          ? colors.primaryContainer.withValues(
                                              alpha: .72,
                                            )
                                          : airmiusSurfaceColor(context)),
                                borderRadius: BorderRadius.circular(999),
                                border: Border.all(
                                  color: selected
                                      ? airmiusAccentColor(context)
                                      : (isMine
                                            ? colors.primary.withValues(
                                                alpha: .46,
                                              )
                                            : airmiusBorderColor(context)),
                                ),
                              ),
                              child: Row(
                                mainAxisSize: MainAxisSize.min,
                                children: [
                                  Icon(
                                    _reactionIcon(entry.key),
                                    size: 14,
                                    color: badgeColor,
                                  ),
                                  const SizedBox(width: 4),
                                  Text(
                                    '${entry.value}',
                                    style: TextStyle(
                                      color: badgeColor,
                                      fontSize: 12,
                                      fontWeight: FontWeight.w900,
                                    ),
                                  ),
                                ],
                              ),
                            );
                          },
                        ),
                    ],
                  ),
                ],
                if (isMine) ...[
                  const SizedBox(height: 4),
                  Align(
                    alignment: Alignment.centerRight,
                    child: Icon(
                      Icons.done_all,
                      size: 14,
                      color: message.read ? AirmiusColors.green : secondaryText,
                    ),
                  ),
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

  String? _userReaction(int? userId) {
    if (userId == null) return null;
    for (final reaction in message.reactions) {
      if (reaction.userId == userId) return reaction.reaction;
    }
    return null;
  }

  IconData _reactionIcon(String reaction) {
    if (reaction == 'heart') return Icons.favorite_border;
    if (reaction == 'ok') return Icons.check_circle_outline;
    return Icons.thumb_up_alt_outlined;
  }

  void _openReactionPicker(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final myReaction = _userReaction(
      AirmiusServicesScope.of(context).authState.user?.id,
    );
    showModalBottomSheet<void>(
      context: context,
      backgroundColor: airmiusSurfaceColor(context),
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(18)),
      ),
      builder: (context) {
        return SafeArea(
          child: Padding(
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 18),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Container(
                  width: 38,
                  height: 4,
                  decoration: BoxDecoration(
                    color: Theme.of(context).colorScheme.outline,
                    borderRadius: BorderRadius.circular(99),
                  ),
                ),
                const SizedBox(height: 16),
                Text(
                  t('chat.chooseReaction'),
                  style: TextStyle(
                    color: airmiusTextColor(context),
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 14),
                Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    _QuickReaction(
                      icon: Icons.thumb_up_alt_outlined,
                      label: t('chat.reaction.like'),
                      selected: myReaction == 'like',
                      onTap: () {
                        Navigator.pop(context);
                        onReact(message, 'like');
                      },
                    ),
                    _QuickReaction(
                      icon: Icons.favorite_border,
                      label: t('chat.reaction.heart'),
                      selected: myReaction == 'heart',
                      onTap: () {
                        Navigator.pop(context);
                        onReact(message, 'heart');
                      },
                    ),
                    _QuickReaction(
                      icon: Icons.check_circle_outline,
                      label: t('chat.reaction.ok'),
                      selected: myReaction == 'ok',
                      onTap: () {
                        Navigator.pop(context);
                        onReact(message, 'ok');
                      },
                    ),
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
    final t = AirmiusScope.of(context).t;
    showModalBottomSheet<void>(
      context: context,
      backgroundColor: airmiusSurfaceColor(context),
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(18)),
      ),
      builder: (context) {
        return SafeArea(
          child: Padding(
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 18),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Container(
                  width: 38,
                  height: 4,
                  decoration: BoxDecoration(
                    color: Theme.of(context).colorScheme.outline,
                    borderRadius: BorderRadius.circular(99),
                  ),
                ),
                const SizedBox(height: 16),
                Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    _QuickReaction(
                      icon: Icons.thumb_up_alt_outlined,
                      label: t('chat.reaction.like'),
                      onTap: () {
                        Navigator.pop(context);
                        onReact(message, 'like');
                      },
                    ),
                    _QuickReaction(
                      icon: Icons.favorite_border,
                      label: t('chat.reaction.heart'),
                      onTap: () {
                        Navigator.pop(context);
                        onReact(message, 'heart');
                      },
                    ),
                    _QuickReaction(
                      icon: Icons.check_circle_outline,
                      label: t('chat.reaction.ok'),
                      onTap: () {
                        Navigator.pop(context);
                        onReact(message, 'ok');
                      },
                    ),
                  ],
                ),
                const SizedBox(height: 14),
                _SheetAction(
                  icon: Icons.visibility_off_outlined,
                  label: t('chat.hideForMe'),
                  onTap: () {
                    Navigator.pop(context);
                    onHide(message);
                  },
                ),
                if (isMine)
                  _SheetAction(
                    icon: Icons.delete_outline,
                    label: t('chat.deleteMessage'),
                    danger: true,
                    onTap: () {
                      Navigator.pop(context);
                      onDelete(message);
                    },
                  )
                else
                  _SheetAction(
                    icon: Icons.flag_outlined,
                    label: t('chat.reportMessage'),
                    danger: true,
                    onTap: () {
                      Navigator.pop(context);
                      onReport(message);
                    },
                  ),
              ],
            ),
          ),
        );
      },
    );
  }
}

class _QuickReaction extends StatelessWidget {
  const _QuickReaction({
    required this.icon,
    required this.label,
    required this.onTap,
    this.selected = false,
  });

  final IconData icon;
  final String label;
  final VoidCallback onTap;
  final bool selected;

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
          decoration: BoxDecoration(
            color: selected
                ? airmiusAccentColor(context).withValues(alpha: 0.16)
                : airmiusInputColor(context),
            borderRadius: BorderRadius.circular(999),
            border: Border.all(
              color: selected
                  ? airmiusAccentColor(context)
                  : airmiusBorderColor(context),
            ),
          ),
          alignment: Alignment.center,
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(
                icon,
                size: 17,
                color: selected
                    ? airmiusAccentColor(context)
                    : airmiusTextColor(context),
              ),
              const SizedBox(width: 6),
              Text(
                label,
                style: TextStyle(
                  color: selected
                      ? airmiusAccentColor(context)
                      : airmiusTextColor(context),
                  fontWeight: FontWeight.w900,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _SheetAction extends StatelessWidget {
  const _SheetAction({
    required this.icon,
    required this.label,
    required this.onTap,
    this.danger = false,
  });

  final IconData icon;
  final String label;
  final VoidCallback onTap;
  final bool danger;

  @override
  Widget build(BuildContext context) {
    final color = danger ? AirmiusColors.red : airmiusTextColor(context);
    return ListTile(
      onTap: onTap,
      leading: Icon(icon, color: color),
      title: Text(
        label,
        style: TextStyle(color: color, fontWeight: FontWeight.w800),
      ),
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
          Icon(Icons.error_outline, color: AirmiusColors.red, size: 34),
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

String _dateTimeLabel(DateTime value) {
  if (value.millisecondsSinceEpoch == 0) return '';
  final day = value.day.toString().padLeft(2, '0');
  final month = value.month.toString().padLeft(2, '0');
  final hour = value.hour.toString().padLeft(2, '0');
  final minute = value.minute.toString().padLeft(2, '0');
  return '$day.$month., $hour:$minute';
}

int _chatInt(Object? value) =>
    value is num ? value.toInt() : int.tryParse('$value') ?? 0;
