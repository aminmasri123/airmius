import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import '../core/airmius_l10n.dart';
import '../core/marketplace_filters.dart';
import '../core/marketplace_filter_labels.dart';
import 'marketplace_taxonomy_fields.dart';

class MarketplaceFiltersSheet extends StatefulWidget {
  const MarketplaceFiltersSheet({
    super.key,
    required this.initial,
    required this.categories,
    this.taxonomy = const {},
  });
  final MarketplaceFilters initial;
  final List<String> categories;
  final Map<String, dynamic> taxonomy;

  @override
  State<MarketplaceFiltersSheet> createState() =>
      _MarketplaceFiltersSheetState();
}

class _MarketplaceFiltersSheetState extends State<MarketplaceFiltersSheet> {
  final _form = GlobalKey<FormState>();
  late String _category;
  late MarketplaceClassification _taxonomy;
  late String _sort;
  late double _rating;
  late final TextEditingController _min;
  late final TextEditingController _max;
  bool _initialized = false;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (_initialized) return;
    _initialized = true;
    _category = widget.initial.category;
    _taxonomy = MarketplaceClassification(
      group: widget.initial.catalogGroup,
      subcategory: widget.initial.catalogSubcategory,
      sports: {if (widget.initial.sport.isNotEmpty) widget.initial.sport},
    );
    _sort = widget.initial.sort;
    _rating = widget.initial.minRating;
    final language = AirmiusScope.of(context).language.locale.languageCode;
    String price(int? cents) =>
        cents == null ? '' : NumberFormat('0.00', language).format(cents / 100);
    _min = TextEditingController(text: price(widget.initial.minPriceCents));
    _max = TextEditingController(text: price(widget.initial.maxPriceCents));
  }

  @override
  void dispose() {
    _min.dispose();
    _max.dispose();
    super.dispose();
  }

  String t(String key) => marketplaceFilterText(context, key);

  String? _validatePrice(String? text, {bool maximum = false}) {
    try {
      final cents = MarketplaceFilters.parsePrice(text ?? '');
      if (maximum && cents != null) {
        final min = MarketplaceFilters.parsePrice(_min.text);
        if (min != null && cents < min) return t('rangeError');
      }
      return null;
    } on FormatException {
      return t('priceError');
    }
  }

  Widget _priceField(
    TextEditingController controller,
    String label, {
    bool maximum = false,
  }) => TextFormField(
    key: ValueKey(maximum ? 'market-price-max' : 'market-price-min'),
    controller: controller,
    keyboardType: const TextInputType.numberWithOptions(decimal: true),
    textInputAction: maximum ? TextInputAction.done : TextInputAction.next,
    validator: (value) => _validatePrice(value, maximum: maximum),
    decoration: InputDecoration(
      labelText: t(label),
      hintText: t('priceHint'),
      errorMaxLines: 3,
      border: const OutlineInputBorder(),
    ),
  );

  void _apply() {
    FocusScope.of(context).unfocus();
    if (!_form.currentState!.validate()) return;
    Navigator.pop(
      context,
      MarketplaceFilters(
        category: _category,
        catalogGroup: _taxonomy.group,
        catalogSubcategory: _taxonomy.subcategory,
        sport: _taxonomy.sports.firstOrNull ?? '',
        minPriceCents: MarketplaceFilters.parsePrice(_min.text),
        maxPriceCents: MarketplaceFilters.parsePrice(_max.text),
        minRating: _rating,
        sort: _sort,
      ),
    );
  }

  void _reset() {
    _form.currentState?.reset();
    _min.clear();
    _max.clear();
    setState(() {
      _category = '';
      _taxonomy = const MarketplaceClassification();
      _sort = 'newest';
      _rating = 0;
    });
  }

  @override
  Widget build(BuildContext context) {
    const ink = Color(0xFF142033);
    const blue = Color(0xFF0EA5E9);
    final categories = {
      ...widget.categories,
      if (_category.isNotEmpty) _category,
    }.toList()..sort();
    return Theme(
      data: ThemeData(
        useMaterial3: true,
        colorScheme: ColorScheme.fromSeed(
          seedColor: blue,
          brightness: Brightness.light,
        ),
        textTheme: Theme.of(
          context,
        ).textTheme.apply(bodyColor: ink, displayColor: ink),
      ),
      child: LayoutBuilder(
        builder: (context, constraints) {
          final inset = MediaQuery.viewInsetsOf(context).bottom;
          final available = (constraints.maxHeight - inset).clamp(0.0, 760.0);
          return Padding(
            padding: EdgeInsets.only(bottom: inset),
            child: SizedBox(
              height: available * .94,
              child: Material(
                color: Colors.white,
                borderRadius: const BorderRadius.vertical(
                  top: Radius.circular(24),
                ),
                clipBehavior: Clip.antiAlias,
                child: SafeArea(
                  top: false,
                  child: Column(
                    children: [
                      Padding(
                        padding: const EdgeInsetsDirectional.fromSTEB(
                          20,
                          12,
                          8,
                          8,
                        ),
                        child: Row(
                          children: [
                            Expanded(
                              child: Text(
                                t('title'),
                                style: const TextStyle(
                                  fontSize: 20,
                                  fontWeight: FontWeight.w800,
                                ),
                              ),
                            ),
                            IconButton(
                              tooltip: t('close'),
                              onPressed: () => Navigator.pop(context),
                              icon: const Icon(Icons.close),
                            ),
                          ],
                        ),
                      ),
                      const Divider(height: 1),
                      Expanded(
                        child: SingleChildScrollView(
                          padding: const EdgeInsets.all(20),
                          child: Form(
                            key: _form,
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.stretch,
                              children: [
                                DropdownButtonFormField<String>(
                                  key: ValueKey('category-$_category'),
                                  initialValue: _category,
                                  isExpanded: true,
                                  decoration: InputDecoration(
                                    labelText: t('category'),
                                    border: const OutlineInputBorder(),
                                  ),
                                  items: [
                                    DropdownMenuItem(
                                      value: '',
                                      child: Text(
                                        t('allCategories'),
                                        overflow: TextOverflow.ellipsis,
                                      ),
                                    ),
                                    ...categories.map(
                                      (value) => DropdownMenuItem(
                                        value: value,
                                        child: Text(
                                          marketplaceCategoryLabel(
                                            context,
                                            value,
                                          ),
                                          overflow: TextOverflow.ellipsis,
                                        ),
                                      ),
                                    ),
                                  ],
                                  onChanged: (value) =>
                                      setState(() => _category = value ?? ''),
                                ),
                                const SizedBox(height: 24),
                                Text(
                                  t('price'),
                                  style: const TextStyle(
                                    fontWeight: FontWeight.w800,
                                  ),
                                ),
                                const SizedBox(height: 14),
                                MarketplaceTaxonomyFields(
                                  buyer: true,
                                  facets: widget.taxonomy,
                                  value: _taxonomy,
                                  onChanged: (value) =>
                                      setState(() => _taxonomy = value),
                                ),
                                const SizedBox(height: 14),
                                LayoutBuilder(
                                  builder: (context, bounds) {
                                    final vertical =
                                        bounds.maxWidth < 320 ||
                                        MediaQuery.textScalerOf(
                                              context,
                                            ).scale(1) >
                                            1.25;
                                    final minimum = _priceField(_min, 'from');
                                    final maximum = _priceField(
                                      _max,
                                      'to',
                                      maximum: true,
                                    );
                                    return vertical
                                        ? Column(
                                            children: [
                                              minimum,
                                              const SizedBox(height: 16),
                                              maximum,
                                            ],
                                          )
                                        : Row(
                                            crossAxisAlignment:
                                                CrossAxisAlignment.start,
                                            children: [
                                              Expanded(child: minimum),
                                              const SizedBox(width: 16),
                                              Expanded(child: maximum),
                                            ],
                                          );
                                  },
                                ),
                                const SizedBox(height: 24),
                                Text(
                                  t('rating'),
                                  style: const TextStyle(
                                    fontWeight: FontWeight.w800,
                                  ),
                                ),
                                const SizedBox(height: 8),
                                Wrap(
                                  spacing: 8,
                                  runSpacing: 8,
                                  children: [0.0, 3.0, 4.0, 4.5, 5.0].map((
                                    value,
                                  ) {
                                    final stars = NumberFormat(
                                      '0.#',
                                      AirmiusScope.of(
                                        context,
                                      ).language.locale.languageCode,
                                    ).format(value);
                                    return Semantics(
                                      label: value == 0
                                          ? t('allRatings')
                                          : t(
                                              'stars',
                                            ).replaceAll('{rating}', stars),
                                      child: ChoiceChip(
                                        key: ValueKey('market-rating-$value'),
                                        selected: _rating == value,
                                        showCheckmark: false,
                                        avatar: value == 0
                                            ? null
                                            : const Icon(
                                                Icons.star_rounded,
                                                size: 18,
                                              ),
                                        label: Text(
                                          value == 0
                                              ? t('allRatings')
                                              : '$stars+',
                                        ),
                                        padding: const EdgeInsets.symmetric(
                                          horizontal: 8,
                                          vertical: 10,
                                        ),
                                        onSelected: (_) =>
                                            setState(() => _rating = value),
                                      ),
                                    );
                                  }).toList(),
                                ),
                                const SizedBox(height: 24),
                                DropdownButtonFormField<String>(
                                  key: ValueKey('sort-$_sort'),
                                  initialValue: _sort,
                                  isExpanded: true,
                                  decoration: InputDecoration(
                                    labelText: t('sort'),
                                    border: const OutlineInputBorder(),
                                  ),
                                  items: ['newest', 'price_asc', 'price_desc']
                                      .map(
                                        (value) => DropdownMenuItem(
                                          value: value,
                                          child: Text(
                                            t(value),
                                            overflow: TextOverflow.ellipsis,
                                          ),
                                        ),
                                      )
                                      .toList(),
                                  onChanged: (value) =>
                                      setState(() => _sort = value ?? 'newest'),
                                ),
                              ],
                            ),
                          ),
                        ),
                      ),
                      const Divider(height: 1),
                      Padding(
                        padding: const EdgeInsets.fromLTRB(16, 12, 16, 12),
                        child: Row(
                          children: [
                            Expanded(
                              child: OutlinedButton(
                                onPressed: _reset,
                                style: OutlinedButton.styleFrom(
                                  minimumSize: const Size(0, 52),
                                  shape: RoundedRectangleBorder(
                                    borderRadius: BorderRadius.circular(12),
                                  ),
                                  padding: const EdgeInsets.symmetric(
                                    horizontal: 12,
                                    vertical: 12,
                                  ),
                                ),
                                child: Text(
                                  t('reset'),
                                  textAlign: TextAlign.center,
                                ),
                              ),
                            ),
                            const SizedBox(width: 12),
                            Expanded(
                              child: FilledButton(
                                key: const ValueKey('market-apply-filters'),
                                onPressed: _apply,
                                style: FilledButton.styleFrom(
                                  minimumSize: const Size(0, 52),
                                  shape: RoundedRectangleBorder(
                                    borderRadius: BorderRadius.circular(12),
                                  ),
                                  padding: const EdgeInsets.symmetric(
                                    horizontal: 12,
                                    vertical: 12,
                                  ),
                                ),
                                child: Text(
                                  t('apply'),
                                  textAlign: TextAlign.center,
                                ),
                              ),
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ),
          );
        },
      ),
    );
  }
}
