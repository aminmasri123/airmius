import 'package:flutter/material.dart';

import '../core/api_contract.dart';
import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

class NotificationChatOperationsScreen extends StatefulWidget {
  const NotificationChatOperationsScreen({super.key, this.initialTab = 'Inbox'});

  final String initialTab;

  @override
  State<NotificationChatOperationsScreen> createState() => _NotificationChatOperationsScreenState();
}

class _NotificationChatOperationsScreenState extends State<NotificationChatOperationsScreen> {
  String _tab = 'Inbox';
  bool _push = true;
  bool _email = true;
  bool _quietHours = false;

  @override
  void initState() {
    super.initState();
    if (_tabs.contains(widget.initialTab)) _tab = widget.initialTab;
  }

  @override
  Widget build(BuildContext context) {
    final items = _tab == 'Alle' ? _operations : _operations.where((item) => item.tab == _tab).toList();
    return Scaffold(
      backgroundColor: AirmiusColors.bg,
      appBar: AppBar(backgroundColor: AirmiusColors.header, surfaceTintColor: Colors.transparent, title: const Text('Inbox & Chat Ops', style: TextStyle(fontWeight: FontWeight.w900))),
      body: PageFrame(
        title: 'Inbox & Chat Ops',
        subtitle: 'Benachrichtigungen, Push-Regeln, Chats, Typing und Einladungen',
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            AirmiusPanel(
              borderColor: AirmiusColors.blue.withValues(alpha: .42),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Eyebrow('Kommunikation'),
                  const SizedBox(height: 8),
                  const Text('Diese UI buendelt alles, was später für Push, Inbox, Chat und Team-/Vereinskommunikation an Laravel angebunden wird.', style: TextStyle(color: AirmiusColors.muted, height: 1.4)),
                  const SizedBox(height: 12),
                  SwitchListTile(value: _push, onChanged: (value) => setState(() => _push = value), activeColor: AirmiusColors.blue, contentPadding: EdgeInsets.zero, title: const Text('Push aktiv', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), subtitle: const Text('Native Push-Berechtigung und Server-Preference.', style: TextStyle(color: AirmiusColors.muted))),
                  SwitchListTile(value: _email, onChanged: (value) => setState(() => _email = value), activeColor: AirmiusColors.green, contentPadding: EdgeInsets.zero, title: const Text('E-Mail Hinweise', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), subtitle: const Text('Wichtige Vereins-, Zahlungs- und Sicherheitsupdates.', style: TextStyle(color: AirmiusColors.muted))),
                  SwitchListTile(value: _quietHours, onChanged: (value) => setState(() => _quietHours = value), activeColor: AirmiusColors.amber, contentPadding: EdgeInsets.zero, title: const Text('Ruhezeiten', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), subtitle: const Text('Nicht dringende Hinweise später gesammelt senden.', style: TextStyle(color: AirmiusColors.muted))),
                  const SizedBox(height: 10),
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: [
                      for (final tab in _tabs)
                        ChoiceChip(
                          label: Text(tab),
                          selected: _tab == tab,
                          onSelected: (_) => setState(() => _tab = tab),
                          selectedColor: AirmiusColors.blue.withValues(alpha: .24),
                          backgroundColor: AirmiusColors.panelSoft,
                          side: BorderSide(color: _tab == tab ? AirmiusColors.blue : AirmiusColors.border),
                          labelStyle: TextStyle(color: _tab == tab ? AirmiusColors.text : AirmiusColors.muted, fontWeight: FontWeight.w900),
                        ),
                    ],
                  ),
                ],
              ),
            ),
            const SizedBox(height: 16),
            for (final item in items) ...[
              _OperationLine(item: item),
              const SizedBox(height: 12),
            ],
          ],
        ),
      ),
    );
  }
}

class _OperationLine extends StatelessWidget {
  const _OperationLine({required this.item});

  final _CommunicationOperation item;

  @override
  Widget build(BuildContext context) => AirmiusPanel(
        borderColor: item.danger ? AirmiusColors.red.withValues(alpha: .45) : AirmiusColors.border,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Container(width: 46, height: 46, decoration: BoxDecoration(color: item.color.withValues(alpha: .12), borderRadius: BorderRadius.circular(15), border: Border.all(color: item.color.withValues(alpha: .45))), child: Icon(item.icon, color: item.color)),
                const SizedBox(width: 12),
                Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text(item.title, style: const TextStyle(color: AirmiusColors.text, fontSize: 16, fontWeight: FontWeight.w900)), const SizedBox(height: 5), Text(item.body, style: const TextStyle(color: AirmiusColors.muted, height: 1.35))])),
                StatusPill(item.tab, color: item.color),
              ],
            ),
            const SizedBox(height: 12),
            Container(width: double.infinity, padding: const EdgeInsets.all(12), decoration: BoxDecoration(color: AirmiusColors.bg, borderRadius: BorderRadius.circular(14), border: Border.all(color: AirmiusColors.border)), child: Text('${item.method} ${item.endpoint}', style: const TextStyle(color: AirmiusColors.green, fontWeight: FontWeight.w900, fontSize: 12))),
            const SizedBox(height: 12),
            Wrap(spacing: 8, runSpacing: 8, children: [
              AirmiusButton(label: item.action, icon: item.icon, danger: item.danger, onPressed: () => openUiAction(context, title: item.title, body: '${item.body}\n\nEndpoint: ${item.method} ${item.endpoint}', status: item.tab, icon: item.icon)),
              AirmiusButton(label: 'Kontext', icon: Icons.manage_search_outlined, secondary: true, onPressed: () => openUiAction(context, title: '${item.title} Kontext', body: 'Payload, Deep Link, Lesestatus, Push-Preference, Audit und spätere Laravel-Response anzeigen.', status: 'Kontext', icon: Icons.manage_search_outlined)),
            ]),
          ],
        ),
      );
}

class _CommunicationOperation {
  const _CommunicationOperation({required this.tab, required this.title, required this.body, required this.method, required this.endpoint, required this.icon, required this.action, required this.color, this.danger = false});
  final String tab;
  final String title;
  final String body;
  final String method;
  final String endpoint;
  final IconData icon;
  final String action;
  final Color color;
  final bool danger;
}

const _tabs = ['Inbox', 'Push', 'Chat', 'Einladungen', 'Moderation', 'Alle'];

final _operations = <_CommunicationOperation>[
  _CommunicationOperation(tab: 'Inbox', title: 'Benachrichtigungen laden', body: 'Inbox mit Typ, Deep Link, Lesestatus und Zeitstempel laden.', method: 'GET', endpoint: ApiContract.notifications, icon: Icons.notifications_outlined, action: 'Inbox laden', color: AirmiusColors.blue),
  _CommunicationOperation(tab: 'Inbox', title: 'Alle als gelesen', body: 'Alle Inbox-Eintraege als gelesen markieren und Badge zaehler resetten.', method: 'POST', endpoint: ApiContract.notificationsReadAll(), icon: Icons.done_all_outlined, action: 'Alles lesen', color: AirmiusColors.green),
  _CommunicationOperation(tab: 'Inbox', title: 'Eine Nachricht lesen', body: 'Einzelne Benachrichtigung als gelesen markieren.', method: 'POST', endpoint: ApiContract.notificationRead('id'), icon: Icons.mark_email_read_outlined, action: 'Lesen', color: AirmiusColors.green),
  _CommunicationOperation(tab: 'Inbox', title: 'Benachrichtigung löschen', body: 'Eintrag aus Inbox entfernen und optional Undo vorbereiten.', method: 'DELETE', endpoint: ApiContract.notification('id'), icon: Icons.delete_outline, action: 'Löschen', color: AirmiusColors.red, danger: true),
  _CommunicationOperation(tab: 'Push', title: 'Push-Preferences speichern', body: 'Push, E-Mail, Ruhezeiten, Kanalgruppen und Sprache speichern.', method: 'PUT', endpoint: ApiContract.notificationPreferences, icon: Icons.tune_outlined, action: 'Speichern', color: AirmiusColors.amber),
  _CommunicationOperation(tab: 'Chat', title: 'Konversationen laden', body: 'Chats mit Teilnehmern, ungelesenen Nachrichten und Kontext laden.', method: 'GET', endpoint: ApiContract.conversations, icon: Icons.chat_bubble_outline, action: 'Chats laden', color: AirmiusColors.blue),
  _CommunicationOperation(tab: 'Chat', title: 'Nachrichten laden', body: 'Nachrichten einer Konversation mit Attachments und Reaktionen laden.', method: 'GET', endpoint: ApiContract.conversationMessages(1), icon: Icons.forum_outlined, action: 'Nachrichten', color: AirmiusColors.blue),
  _CommunicationOperation(tab: 'Chat', title: 'Nachricht senden', body: 'Text, Datei, Medienanhang oder Systemhinweis senden.', method: 'POST', endpoint: ApiContract.conversationMessages(1), icon: Icons.send_outlined, action: 'Senden', color: AirmiusColors.green),
  _CommunicationOperation(tab: 'Chat', title: 'Typing senden', body: 'Live-Typing für Konversation an Server melden.', method: 'POST', endpoint: ApiContract.conversationTyping(1), icon: Icons.keyboard_outlined, action: 'Typing', color: AirmiusColors.green),
  _CommunicationOperation(tab: 'Chat', title: 'Chat stummschalten', body: 'Push für diese Konversation pausieren oder wieder aktivieren.', method: 'POST', endpoint: ApiContract.conversationMute(1), icon: Icons.notifications_off_outlined, action: 'Mute', color: AirmiusColors.amber),
  _CommunicationOperation(tab: 'Chat', title: 'Konversation verlassen', body: 'Chat verlassen, Rollen prüfen und andere Teilnehmer informieren.', method: 'DELETE', endpoint: ApiContract.conversationLeave(1), icon: Icons.logout_outlined, action: 'Verlassen', color: AirmiusColors.red, danger: true),
  _CommunicationOperation(tab: 'Einladungen', title: 'Chat-Einladung annehmen', body: 'Einladung annehmen und Konversation freischalten.', method: 'POST', endpoint: ApiContract.conversationInvitationAccept(1), icon: Icons.check_circle_outline, action: 'Annehmen', color: AirmiusColors.green),
  _CommunicationOperation(tab: 'Einladungen', title: 'Chat-Einladung ablehnen', body: 'Einladung ablehnen und Absender informieren.', method: 'DELETE', endpoint: ApiContract.conversationInvitationDecline(1), icon: Icons.cancel_outlined, action: 'Ablehnen', color: AirmiusColors.red, danger: true),
  _CommunicationOperation(tab: 'Moderation', title: 'Mitglied aus Chat entfernen', body: 'Teilnehmer entfernen, Rechte entziehen und Audit speichern.', method: 'DELETE', endpoint: ApiContract.conversationMember(1, 1), icon: Icons.person_remove_outlined, action: 'Entfernen', color: AirmiusColors.red, danger: true),
  _CommunicationOperation(tab: 'Moderation', title: 'Besitzer wechseln', body: 'Owner oder Moderatorrolle einer Konversation neu setzen.', method: 'PUT', endpoint: ApiContract.conversationOwner(1), icon: Icons.admin_panel_settings_outlined, action: 'Owner setzen', color: AirmiusColors.amber),
];
