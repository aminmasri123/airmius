import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'support_helpdesk_screen.dart';

class ClubMembershipLifecycleSuiteScreen extends StatefulWidget {
  const ClubMembershipLifecycleSuiteScreen({super.key});

  @override
  State<ClubMembershipLifecycleSuiteScreen> createState() => _ClubMembershipLifecycleSuiteScreenState();
}

class _ClubMembershipLifecycleSuiteScreenState extends State<ClubMembershipLifecycleSuiteScreen> {
  String _filter = 'Alle';
  bool _showClub = true;
  bool _showMembership = true;
  bool _showDocuments = true;

  final List<_SuiteItem> _items = const [
    _SuiteItem('Vereinsprofil', 'Club', 'Profil', 'Mobile Vereinsseite mit Hero, Teams, Admins, Mitgliederzahl, Beiträgen und Anfrage-CTA.', Icons.apartment_outlined, Color(0xFF5BA7FF)),
    _SuiteItem('Club Cockpit', 'Club', 'Admin', 'Vereins-Dashboard für Admins mit Aufgaben, Anfragen, Teams, Rollen, Finanzen und Dokumenten.', Icons.dashboard_customize_outlined, Color(0xFF2EE59D)),
    _SuiteItem('Teams Verwaltung', 'Club', 'Teams', 'Teams, Profile, Rollen, Trainer, Mitglieder und Sichtbarkeit als mobile Admin-UI.', Icons.groups_2_outlined, Color(0xFFF8B84E)),
    _SuiteItem('Mitgliedsantrag', 'Membership', 'Form', 'Individuelles Formular mit Personendaten, Adresse, Kontakt, Zahlung, Guardian und Vereinsfeldern.', Icons.assignment_outlined, Color(0xFFB084FF)),
    _SuiteItem('Anfrage Inbox', 'Membership', 'Inbox', 'Neue Mitgliedsanfragen, Status, Rückfragen, Annahme, Ablehnung und Widerruf.', Icons.move_to_inbox_outlined, Color(0xFFFF6B6B)),
    _SuiteItem('Form Builder', 'Membership', 'Builder', 'Vereine konfigurieren Pflichtfelder, optionale Felder, Sichtbarkeit und Zahlungsregeln.', Icons.format_list_bulleted_outlined, Color(0xFF5BA7FF)),
    _SuiteItem('Beitragsregeln', 'Membership', 'Rules', 'Monatlich, vierteljaehrlich, halbjaehrlich, jaehrlich, Bar, Überweisung und Dokumentverknuepfung.', Icons.price_check_outlined, Color(0xFF2EE59D)),
    _SuiteItem('Vereinsdokumente', 'Documents', 'Files', 'Datenschutz, Regeln, Satzung, Upload, Dateimanager-Verknuepfung und Mitgliedsantrag-Anhaenge.', Icons.folder_shared_outlined, Color(0xFFF8B84E)),
    _SuiteItem('Sichtbarkeit & Rollen', 'Documents', 'Access', 'Was Mitglieder, Gäste, Teams und Admins sehen dürfen; Rollenmatrix und Audit-Hinweise.', Icons.visibility_outlined, Color(0xFFB084FF)),
  ];

  List<_SuiteItem> get _visible => _filter == 'Alle' ? _items : _items.where((item) => item.area == _filter).toList();

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
                      eyebrow: 'CLUB LIFECYCLE',
                      title: 'Verein & Mitgliedschaft',
                      subtitle: 'Native Mobile-UI für Vereinsprofil, Cockpit, Teams, Mitgliedsantrag, Inbox, Formbuilder, Beitragsregeln und Dokumente.',
                    ),
                    const SizedBox(height: 18),
                    Row(
                      children: const [
                        Expanded(child: _Metric(value: '9', label: 'Views')),
                        SizedBox(width: 10),
                        Expanded(child: _Metric(value: '4', label: 'Membership')),
                        SizedBox(width: 10),
                        Expanded(child: _Metric(value: '2', label: 'Docs')),
                      ],
                    ),
                    const SizedBox(height: 18),
                    _Tabs(value: _filter, values: const ['Alle', 'Club', 'Membership', 'Documents'], onChanged: (value) => setState(() => _filter = value)),
                    const SizedBox(height: 14),
                    _SwitchPanel(
                      title: 'Vereinsbereiche',
                      rows: [
                        _SwitchRowData('Club anzeigen', _showClub, (value) => setState(() => _showClub = value)),
                        _SwitchRowData('Membership anzeigen', _showMembership, (value) => setState(() => _showMembership = value)),
                        _SwitchRowData('Dokumente anzeigen', _showDocuments, (value) => setState(() => _showDocuments = value)),
                      ],
                    ),
                    const SizedBox(height: 14),
                    for (final item in _visible.where(_isVisible)) ...[
                      _SuiteCard(item: item),
                      const SizedBox(height: 12),
                    ],
                    _ActionPanel(
                      firstIcon: Icons.person_add_alt_1_outlined,
                      firstLabel: 'Mitgliedsantrag starten',
                      secondIcon: Icons.format_list_bulleted_outlined,
                      secondLabel: 'Formular konfigurieren',
                      onFirst: () => openUiAction(context, title: 'Mitgliedsantrag', body: 'Die mobile Vereinsbeitritts-UI ist vorbereitet; API-Anbindung folgt über Laravel.', status: 'UI bereit', icon: Icons.info_outline),
                      onSecond: () => openUiAction(context, title: 'Form Builder', body: 'Vereinsindividuelle Felder und Dokumente sind als UI vorbereitet.', status: 'UI bereit', icon: Icons.info_outline),
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

  bool _isVisible(_SuiteItem item) {
    if (item.area == 'Club') return _showClub;
    if (item.area == 'Membership') return _showMembership;
    return _showDocuments;
  }

  void _openSupport(BuildContext context) {
    Navigator.of(context).push(MaterialPageRoute(builder: (_) => SupportHelpdeskScreen()));
  }
}

class _SuiteItem {
  const _SuiteItem(this.title, this.area, this.status, this.body, this.icon, this.color);

  final String title;
  final String area;
  final String status;
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
        decoration: BoxDecoration(borderRadius: BorderRadius.circular(24), border: Border.all(color: const Color(0xFF26364D)), gradient: const LinearGradient(colors: [Color(0xFF121A27), Color(0xFF0B111B)], begin: Alignment.topLeft, end: Alignment.bottomRight)),
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
          separatorBuilder: (_, __) => const SizedBox(width: 8),
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
  Widget build(BuildContext context) => _Panel(
        title: title,
        child: Column(children: rows.map((row) => SwitchListTile.adaptive(value: row.value, onChanged: row.onChanged, dense: true, contentPadding: EdgeInsets.zero, activeThumbColor: const Color(0xFF5BA7FF), title: Text(row.label, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w800)))).toList()),
      );
}

class _SuiteCard extends StatelessWidget {
  const _SuiteCard({required this.item});

  final _SuiteItem item;

  @override
  Widget build(BuildContext context) => Container(
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(color: const Color(0xFF101722), borderRadius: BorderRadius.circular(24), border: Border.all(color: const Color(0xFF26364D))),
        child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Container(width: 54, height: 54, decoration: BoxDecoration(color: item.color.withOpacity(.14), borderRadius: BorderRadius.circular(16), border: Border.all(color: item.color.withOpacity(.45))), child: Icon(item.icon, color: item.color, size: 28)),
          const SizedBox(width: 14),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Row(children: [Expanded(child: Text(item.title, style: const TextStyle(color: Colors.white, fontSize: 18, fontWeight: FontWeight.w900))), _Pill(label: item.status, color: item.color)]),
            const SizedBox(height: 8),
            Text(item.body, style: const TextStyle(color: Color(0xFFDDE7F5), height: 1.45, fontWeight: FontWeight.w600)),
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
        decoration: BoxDecoration(color: color.withOpacity(.12), borderRadius: BorderRadius.circular(999), border: Border.all(color: color.withOpacity(.55))),
        child: Text(label, style: TextStyle(color: color, fontSize: 12, fontWeight: FontWeight.w900)),
      );
}
