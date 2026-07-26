import 'package:flutter/services.dart';
import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

import '../core/airmius_external_url.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

class SharedFileAccessScreen extends StatefulWidget {
  const SharedFileAccessScreen({super.key, this.token, this.shareUrl});

  final String? token;
  final String? shareUrl;

  @override
  State<SharedFileAccessScreen> createState() => _SharedFileAccessScreenState();
}

class _SharedFileAccessScreenState extends State<SharedFileAccessScreen> {
  String? get _link {
    final value = widget.shareUrl?.trim() ?? '';
    return value.isEmpty ? null : value;
  }

  Future<void> _openLink() async {
    final t = AirmiusScope.of(context).t;
    final uri = safeExternalHttpUrl(_link, httpsOnly: false);
    if (uri == null ||
        !await launchUrl(uri, mode: LaunchMode.externalApplication)) {
      if (mounted) _showMessage(t('filesPreview.unavailable'));
    }
  }

  Future<void> _copyLink() async {
    final t = AirmiusScope.of(context).t;
    final uri = safeExternalHttpUrl(_link, httpsOnly: false);
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
    final hasLink = _link != null;
    final status = hasLink ? t('shared.status.active') : t('shared.noLink');
    return Scaffold(
      appBar: AppBar(
        backgroundColor:
            (Theme.of(context).appBarTheme.backgroundColor ??
            airmiusSurfaceColor(context)),
        surfaceTintColor: Colors.transparent,
        title: Text(
          t('shared.title'),
          style: TextStyle(fontWeight: FontWeight.w900),
        ),
      ),
      body: PageFrame(
        title: t('shared.title'),
        subtitle: t('shared.subtitle'),
        trailing: StatusPill(
          status,
          color: hasLink ? AirmiusColors.green : airmiusMutedColor(context),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            AirmiusPanel(
              gradient: true,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Eyebrow(t('shared.eyebrow')),
                  const SizedBox(height: 8),
                  Text(
                    t('shared.body'),
                    style: TextStyle(
                      color: airmiusMutedColor(context),
                      height: 1.35,
                    ),
                  ),
                  const SizedBox(height: 12),
                  AirmiusTextField(
                    label: t('shared.token'),
                    hint: widget.token ?? t('shared.noLink'),
                    icon: Icons.link_outlined,
                  ),
                ],
              ),
            ),
            const SizedBox(height: 14),
            Row(
              children: [
                Expanded(
                  child: MetricCard(
                    value: t('shared.status.unknown'),
                    label: t('shared.status.type'),
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: MetricCard(
                    value: t('shared.status.unknown'),
                    label: t('shared.status.expiry'),
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
                  Eyebrow(t('shared.preview')),
                  SizedBox(height: 12),
                  Text(
                    t('shared.previewBody'),
                    style: TextStyle(
                      color: airmiusMutedColor(context),
                      height: 1.35,
                    ),
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
                  label: t('shared.open'),
                  icon: Icons.visibility_outlined,
                  onPressed: hasLink ? _openLink : null,
                ),
                AirmiusButton(
                  label: t('shared.download'),
                  icon: Icons.download_outlined,
                  secondary: true,
                  onPressed: hasLink ? _openLink : null,
                ),
                AirmiusButton(
                  label: t('shared.copy'),
                  icon: Icons.copy_outlined,
                  secondary: true,
                  onPressed: hasLink ? _copyLink : null,
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}
