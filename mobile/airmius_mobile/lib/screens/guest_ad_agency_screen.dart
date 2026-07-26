import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'sponsor_ads_operations_screen.dart';
import 'support_helpdesk_screen.dart';

class GuestAdAgencyScreen extends StatefulWidget {
  const GuestAdAgencyScreen({super.key});

  @override
  State<GuestAdAgencyScreen> createState() => _GuestAdAgencyScreenState();
}

class _GuestAdAgencyScreenState extends State<GuestAdAgencyScreen> {
  String _goal = 'Reichweite';
  bool _clubs = true;
  bool _athletes = true;
  bool _content = true;
  bool _reporting = true;

  final List<_AdPackage> _packages = const [
    _AdPackage(
      title: 'Vereinskampagne',
      body:
          'Regionale Sichtbarkeit bei Vereinen, Teams, Events und Clubprofilen.',
      status: 'Local',
      price: 'ab 199 EUR',
      icon: Icons.groups_2_outlined,
      color: AirmiusColors.blue,
    ),
    _AdPackage(
      title: 'Sponsor Paket',
      body:
          'Sponsorenflaechen, Landingpages, Sichtbarkeit und Reporting für Partner.',
      status: 'Sponsor',
      price: 'ab 499 EUR',
      icon: Icons.handshake_outlined,
      color: AirmiusColors.green,
    ),
    _AdPackage(
      title: 'Content Kampagne',
      body: 'Top-Inhalte, Blog, Feed, Social und native App-Platzierungen.',
      status: 'Content',
      price: 'ab 299 EUR',
      icon: Icons.campaign_outlined,
      color: AirmiusColors.amber,
    ),
    _AdPackage(
      title: 'Performance Paket',
      body: 'Zielgruppen, Tracking, Leads, Conversion und Admin-Auswertung.',
      status: 'Performance',
      price: 'auf Anfrage',
      icon: Icons.query_stats_outlined,
      color: AirmiusColors.red,
    ),
  ];

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AirmiusColors.bg,
      body: SafeArea(
        child: CustomScrollView(
          slivers: [
            SliverToBoxAdapter(
              child: Padding(
                padding: const EdgeInsets.fromLTRB(16, 14, 16, 18),
                child: Center(
                  child: ConstrainedBox(
                    constraints: const BoxConstraints(maxWidth: 760),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        const PageTitle(
                          title: 'Airmius Werbeagentur',
                          subtitle:
                              'Kampagnen, Sponsoren, Vereinsreichweite, Creatives, Reporting und Kontakt als mobile Public-UI.',
                        ),
                        const SizedBox(height: 16),
                        _AgencyHero(
                          onContact: () => _toast('Agenturkontakt vorbereiten'),
                        ),
                        const SizedBox(height: 16),
                        _ChoicePanel(
                          title: 'Ziel',
                          value: _goal,
                          values: const [
                            'Reichweite',
                            'Leads',
                            'Sponsoring',
                            'Content',
                            'Performance',
                          ],
                          onChanged: (value) => setState(() => _goal = value),
                        ),
                        const SizedBox(height: 16),
                        AirmiusPanel(
                          title: 'Kampagnenmodule',
                          child: Column(
                            children: [
                              _SwitchRow(
                                title: 'Vereine erreichen',
                                subtitle:
                                    'Regionale Clubs, Teams, Events und Mitgliedschaftsumfeld.',
                                value: _clubs,
                                onChanged: (value) =>
                                    setState(() => _clubs = value),
                              ),
                              _SwitchRow(
                                title: 'Athleten erreichen',
                                subtitle:
                                    'Sportprofile, Training, Badges, Feed und App-Nutzung.',
                                value: _athletes,
                                onChanged: (value) =>
                                    setState(() => _athletes = value),
                              ),
                              _SwitchRow(
                                title: 'Content einplanen',
                                subtitle:
                                    'Blog, Top-Inhalte, Feed und native Public-Seiten.',
                                value: _content,
                                onChanged: (value) =>
                                    setState(() => _content = value),
                              ),
                              _SwitchRow(
                                title: 'Reporting aktivieren',
                                subtitle:
                                    'Reichweite, Klicks, Leads, Budget und Kampagnenstatus.',
                                value: _reporting,
                                onChanged: (value) =>
                                    setState(() => _reporting = value),
                              ),
                            ],
                          ),
                        ),
                        const SizedBox(height: 16),
                        for (final package in _packages) ...[
                          _PackageCard(
                            package: package,
                            onOpen: () =>
                                _toast('${package.title}: Anfrage vorbereiten'),
                          ),
                          const SizedBox(height: 12),
                        ],
                        AirmiusPanel(
                          title: 'Kontakt & Verwaltung',
                          child: Wrap(
                            spacing: 10,
                            runSpacing: 10,
                            children: [
                              AirmiusButton(
                                label: 'Anfrage senden',
                                icon: Icons.send_outlined,
                                onPressed: () =>
                                    _toast('Werbeanfrage vorbereiten'),
                              ),
                              AirmiusButton(
                                label: 'Ads Ops',
                                icon: Icons.campaign_outlined,
                                secondary: true,
                                onPressed: () => Navigator.push(
                                  context,
                                  MaterialPageRoute(
                                    builder: (_) =>
                                        SponsorAdsOperationsScreen(),
                                  ),
                                ),
                              ),
                              AirmiusButton(
                                label: 'Support',
                                icon: Icons.support_agent_outlined,
                                secondary: true,
                                onPressed: () => Navigator.push(
                                  context,
                                  MaterialPageRoute(
                                    builder: (_) => SupportHelpdeskScreen(),
                                  ),
                                ),
                              ),
                            ],
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  void _toast(String message) {
    ScaffoldMessenger.of(
      context,
    ).showSnackBar(SnackBar(content: Text(message)));
  }
}

class _AgencyHero extends StatelessWidget {
  const _AgencyHero({required this.onContact});

  final VoidCallback onContact;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          colors: [Color(0xFF241B12), Color(0xFF0B111B)],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(22),
        border: Border.all(color: AirmiusColors.borderStrong),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              const AirmiusLogo(size: 42),
              const SizedBox(width: 12),
              const Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Eyebrow('ADS AGENCY'),
                    SizedBox(height: 4),
                    Text(
                      'Sport-Reichweite sichtbar machen',
                      style: TextStyle(
                        color: AirmiusColors.text,
                        fontSize: 22,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                  ],
                ),
              ),
              AirmiusButton(
                label: 'Kontakt',
                icon: Icons.send_outlined,
                onPressed: onContact,
              ),
            ],
          ),
          const SizedBox(height: 14),
          const Text(
            'Die Guest-Werbeagentur-Seite wird als mobile Landing-UI abgebildet: Kampagnen, Zielgruppen, Sponsoren, Creatives und Reporting.',
            style: TextStyle(
              color: AirmiusColors.muted,
              height: 1.45,
              fontWeight: FontWeight.w700,
            ),
          ),
          const SizedBox(height: 16),
          const Row(
            children: [
              Expanded(
                child: MetricCard(value: '4', label: 'Pakete'),
              ),
              SizedBox(width: 10),
              Expanded(
                child: MetricCard(value: '5', label: 'Ziele'),
              ),
              SizedBox(width: 10),
              Expanded(
                child: MetricCard(value: '1', label: 'Reporting'),
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
    required this.value,
    required this.values,
    required this.onChanged,
  });

  final String title;
  final String value;
  final List<String> values;
  final ValueChanged<String> onChanged;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      title: title,
      child: Wrap(
        spacing: 8,
        runSpacing: 8,
        children: [
          for (final item in values)
            ChoiceChip(
              label: Text(item),
              selected: value == item,
              onSelected: (_) => onChanged(item),
              selectedColor: AirmiusColors.blue.withValues(alpha: .24),
              backgroundColor: AirmiusColors.card,
              labelStyle: TextStyle(
                color: value == item ? AirmiusColors.text : AirmiusColors.muted,
                fontWeight: FontWeight.w900,
              ),
              side: BorderSide(
                color: value == item
                    ? AirmiusColors.blue
                    : AirmiusColors.border,
              ),
            ),
        ],
      ),
    );
  }
}

class _SwitchRow extends StatelessWidget {
  const _SwitchRow({
    required this.title,
    required this.subtitle,
    required this.value,
    required this.onChanged,
  });

  final String title;
  final String subtitle;
  final bool value;
  final ValueChanged<bool> onChanged;

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: AirmiusColors.input,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: AirmiusColors.border),
      ),
      child: Row(
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: const TextStyle(
                    color: AirmiusColors.text,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  subtitle,
                  style: const TextStyle(
                    color: AirmiusColors.muted,
                    fontSize: 12,
                    height: 1.35,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ],
            ),
          ),
          Switch.adaptive(
            value: value,
            onChanged: onChanged,
            activeThumbColor: AirmiusColors.blue,
          ),
        ],
      ),
    );
  }
}

class _PackageCard extends StatelessWidget {
  const _PackageCard({required this.package, required this.onOpen});

  final _AdPackage package;
  final VoidCallback onOpen;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      title: package.title,
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 48,
            height: 48,
            decoration: BoxDecoration(
              color: package.color.withValues(alpha: .18),
              borderRadius: BorderRadius.circular(16),
              border: Border.all(color: package.color.withValues(alpha: .5)),
            ),
            child: Icon(package.icon, color: package.color),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                StatusPill(package.status, color: package.color),
                const SizedBox(height: 8),
                Text(
                  package.price,
                  style: const TextStyle(
                    color: AirmiusColors.blue,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 6),
                Text(
                  package.body,
                  style: const TextStyle(
                    color: AirmiusColors.muted,
                    height: 1.45,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ],
            ),
          ),
          IconButton(
            onPressed: onOpen,
            icon: const Icon(Icons.chevron_right, color: AirmiusColors.muted),
          ),
        ],
      ),
    );
  }
}

class _AdPackage {
  const _AdPackage({
    required this.title,
    required this.body,
    required this.status,
    required this.price,
    required this.icon,
    required this.color,
  });

  final String title;
  final String body;
  final String status;
  final String price;
  final IconData icon;
  final Color color;
}
