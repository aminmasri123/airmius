import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'notification_chat_operations_screen.dart';
import 'system_admin_operations_screen.dart';

class AdminMailCenterScreen extends StatefulWidget {
  const AdminMailCenterScreen({super.key});

  @override
  State<AdminMailCenterScreen> createState() => _AdminMailCenterScreenState();
}

class _AdminMailCenterScreenState extends State<AdminMailCenterScreen> {
  String _type = 'System';
  bool _includeEmail = true;
  bool _includePush = true;
  bool _includeInApp = true;
  bool _requireApproval = true;

  final _subject = TextEditingController(text: 'Willkommen bei Airmius');
  final _body = TextEditingController(text: 'Diese Nachricht wird als Systemmail, Push oder In-App-Mitteilung vorbereitet.');

  final List<_MailItem> _items = const [
    _MailItem(title: 'Willkommensmail', body: 'Neue User erhalten Hinweise zu Profil, Vereinen, Datenschutz und App-Start.', status: 'Template', channel: 'E-Mail', icon: Icons.mark_email_read_outlined, color: AirmiusColors.blue),
    _MailItem(title: 'Mitgliedsanfrage Update', body: 'Status, Rückfrage, Annahme, Ablehnung oder Rückzug einer Vereinsanfrage.', status: 'Transaktional', channel: 'In-App', icon: Icons.assignment_turned_in_outlined, color: AirmiusColors.green),
    _MailItem(title: 'Zahlungshinweis', body: 'Beitrag, Intervall, Zahlmethode, offene Zahlung oder Überweisungshinweis.', status: 'Finanzen', channel: 'E-Mail', icon: Icons.payments_outlined, color: AirmiusColors.amber),
    _MailItem(title: 'Sicherheitswarnung', body: 'Login, Passwort, 2FA, Datenschutzanfrage oder verdächtige Aktivität.', status: 'Sicherheit', channel: 'Push', icon: Icons.security_outlined, color: AirmiusColors.red),
  ];

  @override
  void dispose() {
    _subject.dispose();
    _body.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AirmiusColors.bg,
      body: SafeArea(
        child: CustomScrollView(
          slivers: [
            SliverToBoxAdapter(
              child: Padding(
                padding: const EdgeInsets.fromLTRB(16, 14, 16, 18),
                child: Center(
                  child: ConstrainedBox(
                    constraints: const BoxConstraints(maxWidth: 760),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        const PageTitle(title: 'Admin Mail Center', subtitle: 'Systemmails, Templates, Kampagnen, Transaktionsmails, Push und In-App-Mitteilungen.'),
                        const SizedBox(height: 16),
                        _MailHero(onSend: _send),
                        const SizedBox(height: 16),
                        _ChoicePanel(title: 'Nachrichtentyp', value: _type, values: const ['System', 'Transaktional', 'Kampagne', 'Sicherheit', 'Finanzen'], onChanged: (value) => setState(() => _type = value)),
                        const SizedBox(height: 12),
                        AirmiusPanel(
                          title: 'Nachricht erstellen',
                          child: Column(
                            children: [
                              AirmiusTextField(label: 'Betreff', controller: _subject),
                              const SizedBox(height: 10),
                              AirmiusTextField(label: 'Inhalt', controller: _body),
                            ],
                          ),
                        ),
                        const SizedBox(height: 16),
                        AirmiusPanel(
                          title: 'Kanaele & Freigaben',
                          child: Column(
                            children: [
                              _SwitchRow(title: 'E-Mail senden', subtitle: 'SMTP/Provider-API später über Laravel anbinden.', value: _includeEmail, onChanged: (value) => setState(() => _includeEmail = value)),
                              _SwitchRow(title: 'Push senden', subtitle: 'Mobile Push-Nachrichten für wichtige Updates vorbereiten.', value: _includePush, onChanged: (value) => setState(() => _includePush = value)),
                              _SwitchRow(title: 'In-App anzeigen', subtitle: 'Benachrichtigung im Airmius Notification Center anzeigen.', value: _includeInApp, onChanged: (value) => setState(() => _includeInApp = value)),
                              _SwitchRow(title: 'Adminfreigabe erforderlich', subtitle: 'Kampagnen und sensible Nachrichten brauchen Freigabe.', value: _requireApproval, onChanged: (value) => setState(() => _requireApproval = value)),
                            ],
                          ),
                        ),
                        const SizedBox(height: 16),
                        for (final item in _items) ...[
                          _MailCard(item: item, onOpen: () => _toast('${item.title}: Template vorbereitet')),
                          const SizedBox(height: 12),
                        ],
                        AirmiusPanel(
                          title: 'Admin-Aktionen',
                          child: Wrap(
                            spacing: 10,
                            runSpacing: 10,
                            children: [
                              AirmiusButton(label: 'Senden', icon: Icons.send_outlined, onPressed: _send),
                              AirmiusButton(label: 'Chat/Push', icon: Icons.forum_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => NotificationChatOperationsScreen(initialTab: 'Push')))),
                              AirmiusButton(label: 'System Admin', icon: Icons.admin_panel_settings_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => SystemAdminOperationsScreen()))),
                            ],
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  void _send() {
    _toast('Admin-Mail vorbereiten: $_type');
  }

  void _toast(String message) {
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(message)));
  }
}

class _MailHero extends StatelessWidget {
  const _MailHero({required this.onSend});

  final VoidCallback onSend;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        gradient: const LinearGradient(colors: [Color(0xFF10243B), Color(0xFF0B111B)], begin: Alignment.topLeft, end: Alignment.bottomRight),
        borderRadius: BorderRadius.circular(22),
        border: Border.all(color: AirmiusColors.borderStrong),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              const AirmiusLogo(size: 42),
              const SizedBox(width: 12),
              const Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Eyebrow('MAIL CENTER'), SizedBox(height: 4), Text('Plattformnachrichten steuern', style: TextStyle(color: AirmiusColors.text, fontSize: 22, fontWeight: FontWeight.w900))])),
              AirmiusButton(label: 'Senden', icon: Icons.send_outlined, onPressed: onSend),
            ],
          ),
          const SizedBox(height: 14),
          const Text('Admins können Systemmails, Transaktionsmails, Kampagnen, Pushes und In-App-Mitteilungen als mobile UI vorbereiten.', style: TextStyle(color: AirmiusColors.muted, height: 1.45, fontWeight: FontWeight.w700)),
          const SizedBox(height: 16),
          const Row(children: [Expanded(child: MetricCard(value: '4', label: 'Templates')), SizedBox(width: 10), Expanded(child: MetricCard(value: '3', label: 'Kanaele')), SizedBox(width: 10), Expanded(child: MetricCard(value: '1', label: 'Freigabe'))]),
        ],
      ),
    );
  }
}

class _ChoicePanel extends StatelessWidget {
  const _ChoicePanel({required this.title, required this.value, required this.values, required this.onChanged});

  final String title;
  final String value;
  final List<String> values;
  final ValueChanged<String> onChanged;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      title: title,
      child: Wrap(
        spacing: 8,
        runSpacing: 8,
        children: [
          for (final item in values)
            ChoiceChip(
              label: Text(item),
              selected: value == item,
              onSelected: (_) => onChanged(item),
              selectedColor: AirmiusColors.blue.withValues(alpha: .24),
              backgroundColor: AirmiusColors.card,
              labelStyle: TextStyle(color: value == item ? AirmiusColors.text : AirmiusColors.muted, fontWeight: FontWeight.w900),
              side: BorderSide(color: value == item ? AirmiusColors.blue : AirmiusColors.border),
            ),
        ],
      ),
    );
  }
}

class _SwitchRow extends StatelessWidget {
  const _SwitchRow({required this.title, required this.subtitle, required this.value, required this.onChanged});

  final String title;
  final String subtitle;
  final bool value;
  final ValueChanged<bool> onChanged;

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(color: AirmiusColors.input, borderRadius: BorderRadius.circular(16), border: Border.all(color: AirmiusColors.border)),
      child: Row(children: [
        Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text(title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), const SizedBox(height: 4), Text(subtitle, style: const TextStyle(color: AirmiusColors.muted, fontSize: 12, height: 1.35, fontWeight: FontWeight.w700))])),
        Switch.adaptive(value: value, onChanged: onChanged, activeThumbColor: AirmiusColors.blue),
      ]),
    );
  }
}

class _MailCard extends StatelessWidget {
  const _MailCard({required this.item, required this.onOpen});

  final _MailItem item;
  final VoidCallback onOpen;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      title: item.title,
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(width: 48, height: 48, decoration: BoxDecoration(color: item.color.withValues(alpha: .18), borderRadius: BorderRadius.circular(16), border: Border.all(color: item.color.withValues(alpha: .5))), child: Icon(item.icon, color: item.color)),
          const SizedBox(width: 12),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [StatusPill(item.status, color: item.color), const SizedBox(height: 8), Text(item.channel, style: const TextStyle(color: AirmiusColors.blue, fontWeight: FontWeight.w900)), const SizedBox(height: 6), Text(item.body, style: const TextStyle(color: AirmiusColors.muted, height: 1.45, fontWeight: FontWeight.w700))])),
          IconButton(onPressed: onOpen, icon: const Icon(Icons.chevron_right, color: AirmiusColors.muted)),
        ],
      ),
    );
  }
}

class _MailItem {
  const _MailItem({required this.title, required this.body, required this.status, required this.channel, required this.icon, required this.color});

  final String title;
  final String body;
  final String status;
  final String channel;
  final IconData icon;
  final Color color;
}
