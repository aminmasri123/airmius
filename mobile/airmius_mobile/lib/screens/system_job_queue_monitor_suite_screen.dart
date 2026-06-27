import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

class SystemJobQueueMonitorSuiteScreen extends StatefulWidget {
  const SystemJobQueueMonitorSuiteScreen({super.key});

  @override
  State<SystemJobQueueMonitorSuiteScreen> createState() => _SystemJobQueueMonitorSuiteScreenState();
}

class _SystemJobQueueMonitorSuiteScreenState extends State<SystemJobQueueMonitorSuiteScreen> {
  String _queue = 'Alle';
  bool _autoRetry = true;
  bool _deadLetter = true;
  bool _adminAlerts = true;
  bool _maintenanceMode = false;

  @override
  Widget build(BuildContext context) {
    final jobs = _jobs.where((job) => _queue == 'Alle' || job.queue == _queue).toList();

    return Scaffold(
      backgroundColor: AirmiusColors.bg,
      appBar: AppBar(
        backgroundColor: AirmiusColors.header,
        surfaceTintColor: Colors.transparent,
        title: const Text('Job Queue Monitor', style: TextStyle(fontWeight: FontWeight.w900)),
      ),
      body: PageFrame(
        title: 'System Job Queue Monitor',
        subtitle: 'Mobile Betriebs-UI für E-Mails, Push, Upload-Scans, Importe, Exporte, Zahlungen, Webhooks und Retry-Queues.',
        trailing: const StatusPill('Queue Ops', color: AirmiusColors.amber),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            AirmiusPanel(
              gradient: true,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Eyebrow('BACKGROUND JOBS'),
                  const SizedBox(height: 8),
                  const Text(
                    'Alles, was im Hintergrund läuft, bekommt Kontrolle.',
                    style: TextStyle(color: AirmiusColors.text, fontSize: 24, fontWeight: FontWeight.w900, height: 1.08),
                  ),
                  const SizedBox(height: 8),
                  const Text(
                    'Diese Ansicht bereitet die mobile Admin-UI für Laravel Queues, Jobs, Retry, Dead Letter, Wartung und Betriebsalarme vor.',
                    style: TextStyle(color: AirmiusColors.muted, height: 1.42),
                  ),
                  const SizedBox(height: 14),
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: ['Alle', 'Mail', 'Files', 'Payments', 'Webhooks', 'Imports', 'Push'].map((item) {
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
                Expanded(child: MetricCard(value: '128', label: 'Queued')),
                SizedBox(width: 10),
                Expanded(child: MetricCard(value: '7', label: 'Failed')),
                SizedBox(width: 10),
                Expanded(child: MetricCard(value: '99%', label: 'OK')),
              ],
            ),
            const SizedBox(height: 14),
            AirmiusPanel(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Row(children: [const Expanded(child: Eyebrow('QUEUE REGELN')), StatusPill(_queue, color: AirmiusColors.blue)]),
                  const SizedBox(height: 12),
                  _QueueToggle(
                    icon: Icons.replay_outlined,
                    title: 'Automatischer Retry',
                    body: 'Fehlgeschlagene Jobs werden mit Backoff erneut gestartet, ohne dass Admins sofort eingreifen müssen.',
                    enabled: _autoRetry,
                    onChanged: (value) => setState(() => _autoRetry = value),
                  ),
                  _QueueToggle(
                    icon: Icons.error_outline,
                    title: 'Dead Letter Queue',
                    body: 'Nicht reparierbare Jobs bleiben sichtbar, auditierbar und können gezielt erneut ausgefuehrt werden.',
                    enabled: _deadLetter,
                    onChanged: (value) => setState(() => _deadLetter = value),
                  ),
                  _QueueToggle(
                    icon: Icons.notifications_active_outlined,
                    title: 'Admin Alerts',
                    body: 'Kritische Queue-Probleme loesen In-App, E-Mail oder Push-Hinweise für Admins aus.',
                    enabled: _adminAlerts,
                    onChanged: (value) => setState(() => _adminAlerts = value),
                  ),
                  _QueueToggle(
                    icon: Icons.construction_outlined,
                    title: 'Wartungsmodus',
                    body: 'Nichtkritische Jobs können pausiert werden, während Login, Sicherheit und Zahlungsstatus weiterlaufen.',
                    enabled: _maintenanceMode,
                    onChanged: (value) => setState(() => _maintenanceMode = value),
                    last: true,
                  ),
                ],
              ),
            ),
            const SizedBox(height: 14),
            for (final job in jobs) ...[
              _JobCard(job: job),
              const SizedBox(height: 12),
            ],
            AirmiusPanel(
              borderColor: AirmiusColors.green.withValues(alpha: .45),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Eyebrow('ADMIN AKTIONEN'),
                  const SizedBox(height: 8),
                  const Text('Später können berechtigte Admins Jobs erneut ausfuehren, pausieren, exportieren, als geloest markieren oder an Provider-Logs springen.', style: TextStyle(color: AirmiusColors.muted, height: 1.38)),
                  const SizedBox(height: 12),
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: const [
                      StatusPill('Retry selected', color: AirmiusColors.green),
                      StatusPill('Pause queue', color: AirmiusColors.amber),
                      StatusPill('Export logs', color: AirmiusColors.blue),
                    ],
                  ),
                  const SizedBox(height: 12),
                  AirmiusButton(label: 'Fehlgeschlagene Jobs prüfen', icon: Icons.fact_check_outlined, onPressed: () {}),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _JobEntry {
  const _JobEntry({required this.queue, required this.title, required this.body, required this.status, required this.time, required this.icon, required this.color});

  final String queue;
  final String title;
  final String body;
  final String status;
  final String time;
  final IconData icon;
  final Color color;
}

const _jobs = [
  _JobEntry(queue: 'Mail', title: 'Mitgliedsanfrage Admin-Mail', body: 'Benachrichtigt Vereinsadmins über neue Antraege, Rückzuege und Rückfragen.', status: 'Running', time: 'vor 2 Min.', icon: Icons.mail_outline, color: AirmiusColors.blue),
  _JobEntry(queue: 'Files', title: 'Dokument Upload Scan', body: 'Prüft Datenschutz, Satzung, Beitragsordnung und Formularanhaenge vor Freigabe.', status: 'Queued', time: 'vor 4 Min.', icon: Icons.document_scanner_outlined, color: AirmiusColors.amber),
  _JobEntry(queue: 'Payments', title: 'Beitragsstatus Sync', body: 'Synchronisiert offene Beiträge, Rechnungen, Mahnungen und Zahlungsbestätigungen.', status: 'OK', time: 'vor 8 Min.', icon: Icons.receipt_long_outlined, color: AirmiusColors.green),
  _JobEntry(queue: 'Webhooks', title: 'Payment Webhook Retry', body: 'Wiederholt fehlgeschlagene Provider-Events mit Backoff und Signaturprüfung.', status: 'Retry 2', time: 'vor 12 Min.', icon: Icons.webhook_outlined, color: AirmiusColors.amber),
  _JobEntry(queue: 'Imports', title: 'Mitglieder CSV Import', body: 'Fuehrt Mapping, Dublettenprüfung, Einladungen und Audit-Notizen aus.', status: 'Failed', time: 'vor 18 Min.', icon: Icons.import_export_outlined, color: AirmiusColors.red),
  _JobEntry(queue: 'Push', title: 'Training Reminder Push', body: 'Sendet Erinnerungen für Events, Fahrgemeinschaften und Teamtermine.', status: 'Scheduled', time: '15:30', icon: Icons.notifications_none_outlined, color: AirmiusColors.blue),
];

class _JobCard extends StatelessWidget {
  const _JobCard({required this.job});

  final _JobEntry job;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      borderColor: job.color.withValues(alpha: .42),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 48,
            height: 48,
            decoration: BoxDecoration(color: job.color.withValues(alpha: .14), borderRadius: BorderRadius.circular(16), border: Border.all(color: job.color.withValues(alpha: .4))),
            child: Icon(job.icon, color: job.color),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Expanded(child: Text(job.title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900))),
                    StatusPill(job.status, color: job.color),
                  ],
                ),
                const SizedBox(height: 5),
                Text(job.body, style: const TextStyle(color: AirmiusColors.muted, height: 1.35)),
                const SizedBox(height: 9),
                Wrap(spacing: 8, runSpacing: 8, children: [StatusPill(job.queue, color: job.color), StatusPill(job.time)]),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _QueueToggle extends StatelessWidget {
  const _QueueToggle({required this.icon, required this.title, required this.body, required this.enabled, required this.onChanged, this.last = false});

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
