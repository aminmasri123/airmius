import 'package:flutter/material.dart';

import '../core/airmius_api_client.dart';
import '../core/airmius_api_models.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';
import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'chat_detail_screen.dart';

class NewConversationScreen extends StatefulWidget {
  const NewConversationScreen({
    super.key,
    this.initialUserId,
    this.initialUserName,
  });

  final int? initialUserId;
  final String? initialUserName;

  @override
  State<NewConversationScreen> createState() => _NewConversationScreenState();
}

class _NewConversationScreenState extends State<NewConversationScreen> {
  final TextEditingController _messageController = TextEditingController();
  final TextEditingController _nameController = TextEditingController();
  String _type = 'direct';
  final Set<int> _selectedUserIds = {};
  int? _selectedTeamId;
  Future<_ConversationChoices>? _choicesFuture;
  bool _starting = false;

  String _t(String key) => AirmiusScope.of(context).t(key);

  bool get _canStart {
    if (_type == 'team') return _selectedTeamId != null;
    if (_type == 'direct') return _selectedUserIds.length == 1;
    return _selectedUserIds.length >= 2 &&
        _nameController.text.trim().isNotEmpty;
  }

  @override
  void dispose() {
    _messageController.dispose();
    _nameController.dispose();
    super.dispose();
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _choicesFuture ??= _loadChoices();
  }

  Future<_ConversationChoices> _loadChoices() async {
    final services = AirmiusServicesScope.of(context);
    final client = services.clientForSession(services.authState.session);
    final results = await Future.wait([client.friends(), client.teams()]);
    final friendData = results[0]['data'];
    final teamData = results[1]['data'];
    final friends = friendData is JsonMap && friendData['friends'] is List
        ? (friendData['friends'] as List).whereType<JsonMap>().toList()
        : const <JsonMap>[];
    final teams = teamData is List
        ? teamData.whereType<JsonMap>().toList()
        : const <JsonMap>[];
    final directContacts = [...friends];
    final initialUserId = widget.initialUserId;
    if (initialUserId != null &&
        !directContacts.any(
          (contact) => _asInt(contact['id']) == initialUserId,
        )) {
      directContacts.insert(0, {
        'id': initialUserId,
        'name': widget.initialUserName ?? _t('chat.contact'),
        'email': '',
      });
    }
    if (initialUserId != null) {
      _selectedUserIds.add(initialUserId);
    }
    return _ConversationChoices(
      friends: friends,
      directContacts: directContacts,
      teams: teams,
    );
  }

  void _setType(String type) {
    setState(() {
      _type = type;
      _selectedUserIds.clear();
      _selectedTeamId = null;
    });
  }

  Future<void> _startConversation() async {
    if (!_canStart || _starting) return;
      setState(() => _starting = true);
    try {
      final conversation = await AirmiusServicesScope.of(context)
          .repositories
          .conversations
          .createConversation(
            type: _type,
            participantIds: _selectedUserIds.toList(),
            teamId: _selectedTeamId,
            name: _type == 'group' ? _nameController.text.trim() : null,
            message: _messageController.text.trim(),
          );
      if (!mounted) return;
      final authUserId = AirmiusServicesScope.of(context).authState.user?.id;
      Navigator.pushReplacement(
        context,
        MaterialPageRoute(
          builder: (_) => ChatDetailScreen(
            conversationId: conversation.id,
            title: conversation.titleForViewer(authUserId),
            kind: conversation.kind,
          ),
        ),
      );
    } catch (error) {
      if (!mounted) return;
      setState(() => _starting = false);
      final message = error is AirmiusApiException
          ? error.userMessage
          : _t('chat.createFailed');
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(message)));
    }
  }

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return Scaffold(
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(14),
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 420),
              child: Container(
                decoration: BoxDecoration(
                  color: airmiusSurfaceColor(context),
                  borderRadius: BorderRadius.circular(8),
                  border: Border.all(color: airmiusBorderColor(context)),
                  boxShadow: const [
                    BoxShadow(
                      color: Color(0x66000000),
                      blurRadius: 28,
                      offset: Offset(0, 16),
                    ),
                  ],
                ),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Padding(
                      padding: const EdgeInsets.all(16),
                      child: Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  t('chat.newConversation'),
                                  style: TextStyle(
                                    color: airmiusTextColor(context),
                                    fontSize: 18,
                                    fontWeight: FontWeight.w900,
                                  ),
                                ),
                                SizedBox(height: 6),
                                Text(
                                  t('chat.groupMinimum'),
                                  style: TextStyle(
                                    color: airmiusMutedColor(context),
                                    fontSize: 14,
                                    height: 1.25,
                                  ),
                                ),
                              ],
                            ),
                          ),
                          IconButton(
                            tooltip: t('chat.close'),
                            onPressed: () => Navigator.pop(context),
                            icon: Icon(
                              Icons.close,
                              color: airmiusMutedColor(context),
                              size: 26,
                            ),
                          ),
                        ],
                      ),
                    ),
                    Container(height: 1, color: airmiusBorderColor(context)),
                    Container(
                      color: airmiusInputColor(context),
                      padding: const EdgeInsets.all(8),
                      child: Row(
                        children: [
                          _ConversationTypeTab(
                            label: t('chat.direct'),
                            active: _type == 'direct',
                            onTap: () => _setType('direct'),
                          ),
                          _ConversationTypeTab(
                            label: t('chat.group'),
                            active: _type == 'group',
                            onTap: () => _setType('group'),
                          ),
                          _ConversationTypeTab(
                            label: t('chat.team'),
                            active: _type == 'team',
                            onTap: () => _setType('team'),
                          ),
                        ],
                      ),
                    ),
                    if (_type == 'team')
                      Padding(
                        padding: const EdgeInsets.all(12),
                        child: FutureBuilder<_ConversationChoices>(
                          future: _choicesFuture,
                          builder: (context, snapshot) => _ChoicesList(
                            loading:
                                snapshot.connectionState ==
                                ConnectionState.waiting,
                            emptyLabel: t('chat.noTeams'),
                            children:
                                (snapshot.data?.teams ?? const <JsonMap>[])
                                    .map(
                                      (team) => _TeamSelectCard(
                                        name:
                                            '${team['name'] ?? t('chat.team')}',
                                        selected:
                                            _selectedTeamId ==
                                            _asInt(team['id']),
                                        onTap: () => setState(() {
                                          final id = _asInt(team['id']);
                                          _selectedTeamId =
                                              _selectedTeamId == id ? null : id;
                                        }),
                                      ),
                                    )
                                    .toList(),
                          ),
                        ),
                      )
                    else
                      Padding(
                        padding: const EdgeInsets.fromLTRB(8, 8, 8, 6),
                        child: FutureBuilder<_ConversationChoices>(
                          future: _choicesFuture,
                          builder: (context, snapshot) => _ChoicesList(
                            loading:
                                snapshot.connectionState ==
                                ConnectionState.waiting,
                            emptyLabel: t('chat.noContacts'),
                            children:
                                (snapshot.data?.directContacts ??
                                        const <JsonMap>[])
                                    .map((friend) {
                                      final id = _asInt(friend['id']);
                                      final name =
                                          '${friend['name'] ?? t('chat.contact')}';
                                      return _ParticipantCard(
                                        initials: initialsFromName(
                                          name,
                                          fallback: '?',
                                        ),
                                        name: name,
                                        email: '${friend['email'] ?? ''}',
                                        selected: _selectedUserIds.contains(id),
                                        onTap: () => setState(() {
                                          if (_type == 'direct') {
                                            _selectedUserIds
                                              ..clear()
                                              ..add(id);
                                          } else if (!_selectedUserIds.add(
                                            id,
                                          )) {
                                            _selectedUserIds.remove(id);
                                          }
                                        }),
                                      );
                                    })
                                    .toList(),
                          ),
                        ),
                      ),
                    Container(height: 1, color: airmiusBorderColor(context)),
                    Padding(
                      padding: const EdgeInsets.all(12),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: [
                          TextField(
                            controller: _nameController,
                            enabled: _type == 'group',
                            onChanged: (_) => setState(() {}),
                            decoration: InputDecoration(
                              labelText: _type == 'group'
                                  ? t('chat.groupName')
                                  : t('chat.groupNameOptional'),
                            ),
                          ),
                          const SizedBox(height: 10),
                          TextField(
                            controller: _messageController,
                            minLines: 2,
                            maxLines: 3,
                            style: TextStyle(
                              color: airmiusTextColor(context),
                              fontWeight: FontWeight.w700,
                            ),
                            decoration: InputDecoration(
                              hintText: t('chat.firstMessageOptional'),
                              filled: true,
                              fillColor: airmiusInputColor(context),
                              contentPadding: const EdgeInsets.symmetric(
                                horizontal: 12,
                                vertical: 12,
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
                          const SizedBox(height: 12),
                          Row(
                            children: [
                              Expanded(
                                child: OutlinedButton(
                                  onPressed: () => Navigator.pop(context),
                                  style: OutlinedButton.styleFrom(
                                    foregroundColor: airmiusMutedColor(context),
                                    side: BorderSide(
                                      color: airmiusBorderColor(context),
                                    ),
                                    shape: RoundedRectangleBorder(
                                      borderRadius: BorderRadius.circular(8),
                                    ),
                                    padding: const EdgeInsets.symmetric(
                                      vertical: 13,
                                    ),
                                  ),
                                  child: Text(
                                    t('common.cancel'),
                                    style: TextStyle(
                                      fontWeight: FontWeight.w800,
                                    ),
                                  ),
                                ),
                              ),
                              const SizedBox(width: 8),
                              Expanded(
                                child: FilledButton(
                                  onPressed: _canStart
                                      ? _startConversation
                                      : null,
                                  style: FilledButton.styleFrom(
                                    backgroundColor: airmiusTextColor(context),
                                    foregroundColor:
                                        Theme.of(
                                          context,
                                        ).appBarTheme.backgroundColor ??
                                        airmiusSurfaceColor(context),
                                    disabledBackgroundColor: airmiusMutedColor(
                                      context,
                                    ),
                                    disabledForegroundColor:
                                        Theme.of(
                                          context,
                                        ).appBarTheme.backgroundColor ??
                                        airmiusSurfaceColor(context),
                                    shape: RoundedRectangleBorder(
                                      borderRadius: BorderRadius.circular(8),
                                    ),
                                    padding: const EdgeInsets.symmetric(
                                      vertical: 13,
                                    ),
                                  ),
                                  child: Text(
                                    _starting
                                        ? t('chat.creating')
                                        : t('chat.start'),
                                    style: TextStyle(
                                      fontWeight: FontWeight.w900,
                                    ),
                                  ),
                                ),
                              ),
                            ],
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}

class _ConversationTypeTab extends StatelessWidget {
  const _ConversationTypeTab({
    required this.label,
    required this.active,
    required this.onTap,
  });

  final String label;
  final bool active;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Expanded(
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(6),
        child: AnimatedContainer(
          duration: const Duration(milliseconds: 140),
          height: 40,
          alignment: Alignment.center,
          decoration: BoxDecoration(
            color: active ? airmiusSurfaceColor(context) : Colors.transparent,
            borderRadius: BorderRadius.circular(6),
            boxShadow: active
                ? const [
                    BoxShadow(
                      color: Color(0x33000000),
                      blurRadius: 8,
                      offset: Offset(0, 3),
                    ),
                  ]
                : null,
          ),
          child: Text(
            label,
            style: TextStyle(
              color: active
                  ? airmiusTextColor(context)
                  : airmiusMutedColor(context),
              fontSize: 14,
              fontWeight: FontWeight.w800,
            ),
          ),
        ),
      ),
    );
  }
}

class _ParticipantCard extends StatelessWidget {
  const _ParticipantCard({
    required this.initials,
    required this.name,
    required this.email,
    required this.selected,
    required this.onTap,
  });

  final String initials;
  final String name;
  final String email;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(8),
      child: Container(
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(
          color: selected
              ? airmiusInputColor(context)
              : airmiusSurfaceColor(context),
          borderRadius: BorderRadius.circular(8),
          border: Border.all(
            color: selected
                ? airmiusAccentColor(context)
                : airmiusBorderColor(context),
          ),
        ),
        child: Row(
          children: [
            Container(
              width: 40,
              height: 40,
              alignment: Alignment.center,
              decoration: BoxDecoration(
                color: airmiusTextColor(context),
                borderRadius: BorderRadius.circular(8),
              ),
              child: Text(
                initials,
                style: TextStyle(
                  color:
                      Theme.of(context).appBarTheme.backgroundColor ??
                      airmiusSurfaceColor(context),
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
                    name,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(
                      color: airmiusTextColor(context),
                      fontSize: 14,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                  const SizedBox(height: 3),
                  Text(
                    email,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(
                      color: airmiusMutedColor(context),
                      fontSize: 12,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ],
              ),
            ),
            Icon(
              selected ? Icons.check_circle : Icons.circle_outlined,
              color: selected
                  ? AirmiusColors.green
                  : airmiusMutedColor(context),
              size: 22,
            ),
          ],
        ),
      ),
    );
  }
}

class _TeamSelectCard extends StatelessWidget {
  const _TeamSelectCard({
    required this.name,
    required this.selected,
    required this.onTap,
  });

  final String name;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(8),
      child: Container(
        height: 46,
        padding: const EdgeInsets.symmetric(horizontal: 12),
        decoration: BoxDecoration(
          color: airmiusInputColor(context),
          borderRadius: BorderRadius.circular(8),
          border: Border.all(
            color: selected
                ? airmiusAccentColor(context)
                : airmiusBorderColor(context),
          ),
        ),
        child: Row(
          children: [
            Expanded(
              child: Text(
                name,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: TextStyle(
                  color: airmiusTextColor(context),
                  fontSize: 14,
                  fontWeight: FontWeight.w800,
                ),
              ),
            ),
            Icon(
              selected ? Icons.check_circle : Icons.keyboard_arrow_down,
              color: selected
                  ? AirmiusColors.green
                  : airmiusMutedColor(context),
            ),
          ],
        ),
      ),
    );
  }
}

class _ConversationChoices {
  const _ConversationChoices({
    required this.friends,
    required this.directContacts,
    required this.teams,
  });

  final List<JsonMap> friends;
  final List<JsonMap> directContacts;
  final List<JsonMap> teams;
}

class _ChoicesList extends StatelessWidget {
  const _ChoicesList({
    required this.loading,
    required this.emptyLabel,
    required this.children,
  });

  final bool loading;
  final String emptyLabel;
  final List<Widget> children;

  @override
  Widget build(BuildContext context) {
    if (loading) {
      return const Padding(
        padding: EdgeInsets.all(18),
        child: Center(child: CircularProgressIndicator()),
      );
    }
    if (children.isEmpty) {
      return Padding(
        padding: const EdgeInsets.all(14),
        child: Text(
          emptyLabel,
          textAlign: TextAlign.center,
          style: TextStyle(
            color: airmiusMutedColor(context),
            fontWeight: FontWeight.w700,
          ),
        ),
      );
    }
    return Column(
      children: [
        for (var index = 0; index < children.length; index++) ...[
          children[index],
          if (index != children.length - 1) const SizedBox(height: 8),
        ],
      ],
    );
  }
}

int _asInt(Object? value) {
  if (value is int) return value;
  if (value is num) return value.toInt();
  return int.tryParse('$value') ?? 0;
}
