import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

class ProfileSkillRecommendationScreen extends StatefulWidget {
  const ProfileSkillRecommendationScreen({
    super.key,
    this.person = 'ZBB Konto',
    this.skill = 'Ausdauer',
    this.status = 'Offen',
  });

  final String person;
  final String skill;
  final String status;

  @override
  State<ProfileSkillRecommendationScreen> createState() => _ProfileSkillRecommendationScreenState();
}

class _ProfileSkillRecommendationScreenState extends State<ProfileSkillRecommendationScreen> {
  String _skillLevel = 'Fortgeschritten';
  bool _visible = true;
  bool _notify = true;
  bool _verifiedClubContext = true;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(backgroundColor: AirmiusColors.header, surfaceTintColor: Colors.transparent, title: const Text('Skills & Empfehlungen', style: TextStyle(fontWeight: FontWeight.w900))),
      body: PageFrame(
        title: widget.skill,
        subtitle: '${widget.person} - Skill, Endorsement, Empfehlung und Freigabe',
        trailing: StatusPill(widget.status, color: widget.status == 'Offen' ? AirmiusColors.amber : AirmiusColors.blue),
        child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          AirmiusPanel(gradient: true, child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            const Eyebrow('Profile Gamification'),
            const SizedBox(height: 8),
            Text('${widget.person} kann Skills pflegen, Empfehlungen erhalten und Endorsements freigeben.', style: const TextStyle(color: AirmiusColors.muted, height: 1.35)),
            const SizedBox(height: 14),
            DropdownButtonFormField<String>(
              initialValue: _skillLevel,
              dropdownColor: AirmiusColors.cardSoft,
              decoration: const InputDecoration(labelText: 'Skill-Level'),
              items: const ['Einsteiger', 'Fortgeschritten', 'Experte', 'Trainer'].map((item) => DropdownMenuItem(value: item, child: Text(item))).toList(),
              onChanged: (value) => setState(() => _skillLevel = value ?? _skillLevel),
            ),
          ])),
          const SizedBox(height: 14),
          Row(children: const [
            Expanded(child: MetricCard(value: '7', label: 'Endorse')),
            SizedBox(width: 10),
            Expanded(child: MetricCard(value: '2', label: 'Empf.')),
            SizedBox(width: 10),
            Expanded(child: MetricCard(value: '82%', label: 'Profil')),
          ]),
          const SizedBox(height: 14),
          AirmiusPanel(child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            const Eyebrow('Skill-Freigabe'),
            SwitchListTile(value: _visible, onChanged: (value) => setState(() => _visible = value), activeThumbColor: AirmiusColors.blue, contentPadding: EdgeInsets.zero, title: const Text('Im Profil anzeigen', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), subtitle: const Text('Skill erscheint je nach Sichtbarkeit im Profil.', style: TextStyle(color: AirmiusColors.muted))),
            SwitchListTile(value: _verifiedClubContext, onChanged: (value) => setState(() => _verifiedClubContext = value), activeThumbColor: AirmiusColors.green, contentPadding: EdgeInsets.zero, title: const Text('Vereinskontext verifiziert', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), subtitle: const Text('Endorsement stammt aus Verein, Team oder Training.', style: TextStyle(color: AirmiusColors.muted))),
            SwitchListTile(value: _notify, onChanged: (value) => setState(() => _notify = value), activeThumbColor: AirmiusColors.amber, contentPadding: EdgeInsets.zero, title: const Text('Benachrichtigen', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), subtitle: const Text('Person oder Empfehlungsgeber über Status informieren.', style: TextStyle(color: AirmiusColors.muted))),
          ])),
          const SizedBox(height: 14),
          const AirmiusPanel(child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            Eyebrow('Empfehlungen'),
            SizedBox(height: 10),
            _SkillLine(icon: Icons.person_add_alt_1_outlined, title: 'Trainer Empfehlung', body: 'Trainer Team empfiehlt Ausdauer für Vereinsprofil.', status: 'Offen'),
            _SkillLine(icon: Icons.verified_outlined, title: 'Endorsement', body: 'Airmius Running Club hat den Skill bestätigt.', status: 'Verified'),
            _SkillLine(icon: Icons.history_outlined, title: 'Historie', body: 'Skilllevel, Freigaben und Ablehnungen werden auditierbar.', status: 'Audit'),
          ])),
          const SizedBox(height: 14),
          Wrap(spacing: 10, runSpacing: 10, children: [
            AirmiusButton(label: 'Skill speichern', icon: Icons.save_outlined, onPressed: () => openUiAction(context, title: 'Skill speichern', body: 'Sport-Skill, Level, Sichtbarkeit und Vereinskontext speichern.', status: 'Skill', icon: Icons.save_outlined)),
            AirmiusButton(label: 'Endorse geben', icon: Icons.thumb_up_outlined, secondary: true, onPressed: () => openUiAction(context, title: 'Endorsement geben', body: 'Skill-Endorsement für ${widget.person} vorbereiten und Benachrichtigung ausloesen.', status: 'Endorse', icon: Icons.thumb_up_outlined)),
            AirmiusButton(label: 'Empfehlung senden', icon: Icons.rate_review_outlined, secondary: true, onPressed: () => openUiAction(context, title: 'Empfehlung senden', body: 'Profilempfehlung für ${widget.person} mit Text, Sichtbarkeit und Freigabe vorbereiten.', status: 'Recommend', icon: Icons.rate_review_outlined)),
            AirmiusButton(label: 'Empfehlung genehmigen', icon: Icons.check_circle_outline, secondary: true, onPressed: () => openUiAction(context, title: 'Empfehlung genehmigen', body: 'Offene Empfehlung freigeben und im Profil sichtbar machen.', status: 'Approve', icon: Icons.check_circle_outline)),
            AirmiusButton(label: 'Empfehlung ablehnen', icon: Icons.close_outlined, danger: true, onPressed: () => openUiAction(context, title: 'Empfehlung ablehnen', body: 'Empfehlung ablehnen, optional Grund speichern und Absender informieren.', status: 'Reject', icon: Icons.close_outlined)),
          ]),
        ]),
      ),
    );
  }
}

class _SkillLine extends StatelessWidget {
  const _SkillLine({required this.icon, required this.title, required this.body, required this.status});

  final IconData icon;
  final String title;
  final String body;
  final String status;

  @override
  Widget build(BuildContext context) {
    return Padding(padding: const EdgeInsets.only(top: 12), child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [Icon(icon, color: AirmiusColors.blue), const SizedBox(width: 12), Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text(title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), const SizedBox(height: 3), Text(body, style: const TextStyle(color: AirmiusColors.muted, height: 1.35))])), StatusPill(status)]));
  }
}
