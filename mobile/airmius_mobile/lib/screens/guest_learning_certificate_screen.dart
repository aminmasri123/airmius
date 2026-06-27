import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'learning_screen.dart';
import 'support_helpdesk_screen.dart';

class GuestLearningCertificateScreen extends StatefulWidget {
  const GuestLearningCertificateScreen({super.key});

  @override
  State<GuestLearningCertificateScreen> createState() => _GuestLearningCertificateScreenState();
}

class _GuestLearningCertificateScreenState extends State<GuestLearningCertificateScreen> {
  String _category = 'Alle';
  bool _showCourses = true;
  bool _showCertificates = true;
  bool _showPreview = true;
  bool _showPublicVerify = true;

  final _certificateCode = TextEditingController(text: 'AIR-2026-ZBB');

  final List<_LearningItem> _items = const [
    _LearningItem(title: 'Vereinsadmin Grundlagen', category: 'Vereine', body: 'Mitgliedsanträge, Rollen, Dokumente, Beitragsregeln und Verifizierung verstehen.', status: 'Kurs', meta: '8 Lektionen', icon: Icons.school_outlined, color: AirmiusColors.blue),
    _LearningItem(title: 'Trainer Safety Basics', category: 'Trainer', body: 'Anwesenheit, Minderjaehrige, Notfallkontakt, Medienfreigabe und Teamkommunikation.', status: 'Kurs', meta: 'Zertifikat', icon: Icons.sports_outlined, color: AirmiusColors.green),
    _LearningItem(title: 'Airmius Zertifikat prüfen', category: 'Zertifikate', body: 'LearningCertificateVerify mit Code, Gültigkeit, Kurs und Inhaberstatus.', status: 'Verify', meta: 'AIR-2026', icon: Icons.verified_outlined, color: AirmiusColors.amber),
    _LearningItem(title: 'Public Course Show', category: 'Kurse', body: 'Öffentliche Kursdetailseite mit Beschreibung, Nutzen, Lektionen und Start-CTA.', status: 'Public', meta: 'Preview', icon: Icons.menu_book_outlined, color: AirmiusColors.red),
  ];

  List<_LearningItem> get _visibleItems => _items.where((item) => _category == 'Alle' || item.category == _category).toList();

  @override
  void dispose() {
    _certificateCode.dispose();
    super.dispose();
  }

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
                        const PageTitle(title: 'E-Learning & Zertifikate', subtitle: 'Guest E-Learning, Kursdetail, Zertifikatsprüfung, Vorschau und Kursstart als mobile Public-UI.'),
                        const SizedBox(height: 16),
                        _LearningHero(onStart: () => Navigator.push(context, MaterialPageRoute(builder: (_) => LearningScreen()))),
                        const SizedBox(height: 16),
                        _ChoicePanel(title: 'Kategorie', value: _category, values: const ['Alle', 'Vereine', 'Trainer', 'Zertifikate', 'Kurse'], onChanged: (value) => setState(() => _category = value)),
                        const SizedBox(height: 16),
                        AirmiusPanel(
                          title: 'Public Learning Optionen',
                          child: Column(
                            children: [
                              _SwitchRow(title: 'Kurse anzeigen', subtitle: 'Guest E-Learning und Kursübersicht mobil vorbereiten.', value: _showCourses, onChanged: (value) => setState(() => _showCourses = value)),
                              _SwitchRow(title: 'Zertifikate anzeigen', subtitle: 'Zertifikate, Gültigkeit und Inhaberstatus sichtbar machen.', value: _showCertificates, onChanged: (value) => setState(() => _showCertificates = value)),
                              _SwitchRow(title: 'Vorschau erlauben', subtitle: 'Public Course Show mit Preview und Start-CTA.', value: _showPreview, onChanged: (value) => setState(() => _showPreview = value)),
                              _SwitchRow(title: 'Öffentliche Prüfung erlauben', subtitle: 'LearningCertificateVerify mit Code und Ergebnis vorbereiten.', value: _showPublicVerify, onChanged: (value) => setState(() => _showPublicVerify = value)),
                            ],
                          ),
                        ),
                        const SizedBox(height: 16),
                        AirmiusPanel(
                          title: 'Zertifikat prüfen',
                          child: Column(
                            children: [
                              AirmiusTextField(label: 'Zertifikatscode', controller: _certificateCode),
                              const SizedBox(height: 10),
                              Align(alignment: Alignment.centerRight, child: AirmiusButton(label: 'Prüfen', icon: Icons.verified_outlined, secondary: true, onPressed: () => _toast('Zertifikat prüfen vorbereitet'))),
                            ],
                          ),
                        ),
                        const SizedBox(height: 16),
                        for (final item in items) ...[
                          _LearningCard(item: item, onOpen: () => _toast('${item.title}: Detail vorbereitet')),
                          const SizedBox(height: 12),
                        ],
                        if (items.isEmpty) const EmptyPanel('Keine Kurse für diese Kategorie gefunden.'),
                        const SizedBox(height: 16),
                        AirmiusPanel(
                          title: 'Aktionen',
                          child: Wrap(
                            spacing: 10,
                            runSpacing: 10,
                            children: [
                              AirmiusButton(label: 'Lernen starten', icon: Icons.school_outlined, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => LearningScreen()))),
                              AirmiusButton(label: 'Zertifikat prüfen', icon: Icons.verified_outlined, secondary: true, onPressed: () => _toast('Zertifikat prüfen vorbereitet')),
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

class _LearningHero extends StatelessWidget {
  const _LearningHero({required this.onStart});

  final VoidCallback onStart;

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
              const Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Eyebrow('PUBLIC LEARNING'), SizedBox(height: 4), Text('Kurse und Zertifikate entdecken', style: TextStyle(color: AirmiusColors.text, fontSize: 22, fontWeight: FontWeight.w900))])),
              AirmiusButton(label: 'Starten', icon: Icons.school_outlined, onPressed: onStart),
            ],
          ),
          const SizedBox(height: 14),
          const Text('Die Guest-Learning-Webseiten werden als mobile UI abgebildet: E-Learning, Course Show, Zertifikatsprüfung und Kursstart.', style: TextStyle(color: AirmiusColors.muted, height: 1.45, fontWeight: FontWeight.w700)),
          const SizedBox(height: 16),
          const Row(children: [Expanded(child: MetricCard(value: '4', label: 'Inhalte')), SizedBox(width: 10), Expanded(child: MetricCard(value: '1', label: 'Verify')), SizedBox(width: 10), Expanded(child: MetricCard(value: '8', label: 'Lektionen'))]),
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

class _LearningCard extends StatelessWidget {
  const _LearningCard({required this.item, required this.onOpen});

  final _LearningItem item;
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

class _LearningItem {
  const _LearningItem({required this.title, required this.category, required this.body, required this.status, required this.meta, required this.icon, required this.color});

  final String title;
  final String category;
  final String body;
  final String status;
  final String meta;
  final IconData icon;
  final Color color;
}
