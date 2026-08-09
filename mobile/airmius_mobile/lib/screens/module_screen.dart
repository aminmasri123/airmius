import 'package:flutter/material.dart';

import '../core/airmius_l10n.dart';
import '../core/airmius_mvp_surface.dart';
import '../core/airmius_theme.dart';
import '../models/club_summary.dart';
import '../models/module_definition.dart';
import '../widgets/airmius_widgets.dart';
import 'module_sections.dart';

class ModuleScreen extends StatelessWidget {
  const ModuleScreen({
    super.key,
    required this.module,
    this.requestedClubIds = const {},
    this.onRequestClub,
    this.onWithdrawClub,
    this.autoOpen = false,
  });

  final ModuleDefinition module;
  final Set<int> requestedClubIds;
  final ValueChanged<ClubSummary>? onRequestClub;
  final ValueChanged<ClubSummary>? onWithdrawClub;
  final bool autoOpen;

  @override
  Widget build(BuildContext context) {
    final scope = AirmiusScope.of(context);
    final moduleTitle = scope.copy(module.title);
    final copiedSubtitle = scope.copy(module.subtitle);
    final moduleSubtitle =
        copiedSubtitle == module.subtitle &&
            scope.language != AirmiusLanguage.de
        ? scope.t('module.defaultSubtitle')
        : copiedSubtitle;
    final accent = Theme.of(context).colorScheme.primary;
    return PageFrame(
      title: moduleTitle,
      subtitle: moduleSubtitle,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          AirmiusPanel(
            gradient: true,
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Container(
                  width: 58,
                  height: 58,
                  decoration: BoxDecoration(
                    color: accent.withValues(alpha: 0.16),
                    borderRadius: BorderRadius.circular(18),
                    border: Border.all(color: accent.withValues(alpha: 0.35)),
                  ),
                  child: Icon(module.icon, color: accent, size: 28),
                ),
                const SizedBox(width: 14),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Eyebrow(scope.t('module.airmiusModule')),
                      const SizedBox(height: 6),
                      Text(
                        moduleTitle,
                        style: TextStyle(
                          color: airmiusTextColor(context),
                          fontSize: 23,
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                      const SizedBox(height: 6),
                      Text(
                        moduleSubtitle,
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
          ModuleSpecificSection(
            module: module,
            requestedClubIds: requestedClubIds,
            onRequestClub: onRequestClub,
            onWithdrawClub: onWithdrawClub,
            autoOpen: autoOpen,
          ),
          if (AirmiusMvpSurface.showDeveloperSuites) ...[
            const SizedBox(height: 14),
            AirmiusPanel(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Eyebrow(scope.t('module.prototypeActions')),
                  const SizedBox(height: 8),
                  for (final action in module.actions) ...[
                    _ActionRow(
                      title: scope.copy(action),
                      body: _bodyFor(action, scope),
                      icon: module.icon,
                    ),
                    const SizedBox(height: 10),
                  ],
                ],
              ),
            ),
          ],
        ],
      ),
    );
  }

  String _bodyFor(String action, AirmiusScope scope) {
    if (action.contains('hochladen')) {
      return 'Upload-UI ist vorbereitet und wird mit File Picker plus Laravel Upload-API verbunden.';
    }
    if (action.contains('suchen') || action.contains('Suche')) {
      return 'Suchfelder und Ergebnislisten folgen dem mobilen Web-App-Muster.';
    }
    if (action.contains('senden')) {
      return 'Formular- und Statuslogik wird später über API gespeichert.';
    }
    return 'Native Oberfläche für diese Funktion, passend zur Web-App-Struktur.';
  }
}

class _ActionRow extends StatelessWidget {
  const _ActionRow({
    required this.title,
    required this.body,
    required this.icon,
  });

  final String title;
  final String body;
  final IconData icon;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: () => openUiAction(
        context,
        title: title,
        body: body,
        status: 'Funktion',
        icon: icon,
      ),
      borderRadius: BorderRadius.circular(15),
      child: Container(
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(
          color: airmiusSurfaceSoftColor(context),
          borderRadius: BorderRadius.circular(15),
          border: Border.all(color: airmiusBorderColor(context)),
        ),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Icon(icon, color: Theme.of(context).colorScheme.primary, size: 21),
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
                      height: 1.3,
                    ),
                  ),
                ],
              ),
            ),
            Icon(Icons.chevron_right, color: airmiusMutedColor(context)),
          ],
        ),
      ),
    );
  }
}
