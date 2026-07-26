import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

class SponsorCampaignManagementSuiteScreen extends StatefulWidget {
  const SponsorCampaignManagementSuiteScreen({super.key});

  @override
  State<SponsorCampaignManagementSuiteScreen> createState() =>
      _SponsorCampaignManagementSuiteScreenState();
}

class _SponsorCampaignManagementSuiteScreenState
    extends State<SponsorCampaignManagementSuiteScreen> {
  String placement = 'Feed';
  bool requireApproval = true;
  bool showClubTargeting = true;
  bool budgetAlerts = true;
  bool creativeReview = true;

  @override
  Widget build(BuildContext context) {
    final campaigns = [
      const _CampaignRow(
        title: 'Sommerlauf Sponsor',
        status: 'Aktiv',
        budget: '350 EUR',
        body:
            'Sponsorhinweis für Event, Feed und Vereinsseite mit Budget, Laufzeit und Zielgruppe.',
        color: AirmiusColors.green,
      ),
      const _CampaignRow(
        title: 'Trikotpartner Angebot',
        status: 'Freigabe',
        budget: '900 EUR',
        body:
            'Creative, Logo, Clubbezug und Sichtbarkeit müssen durch Verein oder Plattform geprüft werden.',
        color: AirmiusColors.amber,
      ),
      const _CampaignRow(
        title: 'Marketplace Gutschein',
        status: 'Geplant',
        budget: 'Code',
        body:
            'Digitales Sponsorangebot mit Einloesecode, Gültigkeit, Tracking und Benachrichtigung.',
        color: AirmiusColors.blue,
      ),
    ];

    return PageFrame(
      title: 'Sponsoren & Kampagnen',
      subtitle: 'Ads, Budgets, Creatives und Freigaben',
      actions: const [AirmiusLogoMark(size: 34)],
      child: ListView(
        shrinkWrap: true,
        physics: const NeverScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 28),
        children: [
          AirmiusPanel(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const SectionLabel('SPONSOR OPS'),
                const SizedBox(height: 8),
                Text(
                  'Sponsoren und Vereine brauchen eine mobile Kampagnen-UI: Anzeige anlegen, Platzierung wählen, Budget steuern, Creative prüfen und Freigaben verfolgen.',
                  style: TextStyle(
                    color: airmiusTextColor(context),
                    height: 1.45,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: 16),
                GridWrap(
                  children: const [
                    Metric(value: '3', label: 'Kampagnen'),
                    Metric(value: 'Feed', label: 'Placement'),
                    Metric(value: 'Budget', label: 'Kontrolle'),
                    Metric(value: 'Review', label: 'Freigabe'),
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
                const SectionLabel('PLATZIERUNG'),
                const SizedBox(height: 12),
                SegmentedButton<String>(
                  segments: const [
                    ButtonSegment(value: 'Feed', label: Text('Feed')),
                    ButtonSegment(value: 'Verein', label: Text('Verein')),
                    ButtonSegment(value: 'Event', label: Text('Event')),
                    ButtonSegment(value: 'Shop', label: Text('Shop')),
                  ],
                  selected: {placement},
                  onSelectionChanged: (value) =>
                      setState(() => placement = value.first),
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
                _SponsorSwitch(
                  title: 'Freigabe erforderlich',
                  value: requireApproval,
                  color: Theme.of(context).colorScheme.tertiary,
                  onChanged: (value) => setState(() => requireApproval = value),
                ),
                _SponsorSwitch(
                  title: 'Club-Targeting anzeigen',
                  value: showClubTargeting,
                  color: airmiusAccentColor(context),
                  onChanged: (value) =>
                      setState(() => showClubTargeting = value),
                ),
                _SponsorSwitch(
                  title: 'Budgetwarnungen',
                  value: budgetAlerts,
                  color: Theme.of(context).colorScheme.secondary,
                  onChanged: (value) => setState(() => budgetAlerts = value),
                ),
                _SponsorSwitch(
                  title: 'Creative Review',
                  value: creativeReview,
                  color: airmiusAccentColor(context),
                  onChanged: (value) => setState(() => creativeReview = value),
                ),
              ],
            ),
          ),
          const SizedBox(height: 14),
          for (final campaign in campaigns) ...[
            _CampaignCard(campaign: campaign),
            const SizedBox(height: 12),
          ],
          AirmiusPanel(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const SectionLabel('VORSCHAU'),
                const SizedBox(height: 8),
                Text(
                  'Aktuelle Platzierung: $placement. Später verbindet die API Sponsor, Verein, Kampagne, Budget, Creative, Freigabe, Ausspielung und Reporting.',
                  style: TextStyle(
                    color: airmiusMutedColor(context),
                    height: 1.45,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                const SizedBox(height: 14),
                AirmiusButton(
                  label: 'Kampagne vorbereiten',
                  icon: Icons.campaign_outlined,
                  onPressed: () => openUiAction(
                    context,
                    title: 'Kampagne vorbereiten',
                    body:
                        'Diese UI bereitet Sponsor-Kampagnen, Budgets, Creatives, Placements, Freigaben und Reporting für die spätere Laravel-API vor.',
                    status: 'UI vorbereitet',
                    icon: Icons.campaign_outlined,
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

class _CampaignRow {
  const _CampaignRow({
    required this.title,
    required this.status,
    required this.budget,
    required this.body,
    required this.color,
  });

  final String title;
  final String status;
  final String budget;
  final String body;
  final Color color;
}

class _SponsorSwitch extends StatelessWidget {
  const _SponsorSwitch({
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
      title: Text(
        title,
        style: TextStyle(
          color: airmiusTextColor(context),
          fontWeight: FontWeight.w900,
        ),
      ),
      value: value,
      activeThumbColor: airmiusSemanticColor(context, color),
      onChanged: onChanged,
    );
  }
}

class _CampaignCard extends StatelessWidget {
  const _CampaignCard({required this.campaign});

  final _CampaignRow campaign;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              IconBadge(
                icon: Icons.campaign_outlined,
                color: airmiusSemanticColor(context, campaign.color),
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Expanded(
                          child: Text(
                            campaign.title,
                            style: TextStyle(
                              color: airmiusTextColor(context),
                              fontSize: 17,
                              fontWeight: FontWeight.w900,
                            ),
                          ),
                        ),
                        StatusPill(
                          campaign.status,
                          color: airmiusSemanticColor(context, campaign.color),
                        ),
                      ],
                    ),
                    const SizedBox(height: 4),
                    Text(
                      campaign.budget,
                      style: TextStyle(
                        color: airmiusAccentColor(context),
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(height: 8),
                    Text(
                      campaign.body,
                      style: TextStyle(
                        color: airmiusMutedColor(context),
                        height: 1.42,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 14),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              AirmiusButton(
                label: 'Creative',
                icon: Icons.image_outlined,
                onPressed: () => openUiAction(
                  context,
                  title: 'Creative prüfen',
                  body:
                      'Creatives können später Bild, Text, Link, Alt-Text, Zielgruppe und Freigabestatus enthalten.',
                  status: 'UI vorbereitet',
                  icon: Icons.image_outlined,
                ),
              ),
              AirmiusButton(
                label: 'Budget',
                icon: Icons.account_balance_wallet_outlined,
                secondary: true,
                onPressed: () => openUiAction(
                  context,
                  title: 'Budget steuern',
                  body:
                      'Budget, Laufzeit, Ausspielung, Ausgaben und Limits werden für die spätere API vorbereitet.',
                  status: 'UI vorbereitet',
                  icon: Icons.account_balance_wallet_outlined,
                ),
              ),
              AirmiusButton(
                label: 'Freigabe',
                icon: Icons.verified_user_outlined,
                secondary: true,
                onPressed: () => openUiAction(
                  context,
                  title: 'Freigabe',
                  body:
                      'Verein oder Plattform kann Kampagnen später freigeben, ablehnen, pausieren oder zurückfragen.',
                  status: 'UI vorbereitet',
                  icon: Icons.verified_user_outlined,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}
