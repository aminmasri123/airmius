import 'package:flutter/material.dart';
import 'learning_operations_screen.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'certificate_verification_screen.dart';
import 'content_operations_screen.dart';
import 'lesson_detail_screen.dart';
import 'ui_action_result_screen.dart';

class LearningScreen extends StatefulWidget {
  const LearningScreen({super.key});

  @override
  State<LearningScreen> createState() => _LearningScreenState();
}

class _LearningScreenState extends State<LearningScreen> {
  String _filter = 'Meine Kurse';

  @override
  Widget build(BuildContext context) {
    final courses = _courses.where((course) => _filter == 'Alle' || course.status == _filter).toList();
    return Scaffold(
        floatingActionButton: FloatingActionButton.extended(backgroundColor: const Color(0xFF16855E), foregroundColor: Colors.white, icon: const Icon(Icons.school_outlined), label: const Text('Learning Ops', style: TextStyle(fontWeight: FontWeight.w900)), onPressed: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => LearningOperationsScreen(initialTab: 'Kurse')))),
        
      appBar: AppBar(
        backgroundColor: AirmiusColors.header,
        surfaceTintColor: Colors.transparent,
        title: const Text('Kurse', style: TextStyle(fontWeight: FontWeight.w900)),
      ),
      body: PageFrame(
        title: 'Kurse',
        subtitle: 'E-Learning, Lektionen, Zertifikate und Lernstudio',
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            AirmiusPanel(
              gradient: true,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: const [
                  Eyebrow('Learning'),
                  SizedBox(height: 8),
                  Text('Kurse starten, Lektionen abschließen, Quiz bestehen und Zertifikate anzeigen.', style: TextStyle(color: AirmiusColors.muted, height: 1.35)),
                ],
              ),
            ),
            const SizedBox(height: 14),
            Row(
              children: const [
                Expanded(child: MetricCard(value: '4', label: 'Kurse')),
                SizedBox(width: 10),
                Expanded(child: MetricCard(value: '2', label: 'Zertifikate')),
                SizedBox(width: 10),
                Expanded(child: MetricCard(value: '72%', label: 'Fortschritt')),
              ],
            ),
            const SizedBox(height: 14),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: [
                for (final filter in const ['Meine Kurse', 'Alle', 'Lernstudio'])
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
            for (final course in courses) ...[
              _CourseCard(course: course),
              const SizedBox(height: 12),
            ],
            if (courses.isEmpty) const AirmiusPanel(child: Padding(padding: EdgeInsets.all(18), child: Center(child: Text('Keine Kurse in diesem Filter.', style: TextStyle(color: AirmiusColors.muted))))),
            const SizedBox(height: 14),
            const _CertificatesPanel(),
            const SizedBox(height: 14),
            const _StudioPanel(),
            const SizedBox(height: 14),
            AirmiusButton(label: 'Learning Quality', icon: Icons.fact_check_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => ContentOperationsScreen()))),
          ],
        ),
      ),
    );
  }
}

class _CourseCard extends StatelessWidget {
  const _CourseCard({required this.course});

  final _Course course;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => LessonDetailScreen(title: course.title, description: course.description, status: course.status, lessons: course.lessons, progress: course.progress))),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                width: 58,
                height: 58,
                decoration: BoxDecoration(color: AirmiusColors.cardSoft, borderRadius: BorderRadius.circular(18), border: Border.all(color: AirmiusColors.border)),
                child: const Icon(Icons.play_circle_outline, color: AirmiusColors.blue, size: 30),
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(course.title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900, fontSize: 17)),
                    const SizedBox(height: 4),
                    Text(course.description, style: const TextStyle(color: AirmiusColors.muted, height: 1.3)),
                    const SizedBox(height: 9),
                    Wrap(spacing: 8, runSpacing: 8, children: [StatusPill(course.status), StatusPill('${course.lessons} Lektionen')]),
                  ],
                ),
              ),
              const Icon(Icons.chevron_right, color: AirmiusColors.muted),
            ],
          ),
          const SizedBox(height: 12),
          ClipRRect(
            borderRadius: BorderRadius.circular(99),
            child: LinearProgressIndicator(value: course.progress, minHeight: 9, backgroundColor: AirmiusColors.cardSoft, valueColor: const AlwaysStoppedAnimation<Color>(AirmiusColors.blue)),
          ),
        ],
      ),
    );
  }
}

class _CertificatesPanel extends StatelessWidget {
  const _CertificatesPanel();

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const Eyebrow('Zertifikate'),
          const SizedBox(height: 10),
          const _LearningLine(icon: Icons.verified_outlined, title: 'Datenschutz im Sportverein', body: 'Zertifikat #AIR-2026-001', trailing: 'Gültig'),
          const _LearningLine(icon: Icons.download_outlined, title: 'Download', body: 'PDF-Zertifikat herunterladen oder teilen.', trailing: 'PDF'),
          const SizedBox(height: 12),
          AirmiusButton(label: 'Zertifikat prüfen', icon: Icons.fact_check_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => CertificateVerificationScreen()))),
        ],
      ),
    );
  }
}

class _StudioPanel extends StatelessWidget {
  const _StudioPanel();

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const Eyebrow('Lernstudio'),
          const SizedBox(height: 10),
          const _LearningLine(icon: Icons.video_library_outlined, title: 'Lektionen verwalten', body: 'Videos, Texte, Abschnitte und Reihenfolge.', trailing: 'Studio'),
          const _LearningLine(icon: Icons.quiz_outlined, title: 'Quiz & Aufgaben', body: 'Fragen, Versuche, Bewertung und Kommentare.', trailing: 'Quiz'),
          const SizedBox(height: 12),
          AirmiusButton(label: 'Kurs erstellen', icon: Icons.add_circle_outline, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => UiActionResultScreen(title: 'Kurs erstellen', body: 'Kurs, Lektionen, Quiz, Aufgaben und Zertifikat im Lernstudio vorbereiten.', status: 'Studio', icon: Icons.add_circle_outline)))),
        ],
      ),
    );
  }
}

class _LearningLine extends StatelessWidget {
  const _LearningLine({required this.icon, required this.title, required this.body, required this.trailing});

  final IconData icon;
  final String title;
  final String body;
  final String trailing;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(top: 10),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, color: AirmiusColors.blue),
          const SizedBox(width: 12),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text(title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), const SizedBox(height: 3), Text(body, style: const TextStyle(color: AirmiusColors.muted, height: 1.3))])),
          StatusPill(trailing),
        ],
      ),
    );
  }
}

class _Course {
  const _Course({required this.title, required this.description, required this.status, required this.lessons, required this.progress});

  final String title;
  final String description;
  final String status;
  final int lessons;
  final double progress;
}

const _courses = [
  _Course(title: 'Grundlagen Vereinsverwaltung', description: 'Rollen, Mitglieder, Dokumente und digitale Prozesse.', status: 'Meine Kurse', lessons: 4, progress: 0.42),
  _Course(title: 'Datenschutz im Sportverein', description: 'Einwilligungen, Dokumente, Minderjaehrige und Datenrechte.', status: 'Meine Kurse', lessons: 6, progress: 0.72),
  _Course(title: 'Trainer-Kommunikation', description: 'Feedback, Chat, Events und Trainingsplanung.', status: 'Alle', lessons: 5, progress: 0.0),
  _Course(title: 'Kursentwurf: Vereinsbeiträge', description: 'Lernstudio-Entwurf mit Quiz und Zertifikat.', status: 'Lernstudio', lessons: 3, progress: 0.25),
];

