import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

class ConsentSignatureVersioningSuiteScreen extends StatefulWidget {
  const ConsentSignatureVersioningSuiteScreen({super.key});

  @override
  State<ConsentSignatureVersioningSuiteScreen> createState() => _ConsentSignatureVersioningSuiteScreenState();
}

class _ConsentSignatureVersioningSuiteScreenState extends State<ConsentSignatureVersioningSuiteScreen> {
  String _context = 'Mitgliedsantrag';
  bool _documentVersion = true;
  bool _guardianConsent = true;
  bool _signatureRequired = false;
  bool _auditTrail = true;

  @override
  Widget build(BuildContext context) {
    final consents = _consents.where((item) => _context == 'Alle' || item.context == _context).toList();

    return Scaffold(
      backgroundColor: AirmiusColors.bg,
      appBar: AppBar(
        backgroundColor: AirmiusColors.header,
        surfaceTintColor: Colors.transparent,
        title: const Text('Consent & Signatur', style: TextStyle(fontWeight: FontWeight.w900)),
      ),
      body: PageFrame(
        title: 'Consent Signature Versioning',
        subtitle: 'Mobile UI für Dokumentversionen, Einwilligungen, digitale Bestätigungen, Guardian-Freigaben und Audit-Nachweise.',
        trailing: const StatusPill('Legal ready', color: AirmiusColors.green),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            AirmiusPanel(
              gradient: true,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Eyebrow('CONSENT FLOW'),
                  const SizedBox(height: 8),
                  const Text(
                    'Jede Zustimmung bekommt Kontext, Version und Nachweis.',
                    style: TextStyle(color: AirmiusColors.text, fontSize: 24, fontWeight: FontWeight.w900, height: 1.08),
                  ),
                  const SizedBox(height: 8),
                  const Text(
                    'Die App bereitet rechtssichere mobile Bestätigungen für Datenschutz, Satzung, Beitragsordnung, SEPA, Medienrechte und Guardian-Freigaben vor.',
                    style: TextStyle(color: AirmiusColors.muted, height: 1.42),
                  ),
                  const SizedBox(height: 14),
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: ['Alle', 'Mitgliedsantrag', 'Profil', 'SEPA', 'Medien', 'Guardian', 'Events'].map((item) {
                      return ChoiceChip(
                        selected: _context == item,
                        label: Text(item),
                        onSelected: (_) => setState(() => _context = item),
                        selectedColor: AirmiusColors.blue.withValues(alpha: .22),
                        backgroundColor: AirmiusColors.panelSoft,
                        side: BorderSide(color: _context == item ? AirmiusColors.blue : AirmiusColors.border),
                        labelStyle: TextStyle(color: _context == item ? AirmiusColors.text : AirmiusColors.muted, fontWeight: FontWeight.w900),
                      );
                    }).toList(),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 14),
            Row(
              children: const [
                Expanded(child: MetricCard(value: '9', label: 'Consents')),
                SizedBox(width: 10),
                Expanded(child: MetricCard(value: 'v4', label: 'Version')),
                SizedBox(width: 10),
                Expanded(child: MetricCard(value: 'Audit', label: 'Proof')),
              ],
            ),
            const SizedBox(height: 14),
            AirmiusPanel(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Row(children: [const Expanded(child: Eyebrow('NACHWEISREGELN')), StatusPill(_context, color: AirmiusColors.blue)]),
                  const SizedBox(height: 12),
                  _ConsentToggle(
                    icon: Icons.rule_folder_outlined,
                    title: 'Dokumentversion speichern',
                    body: 'Jede Zustimmung merkt sich Dateiversion, Titel, Verein, Zeitstempel und Quelle.',
                    enabled: _documentVersion,
                    onChanged: (value) => setState(() => _documentVersion = value),
                  ),
                  _ConsentToggle(
                    icon: Icons.family_restroom_outlined,
                    title: 'Guardian-Freigabe',
                    body: 'Bei Minderjaehrigen werden Eltern-/Guardian-Bestätigungen sichtbar getrennt und prüfbar gehalten.',
                    enabled: _guardianConsent,
                    onChanged: (value) => setState(() => _guardianConsent = value),
                  ),
                  _ConsentToggle(
                    icon: Icons.edit_note_outlined,
                    title: 'Digitale Signatur',
                    body: 'Optional kann eine Signatur oder Namensbestätigung für SEPA, Satzung oder Sonderregeln verlangt werden.',
                    enabled: _signatureRequired,
                    onChanged: (value) => setState(() => _signatureRequired = value),
                  ),
                  _ConsentToggle(
                    icon: Icons.history_outlined,
                    title: 'Audit Trail',
                    body: 'Akzeptiert, widerrufen, erneuert und durch Admin geprüft werden als nachvollziehbare Ereignisse gespeichert.',
                    enabled: _auditTrail,
                    onChanged: (value) => setState(() => _auditTrail = value),
                    last: true,
                  ),
                ],
              ),
            ),
            const SizedBox(height: 14),
            for (final consent in consents) ...[
              _ConsentCard(consent: consent),
              const SizedBox(height: 12),
            ],
            AirmiusPanel(
              borderColor: AirmiusColors.green.withValues(alpha: .45),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Eyebrow('USER BESTAETIGUNG'),
                  const SizedBox(height: 8),
                  const Text(
                    'Ich habe Datenschutz, Satzung und Beitragsordnung gelesen und akzeptiere die für meine Mitgliedschaft geltenden Regeln.',
                    style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900, height: 1.38),
                  ),
                  const SizedBox(height: 10),
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: const [
                      StatusPill('Version v4', color: AirmiusColors.blue),
                      StatusPill('Timestamp', color: AirmiusColors.green),
                      StatusPill('Revocable', color: AirmiusColors.amber),
                    ],
                  ),
                  const SizedBox(height: 12),
                  AirmiusButton(label: 'Zustimmung bestätigen', icon: Icons.fact_check_outlined, onPressed: () {}),
                ],
              ),
            ),
            const SizedBox(height: 14),
            AirmiusPanel(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Eyebrow('API CONSENT PAYLOAD'),
                  const SizedBox(height: 10),
                  const _PayloadLine(label: 'document_id', value: 'club_privacy_policy_v4'),
                  const _PayloadLine(label: 'consent_scope', value: 'membership_application, sepa, media, guardian'),
                  const _PayloadLine(label: 'proof', value: 'accepted_at, ip_hash, user_agent, guardian_id, document_version'),
                  const _PayloadLine(label: 'actions', value: 'accept, revoke, renew, admin_verify'),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _Consent {
  const _Consent({required this.context, required this.title, required this.body, required this.status, required this.icon, required this.color});

  final String context;
  final String title;
  final String body;
  final String status;
  final IconData icon;
  final Color color;
}

const _consents = [
  _Consent(context: 'Mitgliedsantrag', title: 'Datenschutz Verein', body: 'Version v4 wurde gelesen, akzeptiert und mit dem Antrag verknuepft.', status: 'Pflicht', icon: Icons.privacy_tip_outlined, color: AirmiusColors.green),
  _Consent(context: 'Mitgliedsantrag', title: 'Satzung & Regeln', body: 'Satzung, Hausordnung und Vereinsregeln werden als Dokumentversion bestätigt.', status: 'Pflicht', icon: Icons.gavel_outlined, color: AirmiusColors.blue),
  _Consent(context: 'SEPA', title: 'SEPA-Lastschriftmandat', body: 'IBAN, Mandatstext, Name und digitale Bestätigung werden auditierbar gespeichert.', status: 'Signatur', icon: Icons.account_balance_outlined, color: AirmiusColors.amber),
  _Consent(context: 'Medien', title: 'Medienfreigabe', body: 'Foto- und Videoeinwilligung für Training, Events und Vereinsbeiträge.', status: 'Optional', icon: Icons.photo_camera_outlined, color: AirmiusColors.blue),
  _Consent(context: 'Guardian', title: 'Elternfreigabe', body: 'Guardian bestätigt Minderjaehrigenprofil, Kontakt, Notfallkontakt und Vereinsregeln.', status: 'Guardian', icon: Icons.family_restroom_outlined, color: AirmiusColors.green),
  _Consent(context: 'Events', title: 'Event-Haftungshinweis', body: 'Teilnahmebedingungen, Gesundheits- und Sicherheitsinformationen für Events.', status: 'Event', icon: Icons.event_available_outlined, color: AirmiusColors.amber),
  _Consent(context: 'Profil', title: 'Profilsichtbarkeit', body: 'User entscheidet, ob Vereinsmitgliedschaften, Teams und Nachrichtenrechte sichtbar sind.', status: 'User', icon: Icons.visibility_outlined, color: AirmiusColors.blue),
];

class _ConsentCard extends StatelessWidget {
  const _ConsentCard({required this.consent});

  final _Consent consent;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      borderColor: consent.color.withValues(alpha: .42),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 48,
            height: 48,
            decoration: BoxDecoration(color: consent.color.withValues(alpha: .14), borderRadius: BorderRadius.circular(16), border: Border.all(color: consent.color.withValues(alpha: .42))),
            child: Icon(consent.icon, color: consent.color),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Expanded(child: Text(consent.title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900))),
                    StatusPill(consent.status, color: consent.color),
                  ],
                ),
                const SizedBox(height: 6),
                Text(consent.body, style: const TextStyle(color: AirmiusColors.muted, height: 1.35)),
                const SizedBox(height: 9),
                Wrap(spacing: 8, runSpacing: 8, children: [StatusPill(consent.context, color: consent.color), const StatusPill('Versioned')]),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _ConsentToggle extends StatelessWidget {
  const _ConsentToggle({required this.icon, required this.title, required this.body, required this.enabled, required this.onChanged, this.last = false});

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
