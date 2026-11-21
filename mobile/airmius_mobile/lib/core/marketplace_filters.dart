class MarketplaceFilters {
  const MarketplaceFilters({
    this.category = '',
    this.catalogGroup = '',
    this.catalogSubcategory = '',
    this.sport = '',
    this.minPriceCents,
    this.maxPriceCents,
    this.minRating = 0,
    this.sort = 'newest',
  });

  final String category;
  final String catalogGroup;
  final String catalogSubcategory;
  final String sport;
  final int? minPriceCents;
  final int? maxPriceCents;
  final double minRating;
  final String sort;

  int get activeCount =>
      (category.isEmpty ? 0 : 1) +
      (catalogGroup.isEmpty ? 0 : 1) +
      (catalogSubcategory.isEmpty ? 0 : 1) +
      (sport.isEmpty ? 0 : 1) +
      (minPriceCents != null || maxPriceCents != null ? 1 : 0) +
      (minRating > 0 ? 1 : 0) +
      (sort == 'newest' ? 0 : 1);

  MarketplaceFilters copyWith({
    String? category,
    bool clearTaxonomy = false,
    bool clearPrice = false,
    double? minRating,
    String? sort,
  }) => MarketplaceFilters(
    category: category ?? this.category,
    catalogGroup: clearTaxonomy ? '' : catalogGroup,
    catalogSubcategory: clearTaxonomy ? '' : catalogSubcategory,
    sport: clearTaxonomy ? '' : sport,
    minPriceCents: clearPrice ? null : minPriceCents,
    maxPriceCents: clearPrice ? null : maxPriceCents,
    minRating: minRating ?? this.minRating,
    sort: sort ?? this.sort,
  );

  /// Accept decimal comma/point without rounding user input through floating point.
  static int? parsePrice(String value) {
    final text = value.trim().replaceAll('٫', '.').replaceAllMapped(
      RegExp('[٠-٩۰-۹]'),
      (match) {
        final rune = match[0]!.runes.first;
        return '${rune - (rune >= 0x6F0 ? 0x6F0 : 0x660)}';
      },
    );
    if (text.isEmpty) return null;
    if (!RegExp(r'^\d{1,7}([,.]\d{1,2})?$').hasMatch(text)) {
      throw const FormatException();
    }
    final parts = text.replaceAll(',', '.').split('.');
    return int.parse(parts.first) * 100 +
        (parts.length == 1 ? 0 : int.parse(parts[1].padRight(2, '0')));
  }

  @override
  bool operator ==(Object other) =>
      other is MarketplaceFilters &&
      category == other.category &&
      catalogGroup == other.catalogGroup &&
      catalogSubcategory == other.catalogSubcategory &&
      sport == other.sport &&
      minPriceCents == other.minPriceCents &&
      maxPriceCents == other.maxPriceCents &&
      minRating == other.minRating &&
      sort == other.sort;
  @override
  int get hashCode => Object.hash(
    category,
    catalogGroup,
    catalogSubcategory,
    sport,
    minPriceCents,
    maxPriceCents,
    minRating,
    sort,
  );
}
