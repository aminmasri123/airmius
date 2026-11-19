import 'package:flutter/material.dart';
import '../core/airmius_l10n.dart';
import '../core/learning_deletion_copy.dart';

String learningDeletionText(BuildContext context, String key) =>
    (learningDeletionCopy[AirmiusScope.of(context).language.name] ??
    learningDeletionCopy['en']!)[key]!;

Future<bool> confirmLearningDeletion(
  BuildContext context, {
  required String title,
  required bool course,
}) async {
  var confirmation = '';
  return await showDialog<bool>(
        context: context,
        builder: (context) => StatefulBuilder(
          builder: (context, setState) => AlertDialog(
            title: Text(
              learningDeletionText(
                context,
                course ? 'remove_course' : 'remove_section',
              ),
            ),
            content: SingleChildScrollView(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Text(title, style: Theme.of(context).textTheme.titleMedium),
                  const SizedBox(height: 12),
                  Text(
                    learningDeletionText(
                      context,
                      course ? 'warn_course' : 'warn_section',
                    ),
                  ),
                  const SizedBox(height: 12),
                  Text(learningDeletionText(context, 'confirm_hint')),
                  TextField(
                    key: const ValueKey('learning-delete-confirmation'),
                    textDirection: TextDirection.ltr,
                    autocorrect: false,
                    onChanged: (value) => setState(() => confirmation = value),
                  ),
                ],
              ),
            ),
            actions: [
              TextButton(
                onPressed: () => Navigator.pop(context, false),
                child: Text(learningDeletionText(context, 'cancel')),
              ),
              FilledButton(
                key: const ValueKey('learning-delete-confirm'),
                style: FilledButton.styleFrom(
                  backgroundColor: Theme.of(context).colorScheme.error,
                ),
                onPressed: confirmation == 'DELETE'
                    ? () => Navigator.pop(context, true)
                    : null,
                child: Text(learningDeletionText(context, 'delete')),
              ),
            ],
          ),
        ),
      ) ??
      false;
}
