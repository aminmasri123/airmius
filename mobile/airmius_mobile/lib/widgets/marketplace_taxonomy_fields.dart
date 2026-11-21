import 'package:flutter/material.dart';

class MarketplaceClassification {
  const MarketplaceClassification({
    this.group = '',
    this.subcategory = '',
    this.sports = const {},
  });

  factory MarketplaceClassification.fromProduct(Map<String, dynamic>? product) {
    final value = product?['sports'];
    return MarketplaceClassification(
      group: product?['catalog_group'] as String? ?? '',
      subcategory: product?['catalog_subcategory'] as String? ?? '',
      sports: value is List ? value.whereType<String>().toSet() : const {},
    );
  }

  final String group;
  final String subcategory;
  final Set<String> sports;

  bool get complete => group.isEmpty || subcategory.isNotEmpty;

  Map<String, dynamic> toJson() => {
    'catalog_group': group.isEmpty ? null : group,
    'catalog_subcategory': subcategory.isEmpty ? null : subcategory,
    'sports': sports.toList(),
  };
}

class MarketplaceTaxonomyFields extends StatelessWidget {
  const MarketplaceTaxonomyFields({
    super.key,
    required this.value,
    required this.onChanged,
    this.facets,
    this.buyer = false,
  });

  final MarketplaceClassification value;
  final ValueChanged<MarketplaceClassification> onChanged;
  final Map<String, dynamic>? facets;
  final bool buyer;

  @override
  Widget build(BuildContext context) {
    final facetGroups = facets?['groups'];
    final available = facetGroups is Map ? facetGroups : const {};
    final groups = {
      for (final entry in _catalogGroups.entries)
        if (!buyer ||
            available.containsKey(entry.key) ||
            value.group == entry.key)
          entry.key: entry.value.$1,
    };
    final facetGroup = available[value.group];
    final facetChildren = facetGroup is Map ? facetGroup['children'] : null;
    final children = {
      for (final entry
          in (_catalogGroups[value.group]?.$2 ?? const <String, String>{})
              .entries)
        if (!buyer ||
            (facetChildren is Map && facetChildren.containsKey(entry.key)) ||
            value.subcategory == entry.key)
          entry.key: entry.value,
    };
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        _dropdown(
          'Hauptkategorie',
          buyer ? 'Alle Hauptkategorien' : 'Hauptkategorie wählen',
          value.group,
          groups,
          (group) => onChanged(
            MarketplaceClassification(group: group, sports: value.sports),
          ),
        ),
        const SizedBox(height: 12),
        if (value.group.isNotEmpty) ...[
          _dropdown(
            'Unterkategorie',
            buyer ? 'Alle Unterkategorien' : 'Unterkategorie wählen',
            value.subcategory,
            children,
            (subcategory) => onChanged(
              MarketplaceClassification(
                group: value.group,
                subcategory: subcategory,
                sports: value.sports,
              ),
            ),
            required: !buyer,
          ),
          const SizedBox(height: 12),
        ],
        if (buyer)
          _dropdown(
            'Sportart',
            'Alle Sportarten',
            value.sports.firstOrNull ?? '',
            _sports,
            (sport) => onChanged(
              MarketplaceClassification(
                group: value.group,
                subcategory: value.subcategory,
                sports: sport.isEmpty ? const {} : {sport},
              ),
            ),
          )
        else ...[
          Text('Sportarten', style: Theme.of(context).textTheme.titleSmall),
          const SizedBox(height: 6),
          Wrap(
            spacing: 8,
            runSpacing: 4,
            children: [
              for (final sport in _sports.entries)
                FilterChip(
                  label: Text(sport.value),
                  selected: value.sports.contains(sport.key),
                  onSelected: (selected) {
                    final sports = {...value.sports};
                    if (selected) {
                      sports.add(sport.key);
                    } else {
                      sports.remove(sport.key);
                    }
                    onChanged(
                      MarketplaceClassification(
                        group: value.group,
                        subcategory: value.subcategory,
                        sports: sports,
                      ),
                    );
                  },
                ),
            ],
          ),
        ],
      ],
    );
  }

  Widget _dropdown(
    String label,
    String emptyLabel,
    String selected,
    Map<String, String> options,
    ValueChanged<String> onChanged, {
    bool required = false,
  }) => DropdownButtonFormField<String>(
    key: ValueKey((label, selected)),
    initialValue: options.containsKey(selected) ? selected : '',
    isExpanded: true,
    decoration: InputDecoration(labelText: label),
    items: [
      DropdownMenuItem(value: '', child: Text(emptyLabel)),
      for (final option in options.entries)
        DropdownMenuItem(value: option.key, child: Text(option.value)),
    ],
    validator: required
        ? (value) => value == null || value.isEmpty
              ? 'Bitte Unterkategorie wählen'
              : null
        : null,
    onChanged: (value) => onChanged(value ?? ''),
  );
}

const _catalogGroups = {
  'apparel': (
    'Sportbekleidung',
    {
      'shorts': 'Shorts',
      'pants_leggings': 'Hosen & Leggings',
      'shirts_tops': 'Shirts & Tops',
      'jerseys': 'Trikots',
      'jackets_vests': 'Jacken & Westen',
      'sports_socks': 'Sportsocken',
    },
  ),
  'shoes': (
    'Sportschuhe',
    {
      'running_shoes': 'Laufschuhe',
      'trail_shoes': 'Trailrunning-Schuhe',
      'football_shoes': 'Fußballschuhe',
      'training_shoes': 'Fitness- & Trainingsschuhe',
      'indoor_shoes': 'Hallenschuhe',
    },
  ),
  'accessories': (
    'Sportzubehör',
    {
      'bags': 'Taschen & Rucksäcke',
      'bottles': 'Trinkflaschen & Trinksysteme',
      'headwear': 'Caps & Stirnbänder',
      'running_belts': 'Laufgürtel',
      'protection': 'Schutzausrüstung',
    },
  ),
  'equipment': (
    'Sportausrüstung',
    {
      'balls': 'Bälle',
      'strength': 'Krafttraining',
      'coordination': 'Lauf- & Koordinationstraining',
      'club_equipment': 'Vereins- & Trainingsmaterial',
    },
  ),
  'recovery': (
    'Regeneration',
    {
      'foam_rollers': 'Faszienrollen & Massagebälle',
      'massagers': 'Massagegeräte',
      'hot_cold': 'Kühl- & Wärmeprodukte',
    },
  ),
  'nutrition': (
    'Sporternährung',
    {
      'gels_bars': 'Energiegels & Riegel',
      'electrolytes': 'Elektrolyte & Sportgetränke',
      'protein': 'Proteinprodukte',
    },
  ),
};

const _sports = {
  'running': 'Laufen',
  'trailrunning': 'Trailrunning',
  'football': 'Fußball',
  'fitness': 'Fitness',
  'team_sport': 'Teamsport',
};
