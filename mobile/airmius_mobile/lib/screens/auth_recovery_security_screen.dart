import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'support_helpdesk_screen.dart';

class AuthRecoverySecurityScreen extends StatefulWidget {
  const AuthRecoverySecurityScreen({super.key});

  @override
  State<AuthRecoverySecurityScreen> createState() => _AuthRecoverySecurityScreenState();
}

class _AuthRecoverySecurityScreenState extends State<AuthRecoverySecurityScreen> {
  String _filter = 'Alle';
  bool _showRecovery = true;
  bool _showSecurity = true;
  bool _showAccountState = true;

  final List<_AuthFlow> _flows = const [
    _AuthFlow(
      title: 'Profil vervollstaendigen',
      area: 'Profil',
      status: 'Pflicht',
      body: 'Mobile UI fuer fehlende Profildaten, Rolle, Verein, Standort und erste Sicherheitspruefung.',
      icon: Icons.assignment_ind_outlined,
      color: Color(0xFF5BA7FF),
    ),
    _AuthFlow(
      title: 'Passwort vergessen',
      area: 'Recovery',
      status: 'E-Mail',
      body: 'Anfrage fuer Passwort-Reset mit E-Mail, Sicherheitsmeldung und Rueckkehr zum Login.',
      icon: Icons.lock_reset_outlined,
      color: Color(0xFF2EE59D),
    ),
    _AuthFlow(
      title: 'Passwort zuruecksetzen',
      area: 'Recovery',
      status: 'Token',
      body: 'Reset-Formular mit Token, neuem Passwort, Bestaetigung und Erfolgsmeldung.',
      icon: Icons.password_outlined,
      color: Color(0xFF2EE59D),
    ),
    _AuthFlow(
      title: 'Passwort bestaetigen',
      area: 'Security',
      status: 'Check',
      body: 'Sicherheitsabfrage vor sensiblen Aktionen wie Konto loeschen, 2FA oder Zahlungsdaten.',
      icon: Icons.enhanced_encryption_outlined,
      color: Color(0xFFF8B84E),
    ),
    _AuthFlow(
      title: 'Zwei-Faktor Challenge',
      area: 'Security',
      status: '2FA',
      body: 'Code-Eingabe, Recovery-Code Umschaltung und klare Fehlermeldungen fuer Login-Schutz.',
      icon: Icons.pin_outlined,
      color: Color(0xFFF8B84E),
    ),
    _AuthFlow(
      title: 'E-Mail verifizieren',
      area: 'Security',
      status: 'Verify',
      body: 'Hinweise, erneutes Senden der Verifizierung und Status, ob der Account freigeschaltet ist.',
      icon: Icons.mark_email_read_outlined,
      color: Color(0xFFB084FF),
    ),
    _AuthFlow(
      title: 'Account suspendiert',
      area: 'Status',
      status: 'Gesperrt',
      body: 'Mobile Sperrseite mit Grund, Support-Kontakt, Einspruch und sicherer Abmeldung.',
      icon: Icons.block_outlined,
      color: Color(0xFFFF6B6B),
    ),
  ];

  List<_AuthFlow> get _visibleFlows {
    if (_filter == 'Alle') return _flows;
    return _flows.where((flow) => flow.area == _filter).toList();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFF070B12),
      body: SafeArea(
        child: CustomScrollView(
          slivers: [
            SliverPadding(
              padding: const EdgeInsets.fromLTRB(16, 12, 16, 24),
              sliver: SliverToBoxAdapter(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    _TopBar(onSupport: () => _openSupport(context)),
                    const SizedBox(height: 18),
                    const _IntroPanel(),
                    const SizedBox(height: 18),
                    Row(
                      children: const [
                        Expanded(child: _MetricCard(value: '7', label: 'Flows')),
                        SizedBox(width: 10),
                        Expanded(child: _MetricCard(value: '3', label: 'Security')),
                        SizedBox(width: 10),
                        Expanded(child: _MetricCard(value: '2', label: 'Recovery')),
                      ],
                    ),
                    const SizedBox(height: 18),
                    _FilterTabs(
                      value: _filter,
                      values: const ['Alle', 'Profil', 'Recovery', 'Security', 'Status'],
                      onChanged: (value) => setState(() => _filter = value),
                    ),
                    const SizedBox(height: 14),
                    _VisibilityPanel(
                      showRecovery: _showRecovery,
                      showSecurity: _showSecurity,
                      showAccountState: _showAccountState,
                      onRecovery: (value) => setState(() => _showRecovery = value),
                      onSecurity: (value) => setState(() => _showSecurity = value),
                      onAccountState: (value) => setState(() => _showAccountState = value),
                    ),
                    const SizedBox(height: 14),
                    for (final flow in _visibleFlows.where(_isVisible)) ...[
                      _AuthFlowCard(flow: flow),
                      const SizedBox(height: 12),
                    ],
                    _ActionPanel(
                      onReset: () => openUiAction(
                        context,
                        title: 'Passwort-Reset',
                        message: 'Hier wird spaeter die Laravel-API fuer Forgot/Reset Password angebunden.',
                      ),
                      onVerify: () => openUiAction(
                        context,
                        title: 'E-Mail erneut senden',
                        message: 'Die mobile UI ist vorbereitet; die API sendet spaeter den Verify-Link.',
                      ),
                      onSupport: () => _openSupport(context),
                    ),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  bool _isVisible(_AuthFlow flow) {
    if (flow.area == 'Recovery') return _showRecovery;
    if (flow.area == 'Security') return _showSecurity;
    return _showAccountState;
  }

  void _openSupport(BuildContext context) {
    Navigator.of(context).push(MaterialPageRoute(builder: (_) => SupportHelpdeskScreen()));
  }
}

class _AuthFlow {
  const _AuthFlow({
    required this.title,
    required this.area,
    required this.status,
    required this.body,
    required this.icon,
    required this.color,
  });

  final String title;
  final String area;
  final String status;
  final String body;
  final IconData icon;
  final Color color;
}

class _TopBar extends StatelessWidget {
  const _TopBar({required this.onSupport});

  final VoidCallback onSupport;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        const AirmiusLogo(markOnly: true, size: 34),
        const SizedBox(width: 10),
        const Expanded(child: Text('Airmius', style: TextStyle(color: Colors.white, fontSize: 20, fontWeight: FontWeight.w900))),
        IconButton(onPressed: onSupport, icon: const Icon(Icons.support_agent_outlined, color: Color(0xFFAFC0D8))),
      ],
    );
  }
}

class _IntroPanel extends StatelessWidget {
  const _IntroPanel();

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(24),
        border: Border.all(color: const Color(0xFF26364D)),
        gradient: const LinearGradient(colors: [Color(0xFF121A27), Color(0xFF0B111B)], begin: Alignment.topLeft, end: Alignment.bottomRight),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: const [
          Text('AUTH SECURITY', style: TextStyle(color: Color(0xFF5BA7FF), fontSize: 12, fontWeight: FontWeight.w900)),
          SizedBox(height: 8),
          Text('Login-Randfaelle', style: TextStyle(color: Colors.white, fontSize: 28, fontWeight: FontWeight.w900)),
          SizedBox(height: 8),
          Text(
            'Mobile UI fuer Complete Profile, Forgot/Reset Password, Confirm Password, 2FA, Verify Email und Suspended.',
            style: TextStyle(color: Color(0xFFAFC0D8), height: 1.45, fontWeight: FontWeight.w600),
          ),
        ],
      ),
    );
  }
}

class _MetricCard extends StatelessWidget {
  const _MetricCard({required this.value, required this.label});

  final String value;
  final String label;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(color: const Color(0xFF101722), borderRadius: BorderRadius.circular(18), border: Border.all(color: const Color(0xFF26364D))),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(value, style: const TextStyle(color: Colors.white, fontSize: 24, fontWeight: FontWeight.w900)),
          const SizedBox(height: 4),
          Text(label, style: const TextStyle(color: Color(0xFFAFC0D8), fontWeight: FontWeight.w700)),
        ],
      ),
    );
  }
}

class _FilterTabs extends StatelessWidget {
  const _FilterTabs({required this.value, required this.values, required this.onChanged});

  final String value;
  final List<String> values;
  final ValueChanged<String> onChanged;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 42,
      child: ListView.separated(
        scrollDirection: Axis.horizontal,
        itemCount: values.length,
        separatorBuilder: (_, __) => const SizedBox(width: 8),
        itemBuilder: (context, index) {
          final item = values[index];
          final active = item == value;
          return ChoiceChip(
            label: Text(item),
            selected: active,
            onSelected: (_) => onChanged(item),
            labelStyle: TextStyle(color: active ? Colors.white : const Color(0xFFAFC0D8), fontWeight: FontWeight.w900),
            selectedColor: const Color(0xFF173D68),
            backgroundColor: const Color(0xFF101722),
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(999), side: const BorderSide(color: Color(0xFF26364D))),
          );
        },
      ),
    );
  }
}

class _VisibilityPanel extends StatelessWidget {
  const _VisibilityPanel({
    required this.showRecovery,
    required this.showSecurity,
    required this.showAccountState,
    required this.onRecovery,
    required this.onSecurity,
    required this.onAccountState,
  });

  final bool showRecovery;
  final bool showSecurity;
  final bool showAccountState;
  final ValueChanged<bool> onRecovery;
  final ValueChanged<bool> onSecurity;
  final ValueChanged<bool> onAccountState;

  @override
  Widget build(BuildContext context) {
    return _Panel(
      title: 'Mobile Zustandsgruppen',
      child: Column(
        children: [
          _SwitchRow(label: 'Recovery anzeigen', value: showRecovery, onChanged: onRecovery),
          _SwitchRow(label: 'Security anzeigen', value: showSecurity, onChanged: onSecurity),
          _SwitchRow(label: 'Account-Status anzeigen', value: showAccountState, onChanged: onAccountState),
        ],
      ),
    );
  }
}

class _AuthFlowCard extends StatelessWidget {
  const _AuthFlowCard({required this.flow});

  final _AuthFlow flow;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(color: const Color(0xFF101722), borderRadius: BorderRadius.circular(24), border: Border.all(color: const Color(0xFF26364D))),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 54,
            height: 54,
            decoration: BoxDecoration(color: flow.color.withOpacity(.14), borderRadius: BorderRadius.circular(16), border: Border.all(color: flow.color.withOpacity(.45))),
            child: Icon(flow.icon, color: flow.color, size: 28),
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Expanded(child: Text(flow.title, style: const TextStyle(color: Colors.white, fontSize: 18, fontWeight: FontWeight.w900))),
                    _Pill(label: flow.status, color: flow.color),
                  ],
                ),
                const SizedBox(height: 8),
                Text(flow.body, style: const TextStyle(color: Color(0xFFDDE7F5), height: 1.45, fontWeight: FontWeight.w600)),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _ActionPanel extends StatelessWidget {
  const _ActionPanel({required this.onReset, required this.onVerify, required this.onSupport});

  final VoidCallback onReset;
  final VoidCallback onVerify;
  final VoidCallback onSupport;

  @override
  Widget build(BuildContext context) {
    return _Panel(
      title: 'Schnellaktionen',
      child: Column(
        children: [
          _ActionButton(icon: Icons.lock_reset_outlined, label: 'Passwort-Reset starten', onTap: onReset),
          const SizedBox(height: 10),
          _ActionButton(icon: Icons.mark_email_read_outlined, label: 'Verify-Mail senden', onTap: onVerify),
          const SizedBox(height: 10),
          _ActionButton(icon: Icons.support_agent_outlined, label: 'Support kontaktieren', onTap: onSupport),
        ],
      ),
    );
  }
}

class _Panel extends StatelessWidget {
  const _Panel({required this.title, required this.child});

  final String title;
  final Widget child;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(color: const Color(0xFF0D131D), borderRadius: BorderRadius.circular(22), border: Border.all(color: const Color(0xFF26364D))),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(title, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w900)),
          const SizedBox(height: 12),
          child,
        ],
      ),
    );
  }
}

class _SwitchRow extends StatelessWidget {
  const _SwitchRow({required this.label, required this.value, required this.onChanged});

  final String label;
  final bool value;
  final ValueChanged<bool> onChanged;

  @override
  Widget build(BuildContext context) {
    return SwitchListTile.adaptive(
      value: value,
      onChanged: onChanged,
      dense: true,
      contentPadding: EdgeInsets.zero,
      activeThumbColor: const Color(0xFF5BA7FF),
      title: Text(label, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w800)),
    );
  }
}

class _ActionButton extends StatelessWidget {
  const _ActionButton({required this.icon, required this.label, required this.onTap});

  final IconData icon;
  final String label;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(16),
      child: Container(
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(color: const Color(0xFF111A27), borderRadius: BorderRadius.circular(16), border: Border.all(color: const Color(0xFF26364D))),
        child: Row(
          children: [
            Icon(icon, color: AirmiusColors.blue),
            const SizedBox(width: 12),
            Expanded(child: Text(label, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w900))),
            const Icon(Icons.chevron_right, color: Color(0xFFAFC0D8)),
          ],
        ),
      ),
    );
  }
}

class _Pill extends StatelessWidget {
  const _Pill({required this.label, required this.color});

  final String label;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
      decoration: BoxDecoration(color: color.withOpacity(.12), borderRadius: BorderRadius.circular(999), border: Border.all(color: color.withOpacity(.55))),
      child: Text(label, style: TextStyle(color: color, fontSize: 12, fontWeight: FontWeight.w900)),
    );
  }
}
