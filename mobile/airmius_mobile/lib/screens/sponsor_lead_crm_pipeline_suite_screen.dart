import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

class SponsorLeadCrmPipelineSuiteScreen extends StatelessWidget {
  const SponsorLeadCrmPipelineSuiteScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final leads = [
      _LeadItem('Autohaus Becker', 'Angebot offen', 'Bandenwerbung, Trikotsponsor, 2.400 EUR/Jahr', AirmiusColors.amber, Icons.directions_car_outlined),
      _LeadItem('Physio Aktiv', 'Warm', 'Gesundheitspartner, Kursrabatt, Eventstand', AirmiusColors.green, Icons.health_and_safety_outlined),
      _LeadItem('Sparkasse Regional', 'Freigabe', 'Jugendfoerderung, Vereinsprojekt, Vorstandspruefung', AirmiusColors.blue, Icons.account_balance_outlined),
      _LeadItem('Sporthaus Weber', 'Follow-up', 'Materialrabatt, Gutschein, Marketplace-Verknuepfung', AirmiusColors.pink, Icons.storefront_outlined),
    ];

    final pipeline = [
      _PipelineStep('Lead', 'Kontakt erfassen, Quelle, Branche, Ansprechpartner und Notiz speichern.'),
      _PipelineStep('Angebot', 'Paket, Laufzeit, Preis, Vorteile, Dateien und Genehmigung vorbereiten.'),
      _PipelineStep('Freigabe', 'Vereinsadmin, Vorstand oder Plattform prueft Inhalt, Rechte und Sichtbarkeit.'),
      _PipelineStep('Aktiv', 'Kampagne, Rechnung, Placement, Reporting und Renewal-Erinnerung starten.'),
    ];

    return Scaffold(
      backgroundColor: AirmiusColors.bg,
      appBar: AppBar(
        backgroundColor: AirmiusColors.header,
        surfaceTintColor: Colors.transparent,
        title: const Text('Sponsor CRM', style: TextStyle(fontWeight: FontWeight.w900)),
      ),
      body: PageFrame(
        title: 'Sponsor CRM',
        subtitle: 'Leads, Angebote, Sponsorpakete, Freigaben, Kampagnen, Rechnungen und Renewal als mobile Vereins-UI.',
        trailing: const StatusPill('Commerce', color: AirmiusColors.green),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            AirmiusPanel(
              gradient: true,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: const [
                  Eyebrow('SPONSOR PIPELINE'),
                  SizedBox(height: 10),
                  Text('Vereine brauchen Einnahmen, nicht nur Verwaltung.', style: TextStyle(color: AirmiusColors.text, fontSize: 24, fontWeight: FontWeight.w900, height: 1.08)),
                  SizedBox(height: 8),
                  Text('Diese mobile Suite bringt Sponsor-Leads, Angebote, Freigaben, Kampagnen, Dateien, Rechnungen und Reporting in einen klaren Prozess fuer Vereinsadmins.', style: TextStyle(color: AirmiusColors.muted, height: 1.42)),
                ],
              ),
            ),
            const SizedBox(height: 14),
            Row(
              children: const [
                Expanded(child: MetricCard(value: '18', label: 'Leads')),
                SizedBox(width: 10),
                Expanded(child: MetricCard(value: '6', label: 'Angebote')),
                SizedBox(width: 10),
                Expanded(child: MetricCard(value: '12k', label: 'Pipeline')),
              ],
            ),
            const SizedBox(height: 14),
            for (final lead in leads) ...[
              AirmiusPanel(
                borderColor: lead.color.withValues(alpha: .44),
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Container(
                      width: 50,
                      height: 50,
                      decoration: BoxDecoration(
                        color: lead.color.withValues(alpha: .14),
                        borderRadius: BorderRadius.circular(16),
                        border: Border.all(color: lead.color.withValues(alpha: .45)),
                      ),
                      child: Icon(lead.icon, color: lead.color),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(lead.name, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900, fontSize: 16)),
                          const SizedBox(height: 5),
                          Text(lead.body, style: const TextStyle(color: AirmiusColors.muted, height: 1.35)),
                          const SizedBox(height: 10),
                          Wrap(spacing: 8, runSpacing: 8, children: [StatusPill(lead.status, color: lead.color), const StatusPill('CRM')]),
                        ],
                      ),
                    ),
                    const Icon(Icons.chevron_right, color: AirmiusColors.muted),
                  ],
                ),
              ),
              const SizedBox(height: 12),
            ],
            AirmiusPanel(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Eyebrow('PIPELINE'),
                  const SizedBox(height: 12),
                  for (final step in pipeline) ...[
                    _PipelineRow(step: step),
                    if (step != pipeline.last) const SizedBox(height: 10),
                  ],
                ],
              ),
            ),
            const SizedBox(height: 14),
            AirmiusPanel(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: const [
                  Eyebrow('VERKNUEPFTE MODULE'),
                  SizedBox(height: 10),
                  _ModuleLink(title: 'Dateimanager', body: 'Angebote, Logos, Vertraege und Kampagnenassets speichern.'),
                  _ModuleLink(title: 'Rechnungen', body: 'Sponsorvertrag in Rechnung, Zahlungsstatus und Mahnung ueberfuehren.'),
                  _ModuleLink(title: 'Ads & Public', body: 'Freigegebene Partner auf Vereinsprofil, Feed, Events oder Ads ausspielen.'),
                  _ModuleLink(title: 'Audit', body: 'Freigaben, Preiswechsel, Laufzeiten und Kontaktverlauf nachvollziehbar halten.'),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _LeadItem {
  const _LeadItem(this.name, this.status, this.body, this.color, this.icon);

  final String name;
  final String status;
  final String body;
  final Color color;
  final IconData icon;
}

class _PipelineStep {
  const _PipelineStep(this.title, this.body);

  final String title;
  final String body;
}

class _PipelineRow extends StatelessWidget {
  const _PipelineRow({required this.step});

  final _PipelineStep step;

  @override
  Widget build(BuildContext context) => Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 34,
            height: 34,
            alignment: Alignment.center,
            decoration: BoxDecoration(color: AirmiusColors.green.withValues(alpha: .16), borderRadius: BorderRadius.circular(12), border: Border.all(color: AirmiusColors.green.withValues(alpha: .42))),
            child: const Icon(Icons.trending_up_outlined, color: AirmiusColors.green, size: 19),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(step.title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
                const SizedBox(height: 4),
                Text(step.body, style: const TextStyle(color: AirmiusColors.muted, height: 1.35)),
              ],
            ),
          ),
        ],
      );
}

class _ModuleLink extends StatelessWidget {
  const _ModuleLink({required this.title, required this.body});

  final String title;
  final String body;

  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.only(bottom: 11),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Icon(Icons.link_outlined, color: AirmiusColors.blue),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
                  const SizedBox(height: 3),
                  Text(body, style: const TextStyle(color: AirmiusColors.muted, height: 1.35)),
                ],
              ),
            ),
          ],
        ),
      );
}
