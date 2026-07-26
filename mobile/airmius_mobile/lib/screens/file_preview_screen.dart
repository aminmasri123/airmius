import 'package:flutter/services.dart';
import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

import '../core/airmius_api_client.dart';
import '../core/airmius_external_url.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';
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
  });

  final String title;
  final String body;
  final String status;
  final IconData icon;
  final int? fileId;
  final String? fileMeta;
  final String? fileUrl;

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

  Future<void> _copyFileLink() async {
    final t = AirmiusScope.of(context).t;
    final fileId = widget.fileId;
    if (fileId != null) {
      try {
        final data = await AirmiusServicesScope.of(
          context,
        ).repositories.files.createFileShare(fileId);
        final sharedUrl = '${data['url'] ?? ''}'.trim();
        final uri = safeExternalHttpUrl(sharedUrl, httpsOnly: false);
        if (uri == null) {
          _showMessage(t('filesPreview.unavailable'));
          return;
        }
        await Clipboard.setData(ClipboardData(text: uri.toString()));
        if (mounted) _showMessage(t('filesPreview.copied'));
      } catch (error) {
        if (!mounted) return;
        _showMessage(
          error is AirmiusApiException
              ? error.userMessage
              : t('filesPreview.unavailable'),
        );
      }
      return;
    }
    final value = widget.fileUrl?.trim() ?? '';
    final uri = safeExternalHttpUrl(value, httpsOnly: false);
    if (uri == null) {
      _showMessage(t('filesPreview.unavailable'));
      return;
    }
    await Clipboard.setData(ClipboardData(text: uri.toString()));
    if (mounted) _showMessage(t('filesPreview.copied'));
  }

  void _showMessage(String message) {
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(SnackBar(content: Text(message)));
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
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Container(
                    height: 220,
                    decoration: BoxDecoration(
                      color: airmiusSurfaceSoftColor(context),
                      borderRadius: BorderRadius.circular(18),
                      border: Border.all(color: airmiusBorderColor(context)),
                    ),
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Icon(
                          widget.icon,
                          color: airmiusAccentColor(context),
                          size: 72,
                        ),
                        const SizedBox(height: 12),
                        Text(
                          t('filesPreview.title'),
                          style: TextStyle(
                            color: airmiusTextColor(context),
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 12),
                  Text(
                    t('filesPreview.body'),
                    style: TextStyle(
                      color: airmiusMutedColor(context),
                      height: 1.4,
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 14),
            Row(
              children: [
                Expanded(
                  child: MetricCard(
                    value: widget.fileMeta?.trim().isNotEmpty == true
                        ? widget.fileMeta!.trim()
                        : t('shared.status.unknown'),
                    label: t('filesPreview.type'),
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: MetricCard(
                    value: widget.fileId != null
                        ? t('shared.status.available')
                        : t('shared.status.unknown'),
                    label: t('shared.status.links'),
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: MetricCard(
                    value: t('shared.status.readOnly'),
                    label: t('shared.status.permissions'),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 14),
            AirmiusPanel(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Eyebrow(t('filesPreview.permissions')),
                  SizedBox(height: 10),
                  _FileActionLine(
                    icon: Icons.link_outlined,
                    title: t('filesPreview.share'),
                    body: t('filesPreview.shareBody'),
                    status: widget.fileId != null
                        ? t('shared.status.active')
                        : t('shared.status.unknown'),
                  ),
                  _FileActionLine(
                    icon: Icons.download_outlined,
                    title: t('filesPreview.download'),
                    body: t('filesPreview.downloadBody'),
                    status: widget.fileUrl?.trim().isNotEmpty == true
                        ? t('shared.status.available')
                        : t('shared.status.unknown'),
                  ),
                  _FileActionLine(
                    icon: Icons.history_outlined,
                    title: t('filesPreview.versions'),
                    body: t('filesPreview.versionsBody'),
                    status: t('shared.status.unknown'),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 14),
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
                  onPressed: _copyFileLink,
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _FileActionLine extends StatelessWidget {
  const _FileActionLine({
    required this.icon,
    required this.title,
    required this.body,
    required this.status,
  });

  final IconData icon;
  final String title;
  final String body;
  final String status;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(top: 12),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, color: airmiusAccentColor(context)),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: TextStyle(
                    color: airmiusTextColor(context),
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 3),
                Text(
                  body,
                  style: TextStyle(
                    color: airmiusMutedColor(context),
                    height: 1.35,
                  ),
                ),
              ],
            ),
          ),
          StatusPill(status),
        ],
      ),
    );
  }
}
