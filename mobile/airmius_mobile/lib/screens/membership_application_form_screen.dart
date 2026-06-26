import 'package:flutter/material.dart';

import '../core/airmius_api_models.dart';
import '../core/airmius_services_scope.dart';
import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'club_policy_documents_screen.dart';
import 'membership_request_status_screen.dart';

class MembershipApplicationFormScreen extends StatefulWidget {
  const MembershipApplicationFormScreen({super.key});

  @override
  State<MembershipApplicationFormScreen> createState() => _MembershipApplicationFormScreenState();
}

class _MembershipApplicationFormScreenState extends State<MembershipApplicationFormScreen> {
  final _firstName = TextEditingController();
  final _lastName = TextEditingController();
  final _birthday = TextEditingController();
  final _email = TextEditingController();
  final _phone = TextEditingController();
  final _street = TextEditingController();
  final _house = TextEditingController();
  final _zip = TextEditingController();
  final _city = TextEditingController();
  final _license = TextEditingController();
  final _guardianName = TextEditingController();
  final _emergencyName = TextEditingController();
  final _iban = TextEditingController();

  String _gender = '';
  String _membershipType = 'Allgemeine Anfrage';
  String _paymentMethod = 'Ueberweisung';
  String _interval = 'Monatlich';
  bool _privacyAccepted = true;
  bool _rulesAccepted = true;
  bool _contributionAccepted = true;
  bool _sepaAccepted = false;
  bool _profilePrefilled = false;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (_profilePrefilled) return;
    _profilePrefilled = true;
    final authUser = AirmiusServicesScope.of(context).authState.user;
    if (authUser != null) {
      _prefillFromUser(authUser);
    }
  }

  @override
  void dispose() {
    _firstName.dispose();
    _lastName.dispose();
    _birthday.dispose();
    _email.dispose();
    _phone.dispose();
    _street.dispose();
    _house.dispose();
    _zip.dispose();
    _city.dispose();
    _license.dispose();
    _guardianName.dispose();
    _emergencyName.dispose();
    _iban.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final authUser = AirmiusServicesScope.of(context).authState.user;
    final showGuardianSection = !_isKnownAdult(authUser?.birthDate ?? _parseBirthDate(_birthday.text));

    return Scaffold(
      backgroundColor: AirmiusColors.bg,
      body: SafeArea(
        child: CustomScrollView(
          slivers: [
            SliverToBoxAdapter(
              child: Padding(
                padding: const EdgeInsets.fromLTRB(16, 14, 16, 18),
                child: Center(
                  child: ConstrainedBox(
                    constraints: const BoxConstraints(maxWidth: 760),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        const PageTitle(title: 'Mitgliedsantrag', subtitle: 'Mobile Formularstrecke fuer Vereinsbeitritt, Daten, Zahlung, Dokumente und Senden.'),
                        const SizedBox(height: 16),
                        _ApplicationHero(onSubmit: _submit),
                        const SizedBox(height: 16),
                        _SelectPanel(title: 'Mitgliedschaftstyp', value: _membershipType, values: const ['Allgemeine Anfrage', 'Aktivmitglied', 'Jugendmitglied', 'Foerdermitglied'], onChanged: (value) => setState(() => _membershipType = value)),
                        const SizedBox(height: 12),
                        _FormSection(title: 'Personendaten', children: [
                          AirmiusTextField(label: 'Vorname *', controller: _firstName),
                          AirmiusTextField(label: 'Nachname *', controller: _lastName),
                          DropdownButtonFormField<String>(
                            value: _gender.isEmpty ? null : _gender,
                            dropdownColor: AirmiusColors.cardSoft,
                            decoration: const InputDecoration(
                              labelText: 'Geschlecht *',
                              prefixIcon: Icon(Icons.wc_outlined, color: AirmiusColors.muted),
                            ),
                            style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w800),
                            items: const [
                              DropdownMenuItem(value: 'female', child: Text('Weiblich')),
                              DropdownMenuItem(value: 'male', child: Text('Männlich')),
                              DropdownMenuItem(value: 'diverse', child: Text('Divers')),
                              DropdownMenuItem(value: 'not_specified', child: Text('Keine Angabe')),
                            ],
                            onChanged: (value) => setState(() => _gender = value ?? ''),
                          ),
                          AirmiusTextField(label: 'Geburtsdatum *', controller: _birthday),
                        ]),
                        _FormSection(title: 'Sportdaten', children: [
                          AirmiusTextField(label: 'Lizenznummer', controller: _license),
                        ]),
                        _FormSection(title: 'Kontaktdaten', children: [
                          AirmiusTextField(label: 'E-Mail *', controller: _email),
                          AirmiusTextField(label: 'Telefon', controller: _phone),
                        ]),
                        _FormSection(title: 'Wohndaten', children: [
                          AirmiusTextField(label: 'Strasse *', controller: _street),
                          AirmiusTextField(label: 'Hausnummer *', controller: _house),
                          AirmiusTextField(label: 'PLZ *', controller: _zip),
                          AirmiusTextField(label: 'Stadt *', controller: _city),
                        ]),
                        if (showGuardianSection)
                          _FormSection(title: 'Erziehungsberechtigte', children: [
                            AirmiusTextField(label: 'Name Erziehungsberechtigte/r', controller: _guardianName),
                          ]),
                        _FormSection(title: 'Notfallkontakt', children: [
                          AirmiusTextField(label: 'Notfallkontakt Name', controller: _emergencyName),
                        ]),
                        _SelectPanel(title: 'Zahlmethode', value: _paymentMethod, values: const ['Ueberweisung', 'Bar', 'SEPA'], onChanged: (value) => setState(() => _paymentMethod = value)),
                        const SizedBox(height: 12),
                        _SelectPanel(title: 'Zahlungsintervall', value: _interval, values: const ['Monatlich', '4 Monate', '6 Monate', 'Jaehrlich'], onChanged: (value) => setState(() => _interval = value)),
                        const SizedBox(height: 12),
                        _FormSection(title: 'Zahlungsdaten', children: [
                          AirmiusTextField(label: 'IBAN', controller: _iban),
                        ]),
                        _DocumentAcceptancePanel(
                          privacyAccepted: _privacyAccepted,
                          rulesAccepted: _rulesAccepted,
                          contributionAccepted: _contributionAccepted,
                          sepaAccepted: _sepaAccepted,
                          onPrivacy: (value) => setState(() => _privacyAccepted = value),
                          onRules: (value) => setState(() => _rulesAccepted = value),
                          onContribution: (value) => setState(() => _contributionAccepted = value),
                          onSepa: (value) => setState(() => _sepaAccepted = value),
                        ),
                        const SizedBox(height: 16),
                        AirmiusPanel(
                          title: 'Antrag senden',
                          child: Wrap(
                            spacing: 10,
                            runSpacing: 10,
                            children: [
                              AirmiusButton(label: 'Dokumente ansehen', icon: Icons.policy_outlined, secondary: true, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => ClubPolicyDocumentsScreen()))),
                              AirmiusButton(label: 'Entwurf speichern', icon: Icons.save_outlined, secondary: true, onPressed: () => _toast('Entwurf speichern vorbereitet')),
                              AirmiusButton(label: 'Anfrage senden', icon: Icons.send_outlined, onPressed: _submit),
                            ],
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  void _submit() {
    _toast('Mitgliedsanfrage senden vorbereitet');
    Navigator.push(context, MaterialPageRoute(builder: (_) => MembershipRequestStatusScreen()));
  }

  void _prefillFromUser(AirmiusUser user) {
    final nameParts = _splitName(user);
    _fillIfEmpty(_firstName, user.firstName ?? nameParts.$1);
    _fillIfEmpty(_lastName, user.lastName ?? nameParts.$2);
    if (_gender.isEmpty && _membershipGenderOptions.contains(user.gender)) {
      _gender = user.gender!;
    }
    _fillIfEmpty(_birthday, _formatDate(user.birthDate));
    _fillIfEmpty(_email, user.email);
    _fillIfEmpty(_street, user.street);
    _fillIfEmpty(_house, user.houseNumber);
    _fillIfEmpty(_zip, user.postalCode);
    _fillIfEmpty(_city, user.city);
  }

  void _fillIfEmpty(TextEditingController controller, String? value) {
    final text = value?.trim();
    if (text == null || text.isEmpty || controller.text.trim().isNotEmpty) return;
    controller.text = text;
  }

  (String?, String?) _splitName(AirmiusUser user) {
    final parts = user.name.trim().split(RegExp(r'\s+')).where((part) => part.isNotEmpty).toList();
    if (parts.isEmpty) return (null, null);
    if (parts.length == 1) return (parts.first, null);
    return (parts.first, parts.skip(1).join(' '));
  }

  String? _formatDate(DateTime? date) {
    if (date == null) return null;
    final day = date.day.toString().padLeft(2, '0');
    final month = date.month.toString().padLeft(2, '0');
    return '$day.$month.${date.year}';
  }

  DateTime? _parseBirthDate(String value) {
    final text = value.trim();
    if (text.isEmpty) return null;
    final iso = DateTime.tryParse(text);
    if (iso != null) return iso;
    final match = RegExp(r'^(\d{1,2})\.(\d{1,2})\.(\d{4})$').firstMatch(text);
    if (match == null) return null;
    return DateTime.tryParse('${match.group(3)}-${match.group(2)!.padLeft(2, '0')}-${match.group(1)!.padLeft(2, '0')}');
  }

  bool _isKnownAdult(DateTime? birthDate) {
    if (birthDate == null) return false;
    final today = DateTime.now();
    var age = today.year - birthDate.year;
    final hadBirthdayThisYear = today.month > birthDate.month || (today.month == birthDate.month && today.day >= birthDate.day);
    if (!hadBirthdayThisYear) age -= 1;
    return age >= 18;
  }

  void _toast(String message) {
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(message)));
  }
}

class _ApplicationHero extends StatelessWidget {
  const _ApplicationHero({required this.onSubmit});

  final VoidCallback onSubmit;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        gradient: const LinearGradient(colors: [Color(0xFF12243A), Color(0xFF0B111B)], begin: Alignment.topLeft, end: Alignment.bottomRight),
        borderRadius: BorderRadius.circular(22),
        border: Border.all(color: AirmiusColors.borderStrong),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              const AirmiusAvatar('ZBB', large: true),
              const SizedBox(width: 12),
              const Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Eyebrow('MITGLIEDSANTRAG'), SizedBox(height: 4), Text('ZBB beitreten', style: TextStyle(color: AirmiusColors.text, fontSize: 24, fontWeight: FontWeight.w900)), Text('Verein - Profil', style: TextStyle(color: AirmiusColors.muted, fontWeight: FontWeight.w800))])),
              AirmiusButton(label: 'Senden', icon: Icons.send_outlined, onPressed: onSubmit),
            ],
          ),
          const SizedBox(height: 14),
          const Text('Das Formular folgt der mobilen Web-App: klar gegliederte Bereiche, Pflichtdaten, Zahlungsdaten, Dokumente und ein nachvollziehbarer Status nach dem Senden.', style: TextStyle(color: AirmiusColors.muted, height: 1.45, fontWeight: FontWeight.w700)),
        ],
      ),
    );
  }
}

class _SelectPanel extends StatelessWidget {
  const _SelectPanel({required this.title, required this.value, required this.values, required this.onChanged});

  final String title;
  final String value;
  final List<String> values;
  final ValueChanged<String> onChanged;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      title: title,
      child: Wrap(
        spacing: 8,
        runSpacing: 8,
        children: [
          for (final item in values)
            ChoiceChip(
              label: Text(item),
              selected: value == item,
              onSelected: (_) => onChanged(item),
              selectedColor: AirmiusColors.blue.withValues(alpha: .24),
              backgroundColor: AirmiusColors.card,
              labelStyle: TextStyle(color: value == item ? AirmiusColors.text : AirmiusColors.muted, fontWeight: FontWeight.w900),
              side: BorderSide(color: value == item ? AirmiusColors.blue : AirmiusColors.border),
            ),
        ],
      ),
    );
  }
}

class _FormSection extends StatelessWidget {
  const _FormSection({required this.title, required this.children});

  final String title;
  final List<Widget> children;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: AirmiusPanel(
        title: title,
        child: Column(
          children: [
            for (final child in children) ...[
              child,
              const SizedBox(height: 10),
            ],
          ],
        ),
      ),
    );
  }
}

class _DocumentAcceptancePanel extends StatelessWidget {
  const _DocumentAcceptancePanel({
    required this.privacyAccepted,
    required this.rulesAccepted,
    required this.contributionAccepted,
    required this.sepaAccepted,
    required this.onPrivacy,
    required this.onRules,
    required this.onContribution,
    required this.onSepa,
  });

  final bool privacyAccepted;
  final bool rulesAccepted;
  final bool contributionAccepted;
  final bool sepaAccepted;
  final ValueChanged<bool> onPrivacy;
  final ValueChanged<bool> onRules;
  final ValueChanged<bool> onContribution;
  final ValueChanged<bool> onSepa;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      title: 'Dokumente & Zustimmung',
      child: Column(
        children: [
          _CheckRow(title: 'Datenschutz akzeptieren *', value: privacyAccepted, onChanged: onPrivacy),
          _CheckRow(title: 'Vereinsregeln akzeptieren *', value: rulesAccepted, onChanged: onRules),
          _CheckRow(title: 'Beitragsordnung akzeptieren *', value: contributionAccepted, onChanged: onContribution),
          _CheckRow(title: 'SEPA-Mandat akzeptieren', value: sepaAccepted, onChanged: onSepa),
        ],
      ),
    );
  }
}

class _CheckRow extends StatelessWidget {
  const _CheckRow({required this.title, required this.value, required this.onChanged});

  final String title;
  final bool value;
  final ValueChanged<bool> onChanged;

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(color: AirmiusColors.input, borderRadius: BorderRadius.circular(16), border: Border.all(color: value ? AirmiusColors.blue : AirmiusColors.border)),
      child: Row(
        children: [
          Checkbox(value: value, onChanged: (next) => onChanged(next ?? false), activeColor: AirmiusColors.blue),
          const SizedBox(width: 8),
          Expanded(child: Text(title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900))),
        ],
      ),
    );
  }
}

const _membershipGenderOptions = ['female', 'male', 'diverse', 'not_specified'];
