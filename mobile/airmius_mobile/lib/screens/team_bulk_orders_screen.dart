import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:intl/intl.dart';

import '../core/airmius_api_client.dart';
import '../core/airmius_api_models.dart';
import '../core/airmius_services_scope.dart';
import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

class TeamBulkOrdersScreen extends StatefulWidget {
  const TeamBulkOrdersScreen({super.key});

  @override
  State<TeamBulkOrdersScreen> createState() => _TeamBulkOrdersScreenState();
}

class _TeamBulkOrdersScreenState extends State<TeamBulkOrdersScreen> {
  Future<_BulkOrderBundle>? _future;
  bool _busy = false;

  AirmiusApiClient get _client {
    final services = AirmiusServicesScope.of(context);
    return services.clientForSession(services.authState.session);
  }

  String _copy(String de, String en) =>
      Localizations.localeOf(context).languageCode == 'de' ? de : en;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _future ??= _load();
  }

  Future<_BulkOrderBundle> _load() async {
    final responses = await Future.wait([
      _client.commerceTeamBulkOrders(),
      _client.clubs(mine: true),
      _client.commerceProducts(),
    ]);
    return _BulkOrderBundle(
      orders: _maps(responses[0]['data']),
      clubs: _maps(responses[1]['data']),
      products: _maps(
        responses[2]['data'],
      ).where((item) => _text(item['product_type']) == 'teamwear').toList(),
    );
  }

  void _reload() => setState(() => _future = _load());

  Future<void> _create(_BulkOrderBundle bundle) async {
    final created = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      backgroundColor: airmiusSurfaceColor(context),
      builder: (_) => _CreateBulkOrderSheet(
        client: _client,
        clubs: bundle.clubs,
        products: bundle.products,
      ),
    );
    if (created == true && mounted) _reload();
  }

  Future<void> _order(JsonMap order) async {
    final saved = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      backgroundColor: airmiusSurfaceColor(context),
      builder: (_) => _BulkOrderItemSheet(client: _client, order: order),
    );
    if (saved == true && mounted) _reload();
  }

  Future<void> _export(JsonMap order) async {
    if (_busy) return;
    setState(() => _busy = true);
    try {
      final response = await _client.commerceTeamBulkOrderSupplierExport(
        _int(order['id']),
      );
      final content = const JsonEncoder.withIndent(
        '  ',
      ).convert(response['data']);
      await Clipboard.setData(ClipboardData(text: content));
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            _copy(
              'Lieferantenexport wurde in die Zwischenablage kopiert.',
              'Supplier export copied to the clipboard.',
            ),
          ),
        ),
      );
    } on AirmiusApiException catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(error.userMessage)));
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text(_copy('Teamsammelbestellungen', 'Team bulk orders')),
        actions: [
          IconButton(
            tooltip: _copy('Aktualisieren', 'Refresh'),
            onPressed: _busy ? null : _reload,
            icon: const Icon(Icons.refresh),
          ),
        ],
      ),
      body: FutureBuilder<_BulkOrderBundle>(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.connectionState == ConnectionState.waiting) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snapshot.hasError) {
            return Center(
              child: Padding(
                padding: const EdgeInsets.all(24),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    const Icon(Icons.error_outline, size: 42),
                    const SizedBox(height: 12),
                    Text(
                      snapshot.error is AirmiusApiException
                          ? (snapshot.error! as AirmiusApiException).userMessage
                          : _copy(
                              'Sammelbestellungen konnten nicht geladen werden.',
                              'Bulk orders could not be loaded.',
                            ),
                      textAlign: TextAlign.center,
                    ),
                    const SizedBox(height: 16),
                    FilledButton.icon(
                      onPressed: _reload,
                      icon: const Icon(Icons.refresh),
                      label: Text(_copy('Erneut versuchen', 'Try again')),
                    ),
                  ],
                ),
              ),
            );
          }
          final bundle = snapshot.data ?? const _BulkOrderBundle();
          return ListView(
            padding: const EdgeInsets.fromLTRB(16, 16, 16, 110),
            children: [
              AirmiusPanel(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Icon(
                          Icons.groups_2_outlined,
                          color: airmiusAccentColor(context),
                        ),
                        const SizedBox(width: 10),
                        Expanded(
                          child: Text(
                            _copy(
                              'Größen und Mengen gemeinsam erfassen',
                              'Collect sizes and quantities together',
                            ),
                            style: TextStyle(
                              color: airmiusTextColor(context),
                              fontSize: 19,
                              fontWeight: FontWeight.w900,
                            ),
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 8),
                    Text(
                      _copy(
                        'Mitglieder tragen ihre Position bis zur Frist ein. Preise werden beim Anlegen festgeschrieben; nur Berechtigte erhalten den Lieferantenexport.',
                        'Members submit their line before the deadline. Prices are frozen when created; only authorized managers can export supplier data.',
                      ),
                      style: TextStyle(
                        color: airmiusMutedColor(context),
                        height: 1.4,
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 16),
              if (bundle.orders.isEmpty)
                AirmiusPanel(
                  child: Text(
                    _copy(
                      'Noch keine Sammelbestellung für deine Vereine oder Teams.',
                      'No bulk order for your clubs or teams yet.',
                    ),
                  ),
                )
              else
                for (final order in bundle.orders) ...[
                  _BulkOrderCard(
                    order: order,
                    busy: _busy,
                    onOrder: () => _order(order),
                    onExport: () => _export(order),
                  ),
                  const SizedBox(height: 12),
                ],
            ],
          );
        },
      ),
      floatingActionButton: FutureBuilder<_BulkOrderBundle>(
        future: _future,
        builder: (context, snapshot) {
          final bundle = snapshot.data;
          if (bundle == null || bundle.clubs.isEmpty) {
            return const SizedBox.shrink();
          }
          return FloatingActionButton.extended(
            onPressed: _busy ? null : () => _create(bundle),
            icon: const Icon(Icons.add_shopping_cart),
            label: Text(_copy('Sammelauftrag', 'Bulk order')),
          );
        },
      ),
    );
  }
}

class _BulkOrderCard extends StatelessWidget {
  const _BulkOrderCard({
    required this.order,
    required this.busy,
    required this.onOrder,
    required this.onExport,
  });

  final JsonMap order;
  final bool busy;
  final VoidCallback onOrder;
  final VoidCallback onExport;

  @override
  Widget build(BuildContext context) {
    final viewer = _map(order['viewer']);
    final item = _map(viewer['item']);
    final deadline = DateTime.tryParse(_text(order['order_deadline_at']));
    final currency = _text(order['currency'], fallback: 'EUR');
    final formatter = NumberFormat.simpleCurrency(name: currency);
    final deadlineOpen = deadline == null || deadline.isAfter(DateTime.now());
    return AirmiusPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Icon(
                Icons.inventory_2_outlined,
                color: airmiusAccentColor(context),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      _text(order['title']),
                      style: TextStyle(
                        color: airmiusTextColor(context),
                        fontSize: 18,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    Text(
                      _text(_map(order['team'])['name'], fallback: 'Team'),
                      style: TextStyle(color: airmiusMutedColor(context)),
                    ),
                  ],
                ),
              ),
              Chip(
                label: Text(
                  deadlineOpen
                      ? (Localizations.localeOf(context).languageCode == 'de'
                            ? 'Offen'
                            : 'Open')
                      : (Localizations.localeOf(context).languageCode == 'de'
                            ? 'Beendet'
                            : 'Closed'),
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          Text(
            '${formatter.format(_int(order['payable_unit_price_cents']) / 100)} · ${deadline == null ? '-' : DateFormat('dd.MM.yyyy, HH:mm').format(deadline.toLocal())}',
            style: TextStyle(
              color: airmiusTextColor(context),
              fontWeight: FontWeight.w700,
            ),
          ),
          if (item.isNotEmpty) ...[
            const SizedBox(height: 8),
            Text(
              '${Localizations.localeOf(context).languageCode == 'de' ? 'Deine Position' : 'Your line'}: ${_int(item['quantity'])}',
              style: const TextStyle(fontWeight: FontWeight.w800),
            ),
          ],
          const SizedBox(height: 12),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              if (_bool(viewer['is_team_member']))
                FilledButton.icon(
                  onPressed: busy || !deadlineOpen ? null : onOrder,
                  icon: const Icon(Icons.edit_outlined),
                  label: Text(
                    item.isEmpty
                        ? (Localizations.localeOf(context).languageCode == 'de'
                              ? 'Position erfassen'
                              : 'Add line')
                        : (Localizations.localeOf(context).languageCode == 'de'
                              ? 'Position ändern'
                              : 'Edit line'),
                  ),
                ),
              if (_bool(viewer['can_manage']))
                OutlinedButton.icon(
                  onPressed: busy ? null : onExport,
                  icon: const Icon(Icons.download_outlined),
                  label: Text(
                    Localizations.localeOf(context).languageCode == 'de'
                        ? 'Lieferantenexport'
                        : 'Supplier export',
                  ),
                ),
            ],
          ),
        ],
      ),
    );
  }
}

class _CreateBulkOrderSheet extends StatefulWidget {
  const _CreateBulkOrderSheet({
    required this.client,
    required this.clubs,
    required this.products,
  });

  final AirmiusApiClient client;
  final List<JsonMap> clubs;
  final List<JsonMap> products;

  @override
  State<_CreateBulkOrderSheet> createState() => _CreateBulkOrderSheetState();
}

class _CreateBulkOrderSheetState extends State<_CreateBulkOrderSheet> {
  final _title = TextEditingController();
  int? _clubId;
  int? _teamId;
  int? _productId;
  DateTime _deadline = DateTime.now().add(const Duration(days: 7));
  List<JsonMap> _teams = const [];
  bool _busy = false;
  String? _error;

  String _copy(String de, String en) =>
      Localizations.localeOf(context).languageCode == 'de' ? de : en;

  @override
  void dispose() {
    _title.dispose();
    super.dispose();
  }

  Future<void> _selectClub(int? value) async {
    setState(() {
      _clubId = value;
      _teamId = null;
      _productId = null;
      _teams = const [];
      _error = null;
    });
    if (value == null) return;
    try {
      final response = await widget.client.teams(clubId: value);
      if (mounted) setState(() => _teams = _maps(response['data']));
    } on AirmiusApiException catch (error) {
      if (mounted) setState(() => _error = error.userMessage);
    }
  }

  Future<void> _pickDeadline() async {
    final date = await showDatePicker(
      context: context,
      initialDate: _deadline,
      firstDate: DateTime.now(),
      lastDate: DateTime.now().add(const Duration(days: 730)),
    );
    if (date == null || !mounted) return;
    final time = await showTimePicker(
      context: context,
      initialTime: TimeOfDay.fromDateTime(_deadline),
    );
    if (time == null) return;
    setState(() {
      _deadline = DateTime(
        date.year,
        date.month,
        date.day,
        time.hour,
        time.minute,
      );
    });
  }

  Future<void> _submit() async {
    if (_clubId == null || _teamId == null || _productId == null) {
      setState(
        () => _error = _copy(
          'Bitte alle Pflichtfelder wählen.',
          'Please select all required fields.',
        ),
      );
      return;
    }
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      await widget.client.createCommerceTeamBulkOrder({
        'club_id': _clubId,
        'team_id': _teamId,
        'marketplace_product_id': _productId,
        if (_title.text.trim().isNotEmpty) 'title': _title.text.trim(),
        'order_deadline_at': _deadline.toIso8601String(),
      });
      if (mounted) Navigator.pop(context, true);
    } on AirmiusApiException catch (error) {
      if (mounted) setState(() => _error = error.userMessage);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final products = widget.products
        .where((item) => _int(item['club_id']) == _clubId)
        .toList();
    return DraggableScrollableSheet(
      expand: false,
      initialChildSize: .9,
      minChildSize: .65,
      maxChildSize: .98,
      builder: (context, controller) => ListView(
        controller: controller,
        padding: const EdgeInsets.fromLTRB(18, 10, 18, 30),
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  _copy('Sammelauftrag anlegen', 'Create bulk order'),
                  style: const TextStyle(
                    fontSize: 22,
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ),
              IconButton(
                onPressed: () => Navigator.pop(context),
                icon: const Icon(Icons.close),
              ),
            ],
          ),
          const SizedBox(height: 14),
          DropdownButtonFormField<int>(
            initialValue: _clubId,
            decoration: InputDecoration(labelText: _copy('Verein', 'Club')),
            items: widget.clubs
                .map(
                  (club) => DropdownMenuItem(
                    value: _int(club['id']),
                    child: Text(_text(club['name'])),
                  ),
                )
                .toList(),
            onChanged: _busy ? null : _selectClub,
          ),
          const SizedBox(height: 12),
          DropdownButtonFormField<int>(
            initialValue: _teamId,
            decoration: InputDecoration(labelText: _copy('Team', 'Team')),
            items: _teams
                .map(
                  (team) => DropdownMenuItem(
                    value: _int(team['id']),
                    child: Text(_text(team['name'])),
                  ),
                )
                .toList(),
            onChanged: _busy
                ? null
                : (value) => setState(() => _teamId = value),
          ),
          const SizedBox(height: 12),
          DropdownButtonFormField<int>(
            initialValue: _productId,
            decoration: InputDecoration(
              labelText: _copy('Teamwear-Produkt', 'Teamwear product'),
            ),
            items: products
                .map(
                  (product) => DropdownMenuItem(
                    value: _int(product['id']),
                    child: Text(_text(product['title'])),
                  ),
                )
                .toList(),
            onChanged: _busy
                ? null
                : (value) => setState(() => _productId = value),
          ),
          if (_clubId != null && products.isEmpty) ...[
            const SizedBox(height: 8),
            Text(
              _copy(
                'Für diesen Verein ist noch kein freigegebenes Teamwear-Produkt verfügbar.',
                'No approved teamwear product is available for this club.',
              ),
              style: TextStyle(color: Theme.of(context).colorScheme.error),
            ),
          ],
          const SizedBox(height: 12),
          TextField(
            controller: _title,
            decoration: InputDecoration(
              labelText: _copy('Titel (optional)', 'Title (optional)'),
            ),
          ),
          const SizedBox(height: 12),
          ListTile(
            contentPadding: const EdgeInsets.symmetric(horizontal: 12),
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(8),
              side: BorderSide(color: airmiusBorderColor(context)),
            ),
            leading: const Icon(Icons.event_outlined),
            title: Text(_copy('Bestellfrist', 'Order deadline')),
            subtitle: Text(DateFormat('dd.MM.yyyy, HH:mm').format(_deadline)),
            trailing: const Icon(Icons.chevron_right),
            onTap: _busy ? null : _pickDeadline,
          ),
          if (_error != null) ...[
            const SizedBox(height: 12),
            Text(
              _error!,
              style: TextStyle(
                color: Theme.of(context).colorScheme.error,
                fontWeight: FontWeight.w700,
              ),
            ),
          ],
          const SizedBox(height: 18),
          FilledButton.icon(
            onPressed: _busy ? null : _submit,
            icon: _busy
                ? const SizedBox.square(
                    dimension: 18,
                    child: CircularProgressIndicator(strokeWidth: 2),
                  )
                : const Icon(Icons.check),
            label: Text(_copy('Sammelauftrag speichern', 'Save bulk order')),
          ),
        ],
      ),
    );
  }
}

class _BulkOrderItemSheet extends StatefulWidget {
  const _BulkOrderItemSheet({required this.client, required this.order});

  final AirmiusApiClient client;
  final JsonMap order;

  @override
  State<_BulkOrderItemSheet> createState() => _BulkOrderItemSheetState();
}

class _BulkOrderItemSheetState extends State<_BulkOrderItemSheet> {
  late final TextEditingController _quantity;
  late final TextEditingController _size;
  late final TextEditingController _name;
  late final TextEditingController _number;
  bool _busy = false;
  String? _error;

  String _copy(String de, String en) =>
      Localizations.localeOf(context).languageCode == 'de' ? de : en;

  @override
  void initState() {
    super.initState();
    final item = _map(_map(widget.order['viewer'])['item']);
    final personalization = _map(item['personalization']);
    _quantity = TextEditingController(
      text: '${_int(item['quantity']).clamp(1, 99)}',
    );
    _size = TextEditingController(text: _text(personalization['size']));
    _name = TextEditingController(text: _text(personalization['name']));
    _number = TextEditingController(text: _text(personalization['number']));
  }

  @override
  void dispose() {
    _quantity.dispose();
    _size.dispose();
    _name.dispose();
    _number.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    final quantity = int.tryParse(_quantity.text.trim()) ?? 0;
    if (quantity < 1 || quantity > 99) {
      setState(
        () => _error = _copy(
          'Menge muss zwischen 1 und 99 liegen.',
          'Quantity must be between 1 and 99.',
        ),
      );
      return;
    }
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      await widget.client.saveCommerceTeamBulkOrderItem(
        _int(widget.order['id']),
        quantity: quantity,
        personalization: {
          if (_size.text.trim().isNotEmpty) 'size': _size.text.trim(),
          if (_name.text.trim().isNotEmpty) 'name': _name.text.trim(),
          if (_number.text.trim().isNotEmpty) 'number': _number.text.trim(),
        },
      );
      if (mounted) Navigator.pop(context, true);
    } on AirmiusApiException catch (error) {
      if (mounted) setState(() => _error = error.userMessage);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: EdgeInsets.fromLTRB(
        18,
        18,
        18,
        MediaQuery.viewInsetsOf(context).bottom + 24,
      ),
      child: SingleChildScrollView(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text(
              _copy('Deine Bestellposition', 'Your order line'),
              style: const TextStyle(fontSize: 22, fontWeight: FontWeight.w900),
            ),
            const SizedBox(height: 14),
            TextField(
              controller: _quantity,
              keyboardType: TextInputType.number,
              decoration: InputDecoration(
                labelText: _copy('Menge', 'Quantity'),
              ),
            ),
            const SizedBox(height: 10),
            TextField(
              controller: _size,
              decoration: InputDecoration(
                labelText: _copy('Größe (optional)', 'Size (optional)'),
              ),
            ),
            const SizedBox(height: 10),
            TextField(
              controller: _name,
              decoration: InputDecoration(
                labelText: _copy(
                  'Name auf Produkt (optional)',
                  'Name on product (optional)',
                ),
              ),
            ),
            const SizedBox(height: 10),
            TextField(
              controller: _number,
              keyboardType: TextInputType.number,
              decoration: InputDecoration(
                labelText: _copy('Nummer (optional)', 'Number (optional)'),
              ),
            ),
            if (_error != null) ...[
              const SizedBox(height: 10),
              Text(
                _error!,
                style: TextStyle(
                  color: Theme.of(context).colorScheme.error,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ],
            const SizedBox(height: 16),
            FilledButton.icon(
              onPressed: _busy ? null : _submit,
              icon: const Icon(Icons.save_outlined),
              label: Text(_copy('Position speichern', 'Save line')),
            ),
          ],
        ),
      ),
    );
  }
}

class _BulkOrderBundle {
  const _BulkOrderBundle({
    this.orders = const [],
    this.clubs = const [],
    this.products = const [],
  });

  final List<JsonMap> orders;
  final List<JsonMap> clubs;
  final List<JsonMap> products;
}

JsonMap _map(Object? value) =>
    value is Map ? Map<String, dynamic>.from(value) : <String, dynamic>{};
List<JsonMap> _maps(Object? value) => value is List
    ? value
          .whereType<Map>()
          .map((item) => Map<String, dynamic>.from(item))
          .toList()
    : <JsonMap>[];
String _text(Object? value, {String fallback = ''}) {
  final text = value?.toString().trim() ?? '';
  return text.isEmpty ? fallback : text;
}

int _int(Object? value) =>
    value is num ? value.toInt() : int.tryParse(value?.toString() ?? '') ?? 0;
bool _bool(Object? value) => value == true || value == 1 || value == '1';
