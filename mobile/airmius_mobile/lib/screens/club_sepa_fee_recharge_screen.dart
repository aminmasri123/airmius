import 'dart:math';

import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:url_launcher/url_launcher.dart';

import '../core/airmius_api_client.dart';
import '../core/airmius_date_input.dart';
import '../core/airmius_external_url.dart';
import '../core/airmius_l10n.dart';
import '../core/sepa_fee_recharge_labels.dart';
import 'club_sepa_fee_correction_screen.dart';

List<AirmiusJson> feeRecharges(AirmiusJson result) =>
    (result['fee_recharges'] as List? ?? const []).cast<AirmiusJson>().toList();

class SepaFeeRechargeSummary extends StatelessWidget {
  const SepaFeeRechargeSummary({super.key, required this.result});

  final AirmiusJson result;

  @override
  Widget build(BuildContext context) {
    final language = AirmiusScope.of(context).language.locale.languageCode;
    final labels =
        sepaFeeRechargeLabels[language] ?? sepaFeeRechargeLabels['en']!;
    final proposals = feeRecharges(result);
    String money(num cents) => NumberFormat.simpleCurrency(
      locale: language,
      name: 'EUR',
    ).format(cents / 100);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text('${labels['current']}: ${money(currentFeeCents(result))}'),
        if (proposals.isEmpty) Text(labels['empty']!),
        for (final proposal in proposals)
          Card(
            child: Padding(
              padding: const EdgeInsets.all(12),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    '#${proposal['id']} · ${labels['status_${proposal['status']}'] ?? proposal['status']} · ${money(proposal['amount_cents'] as num)}',
                    style: Theme.of(context).textTheme.titleSmall,
                  ),
                  Text(
                    '${labels['recipient']}: ${(proposal['member'] as AirmiusJson?)?['name'] ?? '#${proposal['member_id']}'}',
                  ),
                  Text(
                    '${labels['due']}: ${proposal['due_date'].toString().split('T').first}',
                  ),
                  Text('${labels['basis']}: ${proposal['basis']}'),
                  Text('${labels['reason']}: ${proposal['reason']}'),
                  if (proposal['revenue_account'] != null)
                    Text(
                      '${labels['account']}: ${proposal['revenue_account']}',
                    ),
                  if (proposal['invoice'] case final AirmiusJson invoice)
                    Text(
                      '${labels['invoice']}: ${invoice['number']} · ${labels['invoice_${invoice['status']}'] ?? invoice['status']} · ${invoice['amount']} EUR',
                    ),
                  if (proposal['review_required'] == true)
                    Text(
                      labels['review']!,
                      style: const TextStyle(fontWeight: FontWeight.bold),
                    ),
                  for (final request
                      in (proposal['void_requests'] as List? ?? const [])
                          .cast<AirmiusJson>())
                    Text(
                      '${labels['voidRequest']} #${request['id']} · ${labels['void_${request['status']}'] ?? request['status']} · ${request['reason']}',
                    ),
                  for (final credit
                      in (proposal['credit_requests'] as List? ?? const [])
                          .cast<AirmiusJson>()) ...[
                    Text(
                      '${labels['creditRequest']} #${credit['id']} · ${labels['credit_${credit['status']}'] ?? credit['status']} · ${money(credit['amount_cents'] as num)}',
                    ),
                    if (credit['credit_note_number'] != null)
                      Text(
                        '${labels['creditNote']}: ${credit['credit_note_number']}',
                      ),
                    if (credit['refund_due_cents'] != null)
                      Text(
                        '${labels['refundDue']}: ${money(credit['refund_due_cents'] as num)}',
                      ),
                    if (credit['refund_reference'] != null)
                      Text(
                        '${labels['refundEvidence']}: ${credit['refund_booked_on']} · ${credit['refund_reference']}',
                      ),
                  ],
                ],
              ),
            ),
          ),
      ],
    );
  }
}

class ClubSepaFeeRechargeScreen extends StatefulWidget {
  const ClubSepaFeeRechargeScreen({
    super.key,
    required this.client,
    required this.clubId,
    required this.batchId,
    required this.itemId,
    required this.result,
    required this.currentUserId,
    required this.canManage,
    required this.draftsAvailable,
    required this.approvalsAvailable,
    required this.voidsAvailable,
    required this.creditsAvailable,
    this.documentOpener,
  });

  final AirmiusApiClient client;
  final int clubId, batchId, itemId;
  final int? currentUserId;
  final bool canManage,
      draftsAvailable,
      approvalsAvailable,
      voidsAvailable,
      creditsAvailable;
  final AirmiusJson result;
  final Future<bool> Function(Uri uri)? documentOpener;

  @override
  State<ClubSepaFeeRechargeScreen> createState() =>
      _ClubSepaFeeRechargeScreenState();
}

class _ClubSepaFeeRechargeScreenState extends State<ClubSepaFeeRechargeScreen> {
  final _amount = TextEditingController();
  final _date = TextEditingController();
  final _basis = TextEditingController();
  final _reason = TextEditingController();
  final _account = TextEditingController();
  final _reference = TextEditingController();
  final _financeEntryId = TextEditingController();
  String? _action;
  int? _proposalId, _voidRequestId, _creditRequestId;
  bool _confirmed = false, _basisConfirmed = false, _busy = false;
  String? _error;
  AirmiusJson? _frozenBody;
  String? _frozenAction;
  int? _frozenProposalId, _frozenVoidRequestId, _frozenCreditRequestId;

  String c(String key) =>
      (sepaFeeRechargeLabels[AirmiusScope.of(
        context,
      ).language.locale.languageCode] ??
      sepaFeeRechargeLabels['en']!)[key]!;

  int get _revision {
    final corrections = feeCorrections(widget.result);
    return corrections.isEmpty ? 0 : corrections.last['revision'] as int;
  }

  String _requestId() {
    final random = Random.secure();
    final bytes = List<int>.generate(16, (_) => random.nextInt(256));
    bytes[6] = (bytes[6] & 15) | 64;
    bytes[8] = (bytes[8] & 63) | 128;
    final hex = bytes.map((b) => b.toRadixString(16).padLeft(2, '0')).join();
    return '${hex.substring(0, 8)}-${hex.substring(8, 12)}-${hex.substring(12, 16)}-${hex.substring(16, 20)}-${hex.substring(20)}';
  }

  void _changed(String _) {
    if (_confirmed || _basisConfirmed) {
      setState(() {
        _confirmed = false;
        _basisConfirmed = false;
      });
    }
  }

  void _select(
    String action, {
    int? proposalId,
    int? voidRequestId,
    int? creditRequestId,
  }) {
    if (_busy || _frozenBody != null) return;
    setState(() {
      _action = action;
      _proposalId = proposalId;
      _voidRequestId = voidRequestId;
      _creditRequestId = creditRequestId;
      _amount.text = (currentFeeCents(widget.result) / 100).toStringAsFixed(2);
      _date.clear();
      _basis.clear();
      _reason.clear();
      _account.clear();
      _reference.clear();
      _financeEntryId.clear();
      _confirmed = false;
      _basisConfirmed = false;
      _error = null;
    });
  }

  AirmiusJson _buildBody() {
    final action = _action;
    if (action == null) throw const FormatException();
    final body = <String, dynamic>{'confirmed': true};
    if (action == 'propose') {
      final value = _amount.text.trim().replaceAll(',', '.');
      final date = formatAirmiusApiDate(parseAirmiusDate(_date.text));
      if (!RegExp(r'^\d{1,6}(?:\.\d{1,2})?$').hasMatch(value) ||
          (double.parse(value) * 100).round() <= 0 ||
          (double.parse(value) * 100).round() >
              currentFeeCents(widget.result) ||
          DateFormat('yyyy-MM-dd').format(
                DateFormat('yyyy-MM-dd').parseStrict(date ?? ''),
              ) !=
              date ||
          _basis.text.trim().isEmpty ||
          _reason.text.trim().isEmpty) {
        throw const FormatException();
      }
      body.addAll({
        'request_id': _requestId(),
        'expected_revision': _revision,
        'amount_cents': (double.parse(value) * 100).round(),
        'due_date': date,
        'basis': _basis.text.trim(),
        'reason': _reason.text.trim(),
      });
    } else if (action == 'approve') {
      if (!_basisConfirmed ||
          !RegExp(r'^\d{1,20}$').hasMatch(_account.text.trim())) {
        throw const FormatException();
      }
      body.addAll({
        'basis_confirmed': true,
        'revenue_account': _account.text.trim(),
      });
    } else if (action == 'cancel' ||
        action == 'request_void' ||
        action == 'request_credit') {
      if (_reason.text.trim().isEmpty) throw const FormatException();
      body['reason'] = _reason.text.trim();
      if (action == 'request_void' || action == 'request_credit') {
        body['request_id'] = _requestId();
      }
    } else if (action == 'withdraw_void' || action == 'withdraw_credit') {
      if (_reason.text.trim().isEmpty) throw const FormatException();
      body['reason'] = _reason.text.trim();
    } else if (action == 'refund') {
      final date = formatAirmiusApiDate(parseAirmiusDate(_date.text));
      final reference = _reference.text.trim();
      final financeEntry = _financeEntryId.text.trim();
      if (DateFormat('yyyy-MM-dd').format(
                DateFormat('yyyy-MM-dd').parseStrict(date ?? ''),
              ) !=
              date ||
          reference.isEmpty ||
          reference.length > 180 ||
          (financeEntry.isNotEmpty &&
              !RegExp(r'^\d+$').hasMatch(financeEntry))) {
        throw const FormatException();
      }
      body.addAll({
        'request_id': _requestId(),
        'booked_on': date,
        'reference': reference,
        if (financeEntry.isNotEmpty)
          'finance_entry_id': int.parse(financeEntry),
      });
    } else if (action != 'approve_void' && action != 'approve_credit') {
      throw const FormatException();
    }
    if ((_reason.text.length > 2000) || (_basis.text.length > 2000)) {
      throw const FormatException();
    }
    return Map<String, dynamic>.unmodifiable(body);
  }

  Future<void> _save() async {
    if (_busy || !_confirmed || _action == null) return;
    if (_frozenBody == null) {
      try {
        _frozenBody = _buildBody();
        _frozenAction = _action;
        _frozenProposalId = _proposalId;
        _frozenVoidRequestId = _voidRequestId;
        _frozenCreditRequestId = _creditRequestId;
      } catch (_) {
        setState(() => _error = c('error'));
        return;
      }
    }
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      await widget.client.recordClubSepaFeeRecharge(
        widget.clubId,
        widget.batchId,
        widget.itemId,
        _frozenAction!,
        _frozenBody!,
        proposalId: _frozenProposalId,
        voidRequestId: _frozenVoidRequestId,
        creditRequestId: _frozenCreditRequestId,
      );
      if (mounted) Navigator.of(context).pop(true);
    } catch (error) {
      if (mounted) {
        setState(() {
          _error = error is AirmiusApiException
              ? error.userMessage
              : c('error');
          _confirmed = false;
          _basisConfirmed = false;
        });
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Widget _actionButton(
    String label,
    String action, {
    int? proposalId,
    int? voidRequestId,
    int? creditRequestId,
    bool enabled = true,
  }) => OutlinedButton(
    key: ValueKey(
      'recharge-$action-${proposalId ?? 0}-${voidRequestId ?? creditRequestId ?? 0}',
    ),
    onPressed: _busy || _frozenBody != null || !enabled
        ? null
        : () => _select(
            action,
            proposalId: proposalId,
            voidRequestId: voidRequestId,
            creditRequestId: creditRequestId,
          ),
    child: Text(label),
  );

  Widget _documentButton(int proposalId, int creditRequestId) => OutlinedButton(
    key: ValueKey('recharge-credit-document-$proposalId-$creditRequestId'),
    onPressed: _busy
        ? null
        : () => _openCreditDocument(proposalId, creditRequestId),
    child: Text(c('creditDocument')),
  );

  Future<void> _openCreditDocument(int proposalId, int creditRequestId) async {
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      final response = await widget.client
          .clubSepaFeeRechargeCreditDocumentLink(
            widget.clubId,
            widget.batchId,
            widget.itemId,
            proposalId,
            creditRequestId,
          );
      final data = response['data'];
      final uri = safeExternalHttpUrl(
        data is Map ? data['url']?.toString() : null,
      );
      final opener =
          widget.documentOpener ??
          (uri) => launchUrl(uri, mode: LaunchMode.externalApplication);
      if (uri == null || !await opener(uri)) throw const FormatException();
    } catch (_) {
      if (mounted) setState(() => _error = c('documentError'));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  List<Widget> _actions() {
    if (!widget.canManage) return const [];
    final proposals = feeRecharges(widget.result);
    final hasActive = proposals.any((p) => p['active_settlement_id'] != null);
    final actions = <Widget>[];
    for (final proposal in proposals) {
      final id = proposal['id'] as int;
      if (proposal['status'] == 'draft') {
        if (widget.draftsAvailable) {
          actions.add(_actionButton(c('cancel'), 'cancel', proposalId: id));
        }
        if (widget.approvalsAvailable &&
            widget.currentUserId != null &&
            proposal['proposed_by'] != null &&
            proposal['proposed_by'] != widget.currentUserId) {
          actions.add(
            _actionButton(
              c('approve'),
              'approve',
              proposalId: id,
              enabled: proposal['fee_revision'] == _revision,
            ),
          );
        }
      }
      final voids = (proposal['void_requests'] as List? ?? const [])
          .cast<AirmiusJson>();
      final pending = voids.where((v) => v['status'] == 'pending').toList();
      final invoice = proposal['invoice'] as AirmiusJson?;
      if (proposal['status'] == 'approved' &&
          widget.voidsAvailable &&
          pending.isEmpty) {
        actions.add(
          _actionButton(
            c('requestVoid'),
            'request_void',
            proposalId: id,
            enabled:
                invoice == null ||
                invoice['status'] == 'open' ||
                invoice['status'] == 'overdue',
          ),
        );
      }
      for (final request in pending) {
        final voidId = request['id'] as int;
        if (widget.voidsAvailable &&
            widget.currentUserId != null &&
            request['requested_by'] != null &&
            request['requested_by'] != widget.currentUserId) {
          actions.add(
            _actionButton(
              c('approveVoid'),
              'approve_void',
              proposalId: id,
              voidRequestId: voidId,
            ),
          );
        }
        if (widget.voidsAvailable) {
          actions.add(
            _actionButton(
              c('withdrawVoid'),
              'withdraw_void',
              proposalId: id,
              voidRequestId: voidId,
            ),
          );
        }
      }
      final credits = (proposal['credit_requests'] as List? ?? const [])
          .cast<AirmiusJson>();
      final pendingCredits = credits
          .where((credit) => credit['status'] == 'pending')
          .toList();
      if (proposal['status'] == 'approved' &&
          widget.creditsAvailable &&
          pendingCredits.isEmpty) {
        actions.add(
          _actionButton(c('requestCredit'), 'request_credit', proposalId: id),
        );
      }
      for (final credit in credits) {
        final creditId = credit['id'] as int;
        if (widget.creditsAvailable &&
            credit['credit_note_number'] != null &&
            const {
              'issued',
              'completed',
              'refunded',
            }.contains(credit['status'])) {
          actions.add(_documentButton(id, creditId));
        }
        if (credit['status'] == 'pending') {
          if (widget.creditsAvailable &&
              widget.currentUserId != null &&
              credit['requested_by'] != null &&
              credit['requested_by'] != widget.currentUserId) {
            actions.add(
              _actionButton(
                c('approveCredit'),
                'approve_credit',
                proposalId: id,
                creditRequestId: creditId,
              ),
            );
          }
          if (widget.creditsAvailable) {
            actions.add(
              _actionButton(
                c('withdrawCredit'),
                'withdraw_credit',
                proposalId: id,
                creditRequestId: creditId,
              ),
            );
          }
        }
        if (widget.creditsAvailable &&
            credit['status'] == 'issued' &&
            (credit['refund_due_cents'] as num? ?? 0) > 0) {
          actions.add(
            _actionButton(
              c('refund'),
              'refund',
              proposalId: id,
              creditRequestId: creditId,
            ),
          );
        }
      }
    }
    if (widget.draftsAvailable &&
        !hasActive &&
        currentFeeCents(widget.result) > 0) {
      actions.add(_actionButton(c('proposal'), 'propose'));
    }
    return actions;
  }

  @override
  void dispose() {
    for (final controller in [
      _amount,
      _date,
      _basis,
      _reason,
      _account,
      _reference,
      _financeEntryId,
    ]) {
      controller.dispose();
    }
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final needsReason = const {
      'propose',
      'cancel',
      'request_void',
      'withdraw_void',
      'request_credit',
      'withdraw_credit',
    }.contains(_action);
    return PopScope(
      canPop: !_busy,
      child: Scaffold(
        appBar: AppBar(title: Text(c('title'))),
        body: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            Text(c('help')),
            SepaFeeRechargeSummary(result: widget.result),
            if (_busy) const LinearProgressIndicator(),
            if (_action == null) Wrap(spacing: 8, children: _actions()),
            if (_action != null) ...[
              if (_action == 'propose') ...[
                TextField(
                  key: const ValueKey('recharge-amount'),
                  controller: _amount,
                  enabled: !_busy && _frozenBody == null,
                  onChanged: _changed,
                  keyboardType: const TextInputType.numberWithOptions(
                    decimal: true,
                  ),
                  decoration: InputDecoration(labelText: c('amount')),
                ),
                TextField(
                  key: const ValueKey('recharge-date'),
                  controller: _date,
                  enabled: !_busy && _frozenBody == null,
                  onChanged: _changed,
                  keyboardType: TextInputType.datetime,
                  inputFormatters: const [AirmiusDateInputFormatter()],
                  decoration: InputDecoration(
                    labelText: c('due'),
                    hintText: 'DD.MM.YYYY',
                  ),
                ),
                TextField(
                  key: const ValueKey('recharge-basis'),
                  controller: _basis,
                  enabled: !_busy && _frozenBody == null,
                  onChanged: _changed,
                  maxLength: 2000,
                  maxLines: 3,
                  decoration: InputDecoration(labelText: c('basis')),
                ),
              ],
              if (_action == 'approve') ...[
                TextField(
                  key: const ValueKey('recharge-account'),
                  controller: _account,
                  enabled: !_busy && _frozenBody == null,
                  onChanged: _changed,
                  maxLength: 20,
                  keyboardType: TextInputType.number,
                  decoration: InputDecoration(labelText: c('account')),
                ),
                CheckboxListTile(
                  key: const ValueKey('recharge-basis-confirm'),
                  value: _basisConfirmed,
                  onChanged: _busy || _frozenBody != null
                      ? null
                      : (value) =>
                            setState(() => _basisConfirmed = value == true),
                  title: Text(c('basisConfirm')),
                ),
              ],
              if (_action == 'refund') ...[
                Text(c('refundHelp')),
                TextField(
                  key: const ValueKey('recharge-refund-date'),
                  controller: _date,
                  enabled: !_busy && _frozenBody == null,
                  onChanged: _changed,
                  keyboardType: TextInputType.datetime,
                  inputFormatters: const [AirmiusDateInputFormatter()],
                  decoration: InputDecoration(
                    labelText: c('refundDate'),
                    hintText: 'DD.MM.YYYY',
                  ),
                ),
                TextField(
                  key: const ValueKey('recharge-refund-reference'),
                  controller: _reference,
                  enabled: !_busy && _frozenBody == null,
                  onChanged: _changed,
                  maxLength: 180,
                  decoration: InputDecoration(labelText: c('refundReference')),
                ),
                TextField(
                  key: const ValueKey('recharge-refund-finance-entry'),
                  controller: _financeEntryId,
                  enabled: !_busy && _frozenBody == null,
                  onChanged: _changed,
                  keyboardType: TextInputType.number,
                  decoration: InputDecoration(labelText: c('financeEntry')),
                ),
                Text(c('financeEntryHelp')),
              ],
              if (needsReason)
                TextField(
                  key: const ValueKey('recharge-reason'),
                  controller: _reason,
                  enabled: !_busy && _frozenBody == null,
                  onChanged: _changed,
                  maxLength: 2000,
                  maxLines: 3,
                  decoration: InputDecoration(labelText: c('reason')),
                ),
              if (_frozenBody != null && !_busy) Text(c('uncertain')),
              if (_error != null)
                Semantics(
                  liveRegion: true,
                  child: Text(
                    _error!,
                    style: TextStyle(
                      color: Theme.of(context).colorScheme.error,
                    ),
                  ),
                ),
              CheckboxListTile(
                key: const ValueKey('recharge-confirm'),
                value: _confirmed,
                onChanged: _busy
                    ? null
                    : (value) => setState(() => _confirmed = value == true),
                title: Text(c('confirm')),
              ),
              FilledButton(
                key: const ValueKey('recharge-submit'),
                onPressed: _busy || !_confirmed ? null : _save,
                child: Text(c(_frozenBody == null ? _buttonKey() : 'retry')),
              ),
              if (_frozenBody != null)
                OutlinedButton(
                  key: const ValueKey('recharge-reload'),
                  onPressed: _busy
                      ? null
                      : () => Navigator.of(context).pop(true),
                  child: Text(c('reload')),
                ),
            ],
          ],
        ),
      ),
    );
  }

  String _buttonKey() => switch (_action) {
    'propose' => 'proposal',
    'request_void' => 'requestVoid',
    'approve_void' => 'approveVoid',
    'withdraw_void' => 'withdrawVoid',
    'request_credit' => 'requestCredit',
    'approve_credit' => 'approveCredit',
    'withdraw_credit' => 'withdrawCredit',
    _ => _action!,
  };
}
