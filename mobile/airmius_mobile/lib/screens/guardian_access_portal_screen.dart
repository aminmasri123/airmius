import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'guardian_center_screen.dart';
import 'support_helpdesk_screen.dart';

class GuardianAccessPortalScreen extends StatefulWidget {
  const GuardianAccessPortalScreen({super.key});

  @override
  State<GuardianAccessPortalScreen> createState() => _GuardianAccessPortalScreenState();
}

class _GuardianAccessPortalScreenState extends State<GuardianAccessPortalScreen> {
  String _active = 'Übersicht';
  bool _guardianVerified = false;
  bool _mailConfirmed = true;
  bool _childLinked = true;

  final List<_GuardianStep> _steps = const [
    _GuardianStep(
      title: 'Elternkonto erstellen',
      area: 'Create',
      status: 'Start',
      description: 'Registrierung für Erziehungsberechtigte mit Name, E-Mail, Passwort und Consent-Hinweisen.',
      icon: Icons.person_add_alt_1_outlined,
      color: Color(0xFF5BA7FF),
    ),
    _GuardianStep(
      title: 'Guardian Login',
      area: 'Login',
      status: 'Sicher',
      description: 'Separater Login für Eltern, damit Kinderkonten, Einwilligungen und Nachrichten geschuetzt bleiben.',
      icon: Icons.login_outlined,
      color: Color(0xFF2EE59D),
    ),
    _GuardianStep(
      title: 'Verifizierung',
      area: 'Verify',
      status: 'Prüfen',
      description: 'E-Mail, Token, Identitaetsstatus und offene Freigaben werden vor der Nutzung sichtbar gemacht.',
      icon: Icons.verified_outlined,
      color: Color(0xFFF8B84E),
    ),
    _GuardianStep(
      title: 'Kinder verwalten',
      area: 'Children',
      status: 'Aktiv',
      description: 'Kindprofile, Vereinsanfragen, Medienfreigaben, Kursfreigaben und Benachrichtigungen verwalten.',
      icon: Icons.escalator_warning_outlined,
      color: Color(0xFFFF6B6B),
    ),
  ];

  List<_GuardianStep> get _visibleSteps {
    if (_active == 'Übersicht') return _steps;
    return _steps.where((step) => step.area == _active).toList();
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
                    _Header(onSupport: () => _openSupport(context)),
                    const SizedBox(height: 18),
                    const _HeroPanel(),
                    const SizedBox(height: 18),
                    Row(
                      children: const [
                        Expanded(child: _MetricCard(value: '2', label: 'Kinder')),
                        SizedBox(width: 10),
                        Expanded(child: _MetricCard(value: '5', label: 'Freigaben')),
                        SizedBox(width: 10),
                        Expanded(child: _MetricCard(value: '1', label: 'Offen')),
                      ],
                    ),
                    const SizedBox(height: 18),
                    _Tabs(
                      active: _active,
                      values: const ['Übersicht', 'Create', 'Login', 'Verify', 'Children'],
                      onChanged: (value) => setState(() => _active = value),
                    ),
                    const SizedBox(height: 14),
                    _StatePanel(
                      guardianVerified: _guardianVerified,
                      mailConfirmed: _mailConfirmed,
                      childLinked: _childLinked,
                      onGuardianVerified: (value) => setState(() => _guardianVerified = value),
                      onMailConfirmed: (value) => setState(() => _mailConfirmed = value),
                      onChildLinked: (value) => setState(() => _childLinked = value),
                    ),
                    const SizedBox(height: 14),
                    for (final step in _visibleSteps) ...[
                      _StepCard(step: step),
                      const SizedBox(height: 12),
                    ],
                    _ActionPanel(
                      onGuardianCenter: () => Navigator.of(context).push(
                        MaterialPageRoute(builder: (_) => GuardianCenterScreen()),
                      ),
                      onVerify: () => openUiAction(
                        context,
                        title: 'Guardian verifizieren',
                        message: 'Hier wird später die Laravel-API für Guardian-Verify und Consent-Token angebunden.',
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

  void _openSupport(BuildContext context) {
    Navigator.of(context).push(MaterialPageRoute(builder: (_) => SupportHelpdeskScreen()));
  }
}

class _GuardianStep {
  const _GuardianStep({
    required this.title,
    required this.area,
    required this.status,
    required this.description,
    required this.icon,
    required this.color,
  });

  final String title;
  final String area;
  final String status;
  final String description;
  final IconData icon;
  final Color color;
}

class _Header extends StatelessWidget {
  const _Header({required this.onSupport});

  final VoidCallback onSupport;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        const AirmiusLogo(markOnly: true, size: 34),
        const SizedBox(width: 10),
        const Expanded(
          child: Text('Airmius', style: TextStyle(color: Colors.white, fontSize: 20, fontWeight: FontWeight.w900)),
        ),
        IconButton(onPressed: onSupport, icon: const Icon(Icons.support_agent_outlined, color: Color(0xFFAFC0D8))),
      ],
    );
  }
}

class _HeroPanel extends StatelessWidget {
  const _HeroPanel();

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(24),
        border: Border.all(color: const Color(0xFF26364D)),
        gradient: const LinearGradient(
          colors: [Color(0xFF121A27), Color(0xFF0B111B)],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: const [
          Text('GUARDIAN ACCESS', style: TextStyle(color: Color(0xFF5BA7FF), fontSize: 12, fontWeight: FontWeight.w900)),
          SizedBox(height: 8),
          Text('Elternkonto & Kinderfreigaben', style: TextStyle(color: Colors.white, fontSize: 28, fontWeight: FontWeight.w900)),
          SizedBox(height: 8),
          Text(
            'Native Mobile-UI für Guardian Login, Kontoerstellung, Verifizierung, Kinderverwaltung und offene Einwilligungen.',
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
      decoration: BoxDecoration(
        color: const Color(0xFF101722),
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: const Color(0xFF26364D)),
      ),
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

class _Tabs extends StatelessWidget {
  const _Tabs({required this.active, required this.values, required this.onChanged});

  final String active;
  final List<String> values;
  final ValueChanged<String> onChanged;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: 42,
      child: ListView.separated(
        scrollDirection: Axis.horizontal,
        itemCount: values.length,
        separatorBuilder: (_, _) => const SizedBox(width: 8),
        itemBuilder: (context, index) {
          final value = values[index];
          final selected = value == active;
          return ChoiceChip(
            label: Text(value),
            selected: selected,
            onSelected: (_) => onChanged(value),
            labelStyle: TextStyle(color: selected ? Colors.white : const Color(0xFFAFC0D8), fontWeight: FontWeight.w900),
            selectedColor: const Color(0xFF173D68),
            backgroundColor: const Color(0xFF101722),
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(999), side: const BorderSide(color: Color(0xFF26364D))),
          );
        },
      ),
    );
  }
}

class _StatePanel extends StatelessWidget {
  const _StatePanel({
    required this.guardianVerified,
    required this.mailConfirmed,
    required this.childLinked,
    required this.onGuardianVerified,
    required this.onMailConfirmed,
    required this.onChildLinked,
  });

  final bool guardianVerified;
  final bool mailConfirmed;
  final bool childLinked;
  final ValueChanged<bool> onGuardianVerified;
  final ValueChanged<bool> onMailConfirmed;
  final ValueChanged<bool> onChildLinked;

  @override
  Widget build(BuildContext context) {
    return _Panel(
      title: 'Aktueller Status',
      child: Column(
        children: [
          _SwitchRow(label: 'Guardian verifiziert', value: guardianVerified, onChanged: onGuardianVerified),
          _SwitchRow(label: 'E-Mail bestätigt', value: mailConfirmed, onChanged: onMailConfirmed),
          _SwitchRow(label: 'Kind verknuepft', value: childLinked, onChanged: onChildLinked),
        ],
      ),
    );
  }
}

class _StepCard extends StatelessWidget {
  const _StepCard({required this.step});

  final _GuardianStep step;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: const Color(0xFF101722),
        borderRadius: BorderRadius.circular(24),
        border: Border.all(color: const Color(0xFF26364D)),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 54,
            height: 54,
            decoration: BoxDecoration(
              color: step.color.withValues(alpha: .14),
              borderRadius: BorderRadius.circular(16),
              border: Border.all(color: step.color.withValues(alpha: .45)),
            ),
            child: Icon(step.icon, color: step.color, size: 28),
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Expanded(child: Text(step.title, style: const TextStyle(color: Colors.white, fontSize: 18, fontWeight: FontWeight.w900))),
                    _Pill(label: step.status, color: step.color),
                  ],
                ),
                const SizedBox(height: 8),
                Text(step.description, style: const TextStyle(color: Color(0xFFDDE7F5), height: 1.45, fontWeight: FontWeight.w600)),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _ActionPanel extends StatelessWidget {
  const _ActionPanel({required this.onGuardianCenter, required this.onVerify, required this.onSupport});

  final VoidCallback onGuardianCenter;
  final VoidCallback onVerify;
  final VoidCallback onSupport;

  @override
  Widget build(BuildContext context) {
    return _Panel(
      title: 'Schnellaktionen',
      child: Column(
        children: [
          _ActionButton(icon: Icons.family_restroom_outlined, label: 'Guardian Center', onTap: onGuardianCenter),
          const SizedBox(height: 10),
          _ActionButton(icon: Icons.verified_user_outlined, label: 'Verifizierung starten', onTap: onVerify),
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
      decoration: BoxDecoration(
        color: const Color(0xFF0D131D),
        borderRadius: BorderRadius.circular(22),
        border: Border.all(color: const Color(0xFF26364D)),
      ),
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
        decoration: BoxDecoration(
          color: const Color(0xFF111A27),
          borderRadius: BorderRadius.circular(16),
          border: Border.all(color: const Color(0xFF26364D)),
        ),
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
      decoration: BoxDecoration(
        color: color.withValues(alpha: .12),
        borderRadius: BorderRadius.circular(999),
        border: Border.all(color: color.withValues(alpha: .55)),
      ),
      child: Text(label, style: TextStyle(color: color, fontSize: 12, fontWeight: FontWeight.w900)),
    );
  }
}
