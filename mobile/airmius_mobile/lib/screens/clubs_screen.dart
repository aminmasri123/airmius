import 'package:flutter/material.dart';

import '../core/airmius_api_models.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';
import '../core/airmius_theme.dart';
import '../models/club_summary.dart';
import '../widgets/airmius_widgets.dart';
import 'application_screen.dart';
import 'club_cockpit_screen.dart';
import 'club_membership_management_screen.dart';
import 'team_detail_screen.dart';

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
    return PageFrame(
      title: 'Vereine & Teams',
      subtitle: 'Verwalte Vereinsstruktur, Teams, Rollen und Einladungen',
      showHeader: true,
      trailing: _CreateClubButton(onPressed: _openCreateClub),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          FutureBuilder<List<ClubSummary>>(
            future: _clubsFuture,
            builder: (context, snapshot) {
              if (snapshot.connectionState == ConnectionState.waiting) {
                return const Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                  _ClubWorkspaceNav(canManageClubs: false),
                  SizedBox(height: 24),
                  AirmiusPanel(child: Center(child: Padding(padding: EdgeInsets.all(18), child: CircularProgressIndicator(color: AirmiusColors.blue)))),
                ]);
              }
              if (snapshot.hasError) {
                return Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                  const _ClubWorkspaceNav(canManageClubs: false),
                  const SizedBox(height: 24),
                  AirmiusPanel(
                    borderColor: AirmiusColors.red.withValues(alpha: .5),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        const Text('Vereine konnten nicht geladen werden.', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
                        const SizedBox(height: 8),
                        Text('${snapshot.error}', style: const TextStyle(color: AirmiusColors.muted, height: 1.35)),
                        const SizedBox(height: 12),
                        AirmiusButton(
                          label: 'Erneut laden',
                          icon: Icons.refresh_outlined,
                          secondary: true,
                          onPressed: () => setState(() {
                            _clubsFuture = _loadClubs();
                          }),
                        ),
                      ],
                    ),
                  ),
                ]);
              }
              final clubs = snapshot.data ?? const <ClubSummary>[];
              final canManageClubs = clubs.any((club) => club.canManage);
              if (clubs.isEmpty) {
                return const Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                  _ClubWorkspaceNav(canManageClubs: false),
                  SizedBox(height: 24),
                  AirmiusPanel(child: Center(child: Padding(padding: EdgeInsets.all(18), child: Text('Keine Vereine gefunden.', style: TextStyle(color: AirmiusColors.muted))))),
                ]);
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
                      requested: widget.requestedClubIds.contains(entry.$2.id) || entry.$2.hasPendingMembershipRequest,
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
        builder: (_) => const _ClubCreateWizardScreen(),
      ),
    );
    if (created == true && mounted) {
      setState(() {
        _clubsFuture = _loadClubs();
      });
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Verein registriert. Der Antrag wartet jetzt auf Pruefung.')),
      );
    }
  }
}

class _ClubCreateWizardScreen extends StatefulWidget {
  const _ClubCreateWizardScreen();

  @override
  State<_ClubCreateWizardScreen> createState() => _ClubCreateWizardScreenState();
}

class _ClubCreateWizardScreenState extends State<_ClubCreateWizardScreen> {
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
    ('AT', 'Oesterreich'),
    ('CH', 'Schweiz'),
    ('FR', 'Frankreich'),
    ('NL', 'Niederlande'),
    ('BE', 'Belgien'),
    ('TR', 'Tuerkei'),
    ('US', 'USA'),
  ];

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _sportsFuture ??= AirmiusServicesScope.of(context).repositories.sports.sports().then((page) => page.items);
  }

  @override
  void dispose() {
    for (final controller in [_name, _sportType, _officialNumber, _city, _postalCode, _state, _street, _houseNumber, _accountHolder, _iban, _bic]) {
      controller.dispose();
    }
    super.dispose();
  }

  void _next() {
    if (_step == 1 && _name.text.trim().isEmpty) {
      setState(() => _notice = 'Bitte gib einen Vereinsnamen ein.');
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
        _notice = 'Bitte gib einen Vereinsnamen ein.';
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
        'official_club_number': _official ? _nullable(_officialNumber.text) : null,
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
      if (mounted) setState(() => _notice = 'Verein konnte nicht gespeichert werden. $error');
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AirmiusColors.bg,
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
    return Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
      const _WizardIntro(title: 'Basisdaten', body: 'Name, Sportart und Land des Vereins. Nach dem Absenden prueft Airmius den Antrag.'),
      const SizedBox(height: 14),
      _WizardField(controller: _name, label: 'Vereinsname', placeholder: 'Vereinsname'),
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
              _selectedSportSlug = sport.slug.isNotEmpty ? sport.slug : sport.name;
              _sportType.text = sport.name;
            },
          );
        },
      ),
      const SizedBox(height: 12),
      _OfficialTile(value: _official, onChanged: (value) => setState(() => _official = value)),
      if (_official) ...[
        const SizedBox(height: 12),
        _WizardField(controller: _officialNumber, label: 'Vereinsnummer zur Pruefung', placeholder: 'z. B. Vereinsregister- oder Verbandsnummer'),
      ],
      const SizedBox(height: 12),
      _CountryField(value: _country, countries: _countries, onChanged: (value) => setState(() => _country = value)),
    ]);
  }

  Widget _adresse() {
    return Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
      const _WizardIntro(title: 'Adresse & Bankkonto', body: 'Optional: Standort und Bankkonto fuer Mitglieder-Ueberweisungen eintragen.'),
      const SizedBox(height: 14),
      _WizardField(controller: _city, placeholder: 'Stadt'),
      const SizedBox(height: 12),
      _WizardField(controller: _postalCode, placeholder: 'PLZ'),
      const SizedBox(height: 12),
      _WizardField(controller: _state, placeholder: 'Region'),
      const SizedBox(height: 12),
      _WizardField(controller: _street, placeholder: 'Strasse'),
      const SizedBox(height: 12),
      _WizardField(controller: _houseNumber, placeholder: 'Hausnummer'),
      const SizedBox(height: 14),
      AirmiusPanel(
        padding: const EdgeInsets.all(14),
        child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          const Text('BANKKONTO FUER VEREINSRECHNUNGEN', style: TextStyle(color: AirmiusColors.muted, fontSize: 11, fontWeight: FontWeight.w900)),
          const SizedBox(height: 6),
          const Text('Diese Daten werden Mitgliedern angezeigt, wenn sie offene Vereinsrechnungen per Ueberweisung zahlen.', style: TextStyle(color: AirmiusColors.muted, fontSize: 12, height: 1.35)),
          const SizedBox(height: 12),
          _WizardField(controller: _accountHolder, placeholder: 'Kontoinhaber'),
          const SizedBox(height: 12),
          _WizardField(controller: _iban, placeholder: 'IBAN'),
          const SizedBox(height: 12),
          _WizardField(controller: _bic, placeholder: 'BIC'),
        ]),
      ),
    ]);
  }

  Widget _pruefen() {
    return Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
      const _WizardIntro(title: 'Pruefen', body: 'Kontrolliere die Angaben vor dem Absenden. Der Verein wird als Antrag gespeichert.'),
      const SizedBox(height: 14),
      AirmiusPanel(
        padding: const EdgeInsets.all(16),
        child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          _ReviewLine(label: 'Verein', value: _name.text),
          _ReviewLine(label: 'Sportart', value: _sportType.text),
          _ReviewLine(label: 'Offizielle Pruefung', value: _official ? 'Beantragt' : 'Nicht beantragt'),
          if (_official) _ReviewLine(label: 'Vereinsnummer zur Pruefung', value: _officialNumber.text),
          const _ReviewLine(label: 'Status nach Absenden', value: 'Wartet auf Pruefung'),
          _ReviewLine(label: 'Land', value: _country),
          _ReviewLine(label: 'Adresse', value: '${_street.text} ${_houseNumber.text}, ${_postalCode.text} ${_city.text}'),
          _ReviewLine(label: 'Region', value: _state.text),
          _ReviewLine(label: 'Kontoinhaber', value: _accountHolder.text),
          _ReviewLine(label: 'IBAN', value: _iban.text),
          _ReviewLine(label: 'BIC', value: _bic.text, last: true),
        ]),
      ),
    ]);
  }
}

class _WizardHeader extends StatelessWidget {
  const _WizardHeader({required this.step, required this.onClose, required this.onStep});

  final int step;
  final VoidCallback? onClose;
  final ValueChanged<int> onStep;

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: const BoxDecoration(color: AirmiusColors.card, border: Border(bottom: BorderSide(color: AirmiusColors.border))),
      padding: const EdgeInsets.fromLTRB(16, 14, 16, 14),
      child: Column(children: [
        Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Expanded(
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              const Text('Verein registrieren', maxLines: 1, overflow: TextOverflow.ellipsis, style: TextStyle(color: AirmiusColors.text, fontSize: 18, fontWeight: FontWeight.w900)),
              const SizedBox(height: 4),
              Text('Schritt $step von 3', style: const TextStyle(color: AirmiusColors.muted, fontSize: 13, fontWeight: FontWeight.w700)),
            ]),
          ),
          IconButton(tooltip: 'Schliessen', onPressed: onClose, icon: const Icon(Icons.close_rounded, color: AirmiusColors.muted)),
        ]),
        const SizedBox(height: 14),
        Row(children: [
          Expanded(child: _StepPill(label: 'Basisdaten', active: step == 1, done: step > 1, onTap: () => onStep(1))),
          const SizedBox(width: 8),
          Expanded(child: _StepPill(label: 'Adresse', active: step == 2, done: step > 2, onTap: () => onStep(2))),
          const SizedBox(width: 8),
          Expanded(child: _StepPill(label: 'Pruefen', active: step == 3, done: false, onTap: () => onStep(3))),
        ]),
      ]),
    );
  }
}

class _StepPill extends StatelessWidget {
  const _StepPill({required this.label, required this.active, required this.done, required this.onTap});

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
          color: active ? AirmiusColors.blue : done ? AirmiusColors.green.withValues(alpha: .16) : AirmiusColors.input,
          borderRadius: BorderRadius.circular(99),
        ),
        child: Text(label, maxLines: 1, overflow: TextOverflow.ellipsis, style: TextStyle(color: active ? Colors.white : done ? AirmiusColors.green : AirmiusColors.muted, fontSize: 12, fontWeight: FontWeight.w900)),
      ),
    );
  }
}

class _WizardFooter extends StatelessWidget {
  const _WizardFooter({required this.step, required this.saving, required this.onBack, required this.onNext, required this.onSave});

  final int step;
  final bool saving;
  final VoidCallback onBack;
  final VoidCallback onNext;
  final VoidCallback onSave;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: const BoxDecoration(color: AirmiusColors.card, border: Border(top: BorderSide(color: AirmiusColors.border))),
      child: Row(children: [
        Expanded(
          child: OutlinedButton(
            onPressed: step == 1 || saving ? null : onBack,
            style: OutlinedButton.styleFrom(foregroundColor: AirmiusColors.muted, side: const BorderSide(color: AirmiusColors.border), padding: const EdgeInsets.symmetric(vertical: 14), shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8))),
            child: const Text('Zurueck', style: TextStyle(fontWeight: FontWeight.w900)),
          ),
        ),
        const SizedBox(width: 12),
        Expanded(
          child: FilledButton(
            onPressed: saving ? null : (step < 3 ? onNext : onSave),
            style: FilledButton.styleFrom(backgroundColor: AirmiusColors.blue, foregroundColor: Colors.white, padding: const EdgeInsets.symmetric(vertical: 14), shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8))),
            child: Text(saving ? 'Speichert...' : (step < 3 ? 'Weiter' : 'Speichern'), style: const TextStyle(fontWeight: FontWeight.w900)),
          ),
        ),
      ]),
    );
  }
}

class _WizardIntro extends StatelessWidget {
  const _WizardIntro({required this.title, required this.body});

  final String title;
  final String body;

  @override
  Widget build(BuildContext context) {
    return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Text(title, style: const TextStyle(color: AirmiusColors.text, fontSize: 16, fontWeight: FontWeight.w900)),
      const SizedBox(height: 6),
      Text(body, style: const TextStyle(color: AirmiusColors.muted, height: 1.35)),
    ]);
  }
}

class _WizardField extends StatelessWidget {
  const _WizardField({required this.controller, this.label, required this.placeholder});

  final TextEditingController controller;
  final String? label;
  final String placeholder;

  @override
  Widget build(BuildContext context) {
    return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      if (label != null) ...[
        Text(label!, style: const TextStyle(color: AirmiusColors.text, fontSize: 13, fontWeight: FontWeight.w900)),
        const SizedBox(height: 6),
      ],
      TextField(
        controller: controller,
        style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w800),
        decoration: InputDecoration(hintText: placeholder),
      ),
    ]);
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
    if (sports.isEmpty) {
      return _WizardField(
        controller: controller,
        label: 'Sportart',
        placeholder: loading ? 'Sportarten werden geladen...' : 'Sportart suchen',
      );
    }

    return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      const Text('Sportart', style: TextStyle(color: AirmiusColors.text, fontSize: 13, fontWeight: FontWeight.w900)),
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
        fieldViewBuilder: (context, textController, focusNode, onFieldSubmitted) {
          if (textController.text.isEmpty && controller.text.isNotEmpty) {
            textController.text = controller.text;
          }
          return TextField(
            controller: textController,
            focusNode: focusNode,
            style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w800),
            decoration: const InputDecoration(
              hintText: 'Sportart suchen',
              suffixIcon: Icon(Icons.search, color: AirmiusColors.muted),
            ),
            onChanged: (value) {
              controller.text = value;
              onTextChanged();
            },
          );
        },
        optionsViewBuilder: (context, onSelected, options) {
          final items = options.toList();
          final menuWidth = (MediaQuery.of(context).size.width - 32).clamp(180.0, 520.0).toDouble();
          return Align(
            alignment: Alignment.topLeft,
            child: Material(
              color: Colors.transparent,
              child: Container(
                width: menuWidth,
                margin: const EdgeInsets.only(top: 6),
                constraints: const BoxConstraints(maxHeight: 260),
                decoration: BoxDecoration(
                  color: AirmiusColors.card,
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(color: AirmiusColors.border),
                  boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: .35), blurRadius: 18, offset: const Offset(0, 10))],
                ),
                child: ListView.separated(
                  padding: const EdgeInsets.symmetric(vertical: 6),
                  shrinkWrap: true,
                  itemCount: items.length,
                  separatorBuilder: (_, __) => const Divider(height: 1, color: AirmiusColors.border),
                  itemBuilder: (context, index) {
                    final sport = items[index];
                    return ListTile(
                      dense: true,
                      onTap: () => onSelected(sport),
                      leading: const Icon(Icons.sports_outlined, color: AirmiusColors.blue),
                      title: Text(sport.name, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
                      subtitle: sport.slug.isEmpty ? null : Text(sport.slug, style: const TextStyle(color: AirmiusColors.muted, fontSize: 12)),
                    );
                  },
                ),
              ),
            ),
          );
        },
      ),
    ]);
  }
}

class _CountryField extends StatelessWidget {
  const _CountryField({required this.value, required this.countries, required this.onChanged});

  final String value;
  final List<(String, String)> countries;
  final ValueChanged<String> onChanged;

  @override
  Widget build(BuildContext context) {
    return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
      const Text('Land', style: TextStyle(color: AirmiusColors.text, fontSize: 13, fontWeight: FontWeight.w900)),
      const SizedBox(height: 6),
      DropdownButtonFormField<String>(
        value: value,
        dropdownColor: AirmiusColors.card,
        decoration: const InputDecoration(),
        style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w800),
        items: [for (final country in countries) DropdownMenuItem(value: country.$1, child: Text(country.$2))],
        onChanged: (value) {
          if (value != null) onChanged(value);
        },
      ),
    ]);
  }
}

class _OfficialTile extends StatelessWidget {
  const _OfficialTile({required this.value, required this.onChanged});

  final bool value;
  final ValueChanged<bool> onChanged;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      borderRadius: BorderRadius.circular(8),
      onTap: () => onChanged(!value),
      child: Container(
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(color: value ? AirmiusColors.blue.withValues(alpha: .1) : AirmiusColors.bg, borderRadius: BorderRadius.circular(8), border: Border.all(color: value ? AirmiusColors.blue : AirmiusColors.border)),
        child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Container(
            width: 24,
            height: 24,
            decoration: BoxDecoration(color: value ? AirmiusColors.blue : AirmiusColors.input, borderRadius: BorderRadius.circular(7), border: Border.all(color: value ? AirmiusColors.blue : AirmiusColors.border)),
            child: value ? const Icon(Icons.check, color: Colors.white, size: 16) : null,
          ),
          const SizedBox(width: 12),
          const Expanded(
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text('Offizielle Pruefung beantragen', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
              SizedBox(height: 4),
              Text('Der Verein wird erst nach Admin-Freigabe oeffentlich sichtbar und als offiziell markiert.', style: TextStyle(color: AirmiusColors.muted, fontSize: 12, height: 1.35)),
            ]),
          ),
        ]),
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
      decoration: BoxDecoration(color: AirmiusColors.red.withValues(alpha: .12), borderRadius: BorderRadius.circular(8), border: Border.all(color: AirmiusColors.red.withValues(alpha: .4))),
      child: Text(text, style: const TextStyle(color: AirmiusColors.red, fontWeight: FontWeight.w800, height: 1.35)),
    );
  }
}

class _ReviewLine extends StatelessWidget {
  const _ReviewLine({required this.label, required this.value, this.last = false});

  final String label;
  final String value;
  final bool last;

  @override
  Widget build(BuildContext context) {
    final display = value.trim().isEmpty || value.trim() == ',' ? '-' : value.trim();
    return Padding(
      padding: EdgeInsets.only(bottom: last ? 0 : 10),
      child: RichText(
        text: TextSpan(
          style: const TextStyle(color: AirmiusColors.muted, fontSize: 14, height: 1.3),
          children: [
            TextSpan(text: '$label: ', style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
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
    return FilledButton.icon(
      onPressed: onPressed,
      icon: const Icon(Icons.add, size: 18),
      label: const Text('Verein registrieren', style: TextStyle(fontWeight: FontWeight.w900)),
      style: FilledButton.styleFrom(
        backgroundColor: AirmiusColors.text,
        foregroundColor: AirmiusColors.header,
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 13),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
      ),
    );
  }
}

class _ClubWorkspaceNav extends StatelessWidget {
  const _ClubWorkspaceNav({required this.canManageClubs});

  final bool canManageClubs;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      padding: const EdgeInsets.fromLTRB(14, 14, 12, 12),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text('VEREINSBEREICH', style: TextStyle(color: AirmiusColors.muted, fontSize: 12, letterSpacing: .4, fontWeight: FontWeight.w900)),
                    SizedBox(height: 8),
                    Text('Vereinsstruktur, Teams, Rollen und Einladungen.', style: TextStyle(color: AirmiusColors.muted, fontSize: 14, height: 1.45, fontWeight: FontWeight.w600)),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 14),
          Row(
            children: [
              if (canManageClubs) ...[
                Expanded(
                  child: _WorkspaceTab(
                    icon: Icons.speed_outlined,
                    label: 'Cockpit',
                    selected: false,
                    onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const ClubCockpitScreen())),
                  ),
                ),
                const SizedBox(width: 8),
              ],
              Expanded(
                child: _WorkspaceTab(
                  icon: Icons.account_tree_outlined,
                  label: 'Vereine & Teams',
                  selected: true,
                  onTap: () {},
                ),
              ),
              if (canManageClubs) ...[
                const SizedBox(width: 8),
                Expanded(
                  child: _WorkspaceTab(
                    icon: Icons.badge_outlined,
                    label: 'Mitglieder',
                    selected: false,
                    onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const ClubMembershipManagementScreen())),
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
  const _WorkspaceTab({required this.icon, required this.label, required this.selected, required this.onTap});

  final IconData icon;
  final String label;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final color = selected ? AirmiusColors.header : AirmiusColors.text;
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(4),
      child: Container(
        height: 38,
        padding: const EdgeInsets.symmetric(horizontal: 6),
        decoration: BoxDecoration(
          color: selected ? AirmiusColors.text : AirmiusColors.card,
          borderRadius: BorderRadius.circular(4),
          border: Border.all(color: selected ? AirmiusColors.text : AirmiusColors.border),
        ),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(icon, size: 15, color: color),
            const SizedBox(width: 5),
            Flexible(child: Text(label, maxLines: 1, overflow: TextOverflow.ellipsis, style: TextStyle(color: color, fontSize: 12, fontWeight: FontWeight.w900))),
          ],
        ),
      ),
    );
  }
}

class _ClubCard extends StatefulWidget {
  const _ClubCard({required this.club, required this.showManageActions, required this.requested, required this.onRequest, required this.onWithdraw, required this.onReload});

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

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      onTap: _toggleExpanded,
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              _InitialsCircle(club.name),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(club.name, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(color: AirmiusColors.text, fontSize: 16, fontWeight: FontWeight.w900)),
                    const SizedBox(height: 3),
                    Text(_clubMeta(club), maxLines: 2, overflow: TextOverflow.ellipsis, style: const TextStyle(color: AirmiusColors.muted, fontSize: 12, height: 1.25, fontWeight: FontWeight.w700)),
                  ],
                ),
              ),
            ],
          ),
          if (widget.showManageActions || club.canDelete || widget.requested) ...[
            const SizedBox(height: 12),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: [
                if (widget.showManageActions)
                  _InlineAction(
                    label: 'Daten bearbeiten',
                    onPressed: () => _openPanel('edit'),
                  ),
                if (widget.showManageActions)
                  _InlineAction(
                    label: '+ Team',
                    onPressed: () => _openPanel('team'),
                  ),
                if (club.canDelete || widget.showManageActions)
                  _InlineAction(
                    label: 'L\u00f6schen',
                    danger: true,
                    onPressed: () => confirmDanger(context, 'Verein "${club.name}" l\u00f6schen', 'Dadurch werden auch alle Teams dieses Vereins gelöscht. Diese Aktion kann nicht rückgaengig gemacht werden.', 'L\u00f6schen'),
                  ),
                if (widget.requested && !club.canManage)
                  _InlineAction(label: 'Anfrage offen', onPressed: null),
              ],
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
                      builder: (_) => ClubProfileScreen(club: detail, requested: widget.requested, onRequest: widget.onRequest, onWithdraw: widget.onWithdraw),
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
    final sport = (club.sportType == null || club.sportType!.trim().isEmpty) ? 'Sportart offen' : club.sportType!.trim();
    final location = [
      if (club.city.trim().isNotEmpty) club.city.trim() else 'Ort offen',
      if (club.postalCode?.trim().isNotEmpty == true) club.postalCode!.trim(),
    ].join(' ');
    final country = club.country?.trim().isNotEmpty == true ? club.country!.trim() : 'Land offen';
    return '$sport - $location - $country - ${club.teams} Teams';
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
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        if (panel == 'edit') ...[
          _ClubEditInlinePanel(club: club, onOpenProfile: onOpenProfile),
          const SizedBox(height: 12),
        ],
        if (panel == 'team') ...[
          _TeamCreateInlinePanel(club: club, onCreated: onReload),
          const SizedBox(height: 12),
        ],
        _InlineSection(
          title: 'Teams',
          subtitle: '${club.teamList.isNotEmpty ? club.teamList.length : club.teams} Teams',
          action: club.canManage ? _SmallInlineButton(label: '+ Team', onPressed: onTeam) : null,
          child: club.teamList.isEmpty
              ? const Text('Noch keine Teams sichtbar.', style: TextStyle(color: AirmiusColors.muted, fontWeight: FontWeight.w600))
              : LayoutBuilder(
                  builder: (context, constraints) {
                    final columns = constraints.maxWidth >= 520 ? 2 : 1;
                    const gap = 10.0;
                    final width = (constraints.maxWidth - gap * (columns - 1)) / columns;
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
                                MaterialPageRoute(builder: (_) => TeamDetailScreen(title: team.name, mode: 'Profil', teamId: team.id)),
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
          title: 'Vereinsmitglieder',
          subtitle: '${club.management?.linkedPeopleCount ?? club.members} Personen in der Verwaltung',
          action: club.canManage ? _SmallInlineButton(label: 'Daten bearbeiten', onPressed: onEdit) : null,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              _InlineMetricLine(icon: Icons.groups_outlined, title: 'Aktive Mitglieder', value: '${club.management?.activeMembersCount ?? club.members}'),
              const SizedBox(height: 8),
              _InlineMetricLine(icon: Icons.account_tree_outlined, title: 'Teams', value: '${club.teams}'),
              const SizedBox(height: 8),
              _InlineMetricLine(icon: Icons.person_add_alt_1_outlined, title: 'Offene Vereinsanfragen', value: '${club.pendingMembershipRequests}'),
              const SizedBox(height: 8),
              _InlineMetricLine(icon: Icons.verified_outlined, title: 'Status', value: club.verified ? 'Freigegeben' : 'Wartet auf Prüfung'),
            ],
          ),
        ),
        const SizedBox(height: 12),
        if (club.canManage && club.management != null) ...[
          _ClubManagementSection(
            club: club,
            onReload: onReload,
          ),
          const SizedBox(height: 12),
        ],
        _InlineSection(
          title: 'Schnellzugriff',
          subtitle: 'Wie Web: direkt im Vereinsblock arbeiten',
          child: Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              _SmallInlineButton(label: 'Vereinsprofil', onPressed: onOpenProfile),
              _SmallInlineButton(label: 'Neu laden', onPressed: onReload),
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
    final club = widget.club;
    final management = club.management!;
    return _InlineSection(
      title: 'Verwaltungsdaten',
      subtitle: 'Mitglieder, Anfragen, Beiträge und Abrechnung wie im Web',
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          LayoutBuilder(
            builder: (context, constraints) {
              final columns = constraints.maxWidth >= 520 ? 2 : 1;
              const gap = 8.0;
              final width = (constraints.maxWidth - gap * (columns - 1)) / columns;
              return Wrap(
                spacing: gap,
                runSpacing: gap,
                children: [
                  SizedBox(width: width, child: _ManagementMetricTile(title: 'Offen', value: _formatMoney(management.openInvoiceAmount), subtitle: '${management.openInvoicesCount} Rechnung(en)')),
                  SizedBox(width: width, child: _ManagementMetricTile(title: 'SEPA bereit', value: '${management.sepaReadyMembersCount}', subtitle: 'Mandate mit IBAN und Referenz')),
                  SizedBox(width: width, child: _ManagementMetricTile(title: 'Wiederkehrende Beiträge', value: _formatMoney(management.recurringContributionTotal), subtitle: 'Summe aktiver Beitragssaetze')),
                  SizedBox(width: width, child: _ManagementMetricTile(title: 'Regeln', value: '${club.contributionRulesCount}', subtitle: '${club.membershipTypesCount} Mitgliedschaftstyp(en)')),
                  SizedBox(width: width, child: _ManagementMetricTile(title: 'Zahlungen', value: '${club.paymentsCount}', subtitle: '${club.bankTransactionsCount} Banktransaktion(en)')),
                  SizedBox(width: width, child: _ManagementMetricTile(title: 'Externe Mitglieder', value: '${club.externalMembersCount}', subtitle: 'Importierte oder eingeladene Personen')),
                ],
              );
            },
          ),
          if (management.membershipRequests.isNotEmpty) ...[
            const SizedBox(height: 12),
            const Text('Offene Mitgliedschaftsanfragen', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
            const SizedBox(height: 8),
            for (final request in management.membershipRequests.take(4)) ...[
              _MembershipRequestCard(
                request: request,
                onApprove: () => _reviewRequest(context, request, approve: true),
                onDecline: () => _reviewRequest(context, request, approve: false),
              ),
              const SizedBox(height: 8),
            ],
          ],
          if (management.pendingTeamJoinRequests.isNotEmpty) ...[
            const SizedBox(height: 8),
            const Text('Offene Team-Beitrittsanfragen', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
            const SizedBox(height: 8),
            for (final request in management.pendingTeamJoinRequests.take(4)) ...[
              _RawRequestLine(request: request),
              const SizedBox(height: 8),
            ],
          ],
          if (management.members.isNotEmpty) ...[
            const SizedBox(height: 8),
            const Text('Mitglieder', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
            const SizedBox(height: 8),
            for (final member in management.members.take(5)) ...[
              _MemberLine(
                member: member,
                isUpdating: _updatingMemberId == member.id,
                onRoleChanged: (role) => _updateMemberRole(context, member, role),
              ),
              const SizedBox(height: 8),
            ],
          ],
        ],
      ),
    );
  }

  Future<void> _reviewRequest(BuildContext context, AirmiusClubMembershipRequest request, {required bool approve}) async {
    final ok = approve
        ? true
        : await confirmDanger(context, 'Anfrage ablehnen', 'Moechtest du die Anfrage von ${request.applicantName ?? 'dieser Person'} ablehnen?', 'Ablehnen');
    if (!ok || !context.mounted) return;

    try {
      final services = AirmiusServicesScope.of(context);
      if (approve) {
        await services.repositories.memberships.approveClubRequest(widget.club.id, request.id);
      } else {
        await services.repositories.memberships.declineClubRequest(widget.club.id, request.id);
      }
      if (!context.mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(approve ? 'Anfrage angenommen.' : 'Anfrage abgelehnt.')));
      widget.onReload();
    } catch (error) {
      if (!context.mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('Anfrage konnte nicht verarbeitet werden: $error')));
    }
  }

  Future<void> _updateMemberRole(BuildContext context, AirmiusClubMember member, String role) async {
    if (_updatingMemberId != null || member.role == role) return;
    setState(() => _updatingMemberId = member.id);

    try {
      await AirmiusServicesScope.of(context).repositories.clubs.updateClubMemberRole(widget.club.id, member.id, role);
      if (!context.mounted) return;
      setState(() => _updatingMemberId = null);
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Vereinsrolle aktualisiert.')));
      widget.onReload();
    } catch (error) {
      if (!context.mounted) return;
      setState(() => _updatingMemberId = null);
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('Vereinsrolle konnte nicht gespeichert werden: $error')));
    }
  }
}

class _ManagementMetricTile extends StatelessWidget {
  const _ManagementMetricTile({required this.title, required this.value, required this.subtitle});

  final String title;
  final String value;
  final String subtitle;

  @override
  Widget build(BuildContext context) {
    return Container(
      constraints: const BoxConstraints(minHeight: 82),
      padding: const EdgeInsets.all(11),
      decoration: BoxDecoration(color: AirmiusColors.card, borderRadius: BorderRadius.circular(10), border: Border.all(color: AirmiusColors.border)),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(title.toUpperCase(), maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(color: AirmiusColors.muted, fontSize: 10, fontWeight: FontWeight.w900)),
          const SizedBox(height: 8),
          Text(value, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(color: AirmiusColors.text, fontSize: 18, fontWeight: FontWeight.w900)),
          const SizedBox(height: 4),
          Text(subtitle, maxLines: 2, overflow: TextOverflow.ellipsis, style: const TextStyle(color: AirmiusColors.muted, fontSize: 11, height: 1.25)),
        ],
      ),
    );
  }
}

class _MembershipRequestCard extends StatelessWidget {
  const _MembershipRequestCard({required this.request, required this.onApprove, required this.onDecline});

  final AirmiusClubMembershipRequest request;
  final VoidCallback onApprove;
  final VoidCallback onDecline;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(11),
      decoration: BoxDecoration(color: AirmiusColors.card, borderRadius: BorderRadius.circular(10), border: Border.all(color: AirmiusColors.border)),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(request.applicantName ?? 'Unbekannte Person', style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
          const SizedBox(height: 3),
          Text(request.applicantEmail ?? request.message ?? 'Mitgliedschaftsanfrage', maxLines: 2, overflow: TextOverflow.ellipsis, style: const TextStyle(color: AirmiusColors.muted, fontSize: 12, height: 1.3)),
          if (request.membershipTypeName != null || request.previewAmount != null) ...[
            const SizedBox(height: 6),
            Text(
              [
                if (request.membershipTypeName != null) 'Typ: ${request.membershipTypeName}',
                if (request.previewAmount != null) 'Vorschau: ${request.previewAmount} ${request.previewInterval ?? ''}'.trim(),
              ].join(' - '),
              style: const TextStyle(color: AirmiusColors.muted, fontSize: 11, fontWeight: FontWeight.w700),
            ),
          ],
          if (request.preferredPaymentMethod != null || request.requestedBillingInterval != null) ...[
            const SizedBox(height: 6),
            Text(
              [
                if (request.preferredPaymentMethod != null) 'Zahlung: ${request.preferredPaymentMethod}',
                if (request.requestedBillingInterval != null) 'Intervall: ${request.requestedBillingInterval}',
              ].join(' - '),
              style: const TextStyle(color: AirmiusColors.muted, fontSize: 11, fontWeight: FontWeight.w700),
            ),
          ],
          const SizedBox(height: 10),
          Row(
            children: [
              Expanded(child: _SmallInlineButton(label: 'Annehmen', filled: true, onPressed: onApprove)),
              const SizedBox(width: 8),
              Expanded(child: _SmallInlineButton(label: 'Ablehnen', onPressed: onDecline)),
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
    final user = request['user'];
    final team = request['team'];
    final userName = user is JsonMap ? (user['name']?.toString() ?? 'Unbekannt') : 'Unbekannt';
    final teamName = team is JsonMap ? (team['name']?.toString() ?? 'Team') : 'Team';
    return _InlineMetricLine(icon: Icons.group_add_outlined, title: '$userName moechte zu $teamName', value: 'offen');
  }
}

class _MemberLine extends StatelessWidget {
  const _MemberLine({required this.member, required this.isUpdating, required this.onRoleChanged});

  final AirmiusClubMember member;
  final bool isUpdating;
  final ValueChanged<String> onRoleChanged;

  @override
  Widget build(BuildContext context) {
    final status = member.status?.isNotEmpty == true ? member.status! : 'aktiv';
    final role = _clubRoleValues.contains(member.role) ? member.role! : 'member';
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(color: AirmiusColors.cardSoft, borderRadius: BorderRadius.circular(14), border: Border.all(color: AirmiusColors.border)),
      child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
        Row(children: [
          AirmiusAvatar(member.name, imageUrl: member.avatarUrl),
          const SizedBox(width: 10),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(member.name, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
            Text(member.email.isNotEmpty ? member.email : status, style: const TextStyle(color: AirmiusColors.muted)),
          ])),
          StatusPill(_clubRoleLabel(role), color: _clubRoleColor(role)),
        ]),
        const SizedBox(height: 10),
        DropdownButtonFormField<String>(
          value: role,
          isExpanded: true,
          dropdownColor: AirmiusColors.card,
          decoration: InputDecoration(
            labelText: isUpdating ? 'Speichert...' : 'Vereinsrolle',
            labelStyle: const TextStyle(color: AirmiusColors.muted, fontWeight: FontWeight.w800),
            enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: AirmiusColors.border)),
            focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: const BorderSide(color: AirmiusColors.blue)),
            filled: true,
            fillColor: AirmiusColors.card,
          ),
          style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900),
          items: [
            for (final item in _clubRoleValues) DropdownMenuItem(value: item, child: Text(_clubRoleLabel(item))),
          ],
          onChanged: isUpdating ? null : (value) {
            if (value != null) onRoleChanged(value);
          },
        ),
      ]),
    );
  }
}

const _clubRoleValues = ['owner', 'admin', 'manager', 'academy_manager', 'financial_controller', 'trainer', 'member'];

String _clubRoleLabel(String role) => switch (role) {
      'owner' => 'Owner',
      'admin' => 'Verein-Admin',
      'manager' => 'Manager',
      'academy_manager' => 'Akademie-Manager',
      'financial_controller' => 'Finanzen',
      'trainer' => 'Trainer',
      'member' => 'Mitglied',
      _ => role,
    };

Color _clubRoleColor(String role) => switch (role) {
      'owner' || 'admin' => AirmiusColors.blue,
      'manager' || 'academy_manager' || 'financial_controller' => AirmiusColors.green,
      'trainer' => AirmiusColors.amber,
      _ => AirmiusColors.muted,
    };

String _formatMoney(double value) => '${value.toStringAsFixed(2).replaceAll('.', ',')} EUR';

class _ClubEditInlinePanel extends StatelessWidget {
  const _ClubEditInlinePanel({required this.club, required this.onOpenProfile});

  final ClubSummary club;
  final VoidCallback onOpenProfile;

  @override
  Widget build(BuildContext context) {
    return _InlineSection(
      title: 'Vereinsdaten bearbeiten',
      subtitle: 'Basisdaten, Adresse und Sportart pflegen.',
      child: Column(
        children: [
          _ReadOnlyFormLine(label: 'Verein', value: club.name),
          const SizedBox(height: 10),
          _ReadOnlyFormLine(label: 'Sportart', value: club.sportType?.isNotEmpty == true ? club.sportType! : 'Sportart offen'),
          const SizedBox(height: 10),
          Row(
            children: [
              Expanded(child: _ReadOnlyFormLine(label: 'Stadt', value: club.city.isNotEmpty ? club.city : 'Ort offen')),
              const SizedBox(width: 10),
              Expanded(child: _ReadOnlyFormLine(label: 'PLZ', value: club.postalCode?.isNotEmpty == true ? club.postalCode! : '-')),
            ],
          ),
          const SizedBox(height: 12),
          Row(
            children: [
              Expanded(child: _SmallInlineButton(label: 'Speichern', filled: true, onPressed: () => openUiAction(context, title: 'Vereinsdaten speichern', body: 'Der native Flutter-Dialog ist vorbereitet. Für echtes Speichern braucht die mobile API noch PUT /api/v1/clubs/{id}.', status: 'API fehlt', icon: Icons.save_outlined))),
              const SizedBox(width: 10),
              Expanded(child: _SmallInlineButton(label: 'Details', onPressed: onOpenProfile)),
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
    _sportsFuture ??= AirmiusServicesScope.of(context).repositories.sports.sports().then((page) => page.items);
  }

  @override
  void dispose() {
    _nameController.dispose();
    _sportController.dispose();
    super.dispose();
  }

  Future<void> _createTeam() async {
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
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Team erstellt.')));
      widget.onCreated();
      _nameController.clear();
    } catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('Team konnte nicht erstellt werden: $error')));
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return _InlineSection(
      title: 'Team hinzufuegen',
      subtitle: 'Neues Team für ${widget.club.name} erstellen.',
      child: Column(
        children: [
          TextField(
            controller: _nameController,
            style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w800),
            decoration: const InputDecoration(labelText: 'Teamname', hintText: 'z.B. U16, Herren Aktiv'),
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
            label: _saving ? 'Speichert...' : 'Team erstellen',
            filled: true,
            onPressed: _createTeam,
          ),
        ],
      ),
    );
  }
}

class _InlineSection extends StatelessWidget {
  const _InlineSection({required this.title, required this.subtitle, required this.child, this.action});

  final String title;
  final String subtitle;
  final Widget child;
  final Widget? action;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(13),
      decoration: BoxDecoration(color: AirmiusColors.bg, borderRadius: BorderRadius.circular(12), border: Border.all(color: AirmiusColors.border)),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
                    const SizedBox(height: 3),
                    Text(subtitle, style: const TextStyle(color: AirmiusColors.muted, fontSize: 12, fontWeight: FontWeight.w600)),
                  ],
                ),
              ),
              if (action != null) action!,
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
        decoration: BoxDecoration(color: AirmiusColors.card, borderRadius: BorderRadius.circular(12), border: Border.all(color: AirmiusColors.border)),
        child: Row(
          children: [
            _InitialsCircle(team.name, size: 34),
            const SizedBox(width: 10),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(team.name, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
                  const SizedBox(height: 3),
                  Text(team.meta, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(color: AirmiusColors.muted, fontSize: 12)),
                ],
              ),
            ),
            const SizedBox(width: 8),
            const Icon(Icons.chevron_right, color: AirmiusColors.muted),
          ],
        ),
      ),
    );
  }
}

class _InlineMetricLine extends StatelessWidget {
  const _InlineMetricLine({required this.icon, required this.title, required this.value});

  final IconData icon;
  final String title;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Icon(icon, color: AirmiusColors.blue, size: 19),
        const SizedBox(width: 10),
        Expanded(child: Text(title, style: const TextStyle(color: AirmiusColors.muted, fontWeight: FontWeight.w700))),
        Text(value, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
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
      child: Text(value, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w800)),
    );
  }
}

class _SmallInlineButton extends StatelessWidget {
  const _SmallInlineButton({required this.label, required this.onPressed, this.filled = false});

  final String label;
  final VoidCallback onPressed;
  final bool filled;

  @override
  Widget build(BuildContext context) {
    if (filled) {
      return FilledButton(
        onPressed: onPressed,
        style: FilledButton.styleFrom(
          backgroundColor: AirmiusColors.blue,
          foregroundColor: Colors.white,
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
        ),
        child: Text(label, style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w900)),
      );
    }

    return OutlinedButton(
      onPressed: onPressed,
      style: OutlinedButton.styleFrom(
        foregroundColor: AirmiusColors.text,
        side: const BorderSide(color: AirmiusColors.border),
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
      ),
      child: Text(label, style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w900)),
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
      decoration: const BoxDecoration(
        color: AirmiusColors.cardSoft,
        shape: BoxShape.circle,
      ),
      alignment: Alignment.center,
      child: Text(initialsFromName(name), style: const TextStyle(color: AirmiusColors.text, fontSize: 13, fontWeight: FontWeight.w900)),
    );
  }
}

class _InlineAction extends StatelessWidget {
  const _InlineAction({required this.label, required this.onPressed, this.danger = false});

  final String label;
  final VoidCallback? onPressed;
  final bool danger;

  @override
  Widget build(BuildContext context) {
    if (danger) {
      return FilledButton(
        onPressed: onPressed,
        style: FilledButton.styleFrom(
          backgroundColor: AirmiusColors.red,
          foregroundColor: Colors.white,
          padding: const EdgeInsets.symmetric(horizontal: 13, vertical: 12),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
        ),
        child: Text(label, style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w900)),
      );
    }

    return OutlinedButton(
      onPressed: onPressed,
      style: OutlinedButton.styleFrom(
        foregroundColor: AirmiusColors.text,
        side: const BorderSide(color: AirmiusColors.border),
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 12),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
      ),
      child: Text(label, style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w800)),
    );
  }
}

class ClubProfileScreen extends StatefulWidget {
  const ClubProfileScreen({super.key, required this.club, required this.requested, required this.onRequest, required this.onWithdraw});

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
    return Scaffold(
      appBar: AppBar(
        backgroundColor: AirmiusColors.header,
        surfaceTintColor: Colors.transparent,
        title: Text(club.name, style: const TextStyle(fontWeight: FontWeight.w900)),
      ),
      body: PageFrame(
        title: club.name,
        subtitle: 'Vereinsprofil, Teams, Rollen und sichtbare Beiträge',
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            FutureBuilder<ClubSummary>(
              future: _clubDetailFuture,
              builder: (context, snapshot) {
                final profileClub = snapshot.data ?? club;
                final requested = _isRequested(profileClub);
                return _ClubProfileHero(
                  club: profileClub,
                  requested: requested,
                  onJoin: profileClub.acceptsMemberships && !profileClub.isMember && !requested ? () => _openApplication(context, profileClub) : null,
                  onWithdraw: requested ? () => _withdraw(context, profileClub) : null,
                );
              },
            ),
            const SizedBox(height: 14),
            FutureBuilder<ClubSummary>(
              future: _clubDetailFuture,
              builder: (context, snapshot) => _ClubStats(club: snapshot.data ?? club),
            ),
            const SizedBox(height: 14),
            _ClubWorkspaceTabs(active: _activeTab, onSelect: (value) => setState(() => _activeTab = value)),
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
                  onJoin: profileClub.acceptsMemberships && !profileClub.isMember && !requested ? () => _openApplication(context, profileClub) : null,
                  onWithdraw: requested ? () => _withdraw(context, profileClub) : null,
                );
              },
            ),
          ],
        ),
      ),
    );
  }

  Future<void> _openApplication(BuildContext context, ClubSummary selectedClub) async {
    final sent = await Navigator.push<bool>(context, MaterialPageRoute(fullscreenDialog: true, builder: (_) => ApplicationScreen(club: selectedClub)));
    if (sent == true && context.mounted) {
      widget.onRequest(selectedClub);
      setState(() {
        _requestStatusOverride = true;
        _activeTab = 'beitritt';
      });
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Mitgliedschaftsanfrage gesendet.')));
    }
  }

  Future<void> _withdraw(BuildContext context, ClubSummary selectedClub) async {
    final ok = await confirmDanger(context, 'Anfrage zurückziehen', 'Moechtest du deine Mitgliedschaftsanfrage bei ${selectedClub.name} wirklich zurückziehen?');
    if (ok && context.mounted) {
      try {
        final services = AirmiusServicesScope.of(context);
        await services.repositories.memberships.withdrawClubRequest(selectedClub.id);
        widget.onWithdraw(selectedClub);
        setState(() => _requestStatusOverride = false);
        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Mitgliedschaftsanfrage zurückgezogen.')));
      } catch (error) {
        if (!context.mounted) return;
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('Anfrage konnte nicht zurückgezogen werden: $error')));
      }
    }
  }

  bool _isRequested(ClubSummary profileClub) {
    return _requestStatusOverride ?? (widget.requested || profileClub.hasPendingMembershipRequest);
  }
}

class _ClubProfileHero extends StatelessWidget {
  const _ClubProfileHero({required this.club, required this.requested, required this.onJoin, required this.onWithdraw});

  final ClubSummary club;
  final bool requested;
  final VoidCallback? onJoin;
  final VoidCallback? onWithdraw;

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
                child: AirmiusAvatar(club.name, imageUrl: club.logoUrl, large: true),
              ),
              Positioned(
                right: 12,
                bottom: 12,
                child: Wrap(
                  spacing: 8,
                  children: [
                    _RoundAction(icon: Icons.camera_alt_outlined, onTap: () => openUiAction(context, title: 'Cover aktualisieren', body: 'Cover-Bild wie im Web-Cockpit vorbereiten.', icon: Icons.image_outlined)),
                    _RoundAction(icon: Icons.more_horiz, onTap: () => _openMoreSheet(context)),
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
                              Expanded(child: Text(club.name, style: const TextStyle(color: AirmiusColors.text, fontSize: 25, fontWeight: FontWeight.w900, height: 1.05))),
                              if (club.verified) const Icon(Icons.verified, color: AirmiusColors.blue),
                            ],
                          ),
                          const SizedBox(height: 5),
                          Text('${club.city} - Vereinsprofil', style: const TextStyle(color: AirmiusColors.muted, fontWeight: FontWeight.w700)),
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
                    StatusPill(club.isMember ? 'Mitglied' : (requested ? scope.t('sent') : (club.acceptsMemberships ? 'Mitgliedschaft offen' : 'Nur Ansicht')), color: club.isMember ? AirmiusColors.green : requested ? AirmiusColors.green : AirmiusColors.blue),
                    const StatusPill('Struktur'),
                    const StatusPill('Mobile Cockpit'),
                  ],
                ),
                const SizedBox(height: 16),
                if (club.isMember)
                  AirmiusButton(label: 'Mitglied', icon: Icons.verified_user_outlined, secondary: true, onPressed: null)
                else if (requested)
                  AirmiusButton(label: scope.t('withdraw'), icon: Icons.undo_outlined, danger: true, onPressed: onWithdraw)
                else
                  AirmiusButton(label: club.acceptsMemberships ? scope.t('join') : 'Teams ansehen', icon: Icons.assignment_outlined, onPressed: onJoin),
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
      backgroundColor: AirmiusColors.card,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(24))),
      builder: (_) => SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(18),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              const Text('Vereinsaktionen', style: TextStyle(color: AirmiusColors.text, fontSize: 18, fontWeight: FontWeight.w900)),
              const SizedBox(height: 12),
              AirmiusButton(label: 'Nachricht senden', icon: Icons.chat_bubble_outline, secondary: true, onPressed: () => Navigator.pop(context)),
              const SizedBox(height: 10),
              AirmiusButton(label: 'Verein melden', icon: Icons.flag_outlined, danger: true, onPressed: () => Navigator.pop(context)),
            ],
          ),
        ),
      ),
    );
  }
}

class _ClubCover extends StatelessWidget {
  const _ClubCover({required this.club, required this.height, this.compact = false});

  final ClubSummary club;
  final double height;
  final bool compact;

  @override
  Widget build(BuildContext context) {
    final imageUrl = club.bannerUrl;
    return ClipRRect(
      borderRadius: BorderRadius.vertical(top: Radius.circular(compact ? 18 : 18)),
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
                    AirmiusColors.blue.withValues(alpha: 0.80),
                    AirmiusColors.cardSoft,
                    AirmiusColors.amber.withValues(alpha: 0.42),
                  ],
                ),
              ),
            ),
            if (imageUrl != null && imageUrl.isNotEmpty)
              Image.network(
                imageUrl,
                fit: BoxFit.cover,
                errorBuilder: (_, __, ___) => const SizedBox.shrink(),
              ),
            Container(color: Colors.black.withValues(alpha: compact ? 0.16 : 0.24)),
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
        Expanded(child: MetricCard(value: '${club.members}', label: scope.t('members'))),
        const SizedBox(width: 10),
        Expanded(child: MetricCard(value: '${club.teams}', label: scope.t('teams'))),
        const SizedBox(width: 10),
        Expanded(child: MetricCard(value: '${club.posts}', label: scope.t('posts'))),
      ],
    );
  }
}

class _ClubWorkspaceTabs extends StatelessWidget {
  const _ClubWorkspaceTabs({required this.active, required this.onSelect});

  final String active;
  final ValueChanged<String> onSelect;

  static const tabs = [
    _ClubTab('struktur', 'Struktur', Icons.account_tree_outlined),
    _ClubTab('beitritt', 'Beitritt', Icons.assignment_outlined),
    _ClubTab('beiträge', 'Beiträge', Icons.forum_outlined),
    _ClubTab('dokumente', 'Dokumente', Icons.folder_open_outlined),
  ];

  @override
  Widget build(BuildContext context) {
    return SingleChildScrollView(
      scrollDirection: Axis.horizontal,
      child: Row(
        children: [
          for (final tab in tabs) ...[
            _ClubTabChip(tab: tab, selected: active == tab.id, onTap: () => onSelect(tab.id)),
            const SizedBox(width: 8),
          ],
        ],
      ),
    );
  }
}

class _ClubTabBody extends StatelessWidget {
  const _ClubTabBody({required this.tab, required this.club, required this.requested, required this.onJoin, required this.onWithdraw});

  final String tab;
  final ClubSummary club;
  final bool requested;
  final VoidCallback? onJoin;
  final VoidCallback? onWithdraw;

  @override
  Widget build(BuildContext context) {
    return switch (tab) {
      'beitritt' => _MembershipPanel(club: club, requested: requested, onJoin: onJoin, onWithdraw: onWithdraw),
      'beiträge' => _ClubPostsPanel(club: club),
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
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        AirmiusPanel(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Eyebrow('Vereinsdaten'),
              const SizedBox(height: 12),
              _DetailLine(icon: Icons.location_on_outlined, label: 'Standort', value: club.city.isEmpty ? 'Noch nicht hinterlegt' : club.city),
              _DetailLine(icon: Icons.badge_outlined, label: 'Status', value: club.verified ? 'Verifiziert' : 'Profil in Prüfung'),
              _DetailLine(icon: Icons.groups_outlined, label: 'Mitglieder', value: '${club.members} aktive Kontakte'),
            ],
          ),
        ),
        const SizedBox(height: 14),
        _InfoPanel(
          title: 'Admins',
          rows: [
            _InfoRowData('VA', club.name, club.verified ? 'Verein-Admin' : 'Profilverantwortlich'),
          ],
        ),
        const SizedBox(height: 14),
        _TeamsPanel(club: club),
      ],
    );
  }
}

class _MembershipPanel extends StatelessWidget {
  const _MembershipPanel({required this.club, required this.requested, required this.onJoin, required this.onWithdraw});

  final ClubSummary club;
  final bool requested;
  final VoidCallback? onJoin;
  final VoidCallback? onWithdraw;

  @override
  Widget build(BuildContext context) {
    final scope = AirmiusScope.of(context);
    return AirmiusPanel(
      borderColor: requested ? AirmiusColors.green.withValues(alpha: 0.60) : null,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const Eyebrow('Mitgliedschaft'),
          const SizedBox(height: 8),
          Text(
            club.isMember ? 'Du bist Mitglied in diesem Verein.' : requested ? 'Deine Mitgliedschaftsanfrage ist beim Verein angekommen.' : 'Starte eine Anfrage mit Nachricht, Dokumenten und Zahlungswunsch wie im Web.',
            style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w800, height: 1.35),
          ),
          const SizedBox(height: 12),
          const _MembershipOption(title: 'Standard-Mitgliedschaft', meta: 'Jaehrlich - Dokumente erforderlich'),
          const _MembershipOption(title: 'Foerdermitgliedschaft', meta: 'Optional - Verein prüft manuell'),
          const SizedBox(height: 14),
          if (club.isMember)
            AirmiusButton(label: 'Mitglied', icon: Icons.verified_user_outlined, secondary: true, onPressed: null)
          else if (requested)
            AirmiusButton(label: scope.t('withdraw'), icon: Icons.undo_outlined, danger: true, onPressed: onWithdraw)
          else
            AirmiusButton(label: club.acceptsMemberships ? scope.t('join') : 'Anfragen geschlossen', icon: Icons.assignment_outlined, onPressed: onJoin),
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
    return AirmiusPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const Eyebrow('Vereinsbeiträge'),
          const SizedBox(height: 8),
          Text('${club.posts} sichtbare Beiträge für Mitglieder und Community.', style: const TextStyle(color: AirmiusColors.muted, height: 1.35)),
          const SizedBox(height: 14),
          _FeedPreviewLine(title: 'Willkommen im Vereinsfeed', meta: 'Ankündigungen, Bilder und Videos erscheinen hier.'),
          _FeedPreviewLine(title: 'Training & Termine', meta: 'Team-Updates können im Feed verknuepft werden.'),
        ],
      ),
    );
  }
}

class _TeamsPanel extends StatelessWidget {
  const _TeamsPanel({required this.club});

  final ClubSummary club;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Eyebrow('Teams'),
          const SizedBox(height: 8),
          if (club.teamList.isEmpty && club.teams == 0)
            const Text('Noch keine Teams sichtbar.', style: TextStyle(color: AirmiusColors.muted))
          else if (club.teamList.isNotEmpty)
            for (final team in club.teamList)
              _TeamLine(title: team.name, meta: team.meta)
          else
            for (var index = 1; index <= club.teams.clamp(1, 3); index++)
              _TeamLine(title: 'Team $index', meta: index == 1 ? 'Hauptteam' : 'Training & Spielbetrieb'),
        ],
      ),
    );
  }
}

class _DocumentsPanel extends StatelessWidget {
  const _DocumentsPanel();

  @override
  Widget build(BuildContext context) {
    return const AirmiusPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Eyebrow('Vereinsdokumente'),
          SizedBox(height: 8),
          Text('Datenschutz, Beitragsordnung und Vereinsregeln werden wie im Web-Cockpit für die mobile Anmeldung sichtbar gemacht.', style: TextStyle(color: AirmiusColors.muted, height: 1.35)),
          SizedBox(height: 12),
          _DocumentLine(title: 'Datenschutz', requiredDoc: true),
          _DocumentLine(title: 'Beitragsordnung', requiredDoc: true),
          _DocumentLine(title: 'Vereinsregeln', requiredDoc: false),
        ],
      ),
    );
  }
}

class _DocumentLine extends StatelessWidget {
  const _DocumentLine({required this.title, required this.requiredDoc});

  final String title;
  final bool requiredDoc;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(top: 10),
      child: Row(
        children: [
          const Icon(Icons.description_outlined, color: AirmiusColors.blue),
          const SizedBox(width: 12),
          Expanded(child: Text(title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w800))),
          StatusPill(requiredDoc ? 'Pflicht' : 'Optional'),
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
    return AirmiusPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Eyebrow(title),
          if (rows.isEmpty) const Padding(padding: EdgeInsets.only(top: 14), child: Text('Noch keine Eintraege.', style: TextStyle(color: AirmiusColors.muted))),
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
                      Text(row.title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
                      Text(row.meta, style: const TextStyle(color: AirmiusColors.muted, fontSize: 12)),
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
          const Icon(Icons.fact_check_outlined, color: AirmiusColors.green),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
                Text(meta, style: const TextStyle(color: AirmiusColors.muted, fontSize: 12)),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _FeedPreviewLine extends StatelessWidget {
  const _FeedPreviewLine({required this.title, required this.meta});

  final String title;
  final String meta;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(top: 10),
      child: Row(
        children: [
          const Icon(Icons.dynamic_feed_outlined, color: AirmiusColors.amber),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
                Text(meta, style: const TextStyle(color: AirmiusColors.muted, fontSize: 12)),
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
                Text(title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
                Text(meta, style: const TextStyle(color: AirmiusColors.muted, fontSize: 12)),
              ],
            ),
          ),
          const Icon(Icons.chevron_right, color: AirmiusColors.muted),
        ],
      ),
    );
  }
}

class _DetailLine extends StatelessWidget {
  const _DetailLine({required this.icon, required this.label, required this.value});

  final IconData icon;
  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(top: 10),
      child: Row(
        children: [
          Icon(icon, color: AirmiusColors.blue, size: 20),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(label, style: const TextStyle(color: AirmiusColors.muted, fontSize: 12, fontWeight: FontWeight.w800)),
                Text(value, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _RoundAction extends StatelessWidget {
  const _RoundAction({required this.icon, required this.onTap});

  final IconData icon;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(16),
      child: Container(
        width: 42,
        height: 42,
        decoration: BoxDecoration(
          color: AirmiusColors.card.withValues(alpha: 0.88),
          borderRadius: BorderRadius.circular(16),
          border: Border.all(color: AirmiusColors.border),
        ),
        child: Icon(icon, color: AirmiusColors.text, size: 20),
      ),
    );
  }
}

class _ClubTab {
  const _ClubTab(this.id, this.label, this.icon);

  final String id;
  final String label;
  final IconData icon;
}

class _ClubTabChip extends StatelessWidget {
  const _ClubTabChip({required this.tab, required this.selected, required this.onTap});

  final _ClubTab tab;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return ChoiceChip(
      selected: selected,
      label: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(tab.icon, size: 16, color: selected ? AirmiusColors.text : AirmiusColors.muted),
          const SizedBox(width: 6),
          Text(tab.label),
        ],
      ),
      onSelected: (_) => onTap(),
      selectedColor: AirmiusColors.blue.withValues(alpha: 0.24),
      backgroundColor: AirmiusColors.cardSoft,
      side: BorderSide(color: selected ? AirmiusColors.blue : AirmiusColors.border),
      labelStyle: TextStyle(color: selected ? AirmiusColors.text : AirmiusColors.muted, fontWeight: FontWeight.w900),
    );
  }
}
