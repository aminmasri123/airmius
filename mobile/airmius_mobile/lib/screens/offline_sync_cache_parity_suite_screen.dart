import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

class OfflineSyncCacheParitySuiteScreen extends StatefulWidget {
  const OfflineSyncCacheParitySuiteScreen({super.key});

  @override
  State<OfflineSyncCacheParitySuiteScreen> createState() =>
      _OfflineSyncCacheParitySuiteScreenState();
}

class _OfflineSyncCacheParitySuiteScreenState
    extends State<OfflineSyncCacheParitySuiteScreen> {
  String _network = 'Schwach';
  String _strategy = 'Queue';
  bool _offlineBanner = true;
  bool _retryEnabled = true;
  bool _conflictMode = false;

  static const _networks = ['Online', 'Schwach', 'Offline'];
  static const _strategies = ['Live', 'Cache', 'Queue', 'Merge'];

  static const _states = <_SyncState>[
    _SyncState(
      title: 'Mitgliedsantrag zwischenspeichern',
      area: 'Membership',
      body:
          'Langer Antrag bleibt lokal erhalten, wenn Internet weg ist. Pflichtfelder, Dokumente und Zahlweise werden später synchronisiert.',
      status: 'Queued',
      icon: Icons.assignment_ind_outlined,
      primary: 'Queue ansehen',
      secondary: 'Retry',
      color: AirmiusColors.green,
    ),
    _SyncState(
      title: 'Chat-Nachricht senden',
      area: 'Chat',
      body:
          'Nachricht, Anhang und Lesestatus werden lokal markiert und nach Verbindung automatisch erneut gesendet.',
      status: 'Pending',
      icon: Icons.forum_outlined,
      primary: 'Nachricht syncen',
      secondary: 'Status',
      color: AirmiusColors.blue,
    ),
    _SyncState(
      title: 'Datei-Upload fortsetzen',
      area: 'Files',
      body:
          'Upload-Fortschritt, Dateimanager-Verknüpfung und Datenschutz-Zweckbindung bleiben sichtbar, bis Laravel Storage bestätigt.',
      status: 'Resume',
      icon: Icons.cloud_upload_outlined,
      primary: 'Fortsetzen',
      secondary: 'Vorschau',
      color: AirmiusColors.amber,
    ),
    _SyncState(
      title: 'Training Log offline erfassen',
      area: 'Training',
      body:
          'Training, Messwerte, Notizen und Coach-Sichtbarkeit können offline vorbereitet und später mit API-Konfliktprüfung gesendet werden.',
      status: 'Draft',
      icon: Icons.fitness_center_outlined,
      primary: 'Draft speichern',
      secondary: 'Sync Plan',
      color: AirmiusColors.green,
    ),
    _SyncState(
      title: 'Checkout-Status aktualisieren',
      area: 'Commerce',
      body:
          'Banktransfer, Order Status, Rechnung und Zahlungsstatus brauchen klare Lade-, Retry- und Konfliktanzeige.',
      status: 'Retry',
      icon: Icons.receipt_long_outlined,
      primary: 'Status laden',
      secondary: 'Beleg',
      color: AirmiusColors.amber,
    ),
    _SyncState(
      title: 'Admin-Entscheidung konfliktfrei speichern',
      area: 'Admin',
      body:
          'Wenn zwei Admins dieselbe Anfrage bearbeiten, zeigt die App Konflikt, alten Wert, neuen Wert und sichere Entscheidung.',
      status: 'Conflict',
      icon: Icons.warning_amber_outlined,
      primary: 'Konflikt lösen',
      secondary: 'Audit',
      color: AirmiusColors.red,
    ),
  ];

  @override
  Widget build(BuildContext context) {
    final queued = _strategy == 'Queue' || _network == 'Offline';

    return Scaffold(
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      appBar: AppBar(
        backgroundColor:
            Theme.of(context).appBarTheme.backgroundColor ??
            airmiusSurfaceColor(context),
        surfaceTintColor: Colors.transparent,
        title: const AirmiusLogo(compact: true),
      ),
      body: SafeArea(
        child: PageFrame(
          title: 'Offline Sync Cache Parity',
          subtitle: 'Mobile API-Zustände für Laravel-Anbindung vorbereiten.',
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              _Hero(
                network: _network,
                strategy: _strategy,
                offlineBanner: _offlineBanner,
                queued: queued,
              ),
              const SizedBox(height: 16),
              if (_offlineBanner)
                _NetworkBanner(
                  network: _network,
                  onRetry: () => openUiAction(
                    context,
                    title: 'Verbindung prüfen',
                    body:
                        'Netzwerkstatus $_network, Strategie $_strategy, Queue $queued und Retry $_retryEnabled als mobile API-Zustände prüfen.',
                    status: 'Network',
                    icon: Icons.sync_outlined,
                  ),
                ),
              if (_offlineBanner) const SizedBox(height: 16),
              _ChoicePanel(
                title: 'Netzwerkzustand',
                items: _networks,
                active: _network,
                onChanged: (value) => setState(() => _network = value),
                color: airmiusAccentColor(context),
              ),
              const SizedBox(height: 16),
              _ChoicePanel(
                title: 'Sync-Strategie',
                items: _strategies,
                active: _strategy,
                onChanged: (value) => setState(() => _strategy = value),
                color: Theme.of(context).colorScheme.secondary,
              ),
              const SizedBox(height: 16),
              _RulesPanel(
                offlineBanner: _offlineBanner,
                retryEnabled: _retryEnabled,
                conflictMode: _conflictMode,
                onBanner: (value) => setState(() => _offlineBanner = value),
                onRetry: (value) => setState(() => _retryEnabled = value),
                onConflict: (value) => setState(() => _conflictMode = value),
              ),
              const SizedBox(height: 16),
              _QueuePanel(
                queued: queued,
                conflictMode: _conflictMode,
                onOpen: () => openUiAction(
                  context,
                  title: 'Sync Queue',
                  body:
                      'Lokale Warteschlange, Retry-Status, Konflikte, Drafts und letzte Synchronisierung als UI vorbereitet.',
                  status: queued ? 'Queued' : 'Clean',
                  icon: Icons.history_outlined,
                ),
              ),
              const SizedBox(height: 16),
              for (final state in _states) ...[
                _SyncStateCard(
                  state: state,
                  retryEnabled: _retryEnabled,
                  conflictMode: _conflictMode,
                ),
                const SizedBox(height: 12),
              ],
              const SizedBox(height: 4),
              _Checklist(
                onOpen: () => openUiAction(
                  context,
                  title: 'Offline Sync Parity',
                  body:
                      'Offline-Banner, Cache, Retry, Queue, Drafts, Upload Resume, Konflikte und Sync-Historie sind für mobile Laravel-API-Anbindung vorbereitet.',
                  status: 'Offline Sync',
                  icon: Icons.fact_check_outlined,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _Hero extends StatelessWidget {
  const _Hero({
    required this.network,
    required this.strategy,
    required this.offlineBanner,
    required this.queued,
  });

  final String network;
  final String strategy;
  final bool offlineBanner;
  final bool queued;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      gradient: true,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Eyebrow('OFFLINE & SYNC'),
          const SizedBox(height: 8),
          Text(
            'Mobile Apps brauchen gute Zustände, nicht nur gute Screens.',
            style: TextStyle(
              color: airmiusTextColor(context),
              fontSize: 24,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 8),
          Text(
            'Wenn Laravel später per API angebunden wird, zeigt Flutter bereits Offline, Cache, Queue, Retry, Upload Resume, Konflikte und Sync-Historie im Airmius-Stil.',
            style: TextStyle(
              color: airmiusMutedColor(context),
              height: 1.45,
              fontWeight: FontWeight.w700,
            ),
          ),
          const SizedBox(height: 16),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              _Metric(value: network, label: 'Netz'),
              _Metric(value: strategy, label: 'Strategie'),
              _Metric(value: offlineBanner ? 'An' : 'Aus', label: 'Banner'),
              _Metric(value: queued ? 'Queue' : 'Clean', label: 'Status'),
            ],
          ),
        ],
      ),
    );
  }
}

class _NetworkBanner extends StatelessWidget {
  const _NetworkBanner({required this.network, required this.onRetry});

  final String network;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    final offline = network == 'Offline';
    final color = offline
        ? Theme.of(context).colorScheme.error
        : Theme.of(context).colorScheme.tertiary;
    return AirmiusPanel(
      borderColor: color,
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(
            offline ? Icons.wifi_off_outlined : Icons.sync_problem_outlined,
            color: color,
            size: 30,
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  offline ? 'Du bist offline' : 'Verbindung ist schwach',
                  style: TextStyle(
                    color: airmiusTextColor(context),
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 5),
                Text(
                  'Airmius speichert Aktionen lokal und synchronisiert sie, sobald die Verbindung wieder stabil ist.',
                  style: TextStyle(
                    color: airmiusMutedColor(context),
                    height: 1.35,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ],
            ),
          ),
          IconButton(
            onPressed: onRetry,
            icon: Icon(
              Icons.refresh_outlined,
              color: airmiusTextColor(context),
            ),
          ),
        ],
      ),
    );
  }
}

class _ChoicePanel extends StatelessWidget {
  const _ChoicePanel({
    required this.title,
    required this.items,
    required this.active,
    required this.onChanged,
    required this.color,
  });

  final String title;
  final List<String> items;
  final String active;
  final ValueChanged<String> onChanged;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      title: title,
      children: [
        Wrap(
          spacing: 8,
          runSpacing: 8,
          children: items
              .map(
                (item) => ChoiceChip(
                  selected: active == item,
                  label: Text(item),
                  onSelected: (_) => onChanged(item),
                  selectedColor: color.withValues(alpha: .24),
                  backgroundColor: airmiusSurfaceSoftColor(context),
                  side: BorderSide(
                    color: active == item ? color : airmiusBorderColor(context),
                  ),
                  labelStyle: TextStyle(
                    color: active == item
                        ? airmiusTextColor(context)
                        : airmiusMutedColor(context),
                    fontWeight: FontWeight.w900,
                  ),
                ),
              )
              .toList(),
        ),
      ],
    );
  }
}

class _RulesPanel extends StatelessWidget {
  const _RulesPanel({
    required this.offlineBanner,
    required this.retryEnabled,
    required this.conflictMode,
    required this.onBanner,
    required this.onRetry,
    required this.onConflict,
  });

  final bool offlineBanner;
  final bool retryEnabled;
  final bool conflictMode;
  final ValueChanged<bool> onBanner;
  final ValueChanged<bool> onRetry;
  final ValueChanged<bool> onConflict;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      title: 'Sync-Regeln',
      subtitle:
          'Diese Flags werden später durch API-Client, Storage und Netzwerkstatus gesteuert.',
      children: [
        _SwitchLine(
          title: 'Offline-Banner anzeigen',
          value: offlineBanner,
          onChanged: onBanner,
        ),
        _SwitchLine(
          title: 'Retry-Aktionen aktivieren',
          value: retryEnabled,
          onChanged: onRetry,
        ),
        _SwitchLine(
          title: 'Konfliktmodus simulieren',
          value: conflictMode,
          onChanged: onConflict,
        ),
      ],
    );
  }
}

class _QueuePanel extends StatelessWidget {
  const _QueuePanel({
    required this.queued,
    required this.conflictMode,
    required this.onOpen,
  });

  final bool queued;
  final bool conflictMode;
  final VoidCallback onOpen;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      borderColor: conflictMode
          ? Theme.of(context).colorScheme.error
          : Theme.of(context).colorScheme.secondary,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(
                conflictMode
                    ? Icons.warning_amber_outlined
                    : Icons.storage_outlined,
                color: conflictMode
                    ? Theme.of(context).colorScheme.error
                    : Theme.of(context).colorScheme.secondary,
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Text(
                  queued
                      ? 'Lokale Warteschlange aktiv'
                      : 'Keine offenen Sync-Aktionen',
                  style: TextStyle(
                    color: airmiusTextColor(context),
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ),
              StatusPill(
                conflictMode
                    ? 'Konflikt'
                    : queued
                    ? 'Queue'
                    : 'Clean',
                color: conflictMode
                    ? Theme.of(context).colorScheme.error
                    : Theme.of(context).colorScheme.secondary,
              ),
            ],
          ),
          const SizedBox(height: 10),
          Text(
            'Mobile Aktionen behalten ihren Zustand: Draft, Pending, Uploading, Synced, Failed oder Conflict.',
            style: TextStyle(
              color: airmiusMutedColor(context),
              height: 1.4,
              fontWeight: FontWeight.w700,
            ),
          ),
          const SizedBox(height: 14),
          AirmiusButton(
            label: 'Sync-Historie',
            icon: Icons.history_outlined,
            secondary: true,
            onPressed: onOpen,
          ),
        ],
      ),
    );
  }
}

class _SyncStateCard extends StatelessWidget {
  const _SyncStateCard({
    required this.state,
    required this.retryEnabled,
    required this.conflictMode,
  });

  final _SyncState state;
  final bool retryEnabled;
  final bool conflictMode;

  @override
  Widget build(BuildContext context) {
    final danger = conflictMode && state.status == 'Conflict';
    final stateColor = airmiusSemanticColor(context, state.color);
    final dangerColor = Theme.of(context).colorScheme.error;
    return AirmiusPanel(
      borderColor: (danger ? dangerColor : stateColor).withValues(alpha: .55),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                width: 48,
                height: 48,
                decoration: BoxDecoration(
                  color: stateColor.withValues(alpha: .14),
                  borderRadius: BorderRadius.circular(16),
                  border: Border.all(color: stateColor.withValues(alpha: .55)),
                ),
                child: Icon(state.icon, color: stateColor),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      state.title,
                      style: TextStyle(
                        color: airmiusTextColor(context),
                        fontSize: 17,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(height: 5),
                    Text(
                      state.area,
                      style: TextStyle(
                        color: airmiusAccentColor(context),
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ],
                ),
              ),
              StatusPill(
                state.status,
                color: danger ? dangerColor : stateColor,
              ),
            ],
          ),
          const SizedBox(height: 12),
          Text(
            state.body,
            style: TextStyle(
              color: airmiusMutedColor(context),
              height: 1.42,
              fontWeight: FontWeight.w700,
            ),
          ),
          const SizedBox(height: 14),
          Wrap(
            spacing: 10,
            runSpacing: 10,
            children: [
              AirmiusButton(
                label: state.primary,
                icon: state.icon,
                danger: danger,
                onPressed: () => openUiAction(
                  context,
                  title: state.primary,
                  body: '${state.title}: ${state.body}',
                  status: state.status,
                  icon: state.icon,
                ),
              ),
              AirmiusButton(
                label: state.secondary,
                icon: retryEnabled
                    ? Icons.refresh_outlined
                    : Icons.info_outline,
                secondary: true,
                onPressed: () => openUiAction(
                  context,
                  title: state.secondary,
                  body:
                      'Retry, Cache, Queue, API-Fehler, Konfliktstatus und letzte Synchronisierung für ${state.title}.',
                  status: retryEnabled ? 'Retry aktiv' : 'Info',
                  icon: retryEnabled
                      ? Icons.refresh_outlined
                      : Icons.info_outline,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _Checklist extends StatelessWidget {
  const _Checklist({required this.onOpen});

  final VoidCallback onOpen;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      title: 'Offline-/Sync-Parität',
      subtitle:
          'Was für die spätere Laravel-API-Verbindung sichtbar vorbereitet ist.',
      children: [
        const _CheckLine(
          'Offline-Banner, schwache Verbindung und Retry bleiben für User sichtbar.',
        ),
        const _CheckLine(
          'Lange Formulare, Uploads, Chat und Training können als lokale Drafts/Queue abgebildet werden.',
        ),
        const _CheckLine(
          'Konflikte zeigen alten Wert, neuen Wert, Bearbeiter und sichere Auflösung.',
        ),
        const _CheckLine(
          'Cache, Sync-Historie und API-Fehler bekommen eigene mobile Statuskarten.',
        ),
        const SizedBox(height: 12),
        AirmiusButton(
          label: 'Sync-Parität markieren',
          icon: Icons.fact_check_outlined,
          onPressed: onOpen,
        ),
      ],
    );
  }
}

class _SwitchLine extends StatelessWidget {
  const _SwitchLine({
    required this.title,
    required this.value,
    required this.onChanged,
  });

  final String title;
  final bool value;
  final ValueChanged<bool> onChanged;

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(top: 10),
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
      decoration: BoxDecoration(
        color: airmiusInputColor(context),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: airmiusBorderColor(context)),
      ),
      child: Row(
        children: [
          Expanded(
            child: Text(
              title,
              style: TextStyle(
                color: airmiusTextColor(context),
                fontWeight: FontWeight.w900,
              ),
            ),
          ),
          Switch(
            value: value,
            activeThumbColor: Theme.of(context).colorScheme.secondary,
            onChanged: onChanged,
          ),
        ],
      ),
    );
  }
}

class _CheckLine extends StatelessWidget {
  const _CheckLine(this.text);

  final String text;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(top: 8),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(
            Icons.check_circle_outline,
            color: Theme.of(context).colorScheme.secondary,
            size: 19,
          ),
          const SizedBox(width: 8),
          Expanded(
            child: Text(
              text,
              style: TextStyle(
                color: airmiusMutedColor(context),
                fontWeight: FontWeight.w700,
                height: 1.35,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _Metric extends StatelessWidget {
  const _Metric({required this.value, required this.label});

  final String value;
  final String label;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
      decoration: BoxDecoration(
        color: airmiusSurfaceColor(context).withValues(alpha: .55),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: airmiusBorderColor(context)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            value,
            style: TextStyle(
              color: airmiusTextColor(context),
              fontSize: 18,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 2),
          Text(
            label,
            style: TextStyle(
              color: airmiusMutedColor(context),
              fontWeight: FontWeight.w700,
            ),
          ),
        ],
      ),
    );
  }
}

class _SyncState {
  const _SyncState({
    required this.title,
    required this.area,
    required this.body,
    required this.status,
    required this.icon,
    required this.primary,
    required this.secondary,
    required this.color,
  });

  final String title;
  final String area;
  final String body;
  final String status;
  final IconData icon;
  final String primary;
  final String secondary;
  final Color color;
}
