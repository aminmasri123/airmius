import 'package:flutter/material.dart';
import '../core/airmius_services_scope.dart';

class MemberRemovalScreen extends StatefulWidget {
  const MemberRemovalScreen({super.key, required this.removalId});
  final int removalId;
  @override
  State<MemberRemovalScreen> createState() => _MemberRemovalScreenState();
}

class _MemberRemovalScreenState extends State<MemberRemovalScreen> {
  final _message = TextEditingController();
  Map<String, dynamic>? _data;
  bool _busy = false;
  String? _error;
  bool _started = false;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (!_started) {
      _started = true;
      _load();
    }
  }

  @override
  void dispose() {
    _message.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    try {
      final services = AirmiusServicesScope.of(context);
      final result = await services
          .clientForSession(services.authState.session)
          .memberRemoval(widget.removalId);
      if (mounted) {
        setState(() {
          _data = Map<String, dynamic>.from(result['data'] as Map);
          _error = null;
        });
      }
    } catch (_) {
      if (mounted) {
        setState(
          () => _error =
              'Der Vorgang konnte nicht geladen werden. Bitte erneut versuchen.',
        );
      }
    }
  }

  Future<void> _respond(String decision) async {
    if (_busy) return;
    if (decision == 'confirm') {
      final confirmed = await showDialog<bool>(
        context: context,
        builder: (context) => AlertDialog(
          title: const Text('Entfernung bestätigen?'),
          content: const Text(
            'Deine Vereinszuordnung wird jetzt endgültig entfernt.',
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(context, false),
              child: const Text('Abbrechen'),
            ),
            TextButton(
              onPressed: () => Navigator.pop(context, true),
              child: const Text('Bestätigen'),
            ),
          ],
        ),
      );
      if (confirmed != true || !mounted) return;
    }
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      final services = AirmiusServicesScope.of(context);
      await services
          .clientForSession(services.authState.session)
          .respondToMemberRemoval(widget.removalId, decision, _message.text);
      await _load();
    } catch (_) {
      if (mounted) {
        setState(
          () => _error =
              'Die Antwort konnte nicht gespeichert werden. Bitte erneut versuchen.',
        );
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final data = _data;
    final date = DateTime.tryParse('${data?['expires_at']}')?.toLocal();
    final deadline = date == null
        ? ''
        : '${date.day.toString().padLeft(2, '0')}.${date.month.toString().padLeft(2, '0')}.${date.year}';
    return Scaffold(
      appBar: AppBar(title: const Text('Entfernung aus dem Verein')),
      body: ListView(
        padding: const EdgeInsets.all(24),
        children: [
          if (_error != null) ...[
            Text(_error!, semanticsLabel: _error),
            TextButton(
              onPressed: _busy ? null : _load,
              child: const Text('Erneut laden'),
            ),
          ],
          if (data == null && _error == null)
            const Center(child: CircularProgressIndicator()),
          if (data != null) ...[
            Text(
              '${data['reason']}',
              style: Theme.of(context).textTheme.titleMedium,
            ),
            const SizedBox(height: 24),
            if (data['status'] == 'pending') ...[
              Text(
                'Ohne Reaktion wird deine Vereinszuordnung am $deadline entfernt. Ein Widerspruch stoppt die automatische Entfernung.',
              ),
              const SizedBox(height: 24),
              TextField(
                controller: _message,
                maxLength: 2000,
                maxLines: 4,
                decoration: const InputDecoration(
                  labelText: 'Begründung (optional)',
                  border: OutlineInputBorder(),
                ),
              ),
              const SizedBox(height: 16),
              FilledButton(
                onPressed: _busy ? null : () => _respond('object'),
                child: const Text('Widersprechen'),
              ),
              const SizedBox(height: 12),
              OutlinedButton(
                onPressed: _busy ? null : () => _respond('confirm'),
                child: const Text('Entfernung bestätigen'),
              ),
            ] else
              Text(switch (data['status']) {
                'objected' =>
                  'Dein Widerspruch wurde erfasst. Die automatische Entfernung ist gestoppt.',
                'cancelled' => 'Die Entfernung wurde zurückgenommen.',
                _ => 'Die Vereinszuordnung wurde beendet.',
              }),
          ],
        ],
      ),
    );
  }
}
