import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

class AnalyticsChartDashboardParitySuiteScreen extends StatefulWidget {
  const AnalyticsChartDashboardParitySuiteScreen({super.key});

  @override
  State<AnalyticsChartDashboardParitySuiteScreen> createState() =>
      _AnalyticsChartDashboardParitySuiteScreenState();
}

class _AnalyticsChartDashboardParitySuiteScreenState
    extends State<AnalyticsChartDashboardParitySuiteScreen> {
  String _area = 'Verein';
  String _period = '30 Tage';
  bool _showCharts = true;
  bool _showExports = true;
  bool _showEmptyStates = false;

  static const _areas = [
    'Verein',
    'Admin',
    'Commerce',
    'Training',
    'Community',
  ];
  static const _periods = ['7 Tage', '30 Tage', 'Quartal', 'Jahr'];

  static const _cards = <_AnalyticsCard>[
    _AnalyticsCard(
      area: 'Verein',
      title: 'Mitgliedschaftsanfragen',
      value: '24',
      trend: '+18%',
      body:
          'Neue Antraege, Rückzuege, offene Dokumente, angenommene Mitglieder und durchschnittliche Bearbeitungszeit.',
      icon: Icons.assignment_ind_outlined,
      color: AirmiusColors.green,
    ),
    _AnalyticsCard(
      area: 'Verein',
      title: 'Vereinsfinanzen',
      value: '8.420 EUR',
      trend: '+9%',
      body:
          'Beiträge, Rechnungen, offene Zahlungen, Banktransfer, Mahnungen und Monatsabschluss als mobile KPI-Karten.',
      icon: Icons.account_balance_wallet_outlined,
      color: AirmiusColors.amber,
    ),
    _AnalyticsCard(
      area: 'Admin',
      title: 'Moderation Cases',
      value: '13',
      trend: '-4%',
      body:
          'Reports, Eskalationen, Sperren, Bearbeiter, SLA und Trust-Entscheidungen in einer Admin-Reportansicht.',
      icon: Icons.flag_outlined,
      color: AirmiusColors.red,
    ),
    _AnalyticsCard(
      area: 'Admin',
      title: 'Systembetrieb',
      value: '99.8%',
      trend: 'stabil',
      body:
          'Mail, Providerkosten, Webhooks, Wartung, API-Fehler, Queue und Release-Gates als Betriebsübersicht.',
      icon: Icons.monitor_heart_outlined,
      color: AirmiusColors.green,
    ),
    _AnalyticsCard(
      area: 'Commerce',
      title: 'Bestellungen',
      value: '156',
      trend: '+22%',
      body:
          'Marketplace, Checkouts, Banktransfer, Orders, Retouren, Anbieter und Umsatzentwicklung für Mobile Commerce.',
      icon: Icons.storefront_outlined,
      color: AirmiusColors.green,
    ),
    _AnalyticsCard(
      area: 'Commerce',
      title: 'Outfit-Abos',
      value: '42',
      trend: '+6%',
      body:
          'Abos, Lieferungen, Pausen, Zahlstatus, Supportfaelle und offene Pakete als mobile Kennzahlen.',
      icon: Icons.checkroom_outlined,
      color: AirmiusColors.blue,
    ),
    _AnalyticsCard(
      area: 'Training',
      title: 'Trainingsteilnahmen',
      value: '318',
      trend: '+14%',
      body:
          'Events, Zusagen, Wartelisten, No-Shows, Logs, Coach-Feedback und Teamverteilung.',
      icon: Icons.event_available_outlined,
      color: AirmiusColors.green,
    ),
    _AnalyticsCard(
      area: 'Training',
      title: 'Leistungsfortschritt',
      value: '82%',
      trend: '+5%',
      body:
          'Sportprofil, Trainingsplaene, Logs, Zielerreichung, Wellbeing und Coach-Freigabe als mobile Auswertung.',
      icon: Icons.insights_outlined,
      color: AirmiusColors.blue,
    ),
    _AnalyticsCard(
      area: 'Community',
      title: 'Feed Aktivitaet',
      value: '1.2k',
      trend: '+31%',
      body:
          'Posts, Kommentare, Reaktionen, Meldungen, Sichtbarkeit, Vereine, Teams und Community-Wachstum.',
      icon: Icons.dynamic_feed_outlined,
      color: AirmiusColors.blue,
    ),
    _AnalyticsCard(
      area: 'Community',
      title: 'Badges & Learning',
      value: '276',
      trend: '+11%',
      body:
          'Badges, XP, Kurse, Zertifikate, Quiz, Aufgaben und Lernfortschritt als mobile Gamification-Reports.',
      icon: Icons.workspace_premium_outlined,
      color: AirmiusColors.amber,
    ),
  ];

  List<_AnalyticsCard> get _visibleCards =>
      _cards.where((card) => card.area == _area).toList();

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
          title: 'Analytics Chart Dashboard Parity',
          subtitle: 'Mobile KPI-Karten, Trends, Reports und Diagrammzustände.',
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              _Hero(
                area: _area,
                period: _period,
                showCharts: _showCharts,
                showExports: _showExports,
              ),
              const SizedBox(height: 16),
              _ChoicePanel(
                title: 'Dashboard-Bereich',
                items: _areas,
                active: _area,
                onChanged: (value) => setState(() => _area = value),
                color: airmiusAccentColor(context),
              ),
              const SizedBox(height: 16),
              _ChoicePanel(
                title: 'Zeitraum',
                items: _periods,
                active: _period,
                onChanged: (value) => setState(() => _period = value),
                color: Theme.of(context).colorScheme.secondary,
              ),
              const SizedBox(height: 16),
              _RulesPanel(
                showCharts: _showCharts,
                showExports: _showExports,
                showEmptyStates: _showEmptyStates,
                onCharts: (value) => setState(() => _showCharts = value),
                onExports: (value) => setState(() => _showExports = value),
                onEmpty: (value) => setState(() => _showEmptyStates = value),
              ),
              const SizedBox(height: 16),
              if (_showEmptyStates)
                _StatePanel(
                  onRetry: () => openUiAction(
                    context,
                    title: 'Reportdaten neu laden',
                    body:
                        'Loading, Empty, Error, Retry und Cache-Hinweis für mobile Analytics vorbereiten.',
                    status: 'Retry',
                    icon: Icons.refresh_outlined,
                  ),
                )
              else
                _KpiGrid(cards: _visibleCards),
              const SizedBox(height: 16),
              if (_showCharts) _TrendPanel(area: _area, period: _period),
              if (_showCharts) const SizedBox(height: 16),
              for (final card in _visibleCards) ...[
                _ReportCard(
                  card: card,
                  period: _period,
                  showExports: _showExports,
                ),
                const SizedBox(height: 12),
              ],
              const SizedBox(height: 4),
              _Checklist(
                onOpen: () => openUiAction(
                  context,
                  title: 'Analytics Dashboard Parity',
                  body:
                      'Web-Dashboards, KPI-Karten, Diagramme, Trends, Reports, Exporte und Loading/Empty/Error-Zustaende sind als mobile Flutter-UI vorbereitet.',
                  status: 'Analytics',
                  icon: Icons.insights_outlined,
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
    required this.area,
    required this.period,
    required this.showCharts,
    required this.showExports,
  });

  final String area;
  final String period;
  final bool showCharts;
  final bool showExports;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      gradient: true,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Eyebrow('ANALYTICS & REPORTS'),
          const SizedBox(height: 8),
          Text(
            'Dashboards werden mobil zu klaren Entscheidungs-Karten.',
            style: TextStyle(
              color: airmiusTextColor(context),
              fontSize: 24,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 8),
          Text(
            'Flutter übernimmt Web-Reports nicht als breite Diagramme, sondern als KPI-Karten, Trendleisten, Reportdetails, Export-CTAs und saubere Empty/Loading/Error-Zustaende.',
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
              _Metric(value: area, label: 'Bereich'),
              _Metric(value: period, label: 'Zeitraum'),
              _Metric(value: showCharts ? 'An' : 'Aus', label: 'Charts'),
              _Metric(value: showExports ? 'An' : 'Aus', label: 'Export'),
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
    required this.showCharts,
    required this.showExports,
    required this.showEmptyStates,
    required this.onCharts,
    required this.onExports,
    required this.onEmpty,
  });

  final bool showCharts;
  final bool showExports;
  final bool showEmptyStates;
  final ValueChanged<bool> onCharts;
  final ValueChanged<bool> onExports;
  final ValueChanged<bool> onEmpty;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      title: 'Dashboard-Regeln',
      subtitle:
          'Diese Schalter simulieren später API-Daten, Exportrechte und Datenzustand.',
      children: [
        _SwitchLine(
          title: 'Mini-Charts anzeigen',
          value: showCharts,
          onChanged: onCharts,
        ),
        _SwitchLine(
          title: 'Export- und PDF-CTAs anzeigen',
          value: showExports,
          onChanged: onExports,
        ),
        _SwitchLine(
          title: 'Empty/Loading/Error-Zustand simulieren',
          value: showEmptyStates,
          onChanged: onEmpty,
        ),
      ],
    );
  }
}

class _KpiGrid extends StatelessWidget {
  const _KpiGrid({required this.cards});

  final List<_AnalyticsCard> cards;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        for (var i = 0; i < cards.length && i < 2; i++) ...[
          Expanded(
            child: MetricCard(value: cards[i].value, label: cards[i].title),
          ),
          if (i == 0) const SizedBox(width: 10),
        ],
      ],
    );
  }
}

class _TrendPanel extends StatelessWidget {
  const _TrendPanel({required this.area, required this.period});

  final String area;
  final String period;

  @override
  Widget build(BuildContext context) {
    const values = [.35, .58, .44, .72, .63, .86, .76];
    return AirmiusPanel(
      title: 'Trend $area',
      subtitle:
          'Mini-Chart für $period, mobil lesbar ohne große Desktop-Achsen.',
      children: [
        Row(
          crossAxisAlignment: CrossAxisAlignment.end,
          children: [
            for (final value in values) ...[
              Expanded(
                child: Container(
                  height: 92,
                  alignment: Alignment.bottomCenter,
                  child: Container(
                    height: 24 + (value * 68),
                    decoration: BoxDecoration(
                      gradient: LinearGradient(
                        colors: [
                          airmiusAccentColor(context),
                          Theme.of(context).colorScheme.secondary,
                        ],
                        begin: Alignment.bottomCenter,
                        end: Alignment.topCenter,
                      ),
                      borderRadius: BorderRadius.circular(999),
                    ),
                  ),
                ),
              ),
              const SizedBox(width: 7),
            ],
          ],
        ),
        const SizedBox(height: 12),
        Text(
          'Trendbars ersetzen komplexe Web-Charts auf kleinen Screens und fuehren in Detailreports.',
          style: TextStyle(
            color: airmiusMutedColor(context),
            height: 1.35,
            fontWeight: FontWeight.w700,
          ),
        ),
      ],
    );
  }
}

class _StatePanel extends StatelessWidget {
  const _StatePanel({required this.onRetry});

  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      borderColor: Theme.of(context).colorScheme.tertiary,
      child: Column(
        children: [
          Icon(
            Icons.query_stats_outlined,
            color: Theme.of(context).colorScheme.tertiary,
            size: 42,
          ),
          const SizedBox(height: 10),
          Text(
            'Noch keine Reportdaten',
            style: TextStyle(
              color: airmiusTextColor(context),
              fontSize: 18,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 6),
          Text(
            'Empty, Loading, Error und Retry sind eigene mobile Zustände, nicht nur fehlende Daten.',
            textAlign: TextAlign.center,
            style: TextStyle(
              color: airmiusMutedColor(context),
              height: 1.4,
              fontWeight: FontWeight.w700,
            ),
          ),
          const SizedBox(height: 14),
          AirmiusButton(
            label: 'Daten neu laden',
            icon: Icons.refresh_outlined,
            onPressed: onRetry,
          ),
        ],
      ),
    );
  }
}

class _ReportCard extends StatelessWidget {
  const _ReportCard({
    required this.card,
    required this.period,
    required this.showExports,
  });

  final _AnalyticsCard card;
  final String period;
  final bool showExports;

  @override
  Widget build(BuildContext context) {
    final color = airmiusSemanticColor(context, card.color);
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
                child: Icon(card.icon, color: color),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      card.title,
                      style: TextStyle(
                        color: airmiusTextColor(context),
                        fontSize: 17,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(height: 5),
                    Text(
                      '$period · ${card.area}',
                      style: TextStyle(
                        color: airmiusAccentColor(context),
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ],
                ),
              ),
              StatusPill(card.trend, color: color),
            ],
          ),
          const SizedBox(height: 12),
          Text(
            card.body,
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
                label: 'Report öffnen',
                icon: card.icon,
                onPressed: () => openUiAction(
                  context,
                  title: card.title,
                  body:
                      '${card.title}: ${card.body}\n\nZeitraum: $period, Wert: ${card.value}, Trend: ${card.trend}.',
                  status: 'Report',
                  icon: card.icon,
                ),
              ),
              if (showExports)
                AirmiusButton(
                  label: 'Export',
                  icon: Icons.download_outlined,
                  secondary: true,
                  onPressed: () => openUiAction(
                    context,
                    title: '${card.title} Export',
                    body:
                        'CSV, PDF, Zeitraum, Filter, Berechtigung und Audit für ${card.title} vorbereiten.',
                    status: 'Export',
                    icon: Icons.download_outlined,
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
      title: 'Dashboard-Paritaet',
      subtitle: 'Was aus Web-Reports mobil übernommen wird.',
      children: [
        const _CheckLine(
          'KPI-Karten ersetzen breite Tabellen und komplexe Desktop-Charts.',
        ),
        const _CheckLine(
          'Mini-Charts, Trends und Reportdetails bleiben auf kleinen Screens lesbar.',
        ),
        const _CheckLine(
          'Export, Zeitraumfilter, Rollenrechte und Audit bleiben als CTAs sichtbar.',
        ),
        const _CheckLine(
          'Loading, Empty, Error und Retry bekommen eigene mobile Reportzustände.',
        ),
        const SizedBox(height: 12),
        AirmiusButton(
          label: 'Analytics-Paritaet markieren',
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

class _AnalyticsCard {
  const _AnalyticsCard({
    required this.area,
    required this.title,
    required this.value,
    required this.trend,
    required this.body,
    required this.icon,
    required this.color,
  });

  final String area;
  final String title;
  final String value;
  final String trend;
  final String body;
  final IconData icon;
  final Color color;
}
