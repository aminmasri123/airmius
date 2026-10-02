import 'package:flutter/material.dart';

import '../core/airmius_api_client.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';
import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import '../widgets/inventory_checkout_dialog.dart';
import 'club_inventory_qr_scanner_screen.dart';
import 'club_metadata_subject_screen.dart';

class ClubAssetInventoryCheckoutSuiteScreen extends StatefulWidget {
  const ClubAssetInventoryCheckoutSuiteScreen({
    super.key,
    this.initialClubId,
    this.scheduledCheckout = false,
  });
  final int? initialClubId;
  final bool scheduledCheckout;

  @override
  State<ClubAssetInventoryCheckoutSuiteScreen> createState() =>
      _ClubAssetInventoryCheckoutSuiteScreenState();
}

class _ClubAssetInventoryCheckoutSuiteScreenState
    extends State<ClubAssetInventoryCheckoutSuiteScreen> {
  List<Map<String, dynamic>> _clubs = const [];
  Map<String, dynamic>? _club;
  List<Map<String, dynamic>> _items = const [];
  List<Map<String, dynamic>> _loans = const [];
  List<Map<String, dynamic>> _maintenance = const [];
  String _tab = 'items';
  String? _error;
  bool _loading = true;
  bool _busy = false;
  bool _canManage = false;
  bool _canManageMetadata = false;

  String t(String key) => AirmiusScope.of(context).t(key);
  String tx(String key, [Map<String, Object?> values = const {}]) {
    var value = t(key);
    for (final entry in values.entries) {
      value = value.replaceAll('{${entry.key}}', '${entry.value ?? ''}');
    }
    return value;
  }

  AirmiusApiClient get _client {
    final services = AirmiusServicesScope.of(context);
    return services.clientForSession(services.authState.session);
  }

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _loadClubs());
  }

  Future<void> _loadClubs() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final rows = _maps((await _client.clubs(mine: true))['data']);
      final selected = rows.cast<Map<String, dynamic>?>().firstWhere(
        (row) => _int(row?['id']) == widget.initialClubId,
        orElse: () => rows.isEmpty ? null : rows.first,
      );
      if (!mounted) return;
      setState(() {
        _clubs = rows;
        _club = selected;
        _loading = false;
      });
      if (selected != null) await _loadInventory();
    } on AirmiusApiException catch (error) {
      if (mounted) {
        setState(() {
          _error = error.userMessage;
          _loading = false;
        });
      }
    } catch (_) {
      if (mounted) {
        setState(() {
          _error = t('inventory.loadFailed');
          _loading = false;
        });
      }
    }
  }

  Future<void> _loadInventory() async {
    final clubId = _int(_club?['id']);
    if (clubId == null || _busy) return;
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      final data = _map((await _client.clubInventory(clubId))['data']);
      if (!mounted) return;
      setState(() {
        _items = _maps(data['items']);
        _loans = _maps(data['loans']);
        _maintenance = _maps(data['maintenance']);
        _canManage = data['can_manage'] == true;
        _canManageMetadata = data['can_manage_metadata'] == true;
        _busy = false;
      });
    } on AirmiusApiException catch (error) {
      if (mounted) {
        setState(() {
          _error = error.userMessage;
          _busy = false;
        });
      }
    } catch (_) {
      if (mounted) {
        setState(() {
          _error = t('inventory.loadFailed');
          _busy = false;
        });
      }
    }
  }

  Future<void> _run(Future<Map<String, dynamic>> Function() action) async {
    if (_busy) return;
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      final response = await action();
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('${response['message'] ?? t('inventory.saved')}'),
        ),
      );
      setState(() => _busy = false);
      await _loadInventory();
    } on AirmiusApiException catch (error) {
      if (mounted) {
        setState(() {
          _error = error.userMessage;
          _busy = false;
        });
      }
    } catch (_) {
      if (mounted) {
        setState(() {
          _error = t('inventory.actionFailed');
          _busy = false;
        });
      }
    }
  }

  Future<void> _checkout(Map<String, dynamic> item) async {
    final clubId = _int(_club?['id']);
    final itemId = _int(item['id']);
    if (clubId == null || itemId == null) return;
    final payload = await showDialog<Map<String, dynamic>>(
      context: context,
      builder: (_) => InventoryCheckoutDialog(
        name: '${item['name']}',
        scheduled: widget.scheduledCheckout,
      ),
    );
    if (!mounted || payload == null) return;
    await _run(
      () => _client.checkoutClubInventoryItem(clubId, itemId, payload),
    );
  }

  Future<void> _loanAction(Map<String, dynamic> loan, String action) async {
    final clubId = _int(_club?['id']);
    final loanId = _int(loan['id']);
    if (clubId == null || loanId == null) return;
    await _run(
      () => switch (action) {
        'approve' => _client.approveClubInventoryLoan(clubId, loanId),
        'reject' => _client.rejectClubInventoryLoan(clubId, loanId),
        _ => _client.returnClubInventoryLoan(clubId, loanId),
      },
    );
  }

  Future<void> _createItem() async {
    final name = TextEditingController();
    final quantity = TextEditingController(text: '1');
    final accepted = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: Text(t('inventory.createItem')),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            TextFormField(
              controller: name,
              decoration: InputDecoration(labelText: t('inventory.name')),
            ),
            TextFormField(
              controller: quantity,
              keyboardType: TextInputType.number,
              decoration: InputDecoration(
                labelText: t('inventory.totalQuantity'),
              ),
            ),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext, false),
            child: Text(t('inventory.cancel')),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(dialogContext, true),
            child: Text(t('inventory.create')),
          ),
        ],
      ),
    );
    final clubId = _int(_club?['id']);
    final amount = int.tryParse(quantity.text.trim());
    if (accepted != true ||
        clubId == null ||
        name.text.trim().isEmpty ||
        amount == null) {
      return;
    }
    await _run(
      () => _client.createClubInventoryItem(clubId, {
        'name': name.text.trim(),
        'quantity_total': amount,
        'condition': 'good',
        'status': 'active',
        'requires_approval': false,
      }),
    );
  }

  Future<void> _createMaintenance(Map<String, dynamic> item) async {
    final title = TextEditingController();
    final accepted = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: Text(tx('inventory.reportMaintenance', {'name': item['name']})),
        content: TextFormField(
          controller: title,
          decoration: InputDecoration(labelText: t('inventory.problem')),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext, false),
            child: Text(t('inventory.cancel')),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(dialogContext, true),
            child: Text(t('inventory.report')),
          ),
        ],
      ),
    );
    final clubId = _int(_club?['id']);
    final itemId = _int(item['id']);
    if (accepted != true ||
        clubId == null ||
        itemId == null ||
        title.text.trim().isEmpty) {
      return;
    }
    await _run(
      () => _client.createClubInventoryMaintenance(clubId, itemId, {
        'title': title.text.trim(),
      }),
    );
  }

  Future<void> _completeMaintenance(Map<String, dynamic> record) async {
    final clubId = _int(_club?['id']);
    final recordId = _int(record['id']);
    if (clubId == null || recordId == null) return;
    await _run(
      () => _client.updateClubInventoryMaintenance(clubId, recordId, {
        'status': 'completed',
        'description': record['description'],
        'cost': record['cost'],
      }),
    );
  }

  Future<void> _scan() async {
    final token = await Navigator.of(context).push<String>(
      MaterialPageRoute(builder: (_) => const ClubInventoryQrScannerScreen()),
    );
    final clubId = _int(_club?['id']);
    if (!mounted || token == null || clubId == null) return;
    setState(() => _busy = true);
    try {
      final item = _map(
        (await _client.scanClubInventoryItem(clubId, token))['data'],
      );
      if (!mounted) return;
      setState(() => _busy = false);
      await showModalBottomSheet<void>(
        context: context,
        showDragHandle: true,
        builder: (sheetContext) => Padding(
          padding: const EdgeInsets.fromLTRB(20, 4, 20, 28),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text(
                '${item['name'] ?? t('inventory.fallbackItem')}',
                style: Theme.of(
                  context,
                ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w900),
              ),
              const SizedBox(height: 8),
              Text(
                tx('inventory.availableCount', {
                  'available': item['quantity_available'] ?? 0,
                  'total': item['quantity_total'] ?? 0,
                }),
              ),
              const SizedBox(height: 16),
              FilledButton.icon(
                onPressed: (_int(item['quantity_available']) ?? 0) > 0
                    ? () {
                        Navigator.pop(sheetContext);
                        _checkout(item);
                      }
                    : null,
                icon: const Icon(Icons.assignment_return_outlined),
                label: Text(t('inventory.checkout')),
              ),
            ],
          ),
        ),
      );
    } on AirmiusApiException catch (error) {
      if (mounted) {
        setState(() {
          _error = error.userMessage;
          _busy = false;
        });
      }
    } catch (_) {
      if (mounted) {
        setState(() {
          _error = t('inventory.loadFailed');
          _busy = false;
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final openLoans = _loans
        .where((row) => ['pending', 'active'].contains('${row['status']}'))
        .length;
    final openMaintenance = _maintenance
        .where((row) => '${row['status']}' != 'completed')
        .length;
    return Scaffold(
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      appBar: AppBar(
        title: Text(
          t('inventory.title'),
          style: const TextStyle(fontWeight: FontWeight.w900),
        ),
        actions: [
          IconButton(
            tooltip: t('inventory.scanQr'),
            onPressed: _club == null || _busy ? null : _scan,
            icon: const Icon(Icons.qr_code_scanner),
          ),
          if (_canManage)
            IconButton(
              tooltip: t('inventory.createItem'),
              onPressed: _busy ? null : _createItem,
              icon: const Icon(Icons.add_circle_outline),
            ),
        ],
      ),
      body: PageFrame(
        title: t('inventory.title'),
        subtitle: t('inventory.subtitle'),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            if (_clubs.length > 1) ...[
              DropdownButtonFormField<int>(
                initialValue: _int(_club?['id']),
                decoration: InputDecoration(labelText: t('inventory.club')),
                items: _clubs
                    .map(
                      (row) => DropdownMenuItem(
                        value: _int(row['id']),
                        child: Text('${row['name']}'),
                      ),
                    )
                    .toList(),
                onChanged: _busy
                    ? null
                    : (id) async {
                        setState(
                          () => _club = _clubs.firstWhere(
                            (row) => _int(row['id']) == id,
                          ),
                        );
                        await _loadInventory();
                      },
              ),
              const SizedBox(height: 12),
            ],
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: [
                ChoiceChip(
                  label: Text('${t('inventory.stock')} (${_items.length})'),
                  selected: _tab == 'items',
                  onSelected: (_) => setState(() => _tab = 'items'),
                ),
                ChoiceChip(
                  label: Text('${t('inventory.loans')} ($openLoans)'),
                  selected: _tab == 'loans',
                  onSelected: (_) => setState(() => _tab = 'loans'),
                ),
                ChoiceChip(
                  label: Text(
                    '${t('inventory.maintenance')} ($openMaintenance)',
                  ),
                  selected: _tab == 'maintenance',
                  onSelected: (_) => setState(() => _tab = 'maintenance'),
                ),
              ],
            ),
            const SizedBox(height: 14),
            if (_loading || _busy) const LinearProgressIndicator(),
            if (_error != null) ...[
              const SizedBox(height: 12),
              AirmiusPanel(
                borderColor: AirmiusColors.red.withValues(alpha: .5),
                child: Text(
                  _error!,
                  style: const TextStyle(
                    color: AirmiusColors.red,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ),
            ],
            if (!_loading) ...[
              const SizedBox(height: 12),
              if (_tab == 'items') _itemsView(),
              if (_tab == 'loans') _loansView(),
              if (_tab == 'maintenance') _maintenanceView(),
            ],
          ],
        ),
      ),
    );
  }

  Widget _itemsView() {
    if (_items.isEmpty) {
      return AirmiusPanel(child: Text(t('inventory.emptyItems')));
    }
    return Column(
      children: [
        for (final item in _items) ...[
          AirmiusPanel(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Row(
                  children: [
                    IconBadge(
                      icon: Icons.inventory_2_outlined,
                      color: (_int(item['quantity_available']) ?? 0) > 0
                          ? AirmiusColors.green
                          : AirmiusColors.red,
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            '${item['name']}',
                            style: TextStyle(
                              color: airmiusTextColor(context),
                              fontWeight: FontWeight.w900,
                              fontSize: 16,
                            ),
                          ),
                          const SizedBox(height: 4),
                          Text(
                            tx('inventory.availableCount', {
                              'available': item['quantity_available'],
                              'total': item['quantity_total'],
                            }),
                            style: TextStyle(color: airmiusMutedColor(context)),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 12),
                Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: [
                    FilledButton.tonalIcon(
                      onPressed:
                          (widget.scheduledCheckout ||
                                  (_int(item['quantity_available']) ?? 0) >
                                      0) &&
                              !_busy
                          ? () => _checkout(item)
                          : null,
                      icon: const Icon(Icons.assignment_return_outlined),
                      label: Text(t('inventory.checkout')),
                    ),
                    if (_canManage)
                      OutlinedButton.icon(
                        onPressed: _busy
                            ? null
                            : () => _createMaintenance(item),
                        icon: const Icon(Icons.build_outlined),
                        label: Text(t('inventory.maintenance')),
                      ),
                    if (_canManageMetadata &&
                        _int(_club?['id']) != null &&
                        _int(item['id']) != null)
                      ClubMetadataSubjectButton(
                        clubId: _int(_club?['id'])!,
                        subjectType: 'inventory_item',
                        subjectId: _int(item['id'])!,
                        subjectTitle: '${item['name']}',
                      ),
                  ],
                ),
              ],
            ),
          ),
          const SizedBox(height: 10),
        ],
      ],
    );
  }

  Widget _loansView() {
    String dateLabel(DateTime value) {
      final local = value.toLocal();
      final locale = MaterialLocalizations.of(context);
      return '${locale.formatCompactDate(local)} ${locale.formatTimeOfDay(TimeOfDay.fromDateTime(local))}';
    }

    if (_loans.isEmpty) {
      return AirmiusPanel(child: Text(t('inventory.emptyLoans')));
    }
    return Column(
      children: [
        for (final loan in _loans) ...[
          AirmiusPanel(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Text(
                  '${_map(loan['item'])['name'] ?? t('inventory.fallbackMaterial')}',
                  style: TextStyle(
                    color: airmiusTextColor(context),
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 5),
                Text(
                  '${_map(loan['borrower'])['name'] ?? ''} · ${loan['quantity']} ${t('inventory.pieces')} · ${t('inventory.loan.${loan['status']}')}',
                  style: TextStyle(color: airmiusMutedColor(context)),
                ),
                if (DateTime.tryParse('${loan['starts_at']}')
                    case final DateTime start)
                  Text(
                    '${t('membership.access.startsAt')}: ${dateLabel(start)}',
                  ),
                if (DateTime.tryParse('${loan['due_at']}')
                    case final DateTime end)
                  Text('${t('membership.access.endsAt')}: ${dateLabel(end)}'),
                if (loan['status'] == 'pending' && _canManage) ...[
                  const SizedBox(height: 10),
                  Wrap(
                    spacing: 8,
                    children: [
                      FilledButton(
                        onPressed: _busy
                            ? null
                            : () => _loanAction(loan, 'approve'),
                        child: Text(t('inventory.approve')),
                      ),
                      OutlinedButton(
                        onPressed: _busy
                            ? null
                            : () => _loanAction(loan, 'reject'),
                        child: Text(t('inventory.reject')),
                      ),
                    ],
                  ),
                ],
                if (loan['status'] == 'active') ...[
                  const SizedBox(height: 10),
                  OutlinedButton.icon(
                    onPressed: _busy ? null : () => _loanAction(loan, 'return'),
                    icon: const Icon(Icons.assignment_turned_in_outlined),
                    label: Text(t('inventory.recordReturn')),
                  ),
                ],
              ],
            ),
          ),
          const SizedBox(height: 10),
        ],
      ],
    );
  }

  Widget _maintenanceView() {
    if (_maintenance.isEmpty) {
      return AirmiusPanel(child: Text(t('inventory.emptyMaintenance')));
    }
    return Column(
      children: [
        for (final record in _maintenance) ...[
          AirmiusPanel(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Text(
                  '${record['title']}',
                  style: TextStyle(
                    color: airmiusTextColor(context),
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 5),
                Text(
                  '${_map(record['item'])['name'] ?? ''} · ${t('inventory.maintenance.${record['status']}')}',
                  style: TextStyle(color: airmiusMutedColor(context)),
                ),
                if (record['status'] != 'completed') ...[
                  const SizedBox(height: 10),
                  OutlinedButton.icon(
                    onPressed: _busy
                        ? null
                        : () => _completeMaintenance(record),
                    icon: const Icon(Icons.task_alt),
                    label: Text(t('inventory.completeMaintenance')),
                  ),
                ],
              ],
            ),
          ),
          const SizedBox(height: 10),
        ],
      ],
    );
  }
}

Map<String, dynamic> _map(dynamic value) => value is Map
    ? value.map((key, item) => MapEntry('$key', item))
    : <String, dynamic>{};
List<Map<String, dynamic>> _maps(dynamic value) => value is List
    ? value.whereType<Map>().map(_map).toList()
    : <Map<String, dynamic>>[];
int? _int(dynamic value) => value is int ? value : int.tryParse('$value');
