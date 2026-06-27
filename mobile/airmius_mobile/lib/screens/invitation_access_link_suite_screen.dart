import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

class InvitationAccessLinkSuiteScreen extends StatefulWidget {
  const InvitationAccessLinkSuiteScreen({super.key});

  @override
  State<InvitationAccessLinkSuiteScreen> createState() => _InvitationAccessLinkSuiteScreenState();
}

class _InvitationAccessLinkSuiteScreenState extends State<InvitationAccessLinkSuiteScreen> {
  String _target = 'Mitglied';
  bool _emailInvite = true;
  bool _qrInvite = true;
  bool _roleBound = true;
  bool _expiry = true;

  @override
  Widget build(BuildContext context) {
    final invites = _invites.where((invite) => _target == 'Alle' || invite.target == _target).toList();

    return Scaffold(
      backgroundColor: AirmiusColors.bg,
      appBar: AppBar(
        backgroundColor: AirmiusColors.header,
        surfaceTintColor: Colors.transparent,
        title: const Text('Einladungen', style: TextStyle(fontWeight: FontWeight.w900)),
      ),
      body: PageFrame(
        title: 'Invitation Access Links',
        subtitle: 'Mobile UI fuer Einladungen, QR-Codes, Zugangslinks, Rollenbindung, Ablauf, Widerruf und Annahmestatus.',
        trailing: const StatusPill('Invite flow', color: AirmiusColors.blue),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            AirmiusPanel(
              gradient: true,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Eyebrow('ACCESS LINKS'),
                  const SizedBox(height: 8),
                  const Text(
                    'Vereine koennen Menschen gezielt einladen.',
                    style: TextStyle(color: AirmiusColors.text, fontSize: 24, fontWeight: FontWeight.w900, height: 1.08),
                  ),
                  const SizedBox(height: 8),
                  const Text(
                    'Die App bereitet Einladungen per E-Mail, QR-Code, Link und Rolle vor, damit Mitglieder, Trainer, Guardians und externe Kontakte sauber in den richtigen Workspace kommen.',
                    style: TextStyle(color: AirmiusColors.muted, height: 1.42),
                  ),
                  const SizedBox(height: 14),
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: ['Alle', 'Mitglied', 'Trainer', 'Guardian', 'Team', 'Sponsor', 'Extern'].map((item) {
                      return ChoiceChip(
                        selected: _target == item,
                        label: Text(item),
                        onSelected: (_) => setState(() => _target = item),
                        selectedColor: AirmiusColors.blue.withValues(alpha: .22),
                        backgroundColor: AirmiusColors.panelSoft,
                        side: BorderSide(color: _target == item ? AirmiusColors.blue : AirmiusColors.border),
                        labelStyle: TextStyle(color: _target == item ? AirmiusColors.text : AirmiusColors.muted, fontWeight: FontWeight.w900),
                      );
                    }).toList(),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 14),
            Row(
              children: const [
                Expanded(child: MetricCard(value: '14', label: 'Offen')),
                SizedBox(width: 10),
                Expanded(child: MetricCard(value: '8', label: 'Akzeptiert')),
                SizedBox(width: 10),
                Expanded(child: MetricCard(value: '3d', label: 'Ablauf')),
              ],
            ),
            const SizedBox(height: 14),
            AirmiusPanel(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Row(children: [const Expanded(child: Eyebrow('EINLADUNGSREGELN')), StatusPill(_target, color: AirmiusColors.blue)]),
                  const SizedBox(height: 12),
                  _InviteToggle(
                    icon: Icons.mail_outline,
                    title: 'E-Mail Einladung',
                    body: 'Einladung mit Rollenhinweis, Clubname, Ablaufdatum und sicherem Annahmelink.',
                    enabled: _emailInvite,
                    onChanged: (value) => setState(() => _emailInvite = value),
                  ),
                  _InviteToggle(
                    icon: Icons.qr_code_2_outlined,
                    title: 'QR-Code Beitritt',
                    body: 'Trainer oder Vereinsadmins koennen vor Ort QR-Codes fuer Team, Event oder Mitgliedschaft zeigen.',
                    enabled: _qrInvite,
                    onChanged: (value) => setState(() => _qrInvite = value),
                  ),
                  _InviteToggle(
                    icon: Icons.admin_panel_settings_outlined,
                    title: 'Rollenbindung',
                    body: 'Links koennen nur fuer Mitglied, Trainer, Guardian, Sponsor oder externe Kontakte gelten.',
                    enabled: _roleBound,
                    onChanged: (value) => setState(() => _roleBound = value),
                  ),
                  _InviteToggle(
                    icon: Icons.timer_outlined,
                    title: 'Ablauf & Widerruf',
                    body: 'Einladungen laufen automatisch ab und koennen jederzeit vom Verein widerrufen werden.',
                    enabled: _expiry,
                    onChanged: (value) => setState(() => _expiry = value),
                    last: true,
                  ),
                ],
              ),
            ),
            const SizedBox(height: 14),
            AirmiusPanel(
              borderColor: AirmiusColors.blue.withValues(alpha: .42),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Eyebrow('QR & LINK VORSCHAU'),
                  const SizedBox(height: 12),
                  Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Container(
                        width: 92,
                        height: 92,
                        decoration: BoxDecoration(
                          color: AirmiusColors.cardSoft,
                          borderRadius: BorderRadius.circular(20),
                          border: Border.all(color: AirmiusColors.blue.withValues(alpha: .45)),
                        ),
                        child: const Icon(Icons.qr_code_2_outlined, color: AirmiusColors.blue, size: 54),
                      ),
                      const SizedBox(width: 14),
                      const Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text('ZBB Einladung', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
                            SizedBox(height: 6),
                            Text('Rolle: Mitglied\nAblauf: 3 Tage\nAktion: Antrag starten oder Konto verbinden', style: TextStyle(color: AirmiusColors.muted, height: 1.35)),
                          ],
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 12),
                  AirmiusButton(label: 'Einladung teilen', icon: Icons.ios_share_outlined, onPressed: () {}),
                ],
              ),
            ),
            const SizedBox(height: 14),
            for (final invite in invites) ...[
              _InviteCard(invite: invite),
              const SizedBox(height: 12),
            ],
            AirmiusPanel(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Eyebrow('API PAYLOAD'),
                  const SizedBox(height: 10),
                  const _PayloadLine(label: 'invite_type', value: 'club_member, coach, guardian, sponsor, external'),
                  const _PayloadLine(label: 'delivery', value: 'email, qr, share_link, in_app'),
                  const _PayloadLine(label: 'security', value: 'expires_at, single_use, role_bound, revocable'),
                  const _PayloadLine(label: 'audit', value: 'created_by, accepted_at, revoked_at, target_workspace'),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _Invite {
  const _Invite({required this.target, required this.title, required this.body, required this.status, required this.icon, required this.color});

  final String target;
  final String title;
  final String body;
  final String status;
  final IconData icon;
  final Color color;
}

const _invites = [
  _Invite(target: 'Mitglied', title: 'Max Mustermann', body: 'Mitgliedschaftseinladung fuer ZBB, Link laeuft in 3 Tagen ab.', status: 'Offen', icon: Icons.person_add_alt_1_outlined, color: AirmiusColors.blue),
  _Invite(target: 'Trainer', title: 'Coach Einladung', body: 'Trainerrolle mit Teamzugriff, Kader und Anwesenheitsrechten.', status: 'Akzeptiert', icon: Icons.sports_outlined, color: AirmiusColors.green),
  _Invite(target: 'Guardian', title: 'Elternfreigabe', body: 'Guardian-Link fuer Minderjaehrigenprofil und Consent-Prüfung.', status: 'Wartet', icon: Icons.family_restroom_outlined, color: AirmiusColors.amber),
  _Invite(target: 'Team', title: 'U16 Team QR', body: 'QR-Code fuer Teambeitritt nach Training, nur fuer Vereinsmitglieder.', status: 'Aktiv', icon: Icons.groups_2_outlined, color: AirmiusColors.green),
  _Invite(target: 'Sponsor', title: 'Sponsor Workspace', body: 'Einladung fuer Kampagnen, Placements und Reporting-Zugriff.', status: 'Offen', icon: Icons.campaign_outlined, color: AirmiusColors.blue),
  _Invite(target: 'Extern', title: 'Externer Kontakt', body: 'Kontakt ohne volles Mitgliedskonto fuer Kommunikation und Dokumentfreigabe.', status: 'Begrenzt', icon: Icons.link_outlined, color: AirmiusColors.amber),
];

class _InviteCard extends StatelessWidget {
  const _InviteCard({required this.invite});

  final _Invite invite;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      borderColor: invite.color.withValues(alpha: .42),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 48,
            height: 48,
            decoration: BoxDecoration(color: invite.color.withValues(alpha: .14), borderRadius: BorderRadius.circular(16), border: Border.all(color: invite.color.withValues(alpha: .42))),
            child: Icon(invite.icon, color: invite.color),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Expanded(child: Text(invite.title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900))),
                    StatusPill(invite.status, color: invite.color),
                  ],
                ),
                const SizedBox(height: 6),
                Text(invite.body, style: const TextStyle(color: AirmiusColors.muted, height: 1.35)),
                const SizedBox(height: 9),
                Wrap(spacing: 8, runSpacing: 8, children: [StatusPill(invite.target, color: invite.color), const StatusPill('Revocable')]),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _InviteToggle extends StatelessWidget {
  const _InviteToggle({required this.icon, required this.title, required this.body, required this.enabled, required this.onChanged, this.last = false});

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
