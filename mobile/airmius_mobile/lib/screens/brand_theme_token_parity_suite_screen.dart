import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

class BrandThemeTokenParitySuiteScreen extends StatefulWidget {
  const BrandThemeTokenParitySuiteScreen({super.key});

  @override
  State<BrandThemeTokenParitySuiteScreen> createState() =>
      _BrandThemeTokenParitySuiteScreenState();
}

class _BrandThemeTokenParitySuiteScreenState
    extends State<BrandThemeTokenParitySuiteScreen> {
  String _surface = 'Card';
  String _density = 'Mobile';
  bool _showLogo = true;
  bool _webLikeHeader = true;
  bool _strongContrast = true;

  static const _surfaces = ['Header', 'Card', 'Form', 'Modal', 'Bottom Nav'];
  static const _densities = ['Compact', 'Mobile', 'Comfort'];

  static const _tokens = <_DesignToken>[
    _DesignToken(
      title: 'Airmius Header',
      source: 'Mobile Web-App Header',
      body:
          'Dunkler Header, Logo, Suche, Notifications, Profilchip und klare Trennung zum Inhalt.',
      status: 'Shell',
      icon: Icons.web_asset_outlined,
      primary: 'Header prüfen',
      secondary: 'Suche',
      color: AirmiusColors.blue,
    ),
    _DesignToken(
      title: 'Logo und Brand Mark',
      source: 'assets/images/airmius-logo-light.png',
      body:
          'Logo wird in Header, Hero, Auth, Splash, Settings und Release-Gates konsistent verwendet.',
      status: 'Brand',
      icon: Icons.auto_awesome_outlined,
      primary: 'Logo',
      secondary: 'Assets',
      color: AirmiusColors.amber,
    ),
    _DesignToken(
      title: 'Panels und Karten',
      source: 'AirmiusPanel / MetricCard / StatusPill',
      body:
          'Cards haben dunkle Flaechen, feine Border, runde Ecken, Glow-Akzente und klare Inhaltsstruktur.',
      status: 'Surface',
      icon: Icons.dashboard_customize_outlined,
      primary: 'Karten',
      secondary: 'Spacing',
      color: AirmiusColors.green,
    ),
    _DesignToken(
      title: 'Formular-Stil',
      source: 'InputDecorationTheme',
      body:
          'Eingaben bleiben dunkel, kontrastreich, gut beruehrbar und mit Airmius-Border/Focus-Zustand.',
      status: 'Input',
      icon: Icons.keyboard_outlined,
      primary: 'Form',
      secondary: 'Focus',
      color: AirmiusColors.blue,
    ),
    _DesignToken(
      title: 'Buttons und CTAs',
      source: 'AirmiusButton',
      body:
          'Primaer, sekundar und Danger-Aktionen unterscheiden sich klar und bleiben auf Mobile gut bedienbar.',
      status: 'Action',
      icon: Icons.touch_app_outlined,
      primary: 'Buttons',
      secondary: 'Danger',
      color: AirmiusColors.green,
    ),
    _DesignToken(
      title: 'Status und Pills',
      source: 'StatusPill',
      body:
          'Status wie Gesendet, Offen, Aktiv, Fehler, Review und Erfolgreich wirken in allen Modulen gleich.',
      status: 'Status',
      icon: Icons.label_outlined,
      primary: 'Pills',
      secondary: 'Farben',
      color: AirmiusColors.amber,
    ),
    _DesignToken(
      title: 'Overlay-Hintergrund',
      source: 'Modal / Sheet / Drawer',
      body:
          'Modals, Sheets und Drawer nutzen dunkles Fullscreen-Dimming, lesbare Karten und Scrollbereiche.',
      status: 'Overlay',
      icon: Icons.layers_outlined,
      primary: 'Overlay',
      secondary: 'Dimming',
      color: AirmiusColors.blue,
    ),
    _DesignToken(
      title: 'Mobile Navigation',
      source: 'Bottom Navigation / Drawer',
      body:
          'Bottom-Bar, Drawer, Modulgruppen und aktive Bereiche orientieren sich an der mobilen Web-App.',
      status: 'Nav',
      icon: Icons.menu_open_outlined,
      primary: 'Navigation',
      secondary: 'Active',
      color: AirmiusColors.green,
    ),
  ];

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      appBar: AppBar(
        backgroundColor:
            Theme.of(context).appBarTheme.backgroundColor ??
            airmiusSurfaceColor(context),
        surfaceTintColor: Colors.transparent,
        title: _showLogo
            ? const AirmiusLogo(compact: true)
            : const Text(
                'Airmius',
                style: TextStyle(fontWeight: FontWeight.w900),
              ),
      ),
      body: SafeArea(
        child: PageFrame(
          title: 'Brand Theme Token Parity',
          subtitle:
              'Airmius-Designsystem, Logo, Farben und mobile Web-App-Atmosphaere.',
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              _Hero(
                surface: _surface,
                density: _density,
                showLogo: _showLogo,
                strongContrast: _strongContrast,
              ),
              const SizedBox(height: 16),
              _ChoicePanel(
                title: 'UI-Oberflaeche',
                items: _surfaces,
                active: _surface,
                color: airmiusAccentColor(context),
                onChanged: (value) => setState(() => _surface = value),
              ),
              const SizedBox(height: 16),
              _ChoicePanel(
                title: 'Mobile Dichte',
                items: _densities,
                active: _density,
                color: Theme.of(context).colorScheme.secondary,
                onChanged: (value) => setState(() => _density = value),
              ),
              const SizedBox(height: 16),
              _RulesPanel(
                showLogo: _showLogo,
                webLikeHeader: _webLikeHeader,
                strongContrast: _strongContrast,
                onLogo: (value) => setState(() => _showLogo = value),
                onHeader: (value) => setState(() => _webLikeHeader = value),
                onContrast: (value) => setState(() => _strongContrast = value),
              ),
              const SizedBox(height: 16),
              _VisualPreview(
                surface: _surface,
                density: _density,
                webLikeHeader: _webLikeHeader,
              ),
              const SizedBox(height: 16),
              for (final token in _tokens) ...[
                _DesignTokenCard(token: token),
                const SizedBox(height: 12),
              ],
              const SizedBox(height: 4),
              _Checklist(
                onOpen: () => openUiAction(
                  context,
                  title: 'Brand Theme Parity',
                  body:
                      'Logo, Header, Farben, Panels, Cards, Inputs, Buttons, Status-Pills, Overlays und Bottom Navigation sind als Airmius-Designsystem für Flutter vorbereitet.',
                  status: 'Brand UI',
                  icon: Icons.palette_outlined,
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
    required this.surface,
    required this.density,
    required this.showLogo,
    required this.strongContrast,
  });

  final String surface;
  final String density;
  final bool showLogo;
  final bool strongContrast;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      gradient: true,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Eyebrow('BRAND & THEME'),
          const SizedBox(height: 8),
          Text(
            'Flutter soll sich wie Airmius anfuehlen, nicht nur Airmius heissen.',
            style: TextStyle(
              color: airmiusTextColor(context),
              fontSize: 24,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 8),
          Text(
            'Diese Suite sammelt die visuellen Regeln der mobilen Web-App: Logo, dunkle Flaechen, blaue/gruene Akzente, Panels, Status, Formulare, Overlays und Navigation.',
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
              _Metric(value: surface, label: 'Surface'),
              _Metric(value: density, label: 'Dichte'),
              _Metric(value: showLogo ? 'Logo' : 'Text', label: 'Brand'),
              _Metric(
                value: strongContrast ? 'Stark' : 'Soft',
                label: 'Kontrast',
              ),
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
    required this.showLogo,
    required this.webLikeHeader,
    required this.strongContrast,
    required this.onLogo,
    required this.onHeader,
    required this.onContrast,
  });

  final bool showLogo;
  final bool webLikeHeader;
  final bool strongContrast;
  final ValueChanged<bool> onLogo;
  final ValueChanged<bool> onHeader;
  final ValueChanged<bool> onContrast;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      title: 'Design-Regeln',
      subtitle: 'Diese Flags halten Flutter nah an der mobilen Web-App.',
      children: [
        _SwitchLine(
          title: 'Airmius-Logo anzeigen',
          value: showLogo,
          onChanged: onLogo,
        ),
        _SwitchLine(
          title: 'Header wie Web-App gestalten',
          value: webLikeHeader,
          onChanged: onHeader,
        ),
        _SwitchLine(
          title: 'Kontrast stark halten',
          value: strongContrast,
          onChanged: onContrast,
        ),
      ],
    );
  }
}

class _VisualPreview extends StatelessWidget {
  const _VisualPreview({
    required this.surface,
    required this.density,
    required this.webLikeHeader,
  });

  final String surface;
  final String density;
  final bool webLikeHeader;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      borderColor: airmiusAccentColor(context),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          if (webLikeHeader)
            Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: airmiusSurfaceColor(context),
                borderRadius: BorderRadius.circular(16),
                border: Border.all(color: airmiusBorderColor(context)),
              ),
              child: Row(
                children: [
                  AirmiusLogo(compact: true),
                  Spacer(),
                  Icon(
                    Icons.search_outlined,
                    color: airmiusMutedColor(context),
                  ),
                  SizedBox(width: 12),
                  Icon(
                    Icons.notifications_none_outlined,
                    color: airmiusMutedColor(context),
                  ),
                ],
              ),
            ),
          if (webLikeHeader) const SizedBox(height: 14),
          Row(
            children: const [
              Expanded(
                child: MetricCard(value: 'ZBB', label: 'Workspace'),
              ),
              SizedBox(width: 10),
              Expanded(
                child: MetricCard(value: 'UI', label: 'Airmius'),
              ),
            ],
          ),
          const SizedBox(height: 14),
          Text(
            surface,
            style: TextStyle(
              color: airmiusTextColor(context),
              fontSize: 18,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 6),
          Text(
            'Preview für $density-Dichte mit Web-App-Farben, runden Cards, Status-Pills und klarer Button-Hierarchie.',
            style: TextStyle(
              color: airmiusMutedColor(context),
              height: 1.4,
              fontWeight: FontWeight.w700,
            ),
          ),
          const SizedBox(height: 14),
          Wrap(
            spacing: 10,
            runSpacing: 10,
            children: [
              AirmiusButton(
                label: 'Design prüfen',
                icon: Icons.palette_outlined,
                onPressed: () => openUiAction(
                  context,
                  title: 'Design Preview',
                  body:
                      'Surface $surface, Dichte $density, Header $webLikeHeader und Airmius-Theme als visuelle Paritaet prüfen.',
                  status: 'Design',
                  icon: Icons.palette_outlined,
                ),
              ),
              StatusPill(
                'Native UI',
                color: Theme.of(context).colorScheme.secondary,
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _DesignTokenCard extends StatelessWidget {
  const _DesignTokenCard({required this.token});

  final _DesignToken token;

  @override
  Widget build(BuildContext context) {
    final color = airmiusSemanticColor(context, token.color);
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
                child: Icon(token.icon, color: color),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      token.title,
                      style: TextStyle(
                        color: airmiusTextColor(context),
                        fontSize: 17,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(height: 5),
                    Text(
                      token.source,
                      style: TextStyle(
                        color: airmiusAccentColor(context),
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ],
                ),
              ),
              StatusPill(token.status, color: color),
            ],
          ),
          const SizedBox(height: 12),
          Text(
            token.body,
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
                label: token.primary,
                icon: token.icon,
                onPressed: () => openUiAction(
                  context,
                  title: token.primary,
                  body:
                      '${token.title}: ${token.body}\n\nQuelle: ${token.source}',
                  status: token.status,
                  icon: token.icon,
                ),
              ),
              AirmiusButton(
                label: token.secondary,
                icon: Icons.tune_outlined,
                secondary: true,
                onPressed: () => openUiAction(
                  context,
                  title: token.secondary,
                  body:
                      'Farbe, Border, Radius, Spacing, Kontrast, Icon und mobile Dichte für ${token.title}.',
                  status: 'Token',
                  icon: Icons.tune_outlined,
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
      title: 'Brand-Paritaet',
      subtitle: 'Was visuell immer Airmius bleiben soll.',
      children: [
        const _CheckLine(
          'Logo, Header, Suche, Profilchip und Bottom Navigation bleiben im Airmius-Stil.',
        ),
        const _CheckLine(
          'Panels, Cards, Inputs, Buttons und Status-Pills nutzen gemeinsame Tokens.',
        ),
        const _CheckLine(
          'Dunkle Flaechen, blaue/gruene Akzente und klare Borders bleiben konsistent.',
        ),
        const _CheckLine(
          'Overlays, Formulare, Listen und Dashboards wirken wie mobile Web-App, nicht wie fremde App.',
        ),
        const SizedBox(height: 12),
        AirmiusButton(
          label: 'Brand-Paritaet markieren',
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

class _DesignToken {
  const _DesignToken({
    required this.title,
    required this.source,
    required this.body,
    required this.status,
    required this.icon,
    required this.primary,
    required this.secondary,
    required this.color,
  });

  final String title;
  final String source;
  final String body;
  final String status;
  final IconData icon;
  final String primary;
  final String secondary;
  final Color color;
}
