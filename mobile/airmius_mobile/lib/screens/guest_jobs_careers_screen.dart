import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'support_helpdesk_screen.dart';

class GuestJobsCareersScreen extends StatefulWidget {
  const GuestJobsCareersScreen({super.key});

  @override
  State<GuestJobsCareersScreen> createState() => _GuestJobsCareersScreenState();
}

class _GuestJobsCareersScreenState extends State<GuestJobsCareersScreen> {
  String _area = 'Alle';
  bool _remote = true;
  bool _partTime = true;
  bool _student = true;

  final List<_JobItem> _jobs = const [
    _JobItem(title: 'Flutter App Developer', area: 'Tech', body: 'Mobile UI, Laravel API-Anbindung, App Store Vorbereitung und Design-System-Ausbau.', status: 'Remote', icon: Icons.phone_iphone_outlined, color: AirmiusColors.blue),
    _JobItem(title: 'Club Success Manager', area: 'Vereine', body: 'Vereine onboarden, Mitgliedsanträge, Dokumente, Rollen und Beitragsregeln begleiten.', status: 'Hybrid', icon: Icons.groups_2_outlined, color: AirmiusColors.green),
    _JobItem(title: 'Content & Community', area: 'Community', body: 'Top-Inhalte, Blog, Social Posts, Moderation und Vereinskommunikation betreuen.', status: 'Teilzeit', icon: Icons.dynamic_feed_outlined, color: AirmiusColors.amber),
    _JobItem(title: 'Sales Partner', area: 'Growth', body: 'Sponsoren, Werbeagentur, Marketplace, Vereine und regionale Partnerschaften aufbauen.', status: 'Provision', icon: Icons.trending_up_outlined, color: AirmiusColors.red),
  ];

  List<_JobItem> get _visibleJobs => _jobs.where((job) => _area == 'Alle' || job.area == _area).toList();

  @override
  Widget build(BuildContext context) {
    final jobs = _visibleJobs;

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
                        const PageTitle(title: 'Jobs bei Airmius', subtitle: 'Karriere, Rollen, Remote, Teilzeit, Bewerbung und Kontakt als mobile Public-UI.'),
                        const SizedBox(height: 16),
                        _JobsHero(onApply: () => _toast('Bewerbung vorbereiten')),
                        const SizedBox(height: 16),
                        _ChoicePanel(title: 'Bereich', value: _area, values: const ['Alle', 'Tech', 'Vereine', 'Community', 'Growth'], onChanged: (value) => setState(() => _area = value)),
                        const SizedBox(height: 16),
                        AirmiusPanel(
                          title: 'Arbeitsmodell',
                          child: Column(
                            children: [
                              _SwitchRow(title: 'Remote möglich', subtitle: 'Mobile, API, Support und Content können remote vorbereitet werden.', value: _remote, onChanged: (value) => setState(() => _remote = value)),
                              _SwitchRow(title: 'Teilzeit möglich', subtitle: 'Rollen können als Teilzeit- oder Projektmodell angezeigt werden.', value: _partTime, onChanged: (value) => setState(() => _partTime = value)),
                              _SwitchRow(title: 'Studenten willkommen', subtitle: 'Werkstudenten, Praktika und Junior-Rollen als Public Flow.', value: _student, onChanged: (value) => setState(() => _student = value)),
                            ],
                          ),
                        ),
                        const SizedBox(height: 16),
                        for (final job in jobs) ...[
                          _JobCard(job: job, onApply: () => _toast('${job.title}: Bewerbung vorbereiten')),
                          const SizedBox(height: 12),
                        ],
                        if (jobs.isEmpty) const EmptyPanel('Keine Jobs für diesen Bereich gefunden.'),
                        const SizedBox(height: 16),
                        AirmiusPanel(
                          title: 'Kontakt',
                          child: Wrap(
                            spacing: 10,
                            runSpacing: 10,
                            children: [
                              AirmiusButton(label: 'Initiativ bewerben', icon: Icons.send_outlined, onPressed: () => _toast('Initiativbewerbung vorbereiten')),
                              AirmiusButton(label: 'Frage stellen', icon: Icons.support_agent_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => SupportHelpdeskScreen()))),
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

class _JobsHero extends StatelessWidget {
  const _JobsHero({required this.onApply});

  final VoidCallback onApply;

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
              const Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Eyebrow('CAREERS'), SizedBox(height: 4), Text('Mit Airmius wachsen', style: TextStyle(color: AirmiusColors.text, fontSize: 22, fontWeight: FontWeight.w900))])),
              AirmiusButton(label: 'Bewerben', icon: Icons.send_outlined, onPressed: onApply),
            ],
          ),
          const SizedBox(height: 14),
          const Text('Die Guest-Jobs-Seite wird als mobile Landing-UI abgebildet: Rollen, Arbeitsmodell, Bewerbung und Kontakt bleiben nah an der Web-App.', style: TextStyle(color: AirmiusColors.muted, height: 1.45, fontWeight: FontWeight.w700)),
          const SizedBox(height: 16),
          const Row(children: [Expanded(child: MetricCard(value: '4', label: 'Rollen')), SizedBox(width: 10), Expanded(child: MetricCard(value: '3', label: 'Modelle')), SizedBox(width: 10), Expanded(child: MetricCard(value: '1', label: 'Kontakt'))]),
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

class _JobCard extends StatelessWidget {
  const _JobCard({required this.job, required this.onApply});

  final _JobItem job;
  final VoidCallback onApply;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      title: job.title,
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(width: 48, height: 48, decoration: BoxDecoration(color: job.color.withValues(alpha: .18), borderRadius: BorderRadius.circular(16), border: Border.all(color: job.color.withValues(alpha: .5))), child: Icon(job.icon, color: job.color)),
          const SizedBox(width: 12),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [StatusPill(job.status, color: job.color), const SizedBox(height: 8), Text(job.area, style: const TextStyle(color: AirmiusColors.blue, fontWeight: FontWeight.w900)), const SizedBox(height: 6), Text(job.body, style: const TextStyle(color: AirmiusColors.muted, height: 1.45, fontWeight: FontWeight.w700))])),
          IconButton(onPressed: onApply, icon: const Icon(Icons.chevron_right, color: AirmiusColors.muted)),
        ],
      ),
    );
  }
}

class _JobItem {
  const _JobItem({required this.title, required this.area, required this.body, required this.status, required this.icon, required this.color});

  final String title;
  final String area;
  final String body;
  final String status;
  final IconData icon;
  final Color color;
}
