import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import '../core/exercise_content.dart';
import '../core/exercise_content_copy.dart';

class ExerciseContentEditor extends StatefulWidget {
  const ExerciseContentEditor({
    super.key,
    required this.draft,
    required this.onChanged,
    this.showTextFields = true,
  });
  final ExerciseContentDraft draft;
  final VoidCallback onChanged;
  final bool showTextFields;
  @override
  State<ExerciseContentEditor> createState() => _ExerciseContentEditorState();
}

class _ExerciseContentEditorState extends State<ExerciseContentEditor> {
  late String _language = widget.draft.originalLocale;
  String ec(String key) => exerciseCopy(context, key);
  void _changed(VoidCallback change) {
    setState(change);
    widget.onChanged();
  }

  Future<void> _pick() async {
    final picked = await FilePicker.platform.pickFiles(
      type: FileType.custom,
      allowedExtensions: ['jpg', 'jpeg', 'png', 'webp'],
      allowMultiple: true,
      withData: true,
    );
    if (picked == null || !mounted) return;
    if (widget.draft.images.length + picked.files.length > 12 ||
        picked.files.any(
          (file) =>
              file.size > 5 * 1024 * 1024 ||
              ![
                'jpg',
                'jpeg',
                'png',
                'webp',
              ].contains(file.extension?.toLowerCase()),
        )) {
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(ec('limit'))));
      return;
    }
    _changed(() {
      for (final file in picked.files) {
        widget.draft.images.add(
          ExerciseImageDraft(
            id: 'new-${DateTime.now().microsecondsSinceEpoch}-${widget.draft.images.length}',
            file: file,
          ),
        );
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    final draft = widget.draft;
    final texts = draft.textsFor(_language);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        if (widget.showTextFields) ...[
          DropdownButtonFormField<String>(
            key: ValueKey('original-${draft.originalLocale}'),
            initialValue: draft.originalLocale,
            decoration: InputDecoration(labelText: ec('original')),
            items: exerciseLanguages.entries
                .map(
                  (entry) => DropdownMenuItem(
                    value: entry.key,
                    child: Text(entry.value),
                  ),
                )
                .toList(),
            onChanged: (value) {
              if (value != null) {
                _changed(() {
                  draft.originalLocale = value;
                  _language = value;
                });
              }
            },
          ),
          const SizedBox(height: 16),
        ],
        DropdownButtonFormField<String>(
          key: ValueKey('content-$_language'),
          initialValue: _language,
          decoration: InputDecoration(labelText: ec('language')),
          items: exerciseLanguages.entries
              .map(
                (entry) => DropdownMenuItem(
                  value: entry.key,
                  child: Text(entry.value),
                ),
              )
              .toList(),
          onChanged: (value) {
            if (value != null) setState(() => _language = value);
          },
        ),
        const SizedBox(height: 8),
        Text(ec('hint')),
        const SizedBox(height: 16),
        if (widget.showTextFields)
          for (final field in exerciseTextFields)
            Padding(
              padding: const EdgeInsets.only(bottom: 16),
              child: Directionality(
                textDirection: _language == 'ar'
                    ? TextDirection.rtl
                    : TextDirection.ltr,
                child: TextFormField(
                  key: ValueKey('$_language:${draft.originalLocale}:$field'),
                  initialValue: texts[field],
                  maxLength: field == 'title'
                      ? 160
                      : field == 'instructions'
                      ? 10000
                      : 3000,
                  minLines: field == 'title' ? 1 : 3,
                  maxLines: field == 'title' ? 1 : 8,
                  decoration: InputDecoration(
                    labelText: ec(field),
                    hintText: _language != draft.originalLocale
                        ? draft.originalTexts[field]
                        : null,
                  ),
                  onChanged: (value) {
                    texts[field] = value;
                    widget.onChanged();
                  },
                ),
              ),
            ),
        Text(ec('images'), style: Theme.of(context).textTheme.titleMedium),
        const SizedBox(height: 8),
        Text(ec('imageHint')),
        const SizedBox(height: 12),
        OutlinedButton.icon(
          onPressed: _pick,
          icon: const Icon(Icons.add_photo_alternate_outlined),
          label: Text(ec('addImages')),
        ),
        for (var index = 0; index < draft.images.length; index++)
          _image(draft.images[index], index),
      ],
    );
  }

  Widget _image(ExerciseImageDraft image, int index) {
    final texts = exerciseMap(image.texts[_language]);
    final original = exerciseMap(image.texts[widget.draft.originalLocale]);
    return Card(
      key: ValueKey(image.id),
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            if (image.file?.bytes != null)
              Image.memory(image.file!.bytes!, height: 180, fit: BoxFit.contain)
            else if (image.url != null)
              Image.network(
                image.url!,
                height: 180,
                fit: BoxFit.contain,
                errorBuilder: (_, _, _) => const SizedBox(
                  height: 80,
                  child: Icon(Icons.broken_image_outlined),
                ),
              ),
            const SizedBox(height: 8),
            Text('${index + 1} · ${exerciseLanguages[_language]}'),
            for (final field in ['caption', 'alt'])
              TextFormField(
                key: ValueKey('${image.id}:$_language:$field'),
                initialValue: texts[field]?.toString() ?? '',
                maxLength: 500,
                textDirection: _language == 'ar'
                    ? TextDirection.rtl
                    : TextDirection.ltr,
                decoration: InputDecoration(
                  labelText: ec(field),
                  hintText: original[field]?.toString(),
                ),
                onChanged: (value) {
                  image.texts[_language] = {
                    ...exerciseMap(image.texts[_language]),
                    field: value,
                  };
                  widget.onChanged();
                },
              ),
            Wrap(
              spacing: 8,
              children: [
                IconButton(
                  tooltip: ec('up'),
                  onPressed: index == 0
                      ? null
                      : () => _changed(() {
                          final moved = widget.draft.images.removeAt(index);
                          widget.draft.images.insert(index - 1, moved);
                        }),
                  icon: const Icon(Icons.arrow_upward),
                ),
                IconButton(
                  tooltip: ec('down'),
                  onPressed: index == widget.draft.images.length - 1
                      ? null
                      : () => _changed(() {
                          final moved = widget.draft.images.removeAt(index);
                          widget.draft.images.insert(index + 1, moved);
                        }),
                  icon: const Icon(Icons.arrow_downward),
                ),
                TextButton.icon(
                  onPressed: () =>
                      _changed(() => widget.draft.images.removeAt(index)),
                  icon: const Icon(Icons.delete_outline),
                  label: Text(ec('remove')),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}
