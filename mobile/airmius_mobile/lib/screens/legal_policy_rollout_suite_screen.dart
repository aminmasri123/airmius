import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

class LegalPolicyRolloutSuiteScreen extends StatelessWidget {
  const LegalPolicyRolloutSuiteScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final policies = [
      _PolicyItem('Datenschutz 2026.06', '78% bestätigt', 'Neue Verarbeitungshinweise für Vereinsprofile, Dateien und Push.', AirmiusColors.blue, Icons.privacy_tip_outlined),
      _PolicyItem('Beitragsordnung', 'Entwurf', 'Neue Zahlungszyklen, Barzahlung, Überweisung und SEPA-Regeln.', AirmiusColors.amber, Icons.receipt_long_outlined),
      _PolicyItem('Satzung & Regeln', 'Aktiv', 'Vereinsregeln, Rollen, Stimmrecht, Ausschluss und Beschwerdeweg.', AirmiusColors.green, Icons.gavel_outlined),
      _PolicyItem('Medienfreigabe', 'Guardian', 'Foto, Video, Social Feed, Teamseiten und Altersfreigabe.', AirmiusColors.pink, Icons.photo_library_outlined),
    ];

    final rollout = [
      _RolloutStep('Version erstellen', 'Dokument, Pflichttext, Kurzfassung, Sprache und Gültigkeitsdatum vorbereiten.'),
      _RolloutStep('Zielgruppe wählen', 'Alle Mitglieder, neues Formular, Team, Guardian, Trainer, Verein oder Rolle.'),
      _RolloutStep('Bestätigung einholen', 'App-Banner, Push, E-Mail, Formularblocker, Erinnerung und Rückfrage.'),
      _RolloutStep('Audit sichern', 'Zeitpunkt, IP/Device, Version, Guardian, Widerruf, Export und Aufbewahrung.'),
    ];

    return Scaffold(
      backgroundColor: AirmiusColors.bg,
      appBar: AppBar(
        backgroundColor: AirmiusColors.header,
        surfaceTintColor: Colors.transparent,
        title: const Text('Policy Rollout', style: TextStyle(fontWeight: FontWeight.w900)),
      ),
      body: PageFrame(
        title: 'Policy Rollout',
        subtitle: 'Datenschutz, Satzung, Beitragsordnung, SEPA, Medienfreigabe, Consent und Audit als mobile Rechts-UI.',
        trailing: const StatusPill('Legal', color: AirmiusColors.amber),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            AirmiusPanel(
              gradient: true,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: const [
                  Eyebrow('LEGAL ROLLOUT'),
                  SizedBox(height: 10),
                  Text('Neue Regeln müssen aktiv bei Mitgliedern ankommen.', style: TextStyle(color: AirmiusColors.text, fontSize: 24, fontWeight: FontWeight.w900, height: 1.08)),
                  SizedBox(height: 8),
                  Text('Diese mobile Suite macht Dokumentversionen, Pflichtbestätigungen, Guardian-Freigaben, Erinnerungen, Widerruf und Audit für Vereine und Plattform sauber steuerbar.', style: TextStyle(color: AirmiusColors.muted, height: 1.42)),
                ],
              ),
            ),
            const SizedBox(height: 14),
            Row(
              children: const [
                Expanded(child: MetricCard(value: '4', label: 'Policies')),
                SizedBox(width: 10),
                Expanded(child: MetricCard(value: '78%', label: 'Consent')),
                SizedBox(width: 10),
                Expanded(child: MetricCard(value: 'Audit', label: 'Safe')),
              ],
            ),
            const SizedBox(height: 14),
            for (final policy in policies) ...[
              AirmiusPanel(
                borderColor: policy.color.withValues(alpha: .44),
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Container(
                      width: 50,
                      height: 50,
                      decoration: BoxDecoration(
                        color: policy.color.withValues(alpha: .14),
                        borderRadius: BorderRadius.circular(16),
                        border: Border.all(color: policy.color.withValues(alpha: .45)),
                      ),
                      child: Icon(policy.icon, color: policy.color),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(policy.title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900, fontSize: 16)),
                          const SizedBox(height: 5),
                          Text(policy.body, style: const TextStyle(color: AirmiusColors.muted, height: 1.35)),
                          const SizedBox(height: 10),
                          Wrap(spacing: 8, runSpacing: 8, children: [StatusPill(policy.status, color: policy.color), const StatusPill('Versioniert')]),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 12),
            ],
            AirmiusPanel(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Eyebrow('ROLLOUT FLOW'),
                  const SizedBox(height: 12),
                  for (final step in rollout) ...[
                    _RolloutRow(step: step),
                    if (step != rollout.last) const SizedBox(height: 10),
                  ],
                ],
              ),
            ),
            const SizedBox(height: 14),
            AirmiusPanel(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: const [
                  Eyebrow('API & COMPLIANCE'),
                  SizedBox(height: 10),
                  _ApiLine(label: 'policy_version', value: 'Typ, Version, Sprache, Datei, Kurztext, Pflichtstatus, Gültigkeit'),
                  _ApiLine(label: 'targeting', value: 'Mitglied, Team, Rolle, Guardian, neuer Antrag, Bestandsmitglied'),
                  _ApiLine(label: 'consent_event', value: 'Bestätigt, abgelehnt, widerrufen, erinnert, blockiert, exportiert'),
                  _ApiLine(label: 'audit_retention', value: 'Aufbewahrung, Datenschutzexport, Löschfrist, Adminnachweis'),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _PolicyItem {
  const _PolicyItem(this.title, this.status, this.body, this.color, this.icon);

  final String title;
  final String status;
  final String body;
  final Color color;
  final IconData icon;
}

class _RolloutStep {
  const _RolloutStep(this.title, this.body);

  final String title;
  final String body;
}

class _RolloutRow extends StatelessWidget {
  const _RolloutRow({required this.step});

  final _RolloutStep step;

  @override
  Widget build(BuildContext context) => Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 34,
            height: 34,
            alignment: Alignment.center,
            decoration: BoxDecoration(color: AirmiusColors.amber.withValues(alpha: .16), borderRadius: BorderRadius.circular(12), border: Border.all(color: AirmiusColors.amber.withValues(alpha: .42))),
            child: const Icon(Icons.verified_user_outlined, color: AirmiusColors.amber, size: 19),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(step.title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
                const SizedBox(height: 4),
                Text(step.body, style: const TextStyle(color: AirmiusColors.muted, height: 1.35)),
              ],
            ),
          ),
        ],
      );
}

class _ApiLine extends StatelessWidget {
  const _ApiLine({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.only(bottom: 9),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            SizedBox(width: 122, child: Text(label, style: const TextStyle(color: AirmiusColors.amber, fontWeight: FontWeight.w900))),
            Expanded(child: Text(value, style: const TextStyle(color: AirmiusColors.muted, height: 1.35))),
          ],
        ),
      );
}
