import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';
import '../core/airmius_external_url.dart';
import '../core/airmius_l10n.dart';
import '../core/exercise_content.dart';
import '../core/exercise_content_copy.dart';

class ExerciseContentView extends StatelessWidget {
  const ExerciseContentView({super.key, required this.content});
  final Map<String, dynamic> content;
  @override
  Widget build(BuildContext context) {
    final data = localizeExercise(
      content,
      AirmiusScope.of(context).language.locale.languageCode,
    );
    final video = safeExternalHttpUrl(
      data['video_url']?.toString(),
      httpsOnly: false,
    );
    String ec(String key) => exerciseCopy(context, key);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        if (data['uses_original_fallback'] == true)
          Padding(
            padding: const EdgeInsets.only(bottom: 12),
            child: Text(
              '${ec('fallback')} ${exerciseLanguages[data['original_locale']] ?? data['original_locale'] ?? ''}',
              style: Theme.of(context).textTheme.bodySmall,
            ),
          ),
        for (final field in ['description', 'instructions', 'notes'])
          if (data[field]?.toString().trim().isNotEmpty == true)
            Padding(
              padding: const EdgeInsets.only(bottom: 16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    ec(field),
                    style: Theme.of(context).textTheme.titleSmall,
                  ),
                  const SizedBox(height: 6),
                  Text(
                    '${data[field]}',
                    textDirection:
                        RegExp(r'[\u0600-\u06ff]').hasMatch('${data[field]}')
                        ? TextDirection.rtl
                        : TextDirection.ltr,
                  ),
                ],
              ),
            ),
        if (data['todos'] is List && (data['todos'] as List).isNotEmpty) ...[
          Text(ec('todos'), style: Theme.of(context).textTheme.titleSmall),
          for (final todo in data['todos'] as List) Text('• $todo'),
          const SizedBox(height: 16),
        ],
        if (video != null)
          Padding(
            padding: const EdgeInsets.only(bottom: 16),
            child: OutlinedButton.icon(
              onPressed: () =>
                  launchUrl(video, mode: LaunchMode.externalApplication),
              icon: const Icon(Icons.play_circle_outline),
              label: Text(AirmiusScope.of(context).t('trainingHub.videoUrl')),
            ),
          ),
        for (final image in exerciseMaps(data['explanation_images']))
          Padding(
            padding: const EdgeInsets.only(bottom: 16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Semantics(
                  label: '${image['alt'] ?? image['caption'] ?? ''}',
                  image: true,
                  child: Image.network(
                    '${image['url']}',
                    height: 240,
                    fit: BoxFit.contain,
                    excludeFromSemantics: true,
                    errorBuilder: (_, _, _) => const SizedBox(
                      height: 80,
                      child: Icon(Icons.broken_image_outlined),
                    ),
                  ),
                ),
                if (image['caption']?.toString().isNotEmpty == true)
                  Padding(
                    padding: const EdgeInsets.only(top: 6),
                    child: Text('${image['caption']}'),
                  ),
              ],
            ),
          ),
      ],
    );
  }
}
