import 'package:flutter/material.dart';

import '../core/airmius_api_client.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';
import '../core/challenge_input.dart';
import '../core/challenge_feedback.dart';

class ChallengesScreen extends StatefulWidget {
  const ChallengesScreen({super.key});

  @override
  State<ChallengesScreen> createState() => _ChallengesScreenState();
}

class _ChallengesScreenState extends State<ChallengesScreen> {
  Future<AirmiusJson>? _future;
  String _filter = '';

  AirmiusApiClient get _client {
    final services = AirmiusServicesScope.of(context);
    return services.clientForSession(services.authState.session);
  }

  String _c(String de, String en, String fr, String ar) =>
      switch (AirmiusScope.of(context).language) {
        AirmiusLanguage.de => de,
        AirmiusLanguage.en => en,
        AirmiusLanguage.fr => fr,
        AirmiusLanguage.ar => ar,
      };

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _future ??= _client.challenges(status: _filter.isEmpty ? null : _filter);
  }

  void _reload() {
    setState(() {
      _future = _client.challenges(status: _filter.isEmpty ? null : _filter);
    });
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(
      title: Text(
        _c('Challenges', 'Challenges', 'Défis', 'التحديات'),
        style: const TextStyle(fontWeight: FontWeight.w900),
      ),
      actions: [
        IconButton(
          tooltip: _c('Aktualisieren', 'Refresh', 'Actualiser', 'تحديث'),
          onPressed: _reload,
          icon: const Icon(Icons.refresh),
        ),
      ],
    ),
    floatingActionButton: FloatingActionButton.extended(
      onPressed: _openCreate,
      icon: const Icon(Icons.add),
      label: Text(_c('Erstellen', 'Create', 'Créer', 'إنشاء')),
    ),
    body: FutureBuilder<AirmiusJson>(
      future: _future,
      builder: (context, snapshot) {
        if (snapshot.connectionState == ConnectionState.waiting) {
          return const Center(child: CircularProgressIndicator());
        }
        if (snapshot.hasError) {
          return _ErrorState(error: snapshot.error, onRetry: _reload);
        }
        final response = snapshot.data ?? const <String, dynamic>{};
        final challenges = _maps(response['data']);
        final meta = response['meta'] is AirmiusJson
            ? response['meta'] as AirmiusJson
            : <String, dynamic>{};
        return RefreshIndicator(
          onRefresh: () async => _reload(),
          child: ListView(
            padding: const EdgeInsets.fromLTRB(16, 16, 16, 100),
            children: [
              _HeroCard(
                title: _c(
                  'Gemeinsam dranbleiben',
                  'Stay consistent together',
                  'Restez motivés ensemble',
                  'استمروا معًا',
                ),
                subtitle: _c(
                  'Tägliche und wöchentliche Ziele mit Freunden, Teams und Vereinen.',
                  'Daily and weekly goals with friends, teams and clubs.',
                  'Des objectifs quotidiens et hebdomadaires entre amis, équipes et clubs.',
                  'أهداف يومية وأسبوعية مع الأصدقاء والفرق والأندية.',
                ),
              ),
              const SizedBox(height: 16),
              SegmentedButton<String>(
                expandedInsets: EdgeInsets.zero,
                showSelectedIcon: false,
                style: ButtonStyle(
                  visualDensity: VisualDensity.compact,
                  padding: const WidgetStatePropertyAll(
                    EdgeInsets.symmetric(horizontal: 4, vertical: 12),
                  ),
                  textStyle: const WidgetStatePropertyAll(
                    TextStyle(fontSize: 12, fontWeight: FontWeight.w800),
                  ),
                ),
                segments: [
                  ButtonSegment(
                    value: '',
                    label: Text(_c('Alle', 'All', 'Tous', 'الكل')),
                  ),
                  ButtonSegment(
                    value: 'active',
                    label: Text(_c('Aktiv', 'Active', 'Actifs', 'نشط')),
                  ),
                  ButtonSegment(
                    value: 'upcoming',
                    label: Text(
                      _c('Demnächst', 'Upcoming', 'À venir', 'قريبًا'),
                    ),
                  ),
                  ButtonSegment(
                    value: 'finished',
                    label: Text(_c('Beendet', 'Finished', 'Terminés', 'منتهٍ')),
                  ),
                ],
                selected: {_filter},
                onSelectionChanged: (selected) {
                  _filter = selected.first;
                  _reload();
                },
              ),
              const SizedBox(height: 16),
              if (challenges.isEmpty)
                _EmptyState(
                  text: _c(
                    'Noch keine Challenges in diesem Bereich.',
                    'No challenges in this section yet.',
                    'Aucun défi dans cette section.',
                    'لا توجد تحديات في هذا القسم.',
                  ),
                )
              else
                for (final challenge in challenges) ...[
                  _ChallengeCard(
                    challenge: challenge,
                    metricLabel: _metricLabel('${challenge['metric']}'),
                    frequencyLabel: _frequencyLabel(
                      '${challenge['frequency']}',
                    ),
                    stateLabel: _stateLabel('${challenge['state']}'),
                    onOpen: () => _openChallenge(_int(challenge['id'])),
                    onAccept: _status(challenge) == 'pending'
                        ? () => _respond(_int(challenge['id']), 'accepted')
                        : null,
                  ),
                  const SizedBox(height: 12),
                ],
              // Keep the latest catalogs for the create sheet even when the
              // list is empty.
              Offstage(child: Text('${meta.length}')),
            ],
          ),
        );
      },
    ),
  );

  Future<void> _respond(int id, String status) async {
    try {
      await _client.respondToChallenge(id, status);
      _reload();
    } catch (error) {
      _showError(error);
    }
  }

  Future<void> _openChallenge(int id) async {
    await Navigator.of(context).push(
      MaterialPageRoute(builder: (_) => ChallengeDetailScreen(challengeId: id)),
    );
    if (mounted) _reload();
  }

  Future<void> _openCreate() async {
    AirmiusJson response;
    try {
      response = await _client.challenges();
    } catch (error) {
      _showError(error);
      return;
    }
    if (!mounted) return;
    final created = await Navigator.of(context).push<bool>(
      MaterialPageRoute(
        fullscreenDialog: true,
        builder: (_) => ChallengeCreateScreen(
          client: _client,
          meta: response['meta'] is AirmiusJson
              ? response['meta'] as AirmiusJson
              : <String, dynamic>{},
        ),
      ),
    );
    if (created == true && mounted) _reload();
  }

  String _metricLabel(String metric) => switch (metric) {
    'steps' => _c('Schritte', 'Steps', 'Pas', 'خطوات'),
    'distance_meters' => _c('Distanz', 'Distance', 'Distance', 'المسافة'),
    'duration_minutes' => _c(
      'Trainingszeit',
      'Training time',
      'Durée',
      'مدة التدريب',
    ),
    'sessions' => _c('Einheiten', 'Sessions', 'Séances', 'حصص'),
    'repetitions' => _c(
      'Wiederholungen',
      'Repetitions',
      'Répétitions',
      'تكرارات',
    ),
    'calories' => _c('Kalorien', 'Calories', 'Calories', 'سعرات'),
    _ => _c('Eigenes Ziel', 'Custom goal', 'Objectif libre', 'هدف مخصص'),
  };

  String _frequencyLabel(String frequency) => switch (frequency) {
    'weekly' => _c('Wöchentlich', 'Weekly', 'Hebdomadaire', 'أسبوعي'),
    'once' => _c('Einmalig', 'Once', 'Une fois', 'مرة واحدة'),
    _ => _c('Täglich', 'Daily', 'Quotidien', 'يومي'),
  };

  String _stateLabel(String state) => switch (state) {
    'upcoming' => _c('Demnächst', 'Upcoming', 'À venir', 'قريبًا'),
    'finished' => _c('Beendet', 'Finished', 'Terminé', 'منتهٍ'),
    'cancelled' => _c('Abgebrochen', 'Cancelled', 'Annulé', 'ملغى'),
    _ => _c('Aktiv', 'Active', 'Actif', 'نشط'),
  };

  void _showError(Object error) {
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(
          challengeErrorMessage(error, AirmiusScope.of(context).language),
        ),
      ),
    );
  }
}

class ChallengeDetailScreen extends StatefulWidget {
  const ChallengeDetailScreen({super.key, required this.challengeId});
  final int challengeId;

  @override
  State<ChallengeDetailScreen> createState() => _ChallengeDetailScreenState();
}

class _ChallengeDetailScreenState extends State<ChallengeDetailScreen> {
  final Map<String, TextEditingController> _values = {};
  final _comment = TextEditingController();
  Future<AirmiusJson>? _future;
  bool _busy = false;

  AirmiusApiClient get _client {
    final services = AirmiusServicesScope.of(context);
    return services.clientForSession(services.authState.session);
  }

  String _c(String de, String en, String fr, String ar) =>
      switch (AirmiusScope.of(context).language) {
        AirmiusLanguage.de => de,
        AirmiusLanguage.en => en,
        AirmiusLanguage.fr => fr,
        AirmiusLanguage.ar => ar,
      };

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _future ??= _client.challenge(widget.challengeId);
  }

  @override
  void dispose() {
    for (final controller in _values.values) {
      controller.dispose();
    }
    _comment.dispose();
    super.dispose();
  }

  void _reload() =>
      setState(() => _future = _client.challenge(widget.challengeId));

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: Text(_c('Challenge', 'Challenge', 'Défi', 'التحدي'))),
    body: FutureBuilder<AirmiusJson>(
      future: _future,
      builder: (context, snapshot) {
        if (snapshot.connectionState == ConnectionState.waiting) {
          return const Center(child: CircularProgressIndicator());
        }
        if (snapshot.hasError) {
          return _ErrorState(error: snapshot.error, onRetry: _reload);
        }
        final envelope = snapshot.data ?? const <String, dynamic>{};
        final challenge = envelope['data'] is AirmiusJson
            ? envelope['data'] as AirmiusJson
            : envelope;
        final participation = challenge['my_participation'] is AirmiusJson
            ? challenge['my_participation'] as AirmiusJson
            : null;
        final progress = challenge['progress'] is AirmiusJson
            ? challenge['progress'] as AirmiusJson
            : const <String, dynamic>{};
        final comments = _maps(challenge['comments']);
        final participants = _maps(challenge['participants']);
        final checkins = _maps(challenge['my_checkins']);
        final slots = _strings(challenge['checkin_slots']).isEmpty
            ? const ['anytime']
            : _strings(challenge['checkin_slots']);
        return ListView(
          padding: const EdgeInsets.all(16),
          children: [
            Text(
              '${challenge['title'] ?? ''}',
              style: Theme.of(
                context,
              ).textTheme.headlineMedium?.copyWith(fontWeight: FontWeight.w900),
            ),
            const SizedBox(height: 8),
            Text(
              '${challenge['description'] ?? ''}',
              style: Theme.of(context).textTheme.bodyLarge,
            ),
            const SizedBox(height: 16),
            Card(
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      '${challenge['target_value']} ${challenge['unit'] ?? ''}',
                      style: Theme.of(context).textTheme.headlineSmall
                          ?.copyWith(fontWeight: FontWeight.w900),
                    ),
                    const SizedBox(height: 8),
                    LinearProgressIndicator(
                      value: (_num(progress['percentage']) / 100).clamp(0, 1),
                    ),
                    const SizedBox(height: 6),
                    Text(
                      '${progress['completed'] ?? 0}/${progress['total'] ?? 0} · ${progress['percentage'] ?? 0}%',
                    ),
                  ],
                ),
              ),
            ),
            if (participation?['status'] == 'pending') ...[
              const SizedBox(height: 12),
              Row(
                children: [
                  Expanded(
                    child: FilledButton(
                      onPressed: _busy ? null : () => _respond('accepted'),
                      child: Text(_c('Annehmen', 'Accept', 'Accepter', 'قبول')),
                    ),
                  ),
                  const SizedBox(width: 8),
                  OutlinedButton(
                    onPressed: _busy ? null : () => _respond('declined'),
                    child: Text(_c('Ablehnen', 'Decline', 'Refuser', 'رفض')),
                  ),
                ],
              ),
            ] else if (challenge['can_join'] == true) ...[
              const SizedBox(height: 12),
              FilledButton(
                onPressed: _busy ? null : _join,
                child: Text(_c('Teilnehmen', 'Join', 'Participer', 'انضمام')),
              ),
            ],
            if (challenge['can_checkin'] == true) ...[
              const SizedBox(height: 16),
              for (final slot in slots) ...[
                _CheckinCard(
                  title: _slotLabel(slot),
                  target:
                      '${challenge['target_value']} ${challenge['unit'] ?? ''}',
                  valueLabel:
                      '${challenge['unit'] ?? _c('Wert', 'Value', 'Valeur', 'القيمة')}',
                  controller: _controllerForSlot(
                    slot,
                    '${challenge['target_value'] ?? ''}',
                  ),
                  done: checkins.any(
                    (item) =>
                        item['date'] == _today() &&
                        '${item['slot'] ?? 'anytime'}' == slot &&
                        item['completed'] == true,
                  ),
                  busy: _busy,
                  onToggle: (done) => _checkin(slot, !done),
                  doneLabel: _c(
                    'Für heute bestätigt ✓',
                    'Confirmed for today ✓',
                    'Confirmé pour aujourd’hui ✓',
                    'تم التأكيد لليوم ✓',
                  ),
                  confirmLabel: _c(
                    'Bestätigen',
                    'Confirm',
                    'Confirmer',
                    'تأكيد',
                  ),
                  undoLabel: _c(
                    'Bestätigung entfernen',
                    'Remove confirmation',
                    'Retirer la confirmation',
                    'إزالة التأكيد',
                  ),
                ),
                const SizedBox(height: 10),
              ],
            ],
            const SizedBox(height: 20),
            Text(
              _c('Teilnehmer', 'Participants', 'Participants', 'المشاركون'),
              style: Theme.of(
                context,
              ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w900),
            ),
            const SizedBox(height: 8),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: [
                for (final item in participants)
                  Chip(
                    label: Text(
                      '${(item['user'] as AirmiusJson?)?['name'] ?? ''} · ${_participantLabel(item['status'])}',
                    ),
                  ),
              ],
            ),
            const SizedBox(height: 20),
            Text(
              _c('Kommentare', 'Comments', 'Commentaires', 'التعليقات'),
              style: Theme.of(
                context,
              ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w900),
            ),
            const SizedBox(height: 8),
            if (comments.isEmpty)
              Text(
                _c(
                  'Noch keine Kommentare.',
                  'No comments yet.',
                  'Aucun commentaire.',
                  'لا تعليقات بعد.',
                ),
              ),
            for (final item in comments)
              ListTile(
                contentPadding: EdgeInsets.zero,
                leading: const CircleAvatar(child: Icon(Icons.person_outline)),
                title: Text(
                  '${(item['user'] as AirmiusJson?)?['name'] ?? ''}',
                  style: const TextStyle(fontWeight: FontWeight.w800),
                ),
                subtitle: Text('${item['content'] ?? ''}'),
              ),
            if (challenge['can_comment'] == true)
              Row(
                children: [
                  Expanded(
                    child: TextField(
                      controller: _comment,
                      maxLength: 1500,
                      decoration: InputDecoration(
                        hintText: _c(
                          'Kommentar schreiben …',
                          'Write a comment …',
                          'Écrire un commentaire…',
                          'اكتب تعليقًا…',
                        ),
                      ),
                    ),
                  ),
                  IconButton(
                    onPressed: _busy ? null : _sendComment,
                    icon: const Icon(Icons.send),
                  ),
                ],
              ),
          ],
        );
      },
    ),
  );

  Future<void> _run(Future<void> Function() action) async {
    setState(() => _busy = true);
    try {
      await action();
      if (mounted) _reload();
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              challengeErrorMessage(error, AirmiusScope.of(context).language),
            ),
          ),
        );
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _respond(String status) => _run(() async {
    await _client.respondToChallenge(widget.challengeId, status);
  });
  Future<void> _join() => _run(() async {
    await _client.joinChallenge(widget.challengeId);
  });
  TextEditingController _controllerForSlot(String slot, String target) =>
      _values.putIfAbsent(slot, () => TextEditingController(text: target));

  String _slotLabel(String slot) => switch (slot) {
    'morning' => _c('Morgens', 'Morning', 'Matin', 'صباحًا'),
    'midday' => _c('Mittags', 'Midday', 'Midi', 'ظهرًا'),
    'evening' => _c('Abends', 'Evening', 'Soir', 'مساءً'),
    _ => _c('Heutiges Ziel', 'Today’s goal', 'Objectif du jour', 'هدف اليوم'),
  };

  String _participantLabel(dynamic status) => switch (status) {
    'accepted' => _c('Dabei', 'Joined', 'Inscrit', 'مشارك'),
    'pending' => _c('Eingeladen', 'Invited', 'Invité', 'مدعو'),
    'declined' => _c('Abgelehnt', 'Declined', 'Refusé', 'مرفوض'),
    _ => _c('Unbekannt', 'Unknown', 'Inconnu', 'غير معروف'),
  };

  Future<void> _checkin(String slot, bool completed) => _run(() async {
    final parsed = parseChallengeValue(_controllerForSlot(slot, '').text);
    if (completed && parsed == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            _c(
              'Bitte gib eine Zahl zwischen 0 und 999999999 ein.',
              'Please enter a number between 0 and 999999999.',
              'Saisis un nombre entre 0 et 999999999.',
              'أدخل رقمًا بين 0 و999999999.',
            ),
          ),
        ),
      );
      return;
    }
    await _client.checkInChallenge(
      widget.challengeId,
      _today(),
      completed: completed,
      value: completed ? parsed : null,
      slot: slot,
    );
  });
  Future<void> _sendComment() => _run(() async {
    final content = _comment.text.trim();
    if (content.isEmpty) return;
    await _client.addChallengeComment(widget.challengeId, content);
    _comment.clear();
  });
}

class ChallengeCreateScreen extends StatefulWidget {
  const ChallengeCreateScreen({
    super.key,
    required this.client,
    required this.meta,
  });
  final AirmiusApiClient client;
  final AirmiusJson meta;

  @override
  State<ChallengeCreateScreen> createState() => _ChallengeCreateScreenState();
}

class _ChallengeCreateScreenState extends State<ChallengeCreateScreen> {
  final _title = TextEditingController();
  final _description = TextEditingController();
  final _target = TextEditingController(text: '10000');
  final _unit = TextEditingController(text: 'Schritte');
  String _visibility = 'invite_only';
  String _metric = 'steps';
  String _frequency = 'daily';
  final Set<String> _checkinSlots = {'anytime'};
  int? _sportId;
  int? _clubId;
  int? _teamId;
  final Set<int> _invitees = {};
  DateTime _start = DateTime.now();
  DateTime _end = DateTime.now().add(const Duration(days: 29));
  bool _busy = false;

  String _c(String de, String en, String fr, String ar) =>
      switch (AirmiusScope.of(context).language) {
        AirmiusLanguage.de => de,
        AirmiusLanguage.en => en,
        AirmiusLanguage.fr => fr,
        AirmiusLanguage.ar => ar,
      };

  @override
  void dispose() {
    _title.dispose();
    _description.dispose();
    _target.dispose();
    _unit.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final sports = _maps(widget.meta['sports']);
    final friends = _maps(widget.meta['friends']);
    final clubs = _maps(
      widget.meta['clubs'],
    ).where((item) => item['can_create'] == true).toList();
    final teams = _maps(
      widget.meta['teams'],
    ).where((item) => item['can_create'] == true).toList();
    final visibilities = <String>[
      'invite_only',
      if (clubs.isNotEmpty) 'club',
      if (teams.isNotEmpty) 'team',
      if (widget.meta['can_create_public'] == true) 'public',
    ];
    return Scaffold(
      appBar: AppBar(
        title: Text(
          _c(
            'Challenge erstellen',
            'Create challenge',
            'Créer un défi',
            'إنشاء تحدٍ',
          ),
        ),
      ),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          TextField(
            controller: _title,
            maxLength: 140,
            decoration: InputDecoration(
              labelText: _c('Titel', 'Title', 'Titre', 'العنوان'),
              hintText: _c(
                '30 Tage – 10.000 Schritte',
                '30 days – 10,000 steps',
                '30 jours – 10 000 pas',
                '30 يومًا – 10000 خطوة',
              ),
            ),
          ),
          TextField(
            controller: _description,
            maxLength: 3000,
            maxLines: 3,
            decoration: InputDecoration(
              labelText: _c(
                'Beschreibung',
                'Description',
                'Description',
                'الوصف',
              ),
            ),
          ),
          DropdownButtonFormField<String>(
            initialValue: _visibility,
            decoration: InputDecoration(
              labelText: _c(
                'Sichtbarkeit',
                'Visibility',
                'Visibilité',
                'الظهور',
              ),
            ),
            items: [
              for (final item in visibilities)
                DropdownMenuItem(
                  value: item,
                  child: Text(_visibilityLabel(item)),
                ),
            ],
            onChanged: (value) =>
                setState(() => _visibility = value ?? _visibility),
          ),
          const SizedBox(height: 12),
          DropdownButtonFormField<int?>(
            initialValue: _sportId,
            decoration: InputDecoration(
              labelText: _c('Sportart', 'Sport', 'Sport', 'الرياضة'),
            ),
            items: [
              DropdownMenuItem(
                value: null,
                child: Text(
                  _c(
                    'Sportübergreifend',
                    'All sports',
                    'Tous sports',
                    'كل الرياضات',
                  ),
                ),
              ),
              for (final item in sports)
                DropdownMenuItem(
                  value: _int(item['id']),
                  child: Text('${item['name']}'),
                ),
            ],
            onChanged: (value) => setState(() => _sportId = value),
          ),
          if (_visibility == 'club') ...[
            const SizedBox(height: 12),
            DropdownButtonFormField<int>(
              initialValue: _clubId,
              decoration: InputDecoration(
                labelText: _c('Verein', 'Club', 'Club', 'النادي'),
              ),
              items: [
                for (final item in clubs)
                  DropdownMenuItem(
                    value: _int(item['id']),
                    child: Text('${item['name']}'),
                  ),
              ],
              onChanged: (value) => setState(() => _clubId = value),
            ),
          ],
          if (_visibility == 'team') ...[
            const SizedBox(height: 12),
            DropdownButtonFormField<int>(
              initialValue: _teamId,
              decoration: InputDecoration(
                labelText: _c('Team', 'Team', 'Équipe', 'الفريق'),
              ),
              items: [
                for (final item in teams)
                  DropdownMenuItem(
                    value: _int(item['id']),
                    child: Text('${item['name']}'),
                  ),
              ],
              onChanged: (value) => setState(() => _teamId = value),
            ),
          ],
          const SizedBox(height: 12),
          DropdownButtonFormField<String>(
            initialValue: _metric,
            decoration: InputDecoration(
              labelText: _c(
                'Zieltyp',
                'Goal type',
                'Type d’objectif',
                'نوع الهدف',
              ),
            ),
            items: [
              for (final item in [
                'steps',
                'distance_meters',
                'duration_minutes',
                'sessions',
                'repetitions',
                'calories',
                'custom',
              ])
                DropdownMenuItem(value: item, child: Text(_metricLabel(item))),
            ],
            onChanged: (value) {
              if (value == null) return;
              setState(() {
                _metric = value;
                final defaults = {
                  'steps': ['10000', 'Schritte'],
                  'distance_meters': ['5000', 'm'],
                  'duration_minutes': ['30', 'Minuten'],
                  'sessions': ['1', 'Einheiten'],
                  'repetitions': ['50', 'Wiederholungen'],
                  'calories': ['500', 'kcal'],
                  'custom': ['1', ''],
                };
                _target.text = defaults[value]![0];
                _unit.text = defaults[value]![1];
              });
            },
          ),
          const SizedBox(height: 12),
          Row(
            children: [
              Expanded(
                child: TextField(
                  controller: _target,
                  keyboardType: const TextInputType.numberWithOptions(
                    decimal: true,
                  ),
                  decoration: InputDecoration(
                    labelText: _c(
                      'Zielwert',
                      'Target',
                      'Objectif',
                      'القيمة المستهدفة',
                    ),
                  ),
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: TextField(
                  controller: _unit,
                  decoration: InputDecoration(
                    labelText: _c('Einheit', 'Unit', 'Unité', 'الوحدة'),
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          DropdownButtonFormField<String>(
            initialValue: _frequency,
            decoration: InputDecoration(
              labelText: _c('Rhythmus', 'Frequency', 'Fréquence', 'التكرار'),
            ),
            items: [
              DropdownMenuItem(
                value: 'daily',
                child: Text(_c('Täglich', 'Daily', 'Quotidien', 'يومي')),
              ),
              DropdownMenuItem(
                value: 'weekly',
                child: Text(
                  _c('Wöchentlich', 'Weekly', 'Hebdomadaire', 'أسبوعي'),
                ),
              ),
              DropdownMenuItem(
                value: 'once',
                child: Text(_c('Einmalig', 'Once', 'Une fois', 'مرة واحدة')),
              ),
            ],
            onChanged: (value) =>
                setState(() => _frequency = value ?? _frequency),
          ),
          if (_frequency == 'daily') ...[
            const SizedBox(height: 16),
            Text(
              _c(
                'Bestätigungen pro Tag',
                'Confirmations per day',
                'Confirmations par jour',
                'التأكيدات اليومية',
              ),
              style: const TextStyle(fontWeight: FontWeight.w900),
            ),
            const SizedBox(height: 4),
            Text(
              _c(
                'Wähle mehrere Tagesabschnitte, wenn das Ziel jedes Mal bestätigt werden soll.',
                'Select multiple times of day when the goal must be confirmed each time.',
                'Sélectionnez plusieurs moments si l’objectif doit être confirmé à chaque fois.',
                'اختر عدة أوقات إذا كان يجب تأكيد الهدف في كل مرة.',
              ),
            ),
            const SizedBox(height: 8),
            Wrap(
              spacing: 8,
              children: [
                for (final slot in ['anytime', 'morning', 'midday', 'evening'])
                  FilterChip(
                    label: Text(_slotLabel(slot)),
                    selected: _checkinSlots.contains(slot),
                    onSelected: (selected) => _toggleSlot(slot, selected),
                  ),
              ],
            ),
          ],
          const SizedBox(height: 12),
          Row(
            children: [
              Expanded(
                child: _DateTile(
                  label: _c('Start', 'Start', 'Début', 'البدء'),
                  date: _start,
                  onTap: () => _pickDate(true),
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: _DateTile(
                  label: _c('Ende', 'End', 'Fin', 'النهاية'),
                  date: _end,
                  onTap: () => _pickDate(false),
                ),
              ),
            ],
          ),
          if (friends.isNotEmpty) ...[
            const SizedBox(height: 20),
            Text(
              _c(
                'Freunde einladen',
                'Invite friends',
                'Inviter des amis',
                'دعوة الأصدقاء',
              ),
              style: const TextStyle(fontWeight: FontWeight.w900),
            ),
            const SizedBox(height: 8),
            Wrap(
              spacing: 8,
              children: [
                for (final item in friends)
                  FilterChip(
                    label: Text('${item['name']}'),
                    selected: _invitees.contains(_int(item['id'])),
                    onSelected: (selected) => setState(() {
                      final id = _int(item['id']);
                      selected ? _invitees.add(id) : _invitees.remove(id);
                    }),
                  ),
              ],
            ),
          ],
          const SizedBox(height: 28),
          FilledButton.icon(
            onPressed: _busy ? null : _submit,
            icon: const Icon(Icons.flag_outlined),
            label: Text(
              _busy
                  ? _c(
                      'Wird erstellt …',
                      'Creating …',
                      'Création…',
                      'جارٍ الإنشاء…',
                    )
                  : _c(
                      'Challenge veröffentlichen',
                      'Publish challenge',
                      'Publier le défi',
                      'نشر التحدي',
                    ),
            ),
          ),
        ],
      ),
    );
  }

  Future<void> _pickDate(bool start) async {
    final selected = await showDatePicker(
      context: context,
      initialDate: start ? _start : _end,
      firstDate: DateTime.now().subtract(const Duration(days: 1)),
      lastDate: DateTime.now().add(const Duration(days: 730)),
    );
    if (selected == null) return;
    setState(() {
      if (start) {
        _start = selected;
        if (_end.isBefore(_start)) _end = _start;
      } else {
        _end = selected;
      }
    });
  }

  Future<void> _submit() async {
    final target = num.tryParse(_target.text.trim().replaceAll(',', '.'));
    if (_title.text.trim().isEmpty ||
        target == null ||
        target <= 0 ||
        (_visibility == 'club' && _clubId == null) ||
        (_visibility == 'team' && _teamId == null)) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            _c(
              'Bitte fülle alle Pflichtfelder aus.',
              'Please complete all required fields.',
              'Veuillez remplir les champs requis.',
              'يرجى إكمال الحقول المطلوبة.',
            ),
          ),
        ),
      );
      return;
    }
    setState(() => _busy = true);
    try {
      await widget.client.createChallenge({
        'title': _title.text.trim(),
        'description': _description.text.trim(),
        'sport_id': _sportId,
        'visibility': _visibility,
        'club_id': _visibility == 'club' ? _clubId : null,
        'team_id': _visibility == 'team' ? _teamId : null,
        'metric': _metric,
        'target_value': target,
        'unit': _unit.text.trim(),
        'frequency': _frequency,
        'checkin_slots': _frequency == 'daily'
            ? _checkinSlots.toList()
            : ['anytime'],
        'verification': 'manual',
        'starts_on': _date(_start),
        'ends_on': _date(_end),
        'invitee_ids': _invitees.toList(),
      });
      if (mounted) Navigator.pop(context, true);
    } catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              challengeErrorMessage(error, AirmiusScope.of(context).language),
            ),
          ),
        );
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  void _toggleSlot(String slot, bool selected) {
    setState(() {
      if (slot == 'anytime' && selected) {
        _checkinSlots
          ..clear()
          ..add('anytime');
        return;
      }
      _checkinSlots.remove('anytime');
      if (selected) {
        _checkinSlots.add(slot);
      } else {
        _checkinSlots.remove(slot);
      }
      if (_checkinSlots.isEmpty) _checkinSlots.add('anytime');
    });
  }

  String _slotLabel(String slot) => switch (slot) {
    'morning' => _c('Morgens', 'Morning', 'Matin', 'صباحًا'),
    'midday' => _c('Mittags', 'Midday', 'Midi', 'ظهرًا'),
    'evening' => _c('Abends', 'Evening', 'Soir', 'مساءً'),
    _ => _c('Einmal täglich', 'Once daily', 'Une fois par jour', 'مرة يوميًا'),
  };

  String _visibilityLabel(String item) => switch (item) {
    'public' => _c('Öffentlich', 'Public', 'Public', 'عام'),
    'club' => _c('Verein', 'Club', 'Club', 'نادي'),
    'team' => _c('Team', 'Team', 'Équipe', 'فريق'),
    _ => _c(
      'Privat / Einladungen',
      'Private / invites',
      'Privé / invitations',
      'خاص / دعوات',
    ),
  };
  String _metricLabel(String item) => switch (item) {
    'steps' => _c('Schritte', 'Steps', 'Pas', 'خطوات'),
    'distance_meters' => _c('Distanz', 'Distance', 'Distance', 'المسافة'),
    'duration_minutes' => _c(
      'Trainingszeit',
      'Training time',
      'Durée',
      'مدة التدريب',
    ),
    'sessions' => _c('Einheiten', 'Sessions', 'Séances', 'حصص'),
    'repetitions' => _c(
      'Wiederholungen',
      'Repetitions',
      'Répétitions',
      'تكرارات',
    ),
    'calories' => _c('Kalorien', 'Calories', 'Calories', 'سعرات'),
    _ => _c('Eigenes Ziel', 'Custom goal', 'Objectif libre', 'هدف مخصص'),
  };
}

class _ChallengeCard extends StatelessWidget {
  const _ChallengeCard({
    required this.challenge,
    required this.metricLabel,
    required this.frequencyLabel,
    required this.stateLabel,
    required this.onOpen,
    this.onAccept,
  });
  final AirmiusJson challenge;
  final String metricLabel;
  final String frequencyLabel;
  final String stateLabel;
  final VoidCallback onOpen;
  final VoidCallback? onAccept;

  @override
  Widget build(BuildContext context) {
    final progress = challenge['progress'] is AirmiusJson
        ? challenge['progress'] as AirmiusJson
        : const <String, dynamic>{};
    return Card(
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: onOpen,
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Expanded(
                    child: Text(
                      '${challenge['title'] ?? ''}',
                      style: Theme.of(context).textTheme.titleLarge?.copyWith(
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                  ),
                  Chip(label: Text(stateLabel)),
                ],
              ),
              if ('${challenge['description'] ?? ''}'.isNotEmpty) ...[
                const SizedBox(height: 6),
                Text(
                  '${challenge['description']}',
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                ),
              ],
              const SizedBox(height: 12),
              Text(
                '${challenge['target_value']} ${challenge['unit'] ?? ''}',
                style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                  fontWeight: FontWeight.w900,
                  color: Theme.of(context).colorScheme.primary,
                ),
              ),
              Text('$metricLabel · $frequencyLabel'),
              if (challenge['my_participation'] != null) ...[
                const SizedBox(height: 12),
                LinearProgressIndicator(
                  value: (_num(progress['percentage']) / 100).clamp(0, 1),
                ),
                const SizedBox(height: 5),
                Text(
                  '${progress['completed'] ?? 0}/${progress['total'] ?? 0} · ${progress['percentage'] ?? 0}%',
                ),
              ],
              if (onAccept != null) ...[
                const SizedBox(height: 12),
                FilledButton.icon(
                  onPressed: onAccept,
                  icon: const Icon(Icons.check),
                  label: const Text('Annehmen'),
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }
}

class _HeroCard extends StatelessWidget {
  const _HeroCard({required this.title, required this.subtitle});
  final String title;
  final String subtitle;
  @override
  Widget build(BuildContext context) => DecoratedBox(
    decoration: BoxDecoration(
      gradient: LinearGradient(
        colors: [
          Theme.of(context).colorScheme.primaryContainer,
          Theme.of(context).colorScheme.surface,
        ],
      ),
      borderRadius: BorderRadius.circular(24),
    ),
    child: Padding(
      padding: const EdgeInsets.all(22),
      child: Row(
        children: [
          Icon(
            Icons.flag_circle,
            size: 48,
            color: Theme.of(context).colorScheme.primary,
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: Theme.of(
                    context,
                  ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w900),
                ),
                const SizedBox(height: 5),
                Text(subtitle),
              ],
            ),
          ),
        ],
      ),
    ),
  );
}

class _DateTile extends StatelessWidget {
  const _DateTile({
    required this.label,
    required this.date,
    required this.onTap,
  });
  final String label;
  final DateTime date;
  final VoidCallback onTap;
  @override
  Widget build(BuildContext context) => ListTile(
    onTap: onTap,
    shape: RoundedRectangleBorder(
      side: BorderSide(color: Theme.of(context).dividerColor),
      borderRadius: BorderRadius.circular(12),
    ),
    title: Text(label),
    subtitle: Text(_date(date)),
    trailing: const Icon(Icons.calendar_month),
  );
}

class _CheckinCard extends StatelessWidget {
  const _CheckinCard({
    required this.title,
    required this.target,
    required this.valueLabel,
    required this.controller,
    required this.done,
    required this.busy,
    required this.onToggle,
    required this.doneLabel,
    required this.confirmLabel,
    required this.undoLabel,
  });

  final String title;
  final String target;
  final String valueLabel;
  final TextEditingController controller;
  final bool done;
  final bool busy;
  final ValueChanged<bool> onToggle;
  final String doneLabel;
  final String confirmLabel;
  final String undoLabel;

  @override
  Widget build(BuildContext context) => Card(
    color: done ? Theme.of(context).colorScheme.primaryContainer : null,
    child: Padding(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              Icon(done ? Icons.check_circle : Icons.schedule),
              const SizedBox(width: 8),
              Expanded(
                child: Text(
                  done ? '$title · $doneLabel' : title,
                  style: const TextStyle(fontWeight: FontWeight.w900),
                ),
              ),
            ],
          ),
          const SizedBox(height: 8),
          Text(target),
          const SizedBox(height: 10),
          TextField(
            controller: controller,
            enabled: !done,
            keyboardType: const TextInputType.numberWithOptions(decimal: true),
            decoration: InputDecoration(labelText: valueLabel),
          ),
          const SizedBox(height: 10),
          FilledButton.icon(
            onPressed: busy ? null : () => onToggle(done),
            icon: Icon(done ? Icons.undo : Icons.check_circle),
            label: Text(done ? undoLabel : confirmLabel),
          ),
        ],
      ),
    ),
  );
}

class _EmptyState extends StatelessWidget {
  const _EmptyState({required this.text});
  final String text;
  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.all(40),
    child: Column(
      children: [
        Icon(
          Icons.flag_outlined,
          size: 52,
          color: Theme.of(context).colorScheme.primary,
        ),
        const SizedBox(height: 12),
        Text(text, textAlign: TextAlign.center),
      ],
    ),
  );
}

class _ErrorState extends StatelessWidget {
  const _ErrorState({required this.error, required this.onRetry});
  final Object? error;
  final VoidCallback onRetry;
  @override
  Widget build(BuildContext context) => Center(
    child: SingleChildScrollView(
      padding: const EdgeInsets.all(24),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Icon(Icons.error_outline, size: 48),
          const SizedBox(height: 12),
          Text(
            challengeErrorMessage(error, AirmiusScope.of(context).language),
            textAlign: TextAlign.center,
          ),
          const SizedBox(height: 12),
          FilledButton(
            onPressed: onRetry,
            child: Text(challengeRetryLabel(AirmiusScope.of(context).language)),
          ),
        ],
      ),
    ),
  );
}

List<AirmiusJson> _maps(dynamic value) =>
    value is List ? value.whereType<AirmiusJson>().toList() : const [];
List<String> _strings(dynamic value) =>
    value is List ? value.whereType<String>().toList() : const [];
int _int(dynamic value) =>
    value is num ? value.toInt() : int.tryParse('$value') ?? 0;
double _num(dynamic value) =>
    value is num ? value.toDouble() : double.tryParse('$value') ?? 0;
String _status(AirmiusJson challenge) =>
    challenge['my_participation'] is AirmiusJson
    ? '${(challenge['my_participation'] as AirmiusJson)['status'] ?? ''}'
    : '';
String _today() => _date(DateTime.now());
String _date(DateTime date) =>
    '${date.year.toString().padLeft(4, '0')}-${date.month.toString().padLeft(2, '0')}-${date.day.toString().padLeft(2, '0')}';
