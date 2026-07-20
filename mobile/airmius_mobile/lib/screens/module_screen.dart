import 'package:flutter/material.dart';

import '../core/airmius_l10n.dart';
import '../core/airmius_theme.dart';
import '../models/club_summary.dart';
import '../models/module_definition.dart';
import '../widgets/airmius_widgets.dart';
import 'module_sections.dart';

class ModuleScreen extends StatelessWidget {
  const ModuleScreen({super.key, required this.module, this.requestedClubIds = const {}, this.onWithdrawClub});

  final ModuleDefinition module;
  final Set<int> requestedClubIds;
  final ValueChanged<ClubSummary>? onWithdrawClub;

  @override
  Widget build(BuildContext context) {
    final scope = AirmiusScope.of(context);
    final moduleTitle = scope.copy(module.title);
    final copiedSubtitle = scope.copy(module.subtitle);
    final moduleSubtitle = copiedSubtitle == module.subtitle && scope.language != AirmiusLanguage.de ? scope.t('module.defaultSubtitle') : copiedSubtitle;
    final requestedClubs = demoClubs.where((club) => requestedClubIds.contains(club.id)).toList();

    return PageFrame(
      title: moduleTitle,
      subtitle: moduleSubtitle,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          AirmiusPanel(
            gradient: true,
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Container(
                  width: 58,
                  height: 58,
                  decoration: BoxDecoration(
                    color: AirmiusColors.blue.withValues(alpha: 0.16),
                    borderRadius: BorderRadius.circular(18),
                    border: Border.all(color: AirmiusColors.blue.withValues(alpha: 0.35)),
                  ),
                  child: Icon(module.icon, color: AirmiusColors.blue, size: 28),
                ),
                const SizedBox(width: 14),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Eyebrow('Airmius Modul'),
                      const SizedBox(height: 6),
                      Text(moduleTitle, style: const TextStyle(color: AirmiusColors.text, fontSize: 23, fontWeight: FontWeight.w900)),
                      const SizedBox(height: 6),
                      Text(moduleSubtitle, style: const TextStyle(color: AirmiusColors.muted, height: 1.35)),
                    ],
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 14),
          Row(
            children: [
              for (final entry in module.metrics.entries.take(2)) ...[
                Expanded(child: MetricCard(value: entry.value, label: entry.key)),
                if (entry.key != module.metrics.keys.take(2).last) const SizedBox(width: 10),
              ],
            ],
          ),
          const SizedBox(height: 14),
          if (module.title == 'Nachrichten' || module.title == 'Einstellungen') ...[
            _RequestsPanel(requestedClubs: requestedClubs, onWithdrawClub: onWithdrawClub),
            const SizedBox(height: 14),
          ],
          ModuleSpecificSection(module: module, requestedClubIds: requestedClubIds, onWithdrawClub: onWithdrawClub),
          const SizedBox(height: 14),
          AirmiusPanel(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                const Eyebrow('Funktionen'),
                const SizedBox(height: 8),
                for (final action in module.actions) ...[
                  _ActionRow(title: scope.copy(action), body: _bodyFor(action, scope), icon: module.icon),
                  const SizedBox(height: 10),
                ],
              ],
            ),
          ),
          const SizedBox(height: 14),
          AirmiusPanel(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                const Eyebrow('API-Anbindung'),
                const SizedBox(height: 8),
                const Text('Dieser Screen ist als native UI vorbereitet. Die echten Daten werden später aus Laravel /api/v1 geladen und Aktionen werden dort gespeichert.', style: TextStyle(color: AirmiusColors.muted, height: 1.35)),
                const SizedBox(height: 12),
                AirmiusButton(label: 'Aktualisieren', icon: Icons.refresh_outlined, secondary: true, onPressed: () => openUiAction(context, title: 'Aktualisieren', body: 'Diese Aktion ist in der Mobile-App vorbereitet und wird später über die Laravel-API synchronisiert.', status: 'UI bereit', icon: Icons.refresh_outlined)),
              ],
            ),
          ),
        ],
      ),
    );
  }

  String _bodyFor(String action, AirmiusScope scope) {
    if (action.contains('hochladen')) return 'Upload-UI ist vorbereitet und wird mit File Picker plus Laravel Upload-API verbunden.';
    if (action.contains('suchen') || action.contains('Suche')) return 'Suchfelder und Ergebnislisten folgen dem mobilen Web-App-Muster.';
    if (action.contains('senden')) return 'Formular- und Statuslogik wird später über API gespeichert.';
    return 'Native Oberflaeche für diese Funktion, passend zur Web-App-Struktur.';
  }
}

class _ActionRow extends StatelessWidget {
  const _ActionRow({required this.title, required this.body, required this.icon});

  final String title;
  final String body;
  final IconData icon;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: () => openUiAction(context, title: title, body: body, status: 'Funktion', icon: icon),
      borderRadius: BorderRadius.circular(15),
      child: Container(
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(
          color: AirmiusColors.cardSoft,
          borderRadius: BorderRadius.circular(15),
          border: Border.all(color: AirmiusColors.border),
        ),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Icon(icon, color: AirmiusColors.blue, size: 21),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
                  const SizedBox(height: 3),
                  Text(body, style: const TextStyle(color: AirmiusColors.muted, height: 1.3)),
                ],
              ),
            ),
            const Icon(Icons.chevron_right, color: AirmiusColors.muted),
          ],
        ),
      ),
    );
  }
}

class _RequestsPanel extends StatelessWidget {
  const _RequestsPanel({required this.requestedClubs, required this.onWithdrawClub});

  final List<ClubSummary> requestedClubs;
  final ValueChanged<ClubSummary>? onWithdrawClub;

  @override
  Widget build(BuildContext context) {
    final scope = AirmiusScope.of(context);
    return AirmiusPanel(
      borderColor: requestedClubs.isEmpty ? AirmiusColors.border : AirmiusColors.green.withValues(alpha: 0.50),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const Eyebrow('Mitgliedschaftsanfragen'),
          if (requestedClubs.isEmpty)
            const Padding(padding: EdgeInsets.only(top: 12), child: Text('Noch keine offenen Anfragen.', style: TextStyle(color: AirmiusColors.muted))),
          for (final club in requestedClubs) ...[
            const SizedBox(height: 14),
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                AirmiusAvatar(club.name),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(club.name, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
                      Text('${club.city} - ${scope.t('sent')}', style: const TextStyle(color: AirmiusColors.muted, fontSize: 12)),
                      const SizedBox(height: 10),
                      AirmiusButton(label: scope.t('withdraw'), icon: Icons.undo_outlined, danger: true, onPressed: onWithdrawClub == null ? null : () async {
                        final ok = await confirmDanger(context, 'Anfrage zurückziehen', 'Moechtest du deine Anfrage bei ${club.name} wirklich zurückziehen?');
                        if (ok) onWithdrawClub!(club);
                      }),
                    ],
                  ),
                ),
              ],
            ),
          ],
        ],
      ),
    );
  }
}
