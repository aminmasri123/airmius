import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

class ApiStateEmptyErrorSuiteScreen extends StatefulWidget {
  const ApiStateEmptyErrorSuiteScreen({super.key});

  @override
  State<ApiStateEmptyErrorSuiteScreen> createState() => _ApiStateEmptyErrorSuiteScreenState();
}

class _ApiStateEmptyErrorSuiteScreenState extends State<ApiStateEmptyErrorSuiteScreen> {
  String state = 'Loading';
  bool showRetry = true;
  bool showOfflineBanner = true;
  bool showSkeletons = true;
  bool logApiErrors = true;

  @override
  Widget build(BuildContext context) {
    final states = [
      const _ApiStateRow(
        title: 'Loading',
        status: 'Skeleton',
        body: 'Listen, Karten, Clubprofile und Formulare zeigen während API-Ladevorgaengen ruhige Skeleton-Zustaende.',
        icon: Icons.hourglass_empty_outlined,
        color: AirmiusColors.blue,
      ),
      const _ApiStateRow(
        title: 'Empty',
        status: 'Leer',
        body: 'Keine Vereine, keine Teams, keine Tickets oder keine Rechnungen bekommen klare Hilfetexte und naechste Aktionen.',
        icon: Icons.inbox_outlined,
        color: AirmiusColors.green,
      ),
      const _ApiStateRow(
        title: 'Error',
        status: 'Retry',
        body: 'API-Fehler zeigen freundliche Meldungen, Retry, Support-Hinweis und keine rohen technischen Details.',
        icon: Icons.error_outline,
        color: AirmiusColors.amber,
      ),
      const _ApiStateRow(
        title: 'Offline',
        status: 'Cache',
        body: 'Offline-Zustaende zeigen lokale Daten, Synchronisationsstatus und sichere Aktionen ohne Datenverlust.',
        icon: Icons.cloud_off_outlined,
        color: AirmiusColors.pink,
      ),
    ];

    return PageFrame(
      title: 'API States',
      subtitle: 'Loading, Empty, Error und Retry',
      actions: const [AirmiusLogoMark(size: 34)],
      child: ListView(
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 28),
        children: [
          AirmiusPanel(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const SectionLabel('API UX'),
                const SizedBox(height: 8),
                const Text(
                  'Wenn Laravel später angebunden wird, braucht jede mobile Seite klare Zustaende: Laden, leer, Fehler, Retry, Offline, Cache und Synchronisation.',
                  style: TextStyle(color: AirmiusColors.text, height: 1.45, fontWeight: FontWeight.w800),
                ),
                const SizedBox(height: 16),
                GridWrap(
                  children: const [
                    Metric(value: '4', label: 'States'),
                    Metric(value: 'Retry', label: 'Aktion'),
                    Metric(value: 'Cache', label: 'Offline'),
                    Metric(value: 'Safe', label: 'UX'),
                  ],
                ),
              ],
            ),
          ),
          const SizedBox(height: 14),
          AirmiusPanel(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const SectionLabel('VORSCHAU-STATUS'),
                const SizedBox(height: 12),
                SegmentedButton<String>(
                  segments: const [
                    ButtonSegment(value: 'Loading', label: Text('Loading')),
                    ButtonSegment(value: 'Empty', label: Text('Empty')),
                    ButtonSegment(value: 'Error', label: Text('Error')),
                    ButtonSegment(value: 'Offline', label: Text('Offline')),
                  ],
                  selected: {state},
                  onSelectionChanged: (value) => setState(() => state = value.first),
                ),
              ],
            ),
          ),
          const SizedBox(height: 14),
          AirmiusPanel(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const SectionLabel('REGELN'),
                const SizedBox(height: 8),
                _ApiStateSwitch(title: 'Retry anzeigen', value: showRetry, color: AirmiusColors.blue, onChanged: (value) => setState(() => showRetry = value)),
                _ApiStateSwitch(title: 'Offline-Banner anzeigen', value: showOfflineBanner, color: AirmiusColors.green, onChanged: (value) => setState(() => showOfflineBanner = value)),
                _ApiStateSwitch(title: 'Skeletons nutzen', value: showSkeletons, color: AirmiusColors.amber, onChanged: (value) => setState(() => showSkeletons = value)),
                _ApiStateSwitch(title: 'API-Fehler protokollieren', value: logApiErrors, color: AirmiusColors.pink, onChanged: (value) => setState(() => logApiErrors = value)),
              ],
            ),
          ),
          const SizedBox(height: 14),
          for (final item in states) ...[
            _ApiStateCard(item: item),
            const SizedBox(height: 12),
          ],
          AirmiusPanel(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const SectionLabel('AKTUELLE VORSCHAU'),
                const SizedBox(height: 8),
                Text(
                  'Aktueller Zustand: $state. Später verbindet die API jeden Screen mit Success, Loading, Empty, Error, Retry, Offline und Cache-Status.',
                  style: const TextStyle(color: AirmiusColors.muted, height: 1.45, fontWeight: FontWeight.w700),
                ),
                const SizedBox(height: 14),
                AirmiusButton(
                  label: 'State testen',
                  icon: Icons.sync_problem_outlined,
                  onPressed: () => openUiAction(
                    context,
                    title: 'API-State testen',
                    body: 'Diese UI bereitet Lade-, Leer-, Fehler-, Retry-, Offline- und Cache-Zustaende für die spätere Laravel-API vor.',
                    status: 'UI vorbereitet',
                    icon: Icons.sync_problem_outlined,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _ApiStateRow {
  const _ApiStateRow({
    required this.title,
    required this.status,
    required this.body,
    required this.icon,
    required this.color,
  });

  final String title;
  final String status;
  final String body;
  final IconData icon;
  final Color color;
}

class _ApiStateSwitch extends StatelessWidget {
  const _ApiStateSwitch({
    required this.title,
    required this.value,
    required this.color,
    required this.onChanged,
  });

  final String title;
  final bool value;
  final Color color;
  final ValueChanged<bool> onChanged;

  @override
  Widget build(BuildContext context) {
    return SwitchListTile.adaptive(
      contentPadding: EdgeInsets.zero,
      title: Text(title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
      value: value,
      activeThumbColor: color,
      onChanged: onChanged,
    );
  }
}

class _ApiStateCard extends StatelessWidget {
  const _ApiStateCard({required this.item});

  final _ApiStateRow item;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          IconBadge(icon: item.icon, color: item.color),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Expanded(child: Text(item.title, style: const TextStyle(color: AirmiusColors.text, fontSize: 17, fontWeight: FontWeight.w900))),
                    StatusPill(item.status, color: item.color),
                  ],
                ),
                const SizedBox(height: 8),
                Text(item.body, style: const TextStyle(color: AirmiusColors.muted, height: 1.42, fontWeight: FontWeight.w700)),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
