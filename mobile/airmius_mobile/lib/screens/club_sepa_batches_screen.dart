import 'dart:convert';
import 'dart:typed_data';
import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import '../core/airmius_api_client.dart';
import '../core/airmius_date_input.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';
import '../core/sepa_batch_labels.dart';
import '../core/sepa_fee_labels.dart';
import 'club_sepa_fee_screen.dart';
import 'club_sepa_fee_correction_screen.dart';
import 'club_sepa_fee_recharge_screen.dart';
import '../core/sepa_fee_correction_labels.dart';
import '../core/sepa_fee_recharge_labels.dart';
import '../core/sepa_return_labels.dart';
import 'club_sepa_return_import.dart';

class ClubSepaBatchesScreen extends StatefulWidget {
  const ClubSepaBatchesScreen({
    super.key,
    required this.clubId,
    required this.invoices,
  });
  final int clubId;
  final List<AirmiusJson> invoices;
  @override
  State<ClubSepaBatchesScreen> createState() => _ClubSepaBatchesScreenState();
}

class _ClubSepaBatchesScreenState extends State<ClubSepaBatchesScreen> {
  bool _started = false, _busy = false, _more = false;
  bool _canManage = false;
  bool _noticesAvailable = false;
  bool _resultsAvailable = false;
  bool _feesAvailable = false;
  bool _feeCorrectionsAvailable = false;
  bool _feeRechargeDraftsAvailable = false;
  bool _feeRechargeApprovalsAvailable = false;
  bool _feeRechargeVoidsAvailable = false;
  bool _feeRechargeCreditsAvailable = false;
  int _page = 1;
  String? _error;
  List<AirmiusJson> _batches = [];
  String c(String key) =>
      (sepaBatchLabels[AirmiusScope.of(context).language.locale.languageCode] ??
          sepaBatchLabels['en']!)[key] ??
      key;
  String money(dynamic cents) => NumberFormat.simpleCurrency(
    locale: AirmiusScope.of(context).language.locale.languageCode,
    name: 'EUR',
  ).format((cents as num) / 100);
  AirmiusApiClient get _client {
    final services = AirmiusServicesScope.of(context);
    return services.clientForSession(services.authState.session);
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (!_started) {
      _started = true;
      _run(() async {});
    }
  }

  Future<void> _fetch({bool append = false}) async {
    final response = await _client.clubSepaBatches(
      widget.clubId,
      page: append ? _page + 1 : 1,
    );
    final result = response['data'] as AirmiusJson;
    final rows = (result['data'] as List).cast<AirmiusJson>();
    if (!mounted) return;
    setState(() {
      _canManage = response['can_manage'] == true;
      _noticesAvailable = response['notices_available'] == true;
      _resultsAvailable = response['results_available'] == true;
      _feesAvailable = response['fees_available'] == true;
      _feeCorrectionsAvailable = response['fee_corrections_available'] == true;
      _feeRechargeDraftsAvailable =
          response['fee_recharge_drafts_available'] == true;
      _feeRechargeApprovalsAvailable =
          response['fee_recharge_approvals_available'] == true;
      _feeRechargeVoidsAvailable =
          response['fee_recharge_voids_available'] == true;
      _feeRechargeCreditsAvailable =
          response['fee_recharge_credits_available'] == true;
      _batches = append ? [..._batches, ...rows] : rows;
      _page = result['current_page'] as int;
      _more = result['next_page_url'] != null;
    });
  }

  Future<void> _run(
    Future<void> Function() action, {
    bool append = false,
  }) async {
    if (_busy) return;
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      await action();
      if (mounted) await _fetch(append: append);
    } on AirmiusApiException catch (e) {
      if (mounted) setState(() => _error = e.userMessage);
    } catch (_) {
      if (mounted) setState(() => _error = c('error'));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  // Wait for the closing animation before disposing controllers owned by callers.
  Future<T?> _showDialog<T>({
    required BuildContext context,
    required WidgetBuilder builder,
  }) async {
    final route = DialogRoute<T>(context: context, builder: builder);
    final result = await Navigator.of(context, rootNavigator: true).push(route);
    await route.completed;
    return result;
  }

  Future<void> _create() async {
    final selected = <int>{};
    final date = TextEditingController();
    final days = TextEditingController(text: '14');
    final form = GlobalKey<FormState>();
    try {
      final result = await _showDialog<AirmiusJson>(
        context: context,
        builder: (context) => StatefulBuilder(
          builder: (context, change) => AlertDialog(
            title: Text(c('prepare')),
            content: SizedBox(
              width: 440,
              child: SingleChildScrollView(
                child: Form(
                  key: form,
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      for (final invoice in _currentInvoices().where(
                        (i) => ['open', 'overdue'].contains(i['status']),
                      ))
                        CheckboxListTile(
                          value: selected.contains(invoice['id']),
                          title: Text(
                            '${invoice['number'] ?? invoice['title']} · ${invoice['outstanding_amount'] ?? invoice['amount']} EUR',
                          ),
                          onChanged: (value) => change(() {
                            if (value == true) {
                              selected.add(invoice['id'] as int);
                            } else {
                              selected.remove(invoice['id']);
                            }
                          }),
                        ),
                      TextFormField(
                        controller: date,
                        keyboardType: TextInputType.datetime,
                        inputFormatters: const [AirmiusDateInputFormatter()],
                        decoration: InputDecoration(
                          labelText: c('date'),
                          hintText: 'DD.MM.YYYY',
                        ),
                        validator: (v) => parseAirmiusDate(v) == null
                            ? c('date')
                            : null,
                      ),
                      TextFormField(
                        controller: days,
                        keyboardType: TextInputType.number,
                        decoration: InputDecoration(labelText: c('days')),
                        validator: (v) {
                          final n = int.tryParse(v ?? '');
                          return n == null || n < 1 || n > 60
                              ? c('days')
                              : null;
                        },
                      ),
                    ],
                  ),
                ),
              ),
            ),
            actions: [
              TextButton(
                onPressed: () => Navigator.pop(context),
                child: Text(
                  MaterialLocalizations.of(context).cancelButtonLabel,
                ),
              ),
              FilledButton(
                onPressed: selected.isEmpty
                    ? null
                    : () {
                        if (form.currentState!.validate()) {
                          Navigator.pop(context, <String, dynamic>{
                            'invoice_ids': selected.toList(),
                            'collection_date': formatAirmiusApiDate(
                              parseAirmiusDate(date.text),
                            ),
                            'notice_days': int.parse(days.text),
                          });
                        }
                      },
                child: Text(c('prepare')),
              ),
            ],
          ),
        ),
      );
      if (result != null && mounted) {
        await _run(() async {
          await _client.createClubSepaBatch(widget.clubId, result);
        });
      }
    } finally {
      date.dispose();
      days.dispose();
    }
  }

  List<AirmiusJson> _currentInvoices() {
    final invoices = {
      for (final invoice in widget.invoices) invoice['id']: invoice,
    };
    for (final batch in _batches) {
      for (final item in (batch['items'] as List).cast<AirmiusJson>()) {
        if (item['invoice'] is AirmiusJson) {
          final invoice = item['invoice'] as AirmiusJson;
          invoices[invoice['id']] = invoice;
        }
      }
    }
    return invoices.values.toList();
  }

  Future<void> _bankResult(
    AirmiusJson batch,
    AirmiusJson item,
    String action,
  ) async {
    final date = TextEditingController(),
        reference = TextEditingController(),
        reason = TextEditingController(),
        fee = TextEditingController(text: '0');
    final form = GlobalKey<FormState>();
    bool confirmed = false;
    int? paymentId;
    final title = c(
      {
        'settle': 'settleItem',
        'return': 'returnItem',
        'retry': 'retryItem',
      }[action]!,
    );
    try {
      final data = await _showDialog<AirmiusJson>(
        context: context,
        builder: (context) => StatefulBuilder(
          builder: (context, change) => AlertDialog(
            title: Text('$title · ${item['number']}'),
            content: SizedBox(
              width: 440,
              child: SingleChildScrollView(
                child: Form(
                  key: form,
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Text('${money(item['amount_cents'])} · ${c('bankHelp')}'),
                      if (action != 'retry') ...[
                        TextFormField(
                          controller: date,
                          keyboardType: TextInputType.datetime,
                          inputFormatters: const [AirmiusDateInputFormatter()],
                          decoration: InputDecoration(
                            labelText: c('bookedOn'),
                            hintText: 'DD.MM.YYYY',
                          ),
                          validator: (value) =>
                              parseAirmiusDate(value) == null
                              ? c('bookedOn')
                              : null,
                        ),
                        TextFormField(
                          controller: reference,
                          maxLength: 180,
                          decoration: InputDecoration(
                            labelText: c('bankReference'),
                          ),
                          validator: (value) => (value ?? '').trim().isEmpty
                              ? c('bankReference')
                              : null,
                        ),
                      ],
                      if (action == 'settle')
                        DropdownButtonFormField<int>(
                          isExpanded: true,
                          decoration: InputDecoration(
                            labelText: c('paymentChoice'),
                          ),
                          items: [
                            DropdownMenuItem(
                              value: 0,
                              child: Text(c('newPayment')),
                            ),
                            for (final payment
                                in (item['payment_options'] as List? ??
                                        const [])
                                    .cast<AirmiusJson>())
                              DropdownMenuItem(
                                value: payment['id'] as int,
                                child: Text(
                                  '${c('existingPayment')} #${payment['id']} · ${payment['amount']} EUR · ${payment['paid_at']} · ${payment['reference'] ?? ''}',
                                  overflow: TextOverflow.ellipsis,
                                ),
                              ),
                          ],
                          validator: (value) =>
                              value == null ? c('paymentChoice') : null,
                          onChanged: (value) => paymentId = value,
                        ),
                      if (action == 'return' &&
                          item['settlement']?['payment_id'] == null)
                        Text(c('returnHelp')),
                      if (action != 'settle')
                        TextFormField(
                          controller: reason,
                          maxLength: 2000,
                          decoration: InputDecoration(
                            labelText: c(
                              action == 'return' ? 'returnReason' : 'reason',
                            ),
                          ),
                          validator: (value) =>
                              (value ?? '').trim().isEmpty ? c('reason') : null,
                        ),
                      if (action == 'return') ...[
                        TextFormField(
                          controller: fee,
                          keyboardType: const TextInputType.numberWithOptions(
                            decimal: true,
                          ),
                          decoration: InputDecoration(labelText: c('bankFee')),
                          validator: (value) {
                            final parsed = double.tryParse(
                              (value ?? '').replaceAll(',', '.'),
                            );
                            return parsed == null ||
                                    parsed < 0 ||
                                    parsed > 999999.99 ||
                                    !RegExp(
                                      r'^\d+([,.]\d{1,2})?$',
                                    ).hasMatch(value ?? '')
                                ? c('bankFee')
                                : null;
                          },
                        ),
                        Text(c('feeHelp')),
                      ],
                      CheckboxListTile(
                        value: confirmed,
                        title: Text(
                          c(action == 'retry' ? 'retryConfirm' : 'bankConfirm'),
                        ),
                        onChanged: (value) =>
                            change(() => confirmed = value == true),
                      ),
                    ],
                  ),
                ),
              ),
            ),
            actions: [
              TextButton(
                onPressed: () => Navigator.pop(context),
                child: Text(
                  MaterialLocalizations.of(context).cancelButtonLabel,
                ),
              ),
              FilledButton(
                onPressed: !confirmed
                    ? null
                    : () {
                        if (form.currentState!.validate()) {
                          Navigator.pop(context, <String, dynamic>{
                            'confirmed': true,
                            if (action != 'retry') ...{
                              'booked_on': formatAirmiusApiDate(
                                parseAirmiusDate(date.text),
                              ),
                              'reference': reference.text.trim(),
                            },
                            if (action == 'settle' &&
                                paymentId != null &&
                                paymentId! > 0)
                              'payment_id': paymentId,
                            if (action != 'settle')
                              'reason': reason.text.trim(),
                            if (action == 'return')
                              'fee_cents':
                                  (double.parse(fee.text.replaceAll(',', '.')) *
                                          100)
                                      .round(),
                          });
                        }
                      },
                child: Text(title),
              ),
            ],
          ),
        ),
      );
      if (data != null && mounted) {
        await _run(() async {
          await _client.recordClubSepaResult(
            widget.clubId,
            batch['id'] as int,
            item['id'] as int,
            action,
            data,
          );
        });
      }
    } finally {
      date.dispose();
      reference.dispose();
      reason.dispose();
      fee.dispose();
    }
  }

  Widget _bankResults(AirmiusJson batch, AirmiusJson item) {
    final result = item['settlement'] as AirmiusJson?;
    final fee = result?['fee_entry'] as AirmiusJson?;
    final labels =
        sepaFeeLabels[AirmiusScope.of(context).language.locale.languageCode] ??
        sepaFeeLabels['en']!;
    final userId = AirmiusServicesScope.of(context).authState.session?.user?.id;
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 8),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          if (result != null)
            Text(
              '${c('result_${result['status']}')} · ${result['settlement_reference'] ?? ''} · ${result['settled_on'] ?? ''}',
            ),
          if (result?['returned_on'] != null)
            Text(
              '${result!['returned_on']} · ${result['return_reference']} · ${result['return_reason']}',
            ),
          if ((result?['return_fee_cents'] as num? ?? 0) > 0)
            Text(
              '${c('bankFee')}: ${money(result!['return_fee_cents'])} · ${c('feeHelp')}',
            ),
          if (result?['retry_authorized_at'] != null) Text(c('retryAllowed')),
          if (fee != null)
            Text(
              '${labels['recorded']} #${fee['id']} · ${money((num.parse('${fee['amount']}') * 100).round())} · ${fee['booked_on'].toString().split('T').first} · ${fee['reference']}',
            ),
          if (fee != null) SepaFeeCorrectionSummary(result: result!),
          if (fee != null && feeRecharges(result!).isNotEmpty)
            SepaFeeRechargeSummary(result: result),
          if (fee != null &&
              _canManage &&
              _feeCorrectionsAvailable &&
              result?['status'] == 'returned')
            OutlinedButton(
              onPressed: _busy
                  ? null
                  : () async {
                      await Navigator.of(context).push<bool>(
                        MaterialPageRoute(
                          builder: (_) => ClubSepaFeeCorrectionScreen(
                            client: _client,
                            clubId: widget.clubId,
                            batchId: batch['id'] as int,
                            itemId: item['id'] as int,
                            result: result!,
                          ),
                        ),
                      );
                      // Also refresh after leaving an uncertain request using the back button.
                      if (mounted) await _run(() async {});
                    },
              child: Text(
                (sepaFeeCorrectionLabels[AirmiusScope.of(
                      context,
                    ).language.locale.languageCode] ??
                    sepaFeeCorrectionLabels['en']!)['title']!,
              ),
            ),
          if (fee != null &&
              _canManage &&
              (_feeRechargeDraftsAvailable || feeRecharges(result!).isNotEmpty))
            OutlinedButton(
              onPressed: _busy
                  ? null
                  : () async {
                      await Navigator.of(context).push<bool>(
                        MaterialPageRoute(
                          builder: (_) => ClubSepaFeeRechargeScreen(
                            client: _client,
                            clubId: widget.clubId,
                            batchId: batch['id'] as int,
                            itemId: item['id'] as int,
                            result: result!,
                            currentUserId: userId,
                            canManage: _canManage,
                            draftsAvailable: _feeRechargeDraftsAvailable,
                            approvalsAvailable: _feeRechargeApprovalsAvailable,
                            voidsAvailable: _feeRechargeVoidsAvailable,
                            creditsAvailable: _feeRechargeCreditsAvailable,
                          ),
                        ),
                      );
                      if (mounted) await _run(() async {});
                    },
              child: Text(
                (sepaFeeRechargeLabels[AirmiusScope.of(
                      context,
                    ).language.locale.languageCode] ??
                    sepaFeeRechargeLabels['en']!)['title']!,
              ),
            ),
          if (_canManage &&
              _feesAvailable &&
              result?['status'] == 'returned' &&
              fee == null)
            OutlinedButton(
              onPressed: _busy
                  ? null
                  : () async {
                      final saved = await Navigator.of(context).push<bool>(
                        MaterialPageRoute(
                          builder: (_) => ClubSepaFeeScreen(
                            client: _client,
                            clubId: widget.clubId,
                            batchId: batch['id'] as int,
                            itemId: item['id'] as int,
                          ),
                        ),
                      );
                      if (saved == true && mounted) {
                        await _run(() async {});
                      }
                    },
              child: Text(labels['title']!),
            ),
          if (_canManage)
            Wrap(
              spacing: 8,
              children: [
                if (result == null)
                  OutlinedButton(
                    onPressed: _busy
                        ? null
                        : () => _bankResult(batch, item, 'settle'),
                    child: Text(c('settleItem')),
                  ),
                if (result?['status'] != 'returned')
                  OutlinedButton(
                    onPressed: _busy
                        ? null
                        : () => _bankResult(batch, item, 'return'),
                    child: Text(c('returnItem')),
                  ),
                if (result?['status'] == 'returned' &&
                    result?['retry_authorized_at'] == null)
                  OutlinedButton(
                    onPressed: _busy || result?['returned_by'] == userId
                        ? null
                        : () => _bankResult(batch, item, 'retry'),
                    child: Text(c('retryItem')),
                  ),
              ],
            ),
          if (_canManage &&
              result?['status'] == 'returned' &&
              result?['retry_authorized_at'] == null &&
              result?['returned_by'] == userId)
            Text(c('secondPerson')),
        ],
      ),
    );
  }

  Future<void> _sendNotices(AirmiusJson batch) async {
    bool confirmed = false;
    final send = await _showDialog<bool>(
      context: context,
      builder: (context) => StatefulBuilder(
        builder: (context, change) => AlertDialog(
          title: Text(c('sendMessages')),
          content: SingleChildScrollView(
            child: CheckboxListTile(
              value: confirmed,
              title: Text(c('sendConfirm')),
              onChanged: (value) => change(() => confirmed = value == true),
            ),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(context, false),
              child: Text(MaterialLocalizations.of(context).cancelButtonLabel),
            ),
            FilledButton(
              onPressed: confirmed ? () => Navigator.pop(context, true) : null,
              child: Text(c('sendMessages')),
            ),
          ],
        ),
      ),
    );
    if (send == true && mounted) {
      await _run(() async {
        await _client.updateClubSepaBatch(
          widget.clubId,
          batch['id'] as int,
          'send_notices',
          {'confirmed': true},
        );
      });
    }
  }

  Widget _noticeMessages(AirmiusJson batch) {
    final notices = (batch['notices'] as List? ?? const []).cast<AirmiusJson>();
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        if (batch['status'] == 'approved' && notices.isEmpty)
          OutlinedButton(
            onPressed: _busy
                ? null
                : () => _run(() async {
                    await _client.updateClubSepaBatch(
                      widget.clubId,
                      batch['id'] as int,
                      'prepare_notices',
                      {},
                    );
                  }),
            child: Text(c('prepareMessages')),
          ),
        if (notices.isNotEmpty)
          ExpansionTile(
            title: Text(c('noticePreview')),
            children: [
              for (final notice in notices)
                Padding(
                  padding: const EdgeInsets.all(8),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        '${notice['content']?['email'] ?? ''} · ${c('mail_${notice['status']}')}',
                      ),
                      if (notice['sent_at'] != null)
                        Text(
                          '${notice['sent_at']} · ${notice['message_id'] ?? ''}',
                        ),
                      if (notice['provider_status'] != null)
                        Text(
                          '${c('provider_${notice['provider_status']}')}'
                          '${notice['provider_status'] == 'bounced' && notice['bounce_type'] != null ? ' · ${notice['bounce_type']}' : ''}',
                          style: notice['provider_status'] == 'bounced'
                              ? TextStyle(color: Theme.of(context).colorScheme.error)
                              : null,
                        ),
                      if (notice['error_code'] != null)
                        Text(c(notice['error_code'] as String)),
                      Text(
                        '${notice['content']?['subject'] ?? ''}',
                        style: Theme.of(context).textTheme.titleSmall,
                      ),
                      Text('${notice['content']?['body'] ?? ''}'),
                    ],
                  ),
                ),
            ],
          ),
        if (notices.isNotEmpty) Text(c('sendNoticeHelp')),
        if (batch['status'] == 'approved' &&
            notices.any(
              (notice) =>
                  ['prepared', 'queued', 'blocked'].contains(notice['status']),
            ))
          OutlinedButton(
            onPressed: _busy ? null : () => _sendNotices(batch),
            child: Text(c('sendMessages')),
          ),
      ],
    );
  }

  Future<void> _notice(AirmiusJson batch) async {
    final date = TextEditingController(), reference = TextEditingController();
    final form = GlobalKey<FormState>();
    bool confirmed = false;
    String channel = 'email';
    try {
      final result = await _showDialog<AirmiusJson>(
        context: context,
        builder: (context) => StatefulBuilder(
          builder: (context, change) => AlertDialog(
            title: Text(c('record')),
            content: SizedBox(
              width: 440,
              child: SingleChildScrollView(
                child: Form(
                  key: form,
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Text(c('noticeHelp')),
                      TextFormField(
                        controller: date,
                        keyboardType: TextInputType.datetime,
                        inputFormatters: const [AirmiusDateInputFormatter()],
                        decoration: InputDecoration(
                          labelText: c('sentOn'),
                          hintText: 'DD.MM.YYYY',
                        ),
                        validator: (v) => parseAirmiusDate(v) == null
                            ? c('sentOn')
                            : null,
                      ),
                      DropdownButtonFormField<String>(
                        initialValue: channel,
                        decoration: InputDecoration(labelText: c('channel')),
                        items: ['email', 'letter', 'portal']
                            .map(
                              (s) =>
                                  DropdownMenuItem(value: s, child: Text(c(s))),
                            )
                            .toList(),
                        onChanged: (v) => channel = v!,
                      ),
                      TextFormField(
                        controller: reference,
                        maxLength: 2000,
                        decoration: InputDecoration(labelText: c('evidence')),
                        validator: (v) =>
                            (v ?? '').trim().isEmpty ? c('evidence') : null,
                      ),
                      CheckboxListTile(
                        value: confirmed,
                        title: Text(c('confirm')),
                        onChanged: (v) => change(() => confirmed = v == true),
                      ),
                    ],
                  ),
                ),
              ),
            ),
            actions: [
              TextButton(
                onPressed: () => Navigator.pop(context),
                child: Text(
                  MaterialLocalizations.of(context).cancelButtonLabel,
                ),
              ),
              FilledButton(
                onPressed: !confirmed
                    ? null
                    : () {
                        if (form.currentState!.validate()) {
                          Navigator.pop(context, <String, dynamic>{
                            'sent_on': formatAirmiusApiDate(
                              parseAirmiusDate(date.text),
                            ),
                            'channel': channel,
                            'reference': reference.text,
                            'confirmed': true,
                          });
                        }
                      },
                child: Text(c('record')),
              ),
            ],
          ),
        ),
      );
      if (result != null && mounted) {
        await _run(() async {
          await _client.updateClubSepaBatch(
            widget.clubId,
            batch['id'] as int,
            'notice',
            result,
          );
        });
      }
    } finally {
      date.dispose();
      reference.dispose();
    }
  }

  Future<void> _cancel(AirmiusJson batch) async {
    final reason = TextEditingController();
    try {
      final result = await _showDialog<String>(
        context: context,
        builder: (context) => AlertDialog(
          title: Text(c('cancel')),
          content: TextField(
            controller: reason,
            maxLength: 2000,
            decoration: InputDecoration(labelText: c('reason')),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(context),
              child: Text(MaterialLocalizations.of(context).cancelButtonLabel),
            ),
            FilledButton(
              onPressed: () {
                if (reason.text.trim().isNotEmpty) {
                  Navigator.pop(context, reason.text);
                }
              },
              child: Text(c('cancel')),
            ),
          ],
        ),
      );
      if (result != null && mounted) {
        await _run(() async {
          await _client.updateClubSepaBatch(
            widget.clubId,
            batch['id'] as int,
            'cancel',
            {'reason': result},
          );
        });
      }
    } finally {
      reason.dispose();
    }
  }

  Future<void> _export(AirmiusJson batch) => _run(() async {
    final xml = await _client.exportClubSepaBatch(
      widget.clubId,
      batch['id'] as int,
    );
    await FilePicker.platform.saveFile(
      fileName: '${batch['reference']}.xml',
      type: FileType.custom,
      allowedExtensions: ['xml'],
      bytes: Uint8List.fromList(utf8.encode(xml)),
    );
  });

  @override
  Widget build(BuildContext context) {
    final userId = AirmiusServicesScope.of(context).authState.session?.user?.id;
    return Scaffold(
      appBar: AppBar(
        title: Text(c('title')),
        actions: [
          IconButton(
            tooltip: c('refresh'),
            onPressed: _busy ? null : () => _run(() async {}),
            icon: const Icon(Icons.refresh),
          ),
        ],
      ),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Text(c('intro')),
          if (_busy) const LinearProgressIndicator(),
          if (_error != null)
            Semantics(
              liveRegion: true,
              child: Text(
                _error!,
                style: TextStyle(color: Theme.of(context).colorScheme.error),
              ),
            ),
          if (_canManage)
            FilledButton(
              onPressed: _busy ? null : _create,
              child: Text(c('prepare')),
            ),
          if (!_busy && _batches.isEmpty) Text(c('empty')),
          for (final batch in _batches)
            Card(
              child: Padding(
                padding: const EdgeInsets.all(12),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      '${batch['reference']} · ${c(batch['status'] as String)}',
                      style: Theme.of(context).textTheme.titleMedium,
                    ),
                    Text('${batch['creditor_name']} · ${batch['creditor_id']}'),
                    Text(
                      '${c('date')}: ${batch['collection_date']} · ${c('total')}: ${money(batch['total_cents'])}',
                    ),
                    for (final item
                        in (batch['items'] as List).cast<AirmiusJson>()) ...[
                      Text(
                        '${item['number']} · ${item['name']} · ${money(item['amount_cents'])} · ${item['mandate_reference']} · •••• ${item['iban_last4']}',
                      ),
                      if (_resultsAvailable && batch['status'] == 'exported')
                        _bankResults(batch, item),
                    ],
                    if (batch['status'] == 'draft' &&
                        batch['created_by'] == userId)
                      Text(c('secondPerson')),
                    if (batch['notice_sent_on'] != null)
                      Text(
                        '${c('sentOn')}: ${batch['notice_sent_on']} · ${batch['notice_reference']}',
                      ),
                    if (_canManage && _noticesAvailable) _noticeMessages(batch),
                    if (_canManage &&
                        _resultsAvailable &&
                        batch['status'] == 'exported')
                      OutlinedButton(
                        onPressed: _busy
                            ? null
                            : () async {
                                final imported = await Navigator.of(context)
                                    .push<bool>(
                                      MaterialPageRoute(
                                        builder: (_) => ClubSepaReturnImport(
                                          client: _client,
                                          clubId: widget.clubId,
                                          batchId: batch['id'] as int,
                                        ),
                                      ),
                                    );
                                if (imported == true && mounted) {
                                  await _run(() async {});
                                }
                              },
                        child: Text(
                          (sepaReturnLabels[AirmiusScope.of(
                                context,
                              ).language.locale.languageCode] ??
                              sepaReturnLabels['en']!)['title']!,
                        ),
                      ),
                    if (_canManage)
                      Wrap(
                        spacing: 8,
                        children: [
                          if (batch['status'] == 'draft')
                            OutlinedButton(
                              onPressed: _busy || batch['created_by'] == userId
                                  ? null
                                  : () => _run(() async {
                                      await _client.updateClubSepaBatch(
                                        widget.clubId,
                                        batch['id'] as int,
                                        'approve',
                                        {},
                                      );
                                    }),
                              child: Text(c('approve')),
                            ),
                          if (batch['status'] == 'approved')
                            OutlinedButton(
                              onPressed: _busy ? null : () => _notice(batch),
                              child: Text(c('record')),
                            ),
                          if ([
                            'notified',
                            'exported',
                          ].contains(batch['status']))
                            OutlinedButton(
                              onPressed: _busy ? null : () => _export(batch),
                              child: Text(c('export')),
                            ),
                          if ([
                            'draft',
                            'approved',
                            'notified',
                          ].contains(batch['status']))
                            TextButton(
                              onPressed: _busy ? null : () => _cancel(batch),
                              child: Text(c('cancel')),
                            ),
                        ],
                      ),
                  ],
                ),
              ),
            ),
          if (_more)
            TextButton(
              onPressed: _busy ? null : () => _run(() async {}, append: true),
              child: Text(c('more')),
            ),
        ],
      ),
    );
  }
}
