import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'new_conversation_screen.dart';
import 'profile_skill_recommendation_screen.dart';
import 'ui_action_result_screen.dart';

class UserProfileDetailScreen extends StatefulWidget {
  const UserProfileDetailScreen({super.key, required this.name, required this.body, required this.status, required this.context, this.ownProfile = false});

  final String name;
  final String body;
  final String status;
  final String context;
  final bool ownProfile;

  @override
  State<UserProfileDetailScreen> createState() => _UserProfileDetailScreenState();
}

class _UserProfileDetailScreenState extends State<UserProfileDetailScreen> {
  String _visibility = 'Freunde';
  bool _shareRecommendations = true;
  bool _blocked = false;
  bool _reported = false;

  @override
  Widget build(BuildContext context) {
    final recommendation = widget.status == 'Empfehlung';
    final pending = widget.status == 'Offen';
    return Scaffold(
      appBar: AppBar(backgroundColor: AirmiusColors.header, surfaceTintColor: Colors.transparent, title: Text(widget.name, style: const TextStyle(fontWeight: FontWeight.w900))),
      body: PageFrame(
        title: widget.name,
        subtitle: widget.body,
        trailing: StatusPill(widget.status, color: pending ? AirmiusColors.amber : AirmiusColors.blue),
        child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          AirmiusPanel(gradient: true, borderColor: _blocked ? AirmiusColors.red.withValues(alpha: 0.45) : null, child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
            AirmiusAvatar(widget.name, large: true),
            const SizedBox(width: 14),
            Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Eyebrow(widget.context),
              const SizedBox(height: 6),
              Text(widget.name, style: const TextStyle(color: AirmiusColors.text, fontSize: 24, fontWeight: FontWeight.w900)),
              const SizedBox(height: 4),
              Text(widget.body, style: const TextStyle(color: AirmiusColors.muted, height: 1.35)),
              const SizedBox(height: 12),
              Wrap(spacing: 8, runSpacing: 8, children: [StatusPill(widget.status), StatusPill(widget.context), if (_blocked) const StatusPill('Blockiert', color: AirmiusColors.red), if (_reported) const StatusPill('Gemeldet', color: AirmiusColors.amber)]),
            ])),
          ])),
          const SizedBox(height: 14),
          Row(children: const [Expanded(child: MetricCard(value: '3', label: 'Vereine')), SizedBox(width: 10), Expanded(child: MetricCard(value: '9', label: 'Badges')), SizedBox(width: 10), Expanded(child: MetricCard(value: '82%', label: 'Profil'))]),
          const SizedBox(height: 14),
          AirmiusPanel(child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            const Eyebrow('Sichtbarkeit'),
            const SizedBox(height: 12),
            DropdownButtonFormField<String>(value: _visibility, dropdownColor: AirmiusColors.cardSoft, decoration: const InputDecoration(labelText: 'Profil sichtbar fuer'), items: const ['Privat', 'Freunde', 'Verein', 'Oeffentlich'].map((item) => DropdownMenuItem(value: item, child: Text(item))).toList(), onChanged: widget.ownProfile ? (value) => setState(() => _visibility = value ?? _visibility) : null),
            SwitchListTile(value: _shareRecommendations, onChanged: (value) => setState(() => _shareRecommendations = value), activeColor: AirmiusColors.blue, contentPadding: EdgeInsets.zero, title: const Text('Empfehlungen freigeben', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), subtitle: const Text('Gemeinsame Vereine und Trainingsvorschlaege sichtbar machen.', style: TextStyle(color: AirmiusColors.muted))),
          ])),
          const SizedBox(height: 14),
          AirmiusPanel(child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            const Eyebrow('Profilvorschau'),
            const SizedBox(height: 10),
            _ProfilePreviewLine(icon: Icons.directions_run_outlined, title: 'Sportprofil', body: 'Laufen, Fitness, Trainingsziel und Erfahrung.', onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => ProfileSkillRecommendationScreen(person: widget.name, skill: 'Ausdauer', status: 'Skill')))),
            const _ProfilePreviewLine(icon: Icons.groups_outlined, title: 'Gemeinsame Vereine', body: 'Airmius Running Club, ZBB oder Teamkontext.'),
            _ProfilePreviewLine(icon: Icons.workspace_premium_outlined, title: 'Badges & Empfehlungen', body: 'Starter, Teamplayer, Endorsements und offene Empfehlungen.', onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => ProfileSkillRecommendationScreen(person: widget.name, skill: 'Empfehlungen', status: 'Offen')))),
          ])),
          const SizedBox(height: 14),
          if (pending)
            Wrap(spacing: 10, runSpacing: 10, children: [
              AirmiusButton(label: 'Annehmen', icon: Icons.check_circle_outline, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => UiActionResultScreen(title: 'Anfrage annehmen', body: '${widget.name} als Kontakt bestaetigen und Sichtbarkeit aktualisieren.', status: 'Freund', icon: Icons.check_circle_outline)))),
              AirmiusButton(label: 'Ablehnen', icon: Icons.close_outlined, danger: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => UiActionResultScreen(title: 'Anfrage ablehnen', body: 'Anfrage von ${widget.name} ablehnen und optional ausblenden.', status: 'Abgelehnt', icon: Icons.close_outlined)))),
            ])
          else if (recommendation)
            Wrap(spacing: 10, runSpacing: 10, children: [
              AirmiusButton(label: 'Anfrage senden', icon: Icons.person_add_alt_1_outlined, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => UiActionResultScreen(title: 'Freundschaftsanfrage senden', body: 'Anfrage an ${widget.name} senden und Benachrichtigung vorbereiten.', status: 'Anfrage', icon: Icons.person_add_alt_1_outlined)))),
              AirmiusButton(label: 'Ausblenden', icon: Icons.visibility_off_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => UiActionResultScreen(title: 'Empfehlung ausblenden', body: '${widget.name} aus Empfehlungen entfernen und Regel aktualisieren.', status: 'Ausblenden', icon: Icons.visibility_off_outlined)))),
            ])
          else
            Wrap(spacing: 10, runSpacing: 10, children: [
              AirmiusButton(label: 'Nachricht', icon: Icons.chat_bubble_outline, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => NewConversationScreen()))),
              AirmiusButton(label: _blocked ? 'Entblocken' : 'Blockieren', icon: Icons.block_outlined, danger: !_blocked, secondary: _blocked, onPressed: () => setState(() => _blocked = !_blocked)),
              AirmiusButton(label: _reported ? 'Gemeldet' : 'Melden', icon: Icons.report_outlined, secondary: true, onPressed: () => setState(() => _reported = true)),
            ]),
        ]),
      ),
    );
  }
}

class _ProfilePreviewLine extends StatelessWidget {
  const _ProfilePreviewLine({required this.icon, required this.title, required this.body, this.onTap});

  final IconData icon;
  final String title;
  final String body;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(12),
      child: Padding(padding: const EdgeInsets.only(top: 12, bottom: 4), child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [Icon(icon, color: AirmiusColors.blue), const SizedBox(width: 12), Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text(title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), const SizedBox(height: 3), Text(body, style: const TextStyle(color: AirmiusColors.muted, height: 1.35))])), if (onTap != null) const Icon(Icons.chevron_right, color: AirmiusColors.muted, size: 20)])),
    );
  }
}
