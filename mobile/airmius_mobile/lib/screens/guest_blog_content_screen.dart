import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'public_growth_operations_screen.dart';
import 'support_helpdesk_screen.dart';

class GuestBlogContentScreen extends StatefulWidget {
  const GuestBlogContentScreen({super.key});

  @override
  State<GuestBlogContentScreen> createState() => _GuestBlogContentScreenState();
}

class _GuestBlogContentScreenState extends State<GuestBlogContentScreen> {
  String _category = 'Alle';
  bool _showFeatured = true;
  bool _showCategories = true;
  bool _showAuthors = true;
  bool _showSharing = true;

  final List<_BlogItem> _items = const [
    _BlogItem(title: 'Vereine digital organisieren', category: 'Vereine', body: 'Mitgliedsantraege, Dokumente, Rollen, Zahlungen und Kommunikation im Alltag.', status: 'Top', meta: '6 min', icon: Icons.article_outlined, color: AirmiusColors.blue),
    _BlogItem(title: 'Training und Anwesenheit', category: 'Training', body: 'Events, Trainings, Wartelisten, Check-ins und Teamkommunikation mobil planen.', status: 'Guide', meta: '8 min', icon: Icons.fitness_center_outlined, color: AirmiusColors.green),
    _BlogItem(title: 'Sponsoring im Sport', category: 'Sponsoren', body: 'Wie lokale Partner Vereine, Teams, Inhalte und Kampagnen sichtbar machen.', status: 'Partner', meta: '5 min', icon: Icons.handshake_outlined, color: AirmiusColors.amber),
    _BlogItem(title: 'Sicherheit und Datenschutz', category: 'Privacy', body: 'Einwilligungen, Minderjaehrige, Datenrechte und Moderation transparent gestalten.', status: 'Wichtig', meta: '7 min', icon: Icons.privacy_tip_outlined, color: AirmiusColors.red),
  ];

  List<_BlogItem> get _visibleItems => _items.where((item) => _category == 'Alle' || item.category == _category).toList();

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
                        const PageTitle(title: 'Airmius Blog & Inhalte', subtitle: 'Guest Blog, Blogdetail, Kategorien, Top-Inhalte, Autoren, Teilen und Public Growth.'),
                        const SizedBox(height: 16),
                        _BlogHero(onOpen: () => _toast('Blogdetail vorbereiten')),
                        const SizedBox(height: 16),
                        _ChoicePanel(title: 'Kategorie', value: _category, values: const ['Alle', 'Vereine', 'Training', 'Sponsoren', 'Privacy'], onChanged: (value) => setState(() => _category = value)),
                        const SizedBox(height: 16),
                        AirmiusPanel(
                          title: 'Content-Optionen',
                          child: Column(
                            children: [
                              _SwitchRow(title: 'Top-Inhalte anzeigen', subtitle: 'Featured Blogposts und oeffentliche Highlights sichtbar machen.', value: _showFeatured, onChanged: (value) => setState(() => _showFeatured = value)),
                              _SwitchRow(title: 'Kategorien anzeigen', subtitle: 'Blog/Categories als mobile Filter- und Uebersichts-UI abbilden.', value: _showCategories, onChanged: (value) => setState(() => _showCategories = value)),
                              _SwitchRow(title: 'Autoren anzeigen', subtitle: 'Autor, Rolle, Verein oder Plattformkontext anzeigen.', value: _showAuthors, onChanged: (value) => setState(() => _showAuthors = value)),
                              _SwitchRow(title: 'Teilen erlauben', subtitle: 'Public Sharing fuer Blog, Top-Inhalte und Landingpages vorbereiten.', value: _showSharing, onChanged: (value) => setState(() => _showSharing = value)),
                            ],
                          ),
                        ),
                        const SizedBox(height: 16),
                        for (final item in items) ...[
                          _BlogCard(item: item, onOpen: () => _toast('${item.title}: Blogdetail vorbereitet')),
                          const SizedBox(height: 12),
                        ],
                        if (items.isEmpty) const EmptyPanel('Keine Blogbeitraege fuer diese Kategorie gefunden.'),
                        const SizedBox(height: 16),
                        AirmiusPanel(
                          title: 'Public Aktionen',
                          child: Wrap(
                            spacing: 10,
                            runSpacing: 10,
                            children: [
                              AirmiusButton(label: 'Beitrag oeffnen', icon: Icons.open_in_new_outlined, onPressed: () => _toast('Blogdetail vorbereitet')),
                              AirmiusButton(label: 'Public Growth', icon: Icons.public_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => PublicGrowthOperationsScreen()))),
                              AirmiusButton(label: 'Support', icon: Icons.support_agent_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => SupportHelpdeskScreen()))),
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
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(message)));
  }
}

class _BlogHero extends StatelessWidget {
  const _BlogHero({required this.onOpen});

  final VoidCallback onOpen;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        gradient: const LinearGradient(colors: [Color(0xFF10243B), Color(0xFF0B111B)], begin: Alignment.topLeft, end: Alignment.bottomRight),
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
              const Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Eyebrow('PUBLIC CONTENT'), SizedBox(height: 4), Text('Stories, Guides und Top-Inhalte', style: TextStyle(color: AirmiusColors.text, fontSize: 22, fontWeight: FontWeight.w900))])),
              AirmiusButton(label: 'Lesen', icon: Icons.article_outlined, onPressed: onOpen),
            ],
          ),
          const SizedBox(height: 14),
          const Text('Die Guest-Blog-Webseiten werden als mobile UI abgebildet: Index, Show, Kategorien, Top-Inhalte, Autoren und Teilen.', style: TextStyle(color: AirmiusColors.muted, height: 1.45, fontWeight: FontWeight.w700)),
          const SizedBox(height: 16),
          const Row(children: [Expanded(child: MetricCard(value: '4', label: 'Posts')), SizedBox(width: 10), Expanded(child: MetricCard(value: '4', label: 'Kategorien')), SizedBox(width: 10), Expanded(child: MetricCard(value: '1', label: 'Top'))]),
        ],
      ),
    );
  }
}

class _ChoicePanel extends StatelessWidget {
  const _ChoicePanel({required this.title, required this.value, required this.values, required this.onChanged});

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
              labelStyle: TextStyle(color: value == item ? AirmiusColors.text : AirmiusColors.muted, fontWeight: FontWeight.w900),
              side: BorderSide(color: value == item ? AirmiusColors.blue : AirmiusColors.border),
            ),
        ],
      ),
    );
  }
}

class _SwitchRow extends StatelessWidget {
  const _SwitchRow({required this.title, required this.subtitle, required this.value, required this.onChanged});

  final String title;
  final String subtitle;
  final bool value;
  final ValueChanged<bool> onChanged;

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(color: AirmiusColors.input, borderRadius: BorderRadius.circular(16), border: Border.all(color: AirmiusColors.border)),
      child: Row(children: [
        Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text(title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), const SizedBox(height: 4), Text(subtitle, style: const TextStyle(color: AirmiusColors.muted, fontSize: 12, height: 1.35, fontWeight: FontWeight.w700))])),
        Switch.adaptive(value: value, onChanged: onChanged, activeColor: AirmiusColors.blue),
      ]),
    );
  }
}

class _BlogCard extends StatelessWidget {
  const _BlogCard({required this.item, required this.onOpen});

  final _BlogItem item;
  final VoidCallback onOpen;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      title: item.title,
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(width: 48, height: 48, decoration: BoxDecoration(color: item.color.withValues(alpha: .18), borderRadius: BorderRadius.circular(16), border: Border.all(color: item.color.withValues(alpha: .5))), child: Icon(item.icon, color: item.color)),
          const SizedBox(width: 12),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [StatusPill(item.status, color: item.color), const SizedBox(height: 8), Text(item.meta, style: const TextStyle(color: AirmiusColors.blue, fontWeight: FontWeight.w900)), const SizedBox(height: 6), Text(item.body, style: const TextStyle(color: AirmiusColors.muted, height: 1.45, fontWeight: FontWeight.w700))])),
          IconButton(onPressed: onOpen, icon: const Icon(Icons.chevron_right, color: AirmiusColors.muted)),
        ],
      ),
    );
  }
}

class _BlogItem {
  const _BlogItem({required this.title, required this.category, required this.body, required this.status, required this.meta, required this.icon, required this.color});

  final String title;
  final String category;
  final String body;
  final String status;
  final String meta;
  final IconData icon;
  final Color color;
}
