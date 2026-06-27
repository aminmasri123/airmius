import 'package:flutter/material.dart';

import '../core/api_contract.dart';
import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

class SearchOperationsScreen extends StatefulWidget {
  const SearchOperationsScreen({super.key});

  @override
  State<SearchOperationsScreen> createState() => _SearchOperationsScreenState();
}

class _SearchOperationsScreenState extends State<SearchOperationsScreen> {
  String _tab = 'Alle';
  bool _clubs = true;
  bool _maturitySafe = true;
  bool _recentFirst = false;

  @override
  Widget build(BuildContext context) {
    final items = _tab == 'Alle' ? _operations : _operations.where((item) => item.tab == _tab).toList();
    return Scaffold(
      backgroundColor: AirmiusColors.bg,
      appBar: AppBar(backgroundColor: AirmiusColors.header, surfaceTintColor: Colors.transparent, title: const Text('Search Ops', style: TextStyle(fontWeight: FontWeight.w900))),
      body: PageFrame(
        title: 'Search Ops',
        subtitle: 'Globale Suche, Autocomplete, Ergebnistypen, Ranking und Maturity-Schutz',
        child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          AirmiusPanel(borderColor: AirmiusColors.blue.withValues(alpha: .42), child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            const Eyebrow('Globale Suche'),
            const SizedBox(height: 8),
            const Text('Die Suche muss Personen, Vereine, Teams, Dateien, Kurse, Events, Produkte und Public-Inhalte typisiert liefern. Genau diese Logik wird hier nativ vorbereitet.', style: TextStyle(color: AirmiusColors.muted, height: 1.4)),
            const SizedBox(height: 12),
            SwitchListTile(value: _clubs, onChanged: (value) => setState(() => _clubs = value), activeColor: AirmiusColors.green, contentPadding: EdgeInsets.zero, title: const Text('Vereine einschließen', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), subtitle: const Text('Header-Suche darf nicht nur Personen liefern.', style: TextStyle(color: AirmiusColors.muted))),
            SwitchListTile(value: _maturitySafe, onChanged: (value) => setState(() => _maturitySafe = value), activeColor: AirmiusColors.blue, contentPadding: EdgeInsets.zero, title: const Text('Maturity-Schutz', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), subtitle: const Text('Minderjaehrige sehen nur erlaubte Inhalte.', style: TextStyle(color: AirmiusColors.muted))),
            SwitchListTile(value: _recentFirst, onChanged: (value) => setState(() => _recentFirst = value), activeColor: AirmiusColors.amber, contentPadding: EdgeInsets.zero, title: const Text('Neueste zuerst', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), subtitle: const Text('Ranking nach Relevanz oder Aktualitaet steuern.', style: TextStyle(color: AirmiusColors.muted))),
            const SizedBox(height: 10),
            Wrap(spacing: 8, runSpacing: 8, children: [for (final tab in _tabs) ChoiceChip(label: Text(tab), selected: _tab == tab, onSelected: (_) => setState(() => _tab = tab), selectedColor: AirmiusColors.blue.withValues(alpha: .22), backgroundColor: AirmiusColors.panelSoft, side: BorderSide(color: _tab == tab ? AirmiusColors.blue : AirmiusColors.border), labelStyle: TextStyle(color: _tab == tab ? AirmiusColors.text : AirmiusColors.muted, fontWeight: FontWeight.w900))]),
          ])),
          const SizedBox(height: 16),
          for (final item in items) ...[_SearchOperationCard(item: item), const SizedBox(height: 12)],
        ]),
      ),
    );
  }
}

class _SearchOperationCard extends StatelessWidget {
  const _SearchOperationCard({required this.item});
  final _SearchOperation item;

  @override
  Widget build(BuildContext context) => AirmiusPanel(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
    Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Container(width: 48, height: 48, decoration: BoxDecoration(color: item.color.withValues(alpha: .13), borderRadius: BorderRadius.circular(16), border: Border.all(color: item.color.withValues(alpha: .45))), child: Icon(item.icon, color: item.color)),
      const SizedBox(width: 12),
      Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text(item.title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900, fontSize: 16)), const SizedBox(height: 5), Text(item.body, style: const TextStyle(color: AirmiusColors.muted, height: 1.35))])),
      StatusPill(item.tab, color: item.color),
    ]),
    const SizedBox(height: 12),
    Container(width: double.infinity, padding: const EdgeInsets.all(12), decoration: BoxDecoration(color: AirmiusColors.bg, borderRadius: BorderRadius.circular(14), border: Border.all(color: AirmiusColors.border)), child: Text('${item.method} ${item.endpoint}', style: const TextStyle(color: AirmiusColors.green, fontSize: 12, fontWeight: FontWeight.w900))),
    const SizedBox(height: 12),
    Wrap(spacing: 8, runSpacing: 8, children: [
      AirmiusButton(label: item.action, icon: item.icon, onPressed: () => openUiAction(context, title: item.title, body: '${item.body}\n\nEndpoint: ${item.method} ${item.endpoint}', status: item.tab, icon: item.icon)),
      AirmiusButton(label: 'Result Routing', icon: Icons.open_in_new, secondary: true, onPressed: () => openUiAction(context, title: '${item.title} Routing', body: 'Result-Type, Zielscreen, Permission, Maturity, Highlight und Leerzustand anzeigen.', status: 'Routing', icon: Icons.open_in_new)),
    ]),
  ]));
}

class _SearchOperation {
  const _SearchOperation({required this.tab, required this.title, required this.body, required this.method, required this.endpoint, required this.icon, required this.action, required this.color});
  final String tab;
  final String title;
  final String body;
  final String method;
  final String endpoint;
  final IconData icon;
  final String action;
  final Color color;
}

const _tabs = ['Alle', 'Personen', 'Vereine', 'Teams', 'Content', 'Commerce', 'Safety'];

final _operations = <_SearchOperation>[
  _SearchOperation(tab: 'Personen', title: 'Personen suchen', body: 'Nutzer, Freunde, Admins und Kontakte typisiert suchen.', method: 'GET', endpoint: ApiContract.globalSearchType('people'), icon: Icons.person_search_outlined, action: 'Suchen', color: AirmiusColors.blue),
  _SearchOperation(tab: 'Vereine', title: 'Vereine suchen', body: 'Vereine müssen im Header-Suchfeld angeboten und direkt öffenbar sein.', method: 'GET', endpoint: ApiContract.globalSearchType('clubs'), icon: Icons.groups_outlined, action: 'Vereine', color: AirmiusColors.green),
  _SearchOperation(tab: 'Teams', title: 'Teams suchen', body: 'Teams, Kader, Trainingsgruppen und Einladungen finden.', method: 'GET', endpoint: ApiContract.globalSearchType('teams'), icon: Icons.groups_2_outlined, action: 'Teams', color: AirmiusColors.blue),
  _SearchOperation(tab: 'Content', title: 'Dateien suchen', body: 'Vereinsdokumente, Teamdateien und geteilte Dateien finden.', method: 'GET', endpoint: ApiContract.globalSearchType('files'), icon: Icons.folder_outlined, action: 'Dateien', color: AirmiusColors.amber),
  _SearchOperation(tab: 'Content', title: 'Kurse & Events suchen', body: 'Kurse, Zertifikate, Events und Trainingskontexte finden.', method: 'GET', endpoint: ApiContract.globalSearchType('learning-events'), icon: Icons.school_outlined, action: 'Content', color: AirmiusColors.green),
  _SearchOperation(tab: 'Commerce', title: 'Produkte suchen', body: 'Marketplace-Produkte, Anbieter und Angebote suchen.', method: 'GET', endpoint: ApiContract.globalSearchType('products'), icon: Icons.storefront_outlined, action: 'Produkte', color: AirmiusColors.blue),
  _SearchOperation(tab: 'Safety', title: 'Maturity Search', body: 'Altersgerechte Suche mit Safety-Filter für Feed und Discovery.', method: 'GET', endpoint: ApiContract.maturitySearch, icon: Icons.security_outlined, action: 'Safety', color: AirmiusColors.amber),
];
