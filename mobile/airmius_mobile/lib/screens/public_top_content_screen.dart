import 'package:flutter/material.dart';
import 'public_growth_operations_screen.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'public_detail_screen.dart';
import 'public_growth_operations_screen.dart';

class PublicTopContentScreen extends StatefulWidget {
  const PublicTopContentScreen({super.key});

  @override
  State<PublicTopContentScreen> createState() => _PublicTopContentScreenState();
}

class _PublicTopContentScreenState extends State<PublicTopContentScreen> {
  String _filter = 'Alle';

  @override
  Widget build(BuildContext context) {
    final items = _items.where((item) => _filter == 'Alle' || item.type == _filter).toList();
    return Scaffold(
        floatingActionButton: FloatingActionButton.extended(backgroundColor: const Color(0xFF1D5FA8), foregroundColor: Colors.white, icon: const Icon(Icons.campaign_outlined), label: const Text('Funnel Ops', style: TextStyle(fontWeight: FontWeight.w900)), onPressed: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => PublicGrowthOperationsScreen(initialTab: 'Leads')))),
        
      appBar: AppBar(backgroundColor: AirmiusColors.header, surfaceTintColor: Colors.transparent, title: const Text('Top-Inhalte', style: TextStyle(fontWeight: FontWeight.w900))),
      body: PageFrame(
        title: 'Top-Inhalte',
        subtitle: 'Kuratierte Public-Inhalte aus Blog, Kursen, Marketplace, Vereinen und Sponsoring',
        trailing: const StatusPill('Public'),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            AirmiusPanel(
              gradient: true,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: const [
                  Eyebrow('Entdecken'),
                  SizedBox(height: 8),
                  Text('Die mobile App bildet die öffentliche Web-App auch für Besucher ab: Inhalte finden, Vertrauen aufbauen und danach registrieren oder Interesse senden.', style: TextStyle(color: AirmiusColors.muted, height: 1.35)),
                ],
              ),
            ),
            const SizedBox(height: 14),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: [
                for (final filter in const ['Alle', 'Blog', 'Kurs', 'Verein', 'Shop', 'Sponsor'])
                  ChoiceChip(
                    selected: _filter == filter,
                    label: Text(filter),
                    onSelected: (_) => setState(() => _filter = filter),
                    selectedColor: AirmiusColors.blue.withValues(alpha: 0.22),
                    backgroundColor: AirmiusColors.cardSoft,
                    side: BorderSide(color: _filter == filter ? AirmiusColors.blue : AirmiusColors.border),
                    labelStyle: TextStyle(color: _filter == filter ? AirmiusColors.blue : AirmiusColors.muted, fontWeight: FontWeight.w900),
                  ),
              ],
            ),
            const SizedBox(height: 14),
            for (final item in items) ...[
              _TopContentCard(item: item),
              const SizedBox(height: 12),
            ],
          ],
        ),
      ),
    );
  }
}

class _TopContentCard extends StatelessWidget {
  const _TopContentCard({required this.item});

  final _TopContentItem item;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => PublicDetailScreen(title: item.title, body: item.body, icon: item.icon, kind: item.type))),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(width: 54, height: 54, decoration: BoxDecoration(color: AirmiusColors.blue.withValues(alpha: 0.14), borderRadius: BorderRadius.circular(16), border: Border.all(color: AirmiusColors.blue.withValues(alpha: 0.35))), child: Icon(item.icon, color: AirmiusColors.blue)),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(children: [Expanded(child: Text(item.title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900))), StatusPill(item.type)]),
                const SizedBox(height: 5),
                Text(item.body, style: const TextStyle(color: AirmiusColors.muted, height: 1.35)),
                const SizedBox(height: 9),
                Wrap(spacing: 8, runSpacing: 8, children: [StatusPill(item.meta, color: AirmiusColors.green), const StatusPill('Public')]),
              ],
            ),
          ),
          const Icon(Icons.chevron_right, color: AirmiusColors.muted),
        ],
      ),
    );
  }
}

class _TopContentItem {
  const _TopContentItem({required this.title, required this.body, required this.type, required this.meta, required this.icon});

  final String title;
  final String body;
  final String type;
  final String meta;
  final IconData icon;
}

const _items = [
  _TopContentItem(title: 'Digitale Mitgliedschaftsanfrage', body: 'Wie Vereine Anfragen, Formulare, Dokumente und Zahlrhythmen mobil verwalten.', type: 'Blog', meta: 'Beliebt', icon: Icons.article_outlined),
  _TopContentItem(title: 'Trainer-Onboarding', body: 'Kurs mit Zertifikat, Lektionen und Fortschritt für Trainer und Vereinsadmins.', type: 'Kurs', meta: 'Zertifikat', icon: Icons.school_outlined),
  _TopContentItem(title: 'Airmius Running Club', body: 'Öffentliches Vereinsprofil mit Teams, sichtbaren Beiträgen und Beitritt.', type: 'Verein', meta: 'Verifiziert', icon: Icons.groups_outlined),
  _TopContentItem(title: 'Starterpaket Verein', body: 'Marketplace-Angebot mit Varianten, Anbieterprofil und Gast-Checkout.', type: 'Shop', meta: 'Neu', icon: Icons.storefront_outlined),
  _TopContentItem(title: 'Sponsor Sichtbarkeit', body: 'Sponsoring-Kachel mit Kampagne, Kontaktanfrage und Reporting.', type: 'Sponsor', meta: 'Aktiv', icon: Icons.handshake_outlined),
];

