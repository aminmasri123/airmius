import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:url_launcher/url_launcher.dart';

import '../core/airmius_external_url.dart';
import '../core/airmius_api_models.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

class FilePreviewScreen extends StatefulWidget {
  const FilePreviewScreen({
    super.key,
    required this.title,
    required this.body,
    required this.status,
    required this.icon,
    this.fileId,
    this.fileMeta,
    this.fileUrl,
    this.previewUrl,
    this.thumbnailUrl,
    this.isImage,
    this.uploadedAt,
    this.accessRights,
    this.onShare,
  });

  final String title;
  final String body;
  final String status;
  final IconData icon;
  final int? fileId;
  final String? fileMeta;
  final String? fileUrl;
  final String? previewUrl;
  final String? thumbnailUrl;
  final bool? isImage;
  final DateTime? uploadedAt;
  final AirmiusFileAccessRights? accessRights;
  final Future<void> Function()? onShare;

  @override
  State<FilePreviewScreen> createState() => _FilePreviewScreenState();
}

class _FilePreviewScreenState extends State<FilePreviewScreen> {
  Future<void> _openFile() async {
    final t = AirmiusScope.of(context).t;
    final uri = safeExternalHttpUrl(widget.fileUrl, httpsOnly: false);
    if (uri == null ||
        !await launchUrl(uri, mode: LaunchMode.externalApplication)) {
      if (mounted) _showMessage(t('filesPreview.unavailable'));
    }
  }

  void _showMessage(String message) {
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(SnackBar(content: Text(message)));
  }

  String get _imageUrl => widget.previewUrl?.trim().isNotEmpty == true
      ? widget.previewUrl!.trim()
      : widget.thumbnailUrl?.trim().isNotEmpty == true
      ? widget.thumbnailUrl!.trim()
      : widget.fileUrl?.trim() ?? '';

  bool get _isImage {
    if (widget.isImage != null) return widget.isImage!;
    final value = '${widget.fileMeta} ${widget.fileUrl} ${widget.title}'
        .toLowerCase();
    return value.contains('image/') ||
        RegExp(r'\.(jpe?g|png|webp|gif|bmp)(?:\?|$)').hasMatch(value);
  }

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return Scaffold(
      appBar: AppBar(
        backgroundColor:
            Theme.of(context).appBarTheme.backgroundColor ??
            airmiusSurfaceColor(context),
        surfaceTintColor: Colors.transparent,
        title: Text(
          widget.title,
          style: TextStyle(fontWeight: FontWeight.w900),
        ),
      ),
      body: PageFrame(
        title: widget.title,
        subtitle: widget.body,
        trailing: StatusPill(widget.status),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            AirmiusPanel(
              gradient: true,
              child: SizedBox(
                height: 250,
                child: _isImage && _imageUrl.isNotEmpty
                    ? AirmiusMediaImage(
                        url: _imageUrl,
                        fallbackUrls:
                            widget.fileUrl != null &&
                                widget.fileUrl!.trim() != _imageUrl
                            ? [widget.fileUrl!.trim()]
                            : const [],
                        height: 250,
                        borderRadius: 18,
                        semanticLabel: widget.title,
                        fallback: _PreviewPlaceholder(
                          icon: widget.icon,
                          label: t('filesPreview.unavailable'),
                        ),
                      )
                    : _PreviewPlaceholder(icon: widget.icon),
              ),
            ),
            const SizedBox(height: 14),
            AirmiusPanel(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Eyebrow(t('filesPreview.details')),
                  const SizedBox(height: 8),
                  _FileDetailRow(
                    label: t('filesPreview.type'),
                    value: widget.fileMeta?.trim().isNotEmpty == true
                        ? widget.fileMeta!.trim()
                        : t('shared.status.unknown'),
                  ),
                  if (widget.uploadedAt != null)
                    _FileDetailRow(
                      label: t('filesPreview.uploadedAt'),
                      value: DateFormat.yMMMd(
                        AirmiusScope.of(
                          context,
                        ).language.locale.toLanguageTag(),
                      ).add_Hm().format(widget.uploadedAt!.toLocal()),
                    ),
                ],
              ),
            ),
            const SizedBox(height: 14),
            if (widget.accessRights != null) ...[
              AirmiusFileRightsPanel(rights: widget.accessRights!),
              const SizedBox(height: 14),
            ],
            Wrap(
              spacing: 10,
              runSpacing: 10,
              children: [
                AirmiusButton(
                  label: t('filesPreview.downloadAction'),
                  icon: Icons.download_outlined,
                  onPressed: _openFile,
                ),
                AirmiusButton(
                  label: t('filesPreview.shareAction'),
                  icon: Icons.share_outlined,
                  secondary: true,
                  onPressed: widget.onShare,
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _PreviewPlaceholder extends StatelessWidget {
  const _PreviewPlaceholder({required this.icon, this.label});

  final IconData icon;
  final String? label;

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
        color: airmiusSurfaceSoftColor(context),
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: airmiusBorderColor(context)),
      ),
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Icon(icon, color: airmiusAccentColor(context), size: 68),
          if (label != null) ...[
            const SizedBox(height: 12),
            Text(
              label!,
              textAlign: TextAlign.center,
              style: TextStyle(
                color: airmiusMutedColor(context),
                fontWeight: FontWeight.w700,
              ),
            ),
          ],
        ],
      ),
    );
  }
}

class _FileDetailRow extends StatelessWidget {
  const _FileDetailRow({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 6),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: 108,
            child: Text(
              label,
              style: TextStyle(color: airmiusMutedColor(context)),
            ),
          ),
          Expanded(
            child: Text(
              value,
              style: TextStyle(
                color: airmiusTextColor(context),
                fontWeight: FontWeight.w700,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class AirmiusFileRightsPanel extends StatelessWidget {
  const AirmiusFileRightsPanel({required this.rights, super.key});

  final AirmiusFileAccessRights rights;

  static const _rows = [
    (key: 'read', label: 'files.rights.read', icon: Icons.visibility_outlined),
    (key: 'edit', label: 'files.rights.edit', icon: Icons.edit_outlined),
    (key: 'share', label: 'files.rights.share', icon: Icons.share_outlined),
    (key: 'delete', label: 'files.rights.delete', icon: Icons.delete_outline),
  ];

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return AirmiusPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Eyebrow(t('files.rights.title')),
          const SizedBox(height: 4),
          Text(
            t('files.rights.subtitle'),
            style: TextStyle(color: airmiusMutedColor(context), fontSize: 12),
          ),
          const SizedBox(height: 10),
          ..._rows.map((row) {
            final right = rights[row.key];
            final allowed = right?.allowed == true;
            final audience = right?.audience ?? 'owner';
            final audienceLabel = t('files.rights.audience.$audience');

            return Padding(
              padding: const EdgeInsets.symmetric(vertical: 5),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Icon(row.icon, color: airmiusAccentColor(context), size: 18),
                  const SizedBox(width: 9),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          t(row.label),
                          style: TextStyle(
                            color: airmiusTextColor(context),
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                        const SizedBox(height: 2),
                        Text(
                          audienceLabel,
                          style: TextStyle(
                            color: airmiusMutedColor(context),
                            fontSize: 12,
                          ),
                        ),
                      ],
                    ),
                  ),
                  StatusPill(
                    allowed
                        ? t('files.rights.allowed')
                        : t('files.rights.notAllowed'),
                  ),
                ],
              ),
            );
          }),
        ],
      ),
    );
  }
}
