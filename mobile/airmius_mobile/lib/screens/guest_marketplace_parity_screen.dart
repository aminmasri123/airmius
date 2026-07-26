import 'package:flutter/material.dart';

import '../core/airmius_api_client.dart';
import '../core/airmius_api_models.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';
import '../widgets/airmius_widgets.dart';
import 'public_interest_screen.dart';

/// Guest-safe marketplace catalogue. Checkout, wishlist and orders stay in
/// the authenticated commerce centre; this page only reads published cards.
class GuestMarketplaceParityScreen extends StatefulWidget {
  const GuestMarketplaceParityScreen({super.key});

  @override
  State<GuestMarketplaceParityScreen> createState() =>
      _GuestMarketplaceParityScreenState();
}

class _GuestMarketplaceParityScreenState
    extends State<GuestMarketplaceParityScreen> {
  Future<List<JsonMap>>? _future;
  String _query = '';
  String _category = '';

  String t(String key) => AirmiusScope.of(context).t(key);

  AirmiusApiClient get _client {
    final services = AirmiusServicesScope.of(context);
    return services.clientForSession(services.authState.session);
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _future ??= _load();
  }

  Future<List<JsonMap>> _load() async {
    final response = await _client.publicMarketplace();
    final data = response['data'];
    if (data is! List) return const <JsonMap>[];
    return data
        .whereType<Map>()
        .map((item) => Map<String, dynamic>.from(item))
        .toList();
  }

  void _reload() => setState(() => _future = _load());

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text(
          t('guestMarket.title'),
          style: const TextStyle(fontWeight: FontWeight.w900),
        ),
        actions: [
          IconButton(
            tooltip: t('guestMarket.reload'),
            onPressed: _reload,
            icon: const Icon(Icons.refresh_outlined),
          ),
        ],
      ),
      body: FutureBuilder<List<JsonMap>>(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.connectionState == ConnectionState.waiting) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snapshot.hasError) {
            return PageFrame(
              title: t('guestMarket.title'),
              subtitle: t('guestMarket.subtitle'),
              child: AirmiusPanel(
                child: Column(
                  children: [
                    Text(
                      t('guestMarket.loadError'),
                      textAlign: TextAlign.center,
                    ),
                    const SizedBox(height: 12),
                    AirmiusButton(
                      label: t('guestMarket.retry'),
                      icon: Icons.refresh_outlined,
                      onPressed: _reload,
                    ),
                  ],
                ),
              ),
            );
          }

          final all = snapshot.data ?? const <JsonMap>[];
          final visible = all.where((item) {
            final haystack = [
              item['title'],
              item['description'],
              item['provider_name'],
              item['category'],
            ].map(_text).join(' ').toLowerCase();
            return (_query.trim().isEmpty ||
                    haystack.contains(_query.trim().toLowerCase())) &&
                (_category.isEmpty || item['category'] == _category);
          }).toList();
          final categories = all
              .map((item) => _text(item['category']))
              .where((value) => value.isNotEmpty)
              .toSet()
              .toList();

          return PageFrame(
            title: t('guestMarket.title'),
            subtitle: t('guestMarket.subtitle'),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                AirmiusPanel(
                  gradient: true,
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      Eyebrow(t('guestMarket.eyebrow')),
                      const SizedBox(height: 8),
                      Text(
                        t('guestMarket.intro'),
                        style: Theme.of(context).textTheme.titleMedium
                            ?.copyWith(fontWeight: FontWeight.w800),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 14),
                SearchBox(
                  hint: t('guestMarket.search'),
                  onChanged: (value) => setState(() => _query = value),
                ),
                if (categories.isNotEmpty) ...[
                  const SizedBox(height: 12),
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: [
                      _CategoryChip(
                        label: t('guestMarket.all'),
                        selected: _category.isEmpty,
                        onTap: () => setState(() => _category = ''),
                      ),
                      ...categories.map(
                        (category) => _CategoryChip(
                          label: category,
                          selected: _category == category,
                          onTap: () => setState(() => _category = category),
                        ),
                      ),
                    ],
                  ),
                ],
                const SizedBox(height: 14),
                if (visible.isEmpty)
                  AirmiusPanel(
                    child: Text(
                      all.isEmpty
                          ? t('guestMarket.empty')
                          : t('guestMarket.noMatch'),
                      textAlign: TextAlign.center,
                    ),
                  )
                else
                  ...visible.map(
                    (item) => Padding(
                      padding: const EdgeInsets.only(bottom: 12),
                      child: _OfferCard(
                        item: item,
                        onInterest: () => Navigator.of(context).push(
                          MaterialPageRoute<void>(
                            builder: (_) => PublicInterestScreen(
                              topic: _text(item['title']),
                              kind: 'marketplace_interest',
                              icon: Icons.storefront_outlined,
                            ),
                          ),
                        ),
                      ),
                    ),
                  ),
              ],
            ),
          );
        },
      ),
    );
  }

  String _text(Object? value) => value?.toString().trim() ?? '';
}

class _OfferCard extends StatelessWidget {
  const _OfferCard({required this.item, required this.onInterest});

  final JsonMap item;
  final VoidCallback onInterest;

  String _text(Object? value, [String fallback = '']) {
    final text = value?.toString().trim() ?? '';
    return text.isEmpty ? fallback : text;
  }

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final title = _text(item['title'], t('market.untitledProduct'));
    final provider = _text(item['provider_name'], t('market.provider'));
    final price = item['price_cents'] is num
        ? '${((item['price_cents'] as num) / 100).toStringAsFixed(2)} ${_text(item['currency'], 'EUR')}'
        : t('guestMarket.priceOnRequest');
    return AirmiusPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                width: 52,
                height: 52,
                decoration: BoxDecoration(
                  color: Theme.of(
                    context,
                  ).colorScheme.primary.withValues(alpha: 0.12),
                  borderRadius: BorderRadius.circular(15),
                ),
                child: Icon(
                  Icons.storefront_outlined,
                  color: Theme.of(context).colorScheme.primary,
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      title,
                      style: const TextStyle(fontWeight: FontWeight.w900),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      provider,
                      style: TextStyle(
                        color: Theme.of(context).colorScheme.primary,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ],
                ),
              ),
              StatusPill(price, color: Theme.of(context).colorScheme.secondary),
            ],
          ),
          if (_text(item['description']).isNotEmpty) ...[
            const SizedBox(height: 10),
            Text(_text(item['description'])),
          ],
          const SizedBox(height: 12),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              if (_text(item['badge']).isNotEmpty)
                StatusPill(_text(item['badge'])),
              if (_text(item['delivery_label']).isNotEmpty)
                StatusPill(_text(item['delivery_label'])),
              AirmiusButton(
                label: t('guestMarket.interest'),
                icon: Icons.send_outlined,
                secondary: true,
                onPressed: onInterest,
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _CategoryChip extends StatelessWidget {
  const _CategoryChip({
    required this.label,
    required this.selected,
    required this.onTap,
  });

  final String label;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return FilterChip(
      label: Text(label),
      selected: selected,
      onSelected: (_) => onTap(),
      showCheckmark: false,
    );
  }
}
