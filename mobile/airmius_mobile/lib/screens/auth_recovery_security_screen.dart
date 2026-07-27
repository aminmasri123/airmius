import 'package:flutter/material.dart';

import '../core/airmius_l10n.dart';
import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'email_verification_screen.dart';
import 'password_recovery_screen.dart';
import 'support_helpdesk_screen.dart';

class AuthRecoverySecurityScreen extends StatefulWidget {
  const AuthRecoverySecurityScreen({super.key});

  @override
  State<AuthRecoverySecurityScreen> createState() =>
      _AuthRecoverySecurityScreenState();
}

class _AuthRecoverySecurityScreenState
    extends State<AuthRecoverySecurityScreen> {
  String _filter = 'all';
  bool _showRecovery = true;
  bool _showSecurity = true;
  bool _showAccountState = true;

  final List<_AuthFlow> _flows = const [
    _AuthFlow(
      titleKey: 'authRecovery.flow.profile.title',
      area: 'profile',
      statusKey: 'authRecovery.flow.profile.status',
      bodyKey: 'authRecovery.flow.profile.body',
      icon: Icons.assignment_ind_outlined,
      color: Color(0xFF5BA7FF),
    ),
    _AuthFlow(
      titleKey: 'authRecovery.flow.forgot.title',
      area: 'recovery',
      statusKey: 'authRecovery.flow.forgot.status',
      bodyKey: 'authRecovery.flow.forgot.body',
      icon: Icons.lock_reset_outlined,
      color: Color(0xFF2EE59D),
    ),
    _AuthFlow(
      titleKey: 'authRecovery.flow.reset.title',
      area: 'recovery',
      statusKey: 'authRecovery.flow.reset.status',
      bodyKey: 'authRecovery.flow.reset.body',
      icon: Icons.password_outlined,
      color: Color(0xFF2EE59D),
    ),
    _AuthFlow(
      titleKey: 'authRecovery.flow.confirm.title',
      area: 'security',
      statusKey: 'authRecovery.flow.confirm.status',
      bodyKey: 'authRecovery.flow.confirm.body',
      icon: Icons.enhanced_encryption_outlined,
      color: Color(0xFFF8B84E),
    ),
    _AuthFlow(
      titleKey: 'authRecovery.flow.twoFactor.title',
      area: 'security',
      statusKey: 'authRecovery.flow.twoFactor.status',
      bodyKey: 'authRecovery.flow.twoFactor.body',
      icon: Icons.pin_outlined,
      color: Color(0xFFF8B84E),
    ),
    _AuthFlow(
      titleKey: 'authRecovery.flow.email.title',
      area: 'security',
      statusKey: 'authRecovery.flow.email.status',
      bodyKey: 'authRecovery.flow.email.body',
      icon: Icons.mark_email_read_outlined,
      color: Color(0xFFB084FF),
    ),
    _AuthFlow(
      titleKey: 'authRecovery.flow.suspended.title',
      area: 'status',
      statusKey: 'authRecovery.flow.suspended.status',
      bodyKey: 'authRecovery.flow.suspended.body',
      icon: Icons.block_outlined,
      color: Color(0xFFFF6B6B),
    ),
  ];

  List<_AuthFlow> get _visibleFlows {
    if (_filter == 'all') return _flows;
    return _flows.where((flow) => flow.area == _filter).toList();
  }

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return Scaffold(
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
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
                      children: [
                        Expanded(
                          child: _MetricCard(
                            value: '7',
                            label: t('authRecovery.metricFlows'),
                          ),
                        ),
                        SizedBox(width: 10),
                        Expanded(
                          child: _MetricCard(
                            value: '3',
                            label: t('authRecovery.metricSecurity'),
                          ),
                        ),
                        SizedBox(width: 10),
                        Expanded(
                          child: _MetricCard(
                            value: '2',
                            label: t('authRecovery.metricRecovery'),
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 18),
                    _FilterTabs(
                      value: _filter,
                      values: const [
                        'all',
                        'profile',
                        'recovery',
                        'security',
                        'status',
                      ],
                      onChanged: (value) => setState(() => _filter = value),
                    ),
                    const SizedBox(height: 14),
                    _VisibilityPanel(
                      showRecovery: _showRecovery,
                      showSecurity: _showSecurity,
                      showAccountState: _showAccountState,
                      onRecovery: (value) =>
                          setState(() => _showRecovery = value),
                      onSecurity: (value) =>
                          setState(() => _showSecurity = value),
                      onAccountState: (value) =>
                          setState(() => _showAccountState = value),
                    ),
                    const SizedBox(height: 14),
                    for (final flow in _visibleFlows.where(_isVisible)) ...[
                      _AuthFlowCard(flow: flow),
                      const SizedBox(height: 12),
                    ],
                    _ActionPanel(
                      onReset: () => Navigator.of(context).push(
                        MaterialPageRoute(
                          builder: (_) => const PasswordRecoveryScreen(),
                        ),
                      ),
                      onVerify: () => Navigator.of(context).push(
                        MaterialPageRoute(
                          builder: (_) => const EmailVerificationScreen(),
                        ),
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
    if (flow.area == 'recovery') return _showRecovery;
    if (flow.area == 'security') return _showSecurity;
    return _showAccountState;
  }

  void _openSupport(BuildContext context) {
    Navigator.of(
      context,
    ).push(MaterialPageRoute(builder: (_) => SupportHelpdeskScreen()));
  }
}

class _AuthFlow {
  const _AuthFlow({
    required this.titleKey,
    required this.area,
    required this.statusKey,
    required this.bodyKey,
    required this.icon,
    required this.color,
  });

  final String titleKey;
  final String area;
  final String statusKey;
  final String bodyKey;
  final IconData icon;
  final Color color;
}

class _TopBar extends StatelessWidget {
  const _TopBar({required this.onSupport});

  final VoidCallback onSupport;

  @override
  Widget build(BuildContext context) {
    final text = airmiusTextColor(context);
    final muted = airmiusMutedColor(context);
    return Row(
      children: [
        const AirmiusLogo(markOnly: true, size: 34),
        const SizedBox(width: 10),
        Expanded(
          child: Text(
            'Airmius',
            style: TextStyle(
              color: text,
              fontSize: 20,
              fontWeight: FontWeight.w900,
            ),
          ),
        ),
        IconButton(
          onPressed: onSupport,
          icon: Icon(Icons.support_agent_outlined, color: muted),
        ),
      ],
    );
  }
}

class _IntroPanel extends StatelessWidget {
  const _IntroPanel();

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final scheme = Theme.of(context).colorScheme;
    final surface = airmiusSurfaceColor(context);
    final soft = airmiusSurfaceSoftColor(context);
    final text = airmiusTextColor(context);
    final muted = airmiusMutedColor(context);
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(24),
        border: Border.all(color: airmiusBorderColor(context)),
        gradient: LinearGradient(
          colors: [
            Color.alphaBlend(scheme.primary.withValues(alpha: .10), surface),
            soft,
          ],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            t('authRecovery.eyebrow'),
            style: TextStyle(
              color: scheme.primary,
              fontSize: 12,
              fontWeight: FontWeight.w900,
            ),
          ),
          SizedBox(height: 8),
          Text(
            t('authRecovery.title'),
            style: TextStyle(
              color: text,
              fontSize: 28,
              fontWeight: FontWeight.w900,
            ),
          ),
          SizedBox(height: 8),
          Text(
            t('authRecovery.description'),
            style: TextStyle(
              color: muted,
              height: 1.45,
              fontWeight: FontWeight.w600,
            ),
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
    final text = airmiusTextColor(context);
    final muted = airmiusMutedColor(context);
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: airmiusSurfaceSoftColor(context),
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: airmiusBorderColor(context)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            value,
            style: TextStyle(
              color: text,
              fontSize: 24,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 4),
          Text(
            label,
            style: TextStyle(color: muted, fontWeight: FontWeight.w700),
          ),
        ],
      ),
    );
  }
}

class _FilterTabs extends StatelessWidget {
  const _FilterTabs({
    required this.value,
    required this.values,
    required this.onChanged,
  });

  final String value;
  final List<String> values;
  final ValueChanged<String> onChanged;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final text = airmiusTextColor(context);
    final muted = airmiusMutedColor(context);
    final accent = airmiusAccentColor(context);
    return SizedBox(
      height: 42,
      child: ListView.separated(
        scrollDirection: Axis.horizontal,
        itemCount: values.length,
        separatorBuilder: (_, _) => const SizedBox(width: 8),
        itemBuilder: (context, index) {
          final item = values[index];
          final active = item == value;
          return ChoiceChip(
            label: Text(t('authRecovery.filter.$item')),
            selected: active,
            onSelected: (_) => onChanged(item),
            labelStyle: TextStyle(
              color: active ? text : muted,
              fontWeight: FontWeight.w900,
            ),
            selectedColor: accent.withValues(alpha: .20),
            backgroundColor: airmiusSurfaceSoftColor(context),
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(999),
              side: BorderSide(color: airmiusBorderColor(context)),
            ),
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
    final t = AirmiusScope.of(context).t;
    return _Panel(
      title: t('authRecovery.groups'),
      child: Column(
        children: [
          _SwitchRow(
            label: t('authRecovery.showRecovery'),
            value: showRecovery,
            onChanged: onRecovery,
          ),
          _SwitchRow(
            label: t('authRecovery.showSecurity'),
            value: showSecurity,
            onChanged: onSecurity,
          ),
          _SwitchRow(
            label: t('authRecovery.showAccountState'),
            value: showAccountState,
            onChanged: onAccountState,
          ),
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
    final t = AirmiusScope.of(context).t;
    final text = airmiusTextColor(context);
    final muted = airmiusMutedColor(context);
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: airmiusSurfaceSoftColor(context),
        borderRadius: BorderRadius.circular(24),
        border: Border.all(color: airmiusBorderColor(context)),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 54,
            height: 54,
            decoration: BoxDecoration(
              color: flow.color.withValues(alpha: .14),
              borderRadius: BorderRadius.circular(16),
              border: Border.all(color: flow.color.withValues(alpha: .45)),
            ),
            child: Icon(flow.icon, color: flow.color, size: 28),
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Expanded(
                      child: Text(
                        t(flow.titleKey),
                        style: TextStyle(
                          color: text,
                          fontSize: 18,
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                    ),
                    _Pill(label: t(flow.statusKey), color: flow.color),
                  ],
                ),
                const SizedBox(height: 8),
                Text(
                  t(flow.bodyKey),
                  style: TextStyle(
                    color: muted,
                    height: 1.45,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _ActionPanel extends StatelessWidget {
  const _ActionPanel({
    required this.onReset,
    required this.onVerify,
    required this.onSupport,
  });

  final VoidCallback onReset;
  final VoidCallback onVerify;
  final VoidCallback onSupport;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return _Panel(
      title: t('authRecovery.quickActions'),
      child: Column(
        children: [
          _ActionButton(
            icon: Icons.lock_reset_outlined,
            label: t('passwordRecovery.title'),
            onTap: onReset,
          ),
          const SizedBox(height: 10),
          _ActionButton(
            icon: Icons.mark_email_read_outlined,
            label: t('emailVerification.resend'),
            onTap: onVerify,
          ),
          const SizedBox(height: 10),
          _ActionButton(
            icon: Icons.support_agent_outlined,
            label: t('settings.support'),
            onTap: onSupport,
          ),
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
    final text = airmiusTextColor(context);
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: airmiusSurfaceColor(context),
        borderRadius: BorderRadius.circular(22),
        border: Border.all(color: airmiusBorderColor(context)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            title,
            style: TextStyle(color: text, fontWeight: FontWeight.w900),
          ),
          const SizedBox(height: 12),
          Material(color: Colors.transparent, child: child),
        ],
      ),
    );
  }
}

class _SwitchRow extends StatelessWidget {
  const _SwitchRow({
    required this.label,
    required this.value,
    required this.onChanged,
  });

  final String label;
  final bool value;
  final ValueChanged<bool> onChanged;

  @override
  Widget build(BuildContext context) {
    final text = airmiusTextColor(context);
    return SwitchListTile.adaptive(
      value: value,
      onChanged: onChanged,
      dense: true,
      contentPadding: EdgeInsets.zero,
      activeThumbColor: airmiusAccentColor(context),
      title: Text(
        label,
        style: TextStyle(color: text, fontWeight: FontWeight.w800),
      ),
    );
  }
}

class _ActionButton extends StatelessWidget {
  const _ActionButton({
    required this.icon,
    required this.label,
    required this.onTap,
  });

  final IconData icon;
  final String label;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final text = airmiusTextColor(context);
    final muted = airmiusMutedColor(context);
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(16),
      child: Container(
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(
          color: airmiusSurfaceSoftColor(context),
          borderRadius: BorderRadius.circular(16),
          border: Border.all(color: airmiusBorderColor(context)),
        ),
        child: Row(
          children: [
            Icon(icon, color: airmiusAccentColor(context)),
            const SizedBox(width: 12),
            Expanded(
              child: Text(
                label,
                style: TextStyle(color: text, fontWeight: FontWeight.w900),
              ),
            ),
            Icon(Icons.chevron_right, color: muted),
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
      child: Text(
        label,
        style: TextStyle(
          color: color,
          fontSize: 12,
          fontWeight: FontWeight.w900,
        ),
      ),
    );
  }
}
