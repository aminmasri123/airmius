import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'support_helpdesk_screen.dart';

class PublicGrowthGuestPagesScreen extends StatefulWidget {
  const PublicGrowthGuestPagesScreen({super.key});

  @override
  State<PublicGrowthGuestPagesScreen> createState() => _PublicGrowthGuestPagesScreenState();
}

class _PublicGrowthGuestPagesScreenState extends State<PublicGrowthGuestPagesScreen> {
  String _filter = 'Alle';
  bool _showDiscovery = true;
  bool _showBusiness = true;
  bool _showContent = true;

  final List<_GuestPage> _pages = const [
    _GuestPage(
      title: 'Vereine entdecken',
      area: 'Discovery',
      status: 'Public',
      body: 'Öffentliche Vereinsliste mit Suche, Ort, Mitgliederzahl, Profilzugang und Beitrittsmöglichkeit.',
      icon: Icons.groups_3_outlined,
      color: Color(0xFF5BA7FF),
    ),
    _GuestPage(
      title: 'Pricing',
      area: 'Business',
      status: 'Plaene',
      body: 'Mobile Preisübersicht für Vereine, Anbieter und Nutzer mit Leistungsumfang und CTA.',
      icon: Icons.sell_outlined,
      color: Color(0xFF2EE59D),
    ),
    _GuestPage(
      title: 'Jobs',
      area: 'Business',
      status: 'Karriere',
      body: 'Job- und Karrierebereich mit Rollen, Mission, Bewerbungs-CTA und Airmius-Kultur.',
      icon: Icons.work_outline,
      color: Color(0xFFF8B84E),
    ),
    _GuestPage(
      title: 'Werbeagentur',
      area: 'Business',
      status: 'Ads',
      body: 'Öffentliche Landingpage für Werbekunden, Sponsoring, Kampagnen und Kontaktanfrage.',
      icon: Icons.campaign_outlined,
      color: Color(0xFFFF6B6B),
    ),
    _GuestPage(
      title: 'Sponsoren',
      area: 'Business',
      status: 'Partner',
      body: 'Sponsorenseite mit Partnerkarten, Benefits, Kampagnenbeispielen und Vereinsbezug.',
      icon: Icons.handshake_outlined,
      color: Color(0xFFB084FF),
    ),
    _GuestPage(
      title: 'Top-Inhalte',
      area: 'Content',
      status: 'Feed',
      body: 'Öffentliche Highlights aus Blog, Vereinen, Events, Gamification und Community.',
      icon: Icons.auto_awesome_outlined,
      color: Color(0xFF5BA7FF),
    ),
    _GuestPage(
      title: 'E-Learning & Zertifikate',
      area: 'Content',
      status: 'Lernen',
      body: 'Gastseiten für Kurse, Kursdetails und Zertifikatsprüfung als mobile Lernstrecke.',
      icon: Icons.school_outlined,
      color: Color(0xFF2EE59D),
    ),
    _GuestPage(
      title: 'Gamification',
      area: 'Content',
      status: 'Badges',
      body: 'Öffentliche Gamification-Erklaerung mit Badges, Punkten, Challenges und Vereinsmotivation.',
      icon: Icons.emoji_events_outlined,
      color: Color(0xFFF8B84E),
    ),
  ];

  List<_GuestPage> get _visiblePages {
    if (_filter == 'Alle') return _pages;
    return _pages.where((page) => page.area == _filter).toList();
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
                    _Header(onSupport: () => _openSupport(context)),
                    const SizedBox(height: 18),
                    const _Hero(),
                    const SizedBox(height: 18),
                    Row(
                      children: const [
                        Expanded(child: _Metric(value: '8', label: 'Guest-Seiten')),
                        SizedBox(width: 10),
                        Expanded(child: _Metric(value: '4', label: 'Business')),
                        SizedBox(width: 10),
                        Expanded(child: _Metric(value: '3', label: 'Content')),
                      ],
                    ),
                    const SizedBox(height: 18),
                    _Tabs(
                      value: _filter,
                      values: const ['Alle', 'Discovery', 'Business', 'Content'],
                      onChanged: (value) => setState(() => _filter = value),
                    ),
                    const SizedBox(height: 14),
                    _VisibilityPanel(
                      showDiscovery: _showDiscovery,
                      showBusiness: _showBusiness,
                      showContent: _showContent,
                      onDiscovery: (value) => setState(() => _showDiscovery = value),
                      onBusiness: (value) => setState(() => _showBusiness = value),
                      onContent: (value) => setState(() => _showContent = value),
                    ),
                    const SizedBox(height: 14),
                    for (final page in _visiblePages.where(_isVisible)) ...[
                      _GuestPageCard(page: page),
                      const SizedBox(height: 12),
                    ],
                    _ActionPanel(
                      onExplore: () => openUiAction(
                        context,
                        title: 'Guest-Seite öffnen',
                        message: 'Die mobile Guest-UI ist vorbereitet; Inhalte werden später per API/CMS geladen.',
                      ),
                      onContact: () => openUiAction(
                        context,
                        title: 'Kontaktanfrage',
                        message: 'Hier wird später das Kontaktformular für Jobs, Ads, Sponsoren und Vereine angebunden.',
                      ),
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

  bool _isVisible(_GuestPage page) {
    if (page.area == 'Discovery') return _showDiscovery;
    if (page.area == 'Business') return _showBusiness;
    return _showContent;
  }

  void _openSupport(BuildContext context) {
    Navigator.of(context).push(MaterialPageRoute(builder: (_) => SupportHelpdeskScreen()));
  }
}

class _GuestPage {
  const _GuestPage({
    required this.title,
    required this.area,
    required this.status,
    required this.body,
    required this.icon,
    required this.color,
  });

  final String title;
  final String area;
  final String status;
  final String body;
  final IconData icon;
  final Color color;
}

class _Header extends StatelessWidget {
  const _Header({required this.onSupport});

  final VoidCallback onSupport;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        const AirmiusLogo(markOnly: true, size: 34),
        const SizedBox(width: 10),
        const Expanded(child: Text('Airmius', style: TextStyle(color: Colors.white, fontSize: 20, fontWeight: FontWeight.w900))),
        IconButton(onPressed: onSupport, icon: const Icon(Icons.support_agent_outlined, color: Color(0xFFAFC0D8))),
      ],
    );
  }
}

class _Hero extends StatelessWidget {
  const _Hero();

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(24),
        border: Border.all(color: const Color(0xFF26364D)),
        gradient: const LinearGradient(colors: [Color(0xFF121A27), Color(0xFF0B111B)], begin: Alignment.topLeft, end: Alignment.bottomRight),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: const [
          Text('PUBLIC GROWTH', style: TextStyle(color: Color(0xFF5BA7FF), fontSize: 12, fontWeight: FontWeight.w900)),
          SizedBox(height: 8),
          Text('Guest-Seiten', style: TextStyle(color: Colors.white, fontSize: 28, fontWeight: FontWeight.w900)),
          SizedBox(height: 8),
          Text(
            'Native Mobile-UI für Vereine, Pricing, Jobs, Werbeagentur, Sponsoren, Top-Inhalte, E-Learning und Gamification.',
            style: TextStyle(color: Color(0xFFAFC0D8), height: 1.45, fontWeight: FontWeight.w600),
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
}

class _Tabs extends StatelessWidget {
  const _Tabs({required this.value, required this.values, required this.onChanged});

  final String value;
  final List<String> values;
  final ValueChanged<String> onChanged;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
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
}

class _VisibilityPanel extends StatelessWidget {
  const _VisibilityPanel({
    required this.showDiscovery,
    required this.showBusiness,
    required this.showContent,
    required this.onDiscovery,
    required this.onBusiness,
    required this.onContent,
  });

  final bool showDiscovery;
  final bool showBusiness;
  final bool showContent;
  final ValueChanged<bool> onDiscovery;
  final ValueChanged<bool> onBusiness;
  final ValueChanged<bool> onContent;

  @override
  Widget build(BuildContext context) {
    return _Panel(
      title: 'Guest-Bereiche',
      child: Column(
        children: [
          _SwitchRow(label: 'Discovery anzeigen', value: showDiscovery, onChanged: onDiscovery),
          _SwitchRow(label: 'Business anzeigen', value: showBusiness, onChanged: onBusiness),
          _SwitchRow(label: 'Content anzeigen', value: showContent, onChanged: onContent),
        ],
      ),
    );
  }
}

class _GuestPageCard extends StatelessWidget {
  const _GuestPageCard({required this.page});

  final _GuestPage page;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(color: const Color(0xFF101722), borderRadius: BorderRadius.circular(24), border: Border.all(color: const Color(0xFF26364D))),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 54,
            height: 54,
            decoration: BoxDecoration(color: page.color.withOpacity(.14), borderRadius: BorderRadius.circular(16), border: Border.all(color: page.color.withOpacity(.45))),
            child: Icon(page.icon, color: page.color, size: 28),
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Expanded(child: Text(page.title, style: const TextStyle(color: Colors.white, fontSize: 18, fontWeight: FontWeight.w900))),
                    _Pill(label: page.status, color: page.color),
                  ],
                ),
                const SizedBox(height: 8),
                Text(page.body, style: const TextStyle(color: Color(0xFFDDE7F5), height: 1.45, fontWeight: FontWeight.w600)),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _ActionPanel extends StatelessWidget {
  const _ActionPanel({required this.onExplore, required this.onContact, required this.onSupport});

  final VoidCallback onExplore;
  final VoidCallback onContact;
  final VoidCallback onSupport;

  @override
  Widget build(BuildContext context) {
    return _Panel(
      title: 'Schnellaktionen',
      child: Column(
        children: [
          _ActionButton(icon: Icons.travel_explore_outlined, label: 'Guest-Bereich öffnen', onTap: onExplore),
          const SizedBox(height: 10),
          _ActionButton(icon: Icons.mail_outline, label: 'Kontaktanfrage starten', onTap: onContact),
          const SizedBox(height: 10),
          _ActionButton(icon: Icons.support_agent_outlined, label: 'Support kontaktieren', onTap: onSupport),
        ],
      ),
    );
  }
}

class _Panel extends StatelessWidget {
  const _Panel({required this.title, required this.child});

  final String title;
  final Widget child;

  @override
  Widget build(BuildContext context) {
    return Container(
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
}

class _SwitchRow extends StatelessWidget {
  const _SwitchRow({required this.label, required this.value, required this.onChanged});

  final String label;
  final bool value;
  final ValueChanged<bool> onChanged;

  @override
  Widget build(BuildContext context) {
    return SwitchListTile.adaptive(
      value: value,
      onChanged: onChanged,
      dense: true,
      contentPadding: EdgeInsets.zero,
      activeThumbColor: const Color(0xFF5BA7FF),
      title: Text(label, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w800)),
    );
  }
}

class _ActionButton extends StatelessWidget {
  const _ActionButton({required this.icon, required this.label, required this.onTap});

  final IconData icon;
  final String label;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
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
}

class _Pill extends StatelessWidget {
  const _Pill({required this.label, required this.color});

  final String label;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
      decoration: BoxDecoration(color: color.withOpacity(.12), borderRadius: BorderRadius.circular(999), border: Border.all(color: color.withOpacity(.55))),
      child: Text(label, style: TextStyle(color: color, fontSize: 12, fontWeight: FontWeight.w900)),
    );
  }
}
