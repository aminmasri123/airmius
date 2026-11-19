import 'package:flutter/material.dart';

class LearningCourseCover extends StatelessWidget {
  const LearningCourseCover({
    super.key,
    required this.url,
    required this.title,
  });
  final String? url;
  final String title;
  @override
  Widget build(BuildContext context) {
    final fallback = Container(
      decoration: BoxDecoration(
        gradient: LinearGradient(
          colors: [
            Theme.of(context).colorScheme.primaryContainer,
            Theme.of(context).colorScheme.secondaryContainer,
          ],
        ),
      ),
      child: Center(
        child: Icon(
          Icons.auto_stories_outlined,
          size: 56,
          color: Theme.of(context).colorScheme.onPrimaryContainer,
        ),
      ),
    );
    return ClipRRect(
      borderRadius: BorderRadius.circular(16),
      child: AspectRatio(
        aspectRatio: 16 / 9,
        child: url == null || url!.isEmpty
            ? fallback
            : Image.network(
                url!,
                fit: BoxFit.cover,
                semanticLabel: title,
                errorBuilder: (_, error, stack) => fallback,
              ),
      ),
    );
  }
}
