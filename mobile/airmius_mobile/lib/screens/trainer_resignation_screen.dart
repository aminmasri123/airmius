import 'package:flutter/material.dart';
import '../core/airmius_api_client.dart';
import '../core/airmius_services_scope.dart';

class TrainerResignationScreen extends StatefulWidget {
  const TrainerResignationScreen({super.key});
  @override
  State<TrainerResignationScreen> createState() => _TrainerResignationState();
}

class _TrainerResignationState extends State<TrainerResignationScreen> {
  Future<AirmiusJson>? _future;
  bool _busy = false, _confirming = false, _confirmed = false;
  Map? _selection;
  String? _error;
  AirmiusApiClient get _client {
    final s = AirmiusServicesScope.of(context);
    return s.clientForSession(s.authState.session);
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _future ??= _client.trainerResignationStatus();
  }

  Future<void> _submit() async {
    if (!_confirmed || _busy) return;
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      await _client.resignTrainer(teamId: _selection?['id'] as int?);
      if (!mounted) return;
      await AirmiusServicesScope.of(context).authState.refreshUser();
      if (!mounted) return;
      setState(() {
        _confirming = false;
        _confirmed = false;
        _future = _client.trainerResignationStatus();
      });
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Der Rücktritt wurde gespeichert.')),
      );
    } catch (e) {
      if (mounted) {
        setState(
          () => _error =
              'Der Rücktritt konnte nicht abgeschlossen werden. Bitte aktualisiere die Seite und versuche es erneut.',
        );
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  void _choose(Map? team) => setState(() {
    _selection = team;
    _confirming = true;
    _confirmed = false;
    _error = null;
  });
  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Trainerrollen verwalten')),
    body: FutureBuilder<AirmiusJson>(
      future: _future,
      builder: (context, snapshot) {
        if (snapshot.connectionState != ConnectionState.done) {
          return const Center(child: CircularProgressIndicator());
        }
        if (snapshot.hasError) {
          return Center(
            child: TextButton(
              onPressed: () =>
                  setState(() => _future = _client.trainerResignationStatus()),
              child: const Text('Erneut laden'),
            ),
          );
        }
        final data = snapshot.data ?? {};
        final teams = (data['teams'] as List? ?? []).whereType<Map>().toList();
        return ListView(
          padding: const EdgeInsets.all(20),
          children: [
            const Text(
              'Beim Team-Rücktritt bleibst du als Spieler Mitglied. Andere Teams und Rollen bleiben unverändert.',
            ),
            const SizedBox(height: 24),
            for (final team in teams)
              Card(
                child: Padding(
                  padding: const EdgeInsets.all(16),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      Text(
                        '${team['name']}',
                        style: Theme.of(context).textTheme.titleLarge,
                      ),
                      const SizedBox(height: 12),
                      OutlinedButton(
                        onPressed: _busy ? null : () => _choose(team),
                        child: const Text(
                          'Als Trainer dieses Teams zurücktreten',
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            if (data['has_role'] == true) ...[
              const SizedBox(height: 24),
              const Text(
                'Allgemeine Trainerrolle',
                style: TextStyle(fontWeight: FontWeight.bold),
              ),
              const SizedBox(height: 12),
              const Text(
                'Beende zuerst deine Trainerzuordnungen zu Teams. Dein Nutzerkonto bleibt erhalten.',
              ),
              const SizedBox(height: 12),
              OutlinedButton(
                onPressed: _busy || teams.isNotEmpty
                    ? null
                    : () => _choose(null),
                child: const Text('Trainerrolle aufgeben'),
              ),
            ],
            if (data['has_role'] != true && teams.isEmpty)
              const Text(
                'Du hast keine aktive Trainerrolle oder Trainerzuordnung.',
              ),
            if (_confirming) ...[
              const SizedBox(height: 24),
              Text(
                _selection == null
                    ? 'Trainerrolle aufgeben bestätigen'
                    : 'Rücktritt bei ${_selection!['name']} bestätigen',
                style: Theme.of(context).textTheme.titleLarge,
              ),
              const SizedBox(height: 16),
              Text(
                _selection == null
                    ? 'Die allgemeine Trainerrolle wird entfernt. Offene Traineranträge werden zurückgezogen.'
                    : 'Deine Trainerrechte in diesem Team entfallen. Du bleibst als Spieler Mitglied.',
              ),
              const SizedBox(height: 12),
              const Text(
                'Du und die zuständige Verwaltung erhalten eine Benachrichtigung.',
              ),
              CheckboxListTile(
                contentPadding: EdgeInsets.zero,
                value: _confirmed,
                onChanged: _busy
                    ? null
                    : (value) => setState(() => _confirmed = value ?? false),
                title: const Text('Ich möchte diesen Rücktritt bestätigen.'),
              ),
              if (_error != null)
                Text(
                  _error!,
                  style: TextStyle(color: Theme.of(context).colorScheme.error),
                ),
              FilledButton(
                onPressed: _busy || !_confirmed ? null : _submit,
                child: Text(
                  _busy ? 'Wird gespeichert …' : 'Verbindlich bestätigen',
                ),
              ),
              TextButton(
                onPressed: _busy
                    ? null
                    : () => setState(() => _confirming = false),
                child: const Text('Abbrechen'),
              ),
            ],
          ],
        );
      },
    ),
  );
}
