import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

class ModuleItemDetailScreen extends StatefulWidget {
  const ModuleItemDetailScreen({
    super.key,
    required this.title,
    required this.body,
    required this.trailing,
    required this.icon,
  });

  final String title;
  final String body;
  final String trailing;
  final IconData icon;

  @override
  State<ModuleItemDetailScreen> createState() => _ModuleItemDetailScreenState();
}

class _ModuleItemDetailScreenState extends State<ModuleItemDetailScreen> {
  bool _visible = true;
  bool _notify = false;
  bool _requiresReview = true;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        backgroundColor:
            Theme.of(context).appBarTheme.backgroundColor ??
            airmiusSurfaceColor(context),
        surfaceTintColor: Colors.transparent,
        title: const Text(
          'Moduldetail',
          style: TextStyle(fontWeight: FontWeight.w900),
        ),
      ),
      body: PageFrame(
        title: widget.title,
        subtitle: widget.body,
        trailing: StatusPill(widget.trailing),
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
                    size: 34,
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Eyebrow('Modulbaustein'),
                        const SizedBox(height: 5),
                        Text(
                          widget.body,
                          style: TextStyle(
                            color: airmiusMutedColor(context),
                            height: 1.35,
                          ),
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
                  child: MetricCard(value: 'API', label: 'Später'),
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
                  const Eyebrow('Mobile Modulsteuerung'),
                  const SizedBox(height: 8),
                  SwitchListTile(
                    value: _visible,
                    onChanged: (value) => setState(() => _visible = value),
                    activeThumbColor: airmiusAccentColor(context),
                    contentPadding: EdgeInsets.zero,
                    title: Text(
                      'Im Modul sichtbar',
                      style: TextStyle(
                        color: airmiusTextColor(context),
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    subtitle: Text(
                      'Karte bleibt im mobilen Modulkontext sichtbar.',
                      style: TextStyle(color: airmiusMutedColor(context)),
                    ),
                  ),
                  SwitchListTile(
                    value: _notify,
                    onChanged: (value) => setState(() => _notify = value),
                    activeThumbColor: airmiusAccentColor(context),
                    contentPadding: EdgeInsets.zero,
                    title: Text(
                      'Benachrichtigung aktivieren',
                      style: TextStyle(
                        color: airmiusTextColor(context),
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    subtitle: Text(
                      'Push/Inbox-Regeln werden später an API gekoppelt.',
                      style: TextStyle(color: airmiusMutedColor(context)),
                    ),
                  ),
                  SwitchListTile(
                    value: _requiresReview,
                    onChanged: (value) =>
                        setState(() => _requiresReview = value),
                    activeThumbColor: AirmiusColors.amber,
                    contentPadding: EdgeInsets.zero,
                    title: Text(
                      'Review erforderlich',
                      style: TextStyle(
                        color: airmiusTextColor(context),
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    subtitle: Text(
                      'Sensible Modulaktionen können Freigabe verlangen.',
                      style: TextStyle(color: airmiusMutedColor(context)),
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 14),
            AirmiusPanel(
              borderColor: airmiusAccentColor(context).withValues(alpha: 0.45),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Eyebrow('API-Kontext'),
                  SizedBox(height: 8),
                  Text(
                    'Dieser Detailtyp zeigt Modulzeilen als nativen Mobile-Flow. Später kann jede Karte mit passender Laravel-Route, Berechtigung und Datensatz-ID verbunden werden.',
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
              label: 'Moduldetail speichern',
              icon: Icons.save_outlined,
              onPressed: () => openUiAction(
                context,
                title: 'Moduldetail speichern',
                body:
                    'Diese Aktion ist in der Mobile-App vorbereitet und wird später über die Laravel-API synchronisiert.',
                status: 'UI bereit',
                icon: Icons.save_outlined,
              ),
            ),
          ],
        ),
      ),
    );
  }
}
