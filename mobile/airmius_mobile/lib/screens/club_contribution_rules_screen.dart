import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'billing_operations_screen.dart';
import 'club_policy_documents_screen.dart';
import 'membership_operations_screen.dart';

class ClubContributionRulesScreen extends StatefulWidget {
  const ClubContributionRulesScreen({super.key, this.initialTab = 'Regeln'});

  final String initialTab;

  @override
  State<ClubContributionRulesScreen> createState() => _ClubContributionRulesScreenState();
}

class _ClubContributionRulesScreenState extends State<ClubContributionRulesScreen> {
  late String _tab = widget.initialTab;
  String _frequency = 'Monatlich';
  String _method = 'Ueberweisung';
  bool _cashAllowed = true;
  bool _bankTransferAllowed = true;
  bool _sepaAllowed = false;
  bool _invoiceAutoCreate = true;
  bool _remindersEnabled = true;
  bool _familyDiscount = false;
  bool _trialMonth = false;

  @override
  Widget build(BuildContext context) {
    final rules = _rules.where((rule) => rule.area == _tab).toList();
    return Scaffold(
      backgroundColor: AirmiusColors.bg,
      appBar: AppBar(backgroundColor: AirmiusColors.header, surfaceTintColor: Colors.transparent, title: const Text('Beitragsregeln', style: TextStyle(fontWeight: FontWeight.w900))),
      body: PageFrame(
        title: 'Beitragsregeln',
        subtitle: 'Mitgliedschaftstypen, Zahlungsrhythmus, Methoden, Rechnungen, Mahnungen und Ausnahmen',
        trailing: const StatusPill('Verein'),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            AirmiusPanel(
              gradient: true,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const AirmiusLogo(),
                  const SizedBox(height: 14),
                  const Text('Jeder Verein entscheidet selbst, wie Mitglieder bezahlen.', style: TextStyle(color: AirmiusColors.text, fontSize: 24, fontWeight: FontWeight.w900, height: 1.08)),
                  const SizedBox(height: 8),
                  const Text('Diese UI bildet Beitragsarten, Zahlungsrhythmen, Zahlungsmethoden, Rechnungen, Mahnungen und Ausnahmen mobil ab. Laravel speichert spaeter die Regeln pro Verein und Mitgliedschaftstyp.', style: TextStyle(color: AirmiusColors.muted, height: 1.4)),
                  const SizedBox(height: 14),
                  Row(children: [Expanded(child: MetricCard(value: _frequency, label: 'Rhythmus')), const SizedBox(width: 10), Expanded(child: MetricCard(value: _method, label: 'Methode')), const SizedBox(width: 10), const Expanded(child: MetricCard(value: 'API', label: 'Spaeter'))]),
                  const SizedBox(height: 14),
                  Wrap(spacing: 8, runSpacing: 8, children: [
                    for (final tab in _tabs)
                      ChoiceChip(
                        label: Text(tab),
                        selected: _tab == tab,
                        onSelected: (_) => setState(() => _tab = tab),
                        selectedColor: AirmiusColors.amber.withValues(alpha: .22),
                        backgroundColor: AirmiusColors.cardSoft,
                        side: BorderSide(color: _tab == tab ? AirmiusColors.amber : AirmiusColors.border),
                        labelStyle: TextStyle(color: _tab == tab ? AirmiusColors.text : AirmiusColors.muted, fontWeight: FontWeight.w900),
                      ),
                  ]),
                ],
              ),
            ),
            const SizedBox(height: 16),
            _RuleBuilderPanel(
              frequency: _frequency,
              method: _method,
              cashAllowed: _cashAllowed,
              bankTransferAllowed: _bankTransferAllowed,
              sepaAllowed: _sepaAllowed,
              invoiceAutoCreate: _invoiceAutoCreate,
              remindersEnabled: _remindersEnabled,
              familyDiscount: _familyDiscount,
              trialMonth: _trialMonth,
              onFrequency: (value) => setState(() => _frequency = value ?? _frequency),
              onMethod: (value) => setState(() => _method = value ?? _method),
              onCash: (value) => setState(() => _cashAllowed = value),
              onBank: (value) => setState(() => _bankTransferAllowed = value),
              onSepa: (value) => setState(() => _sepaAllowed = value),
              onInvoice: (value) => setState(() => _invoiceAutoCreate = value),
              onReminder: (value) => setState(() => _remindersEnabled = value),
              onFamily: (value) => setState(() => _familyDiscount = value),
              onTrial: (value) => setState(() => _trialMonth = value),
            ),
            const SizedBox(height: 16),
            for (final rule in rules) ...[
              _ContributionRuleCard(rule: rule),
              const SizedBox(height: 12),
            ],
            _ContributionWorkflowPanel(frequency: _frequency, method: _method),
          ],
        ),
      ),
    );
  }
}

class _RuleBuilderPanel extends StatelessWidget {
  const _RuleBuilderPanel({required this.frequency, required this.method, required this.cashAllowed, required this.bankTransferAllowed, required this.sepaAllowed, required this.invoiceAutoCreate, required this.remindersEnabled, required this.familyDiscount, required this.trialMonth, required this.onFrequency, required this.onMethod, required this.onCash, required this.onBank, required this.onSepa, required this.onInvoice, required this.onReminder, required this.onFamily, required this.onTrial});

  final String frequency;
  final String method;
  final bool cashAllowed;
  final bool bankTransferAllowed;
  final bool sepaAllowed;
  final bool invoiceAutoCreate;
  final bool remindersEnabled;
  final bool familyDiscount;
  final bool trialMonth;
  final ValueChanged<String?> onFrequency;
  final ValueChanged<String?> onMethod;
  final ValueChanged<bool> onCash;
  final ValueChanged<bool> onBank;
  final ValueChanged<bool> onSepa;
  final ValueChanged<bool> onInvoice;
  final ValueChanged<bool> onReminder;
  final ValueChanged<bool> onFamily;
  final ValueChanged<bool> onTrial;

  @override
  Widget build(BuildContext context) => AirmiusPanel(
        borderColor: AirmiusColors.amber.withValues(alpha: .44),
        child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          const Eyebrow('Regel-Builder'),
          const SizedBox(height: 10),
          DropdownButtonFormField<String>(value: frequency, dropdownColor: AirmiusColors.cardSoft, decoration: const InputDecoration(labelText: 'Zahlungsrhythmus'), items: const ['Monatlich', 'Alle 4 Monate', 'Halbjaehrlich', 'Jaehrlich', 'Einmalig'].map((item) => DropdownMenuItem(value: item, child: Text(item))).toList(), onChanged: onFrequency),
          const SizedBox(height: 12),
          DropdownButtonFormField<String>(value: method, dropdownColor: AirmiusColors.cardSoft, decoration: const InputDecoration(labelText: 'Standard-Zahlmethode'), items: const ['Ueberweisung', 'Bar', 'SEPA', 'Online Checkout', 'Kostenlos'].map((item) => DropdownMenuItem(value: item, child: Text(item))).toList(), onChanged: onMethod),
          const SizedBox(height: 10),
          _RuleSwitch(icon: Icons.payments_outlined, title: 'Barzahlung erlauben', body: 'Verein kann Barzahlung fuer Mitglieder oder bestimmte Typen aktivieren.', value: cashAllowed, onChanged: onCash, color: AirmiusColors.green),
          _RuleSwitch(icon: Icons.account_balance_outlined, title: 'Ueberweisung erlauben', body: 'Mitglieder erhalten spaeter Zahlungsdaten, Verwendungszweck und Fälligkeitsdatum.', value: bankTransferAllowed, onChanged: onBank, color: AirmiusColors.blue),
          _RuleSwitch(icon: Icons.fact_check_outlined, title: 'SEPA erlauben', body: 'SEPA-Mandat kann als Pflichtdokument mit Mitgliedsantrag verknuepft werden.', value: sepaAllowed, onChanged: onSepa, color: AirmiusColors.amber),
          _RuleSwitch(icon: Icons.receipt_long_outlined, title: 'Rechnung automatisch erstellen', body: 'Nach Annahme der Mitgliedschaft wird die erste Rechnung oder Zahlungsaufgabe erzeugt.', value: invoiceAutoCreate, onChanged: onInvoice, color: AirmiusColors.green),
          _RuleSwitch(icon: Icons.notifications_active_outlined, title: 'Mahnung / Erinnerung aktivieren', body: 'Offene Zahlungen koennen Push, E-Mail oder Adminhinweis ausloesen.', value: remindersEnabled, onChanged: onReminder, color: AirmiusColors.blue),
          _RuleSwitch(icon: Icons.family_restroom_outlined, title: 'Familienrabatt', body: 'Rabattregeln fuer Geschwister, Familien oder Haushalte koennen spaeter hinterlegt werden.', value: familyDiscount, onChanged: onFamily, color: AirmiusColors.amber),
          _RuleSwitch(icon: Icons.calendar_month_outlined, title: 'Probemonat', body: 'Mitgliedschaft kann mit kostenfreiem oder reduziertem Einstieg starten.', value: trialMonth, onChanged: onTrial, color: AirmiusColors.green),
        ]),
      );
}

class _ContributionRuleCard extends StatelessWidget {
  const _ContributionRuleCard({required this.rule});

  final _ContributionRule rule;

  @override
  Widget build(BuildContext context) => AirmiusPanel(
        borderColor: rule.color.withValues(alpha: .44),
        child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Container(width: 50, height: 50, decoration: BoxDecoration(color: rule.color.withValues(alpha: .13), borderRadius: BorderRadius.circular(16), border: Border.all(color: rule.color.withValues(alpha: .45))), child: Icon(rule.icon, color: rule.color)),
            const SizedBox(width: 14),
            Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text(rule.title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900, fontSize: 16)),
              const SizedBox(height: 5),
              Text(rule.body, style: const TextStyle(color: AirmiusColors.muted, height: 1.35)),
              const SizedBox(height: 10),
              Wrap(spacing: 8, runSpacing: 8, children: [StatusPill(rule.area, color: rule.color), StatusPill(rule.status), StatusPill(rule.amount, color: rule.color)]),
            ])),
          ]),
          const SizedBox(height: 12),
          Wrap(spacing: 8, runSpacing: 8, children: [
            AirmiusButton(label: 'Regel bearbeiten', icon: Icons.edit_outlined, onPressed: () => openUiAction(context, title: '${rule.title} bearbeiten', body: 'Beitrag, Rhythmus, Methode, Faelligkeit, Rabatte, Rechnung und Sichtbarkeit fuer diesen Mitgliedschaftstyp bearbeiten.', status: 'Beitragsregel', icon: Icons.edit_outlined)),
            AirmiusButton(label: 'Billing', icon: Icons.receipt_long_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => BillingOperationsScreen()))),
            AirmiusButton(label: 'Mitgliedschaft', icon: Icons.assignment_ind_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => MembershipOperationsScreen()))),
          ]),
        ]),
      );
}

class _ContributionWorkflowPanel extends StatelessWidget {
  const _ContributionWorkflowPanel({required this.frequency, required this.method});

  final String frequency;
  final String method;

  @override
  Widget build(BuildContext context) => AirmiusPanel(
        borderColor: AirmiusColors.green.withValues(alpha: .44),
        child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          const Eyebrow('Mitgliedschafts-Workflow'),
          const SizedBox(height: 8),
          Text('Aktuelle Regel: $frequency per $method. Spaeter erzeugt Laravel daraus Mitgliedschaftsstatus, Zahlungsaufgabe, Rechnung, Mahnung, Dokumentpflicht und Admin-Audit.', style: const TextStyle(color: AirmiusColors.muted, height: 1.35)),
          const SizedBox(height: 12),
          Wrap(spacing: 8, runSpacing: 8, children: [
            AirmiusButton(label: 'Regeln speichern', icon: Icons.save_outlined, onPressed: () => openUiAction(context, title: 'Beitragsregeln speichern', body: 'Zahlungsrhythmus $frequency, Methode $method, Rabatte, Rechnung, Mahnungen und Dokumentpflicht fuer Verein speichern.', status: 'Beitragsregeln', icon: Icons.save_outlined)),
            AirmiusButton(label: 'Dokumente', icon: Icons.rule_folder_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => ClubPolicyDocumentsScreen(initialTab: 'Beitrag')))),
          ]),
        ]),
      );
}

class _RuleSwitch extends StatelessWidget {
  const _RuleSwitch({required this.icon, required this.title, required this.body, required this.value, required this.onChanged, required this.color});

  final IconData icon;
  final String title;
  final String body;
  final bool value;
  final ValueChanged<bool> onChanged;
  final Color color;

  @override
  Widget build(BuildContext context) => SwitchListTile(
        value: value,
        onChanged: onChanged,
        activeColor: color,
        contentPadding: EdgeInsets.zero,
        secondary: Icon(icon, color: color),
        title: Text(title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
        subtitle: Text(body, style: const TextStyle(color: AirmiusColors.muted, height: 1.3)),
      );
}

class _ContributionRule {
  const _ContributionRule({required this.area, required this.title, required this.body, required this.status, required this.amount, required this.icon, required this.color});

  final String area;
  final String title;
  final String body;
  final String status;
  final String amount;
  final IconData icon;
  final Color color;
}

const _tabs = ['Regeln', 'Typen', 'Zahlung', 'Rabatte', 'Mahnungen'];

const _rules = <_ContributionRule>[
  _ContributionRule(area: 'Regeln', title: 'Standard-Mitgliedschaft', body: 'Grundbeitrag fuer normale Mitglieder mit monatlicher, halbjaehrlicher oder jaehrlicher Zahlung.', status: 'Aktiv', amount: '25 EUR', icon: Icons.person_outline, color: AirmiusColors.green),
  _ContributionRule(area: 'Regeln', title: 'Aufnahmegebuehr', body: 'Einmalige Gebuehr beim Beitritt, optional nach Annahme der Mitgliedschaft automatisch faellig.', status: 'Optional', amount: '15 EUR', icon: Icons.add_card_outlined, color: AirmiusColors.amber),
  _ContributionRule(area: 'Typen', title: 'Jugend / Minderjaehrige', body: 'Reduzierter Beitrag mit Guardian Consent, Elternkontakt und optionaler SEPA-Pflicht.', status: 'Guardian', amount: '12 EUR', icon: Icons.family_restroom_outlined, color: AirmiusColors.blue),
  _ContributionRule(area: 'Typen', title: 'Trainer / Ehrenamt', body: 'Sonderstatus fuer Trainer, Admins oder Ehrenamtliche mit reduziertem oder kostenlosem Beitrag.', status: 'Sonderregel', amount: '0 EUR', icon: Icons.sports_outlined, color: AirmiusColors.green),
  _ContributionRule(area: 'Zahlung', title: 'Bankueberweisung', body: 'IBAN, BIC, Verwendungszweck, Faelligkeit und manueller Admin-Abgleich.', status: 'Erlaubt', amount: 'Manual', icon: Icons.account_balance_outlined, color: AirmiusColors.blue),
  _ContributionRule(area: 'Zahlung', title: 'Barzahlung', body: 'Barzahlung mit Adminnotiz, Quittung, Zahlungsdatum und optionalem Beleg.', status: 'Erlaubt', amount: 'Cash', icon: Icons.payments_outlined, color: AirmiusColors.green),
  _ContributionRule(area: 'Zahlung', title: 'SEPA-Mandat', body: 'SEPA als Dokumentpflicht im Antrag, Mandatsreferenz und spaeterer Einzug.', status: 'Vorbereitet', amount: 'SEPA', icon: Icons.fact_check_outlined, color: AirmiusColors.amber),
  _ContributionRule(area: 'Rabatte', title: 'Familienrabatt', body: 'Rabatt fuer weitere Mitglieder im selben Haushalt oder fuer Geschwister.', status: 'Optional', amount: '-20%', icon: Icons.diversity_1_outlined, color: AirmiusColors.green),
  _ContributionRule(area: 'Rabatte', title: 'Probemonat', body: 'Kostenloser oder reduzierter Einstiegsmonat mit automatischem Uebergang in regulaeren Beitrag.', status: 'Optional', amount: '1 Monat', icon: Icons.calendar_month_outlined, color: AirmiusColors.blue),
  _ContributionRule(area: 'Mahnungen', title: 'Zahlungserinnerung', body: 'Push, E-Mail oder Adminhinweis bei offenen Zahlungen nach Faelligkeit.', status: 'Aktiv', amount: '7 Tage', icon: Icons.notifications_active_outlined, color: AirmiusColors.amber),
  _ContributionRule(area: 'Mahnungen', title: 'Mitgliedschaft pausieren', body: 'Admin kann bei Zahlungsverzug Status, Teamrechte und Kommunikation steuern.', status: 'Admin', amount: 'Status', icon: Icons.pause_circle_outline, color: AirmiusColors.red),
];
