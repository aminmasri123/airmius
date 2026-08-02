import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'privacy_consent_center_screen.dart';
import 'report_moderation_center_screen.dart';
import 'support_helpdesk_screen.dart';

class MaturityMediaGuidelinesScreen extends StatefulWidget {
  const MaturityMediaGuidelinesScreen({super.key});

  @override
  State<MaturityMediaGuidelinesScreen> createState() =>
      _MaturityMediaGuidelinesScreenState();
}

class _MaturityMediaGuidelinesScreenState
    extends State<MaturityMediaGuidelinesScreen> {
  String _filter = 'Alle';
  bool _showAgeRules = true;
  bool _showMediaConsent = true;
  bool _showModeration = true;
  bool _showClubGuides = true;

  final List<_GuidelineItem> _items = const [
    _GuidelineItem(
      title: 'Minderjährige & Guardian',
      area: 'Jugend',
      status: 'Pflicht',
      meta: 'Consent',
      description:
          'Altersstufen, Erziehungsberechtigte, Einwilligungen und geschützte Funktionen für Jugendliche.',
      icon: Icons.family_restroom_outlined,
      color: Color(0xFF5BA7FF),
      details: ['Alter prüfen', 'Guardian-Daten', 'Freigaben', 'Sichtbarkeit'],
    ),
    _GuidelineItem(
      title: 'Medienfreigaben',
      area: 'Medien',
      status: 'Optional',
      meta: 'Fotos/Videos',
      description:
          'Regeln für Bilder, Videos, Profilmedien, Vereinsbeiträge und widerrufbare Medienzustimmungen.',
      icon: Icons.photo_camera_back_outlined,
      color: Color(0xFF2EE59D),
      details: ['Foto erlaubt', 'Video erlaubt', 'Widerruf', 'Dokumente'],
    ),
    _GuidelineItem(
      title: 'Community-Regeln',
      area: 'Community',
      status: 'Regeln',
      meta: 'Safety',
      description:
          'Verhaltenskodex, Meldefunktionen, Moderationswege und klare Hinweise für sichere Vereinsräume.',
      icon: Icons.shield_outlined,
      color: Color(0xFFF8B84E),
      details: ['Kodex', 'Melden', 'Moderation', 'Sanktionen'],
    ),
    _GuidelineItem(
      title: 'Reifegrad & Vereinscheck',
      area: 'Maturity',
      status: 'Audit',
      meta: 'Club',
      description:
          'Mobile Übersicht, ob ein Verein rechtlich, organisatorisch und medial startklar ist.',
      icon: Icons.verified_user_outlined,
      color: Color(0xFFFF6B6B),
      details: ['Profil', 'Datenschutz', 'Beiträge', 'Dokumente'],
    ),
  ];

  List<_GuidelineItem> get _visibleItems {
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
                    _TopBar(onHelp: () => _openSupport(context)),
                    const SizedBox(height: 18),
                    const _PageIntro(
                      eyebrow: 'SAFETY & GUIDELINES',
                      title: 'Maturity & Medien',
                      subtitle:
                          'Mobile Regeln für Alter, Medienfreigaben, Datenschutz, Moderation und Vereinsreife.',
                    ),
                    const SizedBox(height: 18),
                    Row(
                      children: const [
                        Expanded(
                          child: _MetricTile(
                            value: '4',
                            label: 'Regelbereiche',
                          ),
                        ),
                        SizedBox(width: 10),
                        Expanded(
                          child: _MetricTile(value: '8', label: 'Freigaben'),
                        ),
                        SizedBox(width: 10),
                        Expanded(
                          child: _MetricTile(value: '3', label: 'Audits'),
                        ),
                      ],
                    ),
                    const SizedBox(height: 18),
                    _FilterBar(
                      value: _filter,
                      values: const [
                        'Alle',
                        'Jugend',
                        'Medien',
                        'Community',
                        'Maturity',
                      ],
                      onChanged: (value) => setState(() => _filter = value),
                    ),
                    const SizedBox(height: 14),
                    _SettingsPanel(
                      showAgeRules: _showAgeRules,
                      showMediaConsent: _showMediaConsent,
                      showModeration: _showModeration,
                      showClubGuides: _showClubGuides,
                      onAgeRules: (value) =>
                          setState(() => _showAgeRules = value),
                      onMediaConsent: (value) =>
                          setState(() => _showMediaConsent = value),
                      onModeration: (value) =>
                          setState(() => _showModeration = value),
                      onClubGuides: (value) =>
                          setState(() => _showClubGuides = value),
                    ),
                    const SizedBox(height: 14),
                    for (final item in _visibleItems) ...[
                      _GuidelineCard(
                        item: item,
                        showAgeRules: _showAgeRules,
                        showMediaConsent: _showMediaConsent,
                        showModeration: _showModeration,
                        showClubGuides: _showClubGuides,
                      ),
                      const SizedBox(height: 12),
                    ],
                    _ActionPanel(
                      onPrivacy: () => Navigator.of(context).push(
                        MaterialPageRoute(
                          builder: (_) => PrivacyConsentCenterScreen(),
                        ),
                      ),
                      onReport: () => Navigator.of(context).push(
                        MaterialPageRoute(
                          builder: (_) => ReportModerationCenterScreen(),
                        ),
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

  void _openSupport(BuildContext context) {
    Navigator.of(
      context,
    ).push(MaterialPageRoute(builder: (_) => SupportHelpdeskScreen()));
  }
}

class _GuidelineItem {
  const _GuidelineItem({
    required this.title,
    required this.area,
    required this.status,
    required this.meta,
    required this.description,
    required this.icon,
    required this.color,
    required this.details,
  });

  final String title;
  final String area;
  final String status;
  final String meta;
  final String description;
  final IconData icon;
  final Color color;
  final List<String> details;
}

class _TopBar extends StatelessWidget {
  const _TopBar({required this.onHelp});

  final VoidCallback onHelp;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        const AirmiusLogo(markOnly: true, size: 34),
        const SizedBox(width: 10),
        const Expanded(
          child: Text(
            'Airmius',
            style: TextStyle(
              color: Colors.white,
              fontSize: 20,
              fontWeight: FontWeight.w900,
              letterSpacing: .2,
            ),
          ),
        ),
        IconButton(
          onPressed: onHelp,
          icon: const Icon(Icons.help_outline, color: Color(0xFFAFC0D8)),
        ),
      ],
    );
  }
}

class _PageIntro extends StatelessWidget {
  const _PageIntro({
    required this.eyebrow,
    required this.title,
    required this.subtitle,
  });

  final String eyebrow;
  final String title;
  final String subtitle;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(24),
        gradient: const LinearGradient(
          colors: [Color(0xFF121A27), Color(0xFF0B111B)],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        border: Border.all(color: Color(0xFF243348)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            eyebrow,
            style: const TextStyle(
              color: Color(0xFF5BA7FF),
              fontSize: 12,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 8),
          Text(
            title,
            style: const TextStyle(
              color: Colors.white,
              fontSize: 30,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 6),
          Text(
            subtitle,
            style: const TextStyle(
              color: Color(0xFFAFC0D8),
              height: 1.45,
              fontWeight: FontWeight.w600,
            ),
          ),
        ],
      ),
    );
  }
}

class _MetricTile extends StatelessWidget {
  const _MetricTile({required this.value, required this.label});

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
          Text(
            value,
            style: const TextStyle(
              color: Colors.white,
              fontSize: 24,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 4),
          Text(
            label,
            style: const TextStyle(
              color: Color(0xFFAFC0D8),
              fontWeight: FontWeight.w700,
            ),
          ),
        ],
      ),
    );
  }
}

class _FilterBar extends StatelessWidget {
  const _FilterBar({
    required this.value,
    required this.values,
    required this.onChanged,
  });

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
        separatorBuilder: (_, _) => const SizedBox(width: 8),
        itemBuilder: (context, index) {
          final item = values[index];
          final active = item == value;
          return ChoiceChip(
            label: Text(item),
            selected: active,
            onSelected: (_) => onChanged(item),
            labelStyle: TextStyle(
              color: active ? Colors.white : const Color(0xFFAFC0D8),
              fontWeight: FontWeight.w900,
            ),
            selectedColor: const Color(0xFF173D68),
            backgroundColor: const Color(0xFF101722),
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(999),
              side: const BorderSide(color: Color(0xFF26364D)),
            ),
          );
        },
      ),
    );
  }
}

class _SettingsPanel extends StatelessWidget {
  const _SettingsPanel({
    required this.showAgeRules,
    required this.showMediaConsent,
    required this.showModeration,
    required this.showClubGuides,
    required this.onAgeRules,
    required this.onMediaConsent,
    required this.onModeration,
    required this.onClubGuides,
  });

  final bool showAgeRules;
  final bool showMediaConsent;
  final bool showModeration;
  final bool showClubGuides;
  final ValueChanged<bool> onAgeRules;
  final ValueChanged<bool> onMediaConsent;
  final ValueChanged<bool> onModeration;
  final ValueChanged<bool> onClubGuides;

  @override
  Widget build(BuildContext context) {
    return _Panel(
      title: 'Regeln, die Vereine steuern können',
      child: Column(
        children: [
          _SwitchLine(
            label: 'Altersregeln anzeigen',
            value: showAgeRules,
            onChanged: onAgeRules,
          ),
          _SwitchLine(
            label: 'Medienfreigaben anzeigen',
            value: showMediaConsent,
            onChanged: onMediaConsent,
          ),
          _SwitchLine(
            label: 'Moderation anzeigen',
            value: showModeration,
            onChanged: onModeration,
          ),
          _SwitchLine(
            label: 'Vereinsleitfaden anzeigen',
            value: showClubGuides,
            onChanged: onClubGuides,
          ),
        ],
      ),
    );
  }
}

class _SwitchLine extends StatelessWidget {
  const _SwitchLine({
    required this.label,
    required this.value,
    required this.onChanged,
  });

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
      title: Text(
        label,
        style: const TextStyle(
          color: Colors.white,
          fontWeight: FontWeight.w800,
        ),
      ),
    );
  }
}

class _GuidelineCard extends StatelessWidget {
  const _GuidelineCard({
    required this.item,
    required this.showAgeRules,
    required this.showMediaConsent,
    required this.showModeration,
    required this.showClubGuides,
  });

  final _GuidelineItem item;
  final bool showAgeRules;
  final bool showMediaConsent;
  final bool showModeration;
  final bool showClubGuides;

  @override
  Widget build(BuildContext context) {
    final details = [
      if (showAgeRules) item.details[0],
      if (showMediaConsent) item.details[1],
      if (showModeration) item.details[2],
      if (showClubGuides) item.details[3],
    ];

    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: const Color(0xFF101722),
        borderRadius: BorderRadius.circular(24),
        border: Border.all(color: const Color(0xFF26364D)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                width: 54,
                height: 54,
                decoration: BoxDecoration(
                  color: item.color.withValues(alpha: .14),
                  borderRadius: BorderRadius.circular(16),
                  border: Border.all(color: item.color.withValues(alpha: .45)),
                ),
                child: Icon(item.icon, color: item.color, size: 28),
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      item.title,
                      style: const TextStyle(
                        color: Colors.white,
                        fontSize: 18,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      '${item.meta} - ${item.area}',
                      style: const TextStyle(
                        color: Color(0xFFAFC0D8),
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ],
                ),
              ),
              _Pill(label: item.status, color: item.color),
            ],
          ),
          const SizedBox(height: 14),
          Text(
            item.description,
            style: const TextStyle(
              color: Color(0xFFDDE7F5),
              height: 1.45,
              fontWeight: FontWeight.w600,
            ),
          ),
          const SizedBox(height: 12),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [for (final detail in details) _SmallTag(label: detail)],
          ),
        ],
      ),
    );
  }
}

class _ActionPanel extends StatelessWidget {
  const _ActionPanel({
    required this.onPrivacy,
    required this.onReport,
    required this.onSupport,
  });

  final VoidCallback onPrivacy;
  final VoidCallback onReport;
  final VoidCallback onSupport;

  @override
  Widget build(BuildContext context) {
    return _Panel(
      title: 'Schnellaktionen',
      child: Column(
        children: [
          _ActionButton(
            icon: Icons.privacy_tip_outlined,
            label: 'Datenschutz & Einwilligungen',
            onTap: onPrivacy,
          ),
          const SizedBox(height: 10),
          _ActionButton(
            icon: Icons.report_gmailerrorred_outlined,
            label: 'Meldung oder Moderation',
            onTap: onReport,
          ),
          const SizedBox(height: 10),
          _ActionButton(
            icon: Icons.support_agent_outlined,
            label: 'Support kontaktieren',
            onTap: onSupport,
          ),
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
          Text(
            title,
            style: const TextStyle(
              color: Colors.white,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 12),
          child,
        ],
      ),
    );
  }
}

class _ActionButton extends StatelessWidget {
  const _ActionButton({
    required this.icon,
    required this.label,
    required this.onTap,
  });

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
            Expanded(
              child: Text(
                label,
                style: const TextStyle(
                  color: Colors.white,
                  fontWeight: FontWeight.w900,
                ),
              ),
            ),
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
        color: color.withValues(alpha: .12),
        borderRadius: BorderRadius.circular(999),
        border: Border.all(color: color.withValues(alpha: .55)),
      ),
      child: Text(
        label,
        style: TextStyle(
          color: color,
          fontSize: 12,
          fontWeight: FontWeight.w900,
        ),
      ),
    );
  }
}

class _SmallTag extends StatelessWidget {
  const _SmallTag({required this.label});

  final String label;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 7),
      decoration: BoxDecoration(
        color: const Color(0xFF172235),
        borderRadius: BorderRadius.circular(999),
        border: Border.all(color: const Color(0xFF26364D)),
      ),
      child: Text(
        label,
        style: const TextStyle(
          color: Color(0xFFDDE7F5),
          fontSize: 12,
          fontWeight: FontWeight.w800,
        ),
      ),
    );
  }
}
