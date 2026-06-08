import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'safety_community_operations_screen.dart';
import 'ui_action_result_screen.dart';
import 'user_profile_detail_screen.dart';

class CommunityCenterScreen extends StatefulWidget {
  const CommunityCenterScreen({super.key});

  @override
  State<CommunityCenterScreen> createState() => _CommunityCenterScreenState();
}

class _CommunityCenterScreenState extends State<CommunityCenterScreen> {
  String _filter = 'Alle';

  @override
  Widget build(BuildContext context) {
    final people = _people.where((person) => _filter == 'Alle' || person.status == _filter).toList();
    return Scaffold(
      appBar: AppBar(
        backgroundColor: AirmiusColors.header,
        surfaceTintColor: Colors.transparent,
        title: const Text('Freunde', style: TextStyle(fontWeight: FontWeight.w900)),
      ),
      body: PageFrame(
        title: 'Freunde',
        subtitle: 'Kontakte, Einladungen, Empfehlungen und gemeinsame Vereine',
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            AirmiusPanel(
              gradient: true,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Eyebrow('Community'),
                  const SizedBox(height: 8),
                  const Text('Freundschaften, Einladungen, Empfehlungen und sichere Community-Aktionen.', style: TextStyle(color: AirmiusColors.muted, height: 1.35)),
                  const SizedBox(height: 14),
                  SearchBox(hint: 'Person oder Verein suchen', onChanged: (_) {}),
                ],
              ),
            ),
            const SizedBox(height: 14),
            Row(children: const [Expanded(child: MetricCard(value: '18', label: 'Freunde')), SizedBox(width: 10), Expanded(child: MetricCard(value: '2', label: 'Offen')), SizedBox(width: 10), Expanded(child: MetricCard(value: '4', label: 'Matches'))]),
            const SizedBox(height: 14),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: [
                for (final filter in const ['Alle', 'Freund', 'Offen', 'Empfehlung'])
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
            for (final person in people) ...[
              _PersonCard(person: person),
              const SizedBox(height: 12),
            ],
            const SizedBox(height: 4),
            Wrap(spacing: 10, runSpacing: 10, children: [
              AirmiusButton(label: 'Einladungslink erstellen', icon: Icons.link_outlined, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => UiActionResultScreen(title: 'Einladungslink erstellen', body: 'Freundschaftslink, Ablaufdatum, Sichtbarkeit und Missbrauchsschutz vorbereiten.', status: 'Invite', icon: Icons.link_outlined)))),
              AirmiusButton(label: 'Token annehmen', icon: Icons.mark_email_read_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => UiActionResultScreen(title: 'Freundschaftstoken annehmen', body: 'Invitation-Token aus Link pruefen, Kontakt bestaetigen und Sichtbarkeit anwenden.', status: 'Token', icon: Icons.mark_email_read_outlined)))),
              AirmiusButton(label: 'Safety Ops', icon: Icons.health_and_safety_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => SafetyCommunityOperationsScreen()))),
              AirmiusButton(label: 'Freund entfernen', icon: Icons.person_remove_outlined, danger: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => UiActionResultScreen(title: 'Freund entfernen', body: 'Kontakt entfernen, gemeinsame Sichtbarkeit aktualisieren und Chat-Kontext pruefen.', status: 'Remove', icon: Icons.person_remove_outlined)))),
            ]),
          ],
        ),
      ),
    );
  }
}

class _PersonCard extends StatelessWidget {
  const _PersonCard({required this.person});

  final _Person person;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => UserProfileDetailScreen(name: person.name, body: person.body, status: person.status, context: person.context))),
      borderColor: person.status == 'Offen' ? AirmiusColors.green.withValues(alpha: 0.45) : AirmiusColors.border,
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          AirmiusAvatar(person.name),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(person.name, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900, fontSize: 17)),
                const SizedBox(height: 4),
                Text(person.body, style: const TextStyle(color: AirmiusColors.muted, height: 1.3)),
                const SizedBox(height: 9),
                Wrap(spacing: 8, runSpacing: 8, children: [StatusPill(person.status), StatusPill(person.context)]),
                if (person.status == 'Offen') ...[
                  const SizedBox(height: 10),
                  Wrap(spacing: 10, runSpacing: 10, children: [
                    AirmiusButton(label: 'Annehmen', icon: Icons.check_circle_outline, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => UiActionResultScreen(title: 'Freundschaft annehmen', body: '${person.name} als Kontakt bestaetigen und gemeinsame Vereine sichtbar machen.', status: 'Freund', icon: Icons.check_circle_outline)))),
                    AirmiusButton(label: 'Ablehnen', icon: Icons.close_outlined, danger: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => UiActionResultScreen(title: 'Freundschaft ablehnen', body: 'Anfrage von ${person.name} ablehnen und optional ausblenden.', status: 'Abgelehnt', icon: Icons.close_outlined)))),
                  ]),
                ],
              ],
            ),
          ),
          const Icon(Icons.chevron_right, color: AirmiusColors.muted),
        ],
      ),
    );
  }
}

class _Person {
  const _Person({required this.name, required this.body, required this.status, required this.context});

  final String name;
  final String body;
  final String status;
  final String context;
}

const _people = [
  _Person(name: 'Max Mustermann', body: 'Gemeinsamer Verein: Airmius Running Club', status: 'Offen', context: 'Verein'),
  _Person(name: 'verein airmius', body: 'Admin-Kontakt und Vereinsmanagement', status: 'Freund', context: 'Admin'),
  _Person(name: 'Trainer Team', body: 'Gemeinsames Training und Eventchat', status: 'Freund', context: 'Team'),
  _Person(name: 'Tennis Kontakt', body: 'Empfohlen ueber Tennis Zentrum West', status: 'Empfehlung', context: 'Match'),
];


