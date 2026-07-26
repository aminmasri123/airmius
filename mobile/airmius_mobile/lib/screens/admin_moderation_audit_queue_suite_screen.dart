import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

class AdminModerationAuditQueueSuiteScreen extends StatefulWidget {
  const AdminModerationAuditQueueSuiteScreen({super.key});

  @override
  State<AdminModerationAuditQueueSuiteScreen> createState() =>
      _AdminModerationAuditQueueSuiteScreenState();
}

class _AdminModerationAuditQueueSuiteScreenState
    extends State<AdminModerationAuditQueueSuiteScreen> {
  String queue = 'Offen';
  bool showContentReports = true;
  bool showUserReports = true;
  bool showClubReviews = true;
  bool showAuditTrail = true;

  @override
  Widget build(BuildContext context) {
    final cases = [
      const _ModerationCase(
        title: 'Gemeldeter Feed-Beitrag',
        area: 'Community',
        status: 'Offen',
        body:
            'Beitrag wurde wegen unangemessenem Inhalt gemeldet. Moderator sieht Kontext, Reporter und Verlauf.',
        color: AirmiusColors.pink,
      ),
      const _ModerationCase(
        title: 'Nachricht gemeldet',
        area: 'Messages',
        status: 'Prüfung',
        body:
            'Gemeldete Nachricht mit Chatkontext, blockiertem Kontakt und Supportverweis.',
        color: AirmiusColors.blue,
      ),
      const _ModerationCase(
        title: 'Vereinsverifizierung',
        area: 'Clubs',
        status: 'Eskalation',
        body:
            'Vereinsdaten, Dokumente, Adminrechte und Sichtbarkeit müssen durch Plattformadmin geprüft werden.',
        color: AirmiusColors.amber,
      ),
      const _ModerationCase(
        title: 'Datenlöschanfrage',
        area: 'Privacy',
        status: 'Audit',
        body:
            'DSGVO-Anfrage mit Profil, Mitgliedschaften, Dokumenten, Zahlungen und Nachrichtenverlauf.',
        color: AirmiusColors.green,
      ),
    ];

    final filtered = cases
        .where((item) => queue == 'Alle' || item.status == queue)
        .toList();

    return PageFrame(
      title: 'Moderation & Audit',
      subtitle: 'Meldungen, Prüfung und Entscheidungen',
      actions: const [AirmiusLogoMark(size: 34)],
      child: ListView(
        shrinkWrap: true,
        physics: const NeverScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 28),
        children: [
          AirmiusPanel(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const SectionLabel('ADMIN QUEUE'),
                const SizedBox(height: 8),
                Text(
                  'Plattformadmins brauchen eine mobile Queue für Meldungen, Vereinsprüfungen, Support-Eskalationen, Datenschutzanfragen und Audit-Verlauf.',
                  style: TextStyle(
                    color: airmiusTextColor(context),
                    height: 1.45,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: 16),
                GridWrap(
                  children: const [
                    Metric(value: '4', label: 'Faelle'),
                    Metric(value: 'Audit', label: 'Verlauf'),
                    Metric(value: 'SLA', label: 'Status'),
                    Metric(value: 'Admin', label: 'Entscheid'),
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
                const SectionLabel('QUEUE'),
                const SizedBox(height: 12),
                SegmentedButton<String>(
                  segments: const [
                    ButtonSegment(value: 'Alle', label: Text('Alle')),
                    ButtonSegment(value: 'Offen', label: Text('Offen')),
                    ButtonSegment(value: 'Prüfung', label: Text('Prüfung')),
                    ButtonSegment(
                      value: 'Eskalation',
                      label: Text('Eskalation'),
                    ),
                    ButtonSegment(value: 'Audit', label: Text('Audit')),
                  ],
                  selected: {queue},
                  onSelectionChanged: (value) =>
                      setState(() => queue = value.first),
                ),
              ],
            ),
          ),
          const SizedBox(height: 14),
          AirmiusPanel(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const SectionLabel('PRUEFBEREICHE'),
                const SizedBox(height: 8),
                _AuditSwitch(
                  title: 'Content Reports',
                  value: showContentReports,
                  color: airmiusSemanticColor(context, AirmiusColors.pink),
                  onChanged: (value) =>
                      setState(() => showContentReports = value),
                ),
                _AuditSwitch(
                  title: 'User Reports',
                  value: showUserReports,
                  color: airmiusSemanticColor(context, AirmiusColors.blue),
                  onChanged: (value) => setState(() => showUserReports = value),
                ),
                _AuditSwitch(
                  title: 'Vereinsprüfung',
                  value: showClubReviews,
                  color: airmiusSemanticColor(context, AirmiusColors.amber),
                  onChanged: (value) => setState(() => showClubReviews = value),
                ),
                _AuditSwitch(
                  title: 'Audit-Verlauf',
                  value: showAuditTrail,
                  color: airmiusSemanticColor(context, AirmiusColors.green),
                  onChanged: (value) => setState(() => showAuditTrail = value),
                ),
              ],
            ),
          ),
          const SizedBox(height: 14),
          if (filtered.isEmpty)
            const EmptyPanel('Keine Faelle in dieser Queue.')
          else
            for (final item in filtered) ...[
              _ModerationCaseCard(item: item),
              const SizedBox(height: 12),
            ],
        ],
      ),
    );
  }
}

class _ModerationCase {
  const _ModerationCase({
    required this.title,
    required this.area,
    required this.status,
    required this.body,
    required this.color,
  });

  final String title;
  final String area;
  final String status;
  final String body;
  final Color color;
}

class _AuditSwitch extends StatelessWidget {
  const _AuditSwitch({
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
      title: Text(
        title,
        style: TextStyle(
          color: airmiusTextColor(context),
          fontWeight: FontWeight.w900,
        ),
      ),
      value: value,
      activeThumbColor: color,
      onChanged: onChanged,
    );
  }
}

class _ModerationCaseCard extends StatelessWidget {
  const _ModerationCaseCard({required this.item});

  final _ModerationCase item;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              IconBadge(
                icon: Icons.admin_panel_settings_outlined,
                color: airmiusSemanticColor(context, item.color),
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Expanded(
                          child: Text(
                            item.title,
                            style: TextStyle(
                              color: airmiusTextColor(context),
                              fontSize: 17,
                              fontWeight: FontWeight.w900,
                            ),
                          ),
                        ),
                        StatusPill(
                          item.status,
                          color: airmiusSemanticColor(context, item.color),
                        ),
                      ],
                    ),
                    const SizedBox(height: 4),
                    Text(
                      item.area,
                      style: TextStyle(
                        color: airmiusSemanticColor(
                          context,
                          AirmiusColors.blue,
                        ),
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(height: 8),
                    Text(
                      item.body,
                      style: TextStyle(
                        color: airmiusMutedColor(context),
                        height: 1.42,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
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
                label: 'Prüfen',
                icon: Icons.fact_check_outlined,
                onPressed: () => openUiAction(
                  context,
                  title: 'Fall prüfen',
                  body:
                      'Diese UI bereitet Moderationsfaelle mit Kontext, Reporter, Adminentscheidung, Status und Audit-Verlauf für die spätere API vor.',
                  status: 'UI vorbereitet',
                  icon: Icons.fact_check_outlined,
                ),
              ),
              AirmiusButton(
                label: 'Entscheiden',
                icon: Icons.verified_user_outlined,
                secondary: true,
                onPressed: () => openUiAction(
                  context,
                  title: 'Entscheidung',
                  body:
                      'Adminentscheidungen können später Freigabe, Sperre, Hinweis, Eskalation oder Ablehnung enthalten.',
                  status: 'UI vorbereitet',
                  icon: Icons.verified_user_outlined,
                ),
              ),
              AirmiusButton(
                label: 'Audit',
                icon: Icons.history_outlined,
                secondary: true,
                onPressed: () => openUiAction(
                  context,
                  title: 'Audit-Verlauf',
                  body:
                      'Der Audit-Verlauf zeigt später alle Aktionen, Rollen, Zeitpunkte, Dateien und Entscheidungen.',
                  status: 'UI vorbereitet',
                  icon: Icons.history_outlined,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}
