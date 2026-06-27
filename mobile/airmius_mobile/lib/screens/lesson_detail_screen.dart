import 'package:flutter/material.dart';
import 'learning_operations_screen.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'ui_action_result_screen.dart';
import 'learning_operations_screen.dart';

class LessonDetailScreen extends StatefulWidget {
  const LessonDetailScreen({super.key, required this.title, required this.description, required this.status, required this.lessons, required this.progress});

  final String title;
  final String description;
  final String status;
  final int lessons;
  final double progress;

  @override
  State<LessonDetailScreen> createState() => _LessonDetailScreenState();
}

class _LessonDetailScreenState extends State<LessonDetailScreen> {
  String _tab = 'Lektion';

  @override
  Widget build(BuildContext context) {
    return Scaffold(
        floatingActionButton: FloatingActionButton.extended(backgroundColor: const Color(0xFF16855E), foregroundColor: Colors.white, icon: const Icon(Icons.school_outlined), label: const Text('Lesson Ops', style: TextStyle(fontWeight: FontWeight.w900)), onPressed: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => LearningOperationsScreen(initialTab: 'Lektionen')))),
        
      appBar: AppBar(backgroundColor: AirmiusColors.header, surfaceTintColor: Colors.transparent, title: Text(widget.title, style: const TextStyle(fontWeight: FontWeight.w900))),
      body: PageFrame(
        title: widget.title,
        subtitle: 'Lektionen, Video, Quiz, Aufgaben, Kommentare und Zertifikat',
        trailing: StatusPill(widget.status),
        child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          AirmiusPanel(gradient: true, child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            Container(height: 180, decoration: BoxDecoration(color: AirmiusColors.cardSoft, borderRadius: BorderRadius.circular(18), border: Border.all(color: AirmiusColors.border)), child: const Center(child: Icon(Icons.play_circle_outline, color: AirmiusColors.blue, size: 72))),
            const SizedBox(height: 12),
            Text(widget.description, style: const TextStyle(color: AirmiusColors.muted, height: 1.4)),
            const SizedBox(height: 12),
            ClipRRect(borderRadius: BorderRadius.circular(99), child: LinearProgressIndicator(value: widget.progress, minHeight: 9, backgroundColor: AirmiusColors.cardSoft, valueColor: const AlwaysStoppedAnimation<Color>(AirmiusColors.blue))),
          ])),
          const SizedBox(height: 14),
          AirmiusPanel(child: Wrap(spacing: 8, runSpacing: 8, children: ['Lektion', 'Quiz', 'Aufgabe', 'Kommentare'].map((item) => ChoiceChip(
            selected: _tab == item,
            label: Text(item),
            onSelected: (_) => setState(() => _tab = item),
            selectedColor: AirmiusColors.blue.withValues(alpha: 0.22),
            backgroundColor: AirmiusColors.cardSoft,
            side: BorderSide(color: _tab == item ? AirmiusColors.blue : AirmiusColors.border),
            labelStyle: TextStyle(color: _tab == item ? AirmiusColors.blue : AirmiusColors.muted, fontWeight: FontWeight.w900),
          )).toList())),
          const SizedBox(height: 14),
          Row(children: [Expanded(child: MetricCard(value: '${widget.lessons}', label: 'Lektionen')), const SizedBox(width: 10), const Expanded(child: MetricCard(value: '5', label: 'Quizfragen')), const SizedBox(width: 10), const Expanded(child: MetricCard(value: 'PDF', label: 'Zertifikat'))]),
          const SizedBox(height: 14),
          const AirmiusPanel(child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            Eyebrow('Aktuelle Lektion'),
            SizedBox(height: 10),
            _LessonLine(icon: Icons.video_library_outlined, title: 'Video ansehen', body: 'Kapitel, Fortschritt und Wiedergabestatus werden später gespeichert.', status: '12:40'),
            _LessonLine(icon: Icons.quiz_outlined, title: 'Quiz bestehen', body: 'Fragen, Versuche, Bewertung und Ergebnisanzeige.', status: '5 Fragen'),
            _LessonLine(icon: Icons.assignment_turned_in_outlined, title: 'Aufgabe abgeben', body: 'Text, Datei, Kommentar und Trainerfeedback vorbereiten.', status: 'Offen'),
          ])),
          const SizedBox(height: 14),
          Wrap(spacing: 10, runSpacing: 10, children: [
            AirmiusButton(label: 'Als erledigt markieren', icon: Icons.check_circle_outline, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => UiActionResultScreen(title: 'Lektion erledigt', body: '${widget.title} als abgeschlossen markieren und Fortschritt aktualisieren.', status: 'Fortschritt', icon: Icons.check_circle_outline)))),
            AirmiusButton(label: 'Zertifikat', icon: Icons.verified_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => UiActionResultScreen(title: 'Zertifikat', body: 'Zertifikat für ${widget.title} anzeigen, herunterladen oder teilen.', status: 'PDF', icon: Icons.verified_outlined)))),
          ]),
        ]),
      ),
    );
  }
}

class _LessonLine extends StatelessWidget {
  const _LessonLine({required this.icon, required this.title, required this.body, required this.status});

  final IconData icon;
  final String title;
  final String body;
  final String status;

  @override
  Widget build(BuildContext context) {
    return Padding(padding: const EdgeInsets.only(top: 12), child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [Icon(icon, color: AirmiusColors.blue), const SizedBox(width: 12), Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text(title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), const SizedBox(height: 3), Text(body, style: const TextStyle(color: AirmiusColors.muted, height: 1.35))])), StatusPill(status)]));
  }
}

