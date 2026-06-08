import 'package:flutter/material.dart';
import 'sponsor_ads_operations_screen.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'sponsor_detail_screen.dart';
import 'sponsor_ads_operations_screen.dart';

class SponsorsCenterScreen extends StatefulWidget {
  const SponsorsCenterScreen({super.key});

  @override
  State<SponsorsCenterScreen> createState() => _SponsorsCenterScreenState();
}

class _SponsorsCenterScreenState extends State<SponsorsCenterScreen> {
  String _filter = 'Aktiv';

  @override
  Widget build(BuildContext context) {
    return Scaffold(
        floatingActionButton: FloatingActionButton.extended(backgroundColor: const Color(0xFF16855E), foregroundColor: Colors.white, icon: const Icon(Icons.handshake_outlined), label: const Text('Sponsor Ops', style: TextStyle(fontWeight: FontWeight.w900)), onPressed: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => SponsorAdsOperationsScreen(initialTab: 'Sponsoren')))),
        
      appBar: AppBar(backgroundColor: AirmiusColors.header, surfaceTintColor: Colors.transparent, title: const Text('Sponsoren', style: TextStyle(fontWeight: FontWeight.w900))),
      body: PageFrame(
        title: 'Sponsoren',
        subtitle: 'Sponsorprofile, Pakete, Kampagnen, Kontaktanfragen und Sichtbarkeit',
        trailing: const StatusPill('Partner'),
        child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          AirmiusPanel(gradient: true, child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            const Eyebrow('Sponsoring'),
            const SizedBox(height: 8),
            const Text('Vereine und Partner professionell verbinden.', style: TextStyle(color: AirmiusColors.text, fontSize: 23, fontWeight: FontWeight.w900)),
            const SizedBox(height: 8),
            const Text('Sponsorprofile, Pakete, Kampagnen, Reichweite, Laufzeiten und Kontaktanfragen werden als native Mobile-UI vorbereitet.', style: TextStyle(color: AirmiusColors.muted, height: 1.4)),
            const SizedBox(height: 14),
            Wrap(spacing: 8, runSpacing: 8, children: ['Aktiv', 'Anfragen', 'Pakete', 'Kampagnen'].map((item) => ChoiceChip(
              selected: _filter == item,
              label: Text(item),
              onSelected: (_) => setState(() => _filter = item),
              selectedColor: AirmiusColors.blue.withValues(alpha: 0.22),
              backgroundColor: AirmiusColors.cardSoft,
              side: BorderSide(color: _filter == item ? AirmiusColors.blue : AirmiusColors.border),
              labelStyle: TextStyle(color: _filter == item ? AirmiusColors.blue : AirmiusColors.muted, fontWeight: FontWeight.w900),
            )).toList()),
          ])),
          const SizedBox(height: 14),
          Row(children: const [Expanded(child: MetricCard(value: '4', label: 'Sponsoren')), SizedBox(width: 10), Expanded(child: MetricCard(value: '2', label: 'Kampagnen')), SizedBox(width: 10), Expanded(child: MetricCard(value: '1', label: 'Anfrage'))]),
          const SizedBox(height: 14),
          _SponsorLine(
            icon: Icons.handshake_outlined,
            title: 'Airmius Partnerpaket',
            body: 'Logo, Profil, Link, Vereinssichtbarkeit und Laufzeit aktiv.',
            status: 'Aktiv',
            color: AirmiusColors.green,
            onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => SponsorDetailScreen(title: 'Airmius Partnerpaket', status: 'Aktiv'))),
          ),
          const SizedBox(height: 12),
          _SponsorLine(
            icon: Icons.campaign_outlined,
            title: 'Sommerlauf Kampagne',
            body: 'Banner, Zielgruppe, Reporting und Kontaktanfragen vorbereitet.',
            status: 'Kampagne',
            color: AirmiusColors.blue,
            onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => SponsorDetailScreen(title: 'Sommerlauf Kampagne', status: 'Kampagne'))),
          ),
          const SizedBox(height: 12),
          _SponsorLine(
            icon: Icons.contact_mail_outlined,
            title: 'Neue Kontaktanfrage',
            body: 'Sponsor moechte ZBB unterstuetzen und Paketdetails erhalten.',
            status: 'Offen',
            color: AirmiusColors.amber,
            onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => SponsorDetailScreen(title: 'Neue Kontaktanfrage', status: 'Offen'))),
          ),
          const SizedBox(height: 14),
          AirmiusPanel(child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            const Eyebrow('Sponsor-Aktionen'),
            const SizedBox(height: 12),
            Wrap(spacing: 10, runSpacing: 10, children: [
              AirmiusButton(label: 'Sponsor anlegen', icon: Icons.add_business_outlined, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => SponsorDetailScreen(title: 'Sponsor anlegen', status: 'Entwurf')))),
              AirmiusButton(label: 'Paket erstellen', icon: Icons.inventory_2_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => SponsorDetailScreen(title: 'Paket erstellen', status: 'Paket')))),
              AirmiusButton(label: 'Anfrage beantworten', icon: Icons.reply_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => SponsorDetailScreen(title: 'Anfrage beantworten', status: 'Offen')))),
            ]),
          ])),
        ]),
      ),
    );
  }
}

class _SponsorLine extends StatelessWidget {
  const _SponsorLine({required this.icon, required this.title, required this.body, required this.status, required this.color, required this.onTap});

  final IconData icon;
  final String title;
  final String body;
  final String status;
  final Color color;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(onTap: onTap, borderColor: color.withValues(alpha: 0.45), child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Icon(icon, color: color, size: 28),
      const SizedBox(width: 12),
      Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text(title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), const SizedBox(height: 4), Text(body, style: const TextStyle(color: AirmiusColors.muted, height: 1.35)), const SizedBox(height: 10), StatusPill(status, color: color)])),
      const Icon(Icons.chevron_right, color: AirmiusColors.muted),
    ]));
  }
}

