import 'package:flutter/material.dart';
import 'airmius_l10n.dart';

String coachPeriodText(BuildContext context, String key) {
  final language = AirmiusScope.of(context).language.locale.languageCode;
  return (_copy[language] ?? _copy['en']!)[key] ?? key;
}

const _copy = {
  'de': {
    'title': 'Trainingsübersicht',
    'week': 'Woche',
    'month': 'Monat',
    'year': 'Jahr',
    'weekBody':
        'Letzte 7 Tage · abgeschlossene Trainingseinträge der betreuten Teams.',
    'monthBody':
        'Letzte 30 Tage · abgeschlossene Trainingseinträge der betreuten Teams.',
    'yearBody':
        'Letzte 365 Tage · abgeschlossene Trainingseinträge der betreuten Teams.',
    'sessions': 'Trainingseinträge',
    'trend': 'Einträge im Vergleich',
    'rpe': 'Ø Anstrengung (RPE 1–10)',
    'comparison': 'Vergleichszeitraum',
    'noComparison': 'Kein Vergleich verfügbar',
    'empty': 'Noch keine Trainingsdaten',
    'emptyBody':
        'Für diesen Zeitraum liegen keine sichtbaren abgeschlossenen Trainingseinträge vor.',
    'riskEmpty': 'Keine Belastungseinschätzung möglich',
    'riskEmptyBody':
        'Für die letzten 7 Tage fehlen abgeschlossene Trainingsdaten. Daraus lässt sich keine Entwarnung ableiten.',
    'riskBody':
        'Hinweise aus den letzten 7 Tagen. Fehlende Angaben sind keine Entwarnung.',
  },
  'en': {
    'title': 'Training overview',
    'week': 'Week',
    'month': 'Month',
    'year': 'Year',
    'weekBody': 'Last 7 days · completed training entries for managed teams.',
    'monthBody': 'Last 30 days · completed training entries for managed teams.',
    'yearBody': 'Last 365 days · completed training entries for managed teams.',
    'sessions': 'Training entries',
    'trend': 'Entry count comparison',
    'rpe': 'Average effort (RPE 1–10)',
    'comparison': 'Comparison period',
    'noComparison': 'No comparison available',
    'empty': 'No training data yet',
    'emptyBody': 'No visible completed training entries for this period.',
    'riskEmpty': 'Workload cannot be assessed',
    'riskEmptyBody':
        'No completed training data for the last 7 days. This does not imply an all-clear.',
    'riskBody':
        'Signals from the last 7 days. Missing information does not imply an all-clear.',
  },
  'fr': {
    'title': 'Bilan des entraînements',
    'week': 'Semaine',
    'month': 'Mois',
    'year': 'Année',
    'weekBody':
        '7 derniers jours · entraînements terminés des équipes encadrées.',
    'monthBody':
        '30 derniers jours · entraînements terminés des équipes encadrées.',
    'yearBody':
        '365 derniers jours · entraînements terminés des équipes encadrées.',
    'sessions': 'Entrées d’entraînement',
    'trend': 'Comparaison du nombre',
    'rpe': 'Effort moyen (RPE 1–10)',
    'comparison': 'Période de comparaison',
    'noComparison': 'Comparaison indisponible',
    'empty': 'Aucune donnée d’entraînement',
    'emptyBody': 'Aucun entraînement terminé et visible pour cette période.',
    'riskEmpty': 'Charge impossible à évaluer',
    'riskEmptyBody':
        'Aucune donnée d’entraînement terminé sur les 7 derniers jours. Cela ne signifie pas une absence de risque.',
    'riskBody':
        'Signaux des 7 derniers jours. Des données manquantes ne signifient pas une absence de risque.',
  },
  'ar': {
    'title': 'نظرة عامة على التدريب',
    'week': 'أسبوع',
    'month': 'شهر',
    'year': 'سنة',
    'weekBody': 'آخر 7 أيام · سجلات التدريب المكتملة للفرق المُدارة.',
    'monthBody': 'آخر 30 يوماً · سجلات التدريب المكتملة للفرق المُدارة.',
    'yearBody': 'آخر 365 يوماً · سجلات التدريب المكتملة للفرق المُدارة.',
    'sessions': 'سجلات التدريب',
    'trend': 'مقارنة عدد السجلات',
    'rpe': 'متوسط الجهد (RPE 1–10)',
    'comparison': 'فترة المقارنة',
    'noComparison': 'لا تتوفر مقارنة',
    'empty': 'لا توجد بيانات تدريب',
    'emptyBody': 'لا توجد سجلات تدريب مكتملة ومرئية لهذه الفترة.',
    'riskEmpty': 'لا يمكن تقييم الحمل',
    'riskEmptyBody':
        'لا توجد بيانات تدريب مكتملة لآخر 7 أيام. هذا لا يعني عدم وجود مخاطر.',
    'riskBody': 'مؤشرات آخر 7 أيام. نقص المعلومات لا يعني عدم وجود مخاطر.',
  },
};
