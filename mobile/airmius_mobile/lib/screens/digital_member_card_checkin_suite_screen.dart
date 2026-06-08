import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

class DigitalMemberCardCheckinSuiteScreen extends StatefulWidget {
  const DigitalMemberCardCheckinSuiteScreen({super.key});

  @override
  State<DigitalMemberCardCheckinSuiteScreen> createState() => _DigitalMemberCardCheckinSuiteScreenState();
}

class _DigitalMemberCardCheckinSuiteScreenState extends State<DigitalMemberCardCheckinSuiteScreen> {
  String _mode = 'Mitgliedskarte';
  bool _qrEnabled = true;
  bool _offlineVerify = true;
  bool _eventCheckin = true;
  bool _privacyMinimal = true;

  @override
  Widget build(BuildContext context) {
    final cards = _cards.where((card) => _mode == 'Alle' || card.mode == _mode).toList();

    return Scaffold(
      backgroundColor: AirmiusColors.bg,
      appBar: AppBar(
        backgroundColor: AirmiusColors.header,
        surfaceTintColor: Colors.transparent,
        title: const Text('Mitgliedskarte', style: TextStyle(fontWeight: FontWeight.w900)),
      ),
      body: PageFrame(
        title: 'Digital Member Card Check-in',
        subtitle: 'Mobile UI fuer digitale Mitgliedskarte, QR-Verifikation, Training-Check-in, Offline-Pruefung und Datenschutz.',
        trailing: const StatusPill('Native value', color: AirmiusColors.green),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            AirmiusPanel(
              gradient: true,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Eyebrow('MEMBER PASS'),
                  const SizedBox(height: 8),
                  const Text(
                    'Mitgliedschaft wird vor Ort sofort sichtbar.',
                    style: TextStyle(color: AirmiusColors.text, fontSize: 24, fontWeight: FontWeight.w900, height: 1.08),
                  ),
                  const SizedBox(height: 8),
                  const Text(
                    'Die App bereitet digitale Mitgliedskarten, QR-Check-in, Vereinsstatus, Trainingsteilnahme und sichere Offline-Verifikation vor.',
                    style: TextStyle(color: AirmiusColors.muted, height: 1.42),
                  ),
                  const SizedBox(height: 14),
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: ['Alle', 'Mitgliedskarte', 'Check-in', 'Trainer Scan', 'Offline', 'Historie'].map((item) {
                      return ChoiceChip(
                        selected: _mode == item,
                        label: Text(item),
                        onSelected: (_) => setState(() => _mode = item),
                        selectedColor: AirmiusColors.blue.withValues(alpha: .22),
                        backgroundColor: AirmiusColors.panelSoft,
                        side: BorderSide(color: _mode == item ? AirmiusColors.blue : AirmiusColors.border),
                        labelStyle: TextStyle(color: _mode == item ? AirmiusColors.text : AirmiusColors.muted, fontWeight: FontWeight.w900),
                      );
                    }).toList(),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 14),
            Row(
              children: const [
                Expanded(child: MetricCard(value: 'Aktiv', label: 'Status')),
                SizedBox(width: 10),
                Expanded(child: MetricCard(value: 'QR', label: 'Pass')),
                SizedBox(width: 10),
                Expanded(child: MetricCard(value: '2', label: 'Clubs')),
              ],
            ),
            const SizedBox(height: 14),
            _MemberCardPreview(qrEnabled: _qrEnabled),
            const SizedBox(height: 14),
            AirmiusPanel(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Row(children: [const Expanded(child: Eyebrow('KARTENREGELN')), StatusPill(_mode, color: AirmiusColors.blue)]),
                  const SizedBox(height: 12),
                  _CardToggle(
                    icon: Icons.qr_code_2_outlined,
                    title: 'QR-Verifikation',
                    body: 'Trainer und Vereinsadmins koennen die Karte scannen und erhalten nur freigegebene Minimaldaten.',
                    enabled: _qrEnabled,
                    onChanged: (value) => setState(() => _qrEnabled = value),
                  ),
                  _CardToggle(
                    icon: Icons.wifi_off_outlined,
                    title: 'Offline-Pruefung',
                    body: 'Zeitlich begrenzte Tokens erlauben Check-in, auch wenn Internet in der Halle schlecht ist.',
                    enabled: _offlineVerify,
                    onChanged: (value) => setState(() => _offlineVerify = value),
                  ),
                  _CardToggle(
                    icon: Icons.event_available_outlined,
                    title: 'Event-Check-in',
                    body: 'Trainings, Events, Kurse und Wettkaempfe koennen Anwesenheit direkt aus der Karte erfassen.',
                    enabled: _eventCheckin,
                    onChanged: (value) => setState(() => _eventCheckin = value),
                  ),
                  _CardToggle(
                    icon: Icons.privacy_tip_outlined,
                    title: 'Datensparsame Anzeige',
                    body: 'QR-Scan zeigt Status, Verein, Rolle und Ablauf, aber keine sensiblen Profil- oder Zahlungsdaten.',
                    enabled: _privacyMinimal,
                    onChanged: (value) => setState(() => _privacyMinimal = value),
                    last: true,
                  ),
                ],
              ),
            ),
            const SizedBox(height: 14),
            for (final card in cards) ...[
              _CheckinCard(card: card),
              const SizedBox(height: 12),
            ],
            AirmiusPanel(
              borderColor: AirmiusColors.green.withValues(alpha: .45),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Eyebrow('CHECK-IN RESULT'),
                  const SizedBox(height: 8),
                  const Text('ZBB Konto wurde erfolgreich fuer Training U16 verifiziert. Status: Mitglied aktiv. Zahlungsstatus: intern verborgen.', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900, height: 1.38)),
                  const SizedBox(height: 10),
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: const [
                      StatusPill('Verified', color: AirmiusColors.green),
                      StatusPill('Minimal data', color: AirmiusColors.blue),
                      StatusPill('Audit saved', color: AirmiusColors.amber),
                    ],
                  ),
                  const SizedBox(height: 12),
                  AirmiusButton(label: 'Anwesenheit speichern', icon: Icons.check_circle_outline, onPressed: () {}),
                ],
              ),
            ),
            const SizedBox(height: 14),
            AirmiusPanel(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Eyebrow('API CARD PAYLOAD'),
                  const SizedBox(height: 10),
                  const _PayloadLine(label: 'member_card_id', value: 'club_26_member_1042'),
                  const _PayloadLine(label: 'visible_claims', value: 'name, club, role, status, expires_at'),
                  const _PayloadLine(label: 'hidden_claims', value: 'payment_status, address, guardian_contact, medical_notes'),
                  const _PayloadLine(label: 'actions', value: 'verify, check_in, revoke_token, rotate_qr'),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _MemberCardPreview extends StatelessWidget {
  const _MemberCardPreview({required this.qrEnabled});

  final bool qrEnabled;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      borderColor: AirmiusColors.blue.withValues(alpha: .45),
      child: Container(
        padding: const EdgeInsets.all(18),
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(20),
          gradient: const LinearGradient(colors: [Color(0xFF101A2A), Color(0xFF1D5FA8)], begin: Alignment.topLeft, end: Alignment.bottomRight),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Row(
              children: [
                const Expanded(child: Eyebrow('AIRMIUS MEMBER CARD')),
                StatusPill(qrEnabled ? 'QR aktiv' : 'QR aus', color: qrEnabled ? AirmiusColors.green : AirmiusColors.amber),
              ],
            ),
            const SizedBox(height: 16),
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Container(
                  width: 70,
                  height: 70,
                  decoration: BoxDecoration(color: Colors.white.withValues(alpha: .12), borderRadius: BorderRadius.circular(18), border: Border.all(color: Colors.white24)),
                  child: const Center(child: Text('ZK', style: TextStyle(color: Colors.white, fontSize: 24, fontWeight: FontWeight.w900))),
                ),
                const SizedBox(width: 14),
                const Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text('ZBB Konto', style: TextStyle(color: Colors.white, fontSize: 21, fontWeight: FontWeight.w900)),
                      SizedBox(height: 5),
                      Text('ZBB · Mitglied aktiv\nGueltig bis 31.12.2026', style: TextStyle(color: Colors.white70, height: 1.35, fontWeight: FontWeight.w700)),
                    ],
                  ),
                ),
                Container(
                  width: 64,
                  height: 64,
                  decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(14)),
                  child: const Icon(Icons.qr_code_2_outlined, color: AirmiusColors.bg, size: 44),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _CardMode {
  const _CardMode({required this.mode, required this.title, required this.body, required this.status, required this.icon, required this.color});

  final String mode;
  final String title;
  final String body;
  final String status;
  final IconData icon;
  final Color color;
}

const _cards = [
  _CardMode(mode: 'Mitgliedskarte', title: 'Aktive Vereinsmitgliedschaft', body: 'Zeigt Verein, Rolle, Status, Gueltigkeit und QR-Token fuer berechtigte Scans.', status: 'Aktiv', icon: Icons.badge_outlined, color: AirmiusColors.green),
  _CardMode(mode: 'Check-in', title: 'Training Check-in', body: 'Mitglied kann sich bei Training, Kurs oder Event anmelden und Anwesenheit bestaetigen.', status: 'Bereit', icon: Icons.event_available_outlined, color: AirmiusColors.blue),
  _CardMode(mode: 'Trainer Scan', title: 'Trainer Verifikation', body: 'Trainer scannt QR und sieht nur minimalen Status plus passende Teamrolle.', status: 'Minimal', icon: Icons.verified_user_outlined, color: AirmiusColors.green),
  _CardMode(mode: 'Offline', title: 'Offline Token', body: 'Kurzlebiger Token erlaubt Hallen-Check-in ohne stabile Verbindung und synchronisiert spaeter.', status: 'Expires', icon: Icons.wifi_off_outlined, color: AirmiusColors.amber),
  _CardMode(mode: 'Historie', title: 'Check-in Verlauf', body: 'User und Verein sehen Teilnahmeverlauf rollenbasiert und datenschutzkonform.', status: 'Audit', icon: Icons.history_outlined, color: AirmiusColors.blue),
];

class _CheckinCard extends StatelessWidget {
  const _CheckinCard({required this.card});

  final _CardMode card;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      borderColor: card.color.withValues(alpha: .42),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 48,
            height: 48,
            decoration: BoxDecoration(color: card.color.withValues(alpha: .14), borderRadius: BorderRadius.circular(16), border: Border.all(color: card.color.withValues(alpha: .42))),
            child: Icon(card.icon, color: card.color),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Expanded(child: Text(card.title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900))),
                    StatusPill(card.status, color: card.color),
                  ],
                ),
                const SizedBox(height: 6),
                Text(card.body, style: const TextStyle(color: AirmiusColors.muted, height: 1.35)),
                const SizedBox(height: 9),
                StatusPill(card.mode, color: card.color),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _CardToggle extends StatelessWidget {
  const _CardToggle({required this.icon, required this.title, required this.body, required this.enabled, required this.onChanged, this.last = false});

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
            SizedBox(width: 120, child: Text(label, style: const TextStyle(color: AirmiusColors.blue, fontWeight: FontWeight.w900))),
            Expanded(child: Text(value, style: const TextStyle(color: AirmiusColors.text, height: 1.35))),
          ],
        ),
      ),
    );
  }
}
