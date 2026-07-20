import 'package:flutter/material.dart';
import 'sports_operations_screen.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'profile_skill_recommendation_screen.dart';
import 'wellbeing_operations_screen.dart';

class SportProfileDetailScreen extends StatefulWidget {
  const SportProfileDetailScreen({super.key, required this.title, required this.status});

  final String title;
  final String status;

  @override
  State<SportProfileDetailScreen> createState() => _SportProfileDetailScreenState();
}

class _SportProfileDetailScreenState extends State<SportProfileDetailScreen> {
  String _level = 'Fortgeschritten';
  String _goal = 'Ausdauer verbessern';
  bool _useForAi = true;
  bool _shareWithCoach = true;
  bool _medicalNote = false;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
        floatingActionButton: FloatingActionButton.extended(backgroundColor: const Color(0xFF1D5FA8), foregroundColor: Colors.white, icon: const Icon(Icons.sports_outlined), label: const Text('Profil Ops', style: TextStyle(fontWeight: FontWeight.w900)), onPressed: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => SportsOperationsScreen(initialTab: 'Leistung')))),
        
      appBar: AppBar(backgroundColor: AirmiusColors.header, surfaceTintColor: Colors.transparent, title: const Text('Sportprofil', style: TextStyle(fontWeight: FontWeight.w900))),
      body: PageFrame(
        title: widget.title,
        subtitle: 'Disziplinen, Leistungsdaten, Ziele und KI-Readiness',
        trailing: StatusPill(widget.status),
        child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          AirmiusPanel(gradient: true, child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            const Eyebrow('Profilbasis'),
            const SizedBox(height: 12),
            DropdownButtonFormField<String>(
              initialValue: _level,
              dropdownColor: AirmiusColors.card,
              decoration: _fieldDecoration('Erfahrung'),
              items: const ['Einsteiger', 'Fortgeschritten', 'Leistungssport', 'Trainer'].map((item) => DropdownMenuItem(value: item, child: Text(item))).toList(),
              onChanged: (value) => setState(() => _level = value ?? _level),
            ),
            const SizedBox(height: 10),
            DropdownButtonFormField<String>(
              initialValue: _goal,
              dropdownColor: AirmiusColors.card,
              decoration: _fieldDecoration('Hauptziel'),
              items: const ['Ausdauer verbessern', 'Wettkampf vorbereiten', 'Kraft aufbauen', 'Gesund bleiben'].map((item) => DropdownMenuItem(value: item, child: Text(item))).toList(),
              onChanged: (value) => setState(() => _goal = value ?? _goal),
            ),
          ])),
          const SizedBox(height: 14),
          Row(children: const [Expanded(child: MetricCard(value: '82%', label: 'Readiness')), SizedBox(width: 10), Expanded(child: MetricCard(value: '4', label: 'Daten')), SizedBox(width: 10), Expanded(child: MetricCard(value: '2', label: 'Luecken'))]),
          const SizedBox(height: 14),
          AirmiusPanel(child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: const [
            Eyebrow('Leistungswerte'),
            SizedBox(height: 12),
            AirmiusTextField(label: 'Wochenumfang', hint: 'z. B. 4 Stunden oder 35 km', icon: Icons.calendar_view_week_outlined),
            SizedBox(height: 10),
            AirmiusTextField(label: 'Bestwert / Referenz', hint: 'z. B. 10 km in 48:20', icon: Icons.emoji_events_outlined),
            SizedBox(height: 10),
            AirmiusTextField(label: 'Pulsbereiche', hint: 'z. B. Ruhepuls, Maxpuls, Zone 2', icon: Icons.monitor_heart_outlined),
          ])),
          const SizedBox(height: 14),
          AirmiusPanel(child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            const Eyebrow('KI-Coach & Freigaben'),
            const SizedBox(height: 8),
            SwitchListTile(value: _useForAi, onChanged: (value) => setState(() => _useForAi = value), activeThumbColor: AirmiusColors.blue, contentPadding: EdgeInsets.zero, title: const Text('Für KI-Trainingsplan verwenden', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), subtitle: const Text('Profilwerte dürfen in Planvorschläge einfließen.', style: TextStyle(color: AirmiusColors.muted))),
            SwitchListTile(value: _shareWithCoach, onChanged: (value) => setState(() => _shareWithCoach = value), activeThumbColor: AirmiusColors.blue, contentPadding: EdgeInsets.zero, title: const Text('Mit Trainer teilen', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), subtitle: const Text('Coach sieht Leistungswerte, Ziele und Readiness.', style: TextStyle(color: AirmiusColors.muted))),
            SwitchListTile(value: _medicalNote, onChanged: (value) => setState(() => _medicalNote = value), activeThumbColor: AirmiusColors.amber, contentPadding: EdgeInsets.zero, title: const Text('Gesundheitshinweis vorhanden', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), subtitle: const Text('Hinweis nur für berechtigte Trainer sichtbar.', style: TextStyle(color: AirmiusColors.muted))),
          ])),
          const SizedBox(height: 14),
          AirmiusPanel(borderColor: AirmiusColors.amber.withValues(alpha: 0.55), child: const Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Eyebrow('Datenluecken'),
            SizedBox(height: 8),
            Text('Für vollstaendige KI-Readiness fehlen noch Maxpuls und aktueller Wochenumfang. Die App zeigt diese Luecken vor Planerstellung an.', style: TextStyle(color: AirmiusColors.muted, height: 1.35)),
            SizedBox(height: 10),
            Wrap(spacing: 8, runSpacing: 8, children: [StatusPill('Maxpuls fehlt'), StatusPill('Wochenziel offen'), StatusPill('Coach OK')]),
          ])),
          const SizedBox(height: 14),
          Wrap(spacing: 10, runSpacing: 10, children: [
            AirmiusButton(label: 'Sportprofil speichern', icon: Icons.save_outlined, onPressed: () => openUiAction(context, title: 'Sportprofil speichern', body: 'Diese Aktion ist in der Mobile-App vorbereitet und wird später über die Laravel-API synchronisiert.', status: 'UI bereit', icon: Icons.save_outlined)),
            AirmiusButton(label: 'Skills & Empfehlungen', icon: Icons.thumb_up_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => ProfileSkillRecommendationScreen(skill: widget.title, status: 'Skill')))),
            AirmiusButton(label: 'Wellbeing Ops', icon: Icons.tune_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => WellbeingOperationsScreen()))),
          ]),
        ]),
      ),
    );
  }

  InputDecoration _fieldDecoration(String label) {
    return InputDecoration(
      labelText: label,
      labelStyle: const TextStyle(color: AirmiusColors.muted, fontWeight: FontWeight.w800),
      filled: true,
      fillColor: AirmiusColors.input,
      border: OutlineInputBorder(borderRadius: BorderRadius.circular(14), borderSide: const BorderSide(color: AirmiusColors.border)),
      enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(14), borderSide: const BorderSide(color: AirmiusColors.border)),
      focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(14), borderSide: const BorderSide(color: AirmiusColors.blue, width: 1.4)),
    );
  }
}

