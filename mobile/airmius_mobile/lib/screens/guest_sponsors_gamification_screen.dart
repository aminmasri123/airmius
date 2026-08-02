import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'guest_ad_agency_screen.dart';
import 'sponsor_ads_operations_screen.dart';
import 'support_helpdesk_screen.dart';

class GuestSponsorsGamificationScreen extends StatefulWidget {
  const GuestSponsorsGamificationScreen({super.key});

  @override
  State<GuestSponsorsGamificationScreen> createState() =>
      _GuestSponsorsGamificationScreenState();
}

class _GuestSponsorsGamificationScreenState
    extends State<GuestSponsorsGamificationScreen> {
  String _tab = 'Alle';
  bool _showSponsors = true;
  bool _showBadges = true;
  bool _showChallenges = true;
  bool _showRewards = true;

  final List<_SponsorGameItem> _items = const [
    _SponsorGameItem(
      title: 'Sponsor Sichtbarkeit',
      area: 'Sponsoren',
      body:
          'Public Sponsor Landing mit Partnerprofil, Vereinsreichweite und Kampagnen-CTA.',
      status: 'Sponsor',
      meta: 'Partner',
      icon: Icons.handshake_outlined,
      color: AirmiusColors.blue,
    ),
    _SponsorGameItem(
      title: 'Badge Challenge',
      area: 'Gamification',
      body:
          'Badges, Punkte, Regeln und Fortschritt für Sport- und Vereinsaktionen.',
      status: 'Badge',
      meta: '250 Punkte',
      icon: Icons.military_tech_outlined,
      color: AirmiusColors.green,
    ),
    _SponsorGameItem(
      title: 'Vereins-Challenge',
      area: 'Gamification',
      body:
          'Teamziele, Training, Events, Rangliste und Belohnungen als Public-Teaser.',
      status: 'Challenge',
      meta: '7 Tage',
      icon: Icons.emoji_events_outlined,
      color: AirmiusColors.amber,
    ),
    _SponsorGameItem(
      title: 'Reward Partner',
      area: 'Sponsoren',
      body:
          'Sponsor-Rewards, Gutscheine, Marketplace-Verknüpfung und Reporting.',
      status: 'Reward',
      meta: 'Local',
      icon: Icons.card_giftcard_outlined,
      color: AirmiusColors.red,
    ),
  ];

  List<_SponsorGameItem> get _visibleItems =>
      _items.where((item) => _tab == 'Alle' || item.area == _tab).toList();

  @override
  Widget build(BuildContext context) {
    final items = _visibleItems;

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
                          title: 'Sponsoren & Gamification',
                          subtitle:
                              'Guest Sponsors, Guest Gamification, Badges, Challenges, Rewards, Partner und Reporting.',
                        ),
                        const SizedBox(height: 16),
                        _SponsorGameHero(
                          onContact: () => _toast(
                            'Sponsor-/Gamification-Anfrage vorbereitet',
                          ),
                        ),
                        const SizedBox(height: 16),
                        _ChoicePanel(
                          title: 'Bereich',
                          value: _tab,
                          values: const ['Alle', 'Sponsoren', 'Gamification'],
                          onChanged: (value) => setState(() => _tab = value),
                        ),
                        const SizedBox(height: 16),
                        AirmiusPanel(
                          title: 'Landing-Optionen',
                          child: Column(
                            children: [
                              _SwitchRow(
                                title: 'Sponsoren anzeigen',
                                subtitle:
                                    'Guest Sponsors mit Paketen, Partnerprofilen und Kampagnen.',
                                value: _showSponsors,
                                onChanged: (value) =>
                                    setState(() => _showSponsors = value),
                              ),
                              _SwitchRow(
                                title: 'Badges anzeigen',
                                subtitle:
                                    'Gamification-Badges, Fortschritt und öffentliche Motivation.',
                                value: _showBadges,
                                onChanged: (value) =>
                                    setState(() => _showBadges = value),
                              ),
                              _SwitchRow(
                                title: 'Challenges anzeigen',
                                subtitle:
                                    'Sport- und Vereins-Challenges als Public Growth Flow.',
                                value: _showChallenges,
                                onChanged: (value) =>
                                    setState(() => _showChallenges = value),
                              ),
                              _SwitchRow(
                                title: 'Rewards anzeigen',
                                subtitle:
                                    'Belohnungen, Gutscheine, Marketplace und Sponsorvorteile.',
                                value: _showRewards,
                                onChanged: (value) =>
                                    setState(() => _showRewards = value),
                              ),
                            ],
                          ),
                        ),
                        const SizedBox(height: 16),
                        for (final item in items) ...[
                          _SponsorGameCard(
                            item: item,
                            onOpen: () =>
                                _toast('${item.title}: Detail vorbereitet'),
                          ),
                          const SizedBox(height: 12),
                        ],
                        if (items.isEmpty)
                          const EmptyPanel(
                            'Keine Einträge für diesen Bereich gefunden.',
                          ),
                        const SizedBox(height: 16),
                        AirmiusPanel(
                          title: 'Aktionen',
                          child: Wrap(
                            spacing: 10,
                            runSpacing: 10,
                            children: [
                              AirmiusButton(
                                label: 'Partner werden',
                                icon: Icons.handshake_outlined,
                                onPressed: () => Navigator.push(
                                  context,
                                  MaterialPageRoute(
                                    builder: (_) => GuestAdAgencyScreen(),
                                  ),
                                ),
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

class _SponsorGameHero extends StatelessWidget {
  const _SponsorGameHero({required this.onContact});

  final VoidCallback onContact;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          colors: [Color(0xFF1E2412), Color(0xFF0B111B)],
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
                    Eyebrow('SPONSORS & GAME'),
                    SizedBox(height: 4),
                    Text(
                      'Partner, Badges und Rewards',
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
            'Guest/Sponsors und Guest/Gamification werden als mobile Landing-UI abgebildet: Sponsoren, Badges, Challenges, Rewards und Kampagnen.',
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
                child: MetricCard(value: '2', label: 'Sponsor'),
              ),
              SizedBox(width: 10),
              Expanded(
                child: MetricCard(value: '2', label: 'Game'),
              ),
              SizedBox(width: 10),
              Expanded(
                child: MetricCard(value: '1', label: 'Reward'),
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

class _SponsorGameCard extends StatelessWidget {
  const _SponsorGameCard({required this.item, required this.onOpen});

  final _SponsorGameItem item;
  final VoidCallback onOpen;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      title: item.title,
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 48,
            height: 48,
            decoration: BoxDecoration(
              color: item.color.withValues(alpha: .18),
              borderRadius: BorderRadius.circular(16),
              border: Border.all(color: item.color.withValues(alpha: .5)),
            ),
            child: Icon(item.icon, color: item.color),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                StatusPill(item.status, color: item.color),
                const SizedBox(height: 8),
                Text(
                  item.meta,
                  style: const TextStyle(
                    color: AirmiusColors.blue,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 6),
                Text(
                  item.body,
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

class _SponsorGameItem {
  const _SponsorGameItem({
    required this.title,
    required this.area,
    required this.body,
    required this.status,
    required this.meta,
    required this.icon,
    required this.color,
  });

  final String title;
  final String area;
  final String body;
  final String status;
  final String meta;
  final IconData icon;
  final Color color;
}
