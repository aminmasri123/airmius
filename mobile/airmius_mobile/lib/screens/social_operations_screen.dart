import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'ui_action_result_screen.dart';

class SocialOperationsScreen extends StatefulWidget {
  const SocialOperationsScreen({super.key});

  @override
  State<SocialOperationsScreen> createState() => _SocialOperationsScreenState();
}

class _SocialOperationsScreenState extends State<SocialOperationsScreen> {
  String _tab = 'Stories';
  bool _guardianCheck = true;
  bool _notifyAuthor = true;

  @override
  Widget build(BuildContext context) {
    final items = _items.where((item) => _tab == 'Alle' || item.tab == _tab).toList();
    return Scaffold(
      appBar: AppBar(backgroundColor: AirmiusColors.header, surfaceTintColor: Colors.transparent, title: const Text('Social Operations', style: TextStyle(fontWeight: FontWeight.w900))),
      body: PageFrame(
        title: 'Social Operations',
        subtitle: 'Feed, Stories, Kommentare, Reaktionen, Medienfreigaben und Moderation',
        trailing: StatusPill(_tab),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            AirmiusPanel(
              gradient: true,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Eyebrow('Social Web-App Funktionen'),
                  const SizedBox(height: 8),
                  const Text('Mobile Umsetzung fuer Feed- und Story-Randfaelle: Story viewed/react/delete, Kommentarfreigabe, Reaktionen, Medienrechte, Reports und Maturity-Discovery.', style: TextStyle(color: AirmiusColors.muted, height: 1.35)),
                  const SizedBox(height: 14),
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: [
                      for (final tab in const ['Stories', 'Feed', 'Kommentare', 'Moderation', 'Alle'])
                        ChoiceChip(
                          selected: _tab == tab,
                          label: Text(tab),
                          onSelected: (_) => setState(() => _tab = tab),
                          selectedColor: AirmiusColors.blue.withValues(alpha: 0.22),
                          backgroundColor: AirmiusColors.cardSoft,
                          side: BorderSide(color: _tab == tab ? AirmiusColors.blue : AirmiusColors.border),
                          labelStyle: TextStyle(color: _tab == tab ? AirmiusColors.blue : AirmiusColors.muted, fontWeight: FontWeight.w900),
                        ),
                    ],
                  ),
                ],
              ),
            ),
            const SizedBox(height: 14),
            Row(children: const [Expanded(child: MetricCard(value: '12', label: 'Posts')), SizedBox(width: 10), Expanded(child: MetricCard(value: '4', label: 'Stories')), SizedBox(width: 10), Expanded(child: MetricCard(value: '2', label: 'Reports'))]),
            const SizedBox(height: 14),
            AirmiusPanel(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Eyebrow('Sicherheitsregeln'),
                  SwitchListTile(value: _guardianCheck, onChanged: (value) => setState(() => _guardianCheck = value), activeColor: AirmiusColors.amber, contentPadding: EdgeInsets.zero, title: const Text('Guardian/Medienrechte pruefen', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), subtitle: const Text('Stories und Medien mit Minderjaehrigen nur mit passenden Freigaben.', style: TextStyle(color: AirmiusColors.muted))),
                  SwitchListTile(value: _notifyAuthor, onChanged: (value) => setState(() => _notifyAuthor = value), activeColor: AirmiusColors.blue, contentPadding: EdgeInsets.zero, title: const Text('Autor informieren', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), subtitle: const Text('Reaktion, Kommentar, Report oder Moderationsentscheidung erzeugt spaeter eine Benachrichtigung.', style: TextStyle(color: AirmiusColors.muted))),
                ],
              ),
            ),
            const SizedBox(height: 14),
            for (final item in items) ...[
              _SocialOperationCard(item: item),
              const SizedBox(height: 12),
            ],
          ],
        ),
      ),
    );
  }
}

class _SocialOperationCard extends StatelessWidget {
  const _SocialOperationCard({required this.item});

  final _SocialOperation item;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      borderColor: item.danger ? AirmiusColors.red.withValues(alpha: 0.45) : AirmiusColors.border,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Icon(item.icon, color: item.danger ? AirmiusColors.red : AirmiusColors.blue, size: 28),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(item.title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
                    const SizedBox(height: 4),
                    Text(item.body, style: const TextStyle(color: AirmiusColors.muted, height: 1.35)),
                    const SizedBox(height: 9),
                    Wrap(spacing: 8, runSpacing: 8, children: [StatusPill(item.tab), StatusPill(item.status, color: item.danger ? AirmiusColors.red : AirmiusColors.blue)]),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          Wrap(
            spacing: 10,
            runSpacing: 10,
            children: [
              AirmiusButton(label: item.action, icon: item.icon, danger: item.danger, secondary: !item.danger, onPressed: () => _run(context, item)),
              AirmiusButton(label: 'Kontext anzeigen', icon: Icons.manage_search_outlined, secondary: true, onPressed: () => openUiAction(context, title: '${item.title} Kontext', body: 'Autor, Verein, Team, Sichtbarkeit, Maturity und Medienrechte fuer ${item.title} anzeigen.', status: 'Context', icon: Icons.manage_search_outlined)),
            ],
          ),
        ],
      ),
    );
  }

  void _run(BuildContext context, _SocialOperation item) {
    final action = () => openUiAction(context, title: item.action, body: '${item.action}: ${item.body}', status: item.status, icon: item.icon);
    if (item.danger) {
      confirmDanger(context, '${item.action}?', 'Diese Social-Aktion kann Inhalte entfernen, Sichtbarkeit aendern oder Moderation ausloesen.', item.action, action);
      return;
    }
    action();
  }
}

class _SocialOperation {
  const _SocialOperation({required this.tab, required this.title, required this.body, required this.status, required this.icon, required this.action, this.danger = false});

  final String tab;
  final String title;
  final String body;
  final String status;
  final IconData icon;
  final String action;
  final bool danger;
}

const _items = [
  _SocialOperation(tab: 'Stories', title: 'Story erstellen', body: 'Story-Medium, Ablaufzeit, Sichtbarkeit, Team/Verein und Medienrechte vorbereiten.', status: 'Create', icon: Icons.auto_stories_outlined, action: 'Story speichern'),
  _SocialOperation(tab: 'Stories', title: 'Story als angesehen markieren', body: 'Viewed-State speichern, Story-Fortschritt aktualisieren und Badge-Zaehler reduzieren.', status: 'Viewed', icon: Icons.visibility_outlined, action: 'Viewed senden'),
  _SocialOperation(tab: 'Stories', title: 'Story-Reaktion senden', body: 'Story-Reaktion speichern und Autor benachrichtigen.', status: 'React', icon: Icons.add_reaction_outlined, action: 'Reaktion senden'),
  _SocialOperation(tab: 'Stories', title: 'Story loeschen', body: 'Eigene oder moderierte Story entfernen und Medienfreigabe protokollieren.', status: 'Delete', icon: Icons.delete_outline, action: 'Story loeschen', danger: true),
  _SocialOperation(tab: 'Feed', title: 'Feed-Beitrag erstellen', body: 'Post, Sichtbarkeit, Medien, Maturity und Verein/Team-Kontext speichern.', status: 'Post', icon: Icons.dynamic_feed_outlined, action: 'Post speichern'),
  _SocialOperation(tab: 'Feed', title: 'Discovery aktualisieren', body: 'Feed Discovery und Trending nach Alter, Sichtbarkeit, Verein und Interessen darstellen.', status: 'Discovery', icon: Icons.explore_outlined, action: 'Discovery laden'),
  _SocialOperation(tab: 'Feed', title: 'Feed-Beitrag melden', body: 'Report mit Grund, Autor, Medium, Kommentar und Zielkontext vorbereiten.', status: 'Report', icon: Icons.flag_outlined, action: 'Report senden', danger: true),
  _SocialOperation(tab: 'Kommentare', title: 'Kommentar schreiben', body: 'Kommentar speichern, Thread aktualisieren und Autor benachrichtigen.', status: 'Comment', icon: Icons.mode_comment_outlined, action: 'Kommentar senden'),
  _SocialOperation(tab: 'Kommentare', title: 'Kommentarbaum moderieren', body: 'Antworten, versteckte Kommentare, Reports und Sichtbarkeit im Thread pruefen.', status: 'Thread', icon: Icons.forum_outlined, action: 'Thread pruefen'),
  _SocialOperation(tab: 'Kommentare', title: 'Kommentar melden', body: 'Kommentar-Report mit Kontext und Moderationsgrund erstellen.', status: 'Report', icon: Icons.report_outlined, action: 'Kommentar melden', danger: true),
  _SocialOperation(tab: 'Moderation', title: 'Moderationsflag aktualisieren', body: 'Flag-Status, Entscheidung, Notiz und Benachrichtigung speichern.', status: 'Flag', icon: Icons.flag_outlined, action: 'Flag speichern'),
  _SocialOperation(tab: 'Moderation', title: 'Report schliessen', body: 'Report-Entscheidung, Audit und betroffene Inhalte abschliessen.', status: 'Closed', icon: Icons.task_alt_outlined, action: 'Report schliessen'),
  _SocialOperation(tab: 'Moderation', title: 'Medienfreigabe widerrufen', body: 'Bildrechte, Guardian Consent oder Public-Freigabe fuer Medium entfernen.', status: 'Media', icon: Icons.visibility_off_outlined, action: 'Freigabe widerrufen', danger: true),
];
