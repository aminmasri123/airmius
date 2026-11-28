import 'package:flutter/material.dart';

/// Review information travels with the report and is frozen on year close.
class ClubYearReportReview extends StatelessWidget {
  const ClubYearReportReview({super.key, required this.report});

  final Map<String, dynamic> report;

  @override
  Widget build(BuildContext context) {
    final language = Localizations.localeOf(context).languageCode;
    final labels = _labels[language] ?? _labels['en']!;
    final period = report['period'] as Map? ?? {};
    final comparison = report['comparison'] as Map?;
    final previous = comparison?['period'] as Map? ?? {};
    final entries =
        (comparison?['summary'] as Map?)?['finance_entries'] as Map? ?? {};
    final mismatch =
        (report['reconciliation'] as Map?)?['mismatched_entry_count'];
    String money(dynamic value) =>
        '${(num.tryParse('$value') ?? 0).toStringAsFixed(2)} €';
    String date(dynamic value) => '$value'.split('T').first;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text('${date(period['starts_on'])} – ${date(period['ends_on'])}'),
        Text(
          report['closed_at'] == null
              ? labels[0]
              : '${labels[1]} ${date(report['closed_at'])}',
        ),
        const SizedBox(height: 8),
        Text(labels[2]),
        if (mismatch != null) Text('${labels[3]}: $mismatch'),
        const Divider(),
        if (comparison == null)
          Text(labels[4])
        else ...[
          Text(
            '${labels[5]}: ${previous['name']}',
            style: const TextStyle(fontWeight: FontWeight.bold),
          ),
          Text('${date(previous['starts_on'])} – ${date(previous['ends_on'])}'),
          Text('${labels[6]}: ${money(entries['income_amount'])}'),
          Text('${labels[7]}: ${money(entries['expense_amount'])}'),
          Text('${labels[8]}: ${money(entries['net_amount'])}'),
          Text(
            '${labels[9]}: ${money((num.tryParse('${comparison['net_change_cents']}') ?? 0) / 100)}',
          ),
        ],
        const Divider(),
      ],
    );
  }

  static const _labels = {
    'de': [
      'Vorläufiger Bericht',
      'Abgeschlossen · Stand:',
      'Buchungssaldo: Einnahmen minus Ausgaben ohne Eröffnungen und Umbuchungen. Rechnungen und Bankimporte sind separate Sichten; nicht addieren.',
      'Buchungen mit abweichendem Datum/Jahr (vor Abschluss klären)',
      'Kein Vorperiodenvergleich verfügbar.',
      'Vorperiode',
      'Einnahmen',
      'Ausgaben',
      'Buchungssaldo',
      'Änderung des Saldos zur Vorperiode',
    ],
    'en': [
      'Provisional report',
      'Closed · As of:',
      'Entry balance: income minus expenses, excluding openings and transfers. Invoices and bank imports are separate views; do not add them together.',
      'Entries with mismatched date/year (resolve before closing)',
      'No previous period comparison available.',
      'Previous period',
      'Income',
      'Expenses',
      'Entry balance',
      'Balance change from previous period',
    ],
    'fr': [
      'Rapport provisoire',
      'Clôturé · Au :',
      'Solde des écritures : recettes moins dépenses, hors ouvertures et virements internes. Factures et imports bancaires sont des vues distinctes ; ne pas les additionner.',
      'Écritures avec date/exercice incohérents (à résoudre avant clôture)',
      'Aucune comparaison avec une période précédente disponible.',
      'Période précédente',
      'Recettes',
      'Dépenses',
      'Solde des écritures',
      'Variation du solde depuis la période précédente',
    ],
    'ar': [
      'تقرير مؤقت',
      'مغلق · بتاريخ:',
      'رصيد القيود: الإيرادات ناقص المصروفات باستثناء الأرصدة الافتتاحية والتحويلات الداخلية. الفواتير والواردات البنكية عروض منفصلة؛ لا تجمعها.',
      'قيود بتاريخ وسنة غير متطابقين (تُصحح قبل الإغلاق)',
      'لا تتوفر مقارنة مع فترة سابقة.',
      'الفترة السابقة',
      'الإيرادات',
      'المصروفات',
      'رصيد القيود',
      'تغير الرصيد عن الفترة السابقة',
    ],
  };
}
