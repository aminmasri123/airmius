import 'package:flutter/material.dart';

import '../core/airmius_api_client.dart';
import '../core/airmius_api_models.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';
import '../core/airmius_theme.dart';
import '../models/club_summary.dart';
import '../widgets/airmius_widgets.dart';
import 'club_membership_admin_screen.dart';
import 'clubs_screen.dart';

/// Permission-scoped club profile editor.
///
/// This replaces the former illustrative profile wizard with the same API
/// contract used by the clubs workspace. Only clubs the current user can
/// manage are offered, and every save is validated by the server.
class ClubProfileEditorScreen extends StatefulWidget {
  const ClubProfileEditorScreen({super.key, this.initialTab = 'Profil'});

  final String initialTab;

  @override
  State<ClubProfileEditorScreen> createState() =>
      _ClubProfileEditorScreenState();
}

class _ClubProfileEditorScreenState extends State<ClubProfileEditorScreen> {
  final _name = TextEditingController();
  final _sport = TextEditingController();
  final _city = TextEditingController();
  final _postalCode = TextEditingController();
  final _state = TextEditingController();
  final _street = TextEditingController();
  final _houseNumber = TextEditingController();

  Future<AirmiusClub>? _future;
  int? _hydratedId;
  String _country = 'DE';
  bool _listed = true;
  bool _teamsListed = true;
  bool _membersCanPost = true;
  bool _teamsCanPost = true;
  bool _saving = false;

  String t(String key) => AirmiusScope.of(context).t(key);

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _future ??= _loadClub();
  }

  Future<AirmiusClub> _loadClub() async {
    final repository = AirmiusServicesScope.of(context).repositories.clubs;
    final page = await repository.searchClubs(mine: true);
    final manageable = page.items.where((club) => club.canManage).toList();
    if (manageable.isEmpty) throw StateError(t('clubEditor.noManagedClub'));
    final club = await repository.club(manageable.first.id);
    _hydrate(club);
    return club;
  }

  void _hydrate(AirmiusClub club) {
    if (_hydratedId == club.id) return;
    _hydratedId = club.id;
    _name.text = club.name;
    _sport.text = club.sportType ?? '';
    _city.text = club.city;
    _postalCode.text = club.postalCode ?? '';
    _state.text = club.state ?? '';
    _street.text = club.street ?? '';
    _houseNumber.text = club.houseNumber ?? '';
    _country = (club.country ?? 'DE').toUpperCase();
    if (!_countryOptions.contains(_country)) _country = 'DE';
    _listed = true;
    _teamsListed = true;
    _membersCanPost = true;
    _teamsCanPost = true;
  }

  Future<void> _save(AirmiusClub club) async {
    final name = _name.text.trim();
    if (name.isEmpty || _country.length != 2) {
      _showMessage(t('clubEditor.required'));
      return;
    }
    if (_saving) return;
    final repository = AirmiusServicesScope.of(context).repositories.clubs;
    setState(() => _saving = true);
    try {
      await repository.updateClub(club.id, {
        'name': name,
        'sport_type': _sport.text.trim().isEmpty ? null : _sport.text.trim(),
        'country': _country,
        'city': _city.text.trim().isEmpty ? null : _city.text.trim(),
        'postal_code': _postalCode.text.trim().isEmpty
            ? null
            : _postalCode.text.trim(),
        'state': _state.text.trim().isEmpty ? null : _state.text.trim(),
        'street': _street.text.trim().isEmpty ? null : _street.text.trim(),
        'house_number': _houseNumber.text.trim().isEmpty
            ? null
            : _houseNumber.text.trim(),
        'is_listed': _listed,
        'teams_are_listed': _teamsListed,
        'members_can_post_to_club': _membersCanPost,
        'members_can_post_to_teams': _teamsCanPost,
      });
      if (!mounted) return;
      _showMessage(t('clubEditor.saved'));
      setState(() {
        _saving = false;
        _hydratedId = null;
        _future = repository.club(club.id);
      });
    } on AirmiusApiException catch (error) {
      if (!mounted) return;
      setState(() => _saving = false);
      _showMessage(error.userMessage);
    } catch (error) {
      if (!mounted) return;
      setState(() => _saving = false);
      _showMessage(
        '${t('clubEditor.saveFailed')}: ${error is AirmiusApiException ? error.userMessage : t('common.errorDetails')}',
      );
    }
  }

  void _showMessage(String message) {
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(SnackBar(content: Text(message)));
  }

  @override
  void dispose() {
    _name.dispose();
    _sport.dispose();
    _city.dispose();
    _postalCode.dispose();
    _state.dispose();
    _street.dispose();
    _houseNumber.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final future = _future;
    return Scaffold(
      appBar: AppBar(
        title: Text(
          t('clubEditor.title'),
          style: TextStyle(fontWeight: FontWeight.w900),
        ),
        actions: [
          IconButton(
            tooltip: t('clubs.reload'),
            onPressed: _saving
                ? null
                : () => setState(() {
                    _hydratedId = null;
                    _future = _loadClub();
                  }),
            icon: Icon(Icons.refresh_outlined),
          ),
        ],
      ),
      body: PageFrame(
        title: t('clubEditor.title'),
        subtitle: t('clubEditor.subtitle'),
        child: FutureBuilder<AirmiusClub>(
          future: future,
          builder: (context, snapshot) {
            if (snapshot.connectionState == ConnectionState.waiting) {
              return const Center(
                child: Padding(
                  padding: EdgeInsets.all(24),
                  child: CircularProgressIndicator(),
                ),
              );
            }
            if (snapshot.hasError || snapshot.data == null) {
              return AirmiusPanel(
                borderColor: AirmiusColors.red.withValues(alpha: .5),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Text(
                      t('clubEditor.loadFailed'),
                      style: TextStyle(fontWeight: FontWeight.w900),
                    ),
                    const SizedBox(height: 8),
                    Text(
                      snapshot.error is AirmiusApiException
                          ? (snapshot.error! as AirmiusApiException).userMessage
                          : t('clubEditor.noManagedClub'),
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
                        _hydratedId = null;
                        _future = _loadClub();
                      }),
                    ),
                  ],
                ),
              );
            }
            final club = snapshot.data!;
            return Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                AirmiusPanel(
                  gradient: true,
                  child: Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      AirmiusAvatar(club.name, large: true),
                      const SizedBox(width: 14),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Eyebrow(t('clubEditor.managedClub')),
                            const SizedBox(height: 6),
                            Text(
                              club.name,
                              style: TextStyle(
                                fontSize: 23,
                                fontWeight: FontWeight.w900,
                              ),
                            ),
                            const SizedBox(height: 4),
                            Text(
                              '${club.city.isEmpty ? t('clubs.city') : club.city} · ${club.sportType?.isNotEmpty == true ? club.sportType : t('clubs.sportOpen')}',
                              style: TextStyle(
                                color: airmiusMutedColor(context),
                                height: 1.35,
                              ),
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 14),
                AirmiusPanel(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      Eyebrow(t('clubEditor.basicData')),
                      const SizedBox(height: 12),
                      AirmiusTextField(
                        controller: _name,
                        label: t('clubs.club'),
                        icon: Icons.apartment_outlined,
                        textInputAction: TextInputAction.next,
                      ),
                      const SizedBox(height: 10),
                      AirmiusTextField(
                        controller: _sport,
                        label: t('clubs.sport'),
                        icon: Icons.sports_outlined,
                        textInputAction: TextInputAction.next,
                      ),
                      const SizedBox(height: 10),
                      DropdownButtonFormField<String>(
                        initialValue: _country,
                        decoration: InputDecoration(
                          labelText: t('clubEditor.country'),
                          prefixIcon: Icon(Icons.public_outlined),
                        ),
                        items: [
                          for (final code in _countryOptions)
                            DropdownMenuItem(
                              value: code,
                              child: Text(t('clubs.country.$code')),
                            ),
                        ],
                        onChanged: (value) =>
                            setState(() => _country = value ?? _country),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 14),
                AirmiusPanel(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      Eyebrow(t('clubEditor.address')),
                      const SizedBox(height: 12),
                      Row(
                        children: [
                          Expanded(
                            child: AirmiusTextField(
                              controller: _street,
                              label: t('clubEditor.street'),
                              icon: Icons.route_outlined,
                            ),
                          ),
                          const SizedBox(width: 10),
                          SizedBox(
                            width: 100,
                            child: AirmiusTextField(
                              controller: _houseNumber,
                              label: t('clubEditor.houseNumber'),
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 10),
                      Row(
                        children: [
                          SizedBox(
                            width: 120,
                            child: AirmiusTextField(
                              controller: _postalCode,
                              label: t('clubs.postalCode'),
                              keyboardType: TextInputType.number,
                            ),
                          ),
                          const SizedBox(width: 10),
                          Expanded(
                            child: AirmiusTextField(
                              controller: _city,
                              label: t('clubs.city'),
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 10),
                      AirmiusTextField(
                        controller: _state,
                        label: t('clubEditor.region'),
                        icon: Icons.map_outlined,
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 14),
                AirmiusPanel(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      Eyebrow(t('clubEditor.visibility')),
                      const SizedBox(height: 8),
                      _SwitchLine(
                        title: t('clubEditor.listed'),
                        body: t('clubEditor.listedHint'),
                        value: _listed,
                        onChanged: (value) => setState(() => _listed = value),
                      ),
                      _SwitchLine(
                        title: t('clubEditor.teamsListed'),
                        body: t('clubEditor.teamsListedHint'),
                        value: _teamsListed,
                        onChanged: (value) =>
                            setState(() => _teamsListed = value),
                      ),
                      _SwitchLine(
                        title: t('clubEditor.clubPosting'),
                        body: t('clubEditor.clubPostingHint'),
                        value: _membersCanPost,
                        onChanged: (value) =>
                            setState(() => _membersCanPost = value),
                      ),
                      _SwitchLine(
                        title: t('clubEditor.teamPosting'),
                        body: t('clubEditor.teamPostingHint'),
                        value: _teamsCanPost,
                        onChanged: (value) =>
                            setState(() => _teamsCanPost = value),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 16),
                AirmiusButton(
                  label: _saving ? t('clubs.saving') : t('clubs.save'),
                  icon: Icons.save_outlined,
                  onPressed: _saving ? null : () => _save(club),
                ),
                const SizedBox(height: 10),
                AirmiusButton(
                  label: t('clubEditor.membershipSettings'),
                  icon: Icons.assignment_ind_outlined,
                  secondary: true,
                  onPressed: () => Navigator.push(
                    context,
                    MaterialPageRoute(
                      builder: (_) => const ClubMembershipAdminScreen(),
                    ),
                  ),
                ),
                const SizedBox(height: 8),
                AirmiusButton(
                  label: t('clubEditor.openPublicProfile'),
                  icon: Icons.visibility_outlined,
                  secondary: true,
                  onPressed: () => Navigator.push(
                    context,
                    MaterialPageRoute(
                      builder: (_) => ClubProfileScreen(
                        club: ClubSummary.fromAirmiusClub(club),
                        requested: false,
                        onRequest: (_) {},
                        onWithdraw: (_) {},
                      ),
                    ),
                  ),
                ),
              ],
            );
          },
        ),
      ),
    );
  }
}

class _SwitchLine extends StatelessWidget {
  const _SwitchLine({
    required this.title,
    required this.body,
    required this.value,
    required this.onChanged,
  });

  final String title;
  final String body;
  final bool value;
  final ValueChanged<bool> onChanged;

  @override
  Widget build(BuildContext context) => SwitchListTile(
    value: value,
    onChanged: onChanged,
    activeThumbColor: Theme.of(context).colorScheme.primary,
    contentPadding: EdgeInsets.zero,
    title: Text(title, style: TextStyle(fontWeight: FontWeight.w900)),
    subtitle: Text(
      body,
      style: TextStyle(color: airmiusMutedColor(context), height: 1.3),
    ),
  );
}

const _countryOptions = [
  'DE',
  'AT',
  'CH',
  'FR',
  'NL',
  'BE',
  'MA',
  'ES',
  'PT',
  'IT',
  'GB',
  'TR',
  'US',
];
