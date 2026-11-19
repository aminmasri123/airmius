import 'package:flutter/material.dart';
import '../core/learning_experience_copy.dart';

Future<bool> confirmLearningParticipation(
  BuildContext context,
  String title,
  String action,
) async =>
    await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(learningText(context, action)),
        content: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text(title, style: const TextStyle(fontWeight: FontWeight.bold)),
              const SizedBox(height: 12),
              Text(learningText(context, '${action}_hint')),
            ],
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: Text(learningText(context, 'cancel')),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, true),
            child: Text(learningText(context, 'confirm')),
          ),
        ],
      ),
    ) ??
    false;
