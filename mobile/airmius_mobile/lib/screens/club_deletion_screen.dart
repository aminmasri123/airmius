import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../core/airmius_api_client.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';

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
  final _confirmation = TextEditingController();
  Map<String, dynamic>? _status;
  String? _error;
  bool _busy = false;
  bool _loading = true;

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
            );
      if (!mounted) return;
      setState(() {
        _status = Map<String, dynamic>.from(result['data'] as Map);
        _confirmation.clear();
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

  @override
  void dispose() {
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
                      const SizedBox(height: 24),
                      if (_status?['blocked'] == true) ...[
                        Text(
                          t('clubDeletion.blocked'),
                          style: TextStyle(color: scheme.error),
                        ),
                        const SizedBox(height: 16),
                      ],
                      if (blocker != null) ...[
                        Text(blocker, style: TextStyle(color: scheme.error)),
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
                      ] else if (_status != null && blocker == null) ...[
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
                              _busy || _confirmation.text.trim() != phrase
                              ? null
                              : () => _submit(false),
                          icon: const Icon(Icons.delete_outline),
                          label: Text(
                            t('clubDeletion.request'),
                            textAlign: TextAlign.center,
                          ),
                        ),
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
