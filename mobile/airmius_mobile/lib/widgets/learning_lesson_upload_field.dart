import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import '../core/airmius_api_client.dart';
import '../core/airmius_l10n.dart';
import '../core/learning_upload_copy.dart';
import '../core/learning_lesson_asset_upload.dart';

class LearningLessonUploadField extends StatefulWidget {
  const LearningLessonUploadField({
    super.key,
    required this.controller,
    required this.video,
    required this.upload,
    required this.onBusy,
    this.enabled = true,
    this.pickFiles,
  });
  final TextEditingController controller;
  final bool video, enabled;
  final Future<AirmiusJson> Function(PlatformFile, String, ValueChanged<double>)
  upload;
  final ValueChanged<bool> onBusy;
  final Future<List<PlatformFile>?> Function()? pickFiles;
  @override
  State<LearningLessonUploadField> createState() =>
      _LearningLessonUploadFieldState();
}

class _LearningLessonUploadFieldState extends State<LearningLessonUploadField> {
  bool _busy = false;
  double? _progress;
  String? _error;
  final _uploaded = <String, String>{};
  String _t(String key) =>
      (learningUploadCopy[AirmiusScope.of(context).language.name] ??
      learningUploadCopy['en']!)[key]!;

  Future<void> _pick() async {
    if (_busy || !widget.enabled) return;
    setState(() {
      _busy = true;
      _error = null;
      _progress = null;
    });
    widget.onBusy(true);
    try {
      final files = widget.pickFiles != null
          ? await widget.pickFiles!()
          : (await FilePicker.platform.pickFiles(
              type: FileType.custom,
              allowedExtensions: widget.video
                  ? learningVideoExtensions
                  : learningFileExtensions,
              allowMultiple: !widget.video,
              withReadStream: true,
            ))?.files;
      if (files == null || !mounted) return;
      for (final file in files) {
        final allowed = widget.video
            ? learningVideoExtensions
            : learningFileExtensions;
        if (file.size <= 0 ||
            file.size > 500 * 1024 * 1024 ||
            !allowed.contains(file.extension?.toLowerCase())) {
          throw ArgumentError('Unsupported file');
        }
        setState(() => _progress = null);
        final asset = await widget.upload(
          file,
          widget.video ? 'lesson_video' : 'lesson_attachment',
          (progress) {
            if (mounted) setState(() => _progress = progress);
          },
        );
        if (!mounted) return;
        final url = asset['url'] as String;
        if (widget.video) {
          widget.controller.text = url;
          _uploaded.clear();
        } else {
          widget.controller.text = [
            widget.controller.text.trim(),
            url,
          ].where((line) => line.isNotEmpty).join('\n');
        }
        setState(() => _uploaded[url] = file.name);
      }
    } catch (error) {
      if (mounted) {
        setState(
          () => _error = error is AirmiusApiException
              ? error.userMessage
              : error is ArgumentError
              ? _t('invalid')
              : AirmiusScope.of(context).t('common.errorDetails'),
        );
      }
    } finally {
      if (mounted) {
        setState(() => _busy = false);
        widget.onBusy(false);
      }
    }
  }

  @override
  Widget build(BuildContext context) => Column(
    crossAxisAlignment: CrossAxisAlignment.stretch,
    children: [
      OutlinedButton.icon(
        key: ValueKey(
          widget.video ? 'lesson-video-upload' : 'lesson-files-upload',
        ),
        onPressed: _busy || !widget.enabled ? null : _pick,
        icon: Icon(
          widget.video ? Icons.video_file_outlined : Icons.attach_file,
        ),
        label: Text(_t(widget.video ? 'video' : 'files')),
      ),
      if (!widget.video)
        Text(_t('hint'), style: Theme.of(context).textTheme.bodySmall),
      if (_busy) ...[
        Text(_t('uploading')),
        LinearProgressIndicator(
          value: _progress != null && _progress! < 1 ? _progress : null,
        ),
      ],
      for (final file in _uploaded.entries)
        Row(
          children: [
            const Icon(Icons.insert_drive_file_outlined, size: 18),
            const SizedBox(width: 8),
            Expanded(
              child: Text(
                file.value,
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
              ),
            ),
            IconButton(
              tooltip: _t('remove'),
              onPressed: _busy || !widget.enabled
                  ? null
                  : () {
                      widget.controller.text = widget.video
                          ? ''
                          : widget.controller.text
                                .split('\n')
                                .where((line) => line.trim() != file.key)
                                .join('\n');
                      setState(() => _uploaded.remove(file.key));
                    },
              icon: const Icon(Icons.close),
            ),
          ],
        ),
      if (_error != null)
        Text(
          _error!,
          style: TextStyle(color: Theme.of(context).colorScheme.error),
        ),
    ],
  );
}
