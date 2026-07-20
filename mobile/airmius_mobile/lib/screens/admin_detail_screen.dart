import 'package:flutter/material.dart';
import 'trust_operations_screen.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'ui_action_result_screen.dart';

class AdminDetailScreen extends StatefulWidget {
  const AdminDetailScreen({super.key, required this.area, required this.title, required this.body, required this.status, required this.icon, this.urgent = false});

  final String area;
  final String title;
  final String body;
  final String status;
  final IconData icon;
  final bool urgent;

  @override
  State<AdminDetailScreen> createState() => _AdminDetailScreenState();
}

class _AdminDetailScreenState extends State<AdminDetailScreen> {
  String _status = 'In Prüfung';
  String _assignee = 'Admin Team';
  bool _notify = true;

  @override
  void initState() {
    super.initState();
    _status = widget.urgent ? 'Dringend' : widget.status;
  }

  @override
  Widget build(BuildContext context) {
    final danger = widget.urgent || _status == 'Dringend';
    return Scaffold(
        floatingActionButton: FloatingActionButton.extended(backgroundColor: const Color(0xFFB88320), foregroundColor: Colors.white, icon: const Icon(Icons.verified_user_outlined), label: const Text('Trust Ops', style: TextStyle(fontWeight: FontWeight.w900)), onPressed: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => TrustOperationsScreen(initialTab: 'Moderation')))),
        
      appBar: AppBar(backgroundColor: AirmiusColors.header, surfaceTintColor: Colors.transparent, title: Text(widget.title, style: const TextStyle(fontWeight: FontWeight.w900))),
      body: PageFrame(
        title: widget.title,
        subtitle: '${widget.area} - Admin Detail, Entscheidung, Audit und Aktionen',
        trailing: StatusPill(_status, color: danger ? AirmiusColors.red : AirmiusColors.blue),
        child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          AirmiusPanel(gradient: true, borderColor: danger ? AirmiusColors.red.withValues(alpha: 0.45) : null, child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Icon(widget.icon, color: danger ? AirmiusColors.red : AirmiusColors.blue, size: 42),
            const SizedBox(width: 14),
            Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Eyebrow(widget.area),
              const SizedBox(height: 6),
              Text(widget.body, style: const TextStyle(color: AirmiusColors.text, fontSize: 20, fontWeight: FontWeight.w900, height: 1.2)),
              const SizedBox(height: 10),
              Wrap(spacing: 8, runSpacing: 8, children: [StatusPill(widget.area), StatusPill(_status, color: danger ? AirmiusColors.red : AirmiusColors.blue), if (_notify) const StatusPill('Notify', color: AirmiusColors.green)]),
            ])),
          ])),
          const SizedBox(height: 14),
          Row(children: const [Expanded(child: MetricCard(value: '3', label: 'Checks')), SizedBox(width: 10), Expanded(child: MetricCard(value: '2', label: 'Logs')), SizedBox(width: 10), Expanded(child: MetricCard(value: '1', label: 'Owner'))]),
          const SizedBox(height: 14),
          AirmiusPanel(child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            const Eyebrow('Bearbeitung'),
            const SizedBox(height: 12),
            DropdownButtonFormField<String>(initialValue: _status, dropdownColor: AirmiusColors.cardSoft, decoration: const InputDecoration(labelText: 'Status'), items: const ['Offen', 'In Prüfung', 'Genehmigt', 'Abgelehnt', 'Dringend', 'Archiviert', 'Shop', 'Billing', 'Config', 'System', 'Kosten', 'Rollen', 'Aktiv'].map((item) => DropdownMenuItem(value: item, child: Text(item))).toList(), onChanged: (value) => setState(() => _status = value ?? _status)),
            const SizedBox(height: 12),
            DropdownButtonFormField<String>(initialValue: _assignee, dropdownColor: AirmiusColors.cardSoft, decoration: const InputDecoration(labelText: 'Zuweisung'), items: const ['Admin Team', 'Billing Team', 'Moderation', 'Commerce', 'System'].map((item) => DropdownMenuItem(value: item, child: Text(item))).toList(), onChanged: (value) => setState(() => _assignee = value ?? _assignee)),
            SwitchListTile(value: _notify, onChanged: (value) => setState(() => _notify = value), activeThumbColor: AirmiusColors.blue, contentPadding: EdgeInsets.zero, title: const Text('Betroffene informieren', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), subtitle: const Text('Benachrichtigung oder E-Mail nach Statuswechsel vorbereiten.', style: TextStyle(color: AirmiusColors.muted))),
          ])),
          const SizedBox(height: 14),
          AirmiusPanel(child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            const Eyebrow('Prüfliste'),
            const SizedBox(height: 10),
            _AdminCheckLine(icon: Icons.fact_check_outlined, title: _checkTitleOne(), body: _checkBodyOne(), status: 'OK'),
            _AdminCheckLine(icon: Icons.security_outlined, title: 'Berechtigung prüfen', body: 'Rollen, Besitzer, Verein und sensible Daten gegen Regeln prüfen.', status: 'Pflicht'),
            _AdminCheckLine(icon: Icons.history_outlined, title: 'Audit Trail', body: 'Änderungen, Entscheidung, Bearbeiter und Zeitpunkt später speichern.', status: 'Audit'),
          ])),
          const SizedBox(height: 14),
          const AirmiusPanel(child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            Eyebrow('Notiz'),
            SizedBox(height: 10),
            AirmiusTextField(label: 'Interne Admin-Notiz', hint: 'Warum wurde diese Entscheidung getroffen?', maxLines: 3),
          ])),
          const SizedBox(height: 14),
          Wrap(spacing: 10, runSpacing: 10, children: [
            AirmiusButton(label: 'Speichern', icon: Icons.save_outlined, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => UiActionResultScreen(title: 'Admin-Detail speichern', body: 'Admin-Entscheidung, Notiz, Audit und Benachrichtigung vorbereiten.', status: 'Admin', icon: Icons.save_outlined)))),
            AirmiusButton(label: 'Genehmigen', icon: Icons.check_circle_outline, secondary: true, onPressed: () => setState(() => _status = 'Genehmigt')),
            AirmiusButton(label: 'Ablehnen', icon: Icons.cancel_outlined, danger: true, onPressed: () => setState(() => _status = 'Abgelehnt')),
          ]),
          const SizedBox(height: 14),
          AirmiusPanel(borderColor: AirmiusColors.blue.withValues(alpha: 0.45), child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            const Eyebrow('Spezialaktionen'),
            const SizedBox(height: 10),
            Text(_specialIntro(), style: const TextStyle(color: AirmiusColors.muted, height: 1.35)),
            const SizedBox(height: 12),
            Wrap(spacing: 10, runSpacing: 10, children: _specialActions(context)),
          ])),
        ]),
      ),
    );
  }

  String _specialIntro() {
    if (widget.title.contains('Mitglieder') || widget.title.contains('Users')) return 'Nutzerverwaltung aus der Web-App: Inaktivitaetsnotiz, Rollen, Sperre, DSGVO-Export und Statuswechsel.';
    if (widget.title.contains('Club-Verifizierungen')) return 'Club-Verifizierung: Verein prüfen, Dokumente ansehen, genehmigen oder ablehnen.';
    if (widget.area == 'Moderation') return 'Moderationsentscheidungen: Flag prüfen, Report aktualisieren, Nutzer informieren und Audit schreiben.';
    if (widget.area == 'Billing') return 'Billing-Admin: Banktransfer markieren, Rechnung laden, Mahnung, Kündigung oder Erneuerung vorbereiten.';
    if (widget.area == 'Commerce') return 'Commerce-Admin: Produktstatus, Seller-Antrag, Shipping, Refund, Return und Payout bearbeiten.';
    if (widget.area == 'Content') return 'Content-Admin: Badges, Sportarten, Blog, Media und Learning-Quality freigeben.';
    if (widget.area == 'Outfit') return 'Outfit-Admin: Zahlstatus, Lieferadresse, Payment Reminder, Lieferung und Visuals steuern.';
    return 'System-Admin: Mail, Maintenance, globale Einstellungen und Auditaktionen.';
  }

  List<Widget> _specialActions(BuildContext context) {
    final actions = <Widget>[];
    void add(String label, IconData icon, String status, {bool danger = false}) {
      actions.add(AirmiusButton(
        label: label,
        icon: icon,
        danger: danger,
        secondary: !danger,
        onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => UiActionResultScreen(title: label, body: '$label für ${widget.title} vorbereiten, Audit schreiben und später mit Laravel Admin-Route verbinden.', status: status, icon: icon))),
      ));
    }

    if (widget.title.contains('Mitglieder') || widget.title.contains('Users')) {
      add('Inaktivitaetsnotiz senden', Icons.mark_email_read_outlined, 'Notice');
      add('Rolle zuweisen', Icons.admin_panel_settings_outlined, 'Role');
      add('DSGVO Export', Icons.download_outlined, 'Export');
      add('Nutzer löschen', Icons.delete_outline, 'Delete', danger: true);
      return actions;
    }
    if (widget.title.contains('Club-Verifizierungen')) {
      add('Verein genehmigen', Icons.verified_outlined, 'Approve');
      add('Verein ablehnen', Icons.cancel_outlined, 'Reject', danger: true);
      add('Nachweis anfordern', Icons.upload_file_outlined, 'Docs');
      return actions;
    }
    if (widget.area == 'Moderation') {
      add('Flag aktualisieren', Icons.flag_outlined, 'Flag');
      add('Report schließen', Icons.task_alt_outlined, 'Closed');
      add('Eskalieren', Icons.warning_amber_outlined, 'Urgent', danger: true);
      return actions;
    }
    if (widget.area == 'Billing') {
      add('Als bezahlt markieren', Icons.payments_outlined, 'Paid');
      add('Rechnung downloaden', Icons.picture_as_pdf_outlined, 'PDF');
      add('Abo erneuern', Icons.autorenew_outlined, 'Renew');
      add('Abo kündigen', Icons.cancel_outlined, 'Cancel', danger: true);
      return actions;
    }
    if (widget.area == 'Commerce') {
      add('Produktstatus setzen', Icons.inventory_2_outlined, 'Product');
      add('Shipping aktualisieren', Icons.local_shipping_outlined, 'Shipping');
      add('Refund starten', Icons.currency_exchange_outlined, 'Refund', danger: true);
      add('Payout bezahlt', Icons.account_balance_wallet_outlined, 'Payout');
      return actions;
    }
    if (widget.area == 'Content') {
      add('Badge speichern', Icons.workspace_premium_outlined, 'Badge');
      add('Sportart speichern', Icons.sports_outlined, 'Sport');
      add('Learning freigeben', Icons.school_outlined, 'Quality');
      add('Media Visuals aktualisieren', Icons.image_outlined, 'Media');
      return actions;
    }
    if (widget.area == 'Outfit') {
      add('Zahlung erinnern', Icons.notification_important_outlined, 'Reminder');
      add('Lieferung aktualisieren', Icons.local_shipping_outlined, 'Delivery');
      add('Adresse bearbeiten', Icons.location_on_outlined, 'Address');
      add('Abo löschen', Icons.delete_outline, 'Delete', danger: true);
      return actions;
    }

    add('Testmail senden', Icons.mark_email_read_outlined, 'Mail');
    add('Maintenance setzen', Icons.construction_outlined, 'System');
    add('Audit exportieren', Icons.ios_share_outlined, 'Audit');
    return actions;
  }

  String _checkTitleOne() {
    if (widget.area == 'Moderation') return 'Meldung bewerten';
    if (widget.area == 'Billing') return 'Zahlungsstatus prüfen';
    if (widget.area == 'Commerce') return 'Produkt/Order prüfen';
    if (widget.area == 'System') return 'Konfiguration prüfen';
    if (widget.area == 'Nutzer') return 'Nutzerkontext prüfen';
    return 'Admin-Prüfung';
  }

  String _checkBodyOne() {
    if (widget.area == 'Moderation') return 'Content, Meldungsgrund, Autor, Kommentare und Eskalation bewerten.';
    if (widget.area == 'Billing') return 'Invoice, Providerstatus, Banktransfer, Mahnung und Zahlungsausgleich ansehen.';
    if (widget.area == 'Commerce') return 'Produktqualitaet, Bestand, Bestellung, Retoure oder Payout prüfen.';
    if (widget.area == 'System') return 'Mail, Maintenance, globale Settings und Fehlerzustand prüfen.';
    if (widget.area == 'Nutzer') return 'Profil, Rolle, Status, Verbindung und Verifizierung kontrollieren.';
    return 'Details und naechste Aktion prüfen.';
  }
}

class _AdminCheckLine extends StatelessWidget {
  const _AdminCheckLine({required this.icon, required this.title, required this.body, required this.status});

  final IconData icon;
  final String title;
  final String body;
  final String status;

  @override
  Widget build(BuildContext context) {
    return Padding(padding: const EdgeInsets.only(top: 12), child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [Icon(icon, color: AirmiusColors.blue), const SizedBox(width: 12), Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text(title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), const SizedBox(height: 3), Text(body, style: const TextStyle(color: AirmiusColors.muted, height: 1.35))])), StatusPill(status)]));
  }
}

