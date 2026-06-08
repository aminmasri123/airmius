import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'data_rights_request_screen.dart';
import 'notification_chat_operations_screen.dart';

class SupportHelpdeskScreen extends StatefulWidget {
  const SupportHelpdeskScreen({super.key});

  @override
  State<SupportHelpdeskScreen> createState() => _SupportHelpdeskScreenState();
}

class _SupportHelpdeskScreenState extends State<SupportHelpdeskScreen> {
  String _category = 'Technik';
  String _priority = 'Normal';
  bool _includeDevice = true;
  bool _includeScreenshot = false;
  bool _notifyByChat = true;

  final _subject = TextEditingController(text: 'Problem mit Mitgliedsanfrage');
  final _message = TextEditingController(text: 'Ich brauche Hilfe beim Vereinsbeitritt oder beim Zurueckziehen einer Anfrage.');

  final List<_TicketItem> _tickets = const [
    _TicketItem(title: 'Mitgliedsanfrage haengt', body: 'User sieht Anfrage gesendet, aber keine weiteren Details.', status: 'Offen', owner: 'Support', icon: Icons.assignment_turned_in_outlined, color: AirmiusColors.blue),
    _TicketItem(title: 'Dokument kann nicht geladen werden', body: 'Vereinsdokument ist verknuepft, Upload oder Vorschau fehlt.', status: 'In Pruefung', owner: 'Dateien', icon: Icons.folder_copy_outlined, color: AirmiusColors.green),
    _TicketItem(title: 'Zahlungsintervall unklar', body: 'Verein moechte monatlich, 4 Monate, 6 Monate oder jaehrlich anbieten.', status: 'Rueckfrage', owner: 'Finanzen', icon: Icons.payments_outlined, color: AirmiusColors.amber),
  ];

  @override
  void dispose() {
    _subject.dispose();
    _message.dispose();
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
                        const PageTitle(title: 'Support & Helpdesk', subtitle: 'Tickets, Rueckfragen, Fehler, Vereinsanliegen, Prioritaet, Geraetedaten und Supportchat.'),
                        const SizedBox(height: 16),
                        _SupportHero(onSubmit: _submit),
                        const SizedBox(height: 16),
                        _ChoicePanel(title: 'Kategorie', value: _category, values: const ['Technik', 'Verein', 'Mitgliedschaft', 'Zahlung', 'Datenschutz'], onChanged: (value) => setState(() => _category = value)),
                        const SizedBox(height: 12),
                        _ChoicePanel(title: 'Prioritaet', value: _priority, values: const ['Niedrig', 'Normal', 'Hoch', 'Dringend'], onChanged: (value) => setState(() => _priority = value)),
                        const SizedBox(height: 12),
                        AirmiusPanel(
                          title: 'Ticket erstellen',
                          child: Column(
                            children: [
                              AirmiusTextField(label: 'Betreff', controller: _subject),
                              const SizedBox(height: 10),
                              AirmiusTextField(label: 'Nachricht', controller: _message),
                            ],
                          ),
                        ),
                        const SizedBox(height: 16),
                        AirmiusPanel(
                          title: 'Support-Optionen',
                          child: Column(
                            children: [
                              _SwitchRow(title: 'Geraetedaten mitsenden', subtitle: 'Plattform, App-Version und technische Hinweise fuer Diagnose.', value: _includeDevice, onChanged: (value) => setState(() => _includeDevice = value)),
                              _SwitchRow(title: 'Screenshot anhaengen', subtitle: 'Screenshot-Upload ist als UI fuer spaetere API vorbereitet.', value: _includeScreenshot, onChanged: (value) => setState(() => _includeScreenshot = value)),
                              _SwitchRow(title: 'Antwort per Chat', subtitle: 'Support-Rueckfragen sollen im Airmius Chat erscheinen.', value: _notifyByChat, onChanged: (value) => setState(() => _notifyByChat = value)),
                            ],
                          ),
                        ),
                        const SizedBox(height: 16),
                        for (final ticket in _tickets) ...[
                          _TicketCard(ticket: ticket, onOpen: () => _toast('${ticket.title}: Ticketdetail vorbereitet')),
                          const SizedBox(height: 12),
                        ],
                        AirmiusPanel(
                          title: 'Verknuepfte Hilfe',
                          child: Wrap(
                            spacing: 10,
                            runSpacing: 10,
                            children: [
                              AirmiusButton(label: 'Ticket senden', icon: Icons.send_outlined, onPressed: _submit),
                              AirmiusButton(label: 'Supportchat', icon: Icons.forum_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => NotificationChatOperationsScreen(initialTab: 'Chat')))),
                              AirmiusButton(label: 'Datenrechte', icon: Icons.manage_search_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => DataRightsRequestScreen()))),
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

  void _submit() {
    _toast('Supportticket vorbereiten: $_category / $_priority');
  }

  void _toast(String message) {
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(message)));
  }
}

class _SupportHero extends StatelessWidget {
  const _SupportHero({required this.onSubmit});

  final VoidCallback onSubmit;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        gradient: const LinearGradient(colors: [Color(0xFF11243A), Color(0xFF0B111B)], begin: Alignment.topLeft, end: Alignment.bottomRight),
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
              const Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Eyebrow('HELPDESK'), SizedBox(height: 4), Text('Schnell Hilfe bekommen', style: TextStyle(color: AirmiusColors.text, fontSize: 22, fontWeight: FontWeight.w900))])),
              AirmiusButton(label: 'Senden', icon: Icons.send_outlined, onPressed: onSubmit),
            ],
          ),
          const SizedBox(height: 14),
          const Text('Support ist Teil der Plattform-UI: User, Vereinsadmins und Trainer koennen Fehler, Fragen und Rueckfragen strukturiert erfassen.', style: TextStyle(color: AirmiusColors.muted, height: 1.45, fontWeight: FontWeight.w700)),
          const SizedBox(height: 16),
          const Row(children: [Expanded(child: MetricCard(value: '5', label: 'Kategorien')), SizedBox(width: 10), Expanded(child: MetricCard(value: '3', label: 'Tickets')), SizedBox(width: 10), Expanded(child: MetricCard(value: '1', label: 'Chat'))]),
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
        Switch.adaptive(value: value, onChanged: onChanged, activeColor: AirmiusColors.blue),
      ]),
    );
  }
}

class _TicketCard extends StatelessWidget {
  const _TicketCard({required this.ticket, required this.onOpen});

  final _TicketItem ticket;
  final VoidCallback onOpen;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      title: ticket.title,
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(width: 48, height: 48, decoration: BoxDecoration(color: ticket.color.withValues(alpha: .18), borderRadius: BorderRadius.circular(16), border: Border.all(color: ticket.color.withValues(alpha: .5))), child: Icon(ticket.icon, color: ticket.color)),
          const SizedBox(width: 12),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [StatusPill(ticket.status, color: ticket.color), const SizedBox(height: 8), Text(ticket.owner, style: const TextStyle(color: AirmiusColors.blue, fontWeight: FontWeight.w900)), const SizedBox(height: 6), Text(ticket.body, style: const TextStyle(color: AirmiusColors.muted, height: 1.45, fontWeight: FontWeight.w700))])),
          IconButton(onPressed: onOpen, icon: const Icon(Icons.chevron_right, color: AirmiusColors.muted)),
        ],
      ),
    );
  }
}

class _TicketItem {
  const _TicketItem({required this.title, required this.body, required this.status, required this.owner, required this.icon, required this.color});

  final String title;
  final String body;
  final String status;
  final String owner;
  final IconData icon;
  final Color color;
}
