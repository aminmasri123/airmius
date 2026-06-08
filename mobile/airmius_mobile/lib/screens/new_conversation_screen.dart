import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'ui_action_result_screen.dart';

class NewConversationScreen extends StatefulWidget {
  const NewConversationScreen({super.key});

  @override
  State<NewConversationScreen> createState() => _NewConversationScreenState();
}

class _NewConversationScreenState extends State<NewConversationScreen> {
  String _kind = 'Direkt';
  bool _eventLink = false;
  bool _teamVisible = true;
  bool _attachments = true;
  bool _allowReactions = true;
  bool _ownerApproval = false;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(backgroundColor: AirmiusColors.header, surfaceTintColor: Colors.transparent, title: const Text('Neue Konversation', style: TextStyle(fontWeight: FontWeight.w900))),
      body: PageFrame(
        title: 'Neue Konversation',
        subtitle: 'Direktnachricht, Teamchat, Vereinsadmin oder Eventchat starten',
        trailing: const StatusPill('Chat'),
        child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          AirmiusPanel(gradient: true, child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            const Eyebrow('Chat-Typ'),
            const SizedBox(height: 10),
            Wrap(spacing: 8, runSpacing: 8, children: [
              for (final item in const ['Direkt', 'Team', 'Verein', 'Event', 'Support'])
                ChoiceChip(
                  selected: _kind == item,
                  label: Text(item),
                  onSelected: (_) => setState(() => _kind = item),
                  selectedColor: AirmiusColors.blue.withValues(alpha: 0.22),
                  backgroundColor: AirmiusColors.cardSoft,
                  side: BorderSide(color: _kind == item ? AirmiusColors.blue : AirmiusColors.border),
                  labelStyle: TextStyle(color: _kind == item ? AirmiusColors.blue : AirmiusColors.muted, fontWeight: FontWeight.w900),
                ),
            ]),
            const SizedBox(height: 12),
            const AirmiusTextField(label: 'Empfaenger / Gruppe', hint: 'Name, Team, Verein oder Event suchen', icon: Icons.search_outlined),
            const SizedBox(height: 10),
            const AirmiusTextField(label: 'Erste Nachricht', hint: 'Nachricht schreiben...', icon: Icons.chat_bubble_outline, maxLines: 4),
          ])),
          const SizedBox(height: 14),
          Row(children: const [Expanded(child: MetricCard(value: '5', label: 'Typen')), SizedBox(width: 10), Expanded(child: MetricCard(value: '3', label: 'Rechte')), SizedBox(width: 10), Expanded(child: MetricCard(value: 'API', label: 'Spaeter'))]),
          const SizedBox(height: 14),
          AirmiusPanel(child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            const Eyebrow('Chat-Regeln'),
            const SizedBox(height: 8),
            SwitchListTile(value: _teamVisible, onChanged: (value) => setState(() => _teamVisible = value), activeColor: AirmiusColors.blue, contentPadding: EdgeInsets.zero, title: const Text('Fuer berechtigte Mitglieder sichtbar', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), subtitle: const Text('Team-, Vereins- und Eventrechte werden spaeter per API geprueft.', style: TextStyle(color: AirmiusColors.muted))),
            SwitchListTile(value: _eventLink, onChanged: (value) => setState(() => _eventLink = value), activeColor: AirmiusColors.blue, contentPadding: EdgeInsets.zero, title: const Text('Mit Event verknuepfen', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), subtitle: const Text('Chat erscheint direkt am Eventdetail.', style: TextStyle(color: AirmiusColors.muted))),
            SwitchListTile(value: _attachments, onChanged: (value) => setState(() => _attachments = value), activeColor: AirmiusColors.blue, contentPadding: EdgeInsets.zero, title: const Text('Dateianhaenge erlauben', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), subtitle: const Text('Uploads landen spaeter im Dateimanager-Kontext.', style: TextStyle(color: AirmiusColors.muted))),
            SwitchListTile(value: _allowReactions, onChanged: (value) => setState(() => _allowReactions = value), activeColor: AirmiusColors.blue, contentPadding: EdgeInsets.zero, title: const Text('Reaktionen erlauben', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), subtitle: const Text('Emoji-Reaktionen werden je Nachricht gespeichert.', style: TextStyle(color: AirmiusColors.muted))),
            SwitchListTile(value: _ownerApproval, onChanged: (value) => setState(() => _ownerApproval = value), activeColor: AirmiusColors.blue, contentPadding: EdgeInsets.zero, title: const Text('Neue Mitglieder nur mit Owner-Freigabe', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), subtitle: const Text('Einladungen muessen vor dem Beitritt bestaetigt werden.', style: TextStyle(color: AirmiusColors.muted))),
          ])),
          const SizedBox(height: 14),
          AirmiusPanel(borderColor: AirmiusColors.amber.withValues(alpha: 0.55), child: const Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Eyebrow('Sicherheit'),
            SizedBox(height: 8),
            Text('Blocklisten, Melden-Funktion, Jugendschutz und Teilnehmerrechte werden beim Erstellen gegen Laravel geprueft.', style: TextStyle(color: AirmiusColors.muted, height: 1.35)),
            SizedBox(height: 10),
            Wrap(spacing: 8, runSpacing: 8, children: [StatusPill('Blocklist'), StatusPill('Guardian'), StatusPill('Moderation')]),
          ])),
          const SizedBox(height: 14),
          AirmiusButton(label: 'Konversation starten', icon: Icons.send_outlined, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => UiActionResultScreen(title: 'Konversation starten', body: 'Chat erstellen, Empfaengerrechte pruefen, erste Nachricht senden und Inbox-Zustand vorbereiten.', status: 'Chat', icon: Icons.send_outlined)))),
        ]),
      ),
    );
  }
}
