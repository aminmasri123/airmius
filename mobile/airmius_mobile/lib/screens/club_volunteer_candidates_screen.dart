import 'package:flutter/material.dart';

import '../core/airmius_api_models.dart';
import '../core/airmius_services_scope.dart';

class ClubVolunteerCandidatesScreen extends StatefulWidget {
  const ClubVolunteerCandidatesScreen({super.key, required this.clubId});

  final int clubId;

  @override
  State<ClubVolunteerCandidatesScreen> createState() => _ClubVolunteerCandidatesScreenState();
}

class _ClubVolunteerCandidatesScreenState extends State<ClubVolunteerCandidatesScreen> {
  late Future<List<JsonMap>> _candidates;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _candidates = _load();
  }

  Future<List<JsonMap>> _load() async {
    final services = AirmiusServicesScope.of(context);
    final result = await services.clientForSession(services.authState.session)
        .clubVolunteerCandidates(widget.clubId);
    return (result['data'] as List? ?? const []).whereType<JsonMap>().toList();
  }

  List<String> _strings(dynamic value) =>
      (value as List? ?? const []).map((item) => item.toString()).toList();

  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('Ehrenamtskandidaten')),
        body: FutureBuilder<List<JsonMap>>(
          future: _candidates,
          builder: (context, snapshot) {
            if (snapshot.hasError) return const Center(child: Text('Kandidaten konnten nicht geladen werden.'));
            if (!snapshot.hasData) return const Center(child: CircularProgressIndicator());
            final candidates = snapshot.data!;
            if (candidates.isEmpty) return const Center(child: Text('Noch keine freigegebenen Ehrenamtsprofile.'));
            return ListView.builder(
              padding: const EdgeInsets.all(16),
              itemCount: candidates.length,
              itemBuilder: (context, index) {
                final candidate = candidates[index];
                return ListTile(
                  leading: const Icon(Icons.volunteer_activism_outlined),
                  title: Text(candidate['name']?.toString() ?? 'Mitglied'),
                  subtitle: Text(_strings(candidate['skills']).join(', ')),
                  trailing: const Icon(Icons.chevron_right),
                  onTap: () => Navigator.push(context, MaterialPageRoute<void>(
                    builder: (_) => _CandidateProfile(candidate: candidate),
                  )),
                );
              },
            );
          },
        ),
      );
}

class _CandidateProfile extends StatelessWidget {
  const _CandidateProfile({required this.candidate});

  final JsonMap candidate;

  @override
  Widget build(BuildContext context) {
    final areas = (candidate['preferred_areas'] as List? ?? const []).map((value) => switch (value) {
      'all_teams' => 'Alle Teams',
      'organization' => 'Organisation',
      'administration' => 'Verwaltung',
      _ => value.toString(),
    });
    final teams = (candidate['preferred_teams'] as List? ?? const [])
        .whereType<JsonMap>().map((team) => team['name'].toString());
    return Scaffold(
      appBar: AppBar(title: Text(candidate['name']?.toString() ?? 'Ehrenamtsprofil')),
      body: ListView(padding: const EdgeInsets.all(16), children: [
        _row('Verfügbarkeit', candidate['availability']),
        _row('Fähigkeiten', candidate['skills']),
        _row('Interessen', candidate['interests']),
        _row('Wunschbereiche', [...areas, ...teams]),
        if (candidate['workload_limit_minutes_per_week'] != null)
          ListTile(title: const Text('Maximaler Zeitaufwand'), subtitle: Text('${candidate['workload_limit_minutes_per_week']} Minuten pro Woche')),
      ]),
    );
  }

  Widget _row(String title, dynamic values) {
    final label = (values as List? ?? const []).join(', ');
    return ListTile(title: Text(title), subtitle: Text(label.isEmpty ? '–' : label));
  }
}
