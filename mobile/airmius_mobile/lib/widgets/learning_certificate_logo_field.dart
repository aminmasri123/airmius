import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import '../core/airmius_api_client.dart';
import '../core/airmius_l10n.dart';

class LearningCertificateLogoField extends StatefulWidget {
  const LearningCertificateLogoField({
    super.key,
    required this.controller,
    required this.upload,
    required this.onBusy,
    this.pickImage,
  });
  final TextEditingController controller;
  final Future<String> Function(PlatformFile) upload;
  final ValueChanged<bool> onBusy;
  final Future<PlatformFile?> Function()? pickImage;
  @override
  State<LearningCertificateLogoField> createState() =>
      _LearningCertificateLogoFieldState();
}

class _LearningCertificateLogoFieldState
    extends State<LearningCertificateLogoField> {
  bool _busy = false;
  String? _error, _filename;
  Future<void> _pick() async {
    if (_busy) return;
    final t = AirmiusScope.of(context).t;
    setState(() {
      _busy = true;
      _error = null;
    });
    widget.onBusy(true);
    try {
      final file = widget.pickImage != null
          ? await widget.pickImage!()
          : (await FilePicker.platform.pickFiles(
              type: FileType.custom,
              allowedExtensions: ['png', 'jpg', 'jpeg', 'webp'],
              withData: true,
            ))?.files.single;
      if (file == null || !mounted) return;
      if (file.size > 5 * 1024 * 1024 ||
          file.bytes == null ||
          file.bytes!.isEmpty ||
          ![
            'png',
            'jpg',
            'jpeg',
            'webp',
          ].contains(file.extension?.toLowerCase())) {
        setState(() => _error = t('studio.certificateLogoHint'));
        return;
      }
      final url = await widget.upload(file);
      if (!mounted) return;
      widget.controller.text = url;
      setState(() => _filename = file.name);
    } catch (error) {
      if (mounted) {
        setState(
          () => _error = error is AirmiusApiException
              ? error.userMessage
              : t('common.errorDetails'),
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
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        TextField(
          controller: widget.controller,
          enabled: !_busy,
          keyboardType: TextInputType.url,
          decoration: InputDecoration(labelText: t('studio.certificateLogo')),
        ),
        const SizedBox(height: 8),
        OutlinedButton.icon(
          key: const ValueKey('certificate-logo-upload'),
          onPressed: _busy ? null : _pick,
          icon: const Icon(Icons.upload_outlined),
          label: Text(t('studio.certificateLogoUpload')),
        ),
        Text(
          t('studio.certificateLogoHint'),
          style: Theme.of(context).textTheme.bodySmall,
        ),
        if (_filename != null) Text(_filename!),
        if (_busy) const LinearProgressIndicator(),
        if (_error != null)
          Text(
            _error!,
            style: TextStyle(color: Theme.of(context).colorScheme.error),
          ),
      ],
    );
  }
}
