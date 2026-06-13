import 'package:flutter/material.dart';

import '../core/airmius_services_scope.dart';
import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'ui_action_result_screen.dart';

class NewConversationScreen extends StatefulWidget {
  const NewConversationScreen({super.key});

  @override
  State<NewConversationScreen> createState() => _NewConversationScreenState();
}

class _NewConversationScreenState extends State<NewConversationScreen> {
  final TextEditingController _messageController = TextEditingController();
  String _type = 'direct';
  int? _selectedUserId;
  int? _selectedTeamId;

  bool get _canStart {
    if (_type == 'team') return _selectedTeamId != null;
    if (_type == 'direct') return _selectedUserId != null;
    return _selectedUserId != null;
  }

  @override
  void dispose() {
    _messageController.dispose();
    super.dispose();
  }

  void _setType(String type) {
    setState(() {
      _type = type;
      _selectedUserId = null;
      _selectedTeamId = null;
    });
  }

  void _startConversation() {
    if (!_canStart) return;
    Navigator.push(
      context,
      MaterialPageRoute(
        builder: (_) => UiActionResultScreen(
          title: 'Chat starten',
          body: 'Konversation erstellen, Teilnehmer pruefen und erste Nachricht senden.',
          status: 'Chat',
          icon: Icons.chat_bubble_outline,
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final authUser = AirmiusServicesScope.of(context).authState.user;
    final userName = authUser?.name ?? 'Amin Masri';
    final userEmail = authUser?.email ?? 'amin.masri@outlook.com';
    final userInitials = initialsFromName(userName, fallback: 'AM');
    final userId = authUser?.id ?? 1;

    return Scaffold(
      backgroundColor: AirmiusColors.bg,
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(14),
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 420),
              child: Container(
                decoration: BoxDecoration(
                  color: AirmiusColors.card,
                  borderRadius: BorderRadius.circular(8),
                  border: Border.all(color: AirmiusColors.border),
                  boxShadow: const [
                    BoxShadow(color: Color(0x66000000), blurRadius: 28, offset: Offset(0, 16)),
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
                          const Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text('Neue Konversation', style: TextStyle(color: AirmiusColors.text, fontSize: 18, fontWeight: FontWeight.w900)),
                                SizedBox(height: 6),
                                Text('Für Gruppen mindestens zwei Personen auswählen.', style: TextStyle(color: AirmiusColors.muted, fontSize: 14, height: 1.25)),
                              ],
                            ),
                          ),
                          IconButton(
                            tooltip: 'Schliessen',
                            onPressed: () => Navigator.pop(context),
                            icon: const Icon(Icons.close, color: AirmiusColors.muted, size: 26),
                          ),
                        ],
                      ),
                    ),
                    Container(height: 1, color: AirmiusColors.border),
                    Container(
                      color: AirmiusColors.input,
                      padding: const EdgeInsets.all(8),
                      child: Row(
                        children: [
                          _ConversationTypeTab(label: 'Direkt', active: _type == 'direct', onTap: () => _setType('direct')),
                          _ConversationTypeTab(label: 'Gruppe', active: _type == 'group', onTap: () => _setType('group')),
                          _ConversationTypeTab(label: 'Team', active: _type == 'team', onTap: () => _setType('team')),
                        ],
                      ),
                    ),
                    if (_type == 'team')
                      Padding(
                        padding: const EdgeInsets.all(12),
                        child: _TeamSelectCard(
                          selected: _selectedTeamId == 1,
                          onTap: () => setState(() => _selectedTeamId = _selectedTeamId == 1 ? null : 1),
                        ),
                      )
                    else
                      Padding(
                        padding: const EdgeInsets.fromLTRB(8, 8, 8, 6),
                        child: _ParticipantCard(
                          initials: userInitials,
                          name: userName,
                          email: userEmail,
                          selected: _selectedUserId == userId,
                          onTap: () => setState(() => _selectedUserId = _selectedUserId == userId ? null : userId),
                        ),
                      ),
                    Container(height: 1, color: AirmiusColors.border),
                    Padding(
                      padding: const EdgeInsets.all(12),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: [
                          TextField(
                            controller: _messageController,
                            minLines: 2,
                            maxLines: 3,
                            style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w700),
                            decoration: InputDecoration(
                              hintText: 'Erste Nachricht optional',
                              filled: true,
                              fillColor: AirmiusColors.input,
                              contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
                              enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(8), borderSide: const BorderSide(color: AirmiusColors.border)),
                              focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(8), borderSide: const BorderSide(color: AirmiusColors.blue)),
                            ),
                          ),
                          const SizedBox(height: 12),
                          Row(
                            children: [
                              Expanded(
                                child: OutlinedButton(
                                  onPressed: () => Navigator.pop(context),
                                  style: OutlinedButton.styleFrom(
                                    foregroundColor: AirmiusColors.muted,
                                    side: const BorderSide(color: AirmiusColors.border),
                                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                                    padding: const EdgeInsets.symmetric(vertical: 13),
                                  ),
                                  child: const Text('Abbrechen', style: TextStyle(fontWeight: FontWeight.w800)),
                                ),
                              ),
                              const SizedBox(width: 8),
                              Expanded(
                                child: FilledButton(
                                  onPressed: _canStart ? _startConversation : null,
                                  style: FilledButton.styleFrom(
                                    backgroundColor: AirmiusColors.text,
                                    foregroundColor: AirmiusColors.header,
                                    disabledBackgroundColor: AirmiusColors.muted,
                                    disabledForegroundColor: AirmiusColors.header,
                                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                                    padding: const EdgeInsets.symmetric(vertical: 13),
                                  ),
                                  child: const Text('Chat starten', style: TextStyle(fontWeight: FontWeight.w900)),
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
  const _ConversationTypeTab({required this.label, required this.active, required this.onTap});

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
            color: active ? AirmiusColors.card : Colors.transparent,
            borderRadius: BorderRadius.circular(6),
            boxShadow: active ? const [BoxShadow(color: Color(0x33000000), blurRadius: 8, offset: Offset(0, 3))] : null,
          ),
          child: Text(label, style: TextStyle(color: active ? AirmiusColors.text : AirmiusColors.muted, fontSize: 14, fontWeight: FontWeight.w800)),
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
          color: selected ? AirmiusColors.input : AirmiusColors.card,
          borderRadius: BorderRadius.circular(8),
          border: Border.all(color: selected ? AirmiusColors.blue : AirmiusColors.border),
        ),
        child: Row(
          children: [
            Container(
              width: 40,
              height: 40,
              alignment: Alignment.center,
              decoration: BoxDecoration(color: AirmiusColors.text, borderRadius: BorderRadius.circular(8)),
              child: Text(initials, style: const TextStyle(color: AirmiusColors.header, fontWeight: FontWeight.w900)),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(name, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(color: AirmiusColors.text, fontSize: 14, fontWeight: FontWeight.w900)),
                  const SizedBox(height: 3),
                  Text(email, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(color: AirmiusColors.muted, fontSize: 12, fontWeight: FontWeight.w700)),
                ],
              ),
            ),
            Icon(selected ? Icons.check_circle : Icons.circle_outlined, color: selected ? AirmiusColors.green : AirmiusColors.muted, size: 22),
          ],
        ),
      ),
    );
  }
}

class _TeamSelectCard extends StatelessWidget {
  const _TeamSelectCard({required this.selected, required this.onTap});

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
          color: AirmiusColors.input,
          borderRadius: BorderRadius.circular(8),
          border: Border.all(color: selected ? AirmiusColors.blue : AirmiusColors.border),
        ),
        child: Row(
          children: [
            Expanded(child: Text(selected ? 'Team ausgewählt' : 'Team auswählen', style: const TextStyle(color: AirmiusColors.text, fontSize: 14, fontWeight: FontWeight.w800))),
            Icon(selected ? Icons.check_circle : Icons.keyboard_arrow_down, color: selected ? AirmiusColors.green : AirmiusColors.muted),
          ],
        ),
      ),
    );
  }
}
