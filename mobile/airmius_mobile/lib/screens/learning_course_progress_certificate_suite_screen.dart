import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

class LearningCourseProgressCertificateSuiteScreen extends StatefulWidget {
  const LearningCourseProgressCertificateSuiteScreen({super.key});

  @override
  State<LearningCourseProgressCertificateSuiteScreen> createState() => _LearningCourseProgressCertificateSuiteScreenState();
}

class _LearningCourseProgressCertificateSuiteScreenState extends State<LearningCourseProgressCertificateSuiteScreen> {
  String track = 'Verein';
  bool showProgress = true;
  bool requireQuiz = true;
  bool issueCertificate = true;
  bool allowDownload = true;

  @override
  Widget build(BuildContext context) {
    final courses = [
      const _CourseRow(
        title: 'Datenschutz im Verein',
        status: '80%',
        body: 'Pflichtkurs für Vereinsadmins mit Lektionen, Quiz, Nachweis und Consent-Bezug.',
        icon: Icons.privacy_tip_outlined,
        color: AirmiusColors.blue,
      ),
      const _CourseRow(
        title: 'Trainer Grundlagen',
        status: 'Zertifikat',
        body: 'Lernpfad für Trainerrollen, Teamrechte, Anwesenheit, Sicherheit und Jugendschutz.',
        icon: Icons.school_outlined,
        color: AirmiusColors.green,
      ),
      const _CourseRow(
        title: 'Mitgliedschaft verstehen',
        status: 'Neu',
        body: 'User lernen Beitritt, Dokumente, Zahlungsregeln, Mitgliedskarte und Support kennen.',
        icon: Icons.menu_book_outlined,
        color: AirmiusColors.amber,
      ),
      const _CourseRow(
        title: 'Sponsoren & Kampagnen',
        status: 'Review',
        body: 'Kurs für Ads, Creative Review, Budget, Reporting und Vereinsfreigaben.',
        icon: Icons.campaign_outlined,
        color: AirmiusColors.pink,
      ),
    ];

    return PageFrame(
      title: 'Kurse & Zertifikate',
      subtitle: 'Lernen, Fortschritt und Nachweise',
      actions: const [AirmiusLogoMark(size: 34)],
      child: ListView(
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 28),
        children: [
          AirmiusPanel(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const SectionLabel('LEARNING CENTER'),
                const SizedBox(height: 8),
                const Text(
                  'Die mobile App braucht eine Lernstrecke für Kurse, Lektionen, Quiz, Fortschritt, Zertifikate und herunterladbare Nachweise.',
                  style: TextStyle(color: AirmiusColors.text, height: 1.45, fontWeight: FontWeight.w800),
                ),
                const SizedBox(height: 16),
                GridWrap(
                  children: const [
                    Metric(value: '4', label: 'Kurse'),
                    Metric(value: 'Quiz', label: 'Test'),
                    Metric(value: '80%', label: 'Fortschritt'),
                    Metric(value: 'PDF', label: 'Zertifikat'),
                  ],
                ),
              ],
            ),
          ),
          const SizedBox(height: 14),
          AirmiusPanel(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const SectionLabel('LERNPFAD'),
                const SizedBox(height: 12),
                SegmentedButton<String>(
                  segments: const [
                    ButtonSegment(value: 'User', label: Text('User')),
                    ButtonSegment(value: 'Verein', label: Text('Verein')),
                    ButtonSegment(value: 'Trainer', label: Text('Trainer')),
                    ButtonSegment(value: 'Admin', label: Text('Admin')),
                  ],
                  selected: {track},
                  onSelectionChanged: (value) => setState(() => track = value.first),
                ),
              ],
            ),
          ),
          const SizedBox(height: 14),
          AirmiusPanel(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const SectionLabel('OPTIONEN'),
                const SizedBox(height: 8),
                _LearningSwitch(title: 'Fortschritt anzeigen', value: showProgress, color: AirmiusColors.blue, onChanged: (value) => setState(() => showProgress = value)),
                _LearningSwitch(title: 'Quiz erforderlich', value: requireQuiz, color: AirmiusColors.green, onChanged: (value) => setState(() => requireQuiz = value)),
                _LearningSwitch(title: 'Zertifikat ausstellen', value: issueCertificate, color: AirmiusColors.amber, onChanged: (value) => setState(() => issueCertificate = value)),
                _LearningSwitch(title: 'Download erlauben', value: allowDownload, color: AirmiusColors.pink, onChanged: (value) => setState(() => allowDownload = value)),
              ],
            ),
          ),
          const SizedBox(height: 14),
          for (final course in courses) ...[
            _CourseCard(course: course),
            const SizedBox(height: 12),
          ],
          AirmiusPanel(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const SectionLabel('VORSCHAU'),
                const SizedBox(height: 8),
                Text(
                  'Aktueller Lernpfad: $track. Später verbindet die API Einschreibung, Lektionen, Quiz, Fortschritt, Zertifikate, Rollenrechte und Downloads.',
                  style: const TextStyle(color: AirmiusColors.muted, height: 1.45, fontWeight: FontWeight.w700),
                ),
                const SizedBox(height: 14),
                AirmiusButton(
                  label: 'Zertifikat vorbereiten',
                  icon: Icons.workspace_premium_outlined,
                  onPressed: () => openUiAction(
                    context,
                    title: 'Zertifikat vorbereiten',
                    body: 'Diese UI bereitet Kursabschluss, Zertifikate, Downloads, Rollenrechte und Nachweise für die spätere Laravel-API vor.',
                    status: 'UI vorbereitet',
                    icon: Icons.workspace_premium_outlined,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _CourseRow {
  const _CourseRow({
    required this.title,
    required this.status,
    required this.body,
    required this.icon,
    required this.color,
  });

  final String title;
  final String status;
  final String body;
  final IconData icon;
  final Color color;
}

class _LearningSwitch extends StatelessWidget {
  const _LearningSwitch({
    required this.title,
    required this.value,
    required this.color,
    required this.onChanged,
  });

  final String title;
  final bool value;
  final Color color;
  final ValueChanged<bool> onChanged;

  @override
  Widget build(BuildContext context) {
    return SwitchListTile.adaptive(
      contentPadding: EdgeInsets.zero,
      title: Text(title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
      value: value,
      activeColor: color,
      onChanged: onChanged,
    );
  }
}

class _CourseCard extends StatelessWidget {
  const _CourseCard({required this.course});

  final _CourseRow course;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              IconBadge(icon: course.icon, color: course.color),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Expanded(child: Text(course.title, style: const TextStyle(color: AirmiusColors.text, fontSize: 17, fontWeight: FontWeight.w900))),
                        StatusPill(course.status, color: course.color),
                      ],
                    ),
                    const SizedBox(height: 8),
                    Text(course.body, style: const TextStyle(color: AirmiusColors.muted, height: 1.42, fontWeight: FontWeight.w700)),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 14),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              AirmiusButton(
                label: 'Starten',
                icon: Icons.play_circle_outline,
                onPressed: () => openUiAction(
                  context,
                  title: 'Kurs starten',
                  body: 'Kursstart, Lektionen, Fortschritt, Quiz und Abschluss werden für die spätere API vorbereitet.',
                  status: 'UI vorbereitet',
                  icon: Icons.play_circle_outline,
                ),
              ),
              AirmiusButton(
                label: 'Quiz',
                icon: Icons.quiz_outlined,
                secondary: true,
                onPressed: () => openUiAction(
                  context,
                  title: 'Quiz öffnen',
                  body: 'Quizfragen, Bestehensgrenze, Wiederholung und Zertifikatslogik werden später per API gesteuert.',
                  status: 'UI vorbereitet',
                  icon: Icons.quiz_outlined,
                ),
              ),
              AirmiusButton(
                label: 'Download',
                icon: Icons.download_outlined,
                secondary: true,
                onPressed: () => openUiAction(
                  context,
                  title: 'Nachweis herunterladen',
                  body: 'Zertifikate und Kursnachweise können später als PDF exportiert und im Profil angezeigt werden.',
                  status: 'UI vorbereitet',
                  icon: Icons.download_outlined,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}
