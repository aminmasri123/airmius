import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';

class ContentReportDraft {
  const ContentReportDraft({required this.reason, required this.details});

  final String reason;
  final String details;
}

const _contentReportReasons = <MapEntry<String, String>>[
  MapEntry('insult', 'Beleidigung'),
  MapEntry('bullying', 'Mobbing oder Belaestigung'),
  MapEntry('hate', 'Hassrede'),
  MapEntry('sexual', 'Sexueller Inhalt'),
  MapEntry('violence', 'Gewalt'),
  MapEntry('threat', 'Drohung'),
  MapEntry('image_rights', 'Bildrechte / Persoenlichkeitsrechte'),
  MapEntry('spam', 'Spam oder Betrug'),
  MapEntry('other', 'Sonstiges'),
];

Future<ContentReportDraft?> showContentReportDialog(
  BuildContext context, {
  required String title,
}) async {
  final detailsController = TextEditingController();
  var reason = 'insult';

  final result = await showDialog<ContentReportDraft>(
    context: context,
    builder: (dialogContext) {
      return StatefulBuilder(
        builder: (dialogContext, setDialogState) {
          return AlertDialog(
            backgroundColor: AirmiusColors.card,
            surfaceTintColor: Colors.transparent,
            title: Text(title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
            content: SingleChildScrollView(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Text(
                    'Warum soll dieser Inhalt geprueft werden?',
                    style: TextStyle(color: AirmiusColors.muted, fontWeight: FontWeight.w800, height: 1.35),
                  ),
                  const SizedBox(height: 14),
                  DropdownButtonFormField<String>(
                    initialValue: reason,
                    decoration: const InputDecoration(labelText: 'Grund'),
                    dropdownColor: AirmiusColors.card,
                    items: [
                      for (final option in _contentReportReasons)
                        DropdownMenuItem<String>(
                          value: option.key,
                          child: Text(option.value),
                        ),
                    ],
                    onChanged: (value) => setDialogState(() => reason = value ?? 'other'),
                  ),
                  const SizedBox(height: 12),
                  TextField(
                    controller: detailsController,
                    maxLines: 4,
                    style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w700),
                    decoration: const InputDecoration(
                      labelText: 'Details',
                      hintText: 'Optional: Was ist dir aufgefallen?',
                    ),
                  ),
                ],
              ),
            ),
            actions: [
              TextButton(
                onPressed: () => Navigator.pop(dialogContext),
                child: const Text('Abbrechen'),
              ),
              FilledButton.icon(
                onPressed: () {
                  Navigator.pop(
                    dialogContext,
                    ContentReportDraft(reason: reason, details: detailsController.text.trim()),
                  );
                },
                icon: const Icon(Icons.flag_outlined, size: 18),
                label: const Text('Meldung senden'),
              ),
            ],
          );
        },
      );
    },
  );

  detailsController.dispose();
  return result;
}
