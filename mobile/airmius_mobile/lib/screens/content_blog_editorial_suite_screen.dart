import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'support_helpdesk_screen.dart';

class ContentBlogEditorialSuiteScreen extends StatefulWidget {
  const ContentBlogEditorialSuiteScreen({super.key});

  @override
  State<ContentBlogEditorialSuiteScreen> createState() => _ContentBlogEditorialSuiteScreenState();
}

class _ContentBlogEditorialSuiteScreenState extends State<ContentBlogEditorialSuiteScreen> {
  String _filter = 'Alle';
  bool _showEditorial = true;
  bool _showPublic = true;
  bool _showModeration = true;

  final List<_SuiteItem> _items = const [
    _SuiteItem('Blog Dashboard', 'Editorial', 'Index', 'Redaktionelle Blogliste mit Status, Autor, Kategorie, Sichtbarkeit und Vorschau.', Icons.article_outlined, Color(0xFF5BA7FF)),
    _SuiteItem('Blog Kategorien', 'Editorial', 'Taxonomie', 'Kategorien, Slugs, Farben, Reihenfolge und oeffentliche Sichtbarkeit als mobile Admin-UI.', Icons.category_outlined, Color(0xFF2EE59D)),
    _SuiteItem('Blog Detail', 'Editorial', 'Show', 'Beitragsdetail mit Titel, Hero, Inhalt, Tags, Freigabe, Bearbeiten und Teilen.', Icons.chrome_reader_mode_outlined, Color(0xFFF8B84E)),
    _SuiteItem('Public Blog', 'Public', 'Gast', 'Oeffentliche Blogliste mit Suche, Themen, Top-Beitraegen und App-tauglichen Karten.', Icons.public_outlined, Color(0xFFB084FF)),
    _SuiteItem('Public Blog Show', 'Public', 'Lesen', 'Mobile Leseansicht fuer Gastartikel mit Autor, Datum, Abschnitten und CTA.', Icons.menu_book_outlined, Color(0xFF5BA7FF)),
    _SuiteItem('Top-Inhalte', 'Public', 'Highlights', 'Kuratiertes Content-Schaufenster fuer Vereine, Events, Lerninhalte, Blog und Community.', Icons.auto_awesome_outlined, Color(0xFF2EE59D)),
    _SuiteItem('Content Moderation', 'Moderation', 'Review', 'Melden, pruefen, freigeben, ausblenden und dokumentieren von oeffentlichen Inhalten.', Icons.fact_check_outlined, Color(0xFFFF6B6B)),
  ];

  List<_SuiteItem> get _visible {
    if (_filter == 'Alle') return _items;
    return _items.where((item) => item.area == _filter).toList();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFF070B12),
      body: SafeArea(
        child: CustomScrollView(
          slivers: [
            SliverPadding(
              padding: const EdgeInsets.fromLTRB(16, 12, 16, 24),
              sliver: SliverToBoxAdapter(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    _TopBar(onSupport: () => _openSupport(context)),
                    const SizedBox(height: 18),
                    const _Hero(
                      eyebrow: 'CONTENT SUITE',
                      title: 'Blog & Editorial',
                      subtitle: 'Native Mobile-UI fuer Blog-Index, Kategorien, Detailseiten, Public Blog, Top-Inhalte und Moderation.',
                    ),
                    const SizedBox(height: 18),
                    Row(
                      children: const [
                        Expanded(child: _Metric(value: '7', label: 'Views')),
                        SizedBox(width: 10),
                        Expanded(child: _Metric(value: '3', label: 'Editorial')),
                        SizedBox(width: 10),
                        Expanded(child: _Metric(value: '3', label: 'Public')),
                      ],
                    ),
                    const SizedBox(height: 18),
                    _Tabs(value: _filter, values: const ['Alle', 'Editorial', 'Public', 'Moderation'], onChanged: (value) => setState(() => _filter = value)),
                    const SizedBox(height: 14),
                    _SwitchPanel(
                      title: 'Content-Bereiche',
                      rows: [
                        _SwitchRowData('Editorial anzeigen', _showEditorial, (value) => setState(() => _showEditorial = value)),
                        _SwitchRowData('Public anzeigen', _showPublic, (value) => setState(() => _showPublic = value)),
                        _SwitchRowData('Moderation anzeigen', _showModeration, (value) => setState(() => _showModeration = value)),
                      ],
                    ),
                    const SizedBox(height: 14),
                    for (final item in _visible.where(_isVisible)) ...[
                      _SuiteCard(item: item),
                      const SizedBox(height: 12),
                    ],
                    _ActionPanel(
                      primaryIcon: Icons.edit_outlined,
                      primaryLabel: 'Beitrag vorbereiten',
                      secondaryIcon: Icons.visibility_outlined,
                      secondaryLabel: 'Public Preview',
                      onPrimary: () => openUiAction(context, title: 'Blog Beitrag', body: 'Die mobile Editorial-UI ist vorbereitet; echte Inhalte kommen spaeter ueber API/CMS.', status: 'UI bereit', icon: Icons.info_outline),
                      onSecondary: () => openUiAction(context, title: 'Public Preview', body: 'Oeffentliche Blog- und Top-Inhalte-Ansichten sind als App-UI vorhanden.', status: 'UI bereit', icon: Icons.info_outline),
                      onSupport: () => _openSupport(context),
                    ),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  bool _isVisible(_SuiteItem item) {
    if (item.area == 'Editorial') return _showEditorial;
    if (item.area == 'Public') return _showPublic;
    return _showModeration;
  }

  void _openSupport(BuildContext context) {
    Navigator.of(context).push(MaterialPageRoute(builder: (_) => SupportHelpdeskScreen()));
  }
}

class _SuiteItem {
  const _SuiteItem(this.title, this.area, this.status, this.body, this.icon, this.color);

  final String title;
  final String area;
  final String status;
  final String body;
  final IconData icon;
  final Color color;
}

class _SwitchRowData {
  const _SwitchRowData(this.label, this.value, this.onChanged);

  final String label;
  final bool value;
  final ValueChanged<bool> onChanged;
}

class _TopBar extends StatelessWidget {
  const _TopBar({required this.onSupport});

  final VoidCallback onSupport;

  @override
  Widget build(BuildContext context) => Row(
        children: [
          const AirmiusLogo(markOnly: true, size: 34),
          const SizedBox(width: 10),
          const Expanded(child: Text('Airmius', style: TextStyle(color: Colors.white, fontSize: 20, fontWeight: FontWeight.w900))),
          IconButton(onPressed: onSupport, icon: const Icon(Icons.support_agent_outlined, color: Color(0xFFAFC0D8))),
        ],
      );
}

class _Hero extends StatelessWidget {
  const _Hero({required this.eyebrow, required this.title, required this.subtitle});

  final String eyebrow;
  final String title;
  final String subtitle;

  @override
  Widget build(BuildContext context) => Container(
        padding: const EdgeInsets.all(18),
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(24),
          border: Border.all(color: const Color(0xFF26364D)),
          gradient: const LinearGradient(colors: [Color(0xFF121A27), Color(0xFF0B111B)], begin: Alignment.topLeft, end: Alignment.bottomRight),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(eyebrow, style: const TextStyle(color: Color(0xFF5BA7FF), fontSize: 12, fontWeight: FontWeight.w900)),
            const SizedBox(height: 8),
            Text(title, style: const TextStyle(color: Colors.white, fontSize: 28, fontWeight: FontWeight.w900)),
            const SizedBox(height: 8),
            Text(subtitle, style: const TextStyle(color: Color(0xFFAFC0D8), height: 1.45, fontWeight: FontWeight.w600)),
          ],
        ),
      );
}

class _Metric extends StatelessWidget {
  const _Metric({required this.value, required this.label});

  final String value;
  final String label;

  @override
  Widget build(BuildContext context) => Container(
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(color: const Color(0xFF101722), borderRadius: BorderRadius.circular(18), border: Border.all(color: const Color(0xFF26364D))),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(value, style: const TextStyle(color: Colors.white, fontSize: 24, fontWeight: FontWeight.w900)),
            const SizedBox(height: 4),
            Text(label, style: const TextStyle(color: Color(0xFFAFC0D8), fontWeight: FontWeight.w700)),
          ],
        ),
      );
}

class _Tabs extends StatelessWidget {
  const _Tabs({required this.value, required this.values, required this.onChanged});

  final String value;
  final List<String> values;
  final ValueChanged<String> onChanged;

  @override
  Widget build(BuildContext context) => SizedBox(
        height: 42,
        child: ListView.separated(
          scrollDirection: Axis.horizontal,
          itemCount: values.length,
          separatorBuilder: (_, __) => const SizedBox(width: 8),
          itemBuilder: (context, index) {
            final item = values[index];
            final active = item == value;
            return ChoiceChip(
              label: Text(item),
              selected: active,
              onSelected: (_) => onChanged(item),
              labelStyle: TextStyle(color: active ? Colors.white : const Color(0xFFAFC0D8), fontWeight: FontWeight.w900),
              selectedColor: const Color(0xFF173D68),
              backgroundColor: const Color(0xFF101722),
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(999), side: const BorderSide(color: Color(0xFF26364D))),
            );
          },
        ),
      );
}

class _SwitchPanel extends StatelessWidget {
  const _SwitchPanel({required this.title, required this.rows});

  final String title;
  final List<_SwitchRowData> rows;

  @override
  Widget build(BuildContext context) => _Panel(
        title: title,
        child: Column(
          children: rows
              .map((row) => SwitchListTile.adaptive(
                    value: row.value,
                    onChanged: row.onChanged,
                    dense: true,
                    contentPadding: EdgeInsets.zero,
                    activeThumbColor: const Color(0xFF5BA7FF),
                    title: Text(row.label, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w800)),
                  ))
              .toList(),
        ),
      );
}

class _SuiteCard extends StatelessWidget {
  const _SuiteCard({required this.item});

  final _SuiteItem item;

  @override
  Widget build(BuildContext context) => Container(
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(color: const Color(0xFF101722), borderRadius: BorderRadius.circular(24), border: Border.all(color: const Color(0xFF26364D))),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Container(
              width: 54,
              height: 54,
              decoration: BoxDecoration(color: item.color.withOpacity(.14), borderRadius: BorderRadius.circular(16), border: Border.all(color: item.color.withOpacity(.45))),
              child: Icon(item.icon, color: item.color, size: 28),
            ),
            const SizedBox(width: 14),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Expanded(child: Text(item.title, style: const TextStyle(color: Colors.white, fontSize: 18, fontWeight: FontWeight.w900))),
                      _Pill(label: item.status, color: item.color),
                    ],
                  ),
                  const SizedBox(height: 8),
                  Text(item.body, style: const TextStyle(color: Color(0xFFDDE7F5), height: 1.45, fontWeight: FontWeight.w600)),
                ],
              ),
            ),
          ],
        ),
      );
}

class _ActionPanel extends StatelessWidget {
  const _ActionPanel({
    required this.primaryIcon,
    required this.primaryLabel,
    required this.secondaryIcon,
    required this.secondaryLabel,
    required this.onPrimary,
    required this.onSecondary,
    required this.onSupport,
  });

  final IconData primaryIcon;
  final String primaryLabel;
  final IconData secondaryIcon;
  final String secondaryLabel;
  final VoidCallback onPrimary;
  final VoidCallback onSecondary;
  final VoidCallback onSupport;

  @override
  Widget build(BuildContext context) => _Panel(
        title: 'Schnellaktionen',
        child: Column(
          children: [
            _ActionButton(icon: primaryIcon, label: primaryLabel, onTap: onPrimary),
            const SizedBox(height: 10),
            _ActionButton(icon: secondaryIcon, label: secondaryLabel, onTap: onSecondary),
            const SizedBox(height: 10),
            _ActionButton(icon: Icons.support_agent_outlined, label: 'Support kontaktieren', onTap: onSupport),
          ],
        ),
      );
}

class _Panel extends StatelessWidget {
  const _Panel({required this.title, required this.child});

  final String title;
  final Widget child;

  @override
  Widget build(BuildContext context) => Container(
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(color: const Color(0xFF0D131D), borderRadius: BorderRadius.circular(22), border: Border.all(color: const Color(0xFF26364D))),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(title, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w900)),
            const SizedBox(height: 12),
            child,
          ],
        ),
      );
}

class _ActionButton extends StatelessWidget {
  const _ActionButton({required this.icon, required this.label, required this.onTap});

  final IconData icon;
  final String label;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(16),
        child: Container(
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(color: const Color(0xFF111A27), borderRadius: BorderRadius.circular(16), border: Border.all(color: const Color(0xFF26364D))),
          child: Row(
            children: [
              Icon(icon, color: AirmiusColors.blue),
              const SizedBox(width: 12),
              Expanded(child: Text(label, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w900))),
              const Icon(Icons.chevron_right, color: Color(0xFFAFC0D8)),
            ],
          ),
        ),
      );
}

class _Pill extends StatelessWidget {
  const _Pill({required this.label, required this.color});

  final String label;
  final Color color;

  @override
  Widget build(BuildContext context) => Container(
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
        decoration: BoxDecoration(color: color.withOpacity(.12), borderRadius: BorderRadius.circular(999), border: Border.all(color: color.withOpacity(.55))),
        child: Text(label, style: TextStyle(color: color, fontSize: 12, fontWeight: FontWeight.w900)),
      );
}
