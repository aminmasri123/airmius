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
    this.initialTab = 'items',
    this.scheduledCheckout = false,
  });
  final int? initialClubId;
  final String initialTab;
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
    _tab = widget.initialTab;
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

  Future<void> _editRequest(Map<String, dynamic> item) async {
    final clubId = _int(_club?['id']);
    final request = _map(item['pending_request']);
    final loanId = _int(request['id']);
    if (clubId == null || loanId == null) return;
    final payload = await showDialog<Map<String, dynamic>>(
      context: context,
      builder: (_) => InventoryCheckoutDialog(
        name: '${item['name']}',
        scheduled: widget.scheduledCheckout,
        initialRequest: request,
      ),
    );
    if (!mounted || payload == null) return;
    await _run(
      () => _client.updateClubInventoryLoanRequest(clubId, loanId, payload),
    );
  }

  Future<void> _cancelRequest(Map<String, dynamic> item) async {
    final clubId = _int(_club?['id']);
    final loanId = _int(_map(item['pending_request'])['id']);
    if (clubId == null || loanId == null) return;
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(t('inventory.withdrawRequest')),
        content: Text(t('inventory.withdrawRequestConfirm')),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: Text(t('inventory.cancel')),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, true),
            child: Text(t('inventory.withdraw')),
          ),
        ],
      ),
    );
    if (confirmed != true || !mounted) return;
    await _run(() => _client.cancelClubInventoryLoanRequest(clubId, loanId));
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
    final clubId = _int(_club?['id']);
    if (clubId == null) return;
    final changed = await Navigator.push<bool>(
      context,
      MaterialPageRoute(
        builder: (_) => _InventoryItemEditorScreen(clubId: clubId),
      ),
    );
    if (changed == true && mounted) await _loadInventory();
  }

  Future<void> _editItem(Map<String, dynamic> item) async {
    final clubId = _int(_club?['id']);
    if (clubId == null) return;
    final changed = await Navigator.push<bool>(
      context,
      MaterialPageRoute(
        builder: (_) => _InventoryItemEditorScreen(clubId: clubId, item: item),
      ),
    );
    if (changed == true && mounted) await _loadInventory();
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
                onPressed:
                    item['is_loanable'] != false &&
                        item['can_checkout'] != false &&
                        (_int(item['quantity_available']) ?? 0) > 0
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

  Future<void> _qrAction(Map<String, dynamic> item, String action) async {
    final clubId = _int(_club?['id']);
    final itemId = _int(item['id']);
    if (clubId == null || itemId == null || _busy) return;
    final confirm = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: Text(
          action == 'reissue'
              ? t('inventory.qrReissue')
              : t('inventory.qrRevoke'),
        ),
        content: Text(
          action == 'reissue'
              ? t('inventory.qrReissueConfirm')
              : t('inventory.qrRevokeConfirm'),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext, false),
            child: Text(t('inventory.cancel')),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(dialogContext, true),
            child: Text(
              action == 'reissue'
                  ? t('inventory.qrReissue')
                  : t('inventory.qrRevoke'),
            ),
          ),
        ],
      ),
    );
    if (confirm != true || !mounted) return;
    await _run(
      () => action == 'reissue'
          ? _client.reissueClubInventoryQr(clubId, itemId)
          : _client.revokeClubInventoryQr(clubId, itemId),
    );
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
            ] else if (_club != null) ...[
              Text(
                '${t('inventory.club')}: ${_club!['name']}',
                style: TextStyle(color: airmiusMutedColor(context)),
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
                          const SizedBox(height: 4),
                          Text(
                            tx('inventory.damageReportsYear', {
                              'year': DateTime.now().year,
                              'count': item['damage_reports_this_year'] ?? 0,
                            }),
                            style: TextStyle(
                              color: airmiusMutedColor(context),
                              fontSize: 12,
                            ),
                          ),
                          if ('${item['location'] ?? ''}'.trim().isNotEmpty ||
                              '${item['size'] ?? ''}'.trim().isNotEmpty ||
                              '${item['dimensions'] ?? ''}'
                                  .trim()
                                  .isNotEmpty) ...[
                            const SizedBox(height: 5),
                            Text(
                              [
                                if ('${item['location'] ?? ''}'
                                    .trim()
                                    .isNotEmpty)
                                  '${t('inventory.location')}: ${item['location']}',
                                if ('${item['size'] ?? ''}'.trim().isNotEmpty)
                                  '${t('inventory.size')}: ${item['size']}',
                                if ('${item['dimensions'] ?? ''}'
                                    .trim()
                                    .isNotEmpty)
                                  '${t('inventory.dimensions')}: ${item['dimensions']}',
                              ].join(' · '),
                              style: TextStyle(
                                color: airmiusMutedColor(context),
                                fontSize: 12,
                              ),
                            ),
                          ],
                        ],
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 12),
                if (item['is_loanable'] == false) ...[
                  Text(
                    t('inventory.inventoryOnly'),
                    style: TextStyle(color: airmiusMutedColor(context)),
                  ),
                  const SizedBox(height: 8),
                ] else if (item['can_checkout'] == false) ...[
                  Text(
                    t(switch (item['checkout_unavailable_reason']) {
                      'membership_inactive' =>
                        'inventory.checkoutUnavailableMembership',
                      'permission_denied' =>
                        'inventory.checkoutUnavailablePermission',
                      'item_inactive' => 'inventory.checkoutUnavailableItem',
                      _ => 'inventory.checkoutUnavailable',
                    }),
                    style: TextStyle(color: airmiusMutedColor(context)),
                  ),
                  const SizedBox(height: 8),
                ],
                Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: [
                    if (item['pending_request'] == null)
                      FilledButton.tonalIcon(
                        onPressed:
                            item['is_loanable'] != false &&
                                item['can_checkout'] != false &&
                                (widget.scheduledCheckout ||
                                    (_int(item['quantity_available']) ?? 0) >
                                        0) &&
                                !_busy
                            ? () => _checkout(item)
                            : null,
                        icon: const Icon(Icons.assignment_return_outlined),
                        label: Text(t('inventory.checkout')),
                      ),
                    if (item['pending_request'] != null) ...[
                      FilledButton.tonalIcon(
                        onPressed: _busy ? null : () => _editRequest(item),
                        icon: const Icon(Icons.schedule_send_outlined),
                        label: Text(t('inventory.requestSent')),
                      ),
                      OutlinedButton.icon(
                        onPressed: _busy ? null : () => _cancelRequest(item),
                        icon: const Icon(Icons.close),
                        label: Text(t('inventory.withdraw')),
                      ),
                    ],
                    if (item['can_edit'] == true)
                      OutlinedButton.icon(
                        onPressed: _busy ? null : () => _editItem(item),
                        icon: const Icon(Icons.edit_outlined),
                        label: Text(t('inventory.editItem')),
                      ),
                    if (_canManage)
                      OutlinedButton.icon(
                        onPressed: _busy
                            ? null
                            : () => _createMaintenance(item),
                        icon: const Icon(Icons.build_outlined),
                        label: Text(t('inventory.maintenance')),
                      ),
                    if (_canManage)
                      OutlinedButton.icon(
                        onPressed: _busy
                            ? null
                            : () => _qrAction(item, 'reissue'),
                        icon: const Icon(Icons.qr_code_2_outlined),
                        label: Text(t('inventory.qrReissue')),
                      ),
                    if (_canManage && item['qr_revoked_at'] == null)
                      OutlinedButton.icon(
                        onPressed: _busy
                            ? null
                            : () => _qrAction(item, 'revoke'),
                        icon: const Icon(Icons.block_outlined),
                        label: Text(t('inventory.qrRevoke')),
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

class _InventoryItemEditorScreen extends StatefulWidget {
  const _InventoryItemEditorScreen({required this.clubId, this.item});

  final int clubId;
  final Map<String, dynamic>? item;

  @override
  State<_InventoryItemEditorScreen> createState() =>
      _InventoryItemEditorScreenState();
}

class _InventoryItemEditorScreenState
    extends State<_InventoryItemEditorScreen> {
  final _formKey = GlobalKey<FormState>();
  late final TextEditingController _name;
  late final TextEditingController _quantity;
  late final TextEditingController _category;
  late final TextEditingController _size;
  late final TextEditingController _dimensions;
  late final TextEditingController _location;
  late final TextEditingController _sku;
  late final TextEditingController _description;
  late String _condition;
  late bool _requiresApproval;
  late bool _isLoanable;
  bool _saving = false;

  bool get _editing => widget.item != null;
  String t(String key) => AirmiusScope.of(context).t(key);

  AirmiusApiClient get _client {
    final services = AirmiusServicesScope.of(context);
    return services.clientForSession(services.authState.session);
  }

  @override
  void initState() {
    super.initState();
    final item = widget.item ?? const <String, dynamic>{};
    _name = TextEditingController(text: '${item['name'] ?? ''}');
    _quantity = TextEditingController(text: '${item['quantity_total'] ?? 1}');
    _category = TextEditingController(text: '${item['category'] ?? ''}');
    _size = TextEditingController(text: '${item['size'] ?? ''}');
    _dimensions = TextEditingController(text: '${item['dimensions'] ?? ''}');
    _location = TextEditingController(text: '${item['location'] ?? ''}');
    _sku = TextEditingController(text: '${item['sku'] ?? ''}');
    _description = TextEditingController(text: '${item['description'] ?? ''}');
    _condition = '${item['condition'] ?? 'good'}';
    _requiresApproval = item['requires_approval'] == true;
    _isLoanable = item['is_loanable'] != false;
  }

  @override
  void dispose() {
    for (final controller in [
      _name,
      _quantity,
      _category,
      _size,
      _dimensions,
      _location,
      _sku,
      _description,
    ]) {
      controller.dispose();
    }
    super.dispose();
  }

  String? _required(String? value) =>
      value == null || value.trim().isEmpty ? t('inventory.required') : null;

  String? _validQuantity(String? value) {
    final amount = int.tryParse(value?.trim() ?? '');
    return amount == null || amount < 1 ? t('inventory.quantityInvalid') : null;
  }

  String? _optional(TextEditingController controller) {
    final value = controller.text.trim();
    return value.isEmpty ? null : value;
  }

  Future<void> _save() async {
    if (!_formKey.currentState!.validate() || _saving) return;
    setState(() => _saving = true);
    final payload = <String, dynamic>{
      'name': _name.text.trim(),
      'quantity_total': int.parse(_quantity.text.trim()),
      'category': _optional(_category),
      'size': _optional(_size),
      'dimensions': _optional(_dimensions),
      'location': _optional(_location),
      'sku': _optional(_sku),
      'description': _optional(_description),
      'condition': _condition,
      'status': '${widget.item?['status'] ?? 'active'}',
      'requires_approval': _requiresApproval,
      'is_loanable': _isLoanable,
    };
    try {
      if (_editing) {
        await _client.updateClubInventoryItem(
          widget.clubId,
          _int(widget.item!['id'])!,
          payload,
        );
      } else {
        await _client.createClubInventoryItem(widget.clubId, payload);
      }
      if (mounted) Navigator.pop(context, true);
    } on AirmiusApiException catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(error.userMessage)));
        setState(() => _saving = false);
      }
    } catch (_) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(t('inventory.actionFailed'))));
        setState(() => _saving = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text(
          _editing ? t('inventory.editItem') : t('inventory.createItem'),
        ),
      ),
      body: SafeArea(
        child: Form(
          key: _formKey,
          child: ListView(
            padding: const EdgeInsets.fromLTRB(18, 20, 18, 120),
            children: [
              Text(
                t('inventory.basicData'),
                style: Theme.of(context).textTheme.titleLarge,
              ),
              const SizedBox(height: 12),
              TextFormField(
                controller: _name,
                textInputAction: TextInputAction.next,
                validator: _required,
                decoration: InputDecoration(
                  labelText: t('inventory.name'),
                  prefixIcon: const Icon(Icons.inventory_2_outlined),
                ),
              ),
              const SizedBox(height: 12),
              TextFormField(
                controller: _quantity,
                keyboardType: TextInputType.number,
                textInputAction: TextInputAction.next,
                validator: _validQuantity,
                decoration: InputDecoration(
                  labelText: t('inventory.totalQuantity'),
                  prefixIcon: const Icon(Icons.numbers_outlined),
                ),
              ),
              const SizedBox(height: 12),
              DropdownButtonFormField<String>(
                initialValue: _condition,
                decoration: InputDecoration(
                  labelText: t('inventory.condition'),
                  prefixIcon: const Icon(Icons.health_and_safety_outlined),
                ),
                items: ['new', 'good', 'worn', 'damaged']
                    .map(
                      (value) => DropdownMenuItem(
                        value: value,
                        child: Text(t('inventory.condition.$value')),
                      ),
                    )
                    .toList(),
                onChanged: (value) {
                  if (value != null) setState(() => _condition = value);
                },
              ),
              const SizedBox(height: 28),
              Text(
                t('inventory.optionalDetails'),
                style: Theme.of(context).textTheme.titleLarge,
              ),
              const SizedBox(height: 6),
              Text(
                t('inventory.optionalDetailsHint'),
                style: TextStyle(color: airmiusMutedColor(context)),
              ),
              const SizedBox(height: 12),
              _field(_category, 'inventory.category', Icons.category_outlined),
              _field(_size, 'inventory.size', Icons.straighten_outlined),
              _field(
                _dimensions,
                'inventory.dimensions',
                Icons.aspect_ratio_outlined,
              ),
              _field(_location, 'inventory.location', Icons.place_outlined),
              _field(_sku, 'inventory.sku', Icons.tag_outlined),
              TextFormField(
                controller: _description,
                minLines: 3,
                maxLines: 6,
                decoration: InputDecoration(
                  labelText: t('inventory.description'),
                  alignLabelWithHint: true,
                  prefixIcon: const Padding(
                    padding: EdgeInsets.only(bottom: 64),
                    child: Icon(Icons.notes_outlined),
                  ),
                ),
              ),
              const SizedBox(height: 14),
              SwitchListTile.adaptive(
                contentPadding: EdgeInsets.zero,
                title: Text(t('inventory.isLoanable')),
                subtitle: Text(t('inventory.isLoanableHint')),
                value: _isLoanable,
                onChanged: (value) => setState(() => _isLoanable = value),
              ),
              if (_isLoanable)
                SwitchListTile.adaptive(
                  contentPadding: EdgeInsets.zero,
                  title: Text(t('inventory.requiresApproval')),
                  subtitle: Text(t('inventory.requiresApprovalHint')),
                  value: _requiresApproval,
                  onChanged: (value) =>
                      setState(() => _requiresApproval = value),
                ),
            ],
          ),
        ),
      ),
      bottomNavigationBar: SafeArea(
        minimum: const EdgeInsets.fromLTRB(18, 10, 18, 16),
        child: Row(
          children: [
            Expanded(
              child: OutlinedButton(
                onPressed: _saving ? null : () => Navigator.pop(context),
                child: Text(t('inventory.cancel')),
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: FilledButton.icon(
                onPressed: _saving ? null : _save,
                icon: _saving
                    ? const SizedBox.square(
                        dimension: 18,
                        child: CircularProgressIndicator(strokeWidth: 2),
                      )
                    : const Icon(Icons.save_outlined),
                label: Text(t('inventory.save')),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _field(
    TextEditingController controller,
    String labelKey,
    IconData icon,
  ) => Padding(
    padding: const EdgeInsets.only(bottom: 12),
    child: TextFormField(
      controller: controller,
      textInputAction: TextInputAction.next,
      decoration: InputDecoration(
        labelText: t(labelKey),
        prefixIcon: Icon(icon),
      ),
    ),
  );
}

Map<String, dynamic> _map(dynamic value) => value is Map
    ? value.map((key, item) => MapEntry('$key', item))
    : <String, dynamic>{};
List<Map<String, dynamic>> _maps(dynamic value) => value is List
    ? value.whereType<Map>().map(_map).toList()
    : <Map<String, dynamic>>[];
int? _int(dynamic value) => value is int ? value : int.tryParse('$value');
