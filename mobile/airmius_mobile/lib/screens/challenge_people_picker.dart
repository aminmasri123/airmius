import 'package:flutter/material.dart';

class ChallengePeoplePicker extends StatefulWidget {
  const ChallengePeoplePicker({super.key, required this.people, required this.selected, required this.copy});
  final List<Map<String, dynamic>> people;
  final Set<int> selected;
  final String Function(String, String, String, String) copy;

  @override
  State<ChallengePeoplePicker> createState() => _ChallengePeoplePickerState();
}

class _ChallengePeoplePickerState extends State<ChallengePeoplePicker> {
  late final _selected = Set<int>.of(widget.selected);
  final _search = TextEditingController();
  bool _onlySelected = false;

  @override
  void dispose() { _search.dispose(); super.dispose(); }

  @override
  Widget build(BuildContext context) {
    final c = widget.copy;
    final query = _search.text.trim().toLowerCase();
    final people = {for (final person in widget.people)
      if (int.tryParse('${person['id']}') != null) int.parse('${person['id']}'): person}.values.where((person) =>
        '${person['name'] ?? ''}'.toLowerCase().contains(query) &&
        (!_onlySelected || _selected.contains(int.parse('${person['id']}')))).toList();
    return Scaffold(
      appBar: AppBar(title: Text(c('Personen auswählen', 'Choose people', 'Choisir des personnes', 'اختيار الأشخاص'))),
      body: SafeArea(child: Column(children: [
        Padding(padding: const EdgeInsets.all(20), child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          TextField(controller: _search, onChanged: (_) => setState(() {}),
            decoration: InputDecoration(prefixIcon: const Icon(Icons.search),
              labelText: c('Nach Namen suchen', 'Search by name', 'Rechercher un nom', 'البحث بالاسم'),
              suffixIcon: _search.text.isEmpty ? null : IconButton(
                tooltip: c('Suche löschen', 'Clear search', 'Effacer la recherche', 'مسح البحث'),
                icon: const Icon(Icons.clear), onPressed: () => setState(_search.clear)),
            )),
          const SizedBox(height: 12),
          FilterChip(label: Text('${c('Nur ausgewählte', 'Selected only', 'Sélection uniquement', 'المحددون فقط')} (${_selected.length})'),
            selected: _onlySelected, onSelected: (value) => setState(() => _onlySelected = value)),
          Text(c('Die Auswahl wird erst beim Speichern der Challenge übernommen.', 'People are invited only when you save the challenge.', 'Les invitations sont envoyées lors de l’enregistrement du défi.', 'تُرسل الدعوات عند حفظ التحدي.')),
        ])),
        Expanded(child: people.isEmpty
          ? Center(child: Text(c('Keine passenden Personen', 'No matching people', 'Aucune personne correspondante', 'لا توجد نتائج')))
          : ListView.builder(itemCount: people.length, itemBuilder: (context, index) {
              final person = people[index];
              final id = int.parse('${person['id']}');
              final name = '${person['name'] ?? ''}';
              final image = person['profile_photo_url']?.toString();
              return CheckboxListTile(
                key: ValueKey(id), title: Text(name),
                secondary: CircleAvatar(backgroundImage: image != null && image.isNotEmpty ? NetworkImage(image) : null,
                  child: image == null || image.isEmpty ? const Icon(Icons.person_outline) : null),
                value: _selected.contains(id),
                onChanged: (value) => setState(() { value == true ? _selected.add(id) : _selected.remove(id); }),
              );
            })),
        Padding(padding: const EdgeInsets.all(20), child: SizedBox(width: double.infinity,
          child: FilledButton(onPressed: () => Navigator.pop(context, _selected),
            child: Text('${c('Auswahl übernehmen', 'Use selection', 'Valider la sélection', 'تأكيد الاختيار')} (${_selected.length})')))),
      ])),
    );
  }
}
