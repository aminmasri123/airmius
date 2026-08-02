import 'package:flutter/material.dart';

import '../core/airmius_api_client.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';
import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'clubs_screen.dart';

class RegistrationOnboardingScreen extends StatelessWidget {
  const RegistrationOnboardingScreen({super.key, required this.accountType});

  final String accountType;

  Future<void> _startSetup(BuildContext context) async {
    final result = await Navigator.push<bool>(
      context,
      MaterialPageRoute<bool>(
        fullscreenDialog: true,
        builder: (_) => accountType == 'club'
            ? const ClubCreateWizardScreen()
            : const TrainerRegistrationFormScreen(),
      ),
    );
    if (result == true && context.mounted) {
      Navigator.pop(context, true);
    }
  }

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final isClub = accountType == 'club';
    return Scaffold(
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      appBar: AppBar(
        title: Text(t('registrationOnboarding.title')),
        surfaceTintColor: Colors.transparent,
      ),
      body: PageFrame(
        title: isClub
            ? t('registrationOnboarding.clubTitle')
            : t('registrationOnboarding.trainerTitle'),
        subtitle: t('registrationOnboarding.subtitle'),
        child: AirmiusPanel(
          gradient: true,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Icon(
                isClub ? Icons.apartment_outlined : Icons.sports_outlined,
                size: 48,
                color: airmiusAccentColor(context),
              ),
              const SizedBox(height: 14),
              Text(
                isClub
                    ? t('registrationOnboarding.clubBody')
                    : t('registrationOnboarding.trainerBody'),
                style: TextStyle(
                  color: airmiusTextColor(context),
                  height: 1.4,
                  fontWeight: FontWeight.w700,
                ),
              ),
              const SizedBox(height: 18),
              AirmiusButton(
                label: t('registrationOnboarding.startNow'),
                icon: Icons.arrow_forward_outlined,
                onPressed: () => _startSetup(context),
              ),
              const SizedBox(height: 10),
              AirmiusButton(
                label: t('registrationOnboarding.later'),
                icon: Icons.schedule_outlined,
                secondary: true,
                onPressed: () => Navigator.pop(context, false),
              ),
              const SizedBox(height: 10),
              Text(
                t('registrationOnboarding.laterHint'),
                textAlign: TextAlign.center,
                style: TextStyle(color: airmiusMutedColor(context)),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class TrainerRegistrationFormScreen extends StatefulWidget {
  const TrainerRegistrationFormScreen({super.key});

  @override
  State<TrainerRegistrationFormScreen> createState() =>
      _TrainerRegistrationFormScreenState();
}

class _TrainerRegistrationFormScreenState
    extends State<TrainerRegistrationFormScreen> {
  final _experience = TextEditingController();
  final _certification = TextEditingController();
  final _message = TextEditingController();
  final List<Map<String, dynamic>> _sports = [];
  final List<int> _selectedSportIds = [];
  final List<int> _selectedSkillIds = [];
  bool _loadingOptions = true;
  bool _saving = false;
  String? _error;
  String? _optionsError;

  @override
  void initState() {
    super.initState();
    Future<void>.microtask(_loadOptions);
  }

  Future<void> _loadOptions() async {
    try {
      final services = AirmiusServicesScope.of(context);
      final client = services.clientForSession(services.authState.session);
      final response = await client.sports();
      final rawSports = response['data'];
      final sports = rawSports is List
          ? rawSports
                .whereType<Map>()
                .map((sport) => Map<String, dynamic>.from(sport))
                .toList()
          : <Map<String, dynamic>>[];

      if (!mounted) return;
      setState(() {
        _sports
          ..clear()
          ..addAll(sports);
        _loadingOptions = false;
        _optionsError = sports.isEmpty
            ? AirmiusScope.of(context).t('registrationOnboarding.optionsError')
            : null;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _loadingOptions = false;
        _optionsError = AirmiusScope.of(
          context,
        ).t('registrationOnboarding.optionsError');
      });
    }
  }

  int? _asInt(dynamic value) {
    if (value is int) return value;
    return int.tryParse(value?.toString() ?? '');
  }

  List<Map<String, dynamic>> _skillOptionsForSportIds(Iterable<int> sportIds) {
    final selectedSportIds = sportIds.toSet();
    final options = <String, Map<String, dynamic>>{};

    for (final sport in _sports) {
      final sportId = _asInt(sport['id']);
      if (selectedSportIds.isNotEmpty &&
          (sportId == null || !selectedSportIds.contains(sportId))) {
        continue;
      }

      final rawSkills = sport['skills'];
      if (rawSkills is! List) continue;

      for (final rawSkill in rawSkills.whereType<Map>()) {
        final skill = Map<String, dynamic>.from(rawSkill);
        final skillId = _asInt(skill['id']);
        final name = skill['name']?.toString().trim() ?? '';
        if (skillId == null || name.isEmpty) continue;
        skill['sport_name'] = sport['name']?.toString() ?? '';
        options.putIfAbsent(name.toLowerCase(), () => skill);
      }
    }

    return options.values.toList();
  }

  List<Map<String, dynamic>> get _availableSkills =>
      _skillOptionsForSportIds(_selectedSportIds);

  String _selectedLabels(
    Iterable<int> ids,
    Iterable<Map<String, dynamic>> options,
  ) {
    final selected = ids.toSet();
    return options
        .where((option) => selected.contains(_asInt(option['id'])))
        .map((option) => option['name']?.toString().trim() ?? '')
        .where((name) => name.isNotEmpty)
        .join(', ');
  }

  Future<List<int>?> _selectMultiple({
    required String title,
    required List<Map<String, dynamic>> options,
    required List<int> initialSelection,
  }) {
    final draft = <int>{...initialSelection};
    return showModalBottomSheet<List<int>>(
      context: context,
      isScrollControlled: true,
      builder: (sheetContext) => StatefulBuilder(
        builder: (context, setModalState) => SafeArea(
          child: Padding(
            padding: const EdgeInsets.fromLTRB(18, 18, 18, 12),
            child: ConstrainedBox(
              constraints: BoxConstraints(
                maxHeight: MediaQuery.of(context).size.height * 0.75,
              ),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Text(
                    title,
                    style: TextStyle(
                      color: airmiusTextColor(context),
                      fontSize: 18,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                  const SizedBox(height: 10),
                  Expanded(
                    child: ListView.separated(
                      shrinkWrap: true,
                      itemCount: options.length,
                      separatorBuilder: (_, _) => const Divider(height: 1),
                      itemBuilder: (_, index) {
                        final option = options[index];
                        final id = _asInt(option['id']);
                        if (id == null) return const SizedBox.shrink();
                        final name = option['name']?.toString() ?? '';
                        final sportName = option['sport_name']?.toString();
                        return CheckboxListTile(
                          value: draft.contains(id),
                          title: Text(name),
                          subtitle: sportName == null || sportName.isEmpty
                              ? null
                              : Text(sportName),
                          contentPadding: EdgeInsets.zero,
                          onChanged: (checked) {
                            setModalState(() {
                              if (checked == true) {
                                draft.add(id);
                              } else {
                                draft.remove(id);
                              }
                            });
                          },
                        );
                      },
                    ),
                  ),
                  const SizedBox(height: 10),
                  AirmiusButton(
                    label: AirmiusScope.of(
                      context,
                    ).t('registrationOnboarding.optionsSave'),
                    icon: Icons.check_outlined,
                    onPressed: () =>
                        Navigator.of(sheetContext).pop(draft.toList()..sort()),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }

  Widget _multiSelectField({
    required String label,
    required String placeholder,
    required String selectedText,
    required VoidCallback? onTap,
  }) {
    final hasSelection = selectedText.isNotEmpty;
    return Semantics(
      button: true,
      label: label,
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(14),
        child: InputDecorator(
          decoration: InputDecoration(
            labelText: label,
            hintText: placeholder,
            suffixIcon: const Icon(Icons.expand_more_outlined),
          ),
          child: Text(
            hasSelection ? selectedText : placeholder,
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
            style: TextStyle(
              color: hasSelection
                  ? airmiusTextColor(context)
                  : airmiusMutedColor(context),
              fontWeight: FontWeight.w700,
            ),
          ),
        ),
      ),
    );
  }

  Future<void> _chooseSports() async {
    final selected = await _selectMultiple(
      title: AirmiusScope.of(context).t('registrationOnboarding.sports'),
      options: _sports,
      initialSelection: _selectedSportIds,
    );
    if (!mounted || selected == null) return;

    final allowedSkillIds = _skillOptionsForSportIds(
      selected,
    ).map((skill) => _asInt(skill['id'])).whereType<int>().toSet();
    setState(() {
      _selectedSportIds
        ..clear()
        ..addAll(selected);
      _selectedSkillIds.removeWhere(
        (skillId) => !allowedSkillIds.contains(skillId),
      );
    });
  }

  Future<void> _chooseSkills() async {
    if (_selectedSportIds.isEmpty) return;
    final selected = await _selectMultiple(
      title: AirmiusScope.of(context).t('registrationOnboarding.specialties'),
      options: _availableSkills,
      initialSelection: _selectedSkillIds,
    );
    if (!mounted || selected == null) return;
    setState(() {
      _selectedSkillIds
        ..clear()
        ..addAll(selected);
    });
  }

  @override
  void dispose() {
    _experience.dispose();
    _certification.dispose();
    _message.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    final t = AirmiusScope.of(context).t;
    if (_selectedSportIds.isEmpty ||
        _selectedSkillIds.isEmpty ||
        _experience.text.trim().isEmpty) {
      setState(() => _error = t('registrationOnboarding.trainerRequired'));
      return;
    }
    setState(() {
      _saving = true;
      _error = null;
    });
    try {
      final client = AirmiusServicesScope.of(
        context,
      ).clientForSession(AirmiusServicesScope.of(context).authState.session);
      await client.submitRoleApplication(
        type: 'trainer',
        message: _message.text.trim(),
        applicationData: {
          'sports': _selectedLabels(_selectedSportIds, _sports),
          'sport_ids': List<int>.from(_selectedSportIds),
          'sport_skill_ids': List<int>.from(_selectedSkillIds),
          'specialties': _selectedLabels(_selectedSkillIds, _availableSkills),
          'experience': _experience.text.trim(),
          'certification': _certification.text.trim(),
        },
      );
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(t('registrationOnboarding.trainerSubmitted'))),
      );
      Navigator.pop(context, true);
    } on AirmiusApiException catch (error) {
      if (mounted) setState(() => _error = error.userMessage);
    } catch (_) {
      if (mounted) setState(() => _error = t('common.errorDetails'));
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return Scaffold(
      appBar: AppBar(
        title: Text(t('registrationOnboarding.trainerTitle')),
        surfaceTintColor: Colors.transparent,
      ),
      body: PageFrame(
        title: t('registrationOnboarding.trainerTitle'),
        subtitle: t('registrationOnboarding.subtitle'),
        child: AirmiusPanel(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              if (_loadingOptions)
                const Padding(
                  padding: EdgeInsets.symmetric(vertical: 18),
                  child: Center(child: CircularProgressIndicator()),
                )
              else ...[
                _multiSelectField(
                  label: t('registrationOnboarding.sports'),
                  placeholder: t('registrationOnboarding.sportsPlaceholder'),
                  selectedText: _selectedLabels(_selectedSportIds, _sports),
                  onTap: _sports.isEmpty ? null : _chooseSports,
                ),
                const SizedBox(height: 6),
                Text(
                  t('registrationOnboarding.multipleHint'),
                  style: TextStyle(color: airmiusMutedColor(context)),
                ),
                const SizedBox(height: 12),
                _multiSelectField(
                  label: t('registrationOnboarding.specialties'),
                  placeholder: t(
                    'registrationOnboarding.specialtiesPlaceholder',
                  ),
                  selectedText: _selectedLabels(
                    _selectedSkillIds,
                    _availableSkills,
                  ),
                  onTap: _selectedSportIds.isEmpty ? null : _chooseSkills,
                ),
                const SizedBox(height: 6),
                Text(
                  t('registrationOnboarding.multipleHint'),
                  style: TextStyle(color: airmiusMutedColor(context)),
                ),
                if (_optionsError != null) ...[
                  const SizedBox(height: 8),
                  Text(
                    _optionsError!,
                    style: const TextStyle(
                      color: AirmiusColors.red,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ],
              ],
              const SizedBox(height: 12),
              AirmiusTextField(
                label: t('registrationOnboarding.experience'),
                controller: _experience,
                maxLines: 4,
              ),
              const SizedBox(height: 12),
              AirmiusTextField(
                label: t('registrationOnboarding.certification'),
                controller: _certification,
              ),
              const SizedBox(height: 12),
              AirmiusTextField(
                label: t('registrationOnboarding.message'),
                controller: _message,
                maxLines: 4,
              ),
              if (_error != null) ...[
                const SizedBox(height: 12),
                Text(
                  _error!,
                  style: const TextStyle(
                    color: AirmiusColors.red,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ],
              const SizedBox(height: 16),
              AirmiusButton(
                label: _saving
                    ? t('registrationOnboarding.saving')
                    : t('registrationOnboarding.submitTrainer'),
                icon: Icons.send_outlined,
                onPressed: _saving ? null : _submit,
              ),
            ],
          ),
        ),
      ),
    );
  }
}
