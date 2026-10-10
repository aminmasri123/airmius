import 'dart:math';
import 'package:flutter/material.dart';
import '../core/airmius_api_client.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';
import '../core/club_finance_workspace_labels.dart';
import '../models/club_summary.dart';

String financeWorkspaceLabel(BuildContext context, String key) =>
    clubFinanceWorkspaceLabels[AirmiusScope.of(
      context,
    ).language.locale.languageCode]?[key] ??
    key;

Future<Map<String, dynamic>?> chooseClubMoneyAccount(
  BuildContext context,
  AirmiusApiClient client,
  int clubId,
  String type,
) async {
  final response = await client.clubFinanceWorkspace(clubId);
  if (!context.mounted) return null;
  final data = response['data'] as Map<String, dynamic>? ?? {};
  final accounts = (data['accounts'] as List? ?? [])
      .whereType<Map<String, dynamic>>()
      .where((a) => a['type'] == type && a['is_active'] != false)
      .toList();
  if (data['available'] != true || (data['accounts'] as List? ?? []).isEmpty) {
    return {};
  }
  if (accounts.isEmpty) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(
          financeWorkspaceLabel(context, 'Keine Kassen oder Konten angelegt.'),
        ),
      ),
    );
    return null;
  }
  return showDialog<Map<String, dynamic>>(
    context: context,
    builder: (_) => _FinanceDialog(
      title: financeWorkspaceLabel(context, 'Kasse / Konto'),
      fields: [
        _FinanceField(
          'club_money_account_id',
          financeWorkspaceLabel(context, 'Kasse / Konto'),
          choices: {for (final a in accounts) a['id']: '${a['name']}'},
          required: true,
        ),
      ],
      initial: accounts.length == 1
          ? {'club_money_account_id': accounts.first['id']}
          : {},
    ),
  );
}

Future<Map<String, dynamic>?> teamFinancePaymentDialog(
  BuildContext context,
  List<Map<String, dynamic>> accounts, {
  bool refund = false,
}) async {
  String c(String key) => financeWorkspaceLabel(context, key);
  final value = await showDialog<Map<String, dynamic>>(
    context: context,
    builder: (_) => _FinanceDialog(
      title: c(refund ? 'Rückzahlung' : 'Zahlung erfassen'),
      fields: [
        _FinanceField('paid_on', c('Datum'), date: true, required: true),
        if (!refund)
          _FinanceField(
            'account',
            c('Zahlart'),
            choices: {'cash': c('Bar'), 'bank': c('Bank')},
            required: true,
          ),
        if (!refund)
          _FinanceField(
            'club_money_account_id',
            c('Kasse / Konto'),
            choices: {
              for (final a in accounts.where((a) => a['is_active'] != false))
                a['id']: '${a['name']}',
            },
          ),
        if (refund)
          _FinanceField(
            'confirmed',
            c('Rückzahlung bestätigen'),
            checkbox: true,
            required: true,
          ),
      ],
      initial: {
        'paid_on': DateTime.now().toIso8601String().substring(0, 10),
        'account': 'cash',
      },
    ),
  );
  if (value != null && value['club_money_account_id'] != null) {
    value['account'] = accounts.firstWhere(
      (a) => a['id'] == value['club_money_account_id'],
    )['type'];
  }
  return value;
}

class ClubFinanceWorkspaceScreen extends StatefulWidget {
  const ClubFinanceWorkspaceScreen({
    super.key,
    required this.club,
    this.initialTabIndex = 0,
  });
  final ClubSummary club;
  final int initialTabIndex;
  @override
  State<ClubFinanceWorkspaceScreen> createState() =>
      _ClubFinanceWorkspaceScreenState();
}

Future<Map<String, dynamic>?> clubFinanceScopeDialog(
  BuildContext context,
  AirmiusApiClient client,
  int clubId,
  Map<String, dynamic> initial,
  String account,
) async {
  final response = await client.clubFinanceWorkspace(clubId);
  final data = response['data'] as Map<String, dynamic>? ?? {};
  if (data['available'] != true) return initial;
  final budgetResponse = await client.clubFinanceList(clubId, 'budgets');
  if (!context.mounted) return null;
  String c(String key) => financeWorkspaceLabel(context, key);
  List<Map<String, dynamic>> rows(dynamic value) =>
      value is List ? value.whereType<Map<String, dynamic>>().toList() : [];
  Map<dynamic, String> options(dynamic value) => {
    for (final row in rows(value)) row['id']: '${row['name']}',
  };
  return showDialog<Map<String, dynamic>>(
    context: context,
    builder: (_) => _FinanceDialog(
      title: c('Zuordnung'),
      fields: [
        _FinanceField('team_id', c('Team'), choices: options(data['teams'])),
        _FinanceField(
          'club_budget_id',
          c('Budget'),
          choices: options((budgetResponse['data'] as Map?)?['budgets']),
        ),
        _FinanceField(
          'club_department_id',
          c('Abteilung'),
          choices: options(data['departments']),
        ),
        _FinanceField(
          'club_project_id',
          c('Projekt'),
          choices: options(data['projects']),
        ),
        _FinanceField(
          'club_cost_center_id',
          c('Kostenstelle'),
          choices: options(data['cost_centers']),
        ),
        _FinanceField(
          'club_money_account_id',
          c('Kasse / Konto'),
          choices: options(
            rows(data['accounts'])
                .where((a) => a['is_active'] != false && a['type'] == account)
                .toList(),
          ),
        ),
      ],
      initial: initial,
    ),
  );
}

class _ClubFinanceWorkspaceScreenState
    extends State<ClubFinanceWorkspaceScreen> {
  Map<String, dynamic> _data = {};
  List<Map<String, dynamic>> _budgets = [],
      _orders = [],
      _invoices = [],
      _payments = [];
  bool _loading = true, _busy = false;
  String? _error;
  String? _transferKey;
  AirmiusApiClient get _client {
    final services = AirmiusServicesScope.of(context);
    return services.clientForSession(services.authState.session);
  }

  String c(String key) => financeWorkspaceLabel(context, key);
  List<Map<String, dynamic>> rows(dynamic value) =>
      value is List ? value.whereType<Map<String, dynamic>>().toList() : [];
  String money(dynamic cents) =>
      '${((cents as num? ?? 0) / 100).toStringAsFixed(2)} EUR';
  String today() => DateTime.now().toIso8601String().substring(0, 10);
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _load());
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final response = await _client.clubFinanceWorkspace(widget.club.id);
      final data = response['data'] as Map<String, dynamic>? ?? {};
      if (data['available'] != true) {
        if (mounted) setState(() => _data = data);
        return;
      }
      final results = await Future.wait([
        _client.clubFinanceList(widget.club.id, 'budgets'),
        _client.clubFinanceList(widget.club.id, 'procurements'),
        _client.clubDetail(widget.club.id),
      ]);
      final management =
          (results[2]['data'] as Map?)?['management'] as Map? ?? {};
      if (mounted) {
        setState(() {
          _data = data;
          _budgets = rows((results[0]['data'] as Map?)?['budgets']);
          _orders = rows((results[1]['data'] as Map?)?['procurement_requests']);
          _invoices = rows(management['invoices']);
          _payments = rows(management['payments']);
        });
      }
    } catch (error) {
      if (mounted) {
        setState(
          () => _error = error is AirmiusApiException
              ? error.userMessage
              : c('Die Aktion konnte nicht abgeschlossen werden.'),
        );
      }
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  String status(dynamic value) => c(
    const {
          'draft': 'Entwurf',
          'submitted': 'Eingereicht',
          'approved': 'Genehmigt',
          'rejected': 'Abgelehnt',
          'archived': 'Archiviert',
          'ordered': 'Bestellt',
          'partially_received': 'Teilweise geliefert',
          'received': 'Geliefert',
        }['$value'] ??
        '$value',
  );

  Future<void> _write(
    String method,
    String path,
    Map<String, dynamic> payload,
  ) async {
    if (_busy) return;
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      await _client.clubFinanceWrite(widget.club.id, method, path, payload);
      if (path == 'money-transfers') _transferKey = null;
      if (mounted) await _load();
    } catch (error) {
      if (mounted) {
        setState(
          () => _error = error is AirmiusApiException
              ? error.userMessage
              : c('Die Aktion konnte nicht abgeschlossen werden.'),
        );
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Map<int, String> options(List<Map<String, dynamic>> list) => {
    for (final row in list)
      row['id'] as int: '${row['name'] ?? row['title'] ?? row['id']}',
  };
  _FinanceField select(
    String key,
    String label,
    Map<dynamic, String> choices, {
    bool required = false,
  }) => _FinanceField(key, c(label), choices: choices, required: required);
  _FinanceField text(
    String key,
    String label, {
    bool required = false,
    bool number = false,
    bool date = false,
  }) => _FinanceField(
    key,
    c(label),
    required: required,
    number: number,
    date: date,
  );
  Future<Map<String, dynamic>?> form(
    String title,
    List<_FinanceField> fields,
    Map<String, dynamic> initial,
  ) => showDialog<Map<String, dynamic>>(
    context: context,
    builder: (_) =>
        _FinanceDialog(title: c(title), fields: fields, initial: initial),
  );
  int cents(dynamic value) =>
      ((double.tryParse('$value'.replaceAll(',', '.')) ?? 0) * 100).round();

  Future<void> _budget([Map<String, dynamic>? row]) async {
    final value = await form(
      'Budget',
      [
        text('name', 'Name', required: true),
        select(
          'club_year_period_id',
          'Geschäftsjahr',
          options(rows(_data['periods'])),
          required: true,
        ),
        select('scope_type', 'Bereich', {
          for (final key in ['club', 'team', 'department', 'project'])
            key: c(
              {
                'club': 'Verein',
                'team': 'Team',
                'department': 'Abteilung',
                'project': 'Projekt',
              }[key]!,
            ),
        }),
        select('team_id', 'Team', options(rows(_data['teams']))),
        select(
          'club_department_id',
          'Abteilung',
          options(rows(_data['departments'])),
        ),
        select('club_project_id', 'Projekt', options(rows(_data['projects']))),
        select(
          'parent_id',
          'Übergeordnetes Budget',
          options(_budgets.where((b) => b['id'] != row?['id']).toList()),
        ),
        text(
          'income',
          'Geplante Einnahmen (EUR)',
          required: true,
          number: true,
        ),
        text('expense', 'Ausgabenbudget (EUR)', required: true, number: true),
        text('version', 'Version', required: true, number: true),
        select('approval_status', 'Status', {
          'draft': c('Entwurf'),
          'submitted': c('Eingereicht'),
          if (_data['can_approve'] == true) ...{
            'approved': c('Genehmigt'),
            'rejected': c('Abgelehnt'),
            'archived': c('Archiviert'),
          },
        }),
      ],
      {
        ...?row,
        'income': ((row?['planned_income_cents'] as num? ?? 0) / 100),
        'expense': ((row?['planned_expense_cents'] as num? ?? 0) / 100),
        'version': row?['version'] ?? 1,
        'scope_type': row?['scope_type'] ?? 'club',
        'approval_status': row?['approval_status'] ?? 'draft',
      },
    );
    if (value == null || !mounted) return;
    value['planned_income_cents'] = cents(value.remove('income'));
    value['planned_expense_cents'] = cents(value.remove('expense'));
    value['version'] = int.tryParse('${value['version']}');
    if (value['scope_type'] != 'team') value['team_id'] = null;
    if (value['scope_type'] != 'department') value['club_department_id'] = null;
    if (value['scope_type'] != 'project') {
      value['club_project_id'] = null;
      value['project_name'] = null;
    } else {
      value['project_name'] = rows(
        _data['projects'],
      ).where((p) => p['id'] == value['club_project_id']).firstOrNull?['name'];
    }
    await _write(
      row == null ? 'POST' : 'PUT',
      row == null ? 'budgets' : 'budgets/${row['id']}',
      value,
    );
  }

  Future<void> _account([Map<String, dynamic>? row]) async {
    final value = await form(
      'Kasse / Bankkonto',
      [
        text('name', 'Name', required: true),
        if (row == null) ...[
          select('type', 'Art', {'cash': c('Bar'), 'bank': c('Bank')}),
          select('team_id', 'Team', options(rows(_data['teams']))),
          text('opening', 'Anfangsbestand (EUR)', number: true, required: true),
          text('opened_on', 'Stichtag', date: true, required: true),
        ],
        if (row == null || row['type'] == 'bank') ...[
          text('bank_name', 'Bankname'),
          text('account_holder', 'Kontoinhaber'),
          text('iban', 'IBAN'),
          text('bic', 'BIC'),
        ],
        if (row != null) _FinanceField('is_active', c('Aktiv'), checkbox: true),
      ],
      row == null
          ? {'type': 'cash', 'opening': 0, 'opened_on': today()}
          : {...row},
    );
    if (value == null || !mounted) return;
    if (row == null) value['opening_cents'] = cents(value.remove('opening'));
    await _write(
      row == null ? 'POST' : 'PUT',
      row == null ? 'money-accounts' : "money-accounts/${row['id']}",
      value,
    );
  }

  Future<void> _transfer() async {
    final value = await form(
      'Geldtransfer',
      [
        select(
          'from_id',
          'Von',
          options(
            rows(
              _data['accounts'],
            ).where((a) => a['is_active'] != false).toList(),
          ),
          required: true,
        ),
        select(
          'to_id',
          'Nach',
          options(
            rows(
              _data['accounts'],
            ).where((a) => a['is_active'] != false).toList(),
          ),
          required: true,
        ),
        text('amount', 'Betrag (EUR)', required: true, number: true),
        text('booked_on', 'Datum', date: true, required: true),
        text('reference', 'Referenz'),
      ],
      {'booked_on': today()},
    );
    if (value == null || !mounted) return;
    value['amount_cents'] = cents(value.remove('amount'));
    final bytes = List.generate(16, (_) => Random.secure().nextInt(256));
    bytes[6] = (bytes[6] & 15) | 64;
    bytes[8] = (bytes[8] & 63) | 128;
    final hex = bytes.map((b) => b.toRadixString(16).padLeft(2, '0')).join();
    _transferKey ??=
        '${hex.substring(0, 8)}-${hex.substring(8, 12)}-${hex.substring(12, 16)}-${hex.substring(16, 20)}-${hex.substring(20)}';
    value['idempotency_key'] = _transferKey;
    await _write('POST', 'money-transfers', value);
  }

  Future<void> _order() async {
    final value = await form(
      'Beschaffung',
      [
        text('title', 'Titel', required: true),
        text('supplier', 'Lieferant'),
        select(
          'club_budget_id',
          'Budget',
          options(
            _budgets.where((b) => b['approval_status'] == 'approved').toList(),
          ),
        ),
        _FinanceField('items', c('Artikel'), items: true, required: true),
      ],
      {'items': <Map<String, dynamic>>[]},
    );
    if (value == null || !mounted) return;
    value['items'] = [
      for (final item in rows(value['items']))
        {
          'name': item['name'],
          'quantity_requested': int.tryParse('${item['quantity']}'),
          'unit_price_cents': cents(item['price']),
        },
    ];
    value['status'] = 'submitted';
    await _write('POST', 'procurements', value);
  }

  Future<void> _delivery(Map<String, dynamic> order) async {
    final items = rows(order['items'])
        .where(
          (i) =>
              (i['quantity_ordered'] as num) > (i['quantity_received'] as num),
        )
        .toList();
    final value = await form(
      'Wareneingang',
      [
        text('received_on', 'Lieferdatum', required: true, date: true),
        text('reference', 'Referenz'),
        for (final i in items)
          text('item_${i['id']}', '${i['name']}', number: true, required: true),
      ],
      {'received_on': today(), for (final i in items) 'item_${i['id']}': 0},
    );
    if (value == null || !mounted) return;
    value['items'] = [
      for (final i in items)
        if ((int.tryParse('${value['item_${i['id']}']}') ?? 0) > 0)
          {
            'id': i['id'],
            'quantity_received': int.parse('${value['item_${i['id']}']}'),
          },
    ];
    for (final i in items) {
      value.remove('item_${i['id']}');
    }
    await _write('POST', 'procurements/${order['id']}/receipts', value);
  }

  Future<void> _pay(
    Map<String, dynamic> order,
    Map<String, dynamic> receipt,
  ) async {
    final value = await form(
      'Zahlung erfassen',
      [
        text('paid_on', 'Zahlungsdatum', date: true, required: true),
        select(
          'club_money_account_id',
          'Kasse / Konto',
          options(
            rows(
              _data['accounts'],
            ).where((a) => a['is_active'] != false).toList(),
          ),
          required: true,
        ),
        text('reference', 'Referenz'),
      ],
      {'paid_on': today(), 'reference': receipt['reference'] ?? ''},
    );
    if (value == null || !mounted) return;
    value['account'] = rows(
      _data['accounts'],
    ).firstWhere((a) => a['id'] == value['club_money_account_id'])['type'];
    await _write(
      'POST',
      'procurements/${order['id']}/receipts/${receipt['id']}/payment',
      value,
    );
  }

  Future<void> _assign(Map<String, dynamic> row, String kind) async {
    final value = await form('Zuordnung', [
      select('team_id', 'Team', options(rows(_data['teams']))),
      select('club_budget_id', 'Budget', options(_budgets)),
      select(
        'club_department_id',
        'Abteilung',
        options(rows(_data['departments'])),
      ),
      select('club_project_id', 'Projekt', options(rows(_data['projects']))),
      select(
        'club_cost_center_id',
        'Kostenstelle',
        options(rows(_data['cost_centers'])),
      ),
      if (kind == 'payment')
        select(
          'club_money_account_id',
          'Kasse / Konto',
          options(
            rows(_data['accounts'])
                .where(
                  (a) =>
                      a['is_active'] != false &&
                      a['type'] == (row['method'] == 'cash' ? 'cash' : 'bank'),
                )
                .toList(),
          ),
        ),
    ], row);
    if (value == null || !mounted) return;
    await _write('PUT', 'finance-scopes/$kind/${row['id']}', value);
  }

  Future<void> _close(Map<String, dynamic> period) async {
    final value = await form('Jahresabschluss', [
      select(
        'next_period_id',
        'Folgejahr',
        options(
          rows(_data['periods'])
              .where(
                (p) =>
                    p['finance_closed_at'] == null &&
                    '${p['starts_on']}'.compareTo('${period['ends_on']}') > 0,
              )
              .toList(),
        ),
        required: true,
      ),
      _FinanceField(
        'confirmed',
        c('Abschluss bestätigen'),
        checkbox: true,
        required: true,
      ),
    ], {});
    if (value == null || !mounted) return;
    await _write('POST', 'year-periods/${period['id']}/finance-close', value);
  }

  Future<void> _reference() async {
    final value = await form(
      'Projekt / Kostenstelle',
      [
        text('name', 'Name', required: true),
        text('code', 'Kennung', required: true),
        select('kind', 'Art', {
          'project': c('Projekt'),
          'cost-center': c('Kostenstelle'),
        }, required: true),
      ],
      {'kind': 'cost-center'},
    );
    if (value == null || !mounted) return;
    await _write('POST', 'finance-references/${value.remove('kind')}', value);
  }

  Widget command(String label, IconData icon, VoidCallback action) =>
      TextButton.icon(
        onPressed: _busy ? null : action,
        icon: Icon(icon, size: 18),
        label: Text(c(label)),
      );
  Widget lines(List<Widget> children) =>
      ListView(padding: const EdgeInsets.all(16), children: children);
  @override
  Widget build(BuildContext context) {
    final manage = _data['can_manage'] == true,
        approve = _data['can_approve'] == true;
    return DefaultTabController(
      length: 5,
      initialIndex: widget.initialTabIndex,
      child: Scaffold(
        appBar: AppBar(
          title: Text(c('Budgets & Teamkassen')),
          actions: [
            IconButton(
              tooltip: c('Aktualisieren'),
              onPressed: _busy || _loading ? null : _load,
              icon: const Icon(Icons.refresh),
            ),
          ],
          bottom: TabBar(
            isScrollable: true,
            tabs: [
              for (final key in [
                'Budgets',
                'Kassen & Konten',
                'Beschaffungen',
                'Zuordnungen',
                'Jahresabschluss',
              ])
                Tab(text: c(key)),
            ],
          ),
        ),
        body: Column(
          children: [
            if (_error != null)
              Padding(
                padding: const EdgeInsets.all(12),
                child: Text(
                  _error!,
                  style: TextStyle(color: Theme.of(context).colorScheme.error),
                ),
              ),
            if (_loading || _busy) const LinearProgressIndicator(),
            Expanded(
              child: _data['available'] != true
                  ? Center(
                      child: Text(_loading ? '' : c('Noch nicht verfügbar')),
                    )
                  : TabBarView(
                      children: [
                        lines([
                          if (manage)
                            command('Budget anlegen', Icons.add, _budget),
                          if (_budgets.isEmpty)
                            Text(c('Keine Budgets vorhanden.')),
                          for (final b in _budgets) ...[
                            ListTile(
                              contentPadding: EdgeInsets.zero,
                              title: Text('${b['name']}'),
                              subtitle: Text(
                                '${(b['year_period'] as Map?)?['name'] ?? ''} · ${status(b['approval_status'])}',
                              ),
                            ),
                            Wrap(
                              spacing: 16,
                              runSpacing: 8,
                              children: [
                                for (final pair in {
                                  'Ausgabenplan': b['planned_expense_cents'],
                                  'Ausgegeben':
                                      (b['financial_report']
                                          as Map?)?['actual_expense_cents'],
                                  'Reserviert':
                                      (b['financial_report']
                                          as Map?)?['reserved_expense_cents'],
                                  'Frei':
                                      (b['financial_report']
                                          as Map?)?['remaining_budget_cents'],
                                  'Einnahmen':
                                      (b['financial_report']
                                          as Map?)?['actual_income_cents'],
                                  'Offene Forderungen':
                                      (b['financial_report']
                                          as Map?)?['open_receivables_cents'],
                                }.entries)
                                  Text('${c(pair.key)}: ${money(pair.value)}'),
                              ],
                            ),
                            Wrap(
                              children: [
                                if (manage &&
                                    (![
                                          'approved',
                                          'archived',
                                        ].contains(b['approval_status']) ||
                                        approve))
                                  command(
                                    'Budget bearbeiten',
                                    Icons.edit_outlined,
                                    () => _budget(b),
                                  ),
                                if (approve &&
                                    b['approval_status'] == 'submitted') ...[
                                  command(
                                    'Genehmigen',
                                    Icons.check,
                                    () => _write(
                                      'PUT',
                                      'budgets/${b['id']}/approval',
                                      {'approval_status': 'approved'},
                                    ),
                                  ),
                                  command(
                                    'Ablehnen',
                                    Icons.close,
                                    () => _write(
                                      'PUT',
                                      'budgets/${b['id']}/approval',
                                      {'approval_status': 'rejected'},
                                    ),
                                  ),
                                ],
                              ],
                            ),
                            const Divider(),
                          ],
                        ]),
                        lines([
                          if (manage)
                            Wrap(
                              children: [
                                command(
                                  'Kasse / Konto anlegen',
                                  Icons.add,
                                  _account,
                                ),
                                if (rows(_data['accounts']).length >= 2)
                                  command(
                                    'Transfer',
                                    Icons.swap_horiz,
                                    _transfer,
                                  ),
                              ],
                            ),
                          if (rows(_data['accounts']).isEmpty)
                            Text(c('Keine Kassen oder Konten angelegt.')),
                          for (final a in rows(_data['accounts']))
                            ListTile(
                              contentPadding: EdgeInsets.zero,
                              title: Text('${a['name']}'),
                              subtitle: Text(
                                [
                                  c(a['type'] == 'cash' ? 'Bar' : 'Bank'),
                                  rows(_data['teams'])
                                              .where(
                                                (team) =>
                                                    team['id'] == a['team_id'],
                                              )
                                              .firstOrNull?['name']
                                          as String? ??
                                      c('Verein'),
                                  if (a['is_active'] == false) c('Archiviert'),
                                  if (a['iban'] != null) '${a['iban']}',
                                ].join(' · '),
                              ),
                              trailing: Row(
                                mainAxisSize: MainAxisSize.min,
                                children: [
                                  Text(money(a['balance_cents'])),
                                  if (manage)
                                    IconButton(
                                      tooltip: c('Bearbeiten'),
                                      onPressed: _busy
                                          ? null
                                          : () => _account(a),
                                      icon: const Icon(Icons.edit_outlined),
                                    ),
                                ],
                              ),
                            ),
                          for (final a in rows(
                            _data['unassigned_accounts'],
                          ).where((a) => a['balance_cents'] != 0))
                            ListTile(
                              contentPadding: EdgeInsets.zero,
                              title: Text(c('Nicht zugeordnet')),
                              subtitle: Text(
                                c(a['type'] == 'cash' ? 'Bar' : 'Bank'),
                              ),
                              trailing: Text(money(a['balance_cents'])),
                            ),
                        ]),
                        lines([
                          if (manage)
                            command(
                              'Beschaffung beantragen',
                              Icons.add_shopping_cart,
                              _order,
                            ),
                          if (_orders.isEmpty)
                            Text(c('Keine Beschaffungen vorhanden.')),
                          for (final o in _orders) ...[
                            ListTile(
                              contentPadding: EdgeInsets.zero,
                              title: Text('${o['title']}'),
                              subtitle: Text(
                                '${status(o['status'])} · ${money(o['estimated_total_cents'])}',
                              ),
                            ),
                            Wrap(
                              children: [
                                if (approve &&
                                    o['can_approve'] != false &&
                                    o['status'] == 'submitted') ...[
                                  command(
                                    'Genehmigen',
                                    Icons.check,
                                    () => _write(
                                      'PUT',
                                      'procurements/${o['id']}/approval',
                                      {'status': 'approved'},
                                    ),
                                  ),
                                  command(
                                    'Ablehnen',
                                    Icons.close,
                                    () => _write(
                                      'PUT',
                                      'procurements/${o['id']}/approval',
                                      {'status': 'rejected'},
                                    ),
                                  ),
                                ],
                                if (manage && o['status'] == 'approved')
                                  command(
                                    'Bestellen',
                                    Icons.shopping_cart_outlined,
                                    () => _write(
                                      'POST',
                                      'procurements/${o['id']}/order',
                                      {},
                                    ),
                                  ),
                                if (manage &&
                                    [
                                      'ordered',
                                      'partially_received',
                                    ].contains(o['status']))
                                  command(
                                    'Wareneingang',
                                    Icons.inventory_2_outlined,
                                    () => _delivery(o),
                                  ),
                              ],
                            ),
                            for (final r in rows(o['receipts']))
                              ListTile(
                                contentPadding: EdgeInsets.zero,
                                title: Text(
                                  '${r['received_on']} · ${money(r['total_cents'])}',
                                ),
                                subtitle: Text(
                                  c(
                                    r['paid'] == true ? 'Bezahlt' : 'Unbezahlt',
                                  ),
                                ),
                                trailing: manage && r['paid'] != true
                                    ? IconButton(
                                        tooltip: c('Zahlung erfassen'),
                                        onPressed: _busy
                                            ? null
                                            : () => _pay(o, r),
                                        icon: const Icon(
                                          Icons.payments_outlined,
                                        ),
                                      )
                                    : null,
                              ),
                            const Divider(),
                          ],
                        ]),
                        lines([
                          if (manage)
                            command(
                              'Projekt / Kostenstelle anlegen',
                              Icons.add,
                              _reference,
                            ),
                          for (final group in {
                            'invoice': _invoices,
                            'payment': _payments,
                          }.entries) ...[
                            Text(
                              c(
                                group.key == 'invoice'
                                    ? 'Rechnungen'
                                    : 'Zahlungen',
                              ),
                              style: Theme.of(context).textTheme.titleMedium,
                            ),
                            for (final row in group.value)
                              ListTile(
                                contentPadding: EdgeInsets.zero,
                                title: Text(
                                  '${row['number'] ?? row['receipt_number'] ?? row['id']}',
                                ),
                                subtitle: Text(
                                  '${row['title'] ?? row['reference'] ?? row['purpose'] ?? ''}',
                                ),
                                trailing: manage
                                    ? IconButton(
                                        tooltip: c('Zuordnung bearbeiten'),
                                        onPressed: _busy
                                            ? null
                                            : () => _assign(row, group.key),
                                        icon: const Icon(Icons.edit_outlined),
                                      )
                                    : null,
                              ),
                          ],
                        ]),
                        lines([
                          for (final p in rows(_data['periods']))
                            ListTile(
                              contentPadding: EdgeInsets.zero,
                              title: Text('${p['name']}'),
                              subtitle: Text(
                                p['finance_closed_at'] != null
                                    ? '${c('Abgeschlossen')} · ${money((p['finance_closing_snapshot'] as Map?)?['total_cents'])}'
                                    : '${p['starts_on']}'.substring(0, 10),
                              ),
                              trailing:
                                  p['finance_closed_at'] == null &&
                                      _data['can_close'] == true &&
                                      '${p['ends_on']}'
                                              .substring(0, 10)
                                              .compareTo(today()) <
                                          0
                                  ? IconButton(
                                      tooltip: c('Abschließen'),
                                      onPressed: _busy ? null : () => _close(p),
                                      icon: const Icon(Icons.lock_outline),
                                    )
                                  : null,
                            ),
                        ]),
                      ],
                    ),
            ),
          ],
        ),
      ),
    );
  }
}

class _FinanceField {
  const _FinanceField(
    this.key,
    this.label, {
    this.required = false,
    this.number = false,
    this.date = false,
    this.checkbox = false,
    this.items = false,
    this.choices,
  });
  final String key, label;
  final bool required, number, date, checkbox, items;
  final Map<dynamic, String>? choices;
}

class _FinanceDialog extends StatefulWidget {
  const _FinanceDialog({
    required this.title,
    required this.fields,
    required this.initial,
  });
  final String title;
  final List<_FinanceField> fields;
  final Map<String, dynamic> initial;
  @override
  State<_FinanceDialog> createState() => _FinanceDialogState();
}

class _FinanceDialogState extends State<_FinanceDialog> {
  final _form = GlobalKey<FormState>();
  late Map<String, dynamic> _values;
  late Map<String, TextEditingController> _controllers;
  @override
  void initState() {
    super.initState();
    _values = {
      for (final field in widget.fields) field.key: widget.initial[field.key],
    };
    _controllers = {
      for (final field in widget.fields)
        if (field.choices == null && !field.checkbox && !field.items)
          field.key: TextEditingController(
            text: '${widget.initial[field.key] ?? ''}',
          ),
    };
  }

  @override
  void dispose() {
    for (final controller in _controllers.values) {
      controller.dispose();
    }
    super.dispose();
  }

  Widget _itemsField(_FinanceField field) =>
      FormField<List<Map<String, dynamic>>>(
        initialValue: List<Map<String, dynamic>>.from(
          _values[field.key] as List? ?? [],
        ),
        validator: (value) => (value ?? []).isEmpty ? field.label : null,
        builder: (state) => Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            for (final item in state.value ?? <Map<String, dynamic>>[])
              ListTile(
                contentPadding: EdgeInsets.zero,
                title: Text('${item['name']}'),
                subtitle: Text('${item['quantity']} · ${item['price']} EUR'),
                trailing: IconButton(
                  tooltip: financeWorkspaceLabel(context, 'Artikel entfernen'),
                  icon: const Icon(Icons.delete_outline),
                  onPressed: () {
                    final list = [...state.value!];
                    list.remove(item);
                    state.didChange(list);
                    _values[field.key] = list;
                  },
                ),
              ),
            TextButton.icon(
              icon: const Icon(Icons.add),
              label: Text(field.label),
              onPressed: () async {
                final item = await showDialog<Map<String, dynamic>>(
                  context: context,
                  builder: (_) => _FinanceDialog(
                    title: field.label,
                    fields: [
                      _FinanceField('name', field.label, required: true),
                      _FinanceField(
                        'quantity',
                        financeWorkspaceLabel(context, 'Anzahl'),
                        number: true,
                        required: true,
                      ),
                      _FinanceField(
                        'price',
                        financeWorkspaceLabel(context, 'Stückpreis (EUR)'),
                        number: true,
                        required: true,
                      ),
                    ],
                    initial: const {'quantity': 1, 'price': 0},
                  ),
                );
                if (item == null || !mounted) return;
                final list = [...?state.value, item];
                state.didChange(list);
                _values[field.key] = list;
              },
            ),
            if (state.hasError)
              Text(
                state.errorText!,
                style: TextStyle(color: Theme.of(context).colorScheme.error),
              ),
          ],
        ),
      );

  @override
  Widget build(BuildContext context) => AlertDialog(
    title: Text(widget.title),
    content: SizedBox(
      width: 440,
      child: Form(
        key: _form,
        child: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              if (widget.title ==
                  financeWorkspaceLabel(context, 'Jahresabschluss'))
                Padding(
                  padding: const EdgeInsets.only(bottom: 12),
                  child: Text(
                    financeWorkspaceLabel(
                      context,
                      'Buchungen dieses Jahres werden gesperrt. Der Saldo wird ohne neue Einnahmen ins Folgejahr übernommen.',
                    ),
                  ),
                ),
              for (final field in widget.fields.where(
                (field) =>
                    ![
                      'bank_name',
                      'account_holder',
                      'iban',
                      'bic',
                    ].contains(field.key) ||
                    _values['type'] != 'cash',
              ))
                Padding(
                  key: ValueKey(field.key),
                  padding: const EdgeInsets.only(bottom: 12),
                  child: field.items
                      ? _itemsField(field)
                      : field.checkbox
                      ? FormField<bool>(
                          initialValue: _values[field.key] == true,
                          validator: (value) => field.required && value != true
                              ? field.label
                              : null,
                          builder: (state) => Column(
                            children: [
                              CheckboxListTile(
                                contentPadding: EdgeInsets.zero,
                                title: Text(field.label),
                                value: state.value,
                                onChanged: (value) {
                                  state.didChange(value);
                                  _values[field.key] = value;
                                },
                              ),
                              if (state.hasError)
                                Text(
                                  state.errorText!,
                                  style: TextStyle(
                                    color: Theme.of(context).colorScheme.error,
                                  ),
                                ),
                            ],
                          ),
                        )
                      : field.choices != null
                      ? DropdownButtonFormField<dynamic>(
                          initialValue:
                              field.choices!.containsKey(_values[field.key])
                              ? _values[field.key]
                              : null,
                          isExpanded: true,
                          decoration: InputDecoration(labelText: field.label),
                          items: [
                            if (!field.required)
                              DropdownMenuItem<dynamic>(
                                value: null,
                                child: Text(
                                  financeWorkspaceLabel(context, 'Keine'),
                                ),
                              ),
                            for (final option in field.choices!.entries)
                              DropdownMenuItem<dynamic>(
                                value: option.key,
                                child: Text(
                                  option.value,
                                  overflow: TextOverflow.ellipsis,
                                ),
                              ),
                          ],
                          validator: (value) => field.required && value == null
                              ? field.label
                              : null,
                          onChanged: (value) =>
                              setState(() => _values[field.key] = value),
                        )
                      : TextFormField(
                          controller: _controllers[field.key],
                          keyboardType: field.number
                              ? const TextInputType.numberWithOptions(
                                  decimal: true,
                                  signed: true,
                                )
                              : TextInputType.text,
                          readOnly: field.date,
                          decoration: InputDecoration(
                            labelText: field.label,
                            suffixIcon: field.date
                                ? const Icon(Icons.calendar_today_outlined)
                                : null,
                          ),
                          onTap: field.date
                              ? () async {
                                  final date = await showDatePicker(
                                    context: context,
                                    initialDate:
                                        DateTime.tryParse(
                                          _controllers[field.key]!.text,
                                        ) ??
                                        DateTime.now(),
                                    firstDate: DateTime(1900),
                                    lastDate: DateTime(2200),
                                  );
                                  if (date != null) {
                                    _controllers[field.key]!.text = date
                                        .toIso8601String()
                                        .substring(0, 10);
                                  }
                                }
                              : null,
                          validator: (value) {
                            if (field.required &&
                                (value ?? '').trim().isEmpty) {
                              return field.label;
                            }
                            if (field.number &&
                                double.tryParse(
                                      (value ?? '').replaceAll(',', '.'),
                                    ) ==
                                    null) {
                              return field.label;
                            }
                            return null;
                          },
                        ),
                ),
            ],
          ),
        ),
      ),
    ),
    actions: [
      TextButton(
        onPressed: () => Navigator.pop(context),
        child: Text(financeWorkspaceLabel(context, 'Abbrechen')),
      ),
      FilledButton(
        onPressed: () {
          if (!_form.currentState!.validate()) return;
          Navigator.pop(context, {
            ..._values,
            for (final pair in _controllers.entries)
              pair.key: pair.value.text.trim(),
          });
        },
        child: Text(financeWorkspaceLabel(context, 'Speichern')),
      ),
    ],
  );
}
