import 'package:flutter/material.dart';
import '../widgets/country_field.dart';

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
  const ClubProfileEditorScreen({
    super.key,
    this.initialTab = 'Profil',
    this.initialClubId,
  });

  final String initialTab;
  final int? initialClubId;

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
  final _registryAuthority = TextEditingController();
  final _registryNumber = TextEditingController();
  final _taxAuthority = TextEditingController();
  final _taxNumber = TextEditingController();
  final _vatId = TextEditingController();
  final _taxExemptionValidUntil = TextEditingController();
  final List<_AffiliationControllers> _affiliations = [];
  final _contactEmail = TextEditingController();
  final _contactPhone = TextEditingController();
  final _websiteUrl = TextEditingController();
  final List<_ContactPersonControllers> _contactPersons = [];
  final _brandPrimaryColor = TextEditingController();
  final _brandSecondaryColor = TextEditingController();
  final _brandAccentColor = TextEditingController();
  final _letterheadHeader = TextEditingController();
  final _letterheadAddress = TextEditingController();
  final _letterheadFooter = TextEditingController();
  final List<_DocumentTemplateControllers> _documentTemplates = [];

  Future<AirmiusClub>? _future;
  int? _hydratedId;
  String _country = 'DE';
  bool _listed = true;
  bool _teamsListed = true;
  bool _membersCanPost = true;
  bool _teamsCanPost = true;
  bool _saving = false;
  String _taxStatus = 'unknown';
  bool _contactDetailsPublic = false;
  bool _letterheadShowLogo = true;

  String t(String key) => AirmiusScope.of(context).t(key);

  String _sportLabel(String? value) {
    final normalized = (value ?? '').trim().toLowerCase();
    if (normalized == 'strassenlauf') return t('clubHub.sport.strassenlauf');
    return (value ?? '').trim().isEmpty ? t('clubs.sportOpen') : value!.trim();
  }

  String? _sportApiValue() {
    final value = _sport.text.trim();
    if (value.isEmpty) return null;
    if (value.toLowerCase() == t('clubHub.sport.strassenlauf').toLowerCase()) {
      return 'strassenlauf';
    }
    return value;
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _future ??= _loadClub();
  }

  Future<AirmiusClub> _loadClub() async {
    final repository = AirmiusServicesScope.of(context).repositories.clubs;
    final page = await repository.searchClubs(mine: true);
    final manageable = page.items
        .where(
          (club) =>
              club.canEditClubProfile ||
              club.canEditClubLegal ||
              club.canEditClubContact ||
              club.canEditClubBranding,
        )
        .toList();
    if (manageable.isEmpty) throw StateError(t('clubEditor.noManagedClub'));
    final selected =
        manageable
            .where((club) => club.id == widget.initialClubId)
            .firstOrNull ??
        manageable.first;
    final club = await repository.club(selected.id);
    _hydrate(club);
    return club;
  }

  void _hydrate(AirmiusClub club) {
    if (_hydratedId == club.id) return;
    _hydratedId = club.id;
    _name.text = club.name;
    _sport.text = _sportLabel(club.sportType);
    _city.text = club.city;
    _postalCode.text = club.postalCode ?? '';
    _state.text = club.state ?? '';
    _street.text = club.street ?? '';
    _houseNumber.text = club.houseNumber ?? '';
    _registryAuthority.text = club.registryAuthority ?? '';
    _registryNumber.text = club.registryNumber ?? '';
    _taxAuthority.text = club.taxAuthority ?? '';
    _taxNumber.text = club.taxNumber ?? '';
    _vatId.text = club.vatId ?? '';
    _taxExemptionValidUntil.text = club.taxExemptionValidUntil ?? '';
    _taxStatus = club.taxStatus ?? 'unknown';
    _contactEmail.text = club.contactEmail ?? '';
    _contactPhone.text = club.contactPhone ?? '';
    _websiteUrl.text = club.websiteUrl ?? '';
    _contactDetailsPublic = club.contactDetailsPublic;
    _brandPrimaryColor.text = club.brandPrimaryColor ?? '';
    _brandSecondaryColor.text = club.brandSecondaryColor ?? '';
    _brandAccentColor.text = club.brandAccentColor ?? '';
    _letterheadShowLogo = club.letterheadSettings['show_logo'] != false;
    _letterheadHeader.text =
        club.letterheadSettings['header']?.toString() ?? '';
    _letterheadAddress.text =
        club.letterheadSettings['address_line']?.toString() ?? '';
    _letterheadFooter.text =
        club.letterheadSettings['footer']?.toString() ?? '';
    for (final template in _documentTemplates) {
      template.dispose();
    }
    _documentTemplates
      ..clear()
      ..addAll(
        club.documentTemplates.map(_DocumentTemplateControllers.fromJson),
      );
    for (final person in _contactPersons) {
      person.dispose();
    }
    _contactPersons
      ..clear()
      ..addAll(club.contactPersons.map(_ContactPersonControllers.fromJson));
    for (final affiliation in _affiliations) {
      affiliation.dispose();
    }
    _affiliations
      ..clear()
      ..addAll(
        club.federationAffiliations.map(_AffiliationControllers.fromJson),
      );
    _country = (club.country ?? 'DE').toUpperCase();
    _listed = club.isListed;
    _teamsListed = club.teamsAreListed;
    _membersCanPost = club.membersCanPostToClub;
    _teamsCanPost = club.membersCanPostToTeams;
  }

  Future<void> _save(AirmiusClub club) async {
    final name = _name.text.trim();
    if (club.canEditClubProfile && (name.isEmpty || _country.length != 2)) {
      _showMessage(t('clubEditor.required'));
      return;
    }
    if (_saving) return;
    final repository = AirmiusServicesScope.of(context).repositories.clubs;
    setState(() => _saving = true);
    try {
      final payload = <String, dynamic>{};
      if (club.canEditClubProfile) {
        payload.addAll({
          'name': name,
          'sport_type': _sportApiValue(),
          'country': _country,
          'city': _nullIfBlank(_city.text),
          'postal_code': _nullIfBlank(_postalCode.text),
          'state': _nullIfBlank(_state.text),
          'street': _nullIfBlank(_street.text),
          'house_number': _nullIfBlank(_houseNumber.text),
          'is_listed': _listed,
          'teams_are_listed': _teamsListed,
          'members_can_post_to_club': _membersCanPost,
          'members_can_post_to_teams': _teamsCanPost,
        });
      }
      if (club.canEditClubLegal) {
        payload.addAll({
          'registry_authority': _nullIfBlank(_registryAuthority.text),
          'registry_number': _nullIfBlank(_registryNumber.text),
          'federation_affiliations': _affiliations
              .where((entry) => entry.name.text.trim().isNotEmpty)
              .map((entry) => entry.toJson())
              .toList(),
          'tax_authority': _nullIfBlank(_taxAuthority.text),
          'tax_number': _nullIfBlank(_taxNumber.text),
          'vat_id': _nullIfBlank(_vatId.text),
          'tax_status': _taxStatus,
          'tax_exemption_valid_until': _nullIfBlank(
            _taxExemptionValidUntil.text,
          ),
        });
      }
      if (club.canEditClubContact) {
        payload.addAll({
          'contact_email': _nullIfBlank(_contactEmail.text),
          'contact_phone': _nullIfBlank(_contactPhone.text),
          'website_url': _nullIfBlank(_websiteUrl.text),
          'contact_details_public': _contactDetailsPublic,
          'contact_persons': _contactPersons
              .where((person) => person.name.text.trim().isNotEmpty)
              .map((person) => person.toJson())
              .toList(),
        });
      }
      if (club.canEditClubBranding) {
        payload.addAll({
          'brand_primary_color': _nullIfBlank(_brandPrimaryColor.text),
          'brand_secondary_color': _nullIfBlank(_brandSecondaryColor.text),
          'brand_accent_color': _nullIfBlank(_brandAccentColor.text),
          'letterhead_settings': {
            'show_logo': _letterheadShowLogo,
            'header': _nullIfBlank(_letterheadHeader.text),
            'address_line': _nullIfBlank(_letterheadAddress.text),
            'footer': _nullIfBlank(_letterheadFooter.text),
          },
          'document_templates': _documentTemplates
              .where((template) => template.name.text.trim().isNotEmpty)
              .map((template) => template.toJson())
              .toList(),
        });
      }
      await repository.updateClub(club.id, payload);
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

  String? _nullIfBlank(String value) =>
      value.trim().isEmpty ? null : value.trim();

  void _setDefaultTemplate(int index, bool value) {
    final selected = _documentTemplates[index];
    if (value) {
      for (var current = 0; current < _documentTemplates.length; current++) {
        if (current != index &&
            _documentTemplates[current].type == selected.type) {
          _documentTemplates[current].isDefault = false;
        }
      }
    }
    selected.isDefault = value;
    setState(() {});
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
    _registryAuthority.dispose();
    _registryNumber.dispose();
    _taxAuthority.dispose();
    _taxNumber.dispose();
    _vatId.dispose();
    _taxExemptionValidUntil.dispose();
    _contactEmail.dispose();
    _contactPhone.dispose();
    _websiteUrl.dispose();
    for (final person in _contactPersons) {
      person.dispose();
    }
    _brandPrimaryColor.dispose();
    _brandSecondaryColor.dispose();
    _brandAccentColor.dispose();
    _letterheadHeader.dispose();
    _letterheadAddress.dispose();
    _letterheadFooter.dispose();
    for (final template in _documentTemplates) {
      template.dispose();
    }
    for (final affiliation in _affiliations) {
      affiliation.dispose();
    }
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
                              '${club.city.isEmpty ? t('clubs.city') : club.city} · ${_sportLabel(club.sportType)}',
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
                if (club.canEditClubBranding)
                  AirmiusPanel(
                    child: ExpansionTile(
                      tilePadding: EdgeInsets.zero,
                      childrenPadding: EdgeInsets.zero,
                      title: Eyebrow(t('clubEditor.branding')),
                      subtitle: Text(t('clubEditor.brandingHint')),
                      children: [
                        const SizedBox(height: 12),
                        AirmiusTextField(
                          controller: _brandPrimaryColor,
                          label: t('clubEditor.brandPrimary'),
                          hint: '#1D4ED8',
                          autocorrect: false,
                        ),
                        const SizedBox(height: 10),
                        AirmiusTextField(
                          controller: _brandSecondaryColor,
                          label: t('clubEditor.brandSecondary'),
                          hint: '#0F172A',
                          autocorrect: false,
                        ),
                        const SizedBox(height: 10),
                        AirmiusTextField(
                          controller: _brandAccentColor,
                          label: t('clubEditor.brandAccent'),
                          hint: '#F59E0B',
                          autocorrect: false,
                        ),
                        const SizedBox(height: 16),
                        Eyebrow(t('clubEditor.letterhead')),
                        SwitchListTile(
                          contentPadding: EdgeInsets.zero,
                          value: _letterheadShowLogo,
                          title: Text(t('clubEditor.letterheadShowLogo')),
                          onChanged: (value) =>
                              setState(() => _letterheadShowLogo = value),
                        ),
                        AirmiusTextField(
                          controller: _letterheadHeader,
                          label: t('clubEditor.letterheadHeader'),
                        ),
                        const SizedBox(height: 8),
                        AirmiusTextField(
                          controller: _letterheadAddress,
                          label: t('clubEditor.letterheadAddress'),
                        ),
                        const SizedBox(height: 8),
                        AirmiusTextField(
                          controller: _letterheadFooter,
                          label: t('clubEditor.letterheadFooter'),
                          maxLines: 2,
                        ),
                        const SizedBox(height: 16),
                        Row(
                          children: [
                            Expanded(
                              child: Eyebrow(t('clubEditor.documentTemplates')),
                            ),
                            IconButton(
                              tooltip: t('clubEditor.addDocumentTemplate'),
                              onPressed: _documentTemplates.length >= 20
                                  ? null
                                  : () => setState(
                                      () => _documentTemplates.add(
                                        _DocumentTemplateControllers(),
                                      ),
                                    ),
                              icon: const Icon(Icons.note_add_outlined),
                            ),
                          ],
                        ),
                        for (
                          var index = 0;
                          index < _documentTemplates.length;
                          index++
                        ) ...[
                          const SizedBox(height: 10),
                          _DocumentTemplateEditor(
                            controllers: _documentTemplates[index],
                            nameLabel: t('clubEditor.documentTemplateName'),
                            typeLabel: t('clubEditor.documentTemplateType'),
                            headerLabel: t('clubEditor.letterheadHeader'),
                            footerLabel: t('clubEditor.letterheadFooter'),
                            defaultLabel: t(
                              'clubEditor.documentTemplateDefault',
                            ),
                            removeLabel: t('clubEditor.removeDocumentTemplate'),
                            typeLabelFor: (type) =>
                                t('clubEditor.documentType.$type'),
                            onTypeChanged: (value) => setState(() {
                              _documentTemplates[index].type = value;
                              _documentTemplates[index].isDefault = false;
                            }),
                            onDefaultChanged: (value) =>
                                _setDefaultTemplate(index, value),
                            onRemove: () => setState(() {
                              final removed = _documentTemplates.removeAt(
                                index,
                              );
                              removed.dispose();
                            }),
                          ),
                        ],
                      ],
                    ),
                  ),
                const SizedBox(height: 14),
                if (club.canEditClubContact)
                  AirmiusPanel(
                    child: ExpansionTile(
                      tilePadding: EdgeInsets.zero,
                      childrenPadding: EdgeInsets.zero,
                      title: Eyebrow(t('clubEditor.contactData')),
                      subtitle: Text(t('clubEditor.contactDataHint')),
                      children: [
                        const SizedBox(height: 12),
                        AirmiusTextField(
                          controller: _contactEmail,
                          label: t('clubEditor.contactEmail'),
                          keyboardType: TextInputType.emailAddress,
                          autocorrect: false,
                        ),
                        const SizedBox(height: 10),
                        AirmiusTextField(
                          controller: _contactPhone,
                          label: t('clubEditor.contactPhone'),
                          keyboardType: TextInputType.phone,
                        ),
                        const SizedBox(height: 10),
                        AirmiusTextField(
                          controller: _websiteUrl,
                          label: t('clubEditor.website'),
                          hint: 'https://',
                          keyboardType: TextInputType.url,
                          autocorrect: false,
                        ),
                        SwitchListTile(
                          contentPadding: EdgeInsets.zero,
                          value: _contactDetailsPublic,
                          title: Text(t('clubEditor.contactPublic')),
                          subtitle: Text(t('clubEditor.contactPublicHint')),
                          onChanged: (value) =>
                              setState(() => _contactDetailsPublic = value),
                        ),
                        Row(
                          children: [
                            Expanded(
                              child: Eyebrow(t('clubEditor.contactPersons')),
                            ),
                            IconButton(
                              tooltip: t('clubEditor.addContactPerson'),
                              onPressed: _contactPersons.length >= 20
                                  ? null
                                  : () => setState(
                                      () => _contactPersons.add(
                                        _ContactPersonControllers(),
                                      ),
                                    ),
                              icon: const Icon(Icons.person_add_alt_1_outlined),
                            ),
                          ],
                        ),
                        for (
                          var index = 0;
                          index < _contactPersons.length;
                          index++
                        ) ...[
                          const SizedBox(height: 10),
                          _ContactPersonEditor(
                            controllers: _contactPersons[index],
                            nameLabel: t('clubEditor.contactPersonName'),
                            roleLabel: t('clubEditor.contactPersonRole'),
                            emailLabel: t('clubEditor.contactEmail'),
                            phoneLabel: t('clubEditor.contactPhone'),
                            publicLabel: t('clubEditor.contactPersonPublic'),
                            removeLabel: t('clubEditor.removeContactPerson'),
                            onChanged: () => setState(() {}),
                            onRemove: () => setState(() {
                              final removed = _contactPersons.removeAt(index);
                              removed.dispose();
                            }),
                          ),
                        ],
                      ],
                    ),
                  ),
                const SizedBox(height: 14),
                if (club.canEditClubLegal)
                  AirmiusPanel(
                    child: ExpansionTile(
                      tilePadding: EdgeInsets.zero,
                      childrenPadding: EdgeInsets.zero,
                      title: Eyebrow(t('clubEditor.legalData')),
                      subtitle: Text(t('clubEditor.legalDataHint')),
                      children: [
                        const SizedBox(height: 12),
                        AirmiusTextField(
                          controller: _registryAuthority,
                          label: t('clubEditor.registryAuthority'),
                          icon: Icons.account_balance_outlined,
                        ),
                        const SizedBox(height: 10),
                        AirmiusTextField(
                          controller: _registryNumber,
                          label: t('clubEditor.registryNumber'),
                        ),
                        const SizedBox(height: 10),
                        AirmiusTextField(
                          controller: _taxAuthority,
                          label: t('clubEditor.taxAuthority'),
                        ),
                        const SizedBox(height: 10),
                        AirmiusTextField(
                          controller: _taxNumber,
                          label: t('clubEditor.taxNumber'),
                        ),
                        const SizedBox(height: 10),
                        AirmiusTextField(
                          controller: _vatId,
                          label: t('clubEditor.vatId'),
                          autocorrect: false,
                        ),
                        const SizedBox(height: 10),
                        DropdownButtonFormField<String>(
                          initialValue: _taxStatus,
                          decoration: InputDecoration(
                            labelText: t('clubEditor.taxStatus'),
                          ),
                          items: ['unknown', 'nonprofit', 'taxable', 'mixed']
                              .map(
                                (value) => DropdownMenuItem(
                                  value: value,
                                  child: Text(t('clubEditor.taxStatus.$value')),
                                ),
                              )
                              .toList(),
                          onChanged: (value) =>
                              setState(() => _taxStatus = value ?? 'unknown'),
                        ),
                        const SizedBox(height: 10),
                        AirmiusTextField(
                          controller: _taxExemptionValidUntil,
                          label: t('clubEditor.taxExemptionUntil'),
                          hint: 'YYYY-MM-DD',
                          keyboardType: TextInputType.datetime,
                        ),
                        const SizedBox(height: 16),
                        Row(
                          children: [
                            Expanded(
                              child: Eyebrow(t('clubEditor.affiliations')),
                            ),
                            IconButton(
                              tooltip: t('clubEditor.addAffiliation'),
                              onPressed: _affiliations.length >= 20
                                  ? null
                                  : () => setState(
                                      () => _affiliations.add(
                                        _AffiliationControllers(),
                                      ),
                                    ),
                              icon: const Icon(Icons.add_circle_outline),
                            ),
                          ],
                        ),
                        for (
                          var index = 0;
                          index < _affiliations.length;
                          index++
                        ) ...[
                          const SizedBox(height: 10),
                          _AffiliationEditor(
                            controllers: _affiliations[index],
                            nameLabel: t('clubEditor.affiliationName'),
                            numberLabel: t('clubEditor.affiliationNumber'),
                            fromLabel: t('clubEditor.validFrom'),
                            untilLabel: t('clubEditor.validUntil'),
                            removeLabel: t('clubEditor.removeAffiliation'),
                            onRemove: () => setState(() {
                              final removed = _affiliations.removeAt(index);
                              removed.dispose();
                            }),
                          ),
                        ],
                      ],
                    ),
                  ),
                const SizedBox(height: 14),
                if (club.canEditClubProfile)
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
                        CountryField(
                          value: _country,
                          label: t('clubEditor.country'),
                          onChanged: (value) =>
                              setState(() => _country = value),
                        ),
                      ],
                    ),
                  ),
                const SizedBox(height: 14),
                if (club.canEditClubProfile)
                  AirmiusPanel(
                    child: ExpansionTile(
                      tilePadding: EdgeInsets.zero,
                      childrenPadding: EdgeInsets.zero,
                      title: Eyebrow(t('clubEditor.address')),
                      children: [
                        const SizedBox(height: 12),
                        AirmiusTextField(
                          controller: _street,
                          label: t('clubEditor.street'),
                          icon: Icons.route_outlined,
                        ),
                        const SizedBox(height: 10),
                        AirmiusTextField(
                          controller: _houseNumber,
                          label: t('clubEditor.houseNumber'),
                          textInputAction: TextInputAction.next,
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
                if (club.canEditClubProfile)
                  AirmiusPanel(
                    child: ExpansionTile(
                      tilePadding: EdgeInsets.zero,
                      childrenPadding: EdgeInsets.zero,
                      title: Eyebrow(t('clubEditor.visibility')),
                      children: [
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

class _DocumentTemplateControllers {
  _DocumentTemplateControllers({
    String name = '',
    this.type = 'letter',
    String header = '',
    String footer = '',
    this.isDefault = false,
  }) : name = TextEditingController(text: name),
       header = TextEditingController(text: header),
       footer = TextEditingController(text: footer);

  factory _DocumentTemplateControllers.fromJson(JsonMap json) =>
      _DocumentTemplateControllers(
        name: json['name']?.toString() ?? '',
        type: json['type']?.toString() ?? 'letter',
        header: json['header']?.toString() ?? '',
        footer: json['footer']?.toString() ?? '',
        isDefault: json['is_default'] == true,
      );

  final TextEditingController name;
  String type;
  final TextEditingController header;
  final TextEditingController footer;
  bool isDefault;

  JsonMap toJson() => {
    'name': name.text.trim(),
    'type': type,
    'header': header.text.trim().isEmpty ? null : header.text.trim(),
    'footer': footer.text.trim().isEmpty ? null : footer.text.trim(),
    'is_default': isDefault,
  };

  void dispose() {
    name.dispose();
    header.dispose();
    footer.dispose();
  }
}

class _DocumentTemplateEditor extends StatelessWidget {
  const _DocumentTemplateEditor({
    required this.controllers,
    required this.nameLabel,
    required this.typeLabel,
    required this.headerLabel,
    required this.footerLabel,
    required this.defaultLabel,
    required this.removeLabel,
    required this.typeLabelFor,
    required this.onTypeChanged,
    required this.onDefaultChanged,
    required this.onRemove,
  });

  final _DocumentTemplateControllers controllers;
  final String nameLabel;
  final String typeLabel;
  final String headerLabel;
  final String footerLabel;
  final String defaultLabel;
  final String removeLabel;
  final String Function(String) typeLabelFor;
  final ValueChanged<String> onTypeChanged;
  final ValueChanged<bool> onDefaultChanged;
  final VoidCallback onRemove;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(12),
    decoration: BoxDecoration(
      border: Border.all(color: Theme.of(context).dividerColor),
      borderRadius: BorderRadius.circular(12),
    ),
    child: Column(
      children: [
        AirmiusTextField(controller: controllers.name, label: nameLabel),
        const SizedBox(height: 8),
        DropdownButtonFormField<String>(
          initialValue: controllers.type,
          decoration: InputDecoration(labelText: typeLabel),
          items: const ['letter', 'invoice', 'receipt', 'certificate', 'custom']
              .map(
                (type) => DropdownMenuItem(
                  value: type,
                  child: Text(typeLabelFor(type)),
                ),
              )
              .toList(),
          onChanged: (value) {
            if (value != null) onTypeChanged(value);
          },
        ),
        const SizedBox(height: 8),
        AirmiusTextField(controller: controllers.header, label: headerLabel),
        const SizedBox(height: 8),
        AirmiusTextField(
          controller: controllers.footer,
          label: footerLabel,
          maxLines: 2,
        ),
        SwitchListTile(
          contentPadding: EdgeInsets.zero,
          value: controllers.isDefault,
          title: Text(defaultLabel),
          onChanged: onDefaultChanged,
        ),
        Align(
          alignment: AlignmentDirectional.centerEnd,
          child: TextButton.icon(
            onPressed: onRemove,
            icon: const Icon(Icons.delete_outline),
            label: Text(removeLabel),
          ),
        ),
      ],
    ),
  );
}

class _ContactPersonControllers {
  _ContactPersonControllers({
    String name = '',
    String role = '',
    String email = '',
    String phone = '',
    this.isPublic = false,
  }) : name = TextEditingController(text: name),
       role = TextEditingController(text: role),
       email = TextEditingController(text: email),
       phone = TextEditingController(text: phone);

  factory _ContactPersonControllers.fromJson(JsonMap json) =>
      _ContactPersonControllers(
        name: json['name']?.toString() ?? '',
        role: json['role']?.toString() ?? '',
        email: json['email']?.toString() ?? '',
        phone: json['phone']?.toString() ?? '',
        isPublic: json['is_public'] == true,
      );

  final TextEditingController name;
  final TextEditingController role;
  final TextEditingController email;
  final TextEditingController phone;
  bool isPublic;

  JsonMap toJson() => {
    'name': name.text.trim(),
    'role': role.text.trim().isEmpty ? null : role.text.trim(),
    'email': email.text.trim().isEmpty ? null : email.text.trim(),
    'phone': phone.text.trim().isEmpty ? null : phone.text.trim(),
    'is_public': isPublic,
  };

  void dispose() {
    name.dispose();
    role.dispose();
    email.dispose();
    phone.dispose();
  }
}

class _ContactPersonEditor extends StatelessWidget {
  const _ContactPersonEditor({
    required this.controllers,
    required this.nameLabel,
    required this.roleLabel,
    required this.emailLabel,
    required this.phoneLabel,
    required this.publicLabel,
    required this.removeLabel,
    required this.onChanged,
    required this.onRemove,
  });

  final _ContactPersonControllers controllers;
  final String nameLabel;
  final String roleLabel;
  final String emailLabel;
  final String phoneLabel;
  final String publicLabel;
  final String removeLabel;
  final VoidCallback onChanged;
  final VoidCallback onRemove;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(12),
    decoration: BoxDecoration(
      border: Border.all(color: Theme.of(context).dividerColor),
      borderRadius: BorderRadius.circular(12),
    ),
    child: Column(
      children: [
        AirmiusTextField(controller: controllers.name, label: nameLabel),
        const SizedBox(height: 8),
        AirmiusTextField(controller: controllers.role, label: roleLabel),
        const SizedBox(height: 8),
        AirmiusTextField(
          controller: controllers.email,
          label: emailLabel,
          keyboardType: TextInputType.emailAddress,
          autocorrect: false,
        ),
        const SizedBox(height: 8),
        AirmiusTextField(
          controller: controllers.phone,
          label: phoneLabel,
          keyboardType: TextInputType.phone,
        ),
        SwitchListTile(
          contentPadding: EdgeInsets.zero,
          value: controllers.isPublic,
          title: Text(publicLabel),
          onChanged: (value) {
            controllers.isPublic = value;
            onChanged();
          },
        ),
        Align(
          alignment: AlignmentDirectional.centerEnd,
          child: TextButton.icon(
            onPressed: onRemove,
            icon: const Icon(Icons.delete_outline),
            label: Text(removeLabel),
          ),
        ),
      ],
    ),
  );
}

class _AffiliationControllers {
  _AffiliationControllers({
    String name = '',
    String number = '',
    String validFrom = '',
    String validUntil = '',
  }) : name = TextEditingController(text: name),
       number = TextEditingController(text: number),
       validFrom = TextEditingController(text: validFrom),
       validUntil = TextEditingController(text: validUntil);

  factory _AffiliationControllers.fromJson(JsonMap json) =>
      _AffiliationControllers(
        name: json['name']?.toString() ?? '',
        number: json['member_number']?.toString() ?? '',
        validFrom: json['valid_from']?.toString() ?? '',
        validUntil: json['valid_until']?.toString() ?? '',
      );

  final TextEditingController name;
  final TextEditingController number;
  final TextEditingController validFrom;
  final TextEditingController validUntil;

  JsonMap toJson() => {
    'name': name.text.trim(),
    'member_number': number.text.trim().isEmpty ? null : number.text.trim(),
    'valid_from': validFrom.text.trim().isEmpty ? null : validFrom.text.trim(),
    'valid_until': validUntil.text.trim().isEmpty
        ? null
        : validUntil.text.trim(),
  };

  void dispose() {
    name.dispose();
    number.dispose();
    validFrom.dispose();
    validUntil.dispose();
  }
}

class _AffiliationEditor extends StatelessWidget {
  const _AffiliationEditor({
    required this.controllers,
    required this.nameLabel,
    required this.numberLabel,
    required this.fromLabel,
    required this.untilLabel,
    required this.removeLabel,
    required this.onRemove,
  });

  final _AffiliationControllers controllers;
  final String nameLabel;
  final String numberLabel;
  final String fromLabel;
  final String untilLabel;
  final String removeLabel;
  final VoidCallback onRemove;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(12),
    decoration: BoxDecoration(
      border: Border.all(color: Theme.of(context).dividerColor),
      borderRadius: BorderRadius.circular(12),
    ),
    child: Column(
      children: [
        AirmiusTextField(controller: controllers.name, label: nameLabel),
        const SizedBox(height: 8),
        AirmiusTextField(controller: controllers.number, label: numberLabel),
        const SizedBox(height: 8),
        Row(
          children: [
            Expanded(
              child: AirmiusTextField(
                controller: controllers.validFrom,
                label: fromLabel,
                hint: 'YYYY-MM-DD',
                keyboardType: TextInputType.datetime,
              ),
            ),
            const SizedBox(width: 8),
            Expanded(
              child: AirmiusTextField(
                controller: controllers.validUntil,
                label: untilLabel,
                hint: 'YYYY-MM-DD',
                keyboardType: TextInputType.datetime,
              ),
            ),
          ],
        ),
        Align(
          alignment: AlignmentDirectional.centerEnd,
          child: TextButton.icon(
            onPressed: onRemove,
            icon: const Icon(Icons.delete_outline),
            label: Text(removeLabel),
          ),
        ),
      ],
    ),
  );
}
