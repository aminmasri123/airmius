import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'support_helpdesk_screen.dart';

class DashboardActionFlowsScreen extends StatefulWidget {
  const DashboardActionFlowsScreen({super.key});

  @override
  State<DashboardActionFlowsScreen> createState() =>
      _DashboardActionFlowsScreenState();
}

class _DashboardActionFlowsScreenState
    extends State<DashboardActionFlowsScreen> {
  String _filter = 'Alle';
  bool _showCrud = true;
  bool _showPayments = true;
  bool _showDetails = true;

  final List<_ActionFlow> _flows = const [
    _ActionFlow(
      title: 'User erstellen',
      area: 'CRUD',
      status: 'Create',
      body:
          'Mobile Formularstrecke für neue Benutzer, Rollen, Vereinsbezug, E-Mail und Status.',
      icon: Icons.person_add_alt_outlined,
      color: Color(0xFF5BA7FF),
    ),
    _ActionFlow(
      title: 'User bearbeiten',
      area: 'CRUD',
      status: 'Edit',
      body:
          'Bearbeiten von Profil, Rolle, Sichtbarkeit, Verifizierung und administrativen Accountfeldern.',
      icon: Icons.edit_note_outlined,
      color: Color(0xFF2EE59D),
    ),
    _ActionFlow(
      title: 'Training Log erstellen',
      area: 'CRUD',
      status: 'Log',
      body:
          'Trainingsprotokoll mit Sportart, Datum, Intensität, Notizen, Dauer und Fortschritt.',
      icon: Icons.post_add_outlined,
      color: Color(0xFFF8B84E),
    ),
    _ActionFlow(
      title: 'Commerce Banktransfer',
      area: 'Payments',
      status: 'Bank',
      body:
          'Banküberweisung für Commerce-Bestellungen mit Referenz, IBAN-Hinweis und Zahlungsstatus.',
      icon: Icons.account_balance_outlined,
      color: Color(0xFFB084FF),
    ),
    _ActionFlow(
      title: 'Subscription Banktransfer',
      area: 'Payments',
      status: 'Abo',
      body:
          'Abo-Zahlung per Banktransfer mit Plan, Betrag, Zeitraum und Freischaltungshinweis.',
      icon: Icons.workspace_premium_outlined,
      color: Color(0xFFFF6B6B),
    ),
    _ActionFlow(
      title: 'Event Detail',
      area: 'Details',
      status: 'Show',
      body:
          'Detailansicht für Events mit Teilnahme, Ort, Zeiten, Teams, Rollen und Check-in-Status.',
      icon: Icons.event_available_outlined,
      color: Color(0xFF5BA7FF),
    ),
    _ActionFlow(
      title: 'Training Plan Item',
      area: 'Details',
      status: 'Plan',
      body:
          'Planpositionen, Übungen, Sätze, Dauer, Hinweise und Trainerfeedback als mobile Detailseite.',
      icon: Icons.fitness_center_outlined,
      color: Color(0xFF2EE59D),
    ),
    _ActionFlow(
      title: 'Team Profil',
      area: 'Details',
      status: 'Team',
      body:
          'Teamprofil mit Mitgliedern, Trainer, Terminen, Rollen, Beiträgen und Vereinszuordnung.',
      icon: Icons.groups_2_outlined,
      color: Color(0xFFF8B84E),
    ),
  ];

  List<_ActionFlow> get _visibleFlows {
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
                    _Header(onSupport: () => _openSupport(context)),
                    const SizedBox(height: 18),
                    const _Hero(),
                    const SizedBox(height: 18),
                    Row(
                      children: const [
                        Expanded(
                          child: _Metric(value: '8', label: 'Flows'),
                        ),
                        SizedBox(width: 10),
                        Expanded(
                          child: _Metric(value: '3', label: 'CRUD'),
                        ),
                        SizedBox(width: 10),
                        Expanded(
                          child: _Metric(value: '3', label: 'Details'),
                        ),
                      ],
                    ),
                    const SizedBox(height: 18),
                    _Tabs(
                      value: _filter,
                      values: const ['Alle', 'CRUD', 'Payments', 'Details'],
                      onChanged: (value) => setState(() => _filter = value),
                    ),
                    const SizedBox(height: 14),
                    _VisibilityPanel(
                      showCrud: _showCrud,
                      showPayments: _showPayments,
                      showDetails: _showDetails,
                      onCrud: (value) => setState(() => _showCrud = value),
                      onPayments: (value) =>
                          setState(() => _showPayments = value),
                      onDetails: (value) =>
                          setState(() => _showDetails = value),
                    ),
                    const SizedBox(height: 14),
                    for (final flow in _visibleFlows.where(_isVisible)) ...[
                      _ActionFlowCard(flow: flow),
                      const SizedBox(height: 12),
                    ],
                    _ActionPanel(
                      onCreate: () => openUiAction(
                        context,
                        title: 'Create/Edit Flow',
                        message:
                            'Die mobile Formular-UI ist vorbereitet; API-Daten werden später pro Modul geladen.',
                      ),
                      onPayment: () => openUiAction(
                        context,
                        title: 'Banktransfer',
                        message:
                            'Die Zahlungsstrecke ist als UI vorhanden und wird später mit Laravel-Zahlstatus verbunden.',
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

  bool _isVisible(_ActionFlow flow) {
    if (flow.area == 'CRUD') return _showCrud;
    if (flow.area == 'Payments') return _showPayments;
    return _showDetails;
  }

  void _openSupport(BuildContext context) {
    Navigator.of(
      context,
    ).push(MaterialPageRoute(builder: (_) => SupportHelpdeskScreen()));
  }
}

class _ActionFlow {
  const _ActionFlow({
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
          child: Text(
            'Airmius',
            style: TextStyle(
              color: Colors.white,
              fontSize: 20,
              fontWeight: FontWeight.w900,
            ),
          ),
        ),
        IconButton(
          onPressed: onSupport,
          icon: const Icon(
            Icons.support_agent_outlined,
            color: Color(0xFFAFC0D8),
          ),
        ),
      ],
    );
  }
}

class _Hero extends StatelessWidget {
  const _Hero();

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
          Text(
            'DASHBOARD ACTIONS',
            style: TextStyle(
              color: Color(0xFF5BA7FF),
              fontSize: 12,
              fontWeight: FontWeight.w900,
            ),
          ),
          SizedBox(height: 8),
          Text(
            'Create, Edit, Show & Payment',
            style: TextStyle(
              color: Colors.white,
              fontSize: 28,
              fontWeight: FontWeight.w900,
            ),
          ),
          SizedBox(height: 8),
          Text(
            'Native Mobile-UI für die kleinen Dashboard-Aktionsseiten: User Create/Edit, Training Logs, Banktransfer, Events, Teams und Plan Items.',
            style: TextStyle(
              color: Color(0xFFAFC0D8),
              height: 1.45,
              fontWeight: FontWeight.w600,
            ),
          ),
        ],
      ),
    );
  }
}

class _Metric extends StatelessWidget {
  const _Metric({required this.value, required this.label});

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
          Text(
            value,
            style: const TextStyle(
              color: Colors.white,
              fontSize: 24,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 4),
          Text(
            label,
            style: const TextStyle(
              color: Color(0xFFAFC0D8),
              fontWeight: FontWeight.w700,
            ),
          ),
        ],
      ),
    );
  }
}

class _Tabs extends StatelessWidget {
  const _Tabs({
    required this.value,
    required this.values,
    required this.onChanged,
  });

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
        separatorBuilder: (_, _) => const SizedBox(width: 8),
        itemBuilder: (context, index) {
          final item = values[index];
          final active = item == value;
          return ChoiceChip(
            label: Text(item),
            selected: active,
            onSelected: (_) => onChanged(item),
            labelStyle: TextStyle(
              color: active ? Colors.white : const Color(0xFFAFC0D8),
              fontWeight: FontWeight.w900,
            ),
            selectedColor: const Color(0xFF173D68),
            backgroundColor: const Color(0xFF101722),
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(999),
              side: const BorderSide(color: Color(0xFF26364D)),
            ),
          );
        },
      ),
    );
  }
}

class _VisibilityPanel extends StatelessWidget {
  const _VisibilityPanel({
    required this.showCrud,
    required this.showPayments,
    required this.showDetails,
    required this.onCrud,
    required this.onPayments,
    required this.onDetails,
  });

  final bool showCrud;
  final bool showPayments;
  final bool showDetails;
  final ValueChanged<bool> onCrud;
  final ValueChanged<bool> onPayments;
  final ValueChanged<bool> onDetails;

  @override
  Widget build(BuildContext context) {
    return _Panel(
      title: 'Action-Gruppen',
      child: Column(
        children: [
          _SwitchRow(
            label: 'Create/Edit anzeigen',
            value: showCrud,
            onChanged: onCrud,
          ),
          _SwitchRow(
            label: 'Zahlungen anzeigen',
            value: showPayments,
            onChanged: onPayments,
          ),
          _SwitchRow(
            label: 'Detailseiten anzeigen',
            value: showDetails,
            onChanged: onDetails,
          ),
        ],
      ),
    );
  }
}

class _ActionFlowCard extends StatelessWidget {
  const _ActionFlowCard({required this.flow});

  final _ActionFlow flow;

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
                        flow.title,
                        style: const TextStyle(
                          color: Colors.white,
                          fontSize: 18,
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                    ),
                    _Pill(label: flow.status, color: flow.color),
                  ],
                ),
                const SizedBox(height: 8),
                Text(
                  flow.body,
                  style: const TextStyle(
                    color: Color(0xFFDDE7F5),
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
    required this.onCreate,
    required this.onPayment,
    required this.onSupport,
  });

  final VoidCallback onCreate;
  final VoidCallback onPayment;
  final VoidCallback onSupport;

  @override
  Widget build(BuildContext context) {
    return _Panel(
      title: 'Schnellaktionen',
      child: Column(
        children: [
          _ActionButton(
            icon: Icons.add_task_outlined,
            label: 'Formularaktion starten',
            onTap: onCreate,
          ),
          const SizedBox(height: 10),
          _ActionButton(
            icon: Icons.account_balance_outlined,
            label: 'Banktransfer Vorschau',
            onTap: onPayment,
          ),
          const SizedBox(height: 10),
          _ActionButton(
            icon: Icons.support_agent_outlined,
            label: 'Support kontaktieren',
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
          Text(
            title,
            style: const TextStyle(
              color: Colors.white,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 12),
          child,
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
    return SwitchListTile.adaptive(
      value: value,
      onChanged: onChanged,
      dense: true,
      contentPadding: EdgeInsets.zero,
      activeThumbColor: const Color(0xFF5BA7FF),
      title: Text(
        label,
        style: const TextStyle(
          color: Colors.white,
          fontWeight: FontWeight.w800,
        ),
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
            Expanded(
              child: Text(
                label,
                style: const TextStyle(
                  color: Colors.white,
                  fontWeight: FontWeight.w900,
                ),
              ),
            ),
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
