import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'clubs_screen.dart';
import 'membership_application_form_screen.dart';
import 'notification_chat_operations_screen.dart';

class MembershipRequestStatusScreen extends StatefulWidget {
  const MembershipRequestStatusScreen({super.key});

  @override
  State<MembershipRequestStatusScreen> createState() => _MembershipRequestStatusScreenState();
}

class _MembershipRequestStatusScreenState extends State<MembershipRequestStatusScreen> {
  bool _showPersonalData = true;
  bool _showPaymentData = true;
  bool _showDocuments = true;

  final List<_TimelineItem> _timeline = const [
    _TimelineItem(title: 'Anfrage gesendet', body: 'Dein Antrag wurde an ZBB übermittelt.', status: 'Erledigt', icon: Icons.send_outlined, color: AirmiusColors.green),
    _TimelineItem(title: 'Datenprüfung', body: 'Der Verein prüft Pflichtfelder, Dokumente und Zahlungsangaben.', status: 'Läuft', icon: Icons.manage_search_outlined, color: AirmiusColors.blue),
    _TimelineItem(title: 'Rückfrage möglich', body: 'Falls Angaben fehlen, bekommst du eine Nachricht im Chat.', status: 'Bereit', icon: Icons.forum_outlined, color: AirmiusColors.amber),
    _TimelineItem(title: 'Entscheidung', body: 'Der Verein kann annehmen, ablehnen oder weitere Daten anfordern.', status: 'Ausstehend', icon: Icons.verified_outlined, color: AirmiusColors.muted),
  ];

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
                        const PageTitle(title: 'Meine Mitgliedsanfrage', subtitle: 'Status, eingereichte Daten, Rückfragen, Dokumente und Anfrage zurückziehen.'),
                        const SizedBox(height: 16),
                        _StatusHero(onWithdraw: _confirmWithdraw),
                        const SizedBox(height: 16),
                        AirmiusPanel(
                          title: 'Eingereichte Daten anzeigen',
                          child: Column(
                            children: [
                              _SwitchRow(title: 'Personendaten', subtitle: 'Name, Geburtstag, Sprache und Basisdaten anzeigen.', value: _showPersonalData, onChanged: (value) => setState(() => _showPersonalData = value)),
                              _SwitchRow(title: 'Zahlungsdaten', subtitle: 'Intervall, Zahlmethode und SEPA-Hinweise anzeigen.', value: _showPaymentData, onChanged: (value) => setState(() => _showPaymentData = value)),
                              _SwitchRow(title: 'Dokumente', subtitle: 'Datenschutz, Regeln, Beitragsordnung und Mandate anzeigen.', value: _showDocuments, onChanged: (value) => setState(() => _showDocuments = value)),
                            ],
                          ),
                        ),
                        const SizedBox(height: 16),
                        if (_showPersonalData) const _DataPanel(title: 'Personendaten', rows: ['Vorname: ZBB', 'Nachname: Konto', 'Geburtsdatum: 01.01.2000', 'Adresse: Saargemuender Str. 110, 66271 Kleinblittersdorf']),
                        if (_showPaymentData) const _DataPanel(title: 'Zahlungsdaten', rows: ['Zahlmethode: Überweisung', 'Intervall: Monatlich', 'Beitragsregel: Allgemeine Mitgliedschaft', 'Status: Noch nicht faellig']),
                        if (_showDocuments) const _DataPanel(title: 'Dokumente', rows: ['Datenschutz gelesen', 'Vereinsregeln akzeptiert', 'Beitragsordnung verknuepft', 'SEPA optional']),
                        const SizedBox(height: 16),
                        AirmiusPanel(
                          title: 'Statusverlauf',
                          child: Column(children: [for (final item in _timeline) _TimelineRow(item: item)]),
                        ),
                        const SizedBox(height: 16),
                        AirmiusPanel(
                          title: 'Aktionen',
                          child: Wrap(
                            spacing: 10,
                            runSpacing: 10,
                            children: [
                              AirmiusButton(label: 'Rückfrage senden', icon: Icons.forum_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => NotificationChatOperationsScreen(initialTab: 'Chat')))),
                                AirmiusButton(label: 'Daten nachreichen', icon: Icons.edit_document, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => MembershipApplicationFormScreen()))),
                              AirmiusButton(
                                label: 'Andere Vereine',
                                icon: Icons.groups_2_outlined,
                                secondary: true,
                                onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => ClubsScreen(requestedClubIds: const {}, onRequestClub: (_) {}, onWithdrawClub: (_) {}))),
                              ),
                              AirmiusButton(label: 'Zurückziehen', icon: Icons.undo_outlined, secondary: true, onPressed: _confirmWithdraw),
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

  Future<void> _confirmWithdraw() async {
    final confirm = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        backgroundColor: AirmiusColors.card,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(22), side: const BorderSide(color: AirmiusColors.border)),
        title: const Text('Anfrage zurückziehen', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
        content: const Text('Moechtest du deine Mitgliedsanfrage bei ZBB wirklich zurückziehen?', style: TextStyle(color: AirmiusColors.muted, fontWeight: FontWeight.w700)),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Abbrechen')),
          FilledButton(
            style: FilledButton.styleFrom(backgroundColor: AirmiusColors.red, foregroundColor: Colors.white),
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Zurückziehen'),
          ),
        ],
      ),
    );
    if (confirm == true) {
      _toast('Anfrage zurückziehen vorbereitet');
    }
  }

  void _toast(String message) {
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(message)));
  }
}

class _StatusHero extends StatelessWidget {
  const _StatusHero({required this.onWithdraw});

  final VoidCallback onWithdraw;

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
              const AirmiusAvatar('ZBB', large: true),
              const SizedBox(width: 12),
              const Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Eyebrow('MITGLIEDSANFRAGE'), SizedBox(height: 4), Text('ZBB', style: TextStyle(color: AirmiusColors.text, fontSize: 24, fontWeight: FontWeight.w900)), Text('Anfrage gesendet - Prüfung läuft', style: TextStyle(color: AirmiusColors.muted, fontWeight: FontWeight.w800))])),
              StatusPill('Gesendet', color: AirmiusColors.green),
            ],
          ),
          const SizedBox(height: 16),
          const Text('Du kannst sehen, welche Daten übermittelt wurden, Rückfragen beantworten und die Anfrage zurückziehen, falls sie versehentlich gesendet wurde.', style: TextStyle(color: AirmiusColors.muted, height: 1.45, fontWeight: FontWeight.w700)),
          const SizedBox(height: 16),
          Wrap(spacing: 10, runSpacing: 10, children: [
            AirmiusButton(label: 'Zurückziehen', icon: Icons.undo_outlined, secondary: true, onPressed: onWithdraw),
            AirmiusButton(
              label: 'Status ansehen',
              icon: Icons.timeline_outlined,
              secondary: true,
              onPressed: () => openUiAction(
                context,
                title: 'Status',
                body: 'Statusdetail vorbereitet',
                status: 'Status',
                icon: Icons.timeline_outlined,
              ),
            ),
          ]),
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
      child: Row(
        children: [
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text(title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), const SizedBox(height: 4), Text(subtitle, style: const TextStyle(color: AirmiusColors.muted, fontSize: 12, height: 1.35, fontWeight: FontWeight.w700))])),
          Switch.adaptive(value: value, onChanged: onChanged, activeThumbColor: AirmiusColors.blue),
        ],
      ),
    );
  }
}

class _DataPanel extends StatelessWidget {
  const _DataPanel({required this.title, required this.rows});

  final String title;
  final List<String> rows;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: AirmiusPanel(
        title: title,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            for (final row in rows)
              Padding(
                padding: const EdgeInsets.only(bottom: 8),
                child: Row(children: [const Icon(Icons.check_circle_outline, size: 17, color: AirmiusColors.green), const SizedBox(width: 8), Expanded(child: Text(row, style: const TextStyle(color: AirmiusColors.muted, fontWeight: FontWeight.w800)))]),
              ),
          ],
        ),
      ),
    );
  }
}

class _TimelineRow extends StatelessWidget {
  const _TimelineRow({required this.item});

  final _TimelineItem item;

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(color: AirmiusColors.input, borderRadius: BorderRadius.circular(16), border: Border.all(color: AirmiusColors.border)),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(item.icon, color: item.color),
          const SizedBox(width: 12),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text(item.title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), const SizedBox(height: 4), Text(item.body, style: const TextStyle(color: AirmiusColors.muted, height: 1.35, fontWeight: FontWeight.w700))])),
          StatusPill(item.status, color: item.color),
        ],
      ),
    );
  }
}

class _TimelineItem {
  const _TimelineItem({required this.title, required this.body, required this.status, required this.icon, required this.color});

  final String title;
  final String body;
  final String status;
  final IconData icon;
  final Color color;
}

