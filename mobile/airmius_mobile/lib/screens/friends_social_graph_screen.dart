import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'profile_security_center_screen.dart';
import 'report_moderation_center_screen.dart';
import 'support_helpdesk_screen.dart';

class FriendsSocialGraphScreen extends StatefulWidget {
  const FriendsSocialGraphScreen({super.key});

  @override
  State<FriendsSocialGraphScreen> createState() => _FriendsSocialGraphScreenState();
}

class _FriendsSocialGraphScreenState extends State<FriendsSocialGraphScreen> {
  String _tab = 'Alle';
  bool _showRequests = true;
  bool _showSuggestions = true;
  bool _showClubContext = true;
  bool _showBlocked = false;

  final List<_FriendItem> _items = const [
    _FriendItem(title: 'Max Running', area: 'Freunde', body: 'Gemeinsamer Verein, Laufgruppe und drei gemeinsame Events.', status: 'Freund', meta: 'ZBB', icon: Icons.person_outline, color: AirmiusColors.blue),
    _FriendItem(title: 'Sarah Tennis', area: 'Anfragen', body: 'Offene Freundschaftsanfrage mit Vereinsbezug und Profilvorschau.', status: 'Offen', meta: 'Tennis Zentrum West', icon: Icons.person_add_outlined, color: AirmiusColors.green),
    _FriendItem(title: 'Teamkontakt U16', area: 'Vorschlaege', body: 'Vorschlag aus Team, Training und gemeinsamen Vereinsmitgliedern.', status: 'Vorschlag', meta: 'U16 Jugend', icon: Icons.group_add_outlined, color: AirmiusColors.amber),
    _FriendItem(title: 'Gesperrter Kontakt', area: 'Blockiert', body: 'Blockierte Person mit Melde-, Entsperr- und Privatsphaere-Hinweis.', status: 'Blockiert', meta: 'Safety', icon: Icons.block_outlined, color: AirmiusColors.red),
  ];

  List<_FriendItem> get _visibleItems => _items.where((item) => _tab == 'Alle' || item.area == _tab).toList();

  @override
  Widget build(BuildContext context) {
    final items = _visibleItems;

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
                        const PageTitle(title: 'Freunde & Kontakte', subtitle: 'Freundschaften, Anfragen, Vorschlaege, gemeinsame Vereine, Blockieren und Melden.'),
                        const SizedBox(height: 16),
                        _FriendsHero(onInvite: () => _toast('Freund einladen vorbereitet')),
                        const SizedBox(height: 16),
                        _ChoicePanel(title: 'Ansicht', value: _tab, values: const ['Alle', 'Freunde', 'Anfragen', 'Vorschlaege', 'Blockiert'], onChanged: (value) => setState(() => _tab = value)),
                        const SizedBox(height: 16),
                        AirmiusPanel(
                          title: 'Privatsphaere & Filter',
                          child: Column(
                            children: [
                              _SwitchRow(title: 'Anfragen anzeigen', subtitle: 'Eingehende und ausgehende Freundschaftsanfragen sichtbar machen.', value: _showRequests, onChanged: (value) => setState(() => _showRequests = value)),
                              _SwitchRow(title: 'Vorschlaege anzeigen', subtitle: 'Empfehlungen aus Vereinen, Teams, Events und gemeinsamen Kontakten.', value: _showSuggestions, onChanged: (value) => setState(() => _showSuggestions = value)),
                              _SwitchRow(title: 'Vereinskontext anzeigen', subtitle: 'Gemeinsame Vereine, Teams und Trainings als Vertrauenshinweis.', value: _showClubContext, onChanged: (value) => setState(() => _showClubContext = value)),
                              _SwitchRow(title: 'Blockierte anzeigen', subtitle: 'Blockierte Kontakte, Entsperren und Meldeoptionen einblenden.', value: _showBlocked, onChanged: (value) => setState(() => _showBlocked = value)),
                            ],
                          ),
                        ),
                        const SizedBox(height: 16),
                        for (final item in items) ...[
                          _FriendCard(item: item, onOpen: () => _toast('${item.title}: Kontakt-Detail vorbereitet')),
                          const SizedBox(height: 12),
                        ],
                        if (items.isEmpty) const EmptyPanel('Keine Kontakte fuer diese Ansicht gefunden.'),
                        const SizedBox(height: 16),
                        AirmiusPanel(
                          title: 'Aktionen',
                          child: Wrap(
                            spacing: 10,
                            runSpacing: 10,
                            children: [
                              AirmiusButton(label: 'Freund einladen', icon: Icons.person_add_outlined, onPressed: () => _toast('Einladung senden vorbereitet')),
                              AirmiusButton(label: 'Profil & Sicherheit', icon: Icons.manage_accounts_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => ProfileSecurityCenterScreen()))),
                              AirmiusButton(label: 'Melden', icon: Icons.flag_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => ReportModerationCenterScreen()))),
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

class _FriendsHero extends StatelessWidget {
  const _FriendsHero({required this.onInvite});

  final VoidCallback onInvite;

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
              const Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Eyebrow('SOCIAL GRAPH'), SizedBox(height: 4), Text('Kontakte mit Vereinskontext', style: TextStyle(color: AirmiusColors.text, fontSize: 22, fontWeight: FontWeight.w900))])),
              AirmiusButton(label: 'Einladen', icon: Icons.person_add_outlined, onPressed: onInvite),
            ],
          ),
          const SizedBox(height: 14),
          const Text('Das Friends-Webmodul wird als mobile UI abgebildet: Kontakte, Anfragen, Vorschlaege, Vereinskontext, Blockieren und Melden.', style: TextStyle(color: AirmiusColors.muted, height: 1.45, fontWeight: FontWeight.w700)),
          const SizedBox(height: 16),
          const Row(children: [Expanded(child: MetricCard(value: '14', label: 'Freunde')), SizedBox(width: 10), Expanded(child: MetricCard(value: '3', label: 'Anfragen')), SizedBox(width: 10), Expanded(child: MetricCard(value: '6', label: 'Vorschlaege'))]),
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
        Switch.adaptive(value: value, onChanged: onChanged, activeColor: AirmiusColors.blue),
      ]),
    );
  }
}

class _FriendCard extends StatelessWidget {
  const _FriendCard({required this.item, required this.onOpen});

  final _FriendItem item;
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
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [StatusPill(item.status, color: item.color), const SizedBox(height: 8), Text(item.meta, style: const TextStyle(color: AirmiusColors.blue, fontWeight: FontWeight.w900)), const SizedBox(height: 6), Text(item.body, style: const TextStyle(color: AirmiusColors.muted, height: 1.45, fontWeight: FontWeight.w700))])),
          IconButton(onPressed: onOpen, icon: const Icon(Icons.chevron_right, color: AirmiusColors.muted)),
        ],
      ),
    );
  }
}

class _FriendItem {
  const _FriendItem({required this.title, required this.area, required this.body, required this.status, required this.meta, required this.icon, required this.color});

  final String title;
  final String area;
  final String body;
  final String status;
  final String meta;
  final IconData icon;
  final Color color;
}
