import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'club_contribution_rules_screen.dart';
import 'club_document_upload_manager_screen.dart';
import 'club_request_inbox_screen.dart';

class ClubMembershipFormBuilderScreen extends StatefulWidget {
  const ClubMembershipFormBuilderScreen({super.key});

  @override
  State<ClubMembershipFormBuilderScreen> createState() => _ClubMembershipFormBuilderScreenState();
}

class _ClubMembershipFormBuilderScreenState extends State<ClubMembershipFormBuilderScreen> {
  bool _personalRequired = true;
  bool _addressRequired = true;
  bool _contactRequired = true;
  bool _guardianRequired = true;
  bool _sportRequired = false;
  bool _paymentRequired = true;
  bool _documentsRequired = true;
  bool _allowWithdraw = true;

  String _interval = 'Monatlich';
  String _payment = 'Ueberweisung';

  final List<_FieldGroup> _groups = const [
    _FieldGroup(title: 'Personendaten', body: 'Vorname, Nachname, Geburtsdatum, Geschlecht, Sprache und Profilname.', status: 'Pflicht', icon: Icons.person_outline, color: AirmiusColors.blue),
    _FieldGroup(title: 'Kontaktdaten', body: 'E-Mail, Telefon, Notfallkontakt, Elternkontakt und Kommunikationsfreigabe.', status: 'Pflicht', icon: Icons.contact_mail_outlined, color: AirmiusColors.green),
    _FieldGroup(title: 'Wohndaten', body: 'Land, Strasse, Hausnummer, PLZ, Stadt, Bundesland und Rechnungsadresse.', status: 'Pflicht', icon: Icons.home_outlined, color: AirmiusColors.amber),
    _FieldGroup(title: 'Sportdaten', body: 'Lizenznummer, Teamwunsch, Trainingsgruppe, Spielklasse und Erfahrung.', status: 'Optional', icon: Icons.sports_outlined, color: AirmiusColors.blueDeep),
    _FieldGroup(title: 'Zahlungsdaten', body: 'Bar, Ueberweisung, SEPA, IBAN, BIC, Zahlername und Zahlungsintervall.', status: 'Konfigurierbar', icon: Icons.payments_outlined, color: AirmiusColors.red),
    _FieldGroup(title: 'Dokumente', body: 'Datenschutz, Vereinsregeln, Beitragsordnung, SEPA-Mandat und Minderjaehrigenformular.', status: 'Verknuepft', icon: Icons.folder_copy_outlined, color: AirmiusColors.blue),
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
                        const PageTitle(title: 'Mitgliedsantrag konfigurieren', subtitle: 'Felder, Pflichtdaten, Zahlungsarten, Intervalle, Dokumente und Rueckzugsmoeglichkeit je Verein.'),
                        const SizedBox(height: 16),
                        _FormBuilderHero(onPreview: () => _toast('Antragsvorschau vorbereitet')),
                        const SizedBox(height: 16),
                        AirmiusPanel(
                          title: 'Feldgruppen',
                          child: Column(
                            children: [
                              _SwitchRow(title: 'Personendaten verlangen', subtitle: 'Basisdaten fuer Mitgliedschaft und Identitaet.', value: _personalRequired, onChanged: (value) => setState(() => _personalRequired = value)),
                              _SwitchRow(title: 'Wohndaten verlangen', subtitle: 'Adresse fuer Verein, Rechnung und regionale Zuordnung.', value: _addressRequired, onChanged: (value) => setState(() => _addressRequired = value)),
                              _SwitchRow(title: 'Kontaktdaten verlangen', subtitle: 'E-Mail, Telefon und Notfallkontakt.', value: _contactRequired, onChanged: (value) => setState(() => _contactRequired = value)),
                              _SwitchRow(title: 'Erziehungsberechtigte verlangen', subtitle: 'Automatisch relevant bei minderjaehrigen Antragstellern.', value: _guardianRequired, onChanged: (value) => setState(() => _guardianRequired = value)),
                              _SwitchRow(title: 'Sportdaten abfragen', subtitle: 'Lizenznummer, Teamwunsch, Trainingsgruppe und Erfahrung.', value: _sportRequired, onChanged: (value) => setState(() => _sportRequired = value)),
                              _SwitchRow(title: 'Zahlungsdaten verlangen', subtitle: 'Bar, Ueberweisung, SEPA und Zahlerdaten.', value: _paymentRequired, onChanged: (value) => setState(() => _paymentRequired = value)),
                              _SwitchRow(title: 'Pflichtdokumente anzeigen', subtitle: 'Datenschutz, Regeln, Beitrag und SEPA mit Antrag verknuepfen.', value: _documentsRequired, onChanged: (value) => setState(() => _documentsRequired = value)),
                              _SwitchRow(title: 'Rueckzug erlauben', subtitle: 'User koennen versehentlich gesendete Anfragen zurueckziehen.', value: _allowWithdraw, onChanged: (value) => setState(() => _allowWithdraw = value)),
                            ],
                          ),
                        ),
                        const SizedBox(height: 16),
                        _PaymentRules(
                          interval: _interval,
                          payment: _payment,
                          onInterval: (value) => setState(() => _interval = value),
                          onPayment: (value) => setState(() => _payment = value),
                        ),
                        const SizedBox(height: 16),
                        for (final group in _groups) ...[
                          _FieldGroupCard(group: group, onEdit: () => _toast('${group.title}: Felder bearbeiten vorbereitet')),
                          const SizedBox(height: 12),
                        ],
                        AirmiusPanel(
                          title: 'Verbundene Admin-Bereiche',
                          child: Wrap(
                            spacing: 10,
                            runSpacing: 10,
                            children: [
                              AirmiusButton(label: 'Anfrage-Eingang', icon: Icons.inbox_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => ClubRequestInboxScreen()))),
                              AirmiusButton(label: 'Beitragsregeln', icon: Icons.receipt_long_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => ClubContributionRulesScreen()))),
                              AirmiusButton(label: 'Dokumente', icon: Icons.folder_copy_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => ClubDocumentUploadManagerScreen()))),
                              AirmiusButton(label: 'Speichern', icon: Icons.save_outlined, secondary: true, onPressed: () => _toast('Formular-Konfiguration gespeichert vorbereitet')),
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

  void _toast(String message) {
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(message)));
  }
}

class _FormBuilderHero extends StatelessWidget {
  const _FormBuilderHero({required this.onPreview});

  final VoidCallback onPreview;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        gradient: const LinearGradient(colors: [Color(0xFF11243B), Color(0xFF0B111B)], begin: Alignment.topLeft, end: Alignment.bottomRight),
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
              const Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Eyebrow('ANTRAGSFORMULAR'), SizedBox(height: 4), Text('Jeder Verein entscheidet selbst', style: TextStyle(color: AirmiusColors.text, fontSize: 22, fontWeight: FontWeight.w900))])),
              AirmiusButton(label: 'Vorschau', icon: Icons.preview_outlined, onPressed: onPreview),
            ],
          ),
          const SizedBox(height: 14),
          const Text('Die App bildet den Mitgliedsantrag als konfigurierbaren Formularbaukasten ab: Pflichtfelder, optionale Felder, Zahlungsregeln, Dokumente und Rueckzug.', style: TextStyle(color: AirmiusColors.muted, height: 1.45, fontWeight: FontWeight.w700)),
          const SizedBox(height: 16),
          const Row(children: [Expanded(child: MetricCard(value: '8', label: 'Gruppen')), SizedBox(width: 10), Expanded(child: MetricCard(value: '31', label: 'Felder')), SizedBox(width: 10), Expanded(child: MetricCard(value: '4', label: 'Intervalle'))]),
        ],
      ),
    );
  }
}

class _PaymentRules extends StatelessWidget {
  const _PaymentRules({required this.interval, required this.payment, required this.onInterval, required this.onPayment});

  final String interval;
  final String payment;
  final ValueChanged<String> onInterval;
  final ValueChanged<String> onPayment;

  static const intervals = ['Monatlich', '4 Monate', '6 Monate', 'Jaehrlich'];
  static const payments = ['Ueberweisung', 'Bar', 'SEPA', 'Keine Zahlung'];

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      title: 'Zahlungsregeln',
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text('Zahlungsintervall', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
          const SizedBox(height: 10),
          _ChipRow(values: intervals, selected: interval, onChanged: onInterval),
          const SizedBox(height: 14),
          const Text('Zahlmethode', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
          const SizedBox(height: 10),
          _ChipRow(values: payments, selected: payment, onChanged: onPayment),
        ],
      ),
    );
  }
}

class _ChipRow extends StatelessWidget {
  const _ChipRow({required this.values, required this.selected, required this.onChanged});

  final List<String> values;
  final String selected;
  final ValueChanged<String> onChanged;

  @override
  Widget build(BuildContext context) {
    return Wrap(
      spacing: 8,
      runSpacing: 8,
      children: [
        for (final value in values)
          ChoiceChip(
            label: Text(value),
            selected: selected == value,
            onSelected: (_) => onChanged(value),
            selectedColor: AirmiusColors.blue.withValues(alpha: .24),
            backgroundColor: AirmiusColors.card,
            labelStyle: TextStyle(color: selected == value ? AirmiusColors.text : AirmiusColors.muted, fontWeight: FontWeight.w900),
            side: BorderSide(color: selected == value ? AirmiusColors.blue : AirmiusColors.border),
          ),
      ],
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
          Switch.adaptive(value: value, onChanged: onChanged, activeColor: AirmiusColors.blue),
        ],
      ),
    );
  }
}

class _FieldGroupCard extends StatelessWidget {
  const _FieldGroupCard({required this.group, required this.onEdit});

  final _FieldGroup group;
  final VoidCallback onEdit;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      title: group.title,
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(width: 48, height: 48, decoration: BoxDecoration(color: group.color.withValues(alpha: .18), borderRadius: BorderRadius.circular(16), border: Border.all(color: group.color.withValues(alpha: .5))), child: Icon(group.icon, color: group.color)),
          const SizedBox(width: 12),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [StatusPill(group.status, color: group.color), const SizedBox(height: 8), Text(group.body, style: const TextStyle(color: AirmiusColors.muted, height: 1.45, fontWeight: FontWeight.w700))])),
          IconButton(onPressed: onEdit, icon: const Icon(Icons.tune_outlined, color: AirmiusColors.muted)),
        ],
      ),
    );
  }
}

class _FieldGroup {
  const _FieldGroup({required this.title, required this.body, required this.status, required this.icon, required this.color});

  final String title;
  final String body;
  final String status;
  final IconData icon;
  final Color color;
}
