import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'support_helpdesk_screen.dart';

class WebRouteParityMatrixSuiteScreen extends StatefulWidget {
  const WebRouteParityMatrixSuiteScreen({super.key});

  @override
  State<WebRouteParityMatrixSuiteScreen> createState() => _WebRouteParityMatrixSuiteScreenState();
}

class _WebRouteParityMatrixSuiteScreenState extends State<WebRouteParityMatrixSuiteScreen> {
  String _filter = 'Alle';
  bool _showMapped = true;
  bool _showApiReady = true;
  bool _showGates = true;

  final List<_RouteGroup> _groups = const [
    _RouteGroup(
      title: 'Root & Legal',
      area: 'Mapped',
      status: 'Mapped',
      count: '5',
      suite: 'Public System Pages',
      body: 'Welcome, Maintenance, Privacy Policy, Terms of Service und Legal Show sind als native System-/Legal-UI gebuendelt.',
      icon: Icons.public_outlined,
      color: Color(0xFF5BA7FF),
    ),
    _RouteGroup(
      title: 'Auth',
      area: 'Mapped',
      status: 'Mapped',
      count: '10',
      suite: 'Auth API Entry + Auth Recovery',
      body: 'Login, Register, Complete Profile, Forgot/Reset, Confirm Password, 2FA, Verify Email, Suspended und Dashboard Entry sind abgedeckt.',
      icon: Icons.login_outlined,
      color: Color(0xFF2EE59D),
    ),
    _RouteGroup(
      title: 'Guardian',
      area: 'Mapped',
      status: 'Mapped',
      count: '5',
      suite: 'Guardian Access Portal',
      body: 'Guardian Login, Create Account, Verify, Children und Pending Consent sind als Eltern-/Kinderfreigabe-UI abgebildet.',
      icon: Icons.family_restroom_outlined,
      color: Color(0xFFF8B84E),
    ),
    _RouteGroup(
      title: 'Dashboard Core',
      area: 'Mapped',
      status: 'Mapped',
      count: '18+',
      suite: 'Operations + App Shell + Actions',
      body: 'Dashboard Index, Settings, Workspaces, Files, Notifications, Chat, Roles, Search und Aktionsseiten sind als mobile Suiten gebuendelt.',
      icon: Icons.dashboard_customize_outlined,
      color: Color(0xFFB084FF),
    ),
    _RouteGroup(
      title: 'Clubs, Teams & Membership',
      area: 'Mapped',
      status: 'Mapped',
      count: '12+',
      suite: 'Club Membership Lifecycle',
      body: 'Club Profile, Cockpit, Teams, Mitgliedsantrag, Membership Admin, Request Inbox, Form Builder, Regeln und Dokumente sind abgedeckt.',
      icon: Icons.apartment_outlined,
      color: Color(0xFF5BA7FF),
    ),
    _RouteGroup(
      title: 'Commerce, Billing & Finance',
      area: 'Mapped',
      status: 'Mapped',
      count: '16+',
      suite: 'Commerce + Finance Suites',
      body: 'Commerce, Cart, Product Show, BankTransfer, Order Status, Subscriptions, Outfit, Billing, Club Finance und Member Finance sind UI-ready.',
      icon: Icons.receipt_long_outlined,
      color: Color(0xFF2EE59D),
    ),
    _RouteGroup(
      title: 'Learning, Content & Growth',
      area: 'Mapped',
      status: 'Mapped',
      count: '20+',
      suite: 'Learning + Content + Public Growth',
      body: 'Blogs, Top-Inhalte, E-Learning, Learning Studio, Zertifikate, Jobs, Pricing, Sponsors, Ads und Guest-Seiten sind als mobile Suiten vorhanden.',
      icon: Icons.school_outlined,
      color: Color(0xFFF8B84E),
    ),
    _RouteGroup(
      title: 'Sports, Feed & Social',
      area: 'Mapped',
      status: 'Mapped',
      count: '20+',
      suite: 'Sports + Feed + Discovery',
      body: 'Sportarten, Sportkarte, Training, Events, Nutrition, Rides, Feed, Community, Friends und globale Suche sind abgebildet.',
      icon: Icons.dynamic_feed_outlined,
      color: Color(0xFFB084FF),
    ),
    _RouteGroup(
      title: 'Admin & Trust',
      area: 'Mapped',
      status: 'Mapped',
      count: '15+',
      suite: 'Admin Finance + Trust Control',
      body: 'Admin Commerce, Invoices, Payments, Provider Costs, Contracts, Moderation, Verification, Mail Center und Settings sind mobile Suiten.',
      icon: Icons.verified_user_outlined,
      color: Color(0xFFFF6B6B),
    ),
    _RouteGroup(
      title: 'API-ready Boundary',
      area: 'Api',
      status: 'Backend offen',
      count: 'All',
      suite: 'Laravel API',
      body: 'Alle Suiten sind UI-only: echte Daten, Auth-State, Uploads, Rollen, Persistenz und Zahlungsstatus werden später über Laravel APIs verbunden.',
      icon: Icons.cloud_sync_outlined,
      color: Color(0xFF5BA7FF),
    ),
    _RouteGroup(
      title: 'Compile Gate',
      area: 'Gates',
      status: 'Offen',
      count: '1',
      suite: 'flutter analyze/build',
      body: 'Ein harter Compile-/Analyzer-Lauf wurde noch nicht ausgefuehrt und bleibt als naechster technischer Gate offen.',
      icon: Icons.terminal_outlined,
      color: Color(0xFFF8B84E),
    ),
    _RouteGroup(
      title: 'Visual Gate',
      area: 'Gates',
      status: 'Offen',
      count: '1',
      suite: 'Mobile Web-Vergleich',
      body: 'Nach dem Compile-Gate muss die App visuell gegen die mobile Webversion geprüft werden: Abstand, Farben, Cards, Typografie und Navigation.',
      icon: Icons.palette_outlined,
      color: Color(0xFFB084FF),
    ),
  ];

  List<_RouteGroup> get _visible => _filter == 'Alle' ? _groups : _groups.where((item) => item.area == _filter).toList();

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
                    const _Hero(
                      eyebrow: 'ROUTE PARITY MATRIX',
                      title: 'Webseiten zu Flutter-Suiten',
                      subtitle: 'Mobile Mapping-UI für Webbereiche, Flutter-Suiten, API-Grenzen und offene Release-Gates.',
                    ),
                    const SizedBox(height: 18),
                    Row(
                      children: const [
                        Expanded(child: _Metric(value: '9', label: 'Mapped')),
                        SizedBox(width: 10),
                        Expanded(child: _Metric(value: '1', label: 'API')),
                        SizedBox(width: 10),
                        Expanded(child: _Metric(value: '2', label: 'Gates')),
                      ],
                    ),
                    const SizedBox(height: 18),
                    _Tabs(value: _filter, values: const ['Alle', 'Mapped', 'Api', 'Gates'], onChanged: (value) => setState(() => _filter = value)),
                    const SizedBox(height: 14),
                    _SwitchPanel(
                      title: 'Matrix-Sichten',
                      rows: [
                        _SwitchRowData('Gemappte Webbereiche', _showMapped, (value) => setState(() => _showMapped = value)),
                        _SwitchRowData('API-Grenzen', _showApiReady, (value) => setState(() => _showApiReady = value)),
                        _SwitchRowData('Release-Gates', _showGates, (value) => setState(() => _showGates = value)),
                      ],
                    ),
                    const SizedBox(height: 14),
                    for (final group in _visible.where(_isVisible)) ...[
                      _RouteGroupCard(group: group),
                      const SizedBox(height: 12),
                    ],
                    _ActionPanel(
                      firstIcon: Icons.alt_route_outlined,
                      firstLabel: 'Route-Matrix prüfen',
                      secondIcon: Icons.terminal_outlined,
                      secondLabel: 'Compile-Gate planen',
                      onFirst: () => openUiAction(context, title: 'Route-Parity Matrix', body: 'Die Matrix zeigt die aktuelle UI-Zuordnung; finale Bestätigung braucht den harten Prüflauf.', status: 'UI bereit', icon: Icons.info_outline),
                      onSecond: () => openUiAction(context, title: 'Compile-Gate', body: 'Flutter Analyze/Build ist der naechste technische Qualitaetsschritt.', status: 'UI bereit', icon: Icons.info_outline),
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

  bool _isVisible(_RouteGroup item) {
    if (item.area == 'Mapped') return _showMapped;
    if (item.area == 'Api') return _showApiReady;
    return _showGates;
  }

  void _openSupport(BuildContext context) {
    Navigator.of(context).push(MaterialPageRoute(builder: (_) => SupportHelpdeskScreen()));
  }
}

class _RouteGroup {
  const _RouteGroup({
    required this.title,
    required this.area,
    required this.status,
    required this.count,
    required this.suite,
    required this.body,
    required this.icon,
    required this.color,
  });

  final String title;
  final String area;
  final String status;
  final String count;
  final String suite;
  final String body;
  final IconData icon;
  final Color color;
}

class _SwitchRowData {
  const _SwitchRowData(this.label, this.value, this.onChanged);
  final String label;
  final bool value;
  final ValueChanged<bool> onChanged;
}

class _TopBar extends StatelessWidget {
  const _TopBar({required this.onSupport});
  final VoidCallback onSupport;
  @override
  Widget build(BuildContext context) => Row(children: [
        const AirmiusLogo(markOnly: true, size: 34),
        const SizedBox(width: 10),
        const Expanded(child: Text('Airmius', style: TextStyle(color: Colors.white, fontSize: 20, fontWeight: FontWeight.w900))),
        IconButton(onPressed: onSupport, icon: const Icon(Icons.support_agent_outlined, color: Color(0xFFAFC0D8))),
      ]);
}

class _Hero extends StatelessWidget {
  const _Hero({required this.eyebrow, required this.title, required this.subtitle});
  final String eyebrow;
  final String title;
  final String subtitle;
  @override
  Widget build(BuildContext context) => Container(
        padding: const EdgeInsets.all(18),
        decoration: BoxDecoration(borderRadius: BorderRadius.circular(24), border: Border.all(color: const Color(0xFF26364D)), gradient: const LinearGradient(colors: [Color(0xFF121A27), Color(0xFF0B111B)])),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text(eyebrow, style: const TextStyle(color: Color(0xFF5BA7FF), fontSize: 12, fontWeight: FontWeight.w900)),
          const SizedBox(height: 8),
          Text(title, style: const TextStyle(color: Colors.white, fontSize: 28, fontWeight: FontWeight.w900)),
          const SizedBox(height: 8),
          Text(subtitle, style: const TextStyle(color: Color(0xFFAFC0D8), height: 1.45, fontWeight: FontWeight.w600)),
        ]),
      );
}

class _Metric extends StatelessWidget {
  const _Metric({required this.value, required this.label});
  final String value;
  final String label;
  @override
  Widget build(BuildContext context) => Container(
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(color: const Color(0xFF101722), borderRadius: BorderRadius.circular(18), border: Border.all(color: const Color(0xFF26364D))),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text(value, style: const TextStyle(color: Colors.white, fontSize: 24, fontWeight: FontWeight.w900)),
          const SizedBox(height: 4),
          Text(label, style: const TextStyle(color: Color(0xFFAFC0D8), fontWeight: FontWeight.w700)),
        ]),
      );
}

class _Tabs extends StatelessWidget {
  const _Tabs({required this.value, required this.values, required this.onChanged});
  final String value;
  final List<String> values;
  final ValueChanged<String> onChanged;
  @override
  Widget build(BuildContext context) => SizedBox(
        height: 42,
        child: ListView.separated(
          scrollDirection: Axis.horizontal,
          itemCount: values.length,
          separatorBuilder: (_, _) => const SizedBox(width: 8),
          itemBuilder: (context, index) {
            final item = values[index];
            final active = item == value;
            return ChoiceChip(label: Text(item), selected: active, onSelected: (_) => onChanged(item), labelStyle: TextStyle(color: active ? Colors.white : const Color(0xFFAFC0D8), fontWeight: FontWeight.w900), selectedColor: const Color(0xFF173D68), backgroundColor: const Color(0xFF101722), shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(999), side: const BorderSide(color: Color(0xFF26364D))));
          },
        ),
      );
}

class _SwitchPanel extends StatelessWidget {
  const _SwitchPanel({required this.title, required this.rows});
  final String title;
  final List<_SwitchRowData> rows;
  @override
  Widget build(BuildContext context) => _Panel(title: title, child: Column(children: rows.map((row) => SwitchListTile.adaptive(value: row.value, onChanged: row.onChanged, dense: true, contentPadding: EdgeInsets.zero, activeThumbColor: const Color(0xFF5BA7FF), title: Text(row.label, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w800)))).toList()));
}

class _RouteGroupCard extends StatelessWidget {
  const _RouteGroupCard({required this.group});
  final _RouteGroup group;
  @override
  Widget build(BuildContext context) => Container(
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(color: const Color(0xFF101722), borderRadius: BorderRadius.circular(24), border: Border.all(color: const Color(0xFF26364D))),
        child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Container(width: 54, height: 54, decoration: BoxDecoration(color: group.color.withValues(alpha: .14), borderRadius: BorderRadius.circular(16), border: Border.all(color: group.color.withValues(alpha: .45))), child: Icon(group.icon, color: group.color, size: 28)),
          const SizedBox(width: 14),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Row(children: [Expanded(child: Text(group.title, style: const TextStyle(color: Colors.white, fontSize: 18, fontWeight: FontWeight.w900))), _Pill(label: group.status, color: group.color)]),
            const SizedBox(height: 6),
            Text('${group.count} Seiten - ${group.suite}', style: const TextStyle(color: Color(0xFFAFC0D8), fontWeight: FontWeight.w800)),
            const SizedBox(height: 8),
            Text(group.body, style: const TextStyle(color: Color(0xFFDDE7F5), height: 1.45, fontWeight: FontWeight.w600)),
          ])),
        ]),
      );
}

class _ActionPanel extends StatelessWidget {
  const _ActionPanel({required this.firstIcon, required this.firstLabel, required this.secondIcon, required this.secondLabel, required this.onFirst, required this.onSecond, required this.onSupport});
  final IconData firstIcon;
  final String firstLabel;
  final IconData secondIcon;
  final String secondLabel;
  final VoidCallback onFirst;
  final VoidCallback onSecond;
  final VoidCallback onSupport;
  @override
  Widget build(BuildContext context) => _Panel(title: 'Schnellaktionen', child: Column(children: [
        _ActionButton(icon: firstIcon, label: firstLabel, onTap: onFirst),
        const SizedBox(height: 10),
        _ActionButton(icon: secondIcon, label: secondLabel, onTap: onSecond),
        const SizedBox(height: 10),
        _ActionButton(icon: Icons.support_agent_outlined, label: 'Support kontaktieren', onTap: onSupport),
      ]));
}

class _Panel extends StatelessWidget {
  const _Panel({required this.title, required this.child});
  final String title;
  final Widget child;
  @override
  Widget build(BuildContext context) => Container(
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(color: const Color(0xFF0D131D), borderRadius: BorderRadius.circular(22), border: Border.all(color: const Color(0xFF26364D))),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text(title, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w900)), const SizedBox(height: 12), child]),
      );
}

class _ActionButton extends StatelessWidget {
  const _ActionButton({required this.icon, required this.label, required this.onTap});
  final IconData icon;
  final String label;
  final VoidCallback onTap;
  @override
  Widget build(BuildContext context) => InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(16),
        child: Container(
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(color: const Color(0xFF111A27), borderRadius: BorderRadius.circular(16), border: Border.all(color: const Color(0xFF26364D))),
          child: Row(children: [Icon(icon, color: AirmiusColors.blue), const SizedBox(width: 12), Expanded(child: Text(label, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w900))), const Icon(Icons.chevron_right, color: Color(0xFFAFC0D8))]),
        ),
      );
}

class _Pill extends StatelessWidget {
  const _Pill({required this.label, required this.color});
  final String label;
  final Color color;
  @override
  Widget build(BuildContext context) => Container(
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
        decoration: BoxDecoration(color: color.withValues(alpha: .12), borderRadius: BorderRadius.circular(999), border: Border.all(color: color.withValues(alpha: .55))),
        child: Text(label, style: TextStyle(color: color, fontSize: 12, fontWeight: FontWeight.w900)),
      );
}
