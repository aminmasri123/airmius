import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'support_helpdesk_screen.dart';

class AuthAccountAccessCenterScreen extends StatefulWidget {
  const AuthAccountAccessCenterScreen({super.key});

  @override
  State<AuthAccountAccessCenterScreen> createState() => _AuthAccountAccessCenterScreenState();
}

class _AuthAccountAccessCenterScreenState extends State<AuthAccountAccessCenterScreen> {
  String _flow = 'Login';
  bool _emailVerified = true;
  bool _profileComplete = false;
  bool _twoFactorEnabled = true;
  bool _passwordResetAllowed = true;
  bool _suspendedNotice = false;

  final List<_AuthFlowItem> _items = const [
    _AuthFlowItem(title: 'Login', body: 'E-Mail, Passwort, Remember me, Weiterleitung und Fehlermeldungen.', status: 'Basis', icon: Icons.login_outlined, color: AirmiusColors.blue),
    _AuthFlowItem(title: 'Registrierung', body: 'Neues Konto, Rolle, Sprache, Datenschutz und Profilstart.', status: 'Public', icon: Icons.person_add_outlined, color: AirmiusColors.green),
    _AuthFlowItem(title: 'Profil vervollstaendigen', body: 'CompleteProfile mit Name, Rolle, Kontaktdaten und Onboarding-Hinweis.', status: 'Pflicht', icon: Icons.assignment_ind_outlined, color: AirmiusColors.amber),
    _AuthFlowItem(title: 'E-Mail verifizieren', body: 'VerifyEmail mit Status, erneut senden und naechstem Schritt.', status: 'Sicherheit', icon: Icons.mark_email_read_outlined, color: AirmiusColors.blueDeep),
    _AuthFlowItem(title: 'Passwort zurücksetzen', body: 'ForgotPassword, ResetPassword und ConfirmPassword als mobile Form-Flows.', status: 'Recovery', icon: Icons.lock_reset_outlined, color: AirmiusColors.red),
    _AuthFlowItem(title: 'Two-Factor Challenge', body: '2FA-Code, Recovery-Code, Sicherheitshinweis und Support-Option.', status: '2FA', icon: Icons.phonelink_lock_outlined, color: AirmiusColors.blue),
    _AuthFlowItem(title: 'Konto gesperrt', body: 'Suspended-Seite mit Grund, Supportkontakt, Status und naechster Aktion.', status: 'Sperre', icon: Icons.block_outlined, color: AirmiusColors.red),
  ];

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AirmiusColors.bg,
      body: SafeArea(
        child: CustomScrollView(
          slivers: [
            SliverToBoxAdapter(
              child: Padding(
                padding: const EdgeInsets.fromLTRB(16, 14, 16, 18),
                child: Center(
                  child: ConstrainedBox(
                    constraints: const BoxConstraints(maxWidth: 760),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        const PageTitle(title: 'Auth & Account-Zugang', subtitle: 'Login, Registrierung, Profilabschluss, E-Mail-Verifizierung, Passwort, 2FA und Sperrstatus.'),
                        const SizedBox(height: 16),
                        _AuthHero(onContinue: () => _toast('Auth-Flow fortsetzen vorbereitet')),
                        const SizedBox(height: 16),
                        _ChoicePanel(title: 'Aktiver Flow', value: _flow, values: const ['Login', 'Register', 'Verify', 'Password', '2FA', 'Suspended'], onChanged: (value) => setState(() => _flow = value)),
                        const SizedBox(height: 16),
                        AirmiusPanel(
                          title: 'Zugangsstatus',
                          child: Column(
                            children: [
                              _SwitchRow(title: 'E-Mail verifiziert', subtitle: 'VerifyEmail ist erledigt oder erneut anfordern.', value: _emailVerified, onChanged: (value) => setState(() => _emailVerified = value)),
                              _SwitchRow(title: 'Profil vollstaendig', subtitle: 'CompleteProfile mit Pflichtdaten und Onboarding abgeschlossen.', value: _profileComplete, onChanged: (value) => setState(() => _profileComplete = value)),
                              _SwitchRow(title: '2FA aktiv', subtitle: 'TwoFactorChallenge und Recovery-Code als mobile UI vorbereitet.', value: _twoFactorEnabled, onChanged: (value) => setState(() => _twoFactorEnabled = value)),
                              _SwitchRow(title: 'Passwort-Reset erlaubt', subtitle: 'ForgotPassword, ResetPassword und ConfirmPassword aktiv.', value: _passwordResetAllowed, onChanged: (value) => setState(() => _passwordResetAllowed = value)),
                              _SwitchRow(title: 'Sperrhinweis anzeigen', subtitle: 'Suspended-Seite mit Grund und Supportkontakt sichtbar.', value: _suspendedNotice, onChanged: (value) => setState(() => _suspendedNotice = value)),
                            ],
                          ),
                        ),
                        const SizedBox(height: 16),
                        for (final item in _items) ...[
                          _AuthFlowCard(item: item, onOpen: () => _toast('${item.title}: UI-Flow vorbereitet')),
                          const SizedBox(height: 12),
                        ],
                        AirmiusPanel(
                          title: 'Aktionen',
                          child: Wrap(
                            spacing: 10,
                            runSpacing: 10,
                            children: [
                              AirmiusButton(label: 'Flow testen', icon: Icons.play_arrow_outlined, onPressed: () => _toast('Auth-Flow testen vorbereitet')),
                              AirmiusButton(label: 'Support', icon: Icons.support_agent_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => SupportHelpdeskScreen()))),
                            ],
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  void _toast(String message) {
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(message)));
  }
}

class _AuthHero extends StatelessWidget {
  const _AuthHero({required this.onContinue});

  final VoidCallback onContinue;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        gradient: const LinearGradient(colors: [Color(0xFF10243B), Color(0xFF0B111B)], begin: Alignment.topLeft, end: Alignment.bottomRight),
        borderRadius: BorderRadius.circular(22),
        border: Border.all(color: AirmiusColors.borderStrong),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              const AirmiusLogo(size: 42),
              const SizedBox(width: 12),
              const Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Eyebrow('AUTH'), SizedBox(height: 4), Text('Zugang sicher und mobil', style: TextStyle(color: AirmiusColors.text, fontSize: 22, fontWeight: FontWeight.w900))])),
              AirmiusButton(label: 'Weiter', icon: Icons.arrow_forward_outlined, onPressed: onContinue),
            ],
          ),
          const SizedBox(height: 14),
          const Text('Die Auth-Webseiten werden als mobile UI abgebildet: Login, Register, CompleteProfile, VerifyEmail, Passwort-Reset, 2FA und Suspended.', style: TextStyle(color: AirmiusColors.muted, height: 1.45, fontWeight: FontWeight.w700)),
          const SizedBox(height: 16),
          const Row(children: [Expanded(child: MetricCard(value: '7', label: 'Flows')), SizedBox(width: 10), Expanded(child: MetricCard(value: '2', label: 'Security')), SizedBox(width: 10), Expanded(child: MetricCard(value: '1', label: 'Support'))]),
        ],
      ),
    );
  }
}

class _ChoicePanel extends StatelessWidget {
  const _ChoicePanel({required this.title, required this.value, required this.values, required this.onChanged});

  final String title;
  final String value;
  final List<String> values;
  final ValueChanged<String> onChanged;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      title: title,
      child: Wrap(
        spacing: 8,
        runSpacing: 8,
        children: [
          for (final item in values)
            ChoiceChip(
              label: Text(item),
              selected: value == item,
              onSelected: (_) => onChanged(item),
              selectedColor: AirmiusColors.blue.withValues(alpha: .24),
              backgroundColor: AirmiusColors.card,
              labelStyle: TextStyle(color: value == item ? AirmiusColors.text : AirmiusColors.muted, fontWeight: FontWeight.w900),
              side: BorderSide(color: value == item ? AirmiusColors.blue : AirmiusColors.border),
            ),
        ],
      ),
    );
  }
}

class _SwitchRow extends StatelessWidget {
  const _SwitchRow({required this.title, required this.subtitle, required this.value, required this.onChanged});

  final String title;
  final String subtitle;
  final bool value;
  final ValueChanged<bool> onChanged;

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(color: AirmiusColors.input, borderRadius: BorderRadius.circular(16), border: Border.all(color: AirmiusColors.border)),
      child: Row(children: [
        Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text(title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), const SizedBox(height: 4), Text(subtitle, style: const TextStyle(color: AirmiusColors.muted, fontSize: 12, height: 1.35, fontWeight: FontWeight.w700))])),
        Switch.adaptive(value: value, onChanged: onChanged, activeThumbColor: AirmiusColors.blue),
      ]),
    );
  }
}

class _AuthFlowCard extends StatelessWidget {
  const _AuthFlowCard({required this.item, required this.onOpen});

  final _AuthFlowItem item;
  final VoidCallback onOpen;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      title: item.title,
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(width: 48, height: 48, decoration: BoxDecoration(color: item.color.withValues(alpha: .18), borderRadius: BorderRadius.circular(16), border: Border.all(color: item.color.withValues(alpha: .5))), child: Icon(item.icon, color: item.color)),
          const SizedBox(width: 12),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [StatusPill(item.status, color: item.color), const SizedBox(height: 8), Text(item.body, style: const TextStyle(color: AirmiusColors.muted, height: 1.45, fontWeight: FontWeight.w700))])),
          IconButton(onPressed: onOpen, icon: const Icon(Icons.chevron_right, color: AirmiusColors.muted)),
        ],
      ),
    );
  }
}

class _AuthFlowItem {
  const _AuthFlowItem({required this.title, required this.body, required this.status, required this.icon, required this.color});

  final String title;
  final String body;
  final String status;
  final IconData icon;
  final Color color;
}
