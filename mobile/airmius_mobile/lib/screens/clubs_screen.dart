import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;

import '../core/airmius_api_client.dart';
import '../core/airmius_api_models.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';
import '../core/airmius_theme.dart';
import '../models/club_summary.dart';
import '../widgets/airmius_widgets.dart';
import 'chat_detail_screen.dart';
import 'club_cockpit_screen.dart';
import 'club_membership_management_screen.dart';
import 'club_survey_screen.dart';
import 'global_search_screen.dart';
import 'membership_application_form_screen.dart';
import 'team_detail_screen.dart';

String _safeClubError(BuildContext context, Object error) {
  return error is AirmiusApiException
      ? error.userMessage
      : AirmiusScope.of(context).t('common.errorDetails');
}

class ClubsScreen extends StatefulWidget {
  const ClubsScreen({
    super.key,
    required this.requestedClubIds,
    required this.onRequestClub,
    required this.onWithdrawClub,
  });

  final Set<int> requestedClubIds;
  final ValueChanged<ClubSummary> onRequestClub;
  final ValueChanged<ClubSummary> onWithdrawClub;

  @override
  State<ClubsScreen> createState() => _ClubsScreenState();
}

class _ClubsScreenState extends State<ClubsScreen> {
  Future<List<ClubSummary>>? _clubsFuture;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _clubsFuture ??= _loadClubs();
  }

  Future<List<ClubSummary>> _loadClubs() async {
    final services = AirmiusServicesScope.of(context);
    final page = await services.repositories.clubs.searchClubs(mine: true);
    return page.items.map(ClubSummary.fromAirmiusClub).toList();
  }

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return PageFrame(
      title: t('clubs.workspace.title'),
      subtitle: t('clubs.workspace.subtitle'),
      showHeader: true,
      trailing: LayoutBuilder(
        builder: (context, constraints) {
          final stacked = constraints.maxWidth < 380;
          final findButton = OutlinedButton.icon(
            onPressed: () => Navigator.push(
              context,
              MaterialPageRoute(builder: (_) => GlobalSearchScreen()),
            ),
            icon: const Icon(Icons.search_outlined, size: 18),
            label: Text(t('clubs.find')),
            style: OutlinedButton.styleFrom(
              foregroundColor: airmiusAccentColor(context),
              side: BorderSide(color: airmiusBorderColor(context)),
              padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 13),
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(10),
              ),
            ),
          );
          final createButton = _CreateClubButton(onPressed: _openCreateClub);
          if (stacked) {
            return Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                SizedBox(width: double.infinity, child: findButton),
                const SizedBox(height: 8),
                SizedBox(width: double.infinity, child: createButton),
              ],
            );
          }
          return Row(
            children: [
              Expanded(child: findButton),
              const SizedBox(width: 8),
              Expanded(child: createButton),
            ],
          );
        },
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          FutureBuilder<List<ClubSummary>>(
            future: _clubsFuture,
            builder: (context, snapshot) {
              if (snapshot.connectionState == ConnectionState.waiting) {
                return Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    _ClubWorkspaceNav(canManageClubs: false),
                    SizedBox(height: 24),
                    AirmiusPanel(
                      child: Center(
                        child: Padding(
                          padding: EdgeInsets.all(18),
                          child: CircularProgressIndicator(
                            color: airmiusAccentColor(context),
                          ),
                        ),
                      ),
                    ),
                  ],
                );
              }
              if (snapshot.hasError) {
                return Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    const _ClubWorkspaceNav(canManageClubs: false),
                    const SizedBox(height: 24),
                    AirmiusPanel(
                      borderColor: Theme.of(
                        context,
                      ).colorScheme.error.withValues(alpha: .5),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: [
                          Text(
                            t('clubs.loadFailed'),
                            style: TextStyle(
                              color: airmiusTextColor(context),
                              fontWeight: FontWeight.w900,
                            ),
                          ),
                          const SizedBox(height: 8),
                          Text(
                            snapshot.error is AirmiusApiException
                                ? (snapshot.error! as AirmiusApiException)
                                      .userMessage
                                : t('common.errorDetails'),
                            style: TextStyle(
                              color: airmiusMutedColor(context),
                              height: 1.35,
                            ),
                          ),
                          const SizedBox(height: 12),
                          AirmiusButton(
                            label: t('clubs.reload'),
                            icon: Icons.refresh_outlined,
                            secondary: true,
                            onPressed: () => setState(() {
                              _clubsFuture = _loadClubs();
                            }),
                          ),
                        ],
                      ),
                    ),
                  ],
                );
              }
              final clubs = snapshot.data ?? const <ClubSummary>[];
              final canManageClubs = clubs.any((club) => club.canManage);
              if (clubs.isEmpty) {
                return Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    const _ClubWorkspaceNav(canManageClubs: false),
                    const SizedBox(height: 24),
                    AirmiusPanel(
                      child: Padding(
                        padding: const EdgeInsets.all(18),
                        child: Column(
                          children: [
                            Icon(
                              Icons.groups_2_outlined,
                              size: 46,
                              color: airmiusAccentColor(context),
                            ),
                            const SizedBox(height: 12),
                            Text(
                              t('clubs.empty'),
                              textAlign: TextAlign.center,
                              style: TextStyle(
                                color: airmiusTextColor(context),
                                fontSize: 18,
                                fontWeight: FontWeight.w900,
                              ),
                            ),
                            const SizedBox(height: 6),
                            Text(
                              t('clubs.emptyBody'),
                              textAlign: TextAlign.center,
                              style: TextStyle(
                                color: airmiusMutedColor(context),
                                height: 1.4,
                              ),
                            ),
                            const SizedBox(height: 16),
                            AirmiusButton(
                              label: t('clubs.find'),
                              icon: Icons.search_outlined,
                              onPressed: () => Navigator.push(
                                context,
                                MaterialPageRoute(
                                  builder: (_) => GlobalSearchScreen(),
                                ),
                              ),
                            ),
                            const SizedBox(height: 10),
                            AirmiusButton(
                              label: t('clubs.register'),
                              icon: Icons.add_business_outlined,
                              secondary: true,
                              onPressed: _openCreateClub,
                            ),
                          ],
                        ),
                      ),
                    ),
                  ],
                );
              }
              return Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  _ClubWorkspaceNav(canManageClubs: canManageClubs),
                  const SizedBox(height: 24),
                  for (final entry in clubs.indexed) ...[
                    _ClubCard(
                      club: entry.$2,
                      showManageActions: entry.$2.canManage,
                      requested:
                          widget.requestedClubIds.contains(entry.$2.id) ||
                          entry.$2.hasPendingMembershipRequest,
                      onRequest: widget.onRequestClub,
                      onWithdraw: widget.onWithdrawClub,
                      onReload: () => setState(() {
                        _clubsFuture = _loadClubs();
                      }),
                    ),
                    const SizedBox(height: 12),
                  ],
                ],
              );
            },
          ),
        ],
      ),
    );
  }

  Future<void> _openCreateClub() async {
    final created = await Navigator.push<bool>(
      context,
      MaterialPageRoute(
        fullscreenDialog: true,
        builder: (_) => const ClubCreateWizardScreen(),
      ),
    );
    if (created == true && mounted) {
      setState(() {
        _clubsFuture = _loadClubs();
      });
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(AirmiusScope.of(context).t('clubs.createdPending')),
        ),
      );
    }
  }
}

class ClubCreateWizardScreen extends StatefulWidget {
  const ClubCreateWizardScreen({super.key});

  @override
  State<ClubCreateWizardScreen> createState() => _ClubCreateWizardScreenState();
}

class _ClubCreateWizardScreenState extends State<ClubCreateWizardScreen> {
  final _name = TextEditingController();
  final _sportType = TextEditingController();
  final _officialNumber = TextEditingController();
  final _city = TextEditingController();
  final _postalCode = TextEditingController();
  final _state = TextEditingController();
  final _street = TextEditingController();
  final _houseNumber = TextEditingController();
  final _accountHolder = TextEditingController();
  final _iban = TextEditingController();
  final _bic = TextEditingController();

  int _step = 1;
  bool _official = false;
  bool _saving = false;
  String _country = 'DE';
  String? _selectedSportSlug;
  String? _notice;
  Future<List<AirmiusSport>>? _sportsFuture;

  static const _countries = [
    ('DE', 'Deutschland'),
    ('AT', 'Österreich'),
    ('CH', 'Schweiz'),
    ('FR', 'Frankreich'),
    ('NL', 'Niederlande'),
    ('BE', 'Belgien'),
    ('MA', 'Marokko'),
    ('ES', 'Spanien'),
    ('PT', 'Portugal'),
    ('IT', 'Italien'),
    ('GB', 'Großbritannien'),
    ('TR', 'Türkei'),
    ('US', 'USA'),
  ];

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _sportsFuture ??= AirmiusServicesScope.of(
      context,
    ).repositories.sports.sports().then((page) => page.items);
  }

  @override
  void dispose() {
    for (final controller in [
      _name,
      _sportType,
      _officialNumber,
      _city,
      _postalCode,
      _state,
      _street,
      _houseNumber,
      _accountHolder,
      _iban,
      _bic,
    ]) {
      controller.dispose();
    }
    super.dispose();
  }

  void _next() {
    if (_step == 1 && _name.text.trim().isEmpty) {
      setState(
        () => _notice = AirmiusScope.of(context).t('clubs.wizard.nameRequired'),
      );
      return;
    }
    setState(() {
      _notice = null;
      _step = (_step + 1).clamp(1, 3).toInt();
    });
  }

  void _back() {
    setState(() {
      _notice = null;
      _step = (_step - 1).clamp(1, 3).toInt();
    });
  }

  String? _nullable(String value) {
    final trimmed = value.trim();
    return trimmed.isEmpty ? null : trimmed;
  }

  Future<void> _save() async {
    if (_name.text.trim().isEmpty) {
      setState(() {
        _step = 1;
        _notice = AirmiusScope.of(context).t('clubs.wizard.nameRequired');
      });
      return;
    }

    setState(() {
      _saving = true;
      _notice = null;
    });

    try {
      await AirmiusServicesScope.of(context).repositories.clubs.createClub({
        'name': _name.text.trim(),
        'sport_type': _selectedSportSlug ?? _nullable(_sportType.text),
        'is_official': _official,
        'official_club_number': _official
            ? _nullable(_officialNumber.text)
            : null,
        'country': _country,
        'street': _nullable(_street.text),
        'house_number': _nullable(_houseNumber.text),
        'postal_code': _nullable(_postalCode.text),
        'city': _nullable(_city.text),
        'state': _nullable(_state.text),
        'sepa_account_holder': _nullable(_accountHolder.text),
        'sepa_iban': _nullable(_iban.text),
        'sepa_bic': _nullable(_bic.text),
        'is_listed': true,
        'teams_are_listed': true,
        'members_can_post_to_club': true,
        'members_can_post_to_teams': true,
      });
      if (mounted) Navigator.pop(context, true);
    } catch (error) {
      if (mounted) {
        setState(
          () => _notice =
              '${AirmiusScope.of(context).t('clubs.dataSaveFailed')}: ${_safeClubError(context, error)}',
        );
      }
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      body: SafeArea(
        child: Column(
          children: [
            _WizardHeader(
              step: _step,
              onClose: _saving ? null : () => Navigator.pop(context, false),
              onStep: (step) => setState(() => _step = step),
            ),
            Expanded(
              child: ListView(
                padding: const EdgeInsets.all(16),
                children: [
                  if (_notice != null) ...[
                    _NoticeBox(text: _notice!),
                    const SizedBox(height: 14),
                  ],
                  if (_step == 1) _basisdaten(),
                  if (_step == 2) _adresse(),
                  if (_step == 3) _pruefen(),
                ],
              ),
            ),
            _WizardFooter(
              step: _step,
              saving: _saving,
              onBack: _back,
              onNext: _next,
              onSave: _save,
            ),
          ],
        ),
      ),
    );
  }

  Widget _basisdaten() {
    final t = AirmiusScope.of(context).t;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        _WizardIntro(
          title: t('clubs.wizard.basicData'),
          body: t('clubs.wizard.basicDataBody'),
        ),
        const SizedBox(height: 14),
        _WizardField(
          controller: _name,
          label: t('clubs.wizard.clubName'),
          placeholder: t('clubs.wizard.clubName'),
        ),
        const SizedBox(height: 12),
        FutureBuilder<List<AirmiusSport>>(
          future: _sportsFuture,
          builder: (context, snapshot) {
            return _SportAutocompleteField(
              controller: _sportType,
              sports: snapshot.data ?? const [],
              loading: snapshot.connectionState == ConnectionState.waiting,
              onTextChanged: () => _selectedSportSlug = null,
              onSelected: (sport) {
                _selectedSportSlug = sport.slug.isNotEmpty
                    ? sport.slug
                    : sport.name;
                _sportType.text = sport.name;
              },
            );
          },
        ),
        const SizedBox(height: 12),
        _OfficialTile(
          value: _official,
          onChanged: (value) => setState(() => _official = value),
        ),
        if (_official) ...[
          const SizedBox(height: 12),
          _WizardField(
            controller: _officialNumber,
            label: t('clubs.wizard.officialNumber'),
            placeholder: t('clubs.wizard.officialNumberHint'),
          ),
        ],
        const SizedBox(height: 12),
        _CountryField(
          value: _country,
          countries: _countries,
          onChanged: (value) => setState(() => _country = value),
        ),
      ],
    );
  }

  Widget _adresse() {
    final t = AirmiusScope.of(context).t;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        _WizardIntro(
          title: t('clubs.wizard.addressBank'),
          body: t('clubs.wizard.addressBankBody'),
        ),
        const SizedBox(height: 14),
        _WizardField(controller: _city, placeholder: t('clubs.city')),
        const SizedBox(height: 12),
        _WizardField(
          controller: _postalCode,
          placeholder: t('clubs.postalCode'),
        ),
        const SizedBox(height: 12),
        _WizardField(controller: _state, placeholder: t('clubs.wizard.region')),
        const SizedBox(height: 12),
        _WizardField(
          controller: _street,
          placeholder: t('clubs.wizard.street'),
        ),
        const SizedBox(height: 12),
        _WizardField(
          controller: _houseNumber,
          placeholder: t('clubs.wizard.houseNumber'),
        ),
        const SizedBox(height: 14),
        AirmiusPanel(
          padding: const EdgeInsets.all(14),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text(
                t('clubs.wizard.bankAccount').toUpperCase(),
                style: TextStyle(
                  color: airmiusMutedColor(context),
                  fontSize: 11,
                  fontWeight: FontWeight.w900,
                ),
              ),
              const SizedBox(height: 6),
              Text(
                t('clubs.wizard.bankAccountBody'),
                style: TextStyle(
                  color: airmiusMutedColor(context),
                  fontSize: 12,
                  height: 1.35,
                ),
              ),
              const SizedBox(height: 12),
              _WizardField(
                controller: _accountHolder,
                placeholder: t('clubs.wizard.accountHolder'),
              ),
              const SizedBox(height: 12),
              _WizardField(controller: _iban, placeholder: 'IBAN'),
              const SizedBox(height: 12),
              _WizardField(controller: _bic, placeholder: 'BIC'),
            ],
          ),
        ),
      ],
    );
  }

  Widget _pruefen() {
    final t = AirmiusScope.of(context).t;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        _WizardIntro(
          title: t('clubs.wizard.review'),
          body: t('clubs.wizard.reviewBody'),
        ),
        const SizedBox(height: 14),
        AirmiusPanel(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              _ReviewLine(label: t('clubs.club'), value: _name.text),
              _ReviewLine(label: t('clubs.sport'), value: _sportType.text),
              _ReviewLine(
                label: t('clubs.wizard.officialReview'),
                value: _official
                    ? t('clubs.wizard.requested')
                    : t('clubs.wizard.notRequested'),
              ),
              if (_official)
                _ReviewLine(
                  label: t('clubs.wizard.officialNumber'),
                  value: _officialNumber.text,
                ),
              _ReviewLine(
                label: t('clubs.wizard.statusAfterSubmit'),
                value: t('clubs.awaitingReview'),
              ),
              _ReviewLine(label: t('clubs.wizard.country'), value: _country),
              _ReviewLine(
                label: t('clubs.wizard.address'),
                value:
                    '${_street.text} ${_houseNumber.text}, ${_postalCode.text} ${_city.text}',
              ),
              _ReviewLine(label: t('clubs.wizard.region'), value: _state.text),
              _ReviewLine(
                label: t('clubs.wizard.accountHolder'),
                value: _accountHolder.text,
              ),
              _ReviewLine(label: 'IBAN', value: _iban.text),
              _ReviewLine(label: 'BIC', value: _bic.text, last: true),
            ],
          ),
        ),
      ],
    );
  }
}

class _WizardHeader extends StatelessWidget {
  const _WizardHeader({
    required this.step,
    required this.onClose,
    required this.onStep,
  });

  final int step;
  final VoidCallback? onClose;
  final ValueChanged<int> onStep;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return Container(
      decoration: BoxDecoration(
        color: airmiusSurfaceColor(context),
        border: Border(bottom: BorderSide(color: airmiusBorderColor(context))),
      ),
      padding: const EdgeInsets.fromLTRB(16, 14, 16, 14),
      child: Column(
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      t('clubs.register'),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: TextStyle(
                        color: airmiusTextColor(context),
                        fontSize: 18,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      '${t('clubs.wizard.step')} $step ${t('clubs.wizard.of')} 3',
                      style: TextStyle(
                        color: airmiusMutedColor(context),
                        fontSize: 13,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ],
                ),
              ),
              IconButton(
                tooltip: t('clubs.wizard.close'),
                onPressed: onClose,
                icon: Icon(
                  Icons.close_rounded,
                  color: airmiusMutedColor(context),
                ),
              ),
            ],
          ),
          const SizedBox(height: 14),
          Row(
            children: [
              Expanded(
                child: _StepPill(
                  label: t('clubs.wizard.basicData'),
                  active: step == 1,
                  done: step > 1,
                  onTap: () => onStep(1),
                ),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: _StepPill(
                  label: t('clubs.wizard.address'),
                  active: step == 2,
                  done: step > 2,
                  onTap: () => onStep(2),
                ),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: _StepPill(
                  label: t('clubs.wizard.review'),
                  active: step == 3,
                  done: false,
                  onTap: () => onStep(3),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _StepPill extends StatelessWidget {
  const _StepPill({
    required this.label,
    required this.active,
    required this.done,
    required this.onTap,
  });

  final String label;
  final bool active;
  final bool done;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(99),
      child: Container(
        height: 34,
        alignment: Alignment.center,
        decoration: BoxDecoration(
          color: active
              ? airmiusAccentColor(context)
              : done
              ? Theme.of(context).colorScheme.secondary.withValues(alpha: .16)
              : airmiusInputColor(context),
          borderRadius: BorderRadius.circular(99),
        ),
        child: Text(
          label,
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          style: TextStyle(
            color: active
                ? airmiusOnColor(airmiusAccentColor(context))
                : done
                ? Theme.of(context).colorScheme.secondary
                : airmiusMutedColor(context),
            fontSize: 12,
            fontWeight: FontWeight.w900,
          ),
        ),
      ),
    );
  }
}

class _WizardFooter extends StatelessWidget {
  const _WizardFooter({
    required this.step,
    required this.saving,
    required this.onBack,
    required this.onNext,
    required this.onSave,
  });

  final int step;
  final bool saving;
  final VoidCallback onBack;
  final VoidCallback onNext;
  final VoidCallback onSave;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: airmiusSurfaceColor(context),
        border: Border(top: BorderSide(color: airmiusBorderColor(context))),
      ),
      child: Row(
        children: [
          Expanded(
            child: OutlinedButton(
              onPressed: step == 1 || saving ? null : onBack,
              style: OutlinedButton.styleFrom(
                foregroundColor: airmiusMutedColor(context),
                side: BorderSide(color: airmiusBorderColor(context)),
                padding: const EdgeInsets.symmetric(vertical: 14),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(8),
                ),
              ),
              child: Text(
                t('clubs.wizard.back'),
                style: TextStyle(fontWeight: FontWeight.w900),
              ),
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: FilledButton(
              onPressed: saving ? null : (step < 3 ? onNext : onSave),
              style: FilledButton.styleFrom(
                backgroundColor: airmiusAccentColor(context),
                foregroundColor: airmiusOnColor(airmiusAccentColor(context)),
                padding: const EdgeInsets.symmetric(vertical: 14),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(8),
                ),
              ),
              child: Text(
                saving
                    ? t('clubs.saving')
                    : (step < 3 ? t('clubs.wizard.next') : t('clubs.save')),
                style: TextStyle(fontWeight: FontWeight.w900),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _WizardIntro extends StatelessWidget {
  const _WizardIntro({required this.title, required this.body});

  final String title;
  final String body;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          title,
          style: TextStyle(
            color: airmiusTextColor(context),
            fontSize: 16,
            fontWeight: FontWeight.w900,
          ),
        ),
        const SizedBox(height: 6),
        Text(
          body,
          style: TextStyle(color: airmiusMutedColor(context), height: 1.35),
        ),
      ],
    );
  }
}

class _WizardField extends StatelessWidget {
  const _WizardField({
    required this.controller,
    this.label,
    required this.placeholder,
  });

  final TextEditingController controller;
  final String? label;
  final String placeholder;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        if (label != null) ...[
          Text(
            label!,
            style: TextStyle(
              color: airmiusTextColor(context),
              fontSize: 13,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 6),
        ],
        TextField(
          controller: controller,
          style: TextStyle(
            color: airmiusTextColor(context),
            fontWeight: FontWeight.w800,
          ),
          decoration: InputDecoration(hintText: placeholder),
        ),
      ],
    );
  }
}

class _SportAutocompleteField extends StatelessWidget {
  const _SportAutocompleteField({
    required this.controller,
    required this.sports,
    required this.loading,
    required this.onTextChanged,
    required this.onSelected,
  });

  final TextEditingController controller;
  final List<AirmiusSport> sports;
  final bool loading;
  final VoidCallback onTextChanged;
  final ValueChanged<AirmiusSport> onSelected;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    if (sports.isEmpty) {
      return _WizardField(
        controller: controller,
        label: t('clubs.sport'),
        placeholder: loading
            ? t('teamDetail.sportsLoading')
            : t('teamDetail.searchSport'),
      );
    }

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          t('clubs.sport'),
          style: TextStyle(
            color: airmiusTextColor(context),
            fontSize: 13,
            fontWeight: FontWeight.w900,
          ),
        ),
        const SizedBox(height: 6),
        Autocomplete<AirmiusSport>(
          initialValue: TextEditingValue(text: controller.text),
          displayStringForOption: (sport) => sport.name,
          optionsBuilder: (value) {
            final query = value.text.trim().toLowerCase();
            final options = query.isEmpty
                ? sports
                : sports.where((sport) {
                    final name = sport.name.toLowerCase();
                    final slug = sport.slug.toLowerCase();
                    return name.contains(query) || slug.contains(query);
                  });
            return options.take(10);
          },
          onSelected: onSelected,
          fieldViewBuilder:
              (context, textController, focusNode, onFieldSubmitted) {
                if (textController.text.isEmpty && controller.text.isNotEmpty) {
                  textController.text = controller.text;
                }
                return TextField(
                  controller: textController,
                  focusNode: focusNode,
                  style: TextStyle(
                    color: airmiusTextColor(context),
                    fontWeight: FontWeight.w800,
                  ),
                  decoration: InputDecoration(
                    hintText: t('teamDetail.searchSport'),
                    suffixIcon: Icon(
                      Icons.search,
                      color: airmiusMutedColor(context),
                    ),
                  ),
                  onChanged: (value) {
                    controller.text = value;
                    onTextChanged();
                  },
                );
              },
          optionsViewBuilder: (context, onSelected, options) {
            final items = options.toList();
            final menuWidth = (MediaQuery.of(context).size.width - 32)
                .clamp(180.0, 520.0)
                .toDouble();
            return Align(
              alignment: Alignment.topLeft,
              child: Material(
                color: Colors.transparent,
                child: Container(
                  width: menuWidth,
                  margin: const EdgeInsets.only(top: 6),
                  constraints: const BoxConstraints(maxHeight: 260),
                  decoration: BoxDecoration(
                    color: airmiusSurfaceColor(context),
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(color: airmiusBorderColor(context)),
                    boxShadow: [
                      BoxShadow(
                        color: Theme.of(
                          context,
                        ).shadowColor.withValues(alpha: .35),
                        blurRadius: 18,
                        offset: const Offset(0, 10),
                      ),
                    ],
                  ),
                  child: ListView.separated(
                    padding: const EdgeInsets.symmetric(vertical: 6),
                    shrinkWrap: true,
                    itemCount: items.length,
                    separatorBuilder: (_, _) =>
                        Divider(height: 1, color: airmiusBorderColor(context)),
                    itemBuilder: (context, index) {
                      final sport = items[index];
                      return ListTile(
                        dense: true,
                        onTap: () => onSelected(sport),
                        leading: Icon(
                          Icons.sports_outlined,
                          color: airmiusAccentColor(context),
                        ),
                        title: Text(
                          sport.name,
                          style: TextStyle(
                            color: airmiusTextColor(context),
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                        subtitle: sport.slug.isEmpty
                            ? null
                            : Text(
                                sport.slug,
                                style: TextStyle(
                                  color: airmiusMutedColor(context),
                                  fontSize: 12,
                                ),
                              ),
                      );
                    },
                  ),
                ),
              ),
            );
          },
        ),
      ],
    );
  }
}

class _CountryField extends StatelessWidget {
  const _CountryField({
    required this.value,
    required this.countries,
    required this.onChanged,
  });

  final String value;
  final List<(String, String)> countries;
  final ValueChanged<String> onChanged;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          t('clubs.wizard.country'),
          style: TextStyle(
            color: airmiusTextColor(context),
            fontSize: 13,
            fontWeight: FontWeight.w900,
          ),
        ),
        const SizedBox(height: 6),
        DropdownButtonFormField<String>(
          initialValue: value,
          dropdownColor: airmiusSurfaceColor(context),
          decoration: const InputDecoration(),
          style: TextStyle(
            color: airmiusTextColor(context),
            fontWeight: FontWeight.w800,
          ),
          items: [
            for (final country in countries)
              DropdownMenuItem(
                value: country.$1,
                child: Text(t('clubs.country.${country.$1.toLowerCase()}')),
              ),
          ],
          onChanged: (value) {
            if (value != null) onChanged(value);
          },
        ),
      ],
    );
  }
}

class _OfficialTile extends StatelessWidget {
  const _OfficialTile({required this.value, required this.onChanged});

  final bool value;
  final ValueChanged<bool> onChanged;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return Material(
      color: Colors.transparent,
      child: InkWell(
        borderRadius: BorderRadius.circular(8),
        onTap: () => onChanged(!value),
        child: Container(
          padding: const EdgeInsets.all(12),
          decoration: BoxDecoration(
            color: value
                ? airmiusAccentColor(context).withValues(alpha: .1)
                : Theme.of(context).scaffoldBackgroundColor,
            borderRadius: BorderRadius.circular(8),
            border: Border.all(
              color: value
                  ? airmiusAccentColor(context)
                  : airmiusBorderColor(context),
            ),
          ),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                width: 24,
                height: 24,
                decoration: BoxDecoration(
                  color: value
                      ? airmiusAccentColor(context)
                      : airmiusInputColor(context),
                  borderRadius: BorderRadius.circular(7),
                  border: Border.all(
                    color: value
                        ? airmiusAccentColor(context)
                        : airmiusBorderColor(context),
                  ),
                ),
                child: value
                    ? Icon(
                        Icons.check,
                        color: airmiusOnColor(airmiusAccentColor(context)),
                        size: 16,
                      )
                    : null,
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      t('clubs.wizard.requestOfficialReview'),
                      style: TextStyle(
                        color: airmiusTextColor(context),
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      t('clubs.wizard.requestOfficialReviewBody'),
                      style: TextStyle(
                        color: airmiusMutedColor(context),
                        fontSize: 12,
                        height: 1.35,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _NoticeBox extends StatelessWidget {
  const _NoticeBox({required this.text});

  final String text;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: Theme.of(context).colorScheme.error.withValues(alpha: .12),
        borderRadius: BorderRadius.circular(8),
        border: Border.all(
          color: Theme.of(context).colorScheme.error.withValues(alpha: .4),
        ),
      ),
      child: Text(
        text,
        style: TextStyle(
          color: Theme.of(context).colorScheme.error,
          fontWeight: FontWeight.w800,
          height: 1.35,
        ),
      ),
    );
  }
}

class _ReviewLine extends StatelessWidget {
  const _ReviewLine({
    required this.label,
    required this.value,
    this.last = false,
  });

  final String label;
  final String value;
  final bool last;

  @override
  Widget build(BuildContext context) {
    final display = value.trim().isEmpty || value.trim() == ','
        ? '-'
        : value.trim();
    return Padding(
      padding: EdgeInsets.only(bottom: last ? 0 : 10),
      child: RichText(
        text: TextSpan(
          style: TextStyle(
            color: airmiusMutedColor(context),
            fontSize: 14,
            height: 1.3,
          ),
          children: [
            TextSpan(
              text: '$label: ',
              style: TextStyle(
                color: airmiusTextColor(context),
                fontWeight: FontWeight.w900,
              ),
            ),
            TextSpan(text: display),
          ],
        ),
      ),
    );
  }
}

class _CreateClubButton extends StatelessWidget {
  const _CreateClubButton({required this.onPressed});

  final VoidCallback onPressed;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return FilledButton.icon(
      onPressed: onPressed,
      icon: Icon(Icons.add, size: 18),
      label: Text(
        t('clubs.register'),
        style: TextStyle(fontWeight: FontWeight.w900),
      ),
      style: FilledButton.styleFrom(
        backgroundColor: airmiusTextColor(context),
        foregroundColor: airmiusSurfaceColor(context),
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 13),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
      ),
    );
  }
}

class _ClubWorkspaceNav extends StatelessWidget {
  const _ClubWorkspaceNav({required this.canManageClubs});

  final bool canManageClubs;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return AirmiusPanel(
      padding: const EdgeInsets.fromLTRB(14, 14, 14, 14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            t('clubs.workspace.area').toUpperCase(),
            style: TextStyle(
              color: airmiusMutedColor(context),
              fontSize: 11,
              letterSpacing: .4,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 7),
          Text(
            t('clubs.workspace.description'),
            style: TextStyle(
              color: airmiusMutedColor(context),
              fontSize: 13,
              height: 1.35,
              fontWeight: FontWeight.w600,
            ),
          ),
          const SizedBox(height: 12),
          Row(
            children: [
              if (canManageClubs) ...[
                Expanded(
                  child: _WorkspaceTab(
                    icon: Icons.speed_outlined,
                    label: t('clubs.workspace.cockpit'),
                    selected: false,
                    onTap: () => Navigator.push(
                      context,
                      MaterialPageRoute(
                        builder: (_) => const ClubCockpitScreen(),
                      ),
                    ),
                  ),
                ),
                const SizedBox(width: 8),
              ],
              Expanded(
                child: _WorkspaceTab(
                  icon: Icons.account_tree_outlined,
                  label: t('clubs.workspace.title'),
                  selected: true,
                  onTap: () {},
                ),
              ),
              if (canManageClubs) ...[
                const SizedBox(width: 8),
                Expanded(
                  child: _WorkspaceTab(
                    icon: Icons.badge_outlined,
                    label: t('clubs.workspace.membersFinance'),
                    selected: false,
                    onTap: () => Navigator.push(
                      context,
                      MaterialPageRoute(
                        builder: (_) => const ClubMembershipManagementScreen(),
                      ),
                    ),
                  ),
                ),
              ],
            ],
          ),
        ],
      ),
    );
  }
}

class _WorkspaceTab extends StatelessWidget {
  const _WorkspaceTab({
    required this.icon,
    required this.label,
    required this.selected,
    required this.onTap,
  });

  final IconData icon;
  final String label;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final color = selected
        ? airmiusSurfaceColor(context)
        : airmiusTextColor(context);
    return Material(
      color: Colors.transparent,
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(4),
        child: Container(
          height: 44,
          padding: const EdgeInsets.symmetric(horizontal: 7),
          decoration: BoxDecoration(
            color: selected
                ? airmiusTextColor(context)
                : airmiusSurfaceColor(context),
            borderRadius: BorderRadius.circular(8),
            border: Border.all(
              color: selected
                  ? airmiusTextColor(context)
                  : airmiusBorderColor(context),
            ),
          ),
          child: Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Icon(icon, size: 15, color: color),
              const SizedBox(width: 5),
              Flexible(
                child: Text(
                  label,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  textAlign: TextAlign.center,
                  style: TextStyle(
                    color: color,
                    fontSize: 12,
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _ClubCard extends StatefulWidget {
  const _ClubCard({
    required this.club,
    required this.showManageActions,
    required this.requested,
    required this.onRequest,
    required this.onWithdraw,
    required this.onReload,
  });

  final ClubSummary club;
  final bool showManageActions;
  final bool requested;
  final ValueChanged<ClubSummary> onRequest;
  final ValueChanged<ClubSummary> onWithdraw;
  final VoidCallback onReload;

  @override
  State<_ClubCard> createState() => _ClubCardState();
}

class _ClubCardState extends State<_ClubCard> {
  bool _expanded = false;
  String? _panel;
  Future<ClubSummary>? _detailFuture;

  ClubSummary get club => widget.club;

  void _toggleExpanded() {
    setState(() {
      _expanded = !_expanded;
      if (_expanded) {
        _detailFuture ??= _loadDetail();
      } else {
        _panel = null;
      }
    });
  }

  Future<ClubSummary> _loadDetail() async {
    final services = AirmiusServicesScope.of(context);
    final detail = await services.repositories.clubs.club(club.id);
    return ClubSummary.fromAirmiusClub(detail);
  }

  void _reloadDetail() {
    setState(() {
      _detailFuture = _loadDetail();
    });
    widget.onReload();
  }

  void _openPanel(String panel) {
    setState(() {
      _expanded = true;
      _panel = _panel == panel ? null : panel;
      _detailFuture ??= _loadDetail();
    });
  }

  Future<void> _deleteClub() async {
    final t = AirmiusScope.of(context).t;
    final confirmed = await confirmDanger(
      context,
      '${t('clubs.deleteClub')} "${club.name}"',
      t('clubs.deleteWarning'),
      t('common.delete'),
    );
    if (confirmed != true || !mounted) return;

    try {
      await AirmiusServicesScope.of(
        context,
      ).repositories.clubs.deleteClub(club.id);
      if (!mounted) return;
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(t('clubs.deleted'))));
      widget.onReload();
    } catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            '${t('clubs.deleteFailed')}: ${_safeClubError(context, error)}',
          ),
        ),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return AirmiusPanel(
      onTap: _toggleExpanded,
      padding: const EdgeInsets.all(14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              _InitialsCircle(club.name, size: 44),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      club.name,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: TextStyle(
                        color: airmiusTextColor(context),
                        fontSize: 17,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(height: 3),
                    Text(
                      _clubMeta(club),
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: TextStyle(
                        color: airmiusMutedColor(context),
                        fontSize: 12,
                        height: 1.25,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
          if (widget.showManageActions ||
              club.canDelete ||
              widget.requested) ...[
            const SizedBox(height: 12),
            LayoutBuilder(
              builder: (context, constraints) {
                final actions = <Widget>[
                  if (widget.showManageActions)
                    _InlineAction(
                      icon: Icons.edit_outlined,
                      label: t('clubs.editData'),
                      onPressed: () => _openPanel('edit'),
                    ),
                  if (widget.showManageActions)
                    _InlineAction(
                      icon: Icons.add,
                      label: t('clubs.addTeamShort'),
                      onPressed: () => _openPanel('team'),
                    ),
                  if (club.canDelete || widget.showManageActions)
                    _InlineAction(
                      icon: Icons.delete_outline,
                      label: t('common.delete'),
                      danger: true,
                      onPressed: _deleteClub,
                    ),
                  if (widget.requested && !club.canManage)
                    _InlineAction(
                      icon: Icons.pending_actions_outlined,
                      label: t('clubs.requestOpen'),
                      onPressed: null,
                    ),
                ];
                final columns = constraints.maxWidth >= 420
                    ? actions.length
                    : 2;
                final gap = 8.0;
                final width =
                    (constraints.maxWidth - gap * (columns - 1)) / columns;
                return Wrap(
                  spacing: gap,
                  runSpacing: gap,
                  children: [
                    for (final action in actions)
                      SizedBox(width: width, child: action),
                  ],
                );
              },
            ),
          ],
          if (_expanded) ...[
            const SizedBox(height: 14),
            FutureBuilder<ClubSummary>(
              future: _detailFuture,
              builder: (context, snapshot) {
                final detail = snapshot.data ?? club;
                return _ClubInlineWorkspace(
                  club: detail,
                  panel: _panel,
                  onEdit: () => _openPanel('edit'),
                  onTeam: () => _openPanel('team'),
                  onOpenProfile: () => Navigator.push(
                    context,
                    MaterialPageRoute(
                      builder: (_) => ClubProfileScreen(
                        club: detail,
                        requested: widget.requested,
                        onRequest: widget.onRequest,
                        onWithdraw: widget.onWithdraw,
                      ),
                    ),
                  ).then((_) => widget.onReload()),
                  onReload: _reloadDetail,
                );
              },
            ),
          ],
        ],
      ),
    );
  }

  String _clubMeta(ClubSummary club) {
    final t = AirmiusScope.of(context).t;
    final sport = (club.sportType == null || club.sportType!.trim().isEmpty)
        ? t('clubs.sportOpen')
        : club.sportType!.trim();
    final location = [
      if (club.city.trim().isNotEmpty)
        club.city.trim()
      else
        t('clubs.locationOpen'),
      if (club.postalCode?.trim().isNotEmpty == true) club.postalCode!.trim(),
    ].join(' ');
    final country = club.country?.trim().isNotEmpty == true
        ? club.country!.trim()
        : t('clubs.countryOpen');
    return '$sport - $location - $country - ${club.teams} ${t('clubs.teams')}';
  }
}

class _ClubInlineWorkspace extends StatelessWidget {
  const _ClubInlineWorkspace({
    required this.club,
    required this.panel,
    required this.onEdit,
    required this.onTeam,
    required this.onOpenProfile,
    required this.onReload,
  });

  final ClubSummary club;
  final String? panel;
  final VoidCallback onEdit;
  final VoidCallback onTeam;
  final VoidCallback onOpenProfile;
  final VoidCallback onReload;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        if (panel == 'edit') ...[
          _ClubEditInlinePanel(
            club: club,
            onOpenProfile: onOpenProfile,
            onSaved: onReload,
          ),
          const SizedBox(height: 12),
        ],
        if (panel == 'team') ...[
          _TeamCreateInlinePanel(club: club, onCreated: onReload),
          const SizedBox(height: 12),
        ],
        _InlineSection(
          title: t('clubs.teams'),
          subtitle:
              '${club.teamList.isNotEmpty ? club.teamList.length : club.teams} ${t('clubs.teams')}',
          action: club.canManage
              ? _SmallInlineButton(
                  label: t('clubs.addTeamShort'),
                  onPressed: onTeam,
                )
              : null,
          child: club.teamList.isEmpty
              ? Text(
                  t('clubs.noTeams'),
                  style: TextStyle(
                    color: airmiusMutedColor(context),
                    fontWeight: FontWeight.w600,
                  ),
                )
              : LayoutBuilder(
                  builder: (context, constraints) {
                    final columns = constraints.maxWidth >= 520 ? 2 : 1;
                    const gap = 10.0;
                    final width =
                        (constraints.maxWidth - gap * (columns - 1)) / columns;
                    return Wrap(
                      spacing: gap,
                      runSpacing: gap,
                      children: [
                        for (final team in club.teamList)
                          SizedBox(
                            width: width,
                            child: _InlineTeamCard(
                              team: team,
                              onTap: () => Navigator.push(
                                context,
                                MaterialPageRoute(
                                  builder: (_) => TeamDetailScreen(
                                    title: team.name,
                                    mode: 'Profil',
                                    teamId: team.id,
                                  ),
                                ),
                              ),
                            ),
                          ),
                      ],
                    );
                  },
                ),
        ),
        const SizedBox(height: 12),
        _InlineSection(
          title: t('clubs.clubMembers'),
          subtitle:
              '${club.management?.linkedPeopleCount ?? club.members} ${t('clubs.peopleInManagement')}',
          action: club.canManage
              ? _SmallInlineButton(
                  label: t('clubs.editData'),
                  onPressed: onEdit,
                )
              : null,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              _InlineMetricLine(
                icon: Icons.groups_outlined,
                title: t('clubs.activeMembers'),
                value: '${club.management?.activeMembersCount ?? club.members}',
              ),
              const SizedBox(height: 8),
              _InlineMetricLine(
                icon: Icons.account_tree_outlined,
                title: t('clubs.teams'),
                value: '${club.teams}',
              ),
              const SizedBox(height: 8),
              _InlineMetricLine(
                icon: Icons.person_add_alt_1_outlined,
                title: t('clubs.openClubRequests'),
                value: '${club.pendingMembershipRequests}',
              ),
              const SizedBox(height: 8),
              _InlineMetricLine(
                icon: Icons.verified_outlined,
                title: t('clubs.status'),
                value: club.verified
                    ? t('clubs.approved')
                    : t('clubs.awaitingReview'),
              ),
            ],
          ),
        ),
        const SizedBox(height: 12),
        if (club.canManage && club.management != null) ...[
          _ClubManagementSection(club: club, onReload: onReload),
          const SizedBox(height: 12),
        ],
        _InlineSection(
          title: t('clubs.quickAccess'),
          subtitle: t('clubs.quickAccessBody'),
          child: Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              _SmallInlineButton(
                label: t('clubs.clubProfile'),
                onPressed: onOpenProfile,
              ),
              _SmallInlineButton(label: t('clubs.reload'), onPressed: onReload),
            ],
          ),
        ),
      ],
    );
  }
}

class _ClubManagementSection extends StatefulWidget {
  const _ClubManagementSection({required this.club, required this.onReload});

  final ClubSummary club;
  final VoidCallback onReload;

  @override
  State<_ClubManagementSection> createState() => _ClubManagementSectionState();
}

class _ClubManagementSectionState extends State<_ClubManagementSection> {
  int? _updatingMemberId;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final club = widget.club;
    final management = club.management!;
    final permissionMap = management.permissions['effective'];
    final canEditRoles =
        permissionMap is JsonMap && permissionMap['members.roles'] == true;
    return _InlineSection(
      title: t('clubs.managementData'),
      subtitle: t('clubs.managementDataBody'),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          LayoutBuilder(
            builder: (context, constraints) {
              final columns = constraints.maxWidth >= 520 ? 2 : 1;
              const gap = 8.0;
              final width =
                  (constraints.maxWidth - gap * (columns - 1)) / columns;
              return Wrap(
                spacing: gap,
                runSpacing: gap,
                children: [
                  SizedBox(
                    width: width,
                    child: _ManagementMetricTile(
                      title: t('clubs.open'),
                      value: _formatMoney(management.openInvoiceAmount),
                      subtitle:
                          '${management.openInvoicesCount} ${t('clubs.invoices')}',
                    ),
                  ),
                  SizedBox(
                    width: width,
                    child: _ManagementMetricTile(
                      title: t('clubs.sepaReady'),
                      value: '${management.sepaReadyMembersCount}',
                      subtitle: t('clubs.sepaReadyBody'),
                    ),
                  ),
                  SizedBox(
                    width: width,
                    child: _ManagementMetricTile(
                      title: t('clubs.recurringContributions'),
                      value: _formatMoney(
                        management.recurringContributionTotal,
                      ),
                      subtitle: t('clubs.recurringContributionsBody'),
                    ),
                  ),
                  SizedBox(
                    width: width,
                    child: _ManagementMetricTile(
                      title: t('clubs.rules'),
                      value: '${club.contributionRulesCount}',
                      subtitle:
                          '${club.membershipTypesCount} ${t('clubs.membershipTypes')}',
                    ),
                  ),
                  SizedBox(
                    width: width,
                    child: _ManagementMetricTile(
                      title: t('clubs.payments'),
                      value: '${club.paymentsCount}',
                      subtitle:
                          '${club.bankTransactionsCount} ${t('clubs.bankTransactions')}',
                    ),
                  ),
                  SizedBox(
                    width: width,
                    child: _ManagementMetricTile(
                      title: t('clubs.externalMembers'),
                      value: '${club.externalMembersCount}',
                      subtitle: t('clubs.externalMembersBody'),
                    ),
                  ),
                ],
              );
            },
          ),
          if (management.canManageMembers &&
              management.membershipRequests.isNotEmpty) ...[
            const SizedBox(height: 12),
            Text(
              t('clubs.openMembershipRequests'),
              style: TextStyle(
                color: airmiusTextColor(context),
                fontWeight: FontWeight.w900,
              ),
            ),
            const SizedBox(height: 8),
            for (final request in management.membershipRequests.take(4)) ...[
              _MembershipRequestCard(
                request: request,
                onApprove: () =>
                    _reviewRequest(context, request, approve: true),
                onDecline: () =>
                    _reviewRequest(context, request, approve: false),
              ),
              const SizedBox(height: 8),
            ],
          ],
          if (management.canManageMembers &&
              management.pendingTeamJoinRequests.isNotEmpty) ...[
            const SizedBox(height: 8),
            Text(
              t('clubs.openTeamRequests'),
              style: TextStyle(
                color: airmiusTextColor(context),
                fontWeight: FontWeight.w900,
              ),
            ),
            const SizedBox(height: 8),
            for (final request in management.pendingTeamJoinRequests.take(
              4,
            )) ...[
              _RawRequestLine(request: request),
              const SizedBox(height: 8),
            ],
          ],
          if (management.members.isNotEmpty) ...[
            const SizedBox(height: 8),
            Text(
              t('clubs.members'),
              style: TextStyle(
                color: airmiusTextColor(context),
                fontWeight: FontWeight.w900,
              ),
            ),
            const SizedBox(height: 8),
            for (final member in management.members.take(5)) ...[
              _MemberLine(
                member: member,
                isUpdating: _updatingMemberId == member.id,
                canChangeRole: canEditRoles && member.role != 'owner',
                onRoleChanged: (role) =>
                    _updateMemberRole(context, member, role),
              ),
              const SizedBox(height: 8),
            ],
          ],
        ],
      ),
    );
  }

  Future<void> _reviewRequest(
    BuildContext context,
    AirmiusClubMembershipRequest request, {
    required bool approve,
  }) async {
    final t = AirmiusScope.of(context).t;
    final ok = approve
        ? true
        : await confirmDanger(
            context,
            t('clubs.declineRequest'),
            '${t('clubs.declineRequestBefore')} '
            '${request.applicantName ?? t('clubs.thisPerson')}?',
            t('clubs.decline'),
          );
    if (!ok || !context.mounted) return;

    try {
      final services = AirmiusServicesScope.of(context);
      if (approve) {
        await services.repositories.memberships.approveClubRequest(
          widget.club.id,
          request.id,
        );
      } else {
        await services.repositories.memberships.declineClubRequest(
          widget.club.id,
          request.id,
        );
      }
      if (!context.mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            approve ? t('clubs.requestApproved') : t('clubs.requestDeclined'),
          ),
        ),
      );
      widget.onReload();
    } catch (error) {
      if (!context.mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            '${t('clubs.requestFailed')}: ${_safeClubError(context, error)}',
          ),
        ),
      );
    }
  }

  Future<void> _updateMemberRole(
    BuildContext context,
    AirmiusClubMember member,
    String role,
  ) async {
    final t = AirmiusScope.of(context).t;
    if (_updatingMemberId != null || member.role == role) return;
    setState(() => _updatingMemberId = member.id);

    try {
      await AirmiusServicesScope.of(context).repositories.clubs
          .updateClubMemberRole(widget.club.id, member.id, role);
      if (!context.mounted) return;
      setState(() => _updatingMemberId = null);
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(t('clubs.roleUpdated'))));
      widget.onReload();
    } catch (error) {
      if (!context.mounted) return;
      setState(() => _updatingMemberId = null);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            '${t('clubs.roleUpdateFailed')}: ${_safeClubError(context, error)}',
          ),
        ),
      );
    }
  }
}

class _ManagementMetricTile extends StatelessWidget {
  const _ManagementMetricTile({
    required this.title,
    required this.value,
    required this.subtitle,
  });

  final String title;
  final String value;
  final String subtitle;

  @override
  Widget build(BuildContext context) {
    return Container(
      constraints: const BoxConstraints(minHeight: 82),
      padding: const EdgeInsets.all(11),
      decoration: BoxDecoration(
        color: airmiusSurfaceColor(context),
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: airmiusBorderColor(context)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            title.toUpperCase(),
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: TextStyle(
              color: airmiusMutedColor(context),
              fontSize: 10,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 8),
          Text(
            value,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: TextStyle(
              color: airmiusTextColor(context),
              fontSize: 18,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 4),
          Text(
            subtitle,
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
            style: TextStyle(
              color: airmiusMutedColor(context),
              fontSize: 11,
              height: 1.25,
            ),
          ),
        ],
      ),
    );
  }
}

class _MembershipRequestCard extends StatelessWidget {
  const _MembershipRequestCard({
    required this.request,
    required this.onApprove,
    required this.onDecline,
  });

  final AirmiusClubMembershipRequest request;
  final VoidCallback onApprove;
  final VoidCallback onDecline;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return Container(
      padding: const EdgeInsets.all(11),
      decoration: BoxDecoration(
        color: airmiusSurfaceColor(context),
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: airmiusBorderColor(context)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(
            request.applicantName ?? t('clubs.unknownPerson'),
            style: TextStyle(
              color: airmiusTextColor(context),
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 3),
          Text(
            request.applicantEmail ??
                request.message ??
                t('clubs.membershipRequest'),
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
            style: TextStyle(
              color: airmiusMutedColor(context),
              fontSize: 12,
              height: 1.3,
            ),
          ),
          if (request.membershipTypeName != null ||
              request.previewAmount != null) ...[
            const SizedBox(height: 6),
            Text(
              [
                if (request.membershipTypeName != null)
                  '${t('clubs.type')}: ${request.membershipTypeName}',
                if (request.previewAmount != null)
                  '${t('clubs.preview')}: ${request.previewAmount} ${request.previewInterval ?? ''}'
                      .trim(),
                if (request.previewBaseAmount != null &&
                    request.previewBaseAmount != request.previewAmount)
                  '${t('clubs.previewBase')}: ${request.previewBaseAmount}',
                if (request.previewDiscountAmount != null &&
                    request.previewDiscountAmount != '0.00')
                  '${t('clubs.previewDiscount')}: -${request.previewDiscountAmount}',
              ].join(' - '),
              style: TextStyle(
                color: airmiusMutedColor(context),
                fontSize: 11,
                fontWeight: FontWeight.w700,
              ),
            ),
          ],
          if (request.preferredPaymentMethod != null ||
              request.requestedBillingInterval != null) ...[
            const SizedBox(height: 6),
            Text(
              [
                if (request.preferredPaymentMethod != null)
                  '${t('clubs.payment')}: ${request.preferredPaymentMethod}',
                if (request.requestedBillingInterval != null)
                  '${t('clubs.interval')}: ${request.requestedBillingInterval}',
              ].join(' - '),
              style: TextStyle(
                color: airmiusMutedColor(context),
                fontSize: 11,
                fontWeight: FontWeight.w700,
              ),
            ),
          ],
          const SizedBox(height: 10),
          Row(
            children: [
              Expanded(
                child: _SmallInlineButton(
                  label: t('clubs.accept'),
                  filled: true,
                  onPressed: onApprove,
                ),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: _SmallInlineButton(
                  label: t('clubs.decline'),
                  onPressed: onDecline,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _RawRequestLine extends StatelessWidget {
  const _RawRequestLine({required this.request});

  final JsonMap request;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final user = request['user'];
    final team = request['team'];
    final userName = user is JsonMap
        ? (user['name']?.toString() ?? t('clubs.unknown'))
        : t('clubs.unknown');
    final teamName = team is JsonMap
        ? (team['name']?.toString() ?? t('teams'))
        : t('teams');
    return _InlineMetricLine(
      icon: Icons.group_add_outlined,
      title: '$userName ${t('clubs.wantsToJoin')} $teamName',
      value: t('clubs.open'),
    );
  }
}

class _MemberLine extends StatelessWidget {
  const _MemberLine({
    required this.member,
    required this.isUpdating,
    required this.canChangeRole,
    required this.onRoleChanged,
  });

  final AirmiusClubMember member;
  final bool isUpdating;
  final bool canChangeRole;
  final ValueChanged<String> onRoleChanged;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final status = member.status?.isNotEmpty == true
        ? member.status!
        : t('clubs.active');
    final role = _clubRoleValues.contains(member.role)
        ? member.role!
        : 'member';
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: airmiusSurfaceSoftColor(context),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: airmiusBorderColor(context)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              AirmiusAvatar(member.name, imageUrl: member.avatarUrl),
              const SizedBox(width: 10),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      member.name,
                      style: TextStyle(
                        color: airmiusTextColor(context),
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    Text(
                      member.email.isNotEmpty ? member.email : status,
                      style: TextStyle(color: airmiusMutedColor(context)),
                    ),
                  ],
                ),
              ),
              StatusPill(
                _clubRoleLabel(role, t),
                color: _clubRoleColor(context, role),
              ),
            ],
          ),
          const SizedBox(height: 10),
          DropdownButtonFormField<String>(
            initialValue: role,
            isExpanded: true,
            dropdownColor: airmiusSurfaceColor(context),
            decoration: InputDecoration(
              labelText: isUpdating ? t('clubs.saving') : t('clubs.clubRole'),
              labelStyle: TextStyle(
                color: airmiusMutedColor(context),
                fontWeight: FontWeight.w800,
              ),
              enabledBorder: OutlineInputBorder(
                borderRadius: BorderRadius.circular(12),
                borderSide: BorderSide(color: airmiusBorderColor(context)),
              ),
              focusedBorder: OutlineInputBorder(
                borderRadius: BorderRadius.circular(12),
                borderSide: BorderSide(color: airmiusAccentColor(context)),
              ),
              filled: true,
              fillColor: airmiusSurfaceColor(context),
            ),
            style: TextStyle(
              color: airmiusTextColor(context),
              fontWeight: FontWeight.w900,
            ),
            items: [
              for (final item in _clubRoleValues)
                DropdownMenuItem(
                  value: item,
                  child: Text(_clubRoleLabel(item, t)),
                ),
            ],
            onChanged: !canChangeRole || isUpdating
                ? null
                : (value) {
                    if (value != null) onRoleChanged(value);
                  },
          ),
        ],
      ),
    );
  }
}

const _clubRoleValues = [
  'owner',
  'admin',
  'manager',
  'academy_manager',
  'financial_controller',
  'trainer',
  'member',
];

String _clubRoleLabel(String role, String Function(String) t) => switch (role) {
  'owner' => t('clubs.role.owner'),
  'admin' => t('clubs.role.admin'),
  'manager' => t('clubs.role.manager'),
  'academy_manager' => t('clubs.role.academyManager'),
  'financial_controller' => t('clubs.role.financialController'),
  'trainer' => t('clubs.role.trainer'),
  'member' => t('clubs.role.member'),
  _ => role,
};

Color _clubRoleColor(BuildContext context, String role) => switch (role) {
  'owner' || 'admin' => airmiusAccentColor(context),
  'manager' ||
  'academy_manager' ||
  'financial_controller' => Theme.of(context).colorScheme.secondary,
  'trainer' => Theme.of(context).colorScheme.tertiary,
  _ => airmiusMutedColor(context),
};

String _formatMoney(double value) =>
    '${value.toStringAsFixed(2).replaceAll('.', ',')} EUR';

class _ClubEditInlinePanel extends StatefulWidget {
  const _ClubEditInlinePanel({
    required this.club,
    required this.onOpenProfile,
    required this.onSaved,
  });

  final ClubSummary club;
  final VoidCallback onOpenProfile;

  final VoidCallback onSaved;

  @override
  State<_ClubEditInlinePanel> createState() => _ClubEditInlinePanelState();
}

class _ClubEditInlinePanelState extends State<_ClubEditInlinePanel> {
  bool _saving = false;

  Future<void> _save() async {
    final t = AirmiusScope.of(context).t;
    final country = widget.club.country?.trim().toUpperCase();
    if (country == null || country.length != 2) {
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(t('clubs.countryMissing'))));
      return;
    }

    setState(() => _saving = true);
    try {
      await AirmiusServicesScope.of(
        context,
      ).repositories.clubs.updateClub(widget.club.id, {
        'name': widget.club.name.trim(),
        'sport_type': widget.club.sportType?.trim(),
        'country': country,
        'city': widget.club.city.trim().isEmpty
            ? null
            : widget.club.city.trim(),
        'postal_code': widget.club.postalCode?.trim(),
      });
      if (!mounted) return;
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(t('clubs.dataSaved'))));
      widget.onSaved();
    } on AirmiusApiException catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(error.userMessage)));
    } catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            '${t('clubs.dataSaveFailed')}: ${_safeClubError(context, error)}',
          ),
        ),
      );
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final club = widget.club;
    return _InlineSection(
      title: t('clubs.editClubData'),
      subtitle: t('clubs.editClubDataBody'),
      child: Column(
        children: [
          _ReadOnlyFormLine(label: t('clubs.club'), value: club.name),
          const SizedBox(height: 10),
          _ReadOnlyFormLine(
            label: t('clubs.sport'),
            value: club.sportType?.isNotEmpty == true
                ? club.sportType!
                : t('clubs.sportOpen'),
          ),
          const SizedBox(height: 10),
          Row(
            children: [
              Expanded(
                child: _ReadOnlyFormLine(
                  label: t('clubs.city'),
                  value: club.city.isNotEmpty
                      ? club.city
                      : t('clubs.locationOpen'),
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: _ReadOnlyFormLine(
                  label: t('clubs.postalCode'),
                  value: club.postalCode?.isNotEmpty == true
                      ? club.postalCode!
                      : '-',
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          Row(
            children: [
              Expanded(
                child: _SmallInlineButton(
                  label: _saving ? t('clubs.saving') : t('clubs.save'),
                  filled: true,
                  onPressed: _saving ? null : _save,
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: _SmallInlineButton(
                  label: t('clubs.details'),
                  onPressed: widget.onOpenProfile,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _TeamCreateInlinePanel extends StatefulWidget {
  const _TeamCreateInlinePanel({required this.club, required this.onCreated});

  final ClubSummary club;
  final VoidCallback onCreated;

  @override
  State<_TeamCreateInlinePanel> createState() => _TeamCreateInlinePanelState();
}

class _TeamCreateInlinePanelState extends State<_TeamCreateInlinePanel> {
  final TextEditingController _nameController = TextEditingController();
  final TextEditingController _sportController = TextEditingController();
  Future<List<AirmiusSport>>? _sportsFuture;
  String? _selectedSportSlug;
  bool _saving = false;

  @override
  void initState() {
    super.initState();
    _sportController.text = widget.club.sportType ?? '';
    _selectedSportSlug = widget.club.sportType;
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _sportsFuture ??= AirmiusServicesScope.of(
      context,
    ).repositories.sports.sports().then((page) => page.items);
  }

  @override
  void dispose() {
    _nameController.dispose();
    _sportController.dispose();
    super.dispose();
  }

  Future<void> _createTeam() async {
    final t = AirmiusScope.of(context).t;
    final name = _nameController.text.trim();
    if (name.isEmpty || _saving) {
      return;
    }

    setState(() => _saving = true);
    try {
      final services = AirmiusServicesScope.of(context);
      await services.repositories.clubs.createTeam({
        'club_id': widget.club.id,
        'name': name,
        if ((_selectedSportSlug ?? _sportController.text).trim().isNotEmpty)
          'sport_type': (_selectedSportSlug ?? _sportController.text).trim(),
      });
      if (!mounted) return;
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(t('clubs.teamCreated'))));
      widget.onCreated();
      _nameController.clear();
    } catch (error) {
      if (!mounted) return;
      final message = _safeClubError(context, error);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('${t('clubs.teamCreateFailed')}: $message')),
      );
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return _InlineSection(
      title: t('clubs.addTeam'),
      subtitle: '${t('clubs.addTeamFor')} ${widget.club.name}.',
      child: Column(
        children: [
          TextField(
            controller: _nameController,
            style: TextStyle(
              color: airmiusTextColor(context),
              fontWeight: FontWeight.w800,
            ),
            decoration: InputDecoration(
              labelText: t('clubs.teamName'),
              hintText: t('clubs.teamNameHint'),
            ),
          ),
          const SizedBox(height: 10),
          FutureBuilder<List<AirmiusSport>>(
            future: _sportsFuture,
            builder: (context, snapshot) {
              return _SportAutocompleteField(
                controller: _sportController,
                sports: snapshot.data ?? const [],
                loading: snapshot.connectionState == ConnectionState.waiting,
                onTextChanged: () => _selectedSportSlug = null,
                onSelected: (sport) {
                  _sportController.text = sport.name;
                  _selectedSportSlug = sport.slug;
                },
              );
            },
          ),
          const SizedBox(height: 12),
          _SmallInlineButton(
            label: _saving ? t('clubs.saving') : t('clubs.createTeam'),
            filled: true,
            onPressed: _createTeam,
          ),
        ],
      ),
    );
  }
}

class _InlineSection extends StatelessWidget {
  const _InlineSection({
    required this.title,
    required this.subtitle,
    required this.child,
    this.action,
  });

  final String title;
  final String subtitle;
  final Widget child;
  final Widget? action;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(13),
      decoration: BoxDecoration(
        color: Theme.of(context).scaffoldBackgroundColor,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: airmiusBorderColor(context)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      title,
                      style: TextStyle(
                        color: airmiusTextColor(context),
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(height: 3),
                    Text(
                      subtitle,
                      style: TextStyle(
                        color: airmiusMutedColor(context),
                        fontSize: 12,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ],
                ),
              ),
              ?action,
            ],
          ),
          const SizedBox(height: 12),
          child,
        ],
      ),
    );
  }
}

class _InlineTeamCard extends StatelessWidget {
  const _InlineTeamCard({required this.team, required this.onTap});

  final TeamSummary team;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(12),
      child: Container(
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(
          color: airmiusSurfaceColor(context),
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: airmiusBorderColor(context)),
        ),
        child: Row(
          children: [
            _InitialsCircle(team.name, size: 34),
            const SizedBox(width: 10),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    team.name,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(
                      color: airmiusTextColor(context),
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                  const SizedBox(height: 3),
                  Text(
                    team.meta,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(
                      color: airmiusMutedColor(context),
                      fontSize: 12,
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(width: 8),
            Icon(Icons.chevron_right, color: airmiusMutedColor(context)),
          ],
        ),
      ),
    );
  }
}

class _InlineMetricLine extends StatelessWidget {
  const _InlineMetricLine({
    required this.icon,
    required this.title,
    required this.value,
  });

  final IconData icon;
  final String title;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Icon(icon, color: airmiusAccentColor(context), size: 19),
        const SizedBox(width: 10),
        Expanded(
          child: Text(
            title,
            style: TextStyle(
              color: airmiusMutedColor(context),
              fontWeight: FontWeight.w700,
            ),
          ),
        ),
        Text(
          value,
          style: TextStyle(
            color: airmiusTextColor(context),
            fontWeight: FontWeight.w900,
          ),
        ),
      ],
    );
  }
}

class _ReadOnlyFormLine extends StatelessWidget {
  const _ReadOnlyFormLine({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return InputDecorator(
      decoration: InputDecoration(labelText: label),
      child: Text(
        value,
        maxLines: 1,
        overflow: TextOverflow.ellipsis,
        style: TextStyle(
          color: airmiusTextColor(context),
          fontWeight: FontWeight.w800,
        ),
      ),
    );
  }
}

class _SmallInlineButton extends StatelessWidget {
  const _SmallInlineButton({
    required this.label,
    required this.onPressed,
    this.filled = false,
  });

  final String label;
  final VoidCallback? onPressed;
  final bool filled;

  @override
  Widget build(BuildContext context) {
    if (filled) {
      return FilledButton(
        onPressed: onPressed,
        style: FilledButton.styleFrom(
          backgroundColor: airmiusAccentColor(context),
          foregroundColor: airmiusOnColor(airmiusAccentColor(context)),
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
        ),
        child: Text(
          label,
          style: TextStyle(fontSize: 12, fontWeight: FontWeight.w900),
        ),
      );
    }

    return OutlinedButton(
      onPressed: onPressed,
      style: OutlinedButton.styleFrom(
        foregroundColor: airmiusTextColor(context),
        side: BorderSide(color: airmiusBorderColor(context)),
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
      ),
      child: Text(
        label,
        style: TextStyle(fontSize: 12, fontWeight: FontWeight.w900),
      ),
    );
  }
}

class _InitialsCircle extends StatelessWidget {
  const _InitialsCircle(this.name, {this.size = 40});

  final String name;
  final double size;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: size,
      height: size,
      decoration: BoxDecoration(
        color: airmiusSurfaceSoftColor(context),
        shape: BoxShape.circle,
      ),
      alignment: Alignment.center,
      child: Text(
        initialsFromName(name),
        style: TextStyle(
          color: airmiusTextColor(context),
          fontSize: 13,
          fontWeight: FontWeight.w900,
        ),
      ),
    );
  }
}

class _InlineAction extends StatelessWidget {
  const _InlineAction({
    required this.icon,
    required this.label,
    required this.onPressed,
    this.danger = false,
  });

  final IconData icon;
  final String label;
  final VoidCallback? onPressed;
  final bool danger;

  @override
  Widget build(BuildContext context) {
    if (danger) {
      return FilledButton.icon(
        onPressed: onPressed,
        icon: Icon(icon, size: 17),
        style: FilledButton.styleFrom(
          backgroundColor: Theme.of(context).colorScheme.error,
          foregroundColor: Theme.of(context).colorScheme.onError,
          padding: const EdgeInsets.symmetric(horizontal: 11, vertical: 11),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
        ),
        label: Text(
          label,
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          style: TextStyle(fontSize: 13, fontWeight: FontWeight.w900),
        ),
      );
    }

    return OutlinedButton.icon(
      onPressed: onPressed,
      icon: Icon(icon, size: 17),
      style: OutlinedButton.styleFrom(
        foregroundColor: airmiusTextColor(context),
        side: BorderSide(color: airmiusBorderColor(context)),
        padding: const EdgeInsets.symmetric(horizontal: 11, vertical: 11),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
      ),
      label: Text(
        label,
        maxLines: 1,
        overflow: TextOverflow.ellipsis,
        style: TextStyle(fontSize: 13, fontWeight: FontWeight.w800),
      ),
    );
  }
}

class ClubProfileScreen extends StatefulWidget {
  const ClubProfileScreen({
    super.key,
    required this.club,
    required this.requested,
    required this.onRequest,
    required this.onWithdraw,
  });

  final ClubSummary club;
  final bool requested;
  final ValueChanged<ClubSummary> onRequest;
  final ValueChanged<ClubSummary> onWithdraw;

  @override
  State<ClubProfileScreen> createState() => _ClubProfileScreenState();
}

class _ClubProfileScreenState extends State<ClubProfileScreen> {
  String _activeTab = 'struktur';
  Future<ClubSummary>? _clubDetailFuture;
  bool? _requestStatusOverride;
  bool? _followingOverride;
  bool? _blockedOverride;
  bool _uploadingCover = false;
  bool _busyProfileAction = false;

  ClubSummary get club => widget.club;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _clubDetailFuture ??= _loadClubDetail();
  }

  Future<ClubSummary> _loadClubDetail() async {
    final services = AirmiusServicesScope.of(context);
    final detail = await services.repositories.clubs.club(widget.club.id);
    return ClubSummary.fromAirmiusClub(detail);
  }

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return Scaffold(
      appBar: AppBar(
        backgroundColor:
            Theme.of(context).appBarTheme.backgroundColor ??
            Theme.of(context).colorScheme.surface,
        surfaceTintColor: Colors.transparent,
        title: Text(club.name, style: TextStyle(fontWeight: FontWeight.w900)),
      ),
      body: PageFrame(
        title: club.name,
        subtitle: t('clubs.profileSubtitle'),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            FutureBuilder<ClubSummary>(
              future: _clubDetailFuture,
              builder: (context, snapshot) {
                final profileClub = snapshot.data ?? club;
                final requested = _isRequested(profileClub);
                final currentUserId = AirmiusServicesScope.of(
                  context,
                ).authState.user?.id;
                final isOwner =
                    profileClub.ownerId != null &&
                    profileClub.ownerId == currentUserId;
                final following = _following(profileClub);
                final blocked = _blocked(profileClub);
                final canMessage =
                    profileClub.social['can_send_message'] == true;
                final canFollow = profileClub.social['can_follow'] == true;
                return _ClubProfileHero(
                  club: profileClub,
                  requested: requested,
                  onJoin:
                      profileClub.acceptsMemberships &&
                          !profileClub.isMember &&
                          !requested
                      ? () => _openApplication(context, profileClub)
                      : null,
                  onWithdraw: requested
                      ? () => _withdraw(context, profileClub)
                      : null,
                  onUpdateCover: profileClub.canManage && !_uploadingCover
                      ? () => _pickAndUploadCover(profileClub)
                      : null,
                  uploadingCover: _uploadingCover,
                  onMessage:
                      !_busyProfileAction &&
                          !blocked &&
                          canMessage &&
                          profileClub.ownerId != null &&
                          !isOwner
                      ? () => _startOwnerConversation(profileClub)
                      : null,
                  onFollow:
                      !_busyProfileAction && !blocked && canFollow && !isOwner
                      ? (following
                            ? () => _setFollowing(profileClub, false)
                            : () => _setFollowing(profileClub, true))
                      : null,
                  isFollowing: following,
                  isBlocked: blocked,
                  onBlock: !_busyProfileAction && !isOwner
                      ? () => _setBlocked(profileClub, true)
                      : null,
                  onUnblock: !_busyProfileAction && !isOwner
                      ? () => _setBlocked(profileClub, false)
                      : null,
                  onReport: !_busyProfileAction && !isOwner
                      ? () => _reportClub(profileClub)
                      : null,
                );
              },
            ),
            const SizedBox(height: 14),
            FutureBuilder<ClubSummary>(
              future: _clubDetailFuture,
              builder: (context, snapshot) =>
                  _ClubStats(club: snapshot.data ?? club),
            ),
            const SizedBox(height: 14),
            _ClubWorkspaceTabs(
              active: _activeTab,
              includeSurveys: club.isMember || club.canManage,
              onSelect: (value) => setState(() => _activeTab = value),
            ),
            const SizedBox(height: 14),
            FutureBuilder<ClubSummary>(
              future: _clubDetailFuture,
              builder: (context, snapshot) {
                final profileClub = snapshot.data ?? club;
                final requested = _isRequested(profileClub);
                return _ClubTabBody(
                  tab: _activeTab,
                  club: profileClub,
                  requested: requested,
                  onMembershipChanged: () {
                    if (!mounted) return;
                    setState(() => _clubDetailFuture = _loadClubDetail());
                  },
                  onJoin:
                      profileClub.acceptsMemberships &&
                          !profileClub.isMember &&
                          !requested
                      ? () => _openApplication(context, profileClub)
                      : null,
                  onWithdraw: requested
                      ? () => _withdraw(context, profileClub)
                      : null,
                );
              },
            ),
          ],
        ),
      ),
    );
  }

  Future<void> _openApplication(
    BuildContext context,
    ClubSummary selectedClub,
  ) async {
    final sent = await Navigator.push<bool>(
      context,
      MaterialPageRoute(
        fullscreenDialog: true,
        builder: (_) =>
            MembershipApplicationFormScreen(clubId: selectedClub.id),
      ),
    );
    if (sent == true && context.mounted) {
      widget.onRequest(selectedClub);
      setState(() {
        _requestStatusOverride = true;
        _activeTab = 'beitritt';
      });
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            AirmiusScope.of(context).t('clubs.membershipRequestSent'),
          ),
        ),
      );
    }
  }

  Future<void> _withdraw(BuildContext context, ClubSummary selectedClub) async {
    final t = AirmiusScope.of(context).t;
    final ok = await confirmDanger(
      context,
      t('clubs.withdrawRequest'),
      '${t('clubs.withdrawRequestBefore')} ${selectedClub.name}?',
    );
    if (ok && context.mounted) {
      try {
        final services = AirmiusServicesScope.of(context);
        await services.repositories.memberships.withdrawClubRequest(
          selectedClub.id,
        );
        if (!mounted || !context.mounted) return;
        widget.onWithdraw(selectedClub);
        setState(() => _requestStatusOverride = false);
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(t('clubs.membershipRequestWithdrawn'))),
        );
      } catch (error) {
        if (!context.mounted) return;
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              '${t('clubs.withdrawFailed')}: ${_safeClubError(context, error)}',
            ),
          ),
        );
      }
    }
  }

  Future<void> _pickAndUploadCover(ClubSummary selectedClub) async {
    final t = AirmiusScope.of(context).t;
    final selection = await FilePicker.platform.pickFiles(
      type: FileType.image,
      withData: true,
    );
    final file = selection?.files.single;
    if (file == null || !mounted) return;

    setState(() => _uploadingCover = true);
    try {
      final services = AirmiusServicesScope.of(context);
      final session = services.authState.session;
      if (session == null) throw StateError(t('clubs.cover.noSession'));

      final base = Uri.parse(services.clientForSession(session).baseUrl);
      final rootPath = base.path.endsWith('/') ? base.path : '${base.path}/';
      final path = '${rootPath}api/v1/clubs/${selectedClub.id}/images';
      final request =
          http.MultipartRequest(
              'POST',
              base.replace(path: path, query: null, fragment: null),
            )
            ..headers['Authorization'] = 'Bearer ${session.token}'
            ..headers['Accept'] = 'application/json'
            ..headers['Accept-Language'] = AirmiusScope.of(
              context,
            ).language.locale.languageCode;

      if (file.bytes != null) {
        request.files.add(
          http.MultipartFile.fromBytes(
            'cover_image',
            file.bytes!,
            filename: file.name,
          ),
        );
      } else if (file.path != null) {
        request.files.add(
          await http.MultipartFile.fromPath(
            'cover_image',
            file.path!,
            filename: file.name,
          ),
        );
      } else {
        throw StateError(t('clubs.cover.unreadable'));
      }

      final response = await http.Response.fromStream(await request.send());
      if (response.statusCode < 200 || response.statusCode >= 300) {
        throw AirmiusApiException(
          statusCode: response.statusCode,
          body: response.body,
          path: '/api/v1/clubs/${selectedClub.id}/images',
        );
      }

      if (!mounted) return;
      setState(() => _clubDetailFuture = _loadClubDetail());
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(t('clubs.cover.updated'))));
    } on AirmiusApiException catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(error.userMessage)));
    } catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            '${t('clubs.cover.error')} ${_safeClubError(context, error)}',
          ),
        ),
      );
    } finally {
      if (mounted) setState(() => _uploadingCover = false);
    }
  }

  Future<void> _startOwnerConversation(ClubSummary selectedClub) async {
    final t = AirmiusScope.of(context).t;
    final ownerId = selectedClub.ownerId;
    if (ownerId == null) {
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(t('clubs.message.noContact'))));
      return;
    }

    setState(() => _busyProfileAction = true);
    try {
      final services = AirmiusServicesScope.of(context);
      final json = await services
          .clientForSession(services.authState.session)
          .createConversation(
            type: 'direct',
            participantIds: [ownerId],
            clubId: selectedClub.id,
          );
      final raw = json['data'];
      if (raw is! JsonMap) {
        throw StateError(t('clubs.message.invalidResponse'));
      }
      final conversation = AirmiusConversation.fromJson(raw);
      if (!mounted) return;
      await Navigator.of(context).push(
        MaterialPageRoute<void>(
          builder: (_) => ChatDetailScreen(
            conversationId: conversation.id,
            title: selectedClub.name,
            kind: conversation.kind,
          ),
        ),
      );
    } on AirmiusApiException catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(error.userMessage)));
    } catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            '${t('clubs.message.error')} ${_safeClubError(context, error)}',
          ),
        ),
      );
    } finally {
      if (mounted) setState(() => _busyProfileAction = false);
    }
  }

  bool _following(ClubSummary selectedClub) =>
      _followingOverride ?? selectedClub.social['is_following'] == true;

  bool _blocked(ClubSummary selectedClub) =>
      _blockedOverride ?? selectedClub.social['has_blocked'] == true;

  Future<void> _setFollowing(ClubSummary selectedClub, bool follow) async {
    final ownerId = selectedClub.ownerId;
    if (ownerId == null) return;
    final t = AirmiusScope.of(context).t;
    setState(() => _busyProfileAction = true);
    try {
      final client = AirmiusServicesScope.of(
        context,
      ).clientForSession(AirmiusServicesScope.of(context).authState.session);
      if (follow) {
        await client.followUser(ownerId);
      } else {
        await client.unfollowUser(ownerId);
      }
      if (!mounted) return;
      setState(() => _followingOverride = follow);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            t(follow ? 'clubs.social.followed' : 'clubs.social.unfollowed'),
          ),
        ),
      );
    } on AirmiusApiException catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(error.userMessage)));
      }
    } finally {
      if (mounted) setState(() => _busyProfileAction = false);
    }
  }

  Future<void> _setBlocked(ClubSummary selectedClub, bool block) async {
    final ownerId = selectedClub.ownerId;
    if (ownerId == null) return;
    final t = AirmiusScope.of(context).t;
    if (block) {
      final confirmed = await confirmDanger(
        context,
        t('clubs.social.blockTitle'),
        '${t('clubs.social.blockQuestion')} ${selectedClub.name}?',
        t('clubs.social.block'),
      );
      if (confirmed != true || !mounted) return;
    }
    setState(() => _busyProfileAction = true);
    try {
      final services = AirmiusServicesScope.of(context);
      final client = services.clientForSession(services.authState.session);
      if (block) {
        await client.blockUser(ownerId);
      } else {
        await client.unblockUser(ownerId);
      }
      if (!mounted) return;
      setState(() {
        _blockedOverride = block;
        if (block) _followingOverride = false;
      });
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            t(block ? 'clubs.social.blocked' : 'clubs.social.unblocked'),
          ),
        ),
      );
    } on AirmiusApiException catch (error) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(error.userMessage)));
      }
    } finally {
      if (mounted) setState(() => _busyProfileAction = false);
    }
  }

  Future<void> _reportClub(ClubSummary selectedClub) async {
    final t = AirmiusScope.of(context).t;
    final report = await _showClubReportDialog(context);
    if (report == null || !mounted) return;

    setState(() => _busyProfileAction = true);
    try {
      final services = AirmiusServicesScope.of(context);
      await services
          .clientForSession(services.authState.session)
          .reportContent(
            type: 'club',
            id: selectedClub.id,
            reason: report.reason,
            details: report.details,
          );
      if (!mounted) return;
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(t('clubs.report.sent'))));
    } on AirmiusApiException catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(error.userMessage)));
    } catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            '${t('clubs.report.error')} ${_safeClubError(context, error)}',
          ),
        ),
      );
    } finally {
      if (mounted) setState(() => _busyProfileAction = false);
    }
  }

  bool _isRequested(ClubSummary profileClub) {
    return _requestStatusOverride ??
        (widget.requested || profileClub.hasPendingMembershipRequest);
  }
}

class _ClubProfileHero extends StatelessWidget {
  const _ClubProfileHero({
    required this.club,
    required this.requested,
    required this.onJoin,
    required this.onWithdraw,
    required this.onUpdateCover,
    required this.uploadingCover,
    required this.onMessage,
    required this.onFollow,
    required this.isFollowing,
    required this.isBlocked,
    required this.onBlock,
    required this.onUnblock,
    required this.onReport,
  });

  final ClubSummary club;
  final bool requested;
  final VoidCallback? onJoin;
  final VoidCallback? onWithdraw;
  final VoidCallback? onUpdateCover;
  final bool uploadingCover;
  final VoidCallback? onMessage;
  final VoidCallback? onFollow;
  final bool isFollowing;
  final bool isBlocked;
  final VoidCallback? onBlock;
  final VoidCallback? onUnblock;
  final VoidCallback? onReport;

  @override
  Widget build(BuildContext context) {
    final scope = AirmiusScope.of(context);
    return AirmiusPanel(
      padding: EdgeInsets.zero,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Stack(
            clipBehavior: Clip.none,
            children: [
              _ClubCover(club: club, height: 150),
              Positioned(
                left: 18,
                bottom: -34,
                child: AirmiusAvatar(
                  club.name,
                  imageUrl: club.logoUrl,
                  large: true,
                ),
              ),
              Positioned(
                right: 12,
                bottom: 12,
                child: Wrap(
                  spacing: 8,
                  children: [
                    if (club.canManage)
                      _RoundAction(
                        icon: uploadingCover
                            ? Icons.hourglass_top_rounded
                            : Icons.camera_alt_outlined,
                        tooltip: scope.t('clubs.cover.update'),
                        onTap: onUpdateCover,
                      ),
                    if (onMessage != null || onReport != null)
                      _RoundAction(
                        icon: Icons.more_horiz,
                        tooltip: scope.t('clubs.actions'),
                        onTap: () => _openMoreSheet(context),
                      ),
                  ],
                ),
              ),
            ],
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(18, 46, 18, 18),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Row(
                            children: [
                              Expanded(
                                child: Text(
                                  club.name,
                                  style: TextStyle(
                                    color: airmiusTextColor(context),
                                    fontSize: 25,
                                    fontWeight: FontWeight.w900,
                                    height: 1.05,
                                  ),
                                ),
                              ),
                              if (club.verified)
                                Icon(
                                  Icons.verified,
                                  color: airmiusAccentColor(context),
                                ),
                            ],
                          ),
                          const SizedBox(height: 5),
                          Text(
                            '${club.city} - ${scope.t('clubs.clubProfile')}',
                            style: TextStyle(
                              color: airmiusMutedColor(context),
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 14),
                Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: [
                    StatusPill(
                      club.isMember
                          ? scope.t('clubs.role.member')
                          : (requested
                                ? scope.t('sent')
                                : (club.acceptsMemberships
                                      ? scope.t('clubs.membershipOpen')
                                      : scope.t('clubs.viewOnly'))),
                      color: club.isMember
                          ? Theme.of(context).colorScheme.secondary
                          : requested
                          ? Theme.of(context).colorScheme.secondary
                          : airmiusAccentColor(context),
                    ),
                    StatusPill(scope.t('clubs.tab.structure')),
                    StatusPill(scope.t('clubs.mobileCockpit')),
                  ],
                ),
                const SizedBox(height: 16),
                if (club.isMember)
                  AirmiusButton(
                    label: scope.t('clubs.role.member'),
                    icon: Icons.verified_user_outlined,
                    secondary: true,
                    onPressed: null,
                  )
                else if (requested)
                  AirmiusButton(
                    label: scope.t('withdraw'),
                    icon: Icons.undo_outlined,
                    danger: true,
                    onPressed: onWithdraw,
                  )
                else
                  AirmiusButton(
                    label: club.acceptsMemberships
                        ? scope.t('join')
                        : scope.t('clubs.viewTeams'),
                    icon: Icons.assignment_outlined,
                    onPressed: onJoin,
                  ),
                if (onMessage != null || onFollow != null) ...[
                  const SizedBox(height: 10),
                  Wrap(
                    spacing: 10,
                    runSpacing: 10,
                    children: [
                      if (onMessage != null)
                        AirmiusButton(
                          label: scope.t('clubs.message.send'),
                          icon: Icons.chat_bubble_outline,
                          secondary: true,
                          onPressed: onMessage,
                        ),
                      if (onFollow != null)
                        AirmiusButton(
                          label: isFollowing
                              ? scope.t('clubs.social.unfollow')
                              : scope.t('clubs.social.follow'),
                          icon: isFollowing
                              ? Icons.person_remove_alt_1_outlined
                              : Icons.person_add_alt_1_outlined,
                          secondary: true,
                          onPressed: onFollow,
                        ),
                    ],
                  ),
                ],
              ],
            ),
          ),
        ],
      ),
    );
  }

  void _openMoreSheet(BuildContext context) {
    showModalBottomSheet<void>(
      context: context,
      backgroundColor: airmiusSurfaceColor(context),
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      builder: (_) => SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(18),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text(
                AirmiusScope.of(context).t('clubs.actions'),
                style: TextStyle(
                  color: airmiusTextColor(context),
                  fontSize: 18,
                  fontWeight: FontWeight.w900,
                ),
              ),
              const SizedBox(height: 12),
              if (onMessage != null)
                AirmiusButton(
                  label: AirmiusScope.of(context).t('clubs.message.send'),
                  icon: Icons.chat_bubble_outline,
                  secondary: true,
                  onPressed: () {
                    Navigator.pop(context);
                    onMessage?.call();
                  },
                ),
              if (onMessage != null && onReport != null)
                const SizedBox(height: 10),
              if (onReport != null)
                AirmiusButton(
                  label: AirmiusScope.of(context).t('clubs.report.action'),
                  icon: Icons.flag_outlined,
                  danger: true,
                  onPressed: () {
                    Navigator.pop(context);
                    onReport?.call();
                  },
                ),
              if (onBlock != null || onUnblock != null) ...[
                if (onMessage != null || onReport != null)
                  const SizedBox(height: 10),
                AirmiusButton(
                  label: isBlocked
                      ? AirmiusScope.of(context).t('clubs.social.unblock')
                      : AirmiusScope.of(context).t('clubs.social.block'),
                  icon: isBlocked
                      ? Icons.lock_open_outlined
                      : Icons.block_outlined,
                  danger: !isBlocked,
                  secondary: isBlocked,
                  onPressed: () {
                    Navigator.pop(context);
                    (isBlocked ? onUnblock : onBlock)?.call();
                  },
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }
}

class _ClubReportDraft {
  const _ClubReportDraft({required this.reason, this.details});

  final String reason;
  final String? details;
}

Future<_ClubReportDraft?> _showClubReportDialog(BuildContext context) async {
  final t = AirmiusScope.of(context).t;
  final details = TextEditingController();
  var reason = 'spam';
  final result = await showDialog<_ClubReportDraft>(
    context: context,
    builder: (dialogContext) => StatefulBuilder(
      builder: (context, setDialogState) => AlertDialog(
        backgroundColor: airmiusSurfaceColor(context),
        title: Text(t('clubs.report.title')),
        content: SizedBox(
          width: 460,
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text(
                t('clubs.report.explanation'),
                style: TextStyle(color: airmiusMutedColor(context)),
              ),
              const SizedBox(height: 16),
              DropdownButtonFormField<String>(
                initialValue: reason,
                decoration: InputDecoration(
                  labelText: t('clubs.report.reason'),
                ),
                items: [
                  DropdownMenuItem(
                    value: 'spam',
                    child: Text(t('clubs.report.reason.spam')),
                  ),
                  DropdownMenuItem(
                    value: 'hate',
                    child: Text(t('clubs.report.reason.hate')),
                  ),
                  DropdownMenuItem(
                    value: 'bullying',
                    child: Text(t('clubs.report.reason.bullying')),
                  ),
                  DropdownMenuItem(
                    value: 'image_rights',
                    child: Text(t('clubs.report.reason.imageRights')),
                  ),
                  DropdownMenuItem(
                    value: 'other',
                    child: Text(t('clubs.report.reason.other')),
                  ),
                ],
                onChanged: (value) {
                  if (value != null) {
                    setDialogState(() => reason = value);
                  }
                },
              ),
              const SizedBox(height: 14),
              TextField(
                controller: details,
                minLines: 3,
                maxLines: 5,
                maxLength: 1000,
                decoration: InputDecoration(
                  labelText: t('clubs.report.details'),
                  alignLabelWithHint: true,
                ),
              ),
            ],
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext),
            child: Text(t('cancel')),
          ),
          FilledButton.icon(
            onPressed: () => Navigator.pop(
              dialogContext,
              _ClubReportDraft(
                reason: reason,
                details: details.text.trim().isEmpty
                    ? null
                    : details.text.trim(),
              ),
            ),
            icon: Icon(Icons.flag_outlined),
            label: Text(t('clubs.report.submit')),
          ),
        ],
      ),
    ),
  );
  details.dispose();
  return result;
}

class _ClubCover extends StatelessWidget {
  const _ClubCover({required this.club, required this.height})
    : compact = false;

  final ClubSummary club;
  final double height;
  final bool compact;

  @override
  Widget build(BuildContext context) {
    final imageUrl = resolveAirmiusImageUrl(club.bannerUrl);
    return ClipRRect(
      borderRadius: BorderRadius.vertical(
        top: Radius.circular(compact ? 18 : 18),
      ),
      child: SizedBox(
        height: height,
        child: Stack(
          fit: StackFit.expand,
          children: [
            DecoratedBox(
              decoration: BoxDecoration(
                gradient: LinearGradient(
                  begin: Alignment.topLeft,
                  end: Alignment.bottomRight,
                  colors: [
                    airmiusAccentColor(context).withValues(alpha: 0.80),
                    airmiusSurfaceSoftColor(context),
                    Theme.of(
                      context,
                    ).colorScheme.tertiary.withValues(alpha: 0.42),
                  ],
                ),
              ),
            ),
            if (imageUrl != null && imageUrl.isNotEmpty)
              Image.network(
                imageUrl,
                fit: BoxFit.cover,
                errorBuilder: (_, _, _) => const SizedBox.shrink(),
              ),
            Container(
              color: Theme.of(
                context,
              ).shadowColor.withValues(alpha: compact ? 0.16 : 0.24),
            ),
          ],
        ),
      ),
    );
  }
}

class _ClubStats extends StatelessWidget {
  const _ClubStats({required this.club});

  final ClubSummary club;

  @override
  Widget build(BuildContext context) {
    final scope = AirmiusScope.of(context);
    return Row(
      children: [
        Expanded(
          child: MetricCard(
            value: '${club.members}',
            label: scope.t('members'),
          ),
        ),
        const SizedBox(width: 10),
        Expanded(
          child: MetricCard(value: '${club.teams}', label: scope.t('teams')),
        ),
        const SizedBox(width: 10),
        Expanded(
          child: MetricCard(value: '${club.posts}', label: scope.t('posts')),
        ),
      ],
    );
  }
}

class _ClubWorkspaceTabs extends StatelessWidget {
  const _ClubWorkspaceTabs({
    required this.active,
    required this.includeSurveys,
    required this.onSelect,
  });

  final String active;
  final bool includeSurveys;
  final ValueChanged<String> onSelect;

  static const tabs = [
    _ClubTab('struktur', 'clubs.tab.structure', Icons.account_tree_outlined),
    _ClubTab('beitritt', 'clubs.tab.membership', Icons.assignment_outlined),
    _ClubTab('beiträge', 'clubs.tab.posts', Icons.forum_outlined),
    _ClubTab('dokumente', 'clubs.tab.documents', Icons.folder_open_outlined),
  ];

  @override
  Widget build(BuildContext context) {
    return SingleChildScrollView(
      scrollDirection: Axis.horizontal,
      child: Row(
        children: [
          for (final tab in [
            ...tabs.take(3),
            if (includeSurveys)
              const _ClubTab(
                'umfragen',
                'clubs.tab.surveys',
                Icons.how_to_vote_outlined,
              ),
            tabs[3],
          ]) ...[
            _ClubTabChip(
              tab: tab,
              selected: active == tab.id,
              onTap: () => onSelect(tab.id),
            ),
            const SizedBox(width: 8),
          ],
        ],
      ),
    );
  }
}

class _ClubTabBody extends StatelessWidget {
  const _ClubTabBody({
    required this.tab,
    required this.club,
    required this.requested,
    required this.onMembershipChanged,
    required this.onJoin,
    required this.onWithdraw,
  });

  final String tab;
  final ClubSummary club;
  final bool requested;
  final VoidCallback onMembershipChanged;
  final VoidCallback? onJoin;
  final VoidCallback? onWithdraw;

  @override
  Widget build(BuildContext context) {
    return switch (tab) {
      'beitritt' => _MembershipPanel(
        club: club,
        requested: requested,
        onChanged: onMembershipChanged,
        onJoin: onJoin,
        onWithdraw: onWithdraw,
      ),
      'beiträge' => _ClubPostsPanel(club: club),
      'umfragen' => ClubSurveyScreen(club: club),
      'dokumente' => const _DocumentsPanel(),
      _ => _StructurePanel(club: club),
    };
  }
}

class _StructurePanel extends StatelessWidget {
  const _StructurePanel({required this.club});

  final ClubSummary club;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        if (club.description?.trim().isNotEmpty == true) ...[
          AirmiusPanel(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Eyebrow(t('clubs.about')),
                const SizedBox(height: 8),
                Text(
                  club.description!,
                  style: TextStyle(
                    color: airmiusTextColor(context),
                    height: 1.4,
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 14),
        ],
        AirmiusPanel(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Eyebrow(t('clubs.clubData')),
              const SizedBox(height: 12),
              _DetailLine(
                icon: Icons.location_on_outlined,
                label: t('clubs.location'),
                value: club.city.isEmpty ? t('clubs.notProvided') : club.city,
              ),
              _DetailLine(
                icon: Icons.badge_outlined,
                label: t('clubs.status'),
                value: club.verified
                    ? t('clubs.verified')
                    : t('clubs.profileUnderReview'),
              ),
              _DetailLine(
                icon: Icons.groups_outlined,
                label: t('clubs.members'),
                value: '${club.members} ${t('clubs.activeContacts')}',
              ),
            ],
          ),
        ),
        const SizedBox(height: 14),
        _InfoPanel(
          title: t('clubs.admins'),
          rows: club.admins.isEmpty
              ? [
                  _InfoRowData(
                    'VA',
                    club.name,
                    club.verified
                        ? t('clubs.role.admin')
                        : t('clubs.profileOwner'),
                  ),
                ]
              : club.admins
                    .map(
                      (admin) => _InfoRowData(
                        _initials('${admin['name'] ?? club.name}'),
                        '${admin['name'] ?? club.name}',
                        t('clubs.role.admin'),
                      ),
                    )
                    .toList(),
        ),
        const SizedBox(height: 14),
        if (club.gamification != null)
          _ClubProgressPanel(gamification: club.gamification!),
        if (club.gamification != null) const SizedBox(height: 14),
        if (club.badges.isNotEmpty) _ClubBadgesPanel(badges: club.badges),
        if (club.badges.isNotEmpty) const SizedBox(height: 14),
        _TeamsPanel(club: club),
      ],
    );
  }

  static String _initials(String value) => value
      .trim()
      .split(RegExp(r'\s+'))
      .where((part) => part.isNotEmpty)
      .take(2)
      .map((part) => part[0].toUpperCase())
      .join();
}

class _ClubProgressPanel extends StatelessWidget {
  const _ClubProgressPanel({required this.gamification});

  final JsonMap gamification;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final level = gamification['level'] ?? 1;
    final xp = gamification['xp'] ?? 0;
    final next = gamification['next_level_xp'] ?? 0;
    final progress =
        ((gamification['progress'] as num?)?.toDouble() ?? 0).clamp(0, 100) /
        100;
    return AirmiusPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Eyebrow(t('clubs.progress')),
          const SizedBox(height: 8),
          Text(
            '${t('clubs.level')} $level',
            style: TextStyle(fontSize: 22, fontWeight: FontWeight.w900),
          ),
          const SizedBox(height: 3),
          Text(
            '$xp XP / $next XP',
            style: TextStyle(color: airmiusMutedColor(context)),
          ),
          const SizedBox(height: 12),
          ClipRRect(
            borderRadius: BorderRadius.circular(8),
            child: LinearProgressIndicator(value: progress),
          ),
        ],
      ),
    );
  }
}

class _ClubBadgesPanel extends StatelessWidget {
  const _ClubBadgesPanel({required this.badges});

  final List<JsonMap> badges;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return AirmiusPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Eyebrow(t('clubs.badges')),
          const SizedBox(height: 10),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              for (final badge in badges.take(6))
                Chip(
                  avatar: const Icon(Icons.emoji_events_outlined, size: 16),
                  label: Text(
                    '${badge['name'] ?? badge['key'] ?? t('clubs.badge')}',
                  ),
                ),
            ],
          ),
        ],
      ),
    );
  }
}

class _MembershipPanel extends StatefulWidget {
  const _MembershipPanel({
    required this.club,
    required this.requested,
    required this.onChanged,
    required this.onJoin,
    required this.onWithdraw,
  });

  final ClubSummary club;
  final bool requested;
  final VoidCallback onChanged;
  final VoidCallback? onJoin;
  final VoidCallback? onWithdraw;

  @override
  State<_MembershipPanel> createState() => _MembershipPanelState();
}

class _MembershipPanelState extends State<_MembershipPanel> {
  bool _busy = false;

  String t(String key) => AirmiusScope.of(context).t(key);

  AirmiusApiClient get _client {
    final services = AirmiusServicesScope.of(context);
    return services.clientForSession(services.authState.session);
  }

  Future<void> _requestTermination() async {
    final payload = await _terminationPayload();
    if (payload == null || !mounted || _busy) return;
    setState(() => _busy = true);
    try {
      await _client.requestClubMembershipTermination(widget.club.id, payload);
      if (!mounted) return;
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(t('clubs.terminationRequested'))));
      widget.onChanged();
    } on AirmiusApiException catch (error) {
      if (mounted) _showError(error.userMessage);
    } catch (_) {
      if (mounted) _showError(t('clubs.terminationFailed'));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<JsonMap?> _terminationPayload() async {
    DateTime effectiveOn = DateTime.now();
    final reasonController = TextEditingController();
    final payload = await showDialog<JsonMap>(
      context: context,
      builder: (dialogContext) => StatefulBuilder(
        builder: (context, setDialogState) => AlertDialog(
          title: Text(t('clubs.terminationTitle')),
          content: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  t('clubs.terminationBody'),
                  style: TextStyle(color: airmiusMutedColor(context)),
                ),
                const SizedBox(height: 12),
                _DateButton(
                  label: t('clubs.terminationDate'),
                  value: effectiveOn,
                  onPressed: () async {
                    final selected = await showDatePicker(
                      context: context,
                      firstDate: DateTime.now(),
                      lastDate: DateTime.now().add(const Duration(days: 730)),
                      initialDate: effectiveOn.isBefore(DateTime.now())
                          ? DateTime.now()
                          : effectiveOn,
                    );
                    if (selected != null) {
                      setDialogState(() => effectiveOn = selected);
                    }
                  },
                ),
                const SizedBox(height: 10),
                TextField(
                  controller: reasonController,
                  maxLines: 3,
                  decoration: InputDecoration(
                    labelText: t('clubs.terminationReason'),
                  ),
                ),
              ],
            ),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(dialogContext),
              child: Text(t('common.cancel')),
            ),
            FilledButton(
              onPressed: () => Navigator.pop(dialogContext, {
                'requested_termination_on': _dateOnly(effectiveOn),
                if (reasonController.text.trim().isNotEmpty)
                  'termination_reason': reasonController.text.trim(),
              }),
              child: Text(t('clubs.terminationSend')),
            ),
          ],
        ),
      ),
    );
    reasonController.dispose();
    return payload;
  }

  void _showError(String message) {
    ScaffoldMessenger.of(
      context,
    ).showSnackBar(SnackBar(content: Text(message)));
  }

  Future<void> _requestPause() async {
    final payload = await _pausePayload();
    if (payload == null || !mounted || _busy) return;
    setState(() => _busy = true);
    try {
      await _client.requestClubMembershipPause(widget.club.id, payload);
      if (!mounted) return;
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(t('clubs.pauseRequested'))));
      widget.onChanged();
    } on AirmiusApiException catch (error) {
      if (mounted) _showError(error.userMessage);
    } catch (_) {
      if (mounted) _showError(t('clubs.pauseFailed'));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<JsonMap?> _pausePayload() async {
    DateTime from = DateTime.now();
    DateTime? until;
    final messageController = TextEditingController();
    final payload = await showDialog<JsonMap>(
      context: context,
      builder: (dialogContext) => StatefulBuilder(
        builder: (context, setDialogState) => AlertDialog(
          title: Text(t('clubs.pauseTitle')),
          content: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                _DateButton(
                  label: t('clubs.pauseFrom'),
                  value: from,
                  onPressed: () async {
                    final selected = await showDatePicker(
                      context: context,
                      firstDate: DateTime.now(),
                      lastDate: DateTime.now().add(const Duration(days: 730)),
                      initialDate: from.isBefore(DateTime.now())
                          ? DateTime.now()
                          : from,
                    );
                    if (selected != null) {
                      setDialogState(() {
                        from = selected;
                        if (until != null && until!.isBefore(from)) {
                          until = null;
                        }
                      });
                    }
                  },
                ),
                const SizedBox(height: 8),
                _DateButton(
                  label: t('clubs.pauseUntil'),
                  value: until,
                  optional: true,
                  onPressed: () async {
                    final selected = await showDatePicker(
                      context: context,
                      firstDate: from,
                      lastDate: DateTime.now().add(const Duration(days: 730)),
                      initialDate: until ?? from,
                    );
                    if (selected != null) {
                      setDialogState(() => until = selected);
                    }
                  },
                ),
                const SizedBox(height: 8),
                TextField(
                  controller: messageController,
                  maxLines: 3,
                  decoration: InputDecoration(
                    labelText: t('clubs.pauseMessage'),
                  ),
                ),
              ],
            ),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(dialogContext),
              child: Text(t('common.cancel')),
            ),
            FilledButton(
              onPressed: () => Navigator.pop(dialogContext, {
                'type': 'pause',
                'requested_pause_from': _dateOnly(from),
                if (until != null) 'requested_pause_until': _dateOnly(until!),
                if (messageController.text.trim().isNotEmpty)
                  'message': messageController.text.trim(),
              }),
              child: Text(t('clubs.pauseSend')),
            ),
          ],
        ),
      ),
    );
    messageController.dispose();
    return payload;
  }

  @override
  Widget build(BuildContext context) {
    final scope = AirmiusScope.of(context);
    final club = widget.club;
    final requested = widget.requested;
    final isOwner =
        AirmiusServicesScope.of(context).authState.user?.id == club.ownerId;
    final status = club.membershipStatus ?? (club.isMember ? 'active' : null);
    return AirmiusPanel(
      borderColor: requested || club.pauseRequested
          ? Theme.of(context).colorScheme.secondary.withValues(alpha: 0.60)
          : null,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Eyebrow(scope.t('clubs.membership')),
          const SizedBox(height: 8),
          Text(
            club.isMember
                ? _memberIntro(scope, status)
                : requested
                ? scope.t('clubs.requestArrived')
                : scope.t('clubs.requestIntro'),
            style: TextStyle(
              color: airmiusTextColor(context),
              fontWeight: FontWeight.w800,
              height: 1.35,
            ),
          ),
          const SizedBox(height: 12),
          _MembershipOption(
            title: scope.t('clubs.standardMembership'),
            meta: scope.t('clubs.standardMembershipMeta'),
          ),
          _MembershipOption(
            title: scope.t('clubs.supportingMembership'),
            meta: scope.t('clubs.supportingMembershipMeta'),
          ),
          const SizedBox(height: 14),
          if (club.isMember) ...[
            if (status == 'paused')
              StatusPill(scope.t('clubs.membershipPaused'))
            else if (club.pauseRequested)
              StatusPill(scope.t('clubs.pausePending')),
            if (club.memberPauseRequestsEnabled &&
                status == 'active' &&
                !club.pauseRequested)
              AirmiusButton(
                label: _busy
                    ? scope.t('clubs.pauseSending')
                    : scope.t('clubs.requestPause'),
                icon: Icons.pause_circle_outline,
                secondary: true,
                onPressed: _busy ? null : _requestPause,
              ),
            if (!isOwner)
              AirmiusButton(
                label: _busy
                    ? scope.t('clubs.terminationSending')
                    : scope.t('clubs.requestTermination'),
                icon: Icons.logout_outlined,
                danger: true,
                onPressed: _busy ? null : _requestTermination,
              ),
          ] else if (requested)
            AirmiusButton(
              label: scope.t('withdraw'),
              icon: Icons.undo_outlined,
              danger: true,
              onPressed: widget.onWithdraw,
            )
          else
            AirmiusButton(
              label: club.acceptsMemberships
                  ? scope.t('join')
                  : scope.t('clubs.requestsClosed'),
              icon: Icons.assignment_outlined,
              onPressed: widget.onJoin,
            ),
        ],
      ),
    );
  }

  String _memberIntro(AirmiusScope scope, String? status) {
    return switch (status) {
      'paused' => scope.t('clubs.membershipPausedBody'),
      _ => scope.t('clubs.youAreMember'),
    };
  }
}

class _DateButton extends StatelessWidget {
  const _DateButton({
    required this.label,
    required this.value,
    required this.onPressed,
    this.optional = false,
  });

  final String label;
  final DateTime? value;
  final VoidCallback onPressed;
  final bool optional;

  @override
  Widget build(BuildContext context) {
    final valueText = value == null
        ? AirmiusScope.of(context).t('clubs.notSelected')
        : MaterialLocalizations.of(context).formatMediumDate(value!);
    return OutlinedButton.icon(
      onPressed: onPressed,
      icon: const Icon(Icons.event_outlined),
      label: Text('$label: $valueText${optional ? '' : ' *'}'),
    );
  }
}

String _dateOnly(DateTime value) =>
    '${value.year.toString().padLeft(4, '0')}-${value.month.toString().padLeft(2, '0')}-${value.day.toString().padLeft(2, '0')}';

class _TeamsPanel extends StatelessWidget {
  const _TeamsPanel({required this.club});

  final ClubSummary club;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return AirmiusPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Eyebrow(t('clubs.teams')),
          const SizedBox(height: 8),
          if (club.teamList.isEmpty && club.teams == 0)
            Text(
              t('clubs.noTeams'),
              style: TextStyle(color: airmiusMutedColor(context)),
            )
          else if (club.teamList.isNotEmpty)
            for (final team in club.teamList)
              _TeamLine(title: team.name, meta: team.meta)
          else
            for (var index = 1; index <= club.teams.clamp(1, 3); index++)
              _TeamLine(
                title: '${t('teamDetail.team')} $index',
                meta: index == 1
                    ? t('clubs.mainTeam')
                    : t('clubs.trainingCompetition'),
              ),
        ],
      ),
    );
  }
}

class _DocumentsPanel extends StatelessWidget {
  const _DocumentsPanel();

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return AirmiusPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Eyebrow(t('clubs.clubDocuments')),
          const SizedBox(height: 8),
          Text(
            t('clubs.clubDocumentsBody'),
            style: TextStyle(color: airmiusMutedColor(context), height: 1.35),
          ),
          const SizedBox(height: 12),
          _DocumentLine(title: t('clubs.privacy'), requiredDoc: true),
          _DocumentLine(title: t('clubs.contributionRules'), requiredDoc: true),
          _DocumentLine(title: t('clubs.clubRules'), requiredDoc: false),
        ],
      ),
    );
  }
}

class _ClubPostsPanel extends StatelessWidget {
  const _ClubPostsPanel({required this.club});

  final ClubSummary club;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        if (club.postItems.isEmpty)
          AirmiusPanel(
            child: Text(
              t('clubs.noPosts'),
              style: TextStyle(color: airmiusMutedColor(context)),
            ),
          )
        else
          for (final post in club.postItems) ...[
            AirmiusPanel(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Icon(
                        Icons.forum_outlined,
                        color: airmiusAccentColor(context),
                      ),
                      const SizedBox(width: 8),
                      Expanded(
                        child: Text(
                          '${post['user'] is JsonMap ? (post['user'] as JsonMap)['name'] : club.name}',
                          style: TextStyle(fontWeight: FontWeight.w900),
                        ),
                      ),
                      if (post['created_at'] != null)
                        Text(
                          '${post['created_at']}'.split('T').first,
                          style: TextStyle(
                            color: airmiusMutedColor(context),
                            fontSize: 11,
                          ),
                        ),
                    ],
                  ),
                  const SizedBox(height: 10),
                  Text(
                    '${post['content'] ?? ''}',
                    style: TextStyle(
                      color: airmiusTextColor(context),
                      height: 1.4,
                    ),
                  ),
                  const SizedBox(height: 8),
                  Text(
                    '${post['likes_count'] ?? 0} ${t('likes')} · ${post['comments_count'] ?? 0} ${t('comments')}',
                    style: TextStyle(
                      color: airmiusMutedColor(context),
                      fontSize: 12,
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 12),
          ],
      ],
    );
  }
}

class _DocumentLine extends StatelessWidget {
  const _DocumentLine({required this.title, required this.requiredDoc});

  final String title;
  final bool requiredDoc;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return Padding(
      padding: const EdgeInsets.only(top: 10),
      child: Row(
        children: [
          Icon(Icons.description_outlined, color: airmiusAccentColor(context)),
          const SizedBox(width: 12),
          Expanded(
            child: Text(
              title,
              style: TextStyle(
                color: airmiusTextColor(context),
                fontWeight: FontWeight.w800,
              ),
            ),
          ),
          StatusPill(requiredDoc ? t('clubs.required') : t('clubs.optional')),
        ],
      ),
    );
  }
}

class _InfoPanel extends StatelessWidget {
  const _InfoPanel({required this.title, required this.rows});

  final String title;
  final List<_InfoRowData> rows;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return AirmiusPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Eyebrow(title),
          if (rows.isEmpty)
            Padding(
              padding: const EdgeInsets.only(top: 14),
              child: Text(
                t('clubs.noEntries'),
                style: TextStyle(color: airmiusMutedColor(context)),
              ),
            ),
          for (final row in rows) ...[
            const SizedBox(height: 14),
            Row(
              children: [
                UserBubble(label: row.initials, small: true),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        row.title,
                        style: TextStyle(
                          color: airmiusTextColor(context),
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                      Text(
                        row.meta,
                        style: TextStyle(
                          color: airmiusMutedColor(context),
                          fontSize: 12,
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ],
        ],
      ),
    );
  }
}

class _InfoRowData {
  const _InfoRowData(this.initials, this.title, this.meta);

  final String initials;
  final String title;
  final String meta;
}

class _MembershipOption extends StatelessWidget {
  const _MembershipOption({required this.title, required this.meta});

  final String title;
  final String meta;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(top: 10),
      child: Row(
        children: [
          Icon(
            Icons.fact_check_outlined,
            color: Theme.of(context).colorScheme.secondary,
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: TextStyle(
                    color: airmiusTextColor(context),
                    fontWeight: FontWeight.w900,
                  ),
                ),
                Text(
                  meta,
                  style: TextStyle(
                    color: airmiusMutedColor(context),
                    fontSize: 12,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _TeamLine extends StatelessWidget {
  const _TeamLine({required this.title, required this.meta});

  final String title;
  final String meta;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(top: 12),
      child: Row(
        children: [
          const UserBubble(label: 'T', small: true),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: TextStyle(
                    color: airmiusTextColor(context),
                    fontWeight: FontWeight.w900,
                  ),
                ),
                Text(
                  meta,
                  style: TextStyle(
                    color: airmiusMutedColor(context),
                    fontSize: 12,
                  ),
                ),
              ],
            ),
          ),
          Icon(Icons.chevron_right, color: airmiusMutedColor(context)),
        ],
      ),
    );
  }
}

class _DetailLine extends StatelessWidget {
  const _DetailLine({
    required this.icon,
    required this.label,
    required this.value,
  });

  final IconData icon;
  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(top: 10),
      child: Row(
        children: [
          Icon(icon, color: airmiusAccentColor(context), size: 20),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  label,
                  style: TextStyle(
                    color: airmiusMutedColor(context),
                    fontSize: 12,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                Text(
                  value,
                  style: TextStyle(
                    color: airmiusTextColor(context),
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _RoundAction extends StatelessWidget {
  const _RoundAction({required this.icon, required this.onTap, this.tooltip});

  final IconData icon;
  final VoidCallback? onTap;
  final String? tooltip;

  @override
  Widget build(BuildContext context) {
    final action = Semantics(
      button: true,
      enabled: onTap != null,
      label: tooltip,
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(16),
        child: Container(
          width: 48,
          height: 48,
          decoration: BoxDecoration(
            color: airmiusSurfaceColor(context).withValues(alpha: 0.88),
            borderRadius: BorderRadius.circular(16),
            border: Border.all(color: airmiusBorderColor(context)),
          ),
          child: Icon(
            icon,
            color: onTap == null
                ? airmiusMutedColor(context)
                : airmiusTextColor(context),
            size: 20,
          ),
        ),
      ),
    );
    return tooltip == null ? action : Tooltip(message: tooltip!, child: action);
  }
}

class _ClubTab {
  const _ClubTab(this.id, this.label, this.icon);

  final String id;
  final String label;
  final IconData icon;
}

class _ClubTabChip extends StatelessWidget {
  const _ClubTabChip({
    required this.tab,
    required this.selected,
    required this.onTap,
  });

  final _ClubTab tab;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return ChoiceChip(
      selected: selected,
      label: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(
            tab.icon,
            size: 16,
            color: selected
                ? airmiusTextColor(context)
                : airmiusMutedColor(context),
          ),
          const SizedBox(width: 6),
          Text(t(tab.label)),
        ],
      ),
      onSelected: (_) => onTap(),
      selectedColor: airmiusAccentColor(context).withValues(alpha: 0.24),
      backgroundColor: airmiusSurfaceSoftColor(context),
      side: BorderSide(
        color: selected
            ? airmiusAccentColor(context)
            : airmiusBorderColor(context),
      ),
      labelStyle: TextStyle(
        color: selected
            ? airmiusTextColor(context)
            : airmiusMutedColor(context),
        fontWeight: FontWeight.w900,
      ),
    );
  }
}
