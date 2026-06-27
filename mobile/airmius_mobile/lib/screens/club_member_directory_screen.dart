import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'access_operations_screen.dart';
import 'billing_operations_screen.dart';
import 'club_contribution_rules_screen.dart';
import 'club_request_inbox_screen.dart';
import 'membership_operations_screen.dart';

class ClubMemberDirectoryScreen extends StatefulWidget {
  const ClubMemberDirectoryScreen({super.key, this.initialTab = 'Aktiv'});

  final String initialTab;

  @override
  State<ClubMemberDirectoryScreen> createState() => _ClubMemberDirectoryScreenState();
}

class _ClubMemberDirectoryScreenState extends State<ClubMemberDirectoryScreen> {
  late String _tab = widget.initialTab;
  String _query = '';
  bool _showExternal = true;
  bool _showPaymentStatus = true;
  bool _showRoles = true;
  bool _bulkMode = false;

  @override
  Widget build(BuildContext context) {
    final members = _members.where((member) {
      final tabMatch = _tab == 'Alle' || member.status == _tab;
      final text = '${member.name} ${member.email} ${member.role} ${member.payment}'.toLowerCase();
      return tabMatch && (_query.isEmpty || text.contains(_query.toLowerCase()));
    }).toList();

    return Scaffold(
      backgroundColor: AirmiusColors.bg,
      appBar: AppBar(backgroundColor: AirmiusColors.header, surfaceTintColor: Colors.transparent, title: const Text('Mitgliederverwaltung', style: TextStyle(fontWeight: FontWeight.w900))),
      body: PageFrame(
        title: 'Mitgliederverwaltung',
        subtitle: 'Mitglieder, externe Kontakte, Rollen, Zahlstatus, Dokumente, Import und Statuswechsel',
        trailing: StatusPill('${members.length}', color: AirmiusColors.green),
        child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          AirmiusPanel(gradient: true, child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            const AirmiusLogo(),
            const SizedBox(height: 14),
            const Text('Web-Tabellen werden mobil zu klaren Mitgliederkarten.', style: TextStyle(color: AirmiusColors.text, fontSize: 24, fontWeight: FontWeight.w900, height: 1.08)),
            const SizedBox(height: 8),
            const Text('Vereinsadmins können Mitglieder suchen, Status sehen, Rollen wechseln, Zahlungen prüfen, Dokumente öffnen, externe Mitglieder importieren und Massenaktionen vorbereiten.', style: TextStyle(color: AirmiusColors.muted, height: 1.4)),
            const SizedBox(height: 14),
            Row(children: const [Expanded(child: MetricCard(value: '128', label: 'Mitglieder')), SizedBox(width: 10), Expanded(child: MetricCard(value: '7', label: 'Offen')), SizedBox(width: 10), Expanded(child: MetricCard(value: '4', label: 'Rollen'))]),
            const SizedBox(height: 14),
            SearchBox(hint: 'Mitglieder, E-Mail, Rolle oder Zahlstatus suchen', onChanged: (value) => setState(() => _query = value.trim())),
            const SizedBox(height: 12),
            Wrap(spacing: 8, runSpacing: 8, children: [for (final tab in _tabs) ChoiceChip(label: Text(tab), selected: _tab == tab, onSelected: (_) => setState(() => _tab = tab), selectedColor: AirmiusColors.green.withValues(alpha: .22), backgroundColor: AirmiusColors.cardSoft, side: BorderSide(color: _tab == tab ? AirmiusColors.green : AirmiusColors.border), labelStyle: TextStyle(color: _tab == tab ? AirmiusColors.text : AirmiusColors.muted, fontWeight: FontWeight.w900))]),
          ])),
          const SizedBox(height: 16),
          _DirectoryControls(showExternal: _showExternal, showPaymentStatus: _showPaymentStatus, showRoles: _showRoles, bulkMode: _bulkMode, onExternal: (value) => setState(() => _showExternal = value), onPayment: (value) => setState(() => _showPaymentStatus = value), onRoles: (value) => setState(() => _showRoles = value), onBulk: (value) => setState(() => _bulkMode = value)),
          const SizedBox(height: 16),
          for (final member in members) ...[
            if (_showExternal || !member.external) _MemberCard(member: member, showPaymentStatus: _showPaymentStatus, showRoles: _showRoles, bulkMode: _bulkMode),
            if (_showExternal || !member.external) const SizedBox(height: 12),
          ],
          if (members.isEmpty) const EmptyPanel('Keine Mitglieder gefunden.'),
          _DirectoryWorkflowPanel(tab: _tab),
        ]),
      ),
    );
  }
}

class _DirectoryControls extends StatelessWidget {
  const _DirectoryControls({required this.showExternal, required this.showPaymentStatus, required this.showRoles, required this.bulkMode, required this.onExternal, required this.onPayment, required this.onRoles, required this.onBulk});

  final bool showExternal;
  final bool showPaymentStatus;
  final bool showRoles;
  final bool bulkMode;
  final ValueChanged<bool> onExternal;
  final ValueChanged<bool> onPayment;
  final ValueChanged<bool> onRoles;
  final ValueChanged<bool> onBulk;

  @override
  Widget build(BuildContext context) => AirmiusPanel(borderColor: AirmiusColors.blue.withValues(alpha: .44), child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
    const Eyebrow('Ansicht & Aktionen'),
    const SizedBox(height: 8),
    const Text('Diese Schalter ersetzen Tabellenfilter aus der Web-App. Später werden sie serverseitig mit Pagination, Rollenrechten und Export verbunden.', style: TextStyle(color: AirmiusColors.muted, height: 1.35)),
    const SizedBox(height: 10),
    _DirectorySwitch(icon: Icons.person_add_alt_outlined, title: 'Externe Mitglieder anzeigen', body: 'Importierte Kontakte, Warteliste oder Papiermitglieder in derselben mobilen Liste zeigen.', value: showExternal, onChanged: onExternal, color: AirmiusColors.green),
    _DirectorySwitch(icon: Icons.receipt_long_outlined, title: 'Zahlstatus anzeigen', body: 'Offen, bezahlt, Mahnung, Barzahlung oder SEPA direkt auf der Mitgliederkarte anzeigen.', value: showPaymentStatus, onChanged: onPayment, color: AirmiusColors.amber),
    _DirectorySwitch(icon: Icons.admin_panel_settings_outlined, title: 'Rollen anzeigen', body: 'Mitglied, Trainer, Admin, Captain, Guardian oder Gastrolle sichtbar machen.', value: showRoles, onChanged: onRoles, color: AirmiusColors.blue),
    _DirectorySwitch(icon: Icons.checklist_outlined, title: 'Massenaktionen aktivieren', body: 'Mehrere Mitglieder für Export, Mahnung, Rollenwechsel oder Nachricht markieren.', value: bulkMode, onChanged: onBulk, color: AirmiusColors.green),
  ]));
}

class _MemberCard extends StatelessWidget {
  const _MemberCard({required this.member, required this.showPaymentStatus, required this.showRoles, required this.bulkMode});

  final _ClubMember member;
  final bool showPaymentStatus;
  final bool showRoles;
  final bool bulkMode;

  @override
  Widget build(BuildContext context) => AirmiusPanel(borderColor: member.color.withValues(alpha: .44), child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
    Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
      if (bulkMode) Padding(padding: const EdgeInsets.only(right: 8), child: Checkbox(value: false, onChanged: (_) {})),
      AirmiusAvatar(member.name),
      const SizedBox(width: 14),
      Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Text(member.name, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900, fontSize: 16)),
        const SizedBox(height: 5),
        Text(member.email, style: const TextStyle(color: AirmiusColors.muted, height: 1.35)),
        const SizedBox(height: 10),
        Wrap(spacing: 8, runSpacing: 8, children: [StatusPill(member.status, color: member.color), if (showRoles) StatusPill(member.role), if (showPaymentStatus) StatusPill(member.payment, color: member.paymentColor), if (member.external) const StatusPill('Extern', color: AirmiusColors.amber)]),
      ])),
    ]),
    const SizedBox(height: 12),
    _MemberMeta(member: member),
    const SizedBox(height: 12),
    Wrap(spacing: 8, runSpacing: 8, children: [
      AirmiusButton(label: 'Profil', icon: Icons.person_outline, onPressed: () => openUiAction(context, title: '${member.name} öffnen', body: 'Mitgliedsprofil, Rollen, Zahlstatus, Dokumente, Teams, Guardian und Audit anzeigen.', status: member.status, icon: Icons.person_outline)),
      AirmiusButton(label: 'Rolle', icon: Icons.admin_panel_settings_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => AccessOperationsScreen()))),
      AirmiusButton(label: 'Zahlung', icon: Icons.receipt_long_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => BillingOperationsScreen()))),
      AirmiusButton(label: 'Status', icon: Icons.swap_horiz_outlined, secondary: true, onPressed: () => openUiAction(context, title: 'Mitgliedsstatus ändern', body: '${member.name}: aktiv, pausiert, ausgetreten, gesperrt oder Warteliste setzen und Benachrichtigung vorbereiten.', status: 'Status', icon: Icons.swap_horiz_outlined)),
    ]),
  ]));
}

class _MemberMeta extends StatelessWidget {
  const _MemberMeta({required this.member});

  final _ClubMember member;

  @override
  Widget build(BuildContext context) => Container(padding: const EdgeInsets.all(12), decoration: BoxDecoration(color: AirmiusColors.cardSoft, borderRadius: BorderRadius.circular(14), border: Border.all(color: AirmiusColors.border)), child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
    _MetaLine(label: 'Mitgliedsnummer', value: member.number),
    _MetaLine(label: 'Typ', value: member.type),
    _MetaLine(label: 'Team', value: member.team),
    _MetaLine(label: 'Dokumente', value: member.documents),
  ]));
}

class _MetaLine extends StatelessWidget {
  const _MetaLine({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) => Padding(padding: const EdgeInsets.symmetric(vertical: 4), child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [SizedBox(width: 118, child: Text(label, style: const TextStyle(color: AirmiusColors.muted, fontWeight: FontWeight.w800))), Expanded(child: Text(value, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)))]));
}

class _DirectoryWorkflowPanel extends StatelessWidget {
  const _DirectoryWorkflowPanel({required this.tab});

  final String tab;

  @override
  Widget build(BuildContext context) => AirmiusPanel(borderColor: AirmiusColors.green.withValues(alpha: .44), child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
    const Eyebrow('Mitglieder-Workflow'),
    const SizedBox(height: 8),
    Text('Aktueller Filter: $tab. Später verbindet Laravel diese UI mit Mitglieder-Pagination, Import, Rollen, Zahlstatus, Dokumenten, Teamzuweisung und Audit.', style: const TextStyle(color: AirmiusColors.muted, height: 1.35)),
    const SizedBox(height: 12),
    Wrap(spacing: 8, runSpacing: 8, children: [
      AirmiusButton(label: 'Importieren', icon: Icons.upload_file_outlined, onPressed: () => openUiAction(context, title: 'Mitglieder importieren', body: 'CSV/Excel-Import, externe Mitglieder, Dublettenprüfung, Rollen und Zahlungsstatus für Laravel vorbereiten.', status: 'Import', icon: Icons.upload_file_outlined)),
      AirmiusButton(label: 'Anfragen', icon: Icons.inbox_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => ClubRequestInboxScreen()))),
      AirmiusButton(label: 'Regeln', icon: Icons.payments_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => ClubContributionRulesScreen()))),
      AirmiusButton(label: 'Membership Ops', icon: Icons.assignment_ind_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => MembershipOperationsScreen()))),
    ]),
  ]));
}

class _DirectorySwitch extends StatelessWidget {
  const _DirectorySwitch({required this.icon, required this.title, required this.body, required this.value, required this.onChanged, required this.color});

  final IconData icon;
  final String title;
  final String body;
  final bool value;
  final ValueChanged<bool> onChanged;
  final Color color;

  @override
  Widget build(BuildContext context) => SwitchListTile(value: value, onChanged: onChanged, activeColor: color, contentPadding: EdgeInsets.zero, secondary: Icon(icon, color: color), title: Text(title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), subtitle: Text(body, style: const TextStyle(color: AirmiusColors.muted, height: 1.3)));
}

class _ClubMember {
  const _ClubMember({required this.status, required this.name, required this.email, required this.number, required this.type, required this.team, required this.role, required this.payment, required this.documents, required this.external, required this.color, required this.paymentColor});

  final String status;
  final String name;
  final String email;
  final String number;
  final String type;
  final String team;
  final String role;
  final String payment;
  final String documents;
  final bool external;
  final Color color;
  final Color paymentColor;
}

const _tabs = ['Alle', 'Aktiv', 'Pausiert', 'Warteliste', 'Ausgetreten', 'Gesperrt'];

const _members = <_ClubMember>[
  _ClubMember(status: 'Aktiv', name: 'ZBB Konto', email: 'zbb.bop.it@gmail.com', number: 'ZBB-0001', type: 'Standard', team: 'Ohne Team', role: 'Mitglied', payment: 'Bezahlt', documents: 'OK', external: false, color: AirmiusColors.green, paymentColor: AirmiusColors.green),
  _ClubMember(status: 'Aktiv', name: 'Mina Becker', email: 'mina@example.com', number: 'ZBB-0042', type: 'Jugend', team: 'U16', role: 'Athletin', payment: 'SEPA', documents: 'Guardian OK', external: false, color: AirmiusColors.green, paymentColor: AirmiusColors.blue),
  _ClubMember(status: 'Pausiert', name: 'Jonas Weber', email: 'jonas@example.com', number: 'ZBB-0031', type: 'Standard', team: 'Herren', role: 'Captain', payment: 'Offen', documents: 'OK', external: false, color: AirmiusColors.amber, paymentColor: AirmiusColors.amber),
  _ClubMember(status: 'Warteliste', name: 'Ali Hassan', email: 'ali@example.com', number: 'WL-0012', type: 'Probemonat', team: 'Warteliste', role: 'Gast', payment: 'Nicht faellig', documents: 'Fehlt', external: true, color: AirmiusColors.blue, paymentColor: AirmiusColors.muted),
  _ClubMember(status: 'Ausgetreten', name: 'Laura Schmidt', email: 'laura@example.com', number: 'ZBB-0022', type: 'Standard', team: 'Archiv', role: 'Ehemalig', payment: 'Abgeschlossen', documents: 'Archiv', external: false, color: AirmiusColors.muted, paymentColor: AirmiusColors.green),
  _ClubMember(status: 'Gesperrt', name: 'Test Account', email: 'test@example.com', number: 'ZBB-0099', type: 'Unklar', team: 'Keine', role: 'Gesperrt', payment: 'Prüfen', documents: 'Prüfen', external: true, color: AirmiusColors.red, paymentColor: AirmiusColors.red),
];
