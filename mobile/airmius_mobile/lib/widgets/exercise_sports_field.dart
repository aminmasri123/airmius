import 'package:flutter/material.dart';
import '../core/exercise_suitability.dart';

class ExerciseSportsField extends StatelessWidget {
  const ExerciseSportsField({
    super.key,
    required this.selected,
    required this.sports,
    required this.onChanged,
    this.filter = false,
  });
  final List<String> selected;
  final List<Map<String, dynamic>> sports;
  final ValueChanged<List<String>> onChanged;
  final bool filter;

  @override
  Widget build(BuildContext context) {
    String sc(String key) => suitabilityCopy(context, key);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        OutlinedButton(
          key: ValueKey(
            filter ? 'exercise-sport-filter' : 'exercise-suitability',
          ),
          style: OutlinedButton.styleFrom(padding: const EdgeInsets.all(16)),
          onPressed: () async {
            final result = await Navigator.of(context).push<List<String>>(
              MaterialPageRoute(
                builder: (_) => ExerciseSportsPicker(
                  selected: selected,
                  sports: sports,
                  filter: filter,
                ),
              ),
            );
            if (result != null) onChanged(result);
          },
          child: Row(
            children: [
              const Icon(Icons.sports_outlined),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      sc(filter ? 'allSports' : 'suitable'),
                      style: Theme.of(context).textTheme.labelLarge,
                    ),
                    const SizedBox(height: 4),
                    Text(
                      selected.isEmpty
                          ? sc(filter ? 'allSports' : 'general')
                          : selected.join(', '),
                    ),
                  ],
                ),
              ),
              const Icon(Icons.chevron_right),
            ],
          ),
        ),
        const SizedBox(height: 8),
        Text(
          sc(filter ? 'filterHint' : 'hint'),
          style: Theme.of(context).textTheme.bodySmall,
        ),
      ],
    );
  }
}

class ExerciseSportsPicker extends StatefulWidget {
  const ExerciseSportsPicker({
    super.key,
    required this.selected,
    required this.sports,
    this.filter = false,
  });
  final List<String> selected;
  final List<Map<String, dynamic>> sports;
  final bool filter;
  @override
  State<ExerciseSportsPicker> createState() => _ExerciseSportsPickerState();
}

class _ExerciseSportsPickerState extends State<ExerciseSportsPicker> {
  late final _selected = {...widget.selected};
  final _search = TextEditingController();
  @override
  void dispose() {
    _search.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    String sc(String key) => suitabilityCopy(context, key);
    final options = {
      ...widget.sports
          .map((sport) => sport['value']?.toString() ?? '')
          .where((value) => value.isNotEmpty),
      ...widget.selected,
    }.toList()..sort();
    final matches = options
        .where(
          (sport) =>
              sportSearch(sport).contains(sportSearch(_search.text.trim())),
        )
        .toList();
    return Scaffold(
      appBar: AppBar(title: Text(sc(widget.filter ? 'allSports' : 'choose'))),
      body: SafeArea(
        child: Column(
          children: [
            Padding(
              padding: const EdgeInsets.all(16),
              child: TextField(
                key: const ValueKey('exercise-sport-search'),
                controller: _search,
                onChanged: (_) => setState(() {}),
                decoration: InputDecoration(
                  labelText: sc('search'),
                  prefixIcon: const Icon(Icons.search),
                  suffixIcon: _search.text.isEmpty
                      ? null
                      : IconButton(
                          icon: const Icon(Icons.clear),
                          onPressed: () => setState(_search.clear),
                        ),
                ),
              ),
            ),
            CheckboxListTile(
              title: Text(sc(widget.filter ? 'allSports' : 'general')),
              value: _selected.isEmpty,
              onChanged: (_) => setState(_selected.clear),
            ),
            const Divider(),
            Expanded(
              child: matches.isEmpty
                  ? Center(child: Text(sc('empty')))
                  : ListView.builder(
                      itemCount: matches.length,
                      itemBuilder: (context, index) {
                        final sport = matches[index];
                        return CheckboxListTile(
                          title: Text(sport),
                          value: _selected.contains(sport),
                          onChanged: (checked) => setState(() {
                            if (checked == true) {
                              if (widget.filter) _selected.clear();
                              _selected.add(sport);
                            } else {
                              _selected.remove(sport);
                            }
                          }),
                        );
                      },
                    ),
            ),
          ],
        ),
      ),
      bottomNavigationBar: SafeArea(
        minimum: const EdgeInsets.all(16),
        child: FilledButton(
          onPressed: () => Navigator.pop(context, _selected.toList()),
          child: Text(
            '${sc('done')}${_selected.isEmpty ? '' : ' (${_selected.length})'}',
          ),
        ),
      ),
    );
  }
}
