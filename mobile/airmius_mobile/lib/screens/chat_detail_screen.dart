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
import 'profile_screen.dart';

class ChatDetailScreen extends StatefulWidget {
  const ChatDetailScreen({
    super.key,
    required this.conversationId,
    required this.title,
    required this.kind,
    this.onRead,
    this.onConversationRemoved,
  });

  final int conversationId;
  final String title;
  final String kind;
  final VoidCallback? onRead;
  final VoidCallback? onConversationRemoved;

  @override
  State<ChatDetailScreen> createState() => _ChatDetailScreenState();
}

class _ChatDetailScreenState extends State<ChatDetailScreen> {
  final TextEditingController _messageController = TextEditingController();
  final ScrollController _messagesScrollController = ScrollController();
  late Future<AirmiusPage<AirmiusMessage>> _messagesFuture;
  List<AirmiusMessage>? _messages;
  AirmiusConversation? _conversation;
  List<String> _typingUsers = const [];
  Timer? _refreshTimer;
  Timer? _typingStopTimer;
  bool _messagesLoaded = false;
  bool _sending = false;
  bool _markingRead = false;
  bool _refreshingRealtime = false;
  bool _initialScrollScheduled = false;
  bool _typing = false;
  List<PlatformFile> _attachments = const [];

  String _t(String key) => AirmiusScope.of(context).t(key);

  void _openProfile() {
    Navigator.push(
      context,
      MaterialPageRoute(builder: (_) => const ProfileScreen()),
    );
  }

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
    unawaited(_loadConversation());
    _startRealtimePolling();
  }

  Future<void> _loadConversation() async {
    try {
      final conversation = await AirmiusServicesScope.of(
        context,
      ).repositories.conversations.conversation(widget.conversationId);
      if (mounted) setState(() => _conversation = conversation);
    } catch (_) {
      // The message view remains usable if details cannot be refreshed.
    }
  }

  @override
  void dispose() {
    _refreshTimer?.cancel();
    _typingStopTimer?.cancel();
    _messageController.removeListener(_onComposerChanged);
    _messageController.dispose();
    _messagesScrollController.dispose();
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
      _initialScrollScheduled = false;
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
      final previousMessageIds =
          _messages?.map((message) => message.id).toSet() ?? const <int>{};
      final hasNewMessage = snapshot.page.items.any(
        (message) => !previousMessageIds.contains(message.id),
      );
      final messagesChanged = !_sameMessages(_messages, snapshot.page.items);
      final typingChanged = !_sameStrings(_typingUsers, snapshot.typingUsers);
      if (!messagesChanged && !typingChanged) return;
      setState(() {
        _messages = snapshot.page.items;
        _typingUsers = snapshot.typingUsers;
      });
      if (messagesChanged) unawaited(_markRead());
      if (hasNewMessage) _scheduleScrollToLatest();
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
      widget.onRead?.call();
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

  void _scheduleScrollToLatest({bool animated = true}) {
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!mounted || !_messagesScrollController.hasClients) return;
      final target = _messagesScrollController.position.minScrollExtent;
      if (animated) {
        _messagesScrollController.animateTo(
          target,
          duration: const Duration(milliseconds: 250),
          curve: Curves.easeOut,
        );
      } else {
        _messagesScrollController.jumpTo(target);
      }
    });
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
    if ((message.isEmpty && _attachments.isEmpty) ||
        _sending ||
        !(_conversation?.canSendMessages ?? true)) {
      return;
    }

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
      final normalizedKind = conversation.kind.toLowerCase();
      final isGroup =
          normalizedKind.contains('group') || normalizedKind.contains('gruppe');
      final isDirect =
          normalizedKind == 'direct' ||
          normalizedKind == 'chat' ||
          normalizedKind.contains('person');
      final peer = isDirect
          ? conversation.members.cast<JsonMap?>().firstWhere(
              (member) => member != null && '${member['id']}' != '$currentUserId',
              orElse: () => null,
            )
          : null;
      final peerId = peer?['id'] is num
          ? (peer?['id'] as num).toInt()
          : int.tryParse('${peer?['id'] ?? ''}');
      if (isGroup) {
        await _openGroupDetails(conversation, repo);
        return;
      }
      final action = await showModalBottomSheet<String>(
        context: context,
        showDragHandle: true,
        builder: (context) => SafeArea(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              ListTile(
                leading: Icon(Icons.notifications_off_outlined),
                title: Text(_t('chat.notificationSettings')),
                onTap: () => Navigator.pop(context, 'mute'),
              ),
              if (isDirect)
                ListTile(
                  leading: const Icon(Icons.delete_outline),
                  title: Text(_t('chat.deleteForMe')),
                  subtitle: Text(_t('chat.deleteForMeHint')),
                  textColor: AirmiusColors.red,
                  iconColor: AirmiusColors.red,
                  onTap: () => Navigator.pop(context, 'clear'),
                ),
              if (isDirect && peerId != null)
                ListTile(
                  leading: Icon(
                    conversation.directPeerHasBlocked
                        ? Icons.lock_open_outlined
                        : Icons.block_outlined,
                  ),
                  title: Text(
                    _t(
                      conversation.directPeerHasBlocked
                          ? 'chat.unblockPerson'
                          : 'chat.blockPerson',
                    ),
                  ),
                  subtitle: Text(
                    _t(
                      conversation.directPeerHasBlocked
                          ? 'chat.unblockPersonHint'
                          : 'chat.blockPersonHint',
                    ),
                  ),
                  onTap: () => Navigator.pop(context, 'block'),
                ),
            ],
          ),
        ),
      );
      if (!mounted || action == null) return;
      if (action == 'mute') {
        await _muteConversation(conversation);
      } else if (action == 'clear') {
        await _clearConversation(repo);
      } else if (action == 'block' && peerId != null) {
        await _togglePeerBlock(peerId, conversation.directPeerHasBlocked);
      }
    } catch (error) {
      if (mounted) {
        _showActionResult(_t('chat.settingsLoadFailed'));
      }
    }
  }

  Future<void> _openGroupDetails(
    AirmiusConversation conversation,
    AirmiusConversationRepository repo,
  ) async {
    var current = conversation;
    while (mounted) {
      final action = await showModalBottomSheet<String>(
        context: context,
        showDragHandle: true,
        isScrollControlled: true,
        builder: (sheetContext) => _GroupDetailsSheet(
          conversation: current,
          title: widget.title,
          roleLabel: _roleLabel,
          memberRole: _memberRole,
          onAction: (value) => Navigator.pop(sheetContext, value),
        ),
      );
      if (!mounted || action == null) return;

      if (action == 'edit') {
        await _editConversation(current);
      } else if (action == 'invite') {
        await _inviteMembers(current);
      } else if (action == 'members') {
        await _manageMembers(current);
      } else if (action == 'mute') {
        await _muteConversation(current);
      } else if (action == 'leave') {
        final left = await _confirmLeaveGroup(repo);
        if (left) return;
      } else if (action == 'delete_group') {
        await _deleteGroup(repo);
        return;
      }

      try {
        current = await repo.conversation(widget.conversationId);
        if (mounted) setState(() => _conversation = current);
      } catch (_) {
        return;
      }
    }
  }

  Future<bool> _confirmLeaveGroup(AirmiusConversationRepository repo) async {
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
      widget.onConversationRemoved?.call();
      if (mounted) Navigator.pop(context, true);
      return true;
    }
    return false;
  }

  Future<void> _deleteGroup(AirmiusConversationRepository repo) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(_t('chat.deleteGroupForEveryone')),
        content: Text(_t('chat.deleteGroupForEveryoneBody')),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: Text(_t('common.cancel')),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, true),
            child: Text(_t('chat.deleteGroup')),
          ),
        ],
      ),
    );
    if (confirmed != true || !mounted) return;

    await repo.deleteConversation(widget.conversationId);
    widget.onConversationRemoved?.call();
    if (mounted) Navigator.pop(context, true);
  }

  Future<void> _clearConversation(AirmiusConversationRepository repo) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(_t('chat.deleteForMeTitle')),
        content: Text(_t('chat.deleteForMeBody')),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: Text(_t('common.cancel')),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, true),
            child: Text(_t('chat.deleteForMe')),
          ),
        ],
      ),
    );
    if (confirmed != true || !mounted) return;

    await repo.clearConversation(widget.conversationId);
    widget.onConversationRemoved?.call();
    if (mounted) Navigator.pop(context, true);
  }

  Future<void> _togglePeerBlock(int peerId, bool hasBlocked) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(_t(hasBlocked ? 'chat.unblockPerson' : 'chat.blockPerson')),
        content: Text(
          _t(hasBlocked ? 'chat.unblockPersonQuestion' : 'chat.blockPersonQuestion'),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: Text(_t('common.cancel')),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, true),
            child: Text(_t(hasBlocked ? 'chat.unblockPerson' : 'chat.blockPerson')),
          ),
        ],
      ),
    );
    if (confirmed != true || !mounted) return;

    final services = AirmiusServicesScope.of(context);
    final client = services.clientForSession(services.authState.session);
    if (hasBlocked) {
      await client.unblockUser(peerId);
    } else {
      await client.blockUser(peerId);
    }
    if (mounted) {
      _showActionResult(
        _t(hasBlocked ? 'chat.personUnblocked' : 'chat.personBlocked'),
      );
    }
  }

  Future<void> _editConversation(AirmiusConversation conversation) async {
    final name = TextEditingController(text: conversation.title);
    final description = TextEditingController(text: conversation.description);
    var postingPolicy = conversation.postingPolicy;
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => StatefulBuilder(
        builder: (context, setDialogState) => AlertDialog(
          title: Text(_t('chat.groupProfile')),
          content: SingleChildScrollView(
            child: Column(
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
                const SizedBox(height: 12),
                DropdownButtonFormField<String>(
                  initialValue: postingPolicy,
                  decoration: InputDecoration(labelText: _t('chat.whoCanWrite')),
                  items: [
                    DropdownMenuItem(
                      value: 'all',
                      child: Text(_t('chat.allMembers')),
                    ),
                    DropdownMenuItem(
                      value: 'management',
                      child: Text(_t('chat.managementOnly')),
                    ),
                  ],
                  onChanged: (value) => setDialogState(
                    () => postingPolicy = value ?? postingPolicy,
                  ),
                ),
              ],
            ),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(dialogContext, false),
              child: Text(_t('common.cancel')),
            ),
            FilledButton(
              onPressed: () => Navigator.pop(dialogContext, true),
              child: Text(_t('common.save')),
            ),
          ],
        ),
      ),
    );
    if (confirmed == true && mounted) {
      await AirmiusServicesScope.of(
        context,
      ).repositories.conversations.updateConversation(widget.conversationId, {
        'name': name.text.trim(),
        'description': description.text.trim(),
        'posting_policy': postingPolicy,
      });
      if (mounted) {
        _showActionResult(_t('chat.groupProfileSaved'));
        unawaited(_loadConversation());
      }
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

  String _memberRole(JsonMap member) =>
      member['conversation_role']?.toString() ??
      member['team_role']?.toString() ??
      'member';

  String _roleLabel(String role) => switch (role) {
    'owner' => _t('chat.owner'),
    'moderator' => _t('chat.moderator'),
    _ => _t('chat.member'),
  };

  String _memberRoleLabel(JsonMap member) => _roleLabel(_memberRole(member));

  bool _memberActionsAvailable(
    AirmiusConversation conversation,
    JsonMap member,
    int? currentUserId,
  ) {
    final memberId = _chatInt(member['id']);
    if (memberId == currentUserId) return false;

    return conversation.canManageRoles ||
        (conversation.canManageMembers && _memberRole(member) == 'member');
  }

  List<PopupMenuEntry<String>> _memberActionItems(
    AirmiusConversation conversation,
    JsonMap member,
    int? currentUserId,
  ) {
    final currentRole = _memberRole(member);
    return [
      if (conversation.canManageRoles && currentRole != 'owner')
        PopupMenuItem(value: 'owner', child: Text(_t('chat.makeOwner'))),
      if (conversation.canManageRoles && currentRole != 'moderator')
        PopupMenuItem(
          value: 'moderator',
          child: Text(_t('chat.makeModerator')),
        ),
      if (conversation.canManageRoles && currentRole != 'member')
        PopupMenuItem(value: 'member', child: Text(_t('chat.makeMember'))),
      if (_chatInt(member['id']) != currentUserId &&
          (conversation.canManageRoles || currentRole == 'member'))
        PopupMenuItem(
          value: 'remove',
          child: Text(_t('chat.removeFromGroup')),
        ),
    ];
  }

  Future<void> _manageMembers(AirmiusConversation conversation) async {
    final currentUserId = AirmiusServicesScope.of(context).authState.user?.id;
    final manageable = conversation.members.toList();
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
                _t(
                  conversation.canManageMembers
                      ? 'chat.manageMembers'
                      : 'chat.viewMembers',
                ),
                style: TextStyle(fontWeight: FontWeight.w900),
              ),
            ),
            for (final member in manageable)
              ListTile(
                leading: const CircleAvatar(child: Icon(Icons.person_outline)),
                title: Text(member['name']?.toString() ?? _t('chat.member')),
                subtitle: Text(_memberRoleLabel(member)),
                trailing: _memberActionsAvailable(
                  conversation,
                  member,
                  currentUserId,
                )
                    ? PopupMenuButton<String>(
                  onSelected: (selected) => Navigator.pop(sheetContext, (
                    selected,
                    _chatInt(member['id']),
                  )),
                  itemBuilder: (_) => _memberActionItems(
                    conversation,
                    member,
                    currentUserId,
                  ),
                )
                    : null,
              ),
          ],
        ),
      ),
    );
    if (action == null || !mounted) return;
    if (!conversation.canManageMembers) {
      _showActionResult(_t('chat.memberActionFailed'));
      return;
    }
    final member = manageable.firstWhere(
      (entry) => _chatInt(entry['id']) == action.$2,
    );
    final name = member['name']?.toString() ?? _t('chat.member');
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: Text(
          action.$1 == 'remove'
              ? _t('chat.removeMemberTitle')
              : _t('chat.changeRoleTitle'),
        ),
        content: Text(
          action.$1 == 'remove'
              ? '$name ${_t('chat.accessLostAfter')}'
              : '$name: ${_roleLabel(action.$1)}',
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
      if (action.$1 == 'remove') {
        await repo.removeConversationMember(widget.conversationId, action.$2);
        if (mounted) {
          _showActionResult('$name ${_t('chat.removedFromGroupAfter')}');
        }
      } else {
        await repo.updateConversationMemberRole(
          widget.conversationId,
          action.$2,
          action.$1,
        );
        if (mounted) _showActionResult(_t('chat.roleUpdated'));
      }
      if (mounted) unawaited(_loadConversation());
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
            onTap: _openProfile,
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
                          _messages ??
                          snapshot.data?.items ??
                          const <AirmiusMessage>[];
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

                      if (!_initialScrollScheduled) {
                        _initialScrollScheduled = true;
                        _scheduleScrollToLatest(animated: false);
                      }

                      return ListView.separated(
                        controller: _messagesScrollController,
                        reverse: true,
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
                if (_conversation?.canSendMessages ?? true)
                  _MessageComposer(
                    controller: _messageController,
                    sending: _sending,
                    onSend: _send,
                    attachments: _attachments,
                    onAttach: _pickAttachments,
                    onRemoveAttachment: _removeAttachment,
                  )
                else
                  Padding(
                    padding: const EdgeInsets.all(16),
                    child: Text(
                      scope.t('chat.managementOnlyCanWrite'),
                      textAlign: TextAlign.center,
                      style: TextStyle(
                        color: airmiusMutedColor(context),
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  String _conversationSubtitle() {
    if (_typingUsers.length == 1) {
      return '${_typingUsers.first} ${_t('chat.typingAfter')}';
    }
    if (_typingUsers.length > 1) {
      return '${_typingUsers.length} ${_t('chat.peopleTypingAfter')}';
    }
    if (_conversation?.membersCount != null) {
      final count = _conversation!.membersCount!;
      return '${widget.kind} · $count ${count == 1 ? _t('chat.member') : _t('chat.members')}';
    }
    return widget.kind;
  }
}

class _MessagesSnapshot {
  const _MessagesSnapshot({required this.page, required this.typingUsers});

  final AirmiusPage<AirmiusMessage> page;
  final List<String> typingUsers;
}

class _GroupDetailsSheet extends StatelessWidget {
  const _GroupDetailsSheet({
    required this.conversation,
    required this.title,
    required this.roleLabel,
    required this.memberRole,
    required this.onAction,
  });

  final AirmiusConversation conversation;
  final String title;
  final String Function(String role) roleLabel;
  final String Function(JsonMap member) memberRole;
  final ValueChanged<String> onAction;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final memberCount = conversation.membersCount ?? conversation.members.length;
    final ownerCount = conversation.members
        .where((member) => memberRole(member) == 'owner')
        .length;
    final moderatorCount = conversation.members
        .where((member) => memberRole(member) == 'moderator')
        .length;

    return SafeArea(
      child: DraggableScrollableSheet(
        expand: false,
        initialChildSize: 0.78,
        minChildSize: 0.45,
        maxChildSize: 0.92,
        builder: (context, controller) => ListView(
          controller: controller,
          padding: const EdgeInsets.fromLTRB(16, 4, 16, 18),
          children: [
            Row(
              children: [
                CircleAvatar(
                  radius: 24,
                  backgroundColor: airmiusTextColor(context),
                  child: Text(
                    initialsFromName(title, fallback: 'G'),
                    style: TextStyle(
                      color: airmiusSurfaceColor(context),
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                ),
                const SizedBox(width: 12),
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
                      const SizedBox(height: 4),
                      Text(
                        '$memberCount ${memberCount == 1 ? t('chat.member') : t('chat.members')}',
                        style: TextStyle(
                          color: airmiusMutedColor(context),
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
            if ((conversation.description ?? '').trim().isNotEmpty) ...[
              const SizedBox(height: 14),
              Text(
                conversation.description!.trim(),
                style: TextStyle(
                  color: airmiusMutedColor(context),
                  height: 1.35,
                  fontWeight: FontWeight.w600,
                ),
              ),
            ],
            const SizedBox(height: 18),
            _GroupActionSection(
              title: t('chat.groupProfile'),
              children: [
                _GroupActionTile(
                  icon: Icons.notifications_off_outlined,
                  title: t('chat.notificationSettings'),
                  onTap: () => onAction('mute'),
                ),
                if (conversation.canEditGroup)
                  _GroupActionTile(
                    icon: Icons.edit_outlined,
                    title: t('chat.editGroupProfile'),
                    onTap: () => onAction('edit'),
                  ),
              ],
            ),
            const SizedBox(height: 12),
            _GroupActionSection(
              title: t('chat.permissions'),
              children: [
                ListTile(
                  contentPadding: EdgeInsets.zero,
                  leading: Icon(
                    conversation.postingPolicy == 'management'
                        ? Icons.lock_outline
                        : Icons.forum_outlined,
                    color: airmiusTextColor(context),
                  ),
                  title: Text(
                    conversation.postingPolicy == 'management'
                        ? t('chat.managementOnly')
                        : t('chat.allMembers'),
                    style: const TextStyle(fontWeight: FontWeight.w800),
                  ),
                  subtitle: Text(t('chat.whoCanWrite')),
                ),
                Padding(
                  padding: const EdgeInsets.fromLTRB(0, 0, 0, 8),
                  child: Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: [
                      _RoleCountChip(
                        label: roleLabel('owner'),
                        count: ownerCount,
                        icon: Icons.verified_user_outlined,
                      ),
                      _RoleCountChip(
                        label: roleLabel('moderator'),
                        count: moderatorCount,
                        icon: Icons.shield_outlined,
                      ),
                    ],
                  ),
                ),
              ],
            ),
            const SizedBox(height: 12),
            _GroupActionSection(
              title: t('chat.membersAndRoles'),
              children: [
                if (conversation.canManageMembers)
                  _GroupActionTile(
                    icon: Icons.person_add_alt_outlined,
                    title: t('chat.inviteMembers'),
                    onTap: () => onAction('invite'),
                  ),
                _GroupActionTile(
                  icon: conversation.canManageMembers
                      ? Icons.manage_accounts_outlined
                      : Icons.people_outline,
                  title: t(
                    conversation.canManageMembers
                        ? 'chat.manageMembers'
                        : 'chat.viewMembers',
                  ),
                  subtitle: t('chat.membersAndRoles'),
                  onTap: () => onAction('members'),
                ),
              ],
            ),
            const SizedBox(height: 12),
            _GroupActionSection(
              title: t('chat.dangerZone'),
              danger: true,
              children: [
                _GroupActionTile(
                  icon: Icons.logout_outlined,
                  title: t('chat.leaveGroup'),
                  danger: true,
                  onTap: () => onAction('leave'),
                ),
                if (conversation.canDeleteGroup)
                  _GroupActionTile(
                    icon: Icons.delete_forever_outlined,
                    title: t('chat.deleteGroupForEveryone'),
                    danger: true,
                    onTap: () => onAction('delete_group'),
                  ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _GroupActionSection extends StatelessWidget {
  const _GroupActionSection({
    required this.title,
    required this.children,
    this.danger = false,
  });

  final String title;
  final List<Widget> children;
  final bool danger;

  @override
  Widget build(BuildContext context) {
    return DecoratedBox(
      decoration: BoxDecoration(
        color: danger
            ? AirmiusColors.red.withValues(alpha: 0.06)
            : airmiusInputColor(context),
        borderRadius: BorderRadius.circular(8),
        border: Border.all(
          color: danger
              ? AirmiusColors.red.withValues(alpha: 0.25)
              : airmiusBorderColor(context),
        ),
      ),
      child: Padding(
        padding: const EdgeInsets.fromLTRB(12, 10, 12, 6),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text(
              title,
              style: TextStyle(
                color: danger ? AirmiusColors.red : airmiusMutedColor(context),
                fontSize: 12,
                fontWeight: FontWeight.w900,
              ),
            ),
            const SizedBox(height: 4),
            ...children,
          ],
        ),
      ),
    );
  }
}

class _GroupActionTile extends StatelessWidget {
  const _GroupActionTile({
    required this.icon,
    required this.title,
    required this.onTap,
    this.subtitle,
    this.danger = false,
  });

  final IconData icon;
  final String title;
  final String? subtitle;
  final VoidCallback onTap;
  final bool danger;

  @override
  Widget build(BuildContext context) {
    final color = danger ? AirmiusColors.red : airmiusTextColor(context);
    return ListTile(
      contentPadding: EdgeInsets.zero,
      leading: Icon(icon, color: color),
      title: Text(
        title,
        style: TextStyle(color: color, fontWeight: FontWeight.w800),
      ),
      subtitle: subtitle == null ? null : Text(subtitle!),
      trailing: Icon(Icons.chevron_right, color: airmiusMutedColor(context)),
      onTap: onTap,
    );
  }
}

class _RoleCountChip extends StatelessWidget {
  const _RoleCountChip({
    required this.label,
    required this.count,
    required this.icon,
  });

  final String label;
  final int count;
  final IconData icon;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
      decoration: BoxDecoration(
        color: airmiusSurfaceColor(context),
        borderRadius: BorderRadius.circular(8),
        border: Border.all(color: airmiusBorderColor(context)),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 16, color: airmiusMutedColor(context)),
          const SizedBox(width: 6),
          Text(
            '$count $label',
            style: TextStyle(
              color: airmiusTextColor(context),
              fontSize: 12,
              fontWeight: FontWeight.w800,
            ),
          ),
        ],
      ),
    );
  }
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
    final senderAvatarUrl = resolveAirmiusImageUrl(message.senderAvatarUrl);
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
                    if (!isMine) ...[
                      CircleAvatar(
                        radius: 12,
                        backgroundColor: airmiusSurfaceColor(context),
                        foregroundImage: senderAvatarUrl == null
                            ? null
                            : NetworkImage(senderAvatarUrl),
                        onForegroundImageError: senderAvatarUrl == null
                            ? null
                            : (_, _) {},
                        child: Text(
                          initialsFromName(message.senderName),
                          style: TextStyle(
                            color: primaryText,
                            fontSize: 8,
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                      ),
                      const SizedBox(width: 7),
                    ],
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
                if (message.message.trim().isNotEmpty) ...[
                  const SizedBox(height: 6),
                  Text(
                    message.message,
                    style: TextStyle(
                      color: primaryText,
                      height: 1.35,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                ],
                if (message.attachments.isNotEmpty) ...[
                  const SizedBox(height: 8),
                  for (final attachment in message.attachments) ...[
                    _MessageAttachmentTile(
                      attachment: attachment,
                      textColor: primaryText,
                      mutedColor: secondaryText,
                    ),
                    if (attachment != message.attachments.last)
                      const SizedBox(height: 6),
                  ],
                ],
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

class _MessageAttachmentTile extends StatelessWidget {
  const _MessageAttachmentTile({
    required this.attachment,
    required this.textColor,
    required this.mutedColor,
  });

  final AirmiusPostAttachment attachment;
  final Color textColor;
  final Color mutedColor;

  @override
  Widget build(BuildContext context) {
    final url = resolveAirmiusImageUrl(attachment.url) ?? attachment.url;
    if (attachment.isImage) {
      return ClipRRect(
        borderRadius: BorderRadius.circular(10),
        child: ConstrainedBox(
          constraints: const BoxConstraints(
            minWidth: 180,
            maxWidth: 236,
            maxHeight: 260,
          ),
          child: Image.network(
            url,
            fit: BoxFit.cover,
            loadingBuilder: (context, child, progress) {
              if (progress == null) return child;
              return Container(
                height: 170,
                alignment: Alignment.center,
                color: Colors.black.withValues(alpha: .08),
                child: SizedBox(
                  width: 22,
                  height: 22,
                  child: CircularProgressIndicator(
                    strokeWidth: 2,
                    value: progress.expectedTotalBytes == null
                        ? null
                        : progress.cumulativeBytesLoaded /
                              progress.expectedTotalBytes!,
                  ),
                ),
              );
            },
            errorBuilder: (context, error, stackTrace) =>
                _FileAttachmentFallback(
                  attachment: attachment,
                  textColor: textColor,
                  mutedColor: mutedColor,
                ),
          ),
        ),
      );
    }

    return _FileAttachmentFallback(
      attachment: attachment,
      textColor: textColor,
      mutedColor: mutedColor,
    );
  }
}

class _FileAttachmentFallback extends StatelessWidget {
  const _FileAttachmentFallback({
    required this.attachment,
    required this.textColor,
    required this.mutedColor,
  });

  final AirmiusPostAttachment attachment;
  final Color textColor;
  final Color mutedColor;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(10),
      decoration: BoxDecoration(
        color: Colors.black.withValues(alpha: .08),
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: mutedColor.withValues(alpha: .22)),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(Icons.attach_file, size: 18, color: textColor),
          const SizedBox(width: 8),
          Flexible(
            child: Text(
              attachment.name,
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
              style: TextStyle(color: textColor, fontWeight: FontWeight.w800),
            ),
          ),
        ],
      ),
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
