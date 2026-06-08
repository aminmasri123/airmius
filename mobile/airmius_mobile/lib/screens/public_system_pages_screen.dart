import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'support_helpdesk_screen.dart';

class PublicSystemPagesScreen extends StatefulWidget {
  const PublicSystemPagesScreen({super.key});

  @override
  State<PublicSystemPagesScreen> createState() => _PublicSystemPagesScreenState();
}

class _PublicSystemPagesScreenState extends State<PublicSystemPagesScreen> {
  String _filter = 'Alle';
  bool _showLegal = true;
  bool _showStatus = true;
  bool _showGuestCta = true;

  final List<_SystemPage> _pages = const [
    _SystemPage(
      title: 'Welcome',
      area: 'Public',
      status: 'Landing',
      body: 'Mobile Startseite mit Airmius-Marke, Login, Registrierung, Vereine entdecken und Hauptargumenten.',
      icon: Icons.auto_awesome_outlined,
      color: Color(0xFF5BA7FF),
    ),
    _SystemPage(
      title: 'Wartungsmodus',
      area: 'Status',
      status: 'Maintenance',
      body: 'Freundliche Systemseite fuer geplante Wartung, Rueckkehrzeit, Kontakt und Status-Hinweise.',
      icon: Icons.construction_outlined,
      color: Color(0xFFF8B84E),
    ),
    _SystemPage(
      title: 'Kein Zugriff',
      area: 'Status',
      status: 'Forbidden',
      body: 'Klare Fehlerseite fuer fehlende Rechte, gesperrte Bereiche und sichere Ruecknavigation.',
      icon: Icons.lock_person_outlined,
      color: Color(0xFFFF6B6B),
    ),
    _SystemPage(
      title: 'Datenschutz',
      area: 'Legal',
      status: 'DSGVO',
      body: 'Mobile Datenschutzseite mit Datenarten, Rechten, Kontakt, Einwilligungen und Export-Hinweisen.',
      icon: Icons.privacy_tip_outlined,
      color: Color(0xFF2EE59D),
    ),
    _SystemPage(
      title: 'Nutzungsbedingungen',
      area: 'Legal',
      status: 'AGB',
      body: 'Mobile AGB-Ansicht fuer Accounts, Vereine, Zahlungen, Marketplace, Inhalte und Plattformregeln.',
      icon: Icons.description_outlined,
      color: Color(0xFFB084FF),
    ),
  ];

  List<_SystemPage> get _visiblePages {
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
                    const _HeroPanel(),
                    const SizedBox(height: 18),
                    Row(
                      children: const [
                        Expanded(child: _MetricCard(value: '5', label: 'Seiten')),
                        SizedBox(width: 10),
                        Expanded(child: _MetricCard(value: '2', label: 'Legal')),
                        SizedBox(width: 10),
                        Expanded(child: _MetricCard(value: '2', label: 'Status')),
                      ],
                    ),
                    const SizedBox(height: 18),
                    _Tabs(
                      active: _filter,
                      values: const ['Alle', 'Public', 'Status', 'Legal'],
                      onChanged: (value) => setState(() => _filter = value),
                    ),
                    const SizedBox(height: 14),
                    _VisibilityPanel(
                      showLegal: _showLegal,
                      showStatus: _showStatus,
                      showGuestCta: _showGuestCta,
                      onLegal: (value) => setState(() => _showLegal = value),
                      onStatus: (value) => setState(() => _showStatus = value),
                      onGuestCta: (value) => setState(() => _showGuestCta = value),
                    ),
                    const SizedBox(height: 14),
                    for (final page in _visiblePages.where(_isVisible)) ...[
                      _SystemPageCard(page: page),
                      const SizedBox(height: 12),
                    ],
                    _ActionPanel(
                      onPreview: () => openUiAction(
                        context,
                        title: 'Public Preview',
                        message: 'Diese Systemseiten sind als native UI vorbereitet und werden spaeter mit CMS/API-Inhalten gefuellt.',
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

  bool _isVisible(_SystemPage page) {
    if (page.area == 'Legal') return _showLegal;
    if (page.area == 'Status') return _showStatus;
    return _showGuestCta;
  }

  void _openSupport(BuildContext context) {
    Navigator.of(context).push(MaterialPageRoute(builder: (_) => SupportHelpdeskScreen()));
  }
}

class _SystemPage {
  const _SystemPage({
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
        const Expanded(
          child: Text('Airmius', style: TextStyle(color: Colors.white, fontSize: 20, fontWeight: FontWeight.w900)),
        ),
        IconButton(onPressed: onSupport, icon: const Icon(Icons.support_agent_outlined, color: Color(0xFFAFC0D8))),
      ],
    );
  }
}

class _HeroPanel extends StatelessWidget {
  const _HeroPanel();

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(24),
        border: Border.all(color: const Color(0xFF26364D)),
        gradient: const LinearGradient(
          colors: [Color(0xFF121A27), Color(0xFF0B111B)],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: const [
          Text('PUBLIC SYSTEM PAGES', style: TextStyle(color: Color(0xFF5BA7FF), fontSize: 12, fontWeight: FontWeight.w900)),
          SizedBox(height: 8),
          Text('Welcome, Recht & Status', style: TextStyle(color: Colors.white, fontSize: 28, fontWeight: FontWeight.w900)),
          SizedBox(height: 8),
          Text(
            'Native Mobile-UI fuer oeffentliche Einstiegsseiten, Wartung, Forbidden, Datenschutz und Nutzungsbedingungen.',
            style: TextStyle(color: Color(0xFFAFC0D8), height: 1.45, fontWeight: FontWeight.w600),
          ),
        ],
      ),
    );
  }
}

class _MetricCard extends StatelessWidget {
  const _MetricCard({required this.value, required this.label});

  final String value;
  final String label;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: const Color(0xFF101722),
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: const Color(0xFF26364D)),
      ),
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
  const _Tabs({required this.active, required this.values, required this.onChanged});

  final String active;
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
          final value = values[index];
          final selected = value == active;
          return ChoiceChip(
            label: Text(value),
            selected: selected,
            onSelected: (_) => onChanged(value),
            labelStyle: TextStyle(color: selected ? Colors.white : const Color(0xFFAFC0D8), fontWeight: FontWeight.w900),
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
    required this.showLegal,
    required this.showStatus,
    required this.showGuestCta,
    required this.onLegal,
    required this.onStatus,
    required this.onGuestCta,
  });

  final bool showLegal;
  final bool showStatus;
  final bool showGuestCta;
  final ValueChanged<bool> onLegal;
  final ValueChanged<bool> onStatus;
  final ValueChanged<bool> onGuestCta;

  @override
  Widget build(BuildContext context) {
    return _Panel(
      title: 'Mobile Sichtbarkeit',
      child: Column(
        children: [
          _SwitchRow(label: 'Rechtliche Seiten anzeigen', value: showLegal, onChanged: onLegal),
          _SwitchRow(label: 'Statusseiten anzeigen', value: showStatus, onChanged: onStatus),
          _SwitchRow(label: 'Guest CTA anzeigen', value: showGuestCta, onChanged: onGuestCta),
        ],
      ),
    );
  }
}

class _SystemPageCard extends StatelessWidget {
  const _SystemPageCard({required this.page});

  final _SystemPage page;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: const Color(0xFF101722),
        borderRadius: BorderRadius.circular(24),
        border: Border.all(color: const Color(0xFF26364D)),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 54,
            height: 54,
            decoration: BoxDecoration(
              color: page.color.withOpacity(.14),
              borderRadius: BorderRadius.circular(16),
              border: Border.all(color: page.color.withOpacity(.45)),
            ),
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
  const _ActionPanel({required this.onPreview, required this.onSupport});

  final VoidCallback onPreview;
  final VoidCallback onSupport;

  @override
  Widget build(BuildContext context) {
    return _Panel(
      title: 'Schnellaktionen',
      child: Column(
        children: [
          _ActionButton(icon: Icons.visibility_outlined, label: 'Systemseiten Vorschau', onTap: onPreview),
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
      decoration: BoxDecoration(
        color: const Color(0xFF0D131D),
        borderRadius: BorderRadius.circular(22),
        border: Border.all(color: const Color(0xFF26364D)),
      ),
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
        decoration: BoxDecoration(
          color: const Color(0xFF111A27),
          borderRadius: BorderRadius.circular(16),
          border: Border.all(color: const Color(0xFF26364D)),
        ),
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
      decoration: BoxDecoration(
        color: color.withOpacity(.12),
        borderRadius: BorderRadius.circular(999),
        border: Border.all(color: color.withOpacity(.55)),
      ),
      child: Text(label, style: TextStyle(color: color, fontSize: 12, fontWeight: FontWeight.w900)),
    );
  }
}
