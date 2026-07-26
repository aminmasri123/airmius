import 'package:flutter/material.dart';

import '../core/airmius_l10n.dart';
import '../core/airmius_theme.dart';
import '../models/module_definition.dart';
import '../widgets/airmius_widgets.dart';
import 'operations_hub_screen.dart';

class UiCoverageScreen extends StatelessWidget {
  const UiCoverageScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final scope = AirmiusScope.of(context);
    return Scaffold(
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      appBar: AppBar(
        backgroundColor:
            Theme.of(context).appBarTheme.backgroundColor ??
            airmiusSurfaceColor(context),
        surfaceTintColor: Colors.transparent,
        title: Text(
          scope.t('release.coverage'),
          style: TextStyle(
            color: airmiusTextColor(context),
            fontWeight: FontWeight.w900,
          ),
        ),
      ),
      body: PageFrame(
        title: scope.t('release.coverage'),
        subtitle: scope.t('release.coverageSubtitle'),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            AirmiusPanel(
              gradient: true,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Eyebrow(scope.t('release.coverageEyebrow')),
                  const SizedBox(height: 8),
                  Text(
                    scope.t('release.coverageBody'),
                    style: TextStyle(
                      color: airmiusMutedColor(context),
                      height: 1.4,
                    ),
                  ),
                  const SizedBox(height: 14),
                  Row(
                    children: [
                      Expanded(
                        child: MetricCard(
                          value: '${appModules.length}',
                          label: scope.t('release.coverageModules'),
                        ),
                      ),
                      SizedBox(width: 10),
                      Expanded(
                        child: MetricCard(
                          value: '${appModules.where((module) => _status(module.title).ops == 'release.coverage.opsLinked').length}',
                          label: scope.t('release.coverageOps'),
                        ),
                      ),
                      SizedBox(width: 10),
                      Expanded(child: MetricCard(
                        value: 'DE/EN/FR/AR',
                        label: scope.t('release.coverageLanguages'),
                      )),
                    ],
                  ),
                  const SizedBox(height: 14),
                  AirmiusButton(
                    label: scope.t('release.coverageOperationsHub'),
                    icon: Icons.hub_outlined,
                    onPressed: () => Navigator.push(
                      context,
                      MaterialPageRoute(builder: (_) => OperationsHubScreen()),
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 16),
            for (final module in appModules) ...[
              _CoverageLine(module: module),
              const SizedBox(height: 10),
            ],
          ],
        ),
      ),
    );
  }
}

class _CoverageLine extends StatelessWidget {
  const _CoverageLine({required this.module});

  final ModuleDefinition module;

  @override
  Widget build(BuildContext context) {
    final status = _status(module.title);
    final scope = AirmiusScope.of(context);
    final semanticColor = airmiusSemanticColor(context, status.color);
    return AirmiusPanel(
      borderColor: semanticColor.withValues(alpha: .42),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 46,
            height: 46,
            decoration: BoxDecoration(
              color: semanticColor.withValues(alpha: .13),
              borderRadius: BorderRadius.circular(15),
              border: Border.all(color: semanticColor.withValues(alpha: .42)),
            ),
            child: Icon(module.icon, color: semanticColor),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  scope.copy(module.title),
                  style: TextStyle(
                    color: airmiusTextColor(context),
                    fontWeight: FontWeight.w900,
                    fontSize: 16,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  module.subtitle,
                  style: TextStyle(
                    color: airmiusMutedColor(context),
                    height: 1.35,
                  ),
                ),
                const SizedBox(height: 9),
                Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: [
                    StatusPill(scope.t(status.label), color: semanticColor),
                    StatusPill(scope.t(status.ops)),
                    StatusPill(scope.t(status.api)),
                  ],
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

_CoverageStatus _status(String title) {
  const opsReady = {
    'Arbeitsbereiche',
    'Vereins-Cockpit',
    'Vereine & Teams',
    'Teams',
    'Rollen & Rechte',
    'Sportarten',
    'Sport-Apps & Gesundheitsdaten',
    'Feed',
    'Events',
    'Events & Training',
    'Trainer-Cockpit',
    'Ernährung',
    'Sportkarte',
    'Freunde',
    'Nachrichten',
    'Fahrgemeinschaften',
    'Dateien',
    'Badges',
    'Gamification-Regeln',
    'Kurse',
    'Marketplace',
    'Commerce',
    'Sponsoren',
    'Medienrichtlinien',
    'Blog & Medien',
    'Nutzer',
    'Abos & Rechnungen',
    'Eltern & Jugendschutz',
    'Altersfreigaben',
    'Outfit-Abos',
    'Einstellungen',
    'Admin',
  };
  if (opsReady.contains(title)) {
    return const _CoverageStatus(
      'release.coverage.nativeReady',
      'release.coverage.opsLinked',
      'release.coverage.apiLinked',
      AirmiusColors.green,
    );
  }
  return const _CoverageStatus(
    'release.coverage.uiPresent',
    'release.coverage.review',
    'release.coverage.apiReview',
    AirmiusColors.amber,
  );
}

class _CoverageStatus {
  const _CoverageStatus(this.label, this.ops, this.api, this.color);
  final String label;
  final String ops;
  final String api;
  final Color color;
}
