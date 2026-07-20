import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'support_helpdesk_screen.dart';

class LearningStudioCourseSuiteScreen extends StatefulWidget {
  const LearningStudioCourseSuiteScreen({super.key});

  @override
  State<LearningStudioCourseSuiteScreen> createState() => _LearningStudioCourseSuiteScreenState();
}

class _LearningStudioCourseSuiteScreenState extends State<LearningStudioCourseSuiteScreen> {
  String _filter = 'Alle';
  bool _showLearner = true;
  bool _showStudio = true;
  bool _showPublic = true;

  final List<_CourseItem> _items = const [
    _CourseItem('Meine Kurse', 'Learner', 'MyCourses', 'Kursliste, Fortschritt, Lektionen, Zertifikate und naechste Schritte für Nutzer.', Icons.school_outlined, Color(0xFF5BA7FF)),
    _CourseItem('Kursdetails', 'Learner', 'Show', 'Mobile Kursseite mit Modulstruktur, Lernstatus, Dauer, Trainer und CTA.', Icons.menu_book_outlined, Color(0xFF2EE59D)),
    _CourseItem('Lesson Detail', 'Learner', 'Lesson', 'Lektion mit Inhalt, Video-Hinweis, Dateien, Quizstatus und Abschlussaktion.', Icons.play_lesson_outlined, Color(0xFFF8B84E)),
    _CourseItem('Learning Studio', 'Studio', 'Creator', 'Erstellerbereich für Kurse, Module, Lektionen, Veröffentlichung und Qualitaetsstatus.', Icons.video_settings_outlined, Color(0xFFB084FF)),
    _CourseItem('Kurs veröffentlichen', 'Studio', 'Publish', 'Freigabe-Workflow, Sichtbarkeit, Preis, Zielgruppe, Medien und Zertifikatsoptionen.', Icons.publish_outlined, Color(0xFFFF6B6B)),
    _CourseItem('Gast E-Learning', 'Public', 'Guest', 'Öffentliche Lernseite mit Kursvorschau, Kategorien, Benefits und Login-CTA.', Icons.public_outlined, Color(0xFF5BA7FF)),
    _CourseItem('Zertifikat prüfen', 'Public', 'Verify', 'Zertifikatscode, Name, Kurs, Aussteller, Gültigkeit und sichere Prüfansicht.', Icons.verified_outlined, Color(0xFF2EE59D)),
  ];

  List<_CourseItem> get _visible {
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
                      eyebrow: 'LEARNING SUITE',
                      title: 'Kurse & Studio',
                      subtitle: 'Native Mobile-UI für MyCourses, Kursdetails, Lektionen, Studio, Public Learning und Zertifikatsprüfung.',
                    ),
                    const SizedBox(height: 18),
                    Row(
                      children: const [
                        Expanded(child: _Metric(value: '7', label: 'Views')),
                        SizedBox(width: 10),
                        Expanded(child: _Metric(value: '3', label: 'Learner')),
                        SizedBox(width: 10),
                        Expanded(child: _Metric(value: '2', label: 'Public')),
                      ],
                    ),
                    const SizedBox(height: 18),
                    _Tabs(value: _filter, values: const ['Alle', 'Learner', 'Studio', 'Public'], onChanged: (value) => setState(() => _filter = value)),
                    const SizedBox(height: 14),
                    _SwitchPanel(
                      title: 'Lernbereiche',
                      rows: [
                        _SwitchRowData('Learner anzeigen', _showLearner, (value) => setState(() => _showLearner = value)),
                        _SwitchRowData('Studio anzeigen', _showStudio, (value) => setState(() => _showStudio = value)),
                        _SwitchRowData('Public anzeigen', _showPublic, (value) => setState(() => _showPublic = value)),
                      ],
                    ),
                    const SizedBox(height: 14),
                    for (final item in _visible.where(_isVisible)) ...[
                      _CourseCard(item: item),
                      const SizedBox(height: 12),
                    ],
                    _ActionPanel(
                      primaryIcon: Icons.play_circle_outline,
                      primaryLabel: 'Kurs starten',
                      secondaryIcon: Icons.verified_outlined,
                      secondaryLabel: 'Zertifikat prüfen',
                      onPrimary: () => openUiAction(context, title: 'Kurs starten', body: 'Die Kurs-UI ist vorbereitet; Lernfortschritt kommt später über Laravel.', status: 'UI bereit', icon: Icons.info_outline),
                      onSecondary: () => openUiAction(context, title: 'Zertifikat prüfen', body: 'Die mobile Prüfansicht ist für API-Zertifikate vorbereitet.', status: 'UI bereit', icon: Icons.info_outline),
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

  bool _isVisible(_CourseItem item) {
    if (item.area == 'Learner') return _showLearner;
    if (item.area == 'Studio') return _showStudio;
    return _showPublic;
  }

  void _openSupport(BuildContext context) {
    Navigator.of(context).push(MaterialPageRoute(builder: (_) => SupportHelpdeskScreen()));
  }
}

class _CourseItem {
  const _CourseItem(this.title, this.area, this.status, this.body, this.icon, this.color);

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
          separatorBuilder: (_, _) => const SizedBox(width: 8),
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

class _CourseCard extends StatelessWidget {
  const _CourseCard({required this.item});

  final _CourseItem item;

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
              decoration: BoxDecoration(color: item.color.withValues(alpha: .14), borderRadius: BorderRadius.circular(16), border: Border.all(color: item.color.withValues(alpha: .45))),
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
        decoration: BoxDecoration(color: color.withValues(alpha: .12), borderRadius: BorderRadius.circular(999), border: Border.all(color: color.withValues(alpha: .55))),
        child: Text(label, style: TextStyle(color: color, fontSize: 12, fontWeight: FontWeight.w900)),
      );
}
