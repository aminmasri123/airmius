import 'package:flutter/material.dart';

import '../core/airmius_l10n.dart';
import '../core/airmius_theme.dart';

class ContentReportDraft {
  const ContentReportDraft({required this.reason, required this.details});

  final String reason;
  final String details;
}

const _contentReportReasons = <MapEntry<String, String>>[
  MapEntry('insult', 'moderation.report.reason.insult'),
  MapEntry('bullying', 'moderation.report.reason.bullying'),
  MapEntry('hate', 'moderation.report.reason.hate'),
  MapEntry('sexual', 'moderation.report.reason.sexual'),
  MapEntry('violence', 'moderation.report.reason.violence'),
  MapEntry('threat', 'moderation.report.reason.threat'),
  MapEntry('image_rights', 'moderation.report.reason.imageRights'),
  MapEntry('spam', 'moderation.report.reason.spam'),
  MapEntry('other', 'moderation.report.reason.other'),
];

Future<ContentReportDraft?> showContentReportDialog(
  BuildContext context, {
  required String title,
}) async {
  final detailsController = TextEditingController();
  var reason = 'insult';
  final t = AirmiusScope.of(context).t;
  final surface = airmiusSurfaceColor(context);
  final text = airmiusTextColor(context);
  final muted = airmiusMutedColor(context);

  final result = await showDialog<ContentReportDraft>(
    context: context,
    builder: (dialogContext) {
      return StatefulBuilder(
        builder: (dialogContext, setDialogState) {
          return AlertDialog(
            backgroundColor: surface,
            surfaceTintColor: Colors.transparent,
            title: Text(
              title,
              style: TextStyle(color: text, fontWeight: FontWeight.w900),
            ),
            content: SingleChildScrollView(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Text(
                    t('moderation.report.explanation'),
                    style: TextStyle(
                      color: muted,
                      fontWeight: FontWeight.w800,
                      height: 1.35,
                    ),
                  ),
                  const SizedBox(height: 14),
                  DropdownButtonFormField<String>(
                    initialValue: reason,
                    decoration: InputDecoration(
                      labelText: t('moderation.report.reason'),
                    ),
                    dropdownColor: surface,
                    items: [
                      for (final option in _contentReportReasons)
                        DropdownMenuItem<String>(
                          value: option.key,
                          child: Text(t(option.value)),
                        ),
                    ],
                    onChanged: (value) =>
                        setDialogState(() => reason = value ?? 'other'),
                  ),
                  const SizedBox(height: 12),
                  TextField(
                    controller: detailsController,
                    maxLines: 4,
                    style: TextStyle(color: text, fontWeight: FontWeight.w700),
                    decoration: InputDecoration(
                      labelText: t('moderation.report.details'),
                      hintText: t('moderation.report.detailsHint'),
                    ),
                  ),
                ],
              ),
            ),
            actions: [
              TextButton(
                onPressed: () => Navigator.pop(dialogContext),
                child: Text(t('moderation.report.cancel')),
              ),
              FilledButton.icon(
                onPressed: () {
                  Navigator.pop(
                    dialogContext,
                    ContentReportDraft(
                      reason: reason,
                      details: detailsController.text.trim(),
                    ),
                  );
                },
                icon: const Icon(Icons.flag_outlined, size: 18),
                label: Text(t('moderation.report.submit')),
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
