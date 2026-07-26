import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

class StateFeedbackParitySuiteScreen extends StatefulWidget {
  const StateFeedbackParitySuiteScreen({super.key});

  @override
  State<StateFeedbackParitySuiteScreen> createState() =>
      _StateFeedbackParitySuiteScreenState();
}

class _StateFeedbackParitySuiteScreenState
    extends State<StateFeedbackParitySuiteScreen> {
  String _module = 'Mitgliedschaft';
  String _state = 'Loading';
  bool _showRetry = true;
  bool _showActionHint = true;
  bool _showApiCode = true;

  static const _modules = [
    'Mitgliedschaft',
    'Chat',
    'Dateien',
    'Commerce',
    'Training',
    'Admin',
  ];
  static const _states = [
    'Loading',
    'Empty',
    'Error',
    'Success',
    'Unauthorized',
    'Rate Limit',
  ];

  static const _patterns = <_FeedbackPattern>[
    _FeedbackPattern(
      state: 'Loading',
      title: 'Skeleton Loading',
      body:
          'Listen, Karten, Profile, Formulare und Dashboard-Kennzahlen zeigen Platzhalter statt flackernder leerer Bereiche.',
      status: 'Skeleton',
      icon: Icons.hourglass_empty,
      primary: 'Skeleton anzeigen',
      secondary: 'Timeout',
      color: AirmiusColors.blue,
    ),
    _FeedbackPattern(
      state: 'Empty',
      title: 'Leerer Zustand',
      body:
          'Keine Daten wird immer mit Erklaerung, passender Illustration, Hauptaktion und optionalem Filter-Reset angezeigt.',
      status: 'Empty',
      icon: Icons.inbox_outlined,
      primary: 'Erste Aktion',
      secondary: 'Filter reset',
      color: AirmiusColors.amber,
    ),
    _FeedbackPattern(
      state: 'Error',
      title: 'API-Fehler',
      body:
          'Fehler zeigen lesbare Ursache, Retry, Supportweg, API-Code, Cache-Hinweis und sichere Rücknavigation.',
      status: 'Error',
      icon: Icons.error_outline,
      primary: 'Erneut versuchen',
      secondary: 'Support',
      color: AirmiusColors.red,
    ),
    _FeedbackPattern(
      state: 'Success',
      title: 'Erfolgreiche Aktion',
      body:
          'Speichern, Senden, Upload, Checkout oder Entscheidung bestätigt die Aktion und zeigt die naechste sinnvolle Route.',
      status: 'Success',
      icon: Icons.task_alt_outlined,
      primary: 'Weiter',
      secondary: 'Details',
      color: AirmiusColors.green,
    ),
    _FeedbackPattern(
      state: 'Unauthorized',
      title: 'Kein Zugriff',
      body:
          'Fehlende Rolle, falscher Workspace, Guardian-Sperre oder Club-Kontext werden klar und ohne Schuldgefuehl erklaert.',
      status: '403',
      icon: Icons.lock_outline,
      primary: 'Zugriff anfragen',
      secondary: 'Zurück',
      color: AirmiusColors.amber,
    ),
    _FeedbackPattern(
      state: 'Rate Limit',
      title: 'Zu viele Aktionen',
      body:
          'Login, Suche, Chat, Upload oder Admin-Aktionen zeigen Cooldown, verbleibende Zeit und sichere Alternative.',
      status: 'Limit',
      icon: Icons.timer_outlined,
      primary: 'Später erneut',
      secondary: 'Warum?',
      color: AirmiusColors.red,
    ),
  ];

  List<_FeedbackPattern> get _visiblePatterns =>
      _patterns.where((pattern) => pattern.state == _state).toList();

  @override
  Widget build(BuildContext context) {
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
          title: 'State Feedback Parity',
          subtitle:
              'Loading, Empty, Error, Success und API-Feedback als mobile Muster.',
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              _Hero(
                module: _module,
                state: _state,
                showRetry: _showRetry,
                showApiCode: _showApiCode,
              ),
              const SizedBox(height: 16),
              _ChoicePanel(
                title: 'Modul',
                items: _modules,
                active: _module,
                color: airmiusAccentColor(context),
                onChanged: (value) => setState(() => _module = value),
              ),
              const SizedBox(height: 16),
              _ChoicePanel(
                title: 'UI-Zustand',
                items: _states,
                active: _state,
                color: Theme.of(context).colorScheme.secondary,
                onChanged: (value) => setState(() => _state = value),
              ),
              const SizedBox(height: 16),
              _RulesPanel(
                showRetry: _showRetry,
                showActionHint: _showActionHint,
                showApiCode: _showApiCode,
                onRetry: (value) => setState(() => _showRetry = value),
                onActionHint: (value) =>
                    setState(() => _showActionHint = value),
                onApiCode: (value) => setState(() => _showApiCode = value),
              ),
              const SizedBox(height: 16),
              _StatePreview(
                module: _module,
                state: _state,
                showRetry: _showRetry,
                showActionHint: _showActionHint,
                showApiCode: _showApiCode,
              ),
              const SizedBox(height: 16),
              for (final pattern in _visiblePatterns) ...[
                _FeedbackPatternCard(
                  pattern: pattern,
                  module: _module,
                  showRetry: _showRetry,
                ),
                const SizedBox(height: 12),
              ],
              if (_visiblePatterns.isEmpty)
                const EmptyPanel(
                  'Kein Feedback-Muster für diesen Zustand sichtbar.',
                ),
              const SizedBox(height: 4),
              _Checklist(
                onOpen: () => openUiAction(
                  context,
                  title: 'State Feedback Parity',
                  body:
                      'Skeleton Loading, Empty, Error, Success, Unauthorized, Rate Limit, Validation, Cache-Hinweis und Retry sind als mobile UI-Muster vorbereitet.',
                  status: 'State UI',
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
    required this.module,
    required this.state,
    required this.showRetry,
    required this.showApiCode,
  });

  final String module;
  final String state;
  final bool showRetry;
  final bool showApiCode;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      gradient: true,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Eyebrow('STATE FEEDBACK'),
          const SizedBox(height: 8),
          Text(
            'Gute Mobile-UI erklaert immer, was gerade passiert.',
            style: TextStyle(
              color: airmiusTextColor(context),
              fontSize: 24,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 8),
          Text(
            'Flutter bekommt wiederverwendbare Zustandsmuster für Ladezeiten, leere Listen, Fehler, Erfolg, fehlende Rechte, Rate Limits, Validierung und API-Retry.',
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
              _Metric(value: module, label: 'Modul'),
              _Metric(value: state, label: 'Zustand'),
              _Metric(value: showRetry ? 'An' : 'Aus', label: 'Retry'),
              _Metric(value: showApiCode ? 'Code' : 'Text', label: 'API Info'),
            ],
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
    required this.color,
    required this.onChanged,
  });

  final String title;
  final List<String> items;
  final String active;
  final Color color;
  final ValueChanged<String> onChanged;

  @override
  Widget build(BuildContext context) {
    final textColor = airmiusTextColor(context);
    final mutedColor = airmiusMutedColor(context);
    final borderColor = airmiusBorderColor(context);
    final surfaceColor = airmiusSurfaceSoftColor(context);
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
                  backgroundColor: surfaceColor,
                  side: BorderSide(color: active == item ? color : borderColor),
                  labelStyle: TextStyle(
                    color: active == item ? textColor : mutedColor,
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
    required this.showRetry,
    required this.showActionHint,
    required this.showApiCode,
    required this.onRetry,
    required this.onActionHint,
    required this.onApiCode,
  });

  final bool showRetry;
  final bool showActionHint;
  final bool showApiCode;
  final ValueChanged<bool> onRetry;
  final ValueChanged<bool> onActionHint;
  final ValueChanged<bool> onApiCode;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      title: 'Feedback-Regeln',
      subtitle:
          'Diese Regeln halten API- und UI-Zustaende in allen Modulen konsistent.',
      children: [
        _SwitchLine(
          title: 'Retry-CTA anzeigen',
          value: showRetry,
          onChanged: onRetry,
        ),
        _SwitchLine(
          title: 'Naechste Aktion erklaeren',
          value: showActionHint,
          onChanged: onActionHint,
        ),
        _SwitchLine(
          title: 'API-Code sichtbar machen',
          value: showApiCode,
          onChanged: onApiCode,
        ),
      ],
    );
  }
}

class _StatePreview extends StatelessWidget {
  const _StatePreview({
    required this.module,
    required this.state,
    required this.showRetry,
    required this.showActionHint,
    required this.showApiCode,
  });

  final String module;
  final String state;
  final bool showRetry;
  final bool showActionHint;
  final bool showApiCode;

  @override
  Widget build(BuildContext context) {
    final color = airmiusSemanticColor(context, _colorForState(state));
    return AirmiusPanel(
      borderColor: color,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(_iconForState(state), color: color, size: 34),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      '$module · $state',
                      style: TextStyle(
                        color: airmiusTextColor(context),
                        fontSize: 18,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(height: 5),
                    Text(
                      showApiCode
                          ? 'API: ${_apiCodeForState(state)}'
                          : 'Lesbare Statusmeldung für User',
                      style: TextStyle(
                        color: airmiusMutedColor(context),
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ],
                ),
              ),
              StatusPill(state, color: color),
            ],
          ),
          const SizedBox(height: 12),
          if (state == 'Loading')
            const _SkeletonRows()
          else
            Text(
              _messageForState(state),
              style: TextStyle(
                color: airmiusMutedColor(context),
                height: 1.42,
                fontWeight: FontWeight.w700,
              ),
            ),
          if (showActionHint) ...[
            const SizedBox(height: 10),
            Text(
              _hintForState(state),
              style: TextStyle(
                color: airmiusAccentColor(context),
                height: 1.35,
                fontWeight: FontWeight.w900,
              ),
            ),
          ],
          const SizedBox(height: 14),
          Wrap(
            spacing: 10,
            runSpacing: 10,
            children: [
              if (showRetry)
                AirmiusButton(
                  label: 'Erneut versuchen',
                  icon: Icons.refresh_outlined,
                  onPressed: () => openUiAction(
                    context,
                    title: 'Retry $module',
                    body:
                        'Retry für $module im Zustand $state mit API-Code ${_apiCodeForState(state)}.',
                    status: state,
                    icon: Icons.refresh_outlined,
                  ),
                ),
              AirmiusButton(
                label: 'Details',
                icon: Icons.info_outline,
                secondary: true,
                onPressed: () => openUiAction(
                  context,
                  title: '$module Details',
                  body:
                      'Zustand $state, API-Code ${_apiCodeForState(state)}, Cache, Permission, Validation und naechste Aktion.',
                  status: 'Details',
                  icon: Icons.info_outline,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _SkeletonRows extends StatelessWidget {
  const _SkeletonRows();

  @override
  Widget build(BuildContext context) {
    return Column(
      children: const [
        _SkeletonLine(widthFactor: .95),
        _SkeletonLine(widthFactor: .72),
        _SkeletonLine(widthFactor: .86),
      ],
    );
  }
}

class _SkeletonLine extends StatelessWidget {
  const _SkeletonLine({required this.widthFactor});

  final double widthFactor;

  @override
  Widget build(BuildContext context) {
    return FractionallySizedBox(
      widthFactor: widthFactor,
      alignment: Alignment.centerLeft,
      child: Container(
        height: 14,
        margin: const EdgeInsets.only(top: 8),
        decoration: BoxDecoration(
          color: airmiusSurfaceSoftColor(context),
          borderRadius: BorderRadius.circular(999),
          border: Border.all(color: airmiusBorderColor(context)),
        ),
      ),
    );
  }
}

class _FeedbackPatternCard extends StatelessWidget {
  const _FeedbackPatternCard({
    required this.pattern,
    required this.module,
    required this.showRetry,
  });

  final _FeedbackPattern pattern;
  final String module;
  final bool showRetry;

  @override
  Widget build(BuildContext context) {
    final color = airmiusSemanticColor(context, pattern.color);
    return AirmiusPanel(
      borderColor: color.withValues(alpha: .55),
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
                  color: color.withValues(alpha: .14),
                  borderRadius: BorderRadius.circular(16),
                  border: Border.all(color: color.withValues(alpha: .55)),
                ),
                child: Icon(pattern.icon, color: color),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      pattern.title,
                      style: TextStyle(
                        color: airmiusTextColor(context),
                        fontSize: 17,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(height: 5),
                    Text(
                      module,
                      style: TextStyle(
                        color: airmiusAccentColor(context),
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ],
                ),
              ),
              StatusPill(pattern.status, color: color),
            ],
          ),
          const SizedBox(height: 12),
          Text(
            pattern.body,
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
                label: pattern.primary,
                icon: pattern.icon,
                danger: pattern.color == AirmiusColors.red,
                onPressed: () => openUiAction(
                  context,
                  title: pattern.primary,
                  body: '$module: ${pattern.title}. ${pattern.body}',
                  status: pattern.status,
                  icon: pattern.icon,
                ),
              ),
              if (showRetry)
                AirmiusButton(
                  label: pattern.secondary,
                  icon: Icons.refresh_outlined,
                  secondary: true,
                  onPressed: () => openUiAction(
                    context,
                    title: pattern.secondary,
                    body:
                        'Retry, Support, Cache, API-Code und naechste Aktion für ${pattern.title} in $module.',
                    status: 'Feedback',
                    icon: Icons.refresh_outlined,
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
      title: 'State-Paritaet',
      subtitle: 'Was für jeden mobilen Screen gelten soll.',
      children: [
        const _CheckLine(
          'Jede Liste, jedes Formular und jedes Detail hat Loading, Empty, Error und Success.',
        ),
        const _CheckLine(
          'API-Fehler werden lesbar erklaert und behalten optional technischen Code.',
        ),
        const _CheckLine(
          'Unauthorized, Guardian-Gates und Rate Limits haben klare naechste Aktionen.',
        ),
        const _CheckLine(
          'Skeleton, Retry, Cache-Hinweis und Supportweg bleiben im Airmius-Design konsistent.',
        ),
        const SizedBox(height: 12),
        AirmiusButton(
          label: 'State-Paritaet markieren',
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

class _FeedbackPattern {
  const _FeedbackPattern({
    required this.state,
    required this.title,
    required this.body,
    required this.status,
    required this.icon,
    required this.primary,
    required this.secondary,
    required this.color,
  });

  final String state;
  final String title;
  final String body;
  final String status;
  final IconData icon;
  final String primary;
  final String secondary;
  final Color color;
}

Color _colorForState(String state) {
  if (state == 'Success') return AirmiusColors.green;
  if (state == 'Error' || state == 'Rate Limit') return AirmiusColors.red;
  if (state == 'Unauthorized' || state == 'Empty') return AirmiusColors.amber;
  return AirmiusColors.blue;
}

IconData _iconForState(String state) {
  if (state == 'Success') return Icons.task_alt_outlined;
  if (state == 'Error') return Icons.error_outline;
  if (state == 'Unauthorized') return Icons.lock_outline;
  if (state == 'Rate Limit') return Icons.timer_outlined;
  if (state == 'Empty') return Icons.inbox_outlined;
  return Icons.hourglass_empty;
}

String _apiCodeForState(String state) {
  if (state == 'Success') return '200';
  if (state == 'Error') return '500';
  if (state == 'Unauthorized') return '403';
  if (state == 'Rate Limit') return '429';
  if (state == 'Empty') return '204';
  return '102';
}

String _messageForState(String state) {
  if (state == 'Success') {
    return 'Die Aktion wurde erfolgreich vorbereitet und die naechste sinnvolle Route ist sichtbar.';
  }
  if (state == 'Error') {
    return 'Etwas hat nicht funktioniert. Die App zeigt Ursache, Retry, Supportweg und sicheren Rückweg.';
  }
  if (state == 'Unauthorized') {
    return 'Du hast für diesen Bereich aktuell keine Berechtigung oder brauchst eine Freigabe.';
  }
  if (state == 'Rate Limit') {
    return 'Diese Aktion wurde zu oft ausgefuehrt. Die App zeigt Cooldown und Alternative.';
  }
  if (state == 'Empty') {
    return 'Hier gibt es noch keine Eintraege. Die App erklaert den Zustand und bietet eine erste Aktion.';
  }
  return 'Die Daten werden geladen.';
}

String _hintForState(String state) {
  if (state == 'Success') {
    return 'Naechster Schritt: Detail öffnen, Liste aktualisieren oder weiterarbeiten.';
  }
  if (state == 'Error') {
    return 'Naechster Schritt: Retry, Cache nutzen oder Support kontaktieren.';
  }
  if (state == 'Unauthorized') {
    return 'Naechster Schritt: Rolle wechseln, Zugriff anfragen oder Guardian-Freigabe prüfen.';
  }
  if (state == 'Rate Limit') {
    return 'Naechster Schritt: warten, Entwurf speichern oder später erneut senden.';
  }
  if (state == 'Empty') {
    return 'Naechster Schritt: erstellen, Filter zurücksetzen oder Einladung senden.';
  }
  return 'Naechster Schritt: Skeleton bleibt stabil, bis API-Daten da sind.';
}
