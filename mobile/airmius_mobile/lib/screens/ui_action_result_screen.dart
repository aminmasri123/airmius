import 'package:flutter/material.dart';

import '../core/airmius_l10n.dart';
import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

class UiActionResultScreen extends StatefulWidget {
  const UiActionResultScreen({
    super.key,
    required this.title,
    required this.body,
    required this.status,
    required this.icon,
  });

  final String title;
  final String body;
  final String status;
  final IconData icon;

  @override
  State<UiActionResultScreen> createState() => _UiActionResultScreenState();
}

class _UiActionResultScreenState extends State<UiActionResultScreen> {
  bool _notify = true;
  bool _saveAsDraft = true;
  bool _requiresApi = true;

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
          style: const TextStyle(fontWeight: FontWeight.w900),
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
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Icon(
                    widget.icon,
                    color: airmiusAccentColor(context),
                    size: 36,
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Eyebrow(t('uiAction.eyebrow')),
                        const SizedBox(height: 6),
                        Text(
                          widget.body,
                          style: TextStyle(
                            color: airmiusMutedColor(context),
                            height: 1.35,
                          ),
                        ),
                        const SizedBox(height: 12),
                        Wrap(
                          spacing: 8,
                          runSpacing: 8,
                          children: [
                            StatusPill(widget.status),
                            StatusPill(t('uiAction.ready')),
                            StatusPill(t('uiAction.apiLater')),
                          ],
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 14),
            Row(
              children: const [
                Expanded(
                  child: MetricCard(value: 'UI', label: 'Status'),
                ),
                SizedBox(width: 10),
                Expanded(
                  child: MetricCard(value: 'API', label: 'Backend'),
                ),
                SizedBox(width: 10),
                Expanded(
                  child: MetricCard(value: 'OK', label: 'Mobile'),
                ),
              ],
            ),
            const SizedBox(height: 14),
            AirmiusPanel(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Eyebrow(t('uiAction.behavior')),
                  const SizedBox(height: 8),
                  SwitchListTile(
                    value: _saveAsDraft,
                    onChanged: (value) => setState(() => _saveAsDraft = value),
                    activeThumbColor: airmiusAccentColor(context),
                    contentPadding: EdgeInsets.zero,
                    title: Text(
                      t('uiAction.prepareDraft'),
                      style: TextStyle(
                        color: airmiusTextColor(context),
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    subtitle: Text(
                      t('uiAction.prepareDraftBody'),
                      style: TextStyle(color: airmiusMutedColor(context)),
                    ),
                  ),
                  SwitchListTile(
                    value: _notify,
                    onChanged: (value) => setState(() => _notify = value),
                    activeThumbColor: airmiusAccentColor(context),
                    contentPadding: EdgeInsets.zero,
                    title: Text(
                      t('uiAction.notify'),
                      style: TextStyle(
                        color: airmiusTextColor(context),
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    subtitle: Text(
                      t('uiAction.notifyBody'),
                      style: TextStyle(color: airmiusMutedColor(context)),
                    ),
                  ),
                  SwitchListTile(
                    value: _requiresApi,
                    onChanged: (value) => setState(() => _requiresApi = value),
                    activeThumbColor: airmiusSemanticColor(
                      context,
                      AirmiusColors.amber,
                    ),
                    contentPadding: EdgeInsets.zero,
                    title: Text(
                      t('uiAction.apiRequired'),
                      style: TextStyle(
                        color: airmiusTextColor(context),
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    subtitle: Text(
                      t('uiAction.apiRequiredBody'),
                      style: TextStyle(color: airmiusMutedColor(context)),
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 14),
            AirmiusPanel(
              borderColor: airmiusSemanticColor(
                context,
                AirmiusColors.green,
              ).withValues(alpha: 0.45),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Eyebrow(t('uiAction.nextBackend')),
                  SizedBox(height: 8),
                  Text(
                    t('uiAction.nextBackendBody'),
                    style: TextStyle(
                      color: airmiusMutedColor(context),
                      height: 1.35,
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 14),
            AirmiusButton(
              label: t('uiAction.save'),
              icon: Icons.check_circle_outline,
              onPressed: () => Navigator.pop(context),
            ),
          ],
        ),
      ),
    );
  }
}
