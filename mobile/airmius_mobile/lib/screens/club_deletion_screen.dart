import 'dart:typed_data';

import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'package:intl/intl.dart';

import '../core/airmius_api_client.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';
import 'deletion_guide_screen.dart';
import 'support_helpdesk_screen.dart';

class ClubDeletionScreen extends StatefulWidget {
  const ClubDeletionScreen({
    super.key,
    required this.clubId,
    required this.clubName,
  });

  final int clubId;
  final String clubName;

  @override
  State<ClubDeletionScreen> createState() => _ClubDeletionScreenState();
}

class _ClubDeletionScreenState extends State<ClubDeletionScreen> {
  final _identity = TextEditingController();
  final _code = TextEditingController();
  final _confirmation = TextEditingController();
  Map<String, dynamic>? _status;
  String? _error;
  bool _busy = false;
  bool _loading = true;
  bool _codeSent = false;
  bool _archiveConfirmed = false;

  AirmiusApiClient get _client {
    final services = AirmiusServicesScope.of(context);
    return services.clientForSession(services.authState.session);
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (_loading && _status == null && _error == null) _load();
  }

  Future<void> _load() async {
    try {
      final result = await _client.clubDeletionStatus(widget.clubId);
      if (!mounted) return;
      setState(() {
        _status = Map<String, dynamic>.from(result['data'] as Map);
        _loading = false;
        _error = null;
      });
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _loading = false;
        _error = error is AirmiusApiException
            ? error.userMessage
            : AirmiusScope.of(context).t('clubHub.loadFailed');
      });
    }
  }

  Future<void> _submit(bool cancel) async {
    if (_busy) return;
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      final result = cancel
          ? await _client.cancelClubDeletion(widget.clubId)
          : await _client.deleteClub(
              widget.clubId,
              confirmation: _confirmation.text.trim(),
              code: _code.text.trim(),
            );
      if (!mounted) return;
      setState(() {
        _status = Map<String, dynamic>.from(result['data'] as Map);
        _confirmation.clear();
        _code.clear();
        _codeSent = false;
      });
      if (cancel) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(AirmiusScope.of(context).t('clubDeletion.cancelled')),
          ),
        );
      }
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _error = error is AirmiusApiException
            ? error.userMessage
            : AirmiusScope.of(context).t('clubs.deleteFailed');
      });
    } finally {
      if (mounted) {
        setState(() {
          _busy = false;
        });
      }
    }
  }

  Future<void> _sendCode() async {
    if (_busy) return;
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      await _client.requestClubDeletionCode(
        widget.clubId,
        _identity.text.trim(),
      );
      if (!mounted) return;
      _identity.clear();
      _code.clear();
      setState(() => _codeSent = true);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(AirmiusScope.of(context).t('clubDeletion.codeSent')),
        ),
      );
    } catch (error) {
      if (!mounted) return;
      setState(
        () => _error = error is AirmiusApiException
            ? error.userMessage
            : AirmiusScope.of(context).t('clubs.deleteFailed'),
      );
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _requestReview() async {
    if (_busy) return;
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      final result = await _client.requestClubDeletionReview(widget.clubId);
      if (!mounted) return;
      setState(
        () => _status = Map<String, dynamic>.from(result['data'] as Map),
      );
    } catch (error) {
      if (!mounted) return;
      setState(
        () => _error = error is AirmiusApiException
            ? error.userMessage
            : AirmiusScope.of(context).t('clubs.deleteFailed'),
      );
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _downloadFinancialExport() async {
    if (_busy) return;
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      final services = AirmiusServicesScope.of(context);
      final base = Uri.parse(services.environment.apiBaseUrl);
      final prefix = base.path.endsWith('/') ? base.path : '${base.path}/';
      final path = 'api/v1/clubs/${widget.clubId}/deletion-financial-export';
      final uri = base.replace(
        path: '$prefix$path',
        query: null,
        fragment: null,
      );
      final response = await http.get(
        uri,
        headers: {
          'Accept': 'application/zip',
          'Authorization': 'Bearer ${services.authState.session!.token}',
        },
      );
      if (response.statusCode < 200 || response.statusCode >= 300) {
        throw AirmiusApiException(
          statusCode: response.statusCode,
          body: response.body,
          path: '/$path',
        );
      }
      final saved = await FilePicker.platform.saveFile(
        fileName: 'airmius-verein-${widget.clubId}-finanzdaten.zip',
        type: FileType.custom,
        allowedExtensions: const ['zip'],
        bytes: Uint8List.fromList(response.bodyBytes),
      );
      if (saved == null || !mounted) return;
      await _load();
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            response.headers['x-airmius-export-complete'] == 'false'
                ? AirmiusScope.of(context).t('clubDeletion.exportIncomplete')
                : AirmiusScope.of(context).t('clubDeletion.exportSaved'),
          ),
        ),
      );
    } catch (error) {
      if (mounted) {
        setState(
          () => _error = error is AirmiusApiException
              ? error.userMessage
              : AirmiusScope.of(context).t('clubs.deleteFailed'),
        );
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _confirmArchive() async {
    if (_busy || !_archiveConfirmed) return;
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      final result = await _client.confirmClubDeletionArchive(widget.clubId);
      if (mounted) {
        setState(
          () => _status = Map<String, dynamic>.from(result['data'] as Map),
        );
      }
    } catch (error) {
      if (mounted) {
        setState(
          () => _error = error is AirmiusApiException
              ? error.userMessage
              : AirmiusScope.of(context).t('clubs.deleteFailed'),
        );
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  void dispose() {
    _identity.dispose();
    _code.dispose();
    _confirmation.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final scope = AirmiusScope.of(context);
    final t = scope.t;
    final scheme = Theme.of(context).colorScheme;
    final scheduled = DateTime.tryParse('${_status?['scheduled_at'] ?? ''}');
    final phrase = '${_status?['confirmation'] ?? ''}';
    final blocker = _status?['blocker'] as String?;
    return PopScope(
      canPop: !_busy,
      child: Scaffold(
        appBar: AppBar(title: Text(t('clubDeletion.title'))),
        body: _loading
            ? const Center(child: CircularProgressIndicator())
            : Align(
                alignment: Alignment.topCenter,
                child: ConstrainedBox(
                  constraints: const BoxConstraints(maxWidth: 640),
                  child: ListView(
                    padding: const EdgeInsets.all(24),
                    children: [
                      Text(
                        widget.clubName,
                        style: Theme.of(context).textTheme.headlineSmall,
                      ),
                      const SizedBox(height: 20),
                      Text(t('clubDeletion.warning')),
                      const SizedBox(height: 16),
                      Text(t('clubDeletion.data')),
                      const SizedBox(height: 16),
                      Text(t('clubDeletion.notice')),
                      TextButton.icon(
                        onPressed: () => Navigator.of(context).push(
                          MaterialPageRoute<void>(
                            builder: (_) => const DeletionGuideScreen(
                              kind: DeletionGuideKind.club,
                            ),
                          ),
                        ),
                        icon: const Icon(Icons.info_outline),
                        label: Text(t('clubDeletion.guide')),
                      ),
                      const SizedBox(height: 24),
                      if (_status?['has_financial_records'] == true) ...[
                        Text(t('clubDeletion.archiveNotice')),
                        const SizedBox(height: 12),
                        OutlinedButton.icon(
                          onPressed: _busy ? null : _downloadFinancialExport,
                          icon: const Icon(Icons.download_outlined),
                          label: Text(t('clubDeletion.downloadFinance')),
                        ),
                        if (_status?['financial_exported_at'] != null)
                          Text(t('clubDeletion.exportReady')),
                        if (_status?['financial_exported_at'] != null &&
                            _status?['archive_confirmed_at'] == null) ...[
                          CheckboxListTile(
                            contentPadding: EdgeInsets.zero,
                            value: _archiveConfirmed,
                            onChanged: _busy
                                ? null
                                : (value) => setState(
                                    () => _archiveConfirmed = value ?? false,
                                  ),
                            title: Text(t('clubDeletion.archiveConfirm')),
                          ),
                          OutlinedButton.icon(
                            onPressed: _busy || !_archiveConfirmed
                                ? null
                                : _confirmArchive,
                            icon: const Icon(Icons.verified_outlined),
                            label: Text(t('clubDeletion.confirmArchive')),
                          ),
                        ],
                        if (_status?['archive_confirmed_at'] != null)
                          Text(t('clubDeletion.archiveConfirmed')),
                        if (_status?['financial_approved_at'] != null)
                          Text(t('clubDeletion.financeApproved')),
                        const SizedBox(height: 16),
                      ],
                      if (_status?['blocked'] == true) ...[
                        Text(
                          t('clubDeletion.blocked'),
                          style: TextStyle(color: scheme.error),
                        ),
                        const SizedBox(height: 16),
                      ],
                      if (blocker != null) ...[
                        Text(blocker, style: TextStyle(color: scheme.error)),
                        if (_status?['review_ticket_id'] != null)
                          Text(t('clubDeletion.reviewRequested'))
                        else
                          OutlinedButton.icon(
                            onPressed: _busy ? null : _requestReview,
                            icon: const Icon(Icons.mark_email_read_outlined),
                            label: Text(t('clubDeletion.requestReview')),
                          ),
                        TextButton.icon(
                          onPressed: () => Navigator.of(context).push(
                            MaterialPageRoute<void>(
                              builder: (_) => const SupportHelpdeskScreen(),
                            ),
                          ),
                          icon: const Icon(Icons.support_agent_outlined),
                          label: Text(t('clubDeletion.supportReview')),
                        ),
                        const SizedBox(height: 16),
                      ],
                      if (_error != null) ...[
                        Text(_error!, style: TextStyle(color: scheme.error)),
                        const SizedBox(height: 12),
                        if (_status == null)
                          TextButton.icon(
                            onPressed: _load,
                            icon: const Icon(Icons.refresh),
                            label: Text(t('common.refresh')),
                          ),
                      ],
                      if (scheduled != null) ...[
                        Text(
                          t('clubDeletion.pending'),
                          style: Theme.of(context).textTheme.titleLarge,
                        ),
                        const SizedBox(height: 8),
                        Text(
                          '${t('clubDeletion.date')}: ${DateFormat.yMMMd(scope.language.locale.toLanguageTag()).add_Hm().format(scheduled.toLocal())}',
                        ),
                        const SizedBox(height: 24),
                        FilledButton.icon(
                          onPressed: _busy ? null : () => _submit(true),
                          icon: const Icon(Icons.undo),
                          label: Text(
                            t('clubDeletion.cancel'),
                            textAlign: TextAlign.center,
                          ),
                        ),
                      ] else if (_status != null &&
                          _status?['can_request'] == true) ...[
                        TextField(
                          controller: _identity,
                          enabled: !_busy,
                          obscureText: _status?['requires_password'] == true,
                          keyboardType: _status?['requires_password'] == true
                              ? TextInputType.text
                              : TextInputType.emailAddress,
                          decoration: InputDecoration(
                            labelText: t(
                              _status?['requires_password'] == true
                                  ? 'clubDeletion.password'
                                  : 'clubDeletion.email',
                            ),
                            border: const OutlineInputBorder(),
                          ),
                          onChanged: (_) => setState(() {}),
                        ),
                        const SizedBox(height: 12),
                        OutlinedButton.icon(
                          onPressed: _busy || _identity.text.trim().isEmpty
                              ? null
                              : _sendCode,
                          icon: const Icon(Icons.mail_outline),
                          label: Text(t('clubDeletion.sendCode')),
                        ),
                        if (_codeSent) ...[
                          const SizedBox(height: 16),
                          TextField(
                            controller: _code,
                            enabled: !_busy,
                            keyboardType: TextInputType.number,
                            autofillHints: const [AutofillHints.oneTimeCode],
                            maxLength: 6,
                            decoration: InputDecoration(
                              labelText: t('clubDeletion.code'),
                              border: const OutlineInputBorder(),
                            ),
                            onChanged: (_) => setState(() {}),
                          ),
                          const SizedBox(height: 16),
                          Text(t('clubDeletion.phrase')),
                          const SizedBox(height: 8),
                          Text(
                            phrase,
                            style: const TextStyle(fontWeight: FontWeight.bold),
                          ),
                          const SizedBox(height: 16),
                          TextField(
                            controller: _confirmation,
                            enabled: !_busy,
                            autocorrect: false,
                            enableSuggestions: false,
                            decoration: InputDecoration(
                              labelText: t('clubDeletion.confirm'),
                              border: const OutlineInputBorder(),
                            ),
                            onChanged: (_) => setState(() {}),
                          ),
                          const SizedBox(height: 24),
                          FilledButton.icon(
                            style: FilledButton.styleFrom(
                              backgroundColor: scheme.error,
                              foregroundColor: scheme.onError,
                            ),
                            onPressed:
                                _busy ||
                                    _confirmation.text.trim() != phrase ||
                                    !RegExp(
                                      r'^\d{6}$',
                                    ).hasMatch(_code.text.trim())
                                ? null
                                : () => _submit(false),
                            icon: const Icon(Icons.delete_outline),
                            label: Text(
                              t('clubDeletion.request'),
                              textAlign: TextAlign.center,
                            ),
                          ),
                        ],
                      ],
                      if (_busy)
                        const Padding(
                          padding: EdgeInsets.all(16),
                          child: Center(child: CircularProgressIndicator()),
                        ),
                    ],
                  ),
                ),
              ),
      ),
    );
  }
}
