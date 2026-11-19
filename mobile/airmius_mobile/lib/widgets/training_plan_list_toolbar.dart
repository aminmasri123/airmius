import 'package:flutter/material.dart';
import '../core/airmius_l10n.dart';

String planListText(BuildContext context, String key) {
  const copy = {
    'de': {
      'search': 'Pläne suchen',
      'all': 'Alle Status',
      'reset': 'Filter zurücksetzen',
      'empty': 'Keine passenden Trainingspläne.',
    },
    'en': {
      'search': 'Search plans',
      'all': 'All statuses',
      'reset': 'Reset filters',
      'empty': 'No matching training plans.',
    },
    'fr': {
      'search': 'Rechercher un plan',
      'all': 'Tous les statuts',
      'reset': 'Réinitialiser les filtres',
      'empty': 'Aucun plan correspondant.',
    },
    'ar': {
      'search': 'البحث عن خطط',
      'all': 'كل الحالات',
      'reset': 'إعادة تعيين المرشحات',
      'empty': 'لا توجد خطط تدريب مطابقة.',
    },
  };
  return (copy[AirmiusScope.of(context).language.locale.languageCode] ??
          copy['en'])![key] ??
      key;
}

bool matchesTrainingPlan({
  required String text,
  required String status,
  required String query,
  required String selectedStatus,
}) =>
    (selectedStatus.isEmpty || selectedStatus == status) &&
    text.toLowerCase().contains(query.trim().toLowerCase());

class TrainingPlanListToolbar extends StatefulWidget {
  const TrainingPlanListToolbar({
    super.key,
    required this.query,
    required this.status,
    required this.counts,
    required this.onQuery,
    required this.onStatus,
    required this.onReset,
  });
  final String query, status;
  final Map<String, int> counts;
  final ValueChanged<String> onQuery, onStatus;
  final VoidCallback onReset;
  @override
  State<TrainingPlanListToolbar> createState() =>
      _TrainingPlanListToolbarState();
}

class _TrainingPlanListToolbarState extends State<TrainingPlanListToolbar> {
  late final _search = TextEditingController(text: widget.query);
  @override
  void didUpdateWidget(covariant TrainingPlanListToolbar oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (_search.text != widget.query)
      _search.value = TextEditingValue(
        text: widget.query,
        selection: TextSelection.collapsed(offset: widget.query.length),
      );
  }

  @override
  void dispose() {
    _search.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final query = widget.query,
        status = widget.status,
        counts = widget.counts,
        onQuery = widget.onQuery,
        onStatus = widget.onStatus,
        onReset = widget.onReset;
    final t = AirmiusScope.of(context).t;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        TextFormField(
          controller: _search,
          onChanged: onQuery,
          decoration: InputDecoration(
            isDense: true,
            labelText: planListText(context, 'search'),
            prefixIcon: const Icon(Icons.search_rounded),
            contentPadding: const EdgeInsets.symmetric(
              horizontal: 14,
              vertical: 12,
            ),
          ),
        ),
        const SizedBox(height: 12),
        DropdownButtonFormField<String>(
          key: ValueKey('plan-status-$status'),
          initialValue: status,
          isExpanded: true,
          decoration: InputDecoration(
            isDense: true,
            labelText: t('trainingHub.status'),
            contentPadding: const EdgeInsets.symmetric(
              horizontal: 14,
              vertical: 12,
            ),
          ),
          items: [
            DropdownMenuItem(
              value: '',
              child: Text(
                '${planListText(context, 'all')} (${counts.values.fold(0, (a, b) => a + b)})',
              ),
            ),
            for (final entry in counts.entries)
              DropdownMenuItem(
                value: entry.key,
                child: Text(
                  '${t('trainingHub.status.${entry.key}')} (${entry.value})',
                ),
              ),
          ],
          onChanged: (v) => onStatus(v ?? ''),
        ),
        if (query.isNotEmpty || status.isNotEmpty)
          Align(
            alignment: AlignmentDirectional.centerStart,
            child: TextButton(
              onPressed: onReset,
              child: Text(planListText(context, 'reset')),
            ),
          ),
      ],
    );
  }
}
