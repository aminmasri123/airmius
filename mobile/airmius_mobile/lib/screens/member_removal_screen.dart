import 'package:flutter/material.dart';
import '../core/airmius_services_scope.dart';

class MemberRemovalScreen extends StatefulWidget {
  const MemberRemovalScreen({super.key, this.removalId, this.notificationId})
    : assert(removalId != null || notificationId != null);
  final int? removalId;
  final int? notificationId;
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
      final client = services.clientForSession(services.authState.session);
      final result = widget.removalId != null
          ? await client.memberRemoval(widget.removalId!)
          : await client.legacyMemberRemoval(widget.notificationId!);
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
      final client = services.clientForSession(services.authState.session);
      if (widget.removalId != null) {
        await client.respondToMemberRemoval(
          widget.removalId!,
          decision,
          _message.text,
        );
      } else {
        await client.legacyMemberRemoval(
          widget.notificationId!,
          message: _message.text,
        );
      }
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
        : '${date.day.toString().padLeft(2, '0')}.${date.month.toString().padLeft(2, '0')}.${date.year}, ${date.hour.toString().padLeft(2, '0')}:${date.minute.toString().padLeft(2, '0')} Uhr';
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
              '${data['club_name'] ?? 'Verein'}',
              style: Theme.of(context).textTheme.headlineSmall,
            ),
            const SizedBox(height: 12),
            Text(
              data['status'] == 'pending'
                  ? 'Entfernung angekündigt'
                  : data['status'] == 'objected'
                  ? 'Entfernung gestoppt – Prüfung ausstehend'
                  : 'Dein Entfernungsvorgang',
              style: Theme.of(context).textTheme.titleLarge,
            ),
            const SizedBox(height: 24),
            const Text('Begründung des Vereins'),
            const SizedBox(height: 8),
            Text(
              '${data['reason']}',
              style: Theme.of(context).textTheme.titleMedium,
            ),
            const SizedBox(height: 24),
            if (data['status'] == 'pending' ||
                data['status'] == 'legacy_removed') ...[
              Text(
                data['status'] == 'pending'
                    ? 'Ohne Reaktion wird deine Vereinszuordnung am $deadline entfernt. Ein Widerspruch stoppt die automatische Entfernung.'
                    : 'Diese ältere Meldung betrifft eine bereits erfolgte Entfernung. Du kannst eine Prüfung beantragen. Dadurch wird deine Mitgliedschaft nicht automatisch wiederhergestellt.',
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
              if (data['status'] == 'pending')
                OutlinedButton(
                  onPressed: _busy ? null : () => _respond('confirm'),
                  child: const Text('Entfernung bestätigen'),
                ),
            ] else
              Text(switch (data['status']) {
                'legacy_objected' =>
                  'Dein Widerspruch wurde an den Verein gesendet. Die Prüfung steht aus.',
                'legacy_superseded' =>
                  'Diese ältere Meldung ist nicht mehr aktuell. Bitte öffne deinen aktuellen Mitgliedschaftsvorgang.',
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
