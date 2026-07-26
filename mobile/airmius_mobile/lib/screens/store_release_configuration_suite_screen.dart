import 'package:flutter/material.dart';

import '../core/airmius_store_release_config.dart';
import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

class StoreReleaseConfigurationSuiteScreen extends StatelessWidget {
  const StoreReleaseConfigurationSuiteScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final config = airmiusStoreReleaseConfig;
    return Scaffold(
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      appBar: AppBar(
        backgroundColor:
            Theme.of(context).appBarTheme.backgroundColor ??
            airmiusSurfaceColor(context),
        surfaceTintColor: Colors.transparent,
        title: const Text(
          'Store Konfiguration',
          style: TextStyle(fontWeight: FontWeight.w900),
        ),
      ),
      body: PageFrame(
        title: 'Store Konfiguration',
        subtitle:
            'App-Metadaten, Bundle ID, Store-Texte, Berechtigungen, Datenschutz und Release-Gates.',
        trailing: StatusPill(
          '1% Rest',
          color: Theme.of(context).colorScheme.tertiary,
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            AirmiusPanel(
              gradient: true,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Eyebrow('STORE READY CONFIG'),
                  const SizedBox(height: 10),
                  Text(
                    config.appName,
                    style: TextStyle(
                      color: airmiusTextColor(context),
                      fontSize: 26,
                      fontWeight: FontWeight.w900,
                      height: 1.05,
                    ),
                  ),
                  const SizedBox(height: 6),
                  Text(
                    config.shortDescription,
                    style: TextStyle(
                      color: airmiusMutedColor(context),
                      height: 1.4,
                    ),
                  ),
                  const SizedBox(height: 12),
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: [
                      StatusPill(
                        config.bundleId,
                        color: airmiusAccentColor(context),
                      ),
                      StatusPill(
                        config.supportEmail,
                        color: Theme.of(context).colorScheme.secondary,
                      ),
                    ],
                  ),
                ],
              ),
            ),
            const SizedBox(height: 14),
            Row(
              children: const [
                Expanded(
                  child: MetricCard(value: '99%', label: 'Fertig'),
                ),
                SizedBox(width: 10),
                Expanded(
                  child: MetricCard(value: '1%', label: 'Rest'),
                ),
                SizedBox(width: 10),
                Expanded(
                  child: MetricCard(value: '92%', label: 'Store'),
                ),
              ],
            ),
            const SizedBox(height: 14),
            AirmiusPanel(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Eyebrow('STORE TEXT'),
                  const SizedBox(height: 10),
                  Text(
                    config.longDescription,
                    style: TextStyle(
                      color: airmiusMutedColor(context),
                      height: 1.42,
                    ),
                  ),
                  const SizedBox(height: 10),
                  _InfoLine(label: 'Datenschutz', value: config.privacyUrl),
                ],
              ),
            ),
            const SizedBox(height: 14),
            for (final item in config.permissionJustifications) ...[
              AirmiusPanel(
                borderColor: airmiusAccentColor(context).withValues(alpha: .38),
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Container(
                      width: 46,
                      height: 46,
                      decoration: BoxDecoration(
                        color: airmiusAccentColor(
                          context,
                        ).withValues(alpha: .14),
                        borderRadius: BorderRadius.circular(15),
                        border: Border.all(
                          color: airmiusAccentColor(
                            context,
                          ).withValues(alpha: .42),
                        ),
                      ),
                      child: Icon(
                        Icons.privacy_tip_outlined,
                        color: airmiusAccentColor(context),
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            item.permission,
                            style: TextStyle(
                              color: airmiusTextColor(context),
                              fontWeight: FontWeight.w900,
                              fontSize: 16,
                            ),
                          ),
                          const SizedBox(height: 5),
                          Text(
                            item.reason,
                            style: TextStyle(
                              color: airmiusMutedColor(context),
                              height: 1.35,
                            ),
                          ),
                          const SizedBox(height: 10),
                          StatusPill(
                            item.storeText,
                            color: Theme.of(context).colorScheme.secondary,
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 12),
            ],
            AirmiusPanel(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Eyebrow('RELEASE GATES'),
                  const SizedBox(height: 12),
                  for (final gate in config.releaseGates) ...[
                    _GateRow(gate: gate),
                    if (gate != config.releaseGates.last)
                      const SizedBox(height: 10),
                  ],
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _InfoLine extends StatelessWidget {
  const _InfoLine({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) => Row(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      SizedBox(
        width: 100,
        child: Text(
          label,
          style: TextStyle(
            color: airmiusAccentColor(context),
            fontWeight: FontWeight.w900,
          ),
        ),
      ),
      Expanded(
        child: Text(
          value,
          style: TextStyle(color: airmiusMutedColor(context), height: 1.35),
        ),
      ),
    ],
  );
}

class _GateRow extends StatelessWidget {
  const _GateRow({required this.gate});

  final AirmiusReleaseGate gate;

  @override
  Widget build(BuildContext context) => Row(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      Container(
        width: 34,
        height: 34,
        alignment: Alignment.center,
        decoration: BoxDecoration(
          color: Theme.of(context).colorScheme.tertiary.withValues(alpha: .16),
          borderRadius: BorderRadius.circular(12),
          border: Border.all(
            color: Theme.of(
              context,
            ).colorScheme.tertiary.withValues(alpha: .42),
          ),
        ),
        child: Icon(
          Icons.flag_outlined,
          color: Theme.of(context).colorScheme.tertiary,
          size: 19,
        ),
      ),
      const SizedBox(width: 12),
      Expanded(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              gate.title,
              style: TextStyle(
                color: airmiusTextColor(context),
                fontWeight: FontWeight.w900,
              ),
            ),
            const SizedBox(height: 4),
            Text(
              '${gate.owner} - ${gate.status}',
              style: TextStyle(
                color: airmiusAccentColor(context),
                fontWeight: FontWeight.w800,
              ),
            ),
            const SizedBox(height: 4),
            Text(
              gate.remainingWork,
              style: TextStyle(color: airmiusMutedColor(context), height: 1.35),
            ),
          ],
        ),
      ),
    ],
  );
}
