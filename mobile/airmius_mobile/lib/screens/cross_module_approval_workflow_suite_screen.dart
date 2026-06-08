import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

class CrossModuleApprovalWorkflowSuiteScreen extends StatefulWidget {
  const CrossModuleApprovalWorkflowSuiteScreen({super.key});

  @override
  State<CrossModuleApprovalWorkflowSuiteScreen> createState() => _CrossModuleApprovalWorkflowSuiteScreenState();
}

class _CrossModuleApprovalWorkflowSuiteScreenState extends State<CrossModuleApprovalWorkflowSuiteScreen> {
  String _queue = 'Alle';
  bool _twoStepApproval = true;
  bool _commentRequired = true;
  bool _escalation = true;
  bool _auditProof = true;

  @override
  Widget build(BuildContext context) {
    final approvals = _approvals.where((approval) => _queue == 'Alle' || approval.queue == _queue).toList();

    return Scaffold(
      backgroundColor: AirmiusColors.bg,
      appBar: AppBar(
        backgroundColor: AirmiusColors.header,
        surfaceTintColor: Colors.transparent,
        title: const Text('Freigaben', style: TextStyle(fontWeight: FontWeight.w900)),
      ),
      body: PageFrame(
        title: 'Cross Module Approval Workflow',
        subtitle: 'Mobile UI fuer Freigaben, Rueckfragen, Entscheidungen, Eskalation und Audit ueber Mitgliedschaft, Dateien, Zahlungen, Content und Admin.',
        trailing: const StatusPill('Approval', color: AirmiusColors.amber),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            AirmiusPanel(
              gradient: true,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Eyebrow('APPROVAL QUEUE'),
                  const SizedBox(height: 8),
                  const Text(
                    'Entscheidungen bekommen einen ruhigen, klaren Ort.',
                    style: TextStyle(color: AirmiusColors.text, fontSize: 24, fontWeight: FontWeight.w900, height: 1.08),
                  ),
                  const SizedBox(height: 8),
                  const Text(
                    'Die App bereitet eine gemeinsame Freigabezentrale fuer Vereinsadmins, Trainer, Kassenwarte und Plattformadmins vor.',
                    style: TextStyle(color: AirmiusColors.muted, height: 1.42),
                  ),
                  const SizedBox(height: 14),
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: ['Alle', 'Mitgliedschaft', 'Dateien', 'Finanzen', 'Content', 'Events', 'Admin'].map((item) {
                      return ChoiceChip(
                        selected: _queue == item,
                        label: Text(item),
                        onSelected: (_) => setState(() => _queue = item),
                        selectedColor: AirmiusColors.blue.withValues(alpha: .22),
                        backgroundColor: AirmiusColors.panelSoft,
                        side: BorderSide(color: _queue == item ? AirmiusColors.blue : AirmiusColors.border),
                        labelStyle: TextStyle(color: _queue == item ? AirmiusColors.text : AirmiusColors.muted, fontWeight: FontWeight.w900),
                      );
                    }).toList(),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 14),
            Row(
              children: const [
                Expanded(child: MetricCard(value: '12', label: 'Offen')),
                SizedBox(width: 10),
                Expanded(child: MetricCard(value: '4', label: 'Rueckfrage')),
                SizedBox(width: 10),
                Expanded(child: MetricCard(value: '2', label: 'Eilig')),
              ],
            ),
            const SizedBox(height: 14),
            AirmiusPanel(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Row(children: [const Expanded(child: Eyebrow('FREIGABEREGELN')), StatusPill(_queue, color: AirmiusColors.blue)]),
                  const SizedBox(height: 12),
                  _ApprovalToggle(
                    icon: Icons.verified_user_outlined,
                    title: 'Zwei-Stufen-Freigabe',
                    body: 'Sensible Aktionen wie Rollenwechsel, Refunds oder Dokumentfreigaben koennen zwei Entscheider verlangen.',
                    enabled: _twoStepApproval,
                    onChanged: (value) => setState(() => _twoStepApproval = value),
                  ),
                  _ApprovalToggle(
                    icon: Icons.mode_comment_outlined,
                    title: 'Kommentarpflicht',
                    body: 'Ablehnung, Rueckfrage und Eskalation bekommen einen kurzen Grund fuer Antragsteller und Audit.',
                    enabled: _commentRequired,
                    onChanged: (value) => setState(() => _commentRequired = value),
                  ),
                  _ApprovalToggle(
                    icon: Icons.priority_high_outlined,
                    title: 'Eskalation',
                    body: 'Ueberfaellige oder kritische Freigaben koennen an Vorstand, Kassenwart oder Plattformadmin gehen.',
                    enabled: _escalation,
                    onChanged: (value) => setState(() => _escalation = value),
                  ),
                  _ApprovalToggle(
                    icon: Icons.history_outlined,
                    title: 'Audit-Nachweis',
                    body: 'Jede Entscheidung merkt sich Rolle, Zeitpunkt, Aktion, Kommentar und betroffene Datenversion.',
                    enabled: _auditProof,
                    onChanged: (value) => setState(() => _auditProof = value),
                    last: true,
                  ),
                ],
              ),
            ),
            const SizedBox(height: 14),
            for (final approval in approvals) ...[
              _ApprovalCard(approval: approval),
              const SizedBox(height: 12),
            ],
            AirmiusPanel(
              borderColor: AirmiusColors.green.withValues(alpha: .45),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Eyebrow('ENTSCHEIDUNG'),
                  const SizedBox(height: 8),
                  const Text('Admins koennen mobil annehmen, ablehnen, Rueckfrage senden, delegieren oder spaeter erinnern. Die App zeigt immer, welche Daten betroffen sind.', style: TextStyle(color: AirmiusColors.muted, height: 1.38)),
                  const SizedBox(height: 12),
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: const [
                      StatusPill('Annehmen', color: AirmiusColors.green),
                      StatusPill('Rueckfrage', color: AirmiusColors.blue),
                      StatusPill('Ablehnen', color: AirmiusColors.amber),
                      StatusPill('Delegieren', color: AirmiusColors.blue),
                    ],
                  ),
                  const SizedBox(height: 12),
                  AirmiusButton(label: 'Freigabe bearbeiten', icon: Icons.fact_check_outlined, onPressed: () {}),
                ],
              ),
            ),
            const SizedBox(height: 14),
            AirmiusPanel(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Eyebrow('API APPROVAL PAYLOAD'),
                  const SizedBox(height: 10),
                  const _PayloadLine(label: 'queue', value: 'membership, files, finance, content, events, admin'),
                  const _PayloadLine(label: 'actions', value: 'approve, reject, ask, delegate, escalate, remind'),
                  const _PayloadLine(label: 'guards', value: 'role, permission, two_step, conflict_check, comment_required'),
                  const _PayloadLine(label: 'audit', value: 'actor_id, role, decision_at, comment, affected_version'),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _Approval {
  const _Approval({required this.queue, required this.title, required this.body, required this.status, required this.icon, required this.color});

  final String queue;
  final String title;
  final String body;
  final String status;
  final IconData icon;
  final Color color;
}

const _approvals = [
  _Approval(queue: 'Mitgliedschaft', title: 'ZBB Mitgliedsantrag', body: 'Personendaten, Dokumente, Beitrag und Consent sind bereit zur Admin-Entscheidung.', status: 'Neu', icon: Icons.assignment_ind_outlined, color: AirmiusColors.green),
  _Approval(queue: 'Mitgliedschaft', title: 'Rueckfrage beantworten', body: 'Antragsteller hat fehlende Lizenznummer nachgereicht.', status: 'Rueckfrage', icon: Icons.question_answer_outlined, color: AirmiusColors.blue),
  _Approval(queue: 'Dateien', title: 'Beitragsordnung freigeben', body: 'Neue Dokumentversion soll im Mitgliedsantrag als Pflichtdokument sichtbar werden.', status: 'Version', icon: Icons.folder_copy_outlined, color: AirmiusColors.blue),
  _Approval(queue: 'Finanzen', title: 'Refund pruefen', body: 'Rueckerstattung fuer doppelte Beitragszahlung benoetigt Kassenwart-Freigabe.', status: 'Eilig', icon: Icons.payments_outlined, color: AirmiusColors.amber),
  _Approval(queue: 'Content', title: 'Vereinsnews pruefen', body: 'Beitrag mit Medienfreigabe und Sponsorhinweis wartet auf Moderation.', status: 'Review', icon: Icons.article_outlined, color: AirmiusColors.blue),
  _Approval(queue: 'Events', title: 'Event veroeffentlichen', body: 'Trainingstermin mit Fahrgemeinschaft, Check-in und Guardian-Hinweis wartet.', status: 'Planung', icon: Icons.event_available_outlined, color: AirmiusColors.green),
  _Approval(queue: 'Admin', title: 'Rollenwechsel', body: 'Mitglied soll Vereinsadmin-Rechte erhalten; Zwei-Stufen-Freigabe aktiv.', status: '2-Step', icon: Icons.admin_panel_settings_outlined, color: AirmiusColors.amber),
];

class _ApprovalCard extends StatelessWidget {
  const _ApprovalCard({required this.approval});

  final _Approval approval;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      borderColor: approval.color.withValues(alpha: .42),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 48,
            height: 48,
            decoration: BoxDecoration(color: approval.color.withValues(alpha: .14), borderRadius: BorderRadius.circular(16), border: Border.all(color: approval.color.withValues(alpha: .42))),
            child: Icon(approval.icon, color: approval.color),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Expanded(child: Text(approval.title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900))),
                    StatusPill(approval.status, color: approval.color),
                  ],
                ),
                const SizedBox(height: 6),
                Text(approval.body, style: const TextStyle(color: AirmiusColors.muted, height: 1.35)),
                const SizedBox(height: 9),
                Wrap(spacing: 8, runSpacing: 8, children: [StatusPill(approval.queue, color: approval.color), const StatusPill('Audit')]),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _ApprovalToggle extends StatelessWidget {
  const _ApprovalToggle({required this.icon, required this.title, required this.body, required this.enabled, required this.onChanged, this.last = false});

  final IconData icon;
  final String title;
  final String body;
  final bool enabled;
  final ValueChanged<bool> onChanged;
  final bool last;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: EdgeInsets.only(bottom: last ? 0 : 12),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, color: enabled ? AirmiusColors.green : AirmiusColors.muted),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
                const SizedBox(height: 4),
                Text(body, style: const TextStyle(color: AirmiusColors.muted, height: 1.35)),
              ],
            ),
          ),
          Switch(value: enabled, activeThumbColor: AirmiusColors.green, onChanged: onChanged),
        ],
      ),
    );
  }
}

class _PayloadLine extends StatelessWidget {
  const _PayloadLine({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: Container(
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(color: AirmiusColors.cardSoft, borderRadius: BorderRadius.circular(14), border: Border.all(color: AirmiusColors.border)),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            SizedBox(width: 112, child: Text(label, style: const TextStyle(color: AirmiusColors.blue, fontWeight: FontWeight.w900))),
            Expanded(child: Text(value, style: const TextStyle(color: AirmiusColors.text, height: 1.35))),
          ],
        ),
      ),
    );
  }
}
