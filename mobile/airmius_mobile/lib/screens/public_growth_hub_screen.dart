import 'package:flutter/material.dart';

import '../core/airmius_l10n.dart';
import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import '../widgets/airmius_ad_slot.dart';
import 'guest_ad_agency_screen.dart';
import 'public_interest_screen.dart';
import 'public_location_submission_screen.dart';
import 'sponsors_center_screen.dart';
import 'support_helpdesk_screen.dart';

/// Server-connected public entry point for UC-92.
class PublicGrowthHubScreen extends StatefulWidget {
  const PublicGrowthHubScreen({super.key});

  @override
  State<PublicGrowthHubScreen> createState() => _PublicGrowthHubScreenState();
}

class _PublicGrowthHubScreenState extends State<PublicGrowthHubScreen> {
  _GrowthFilter _filter = _GrowthFilter.all;

  String t(String key) => AirmiusScope.of(context).t(key);

  List<_GrowthItem> get _items => [
    _GrowthItem(
      area: _GrowthFilter.leads,
      title: t('publicGrowth.interest'),
      body: t('publicGrowth.interestBody'),
      icon: Icons.waving_hand_outlined,
      color: AirmiusColors.blue,
      open: () => _open(
        PublicInterestScreen(
          topic: t('guestPortal.interest'),
          kind: 'Public',
          icon: Icons.waving_hand_outlined,
        ),
      ),
    ),
    _GrowthItem(
      area: _GrowthFilter.leads,
      title: t('publicLocation.title'),
      body: t('publicGrowth.locationBody'),
      icon: Icons.add_location_alt_outlined,
      color: AirmiusColors.green,
      open: () => _open(const PublicLocationSubmissionScreen()),
    ),
    _GrowthItem(
      area: _GrowthFilter.leads,
      title: t('guestPortal.recommendClub'),
      body: t('guestPortal.recommendClubBody'),
      icon: Icons.apartment_outlined,
      color: AirmiusColors.orange,
      open: () => _open(
        PublicInterestScreen(
          topic: t('guestPortal.recommendClub'),
          kind: 'club_interest',
          icon: Icons.apartment_outlined,
        ),
      ),
    ),
    _GrowthItem(
      area: _GrowthFilter.ads,
      title: t('publicGrowth.agency'),
      body: t('publicGrowth.agencyBody'),
      icon: Icons.campaign_outlined,
      color: AirmiusColors.pink,
      open: () => _open(const GuestAdAgencyScreen()),
    ),
    _GrowthItem(
      area: _GrowthFilter.sponsors,
      title: t('publicGrowth.sponsors'),
      body: t('publicGrowth.sponsorsBody'),
      icon: Icons.handshake_outlined,
      color: AirmiusColors.green,
      open: () => _open(const SponsorsCenterScreen()),
    ),
  ];

  List<_GrowthItem> get _visibleItems => _filter == _GrowthFilter.all
      ? _items
      : _items.where((item) => item.area == _filter).toList();

  bool get _showsAds =>
      _filter == _GrowthFilter.all || _filter == _GrowthFilter.ads;

  void _selectFilter(_GrowthFilter value) {
    setState(() {
      _filter = value;
    });
  }

  void _open(Widget screen) {
    Navigator.of(context).push(MaterialPageRoute<void>(builder: (_) => screen));
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text(
          t('publicGrowth.title'),
          style: const TextStyle(fontWeight: FontWeight.w900),
        ),
        actions: [
          IconButton(
            tooltip: t('publicGrowth.support'),
            onPressed: () => _open(const SupportHelpdeskScreen()),
            icon: const Icon(Icons.support_agent_outlined),
          ),
        ],
      ),
      body: PageFrame(
        title: t('publicGrowth.title'),
        subtitle: t('publicGrowth.subtitle'),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            AirmiusPanel(
              gradient: true,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Eyebrow(t('publicGrowth.eyebrow')),
                  const SizedBox(height: 8),
                  Text(
                    t('publicGrowth.hero'),
                    style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                  const SizedBox(height: 8),
                  Text(t('publicGrowth.heroBody')),
                ],
              ),
            ),
            const SizedBox(height: 14),
            Semantics(
              label: t('publicGrowth.filterLabel'),
              child: Wrap(
                spacing: 8,
                runSpacing: 8,
                children: _GrowthFilter.values.map((value) {
                  return ChoiceChip(
                    key: ValueKey('public-growth-${value.name}'),
                    selected: value == _filter,
                    label: Text(t(value.labelKey)),
                    onSelected: (_) => _selectFilter(value),
                  );
                }).toList(),
              ),
            ),
            const SizedBox(height: 14),
            LayoutBuilder(
              builder: (context, constraints) {
                final columns = constraints.maxWidth >= 900
                    ? 3
                    : constraints.maxWidth >= 560
                    ? 2
                    : 1;
                final width = columns == 1
                    ? constraints.maxWidth
                    : (constraints.maxWidth - (columns - 1) * 12) / columns;
                final cards = <Widget>[
                  ..._visibleItems.map(
                    (item) => SizedBox(
                      width: width,
                      child: _GrowthCard(
                        item: item,
                        area: t(item.area.labelKey),
                      ),
                    ),
                  ),
                  if (_showsAds)
                    SizedBox(
                      width: width,
                      child: const AirmiusAdSlot(placement: 'feed'),
                    ),
                ];
                return Wrap(spacing: 12, runSpacing: 12, children: cards);
              },
            ),
          ],
        ),
      ),
    );
  }
}

enum _GrowthFilter { all, leads, ads, sponsors }

extension on _GrowthFilter {
  String get labelKey => 'publicGrowth.filter.$name';
}

class _GrowthItem {
  const _GrowthItem({
    required this.area,
    required this.title,
    required this.body,
    required this.icon,
    required this.color,
    required this.open,
  });

  final _GrowthFilter area;
  final String title;
  final String body;
  final IconData icon;
  final Color color;
  final VoidCallback open;
}

class _GrowthCard extends StatelessWidget {
  const _GrowthCard({required this.item, required this.area});

  final _GrowthItem item;
  final String area;

  @override
  Widget build(BuildContext context) => AirmiusPanel(
    onTap: item.open,
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            Icon(item.icon, color: item.color, size: 28),
            const Spacer(),
            Chip(label: Text(area), visualDensity: VisualDensity.compact),
          ],
        ),
        const SizedBox(height: 14),
        Text(
          item.title,
          style: Theme.of(
            context,
          ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w900),
        ),
        const SizedBox(height: 6),
        Text(item.body),
        const SizedBox(height: 12),
        Align(
          alignment: AlignmentDirectional.centerEnd,
          child: Icon(
            Icons.arrow_forward,
            color: Theme.of(context).colorScheme.primary,
          ),
        ),
      ],
    ),
  );
}
