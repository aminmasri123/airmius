import 'package:file_picker/file_picker.dart';

Map<String, dynamic> exerciseMap(Object? value) =>
    value is Map ? Map<String, dynamic>.from(value) : {};
List<Map<String, dynamic>> exerciseMaps(Object? value) => value is List
    ? value.whereType<Map>().map((e) => Map<String, dynamic>.from(e)).toList()
    : [];
const exerciseTextFields = [
  'title',
  'description',
  'instructions',
  'notes',
  'todos',
];

Map<String, dynamic> localizeExercise(
  Map<String, dynamic> source,
  String language,
) {
  final original = exerciseMap(source['original_content']);
  final result = {...source, ...original};
  result['title'] ??= source['name'];
  final locale = language.toLowerCase().split(RegExp('[-_]')).first;
  final originalLocale = source['original_locale'];
  final translations = exerciseMap(exerciseMap(source['translations'])[locale]);
  var fallback = false;
  for (final field in exerciseTextFields) {
    final translated = locale == originalLocale ? null : translations[field];
    final hasTranslation = translated is String && translated.trim().isNotEmpty;
    if (hasTranslation) {
      result[field] = field == 'todos'
          ? translated.split(RegExp(r'\r?\n'))
          : translated;
    } else if (originalLocale != null && locale != originalLocale) {
      final base = result[field];
      if (base is List
          ? base.isNotEmpty
          : (base?.toString().trim().isNotEmpty ?? false)) {
        fallback = true;
      }
    }
  }
  result['explanation_images'] = exerciseMaps(source['explanation_images']).map(
    (image) {
      final texts = exerciseMap(image['texts']);
      final translated = exerciseMap(texts[locale]);
      final base = exerciseMap(texts[originalLocale]);
      final localized = {...image};
      for (final field in ['caption', 'alt']) {
        final value = translated[field]?.toString() ?? '';
        final originalValue = image.containsKey('texts')
            ? base[field]?.toString() ?? ''
            : image[field]?.toString() ?? '';
        localized[field] = value.trim().isNotEmpty ? value : originalValue;
        if (locale != originalLocale &&
            value.trim().isEmpty &&
            originalValue.isNotEmpty) {
          fallback = true;
        }
      }
      return localized;
    },
  ).toList();
  result['uses_original_fallback'] = fallback;
  return result;
}

class ExerciseImageDraft {
  ExerciseImageDraft({
    required this.id,
    this.url,
    this.file,
    Map<String, dynamic>? texts,
  }) : texts = texts ?? {};
  final String id;
  final String? url;
  final PlatformFile? file;
  final Map<String, dynamic> texts;
  Map<String, dynamic> toPayload() => {
    'id': id,
    'texts': texts,
    if (file != null) 'file': file,
  };
}

class ExerciseContentDraft {
  ExerciseContentDraft(
    Map<String, dynamic> source, {
    String defaultLocale = 'de',
  }) {
    originalLocale = source['original_locale']?.toString() ?? defaultLocale;
    if (!['de', 'en', 'fr', 'ar'].contains(originalLocale)) {
      originalLocale = 'de';
    }
    final original = exerciseMap(source['original_content']);
    final base = {...source, ...original};
    base['title'] ??= source['name'];
    for (final field in exerciseTextFields) {
      final value = base[field];
      originalTexts[field] = value is List
          ? value.join('\n')
          : value?.toString() ?? '';
    }
    final stored = exerciseMap(source['translations']);
    for (final locale in ['de', 'en', 'fr', 'ar']) {
      translations[locale] = {
        for (final field in exerciseTextFields)
          field: exerciseMap(stored[locale])[field]?.toString() ?? '',
      };
    }
    images.addAll(
      exerciseMaps(source['explanation_images']).map(
        (image) => ExerciseImageDraft(
          id: '${image['id']}',
          url: image['url']?.toString(),
          texts: exerciseMap(image['texts']),
        ),
      ),
    );
  }
  late String originalLocale;
  final Map<String, String> originalTexts = {};
  final Map<String, Map<String, String>> translations = {};
  final List<ExerciseImageDraft> images = [];
  Map<String, String> textsFor(String locale) =>
      locale == originalLocale ? originalTexts : translations[locale]!;
  Map<String, dynamic> toPayload() => {
    ...originalTexts,
    'todos': originalTexts['todos']!
        .split(RegExp(r'\r?\n'))
        .map((e) => e.trim())
        .where((e) => e.isNotEmpty)
        .toList(),
    'original_locale': originalLocale,
    'translations': {
      for (final entry in translations.entries)
        if (entry.key != originalLocale) entry.key: entry.value,
    },
    'explanation_images': images.map((image) => image.toPayload()).toList(),
  };
}
