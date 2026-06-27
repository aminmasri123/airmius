import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

class DraftAutosaveRecoverySuiteScreen extends StatefulWidget {
  const DraftAutosaveRecoverySuiteScreen({super.key});

  @override
  State<DraftAutosaveRecoverySuiteScreen> createState() => _DraftAutosaveRecoverySuiteScreenState();
}

class _DraftAutosaveRecoverySuiteScreenState extends State<DraftAutosaveRecoverySuiteScreen> {
  String _draftType = 'Mitgliedsantrag';
  bool _autosave = true;
  bool _offlineDrafts = true;
  bool _conflictReview = true;
  bool _privacyExpiry = true;

  @override
  Widget build(BuildContext context) {
    final drafts = _drafts.where((draft) => _draftType == 'Alle' || draft.type == _draftType).toList();

    return Scaffold(
      backgroundColor: AirmiusColors.bg,
      appBar: AppBar(
        backgroundColor: AirmiusColors.header,
        surfaceTintColor: Colors.transparent,
        title: const Text('Drafts & Autosave', style: TextStyle(fontWeight: FontWeight.w900)),
      ),
      body: PageFrame(
        title: 'Draft Autosave Recovery',
        subtitle: 'Mobile UI für automatische Zwischenspeicherung, Offline-Drafts, Konflikte, Wiederherstellung und Datenschutz-Ablauf.',
        trailing: const StatusPill('No data loss', color: AirmiusColors.green),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            AirmiusPanel(
              gradient: true,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Eyebrow('FORM SAFETY'),
                  const SizedBox(height: 8),
                  const Text(
                    'Lange Eingaben dürfen nicht verloren gehen.',
                    style: TextStyle(color: AirmiusColors.text, fontSize: 24, fontWeight: FontWeight.w900, height: 1.08),
                  ),
                  const SizedBox(height: 8),
                  const Text(
                    'Die App bereitet Autosave, lokale Drafts, Wiederherstellung, Konfliktprüfung und sichere Ablaufregeln für alle wichtigen Web-App-Formulare vor.',
                    style: TextStyle(color: AirmiusColors.muted, height: 1.42),
                  ),
                  const SizedBox(height: 14),
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: ['Alle', 'Mitgliedsantrag', 'Profil', 'Beitrag', 'Support', 'Checkout', 'Upload'].map((item) {
                      return ChoiceChip(
                        selected: _draftType == item,
                        label: Text(item),
                        onSelected: (_) => setState(() => _draftType = item),
                        selectedColor: AirmiusColors.blue.withValues(alpha: .22),
                        backgroundColor: AirmiusColors.panelSoft,
                        side: BorderSide(color: _draftType == item ? AirmiusColors.blue : AirmiusColors.border),
                        labelStyle: TextStyle(color: _draftType == item ? AirmiusColors.text : AirmiusColors.muted, fontWeight: FontWeight.w900),
                      );
                    }).toList(),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 14),
            Row(
              children: const [
                Expanded(child: MetricCard(value: '8', label: 'Drafts')),
                SizedBox(width: 10),
                Expanded(child: MetricCard(value: '30s', label: 'Save')),
                SizedBox(width: 10),
                Expanded(child: MetricCard(value: '7d', label: 'Expiry')),
              ],
            ),
            const SizedBox(height: 14),
            AirmiusPanel(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Row(children: [const Expanded(child: Eyebrow('DRAFT REGELN')), StatusPill(_draftType, color: AirmiusColors.blue)]),
                  const SizedBox(height: 12),
                  _DraftToggle(
                    icon: Icons.save_outlined,
                    title: 'Automatisches Speichern',
                    body: 'Formulare speichern sichere Zwischenstaende, ohne dass User manuell klicken müssen.',
                    enabled: _autosave,
                    onChanged: (value) => setState(() => _autosave = value),
                  ),
                  _DraftToggle(
                    icon: Icons.cloud_off_outlined,
                    title: 'Offline Drafts',
                    body: 'Bei Netzwerkproblemen bleiben Eingaben lokal erhalten und werden später synchronisiert.',
                    enabled: _offlineDrafts,
                    onChanged: (value) => setState(() => _offlineDrafts = value),
                  ),
                  _DraftToggle(
                    icon: Icons.compare_arrows_outlined,
                    title: 'Konfliktprüfung',
                    body: 'Wenn Serverdaten und lokaler Draft abweichen, zeigt die App eine klare Vergleichsansicht.',
                    enabled: _conflictReview,
                    onChanged: (value) => setState(() => _conflictReview = value),
                  ),
                  _DraftToggle(
                    icon: Icons.privacy_tip_outlined,
                    title: 'Datenschutz-Ablauf',
                    body: 'Sensible Drafts laufen automatisch ab und können von Usern jederzeit gelöscht werden.',
                    enabled: _privacyExpiry,
                    onChanged: (value) => setState(() => _privacyExpiry = value),
                    last: true,
                  ),
                ],
              ),
            ),
            const SizedBox(height: 14),
            for (final draft in drafts) ...[
              _DraftCard(draft: draft),
              const SizedBox(height: 12),
            ],
            AirmiusPanel(
              borderColor: AirmiusColors.green.withValues(alpha: .45),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Eyebrow('WIEDERHERSTELLUNG'),
                  const SizedBox(height: 8),
                  const Text('Beim erneuten Öffnen erkennt die App passende Drafts, zeigt Zeitpunkt, betroffene Felder und bietet Fortsetzen, Verwerfen oder Vergleichen an.', style: TextStyle(color: AirmiusColors.muted, height: 1.38)),
                  const SizedBox(height: 12),
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: const [
                      StatusPill('Fortsetzen', color: AirmiusColors.green),
                      StatusPill('Vergleichen', color: AirmiusColors.blue),
                      StatusPill('Verwerfen', color: AirmiusColors.amber),
                    ],
                  ),
                  const SizedBox(height: 12),
                  AirmiusButton(label: 'Draft wiederherstellen', icon: Icons.restore_outlined, onPressed: () {}),
                ],
              ),
            ),
            const SizedBox(height: 14),
            AirmiusPanel(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Eyebrow('API SYNC PAYLOAD'),
                  const SizedBox(height: 10),
                  const _PayloadLine(label: 'draft_scope', value: 'membership_application'),
                  const _PayloadLine(label: 'save_mode', value: 'local_first, encrypted, retry_safe'),
                  const _PayloadLine(label: 'conflict_keys', value: 'address, payment_method, guardian_email'),
                  const _PayloadLine(label: 'expiry_policy', value: 'user_delete, submitted_delete, 7_days_sensitive'),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _Draft {
  const _Draft({required this.type, required this.title, required this.body, required this.status, required this.time, required this.icon, required this.color});

  final String type;
  final String title;
  final String body;
  final String status;
  final String time;
  final IconData icon;
  final Color color;
}

const _drafts = [
  _Draft(type: 'Mitgliedsantrag', title: 'ZBB Mitgliedsantrag', body: 'Personendaten, Wohndaten, Zahlungsart und Datenschutzbestätigung sind zwischengespeichert.', status: 'Recoverable', time: 'vor 3 Min.', icon: Icons.assignment_ind_outlined, color: AirmiusColors.green),
  _Draft(type: 'Mitgliedsantrag', title: 'SEPA Mandat unvollstaendig', body: 'IBAN wurde begonnen, Mandatsbestätigung fehlt noch.', status: 'Needs input', time: 'vor 18 Min.', icon: Icons.account_balance_outlined, color: AirmiusColors.amber),
  _Draft(type: 'Profil', title: 'Sportprofil bearbeiten', body: 'Sportarten, Ziele, Sichtbarkeit und Kontaktrechte wurden lokal gespeichert.', status: 'Local', time: 'Heute', icon: Icons.person_outline, color: AirmiusColors.blue),
  _Draft(type: 'Beitrag', title: 'Community Post', body: 'Text, Zielgruppe, Bildanhaenge und Moderationshinweis sind als Entwurf vorhanden.', status: 'Draft', time: 'Gestern', icon: Icons.forum_outlined, color: AirmiusColors.blue),
  _Draft(type: 'Support', title: 'Supportticket', body: 'Problemtyp, Beschreibung, Screenshot und Vereinskontext wurden vorbereitet.', status: 'Ready', time: 'Heute', icon: Icons.support_agent_outlined, color: AirmiusColors.green),
  _Draft(type: 'Checkout', title: 'Marketplace Checkout', body: 'Warenkorb, Lieferart, Rechnungsadresse und Zahlungsart warten auf Abschluss.', status: 'Pending', time: 'vor 1 Std.', icon: Icons.shopping_bag_outlined, color: AirmiusColors.amber),
  _Draft(type: 'Upload', title: 'Vereinsdokument Upload', body: 'Beitragsordnung wurde ausgewählt, Zweck und Sichtbarkeit fehlen.', status: 'Incomplete', time: 'Heute', icon: Icons.cloud_upload_outlined, color: AirmiusColors.blue),
];

class _DraftCard extends StatelessWidget {
  const _DraftCard({required this.draft});

  final _Draft draft;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      borderColor: draft.color.withValues(alpha: .42),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 48,
            height: 48,
            decoration: BoxDecoration(color: draft.color.withValues(alpha: .14), borderRadius: BorderRadius.circular(16), border: Border.all(color: draft.color.withValues(alpha: .42))),
            child: Icon(draft.icon, color: draft.color),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Expanded(child: Text(draft.title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900))),
                    StatusPill(draft.status, color: draft.color),
                  ],
                ),
                const SizedBox(height: 6),
                Text(draft.body, style: const TextStyle(color: AirmiusColors.muted, height: 1.35)),
                const SizedBox(height: 9),
                Wrap(spacing: 8, runSpacing: 8, children: [StatusPill(draft.type, color: draft.color), StatusPill(draft.time)]),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _DraftToggle extends StatelessWidget {
  const _DraftToggle({required this.icon, required this.title, required this.body, required this.enabled, required this.onChanged, this.last = false});

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
            SizedBox(width: 118, child: Text(label, style: const TextStyle(color: AirmiusColors.blue, fontWeight: FontWeight.w900))),
            Expanded(child: Text(value, style: const TextStyle(color: AirmiusColors.text, height: 1.35))),
          ],
        ),
      ),
    );
  }
}
