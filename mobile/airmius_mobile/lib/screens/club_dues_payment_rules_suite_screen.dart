import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

class ClubDuesPaymentRulesSuiteScreen extends StatefulWidget {
  const ClubDuesPaymentRulesSuiteScreen({super.key});

  @override
  State<ClubDuesPaymentRulesSuiteScreen> createState() => _ClubDuesPaymentRulesSuiteScreenState();
}

class _ClubDuesPaymentRulesSuiteScreenState extends State<ClubDuesPaymentRulesSuiteScreen> {
  String cycle = 'Jaehrlich';
  String method = 'Überweisung';
  bool showPublicFees = true;
  bool requireSepaMandate = false;
  bool allowCash = true;
  bool attachFeeRules = true;

  @override
  Widget build(BuildContext context) {
    final plans = [
      const _DuesPlan(name: 'Erwachsene', price: '120 EUR', rhythm: 'Jaehrlich', body: 'Standardbeitrag für aktive Mitglieder ab 18 Jahren.', color: AirmiusColors.blue),
      const _DuesPlan(name: 'Jugend', price: '60 EUR', rhythm: 'Jaehrlich', body: 'Reduzierter Beitrag mit optionaler Guardian-Zustimmung.', color: AirmiusColors.green),
      const _DuesPlan(name: 'Foerdermitglied', price: 'frei', rhythm: 'Flexibel', body: 'Frei wählbarer Foerderbetrag mit Vereinsfreigabe.', color: AirmiusColors.amber),
    ];

    return PageFrame(
      title: 'Beitragsregeln',
      subtitle: 'Zahlungsarten, Zyklen und Dokumente',
      actions: const [AirmiusLogoMark(size: 34)],
      child: ListView(
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 28),
        children: [
          AirmiusPanel(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const SectionLabel('VEREINSBEITRAEGE'),
                const SizedBox(height: 8),
                const Text(
                  'Vereine können mobil festlegen, welche Beitragsgruppen gelten, wie oft gezahlt wird, welche Zahlarten erlaubt sind und welche Dokumente mit den Regeln verknuepft werden.',
                  style: TextStyle(color: AirmiusColors.text, height: 1.45, fontWeight: FontWeight.w800),
                ),
                const SizedBox(height: 16),
                GridWrap(
                  children: const [
                    Metric(value: '3', label: 'Plaene'),
                    Metric(value: '4', label: 'Zyklen'),
                    Metric(value: '3', label: 'Zahlarten'),
                    Metric(value: 'Docs', label: 'Regeln'),
                  ],
                ),
              ],
            ),
          ),
          const SizedBox(height: 14),
          AirmiusPanel(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const SectionLabel('STANDARDREGEL'),
                const SizedBox(height: 12),
                SegmentedButton<String>(
                  segments: const [
                    ButtonSegment(value: 'Monatlich', label: Text('Monat')),
                    ButtonSegment(value: '4 Monate', label: Text('4 Mon.')),
                    ButtonSegment(value: '6 Monate', label: Text('6 Mon.')),
                    ButtonSegment(value: 'Jaehrlich', label: Text('Jahr')),
                  ],
                  selected: {cycle},
                  onSelectionChanged: (value) => setState(() => cycle = value.first),
                ),
                const SizedBox(height: 14),
                SegmentedButton<String>(
                  segments: const [
                    ButtonSegment(value: 'Überweisung', label: Text('Überweisung')),
                    ButtonSegment(value: 'Bar', label: Text('Bar')),
                    ButtonSegment(value: 'SEPA', label: Text('SEPA')),
                  ],
                  selected: {method},
                  onSelectionChanged: (value) => setState(() => method = value.first),
                ),
              ],
            ),
          ),
          const SizedBox(height: 14),
          for (final plan in plans) ...[
            _PlanCard(plan: plan),
            const SizedBox(height: 12),
          ],
          AirmiusPanel(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const SectionLabel('OPTIONEN'),
                const SizedBox(height: 8),
                _OptionSwitch(
                  title: 'Beiträge öffentlich anzeigen',
                  body: 'User sehen vor dem Antrag, welche Beitragsgruppen der Verein anbietet.',
                  value: showPublicFees,
                  color: AirmiusColors.blue,
                  onChanged: (value) => setState(() => showPublicFees = value),
                ),
                _OptionSwitch(
                  title: 'SEPA-Mandat verlangen',
                  body: 'Bei Lastschrift wird das Mandat im Antrag als Pflichtdokument vorbereitet.',
                  value: requireSepaMandate,
                  color: AirmiusColors.green,
                  onChanged: (value) => setState(() => requireSepaMandate = value),
                ),
                _OptionSwitch(
                  title: 'Barzahlung erlauben',
                  body: 'Verein kann Barzahlung für bestimmte Gruppen oder Sonderfaelle zulassen.',
                  value: allowCash,
                  color: AirmiusColors.amber,
                  onChanged: (value) => setState(() => allowCash = value),
                ),
                _OptionSwitch(
                  title: 'Beitragsordnung verknuepfen',
                  body: 'PDF oder Link wird später im Vereins-Dateimanager gespeichert und im Antrag bestätigt.',
                  value: attachFeeRules,
                  color: AirmiusColors.pink,
                  onChanged: (value) => setState(() => attachFeeRules = value),
                ),
              ],
            ),
          ),
          const SizedBox(height: 14),
          AirmiusPanel(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const SectionLabel('VORSCHAU'),
                const SizedBox(height: 8),
                Text(
                  'Standard: $cycle per $method. Sichtbare Beiträge und Dokumentpflichten werden später direkt in den Mitgliedschaftsantrag übernommen.',
                  style: const TextStyle(color: AirmiusColors.muted, height: 1.45, fontWeight: FontWeight.w700),
                ),
                const SizedBox(height: 14),
                AirmiusButton(
                  label: 'Beitragsregel prüfen',
                  icon: Icons.receipt_long_outlined,
                  onPressed: () => openUiAction(
                    context,
                    title: 'Beitragsregel',
                    body: 'Diese mobile UI bereitet Beitragsgruppen, Zahlungszyklen, Zahlungsarten, SEPA, Barzahlung und Dokumentverknuepfung für die spätere API vor.',
                    status: 'UI vorbereitet',
                    icon: Icons.receipt_long_outlined,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _DuesPlan {
  const _DuesPlan({
    required this.name,
    required this.price,
    required this.rhythm,
    required this.body,
    required this.color,
  });

  final String name;
  final String price;
  final String rhythm;
  final String body;
  final Color color;
}

class _PlanCard extends StatelessWidget {
  const _PlanCard({required this.plan});

  final _DuesPlan plan;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          IconBadge(icon: Icons.price_change_outlined, color: plan.color),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Expanded(child: Text(plan.name, style: const TextStyle(color: AirmiusColors.text, fontSize: 17, fontWeight: FontWeight.w900))),
                    StatusPill(plan.price, color: plan.color),
                  ],
                ),
                const SizedBox(height: 4),
                Text(plan.rhythm, style: const TextStyle(color: AirmiusColors.blue, fontWeight: FontWeight.w900)),
                const SizedBox(height: 8),
                Text(plan.body, style: const TextStyle(color: AirmiusColors.muted, height: 1.42, fontWeight: FontWeight.w700)),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _OptionSwitch extends StatelessWidget {
  const _OptionSwitch({
    required this.title,
    required this.body,
    required this.value,
    required this.color,
    required this.onChanged,
  });

  final String title;
  final String body;
  final bool value;
  final Color color;
  final ValueChanged<bool> onChanged;

  @override
  Widget build(BuildContext context) {
    return SwitchListTile.adaptive(
      contentPadding: EdgeInsets.zero,
      title: Text(title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
      subtitle: Text(body, style: const TextStyle(color: AirmiusColors.muted, height: 1.35, fontWeight: FontWeight.w700)),
      value: value,
      activeColor: color,
      onChanged: onChanged,
    );
  }
}
