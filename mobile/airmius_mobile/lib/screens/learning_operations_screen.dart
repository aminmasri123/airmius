import 'package:flutter/material.dart';

import '../core/api_contract.dart';
import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

class LearningOperationsScreen extends StatefulWidget {
  const LearningOperationsScreen({super.key, this.initialTab = 'Kurse'});

  final String initialTab;

  @override
  State<LearningOperationsScreen> createState() => _LearningOperationsScreenState();
}

class _LearningOperationsScreenState extends State<LearningOperationsScreen> {
  String _tab = 'Kurse';
  bool _certificateEnabled = true;
  bool _qualityRequired = true;
  bool _publicVisible = false;

  @override
  void initState() {
    super.initState();
    if (_tabs.contains(widget.initialTab)) _tab = widget.initialTab;
  }

  @override
  Widget build(BuildContext context) {
    final items = _tab == 'Alle' ? _operations : _operations.where((item) => item.tab == _tab).toList();
    return Scaffold(
      backgroundColor: AirmiusColors.bg,
      appBar: AppBar(backgroundColor: AirmiusColors.header, surfaceTintColor: Colors.transparent, title: const Text('Learning Ops', style: TextStyle(fontWeight: FontWeight.w900))),
      body: PageFrame(
        title: 'Learning Ops',
        subtitle: 'Kurse, Lektionen, Quiz, Aufgaben, Zertifikate und Quality-Gates',
        child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          AirmiusPanel(borderColor: AirmiusColors.green.withValues(alpha: .42), child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            const Eyebrow('Lernstudio'),
            const SizedBox(height: 8),
            const Text('Die App bildet Public Learning, eigene Kurse, Lektionen, Quiz, Aufgaben und Zertifikatsprüfung als native Mobile-Workflows ab.', style: TextStyle(color: AirmiusColors.muted, height: 1.4)),
            const SizedBox(height: 12),
            SwitchListTile(value: _certificateEnabled, onChanged: (value) => setState(() => _certificateEnabled = value), activeColor: AirmiusColors.green, contentPadding: EdgeInsets.zero, title: const Text('Zertifikat aktiv', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), subtitle: const Text('Kursabschluss erzeugt prüfbaren Zertifikatscode.', style: TextStyle(color: AirmiusColors.muted))),
            SwitchListTile(value: _qualityRequired, onChanged: (value) => setState(() => _qualityRequired = value), activeColor: AirmiusColors.blue, contentPadding: EdgeInsets.zero, title: const Text('Quality Gate', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), subtitle: const Text('Admin muss Kurs, Aufgaben und Zertifikat freigeben.', style: TextStyle(color: AirmiusColors.muted))),
            SwitchListTile(value: _publicVisible, onChanged: (value) => setState(() => _publicVisible = value), activeColor: AirmiusColors.amber, contentPadding: EdgeInsets.zero, title: const Text('Public sichtbar', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), subtitle: const Text('Kurs im öffentlichen Katalog anzeigen.', style: TextStyle(color: AirmiusColors.muted))),
            const SizedBox(height: 10),
            Wrap(spacing: 8, runSpacing: 8, children: [for (final tab in _tabs) ChoiceChip(label: Text(tab), selected: _tab == tab, onSelected: (_) => setState(() => _tab = tab), selectedColor: AirmiusColors.green.withValues(alpha: .2), backgroundColor: AirmiusColors.panelSoft, side: BorderSide(color: _tab == tab ? AirmiusColors.green : AirmiusColors.border), labelStyle: TextStyle(color: _tab == tab ? AirmiusColors.text : AirmiusColors.muted, fontWeight: FontWeight.w900))]),
          ])),
          const SizedBox(height: 16),
          Row(children: const [Expanded(child: MetricCard(value: '4', label: 'Kurse')), SizedBox(width: 10), Expanded(child: MetricCard(value: '2', label: 'Zertifikate')), SizedBox(width: 10), Expanded(child: MetricCard(value: '72%', label: 'Fortschritt'))]),
          const SizedBox(height: 16),
          for (final item in items) ...[_LearningOperationCard(item: item), const SizedBox(height: 12)],
        ]),
      ),
    );
  }
}

class _LearningOperationCard extends StatelessWidget {
  const _LearningOperationCard({required this.item});
  final _LearningOperation item;

  @override
  Widget build(BuildContext context) => AirmiusPanel(borderColor: item.danger ? AirmiusColors.red.withValues(alpha: .45) : AirmiusColors.border, child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
    Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Container(width: 48, height: 48, decoration: BoxDecoration(color: item.color.withValues(alpha: .13), borderRadius: BorderRadius.circular(16), border: Border.all(color: item.color.withValues(alpha: .45))), child: Icon(item.icon, color: item.color)),
      const SizedBox(width: 12),
      Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text(item.title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900, fontSize: 16)), const SizedBox(height: 5), Text(item.body, style: const TextStyle(color: AirmiusColors.muted, height: 1.35))])),
      StatusPill(item.tab, color: item.color),
    ]),
    const SizedBox(height: 12),
    Container(width: double.infinity, padding: const EdgeInsets.all(12), decoration: BoxDecoration(color: AirmiusColors.bg, borderRadius: BorderRadius.circular(14), border: Border.all(color: AirmiusColors.border)), child: Text('${item.method} ${item.endpoint}', style: const TextStyle(color: AirmiusColors.green, fontSize: 12, fontWeight: FontWeight.w900))),
    const SizedBox(height: 12),
    Wrap(spacing: 8, runSpacing: 8, children: [
      AirmiusButton(label: item.action, icon: item.icon, danger: item.danger, onPressed: () => openUiAction(context, title: item.title, body: '${item.body}\n\nEndpoint: ${item.method} ${item.endpoint}', status: item.tab, icon: item.icon)),
      AirmiusButton(label: 'Lernkontext', icon: Icons.manage_search_outlined, secondary: true, onPressed: () => openUiAction(context, title: '${item.title} Kontext', body: 'Kurs, Lektion, User, Fortschritt, Quizversuch, Zertifikatscode, Quality-Gate und Maturity-Status anzeigen.', status: 'Kontext', icon: Icons.manage_search_outlined)),
    ]),
  ]));
}

class _LearningOperation {
  const _LearningOperation({required this.tab, required this.title, required this.body, required this.method, required this.endpoint, required this.icon, required this.action, required this.color, this.danger = false});
  final String tab;
  final String title;
  final String body;
  final String method;
  final String endpoint;
  final IconData icon;
  final String action;
  final Color color;
  final bool danger;
}

const _tabs = ['Kurse', 'Lektionen', 'Quiz', 'Zertifikate', 'Public', 'Admin', 'Alle'];

final _operations = <_LearningOperation>[
  _LearningOperation(tab: 'Kurse', title: 'Kurse laden', body: 'Eigene Kurse, Fortschritt, Zertifikatsstatus und Katalogdaten laden.', method: 'GET', endpoint: ApiContract.courses, icon: Icons.school_outlined, action: 'Laden', color: AirmiusColors.blue),
  _LearningOperation(tab: 'Kurse', title: 'Kurs starten', body: 'Kurs einschreiben, Fortschritt anlegen und erste Lektion öffnen.', method: 'POST', endpoint: ApiContract.courseEnroll(1), icon: Icons.play_circle_outline, action: 'Starten', color: AirmiusColors.green),
  _LearningOperation(tab: 'Kurse', title: 'Kurs abschließen', body: 'Kursabschluss prüfen, Badge/XP vorbereiten und Zertifikat erzeugen.', method: 'POST', endpoint: ApiContract.courseComplete(1), icon: Icons.task_alt_outlined, action: 'Abschließen', color: AirmiusColors.green),
  _LearningOperation(tab: 'Lektionen', title: 'Lektionen laden', body: 'Lektionsliste, Medien, Aufgaben und Reihenfolge laden.', method: 'GET', endpoint: ApiContract.courseLessons(1), icon: Icons.video_library_outlined, action: 'Lektionen', color: AirmiusColors.blue),
  _LearningOperation(tab: 'Lektionen', title: 'Lektion erledigen', body: 'Video-/Textlektion als erledigt markieren und Fortschritt aktualisieren.', method: 'POST', endpoint: ApiContract.lessonComplete(1), icon: Icons.check_circle_outline, action: 'Erledigt', color: AirmiusColors.green),
  _LearningOperation(tab: 'Quiz', title: 'Quizversuch starten', body: 'Fragen, Versuche, Zeitlimit und Bewertungsregeln laden.', method: 'POST', endpoint: ApiContract.lessonQuizAttempt(1), icon: Icons.quiz_outlined, action: 'Quiz', color: AirmiusColors.amber),
  _LearningOperation(tab: 'Quiz', title: 'Aufgabe abgeben', body: 'Text, Datei, Kommentar und Reviewstatus für Aufgabe speichern.', method: 'POST', endpoint: ApiContract.lessonAssignmentSubmit(1), icon: Icons.assignment_turned_in_outlined, action: 'Abgeben', color: AirmiusColors.green),
  _LearningOperation(tab: 'Zertifikate', title: 'Zertifikate laden', body: 'Eigene Zertifikate mit PDF, Code und Ausstellerstatus laden.', method: 'GET', endpoint: ApiContract.certificates, icon: Icons.verified_outlined, action: 'Zertifikate', color: AirmiusColors.blue),
  _LearningOperation(tab: 'Zertifikate', title: 'Zertifikat prüfen', body: 'Code gegen Public-Verify-Route validieren und Ergebnis anzeigen.', method: 'GET', endpoint: ApiContract.publicCertificate('AIR-2026-001'), icon: Icons.fact_check_outlined, action: 'Prüfen', color: AirmiusColors.green),
  _LearningOperation(tab: 'Zertifikate', title: 'Zertifikat melden', body: 'Unstimmigkeit oder Missbrauch eines Zertifikats melden.', method: 'POST', endpoint: ApiContract.certificateReport('AIR-2026-001'), icon: Icons.report_outlined, action: 'Melden', color: AirmiusColors.red, danger: true),
  _LearningOperation(tab: 'Public', title: 'Public Learning laden', body: 'Öffentlichen Kurskatalog für Gastseite und App anzeigen.', method: 'GET', endpoint: ApiContract.publicLearning, icon: Icons.public_outlined, action: 'Public', color: AirmiusColors.blue),
  _LearningOperation(tab: 'Public', title: 'Public Kursdetail', body: 'Kursdetail mit Beschreibung, Lektionen, Anbieter und Interesse laden.', method: 'GET', endpoint: ApiContract.publicCourse(1), icon: Icons.menu_book_outlined, action: 'Detail', color: AirmiusColors.blue),
  _LearningOperation(tab: 'Admin', title: 'Learning Quality speichern', body: 'Kursqualitaet, Inhalte, Zertifikat, Sichtbarkeit und Reviewnotiz speichern.', method: 'PUT', endpoint: ApiContract.adminLearningCourseQuality(1), icon: Icons.fact_check_outlined, action: 'Quality', color: AirmiusColors.amber),
];
