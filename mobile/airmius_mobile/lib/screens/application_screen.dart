import 'package:flutter/material.dart';

import '../core/airmius_api_models.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';
import '../core/airmius_theme.dart';
import '../models/club_summary.dart';
import '../widgets/airmius_widgets.dart';

class ApplicationScreen extends StatefulWidget {
  const ApplicationScreen({super.key, required this.club});

  final ClubSummary club;

  @override
  State<ApplicationScreen> createState() => _ApplicationScreenState();
}

class _ApplicationScreenState extends State<ApplicationScreen> {
  final _firstName = TextEditingController();
  final _lastName = TextEditingController();
  final _birthDate = TextEditingController();
  final _gender = TextEditingController();
  final _license = TextEditingController();
  final _email = TextEditingController();
  final _phone = TextEditingController();
  final _country = TextEditingController();
  final _street = TextEditingController();
  final _houseNumber = TextEditingController();
  final _postalCode = TextEditingController();
  final _city = TextEditingController();
  final _state = TextEditingController();
  final _guardianName = TextEditingController();
  final _guardianEmail = TextEditingController();
  final _emergencyName = TextEditingController();
  final _emergencyPhone = TextEditingController();
  final _iban = TextEditingController();
  final _bic = TextEditingController();
  String _membershipType = 'Allgemeine Anfrage';
  String _paymentMethod = 'Überweisung';
  String _paymentCycle = 'Monatlich';
  bool _documentsAccepted = false;
  bool _privacyAccepted = false;
  bool _uploadedDocument = false;
  bool _sending = false;
  String? _sendError;
  bool _profilePrefilled = false;

  bool get _canSend => _documentsAccepted && _privacyAccepted;

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
    _birthDate.dispose();
    _gender.dispose();
    _license.dispose();
    _email.dispose();
    _phone.dispose();
    _country.dispose();
    _street.dispose();
    _houseNumber.dispose();
    _postalCode.dispose();
    _city.dispose();
    _state.dispose();
    _guardianName.dispose();
    _guardianEmail.dispose();
    _emergencyName.dispose();
    _emergencyPhone.dispose();
    _iban.dispose();
    _bic.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final scope = AirmiusScope.of(context);
    final authUser = AirmiusServicesScope.of(context).authState.user;
    final showGuardianSection = !_isKnownAdult(authUser?.birthDate ?? _parseBirthDate(_birthDate.text));
    return Scaffold(
      backgroundColor: Colors.black.withValues(alpha: 0.62),
      body: SafeArea(
        child: LayoutBuilder(
          builder: (context, constraints) {
            return Align(
              alignment: constraints.maxWidth < 700 ? Alignment.bottomCenter : Alignment.center,
              child: ConstrainedBox(
                constraints: BoxConstraints(maxWidth: 672, maxHeight: constraints.maxHeight - 24),
                child: Container(
                  margin: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: AirmiusColors.card,
                    borderRadius: BorderRadius.circular(24),
                    border: Border.all(color: AirmiusColors.border),
                    boxShadow: [BoxShadow(color: Colors.black.withValues(alpha: 0.34), blurRadius: 30, offset: const Offset(0, 18))],
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      Padding(
                        padding: const EdgeInsets.fromLTRB(16, 14, 10, 8),
                        child: Row(
                          children: [
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text(scope.t('application'), style: const TextStyle(color: AirmiusColors.text, fontSize: 18, fontWeight: FontWeight.w900)),
                                  const SizedBox(height: 3),
                                  Text(widget.club.name, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(color: AirmiusColors.muted, fontSize: 12, fontWeight: FontWeight.w700)),
                                ],
                              ),
                            ),
                            IconButton(onPressed: _sending ? null : () => Navigator.pop(context), icon: const Icon(Icons.close, color: AirmiusColors.muted)),
                          ],
                        ),
                      ),
                      Expanded(
                        child: SingleChildScrollView(
                          padding: const EdgeInsets.fromLTRB(16, 6, 16, 18),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.stretch,
                            children: [
            AirmiusPanel(
              gradient: true,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Eyebrow('Mitgliedsantrag'),
                  const SizedBox(height: 8),
                  Text(widget.club.name, style: const TextStyle(color: AirmiusColors.text, fontSize: 24, fontWeight: FontWeight.w900)),
                  const SizedBox(height: 14),
                  _SelectField(label: 'Mitgliedschaftstyp', value: _membershipType, items: const ['Allgemeine Anfrage', 'Aktives Mitglied', 'Foerdermitglied', 'Probetraining'], onChanged: (value) => setState(() => _membershipType = value)),
                  const SizedBox(height: 10),
                  const Text('Die sichtbaren Felder können später vom Verein pro Mitgliedschaftstyp ein- oder ausgeschaltet werden.', style: TextStyle(color: AirmiusColors.muted, height: 1.35)),
                ],
              ),
            ),
            const SizedBox(height: 12),
            _ProgressPanel(done: _canSend ? 6 : 4),
            const SizedBox(height: 12),
            _FormSection(step: '1', title: 'Personendaten', children: [
              AirmiusTextField(label: 'Vorname *', controller: _firstName),
              AirmiusTextField(label: 'Nachname *', controller: _lastName),
              AirmiusTextField(label: 'Geburtsdatum *', hint: 'TT.MM.JJJJ', icon: Icons.calendar_today_outlined, controller: _birthDate),
              DropdownButtonFormField<String>(
                initialValue: _membershipGenderOptions.contains(_gender.text.trim()) ? _gender.text.trim() : null,
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
                onChanged: (value) => setState(() => _gender.text = value ?? ''),
              ),
              AirmiusTextField(label: 'Lizenznummer', hint: 'Sport- oder Vereinslizenz', controller: _license),
            ]),
            const SizedBox(height: 12),
            _FormSection(step: '2', title: 'Kontaktdaten', children: [
              AirmiusTextField(label: 'E-Mail *', icon: Icons.mail_outline, controller: _email),
              AirmiusTextField(label: 'Telefon', hint: '+49 ...', icon: Icons.phone_outlined, controller: _phone),
            ]),
            const SizedBox(height: 12),
            _FormSection(step: '3', title: 'Wohndaten', children: [
              AirmiusTextField(label: 'Land *', hint: 'DE', controller: _country),
              AirmiusTextField(label: 'Straße *', controller: _street),
              AirmiusTextField(label: 'Hausnummer *', controller: _houseNumber),
              AirmiusTextField(label: 'PLZ *', controller: _postalCode),
              AirmiusTextField(label: 'Stadt *', controller: _city),
              AirmiusTextField(label: 'Bundesland / Region', controller: _state),
            ]),
            if (showGuardianSection) ...[
              const SizedBox(height: 12),
              _FormSection(step: '4', title: 'Erziehungsberechtigte', children: [
                AirmiusTextField(label: 'Name Erziehungsberechtigte/r', hint: 'Falls minderjaehrig', controller: _guardianName),
                AirmiusTextField(label: 'E-Mail Erziehungsberechtigte/r', hint: 'Optional', controller: _guardianEmail),
              ]),
            ],
            const SizedBox(height: 12),
            _FormSection(step: '5', title: 'Notfallkontakt', children: [
              AirmiusTextField(label: 'Notfallkontakt Name', hint: 'Name', controller: _emergencyName),
              AirmiusTextField(label: 'Notfallkontakt Telefon', hint: '+49 ...', controller: _emergencyPhone),
            ]),
            const SizedBox(height: 12),
            AirmiusPanel(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const _StepHeader(step: '6', title: 'Zahlungsdaten'),
                  const SizedBox(height: 14),
                  _SelectField(label: 'Zahlmethode', value: _paymentMethod, items: const ['Überweisung', 'Bar', 'SEPA-Lastschrift'], onChanged: (value) => setState(() => _paymentMethod = value)),
                  const SizedBox(height: 12),
                  _SelectField(label: 'Zahlungsrhythmus', value: _paymentCycle, items: const ['Monatlich', 'Alle 4 Monate', 'Halbjaehrlich', 'Jaehrlich'], onChanged: (value) => setState(() => _paymentCycle = value)),
                  const SizedBox(height: 12),
                  AirmiusTextField(label: 'IBAN', hint: 'Nur falls SEPA aktiv ist', controller: _iban),
                  const SizedBox(height: 12),
                  AirmiusTextField(label: 'BIC', hint: 'Optional', controller: _bic),
                ],
              ),
            ),
            const SizedBox(height: 12),
            AirmiusPanel(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Eyebrow('Dokumente & Regeln'),
                  const SizedBox(height: 8),
                  const Text('Der Verein kann hier später Pflichtdokumente aus dem Dateimanager verknuepfen.', style: TextStyle(color: AirmiusColors.muted, height: 1.35)),
                  const SizedBox(height: 12),
                  _UploadTile(selected: _uploadedDocument, onTap: () => setState(() => _uploadedDocument = !_uploadedDocument)),
                  const SizedBox(height: 10),
                  _CheckLine(label: 'Ich akzeptiere Datenschutz und Verarbeitung meiner Daten.', checked: _privacyAccepted, onChanged: (value) => setState(() => _privacyAccepted = value)),
                  _CheckLine(label: 'Ich akzeptiere Beitragsordnung und Vereinsregeln.', checked: _documentsAccepted, onChanged: (value) => setState(() => _documentsAccepted = value)),
                ],
              ),
            ),
            const SizedBox(height: 16),
            if (_sendError != null) ...[
              AirmiusPanel(
                borderColor: AirmiusColors.red.withValues(alpha: .55),
                child: Text(_sendError!, style: const TextStyle(color: AirmiusColors.muted, height: 1.35)),
              ),
              const SizedBox(height: 12),
            ],
            AirmiusButton(label: _sending ? 'Wird gesendet...' : scope.t('send'), icon: _sending ? Icons.sync_outlined : Icons.send_outlined, onPressed: _canSend && !_sending ? _submit : null),
            const SizedBox(height: 24),
                            ],
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            );
          },
        ),
      ),
    );
  }

  Future<void> _submit() async {
    setState(() {
      _sending = true;
      _sendError = null;
    });
    try {
      final services = AirmiusServicesScope.of(context);
      await services.repositories.memberships.applyToClub(widget.club.id, {
        'type': 'membership',
        'preferred_payment_method': _paymentMethodValue(_paymentMethod),
        'requested_billing_interval': _paymentCycleValue(_paymentCycle),
        'application_data': {
          'membership_type': _membershipType,
          'first_name': _firstName.text.trim(),
          'last_name': _lastName.text.trim(),
          'birth_date': _birthDate.text.trim(),
          'gender': _gender.text.trim(),
          'license_number': _license.text.trim(),
          'email': _email.text.trim(),
          'phone': _phone.text.trim(),
          'country': _country.text.trim(),
          'street': _street.text.trim(),
          'house_number': _houseNumber.text.trim(),
          'postal_code': _postalCode.text.trim(),
          'city': _city.text.trim(),
          'state': _state.text.trim(),
          'guardian_name': _guardianName.text.trim(),
          'guardian_email': _guardianEmail.text.trim(),
          'emergency_name': _emergencyName.text.trim(),
          'emergency_phone': _emergencyPhone.text.trim(),
          'iban': _iban.text.trim(),
          'bic': _bic.text.trim(),
          'privacy_accepted': _privacyAccepted,
          'documents_accepted': _documentsAccepted,
          'uploaded_document': _uploadedDocument,
          'source': 'flutter_mobile',
        },
        'accepted_documents': [
          if (_privacyAccepted) 'privacy',
          if (_documentsAccepted) 'club_rules',
          if (_uploadedDocument) 'uploaded_document',
        ],
        'message': 'Mitgliedschaft: $_membershipType',
        'source': 'flutter_mobile',
      });
      if (mounted) Navigator.pop(context, true);
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _sendError = 'Anfrage konnte nicht gesendet werden: $error';
        _sending = false;
      });
    }
  }

  String _paymentMethodValue(String label) {
    return switch (label) {
      'SEPA-Lastschrift' => 'sepa_debit',
      'Bar' => 'cash',
      _ => 'bank_transfer',
    };
  }

  String _paymentCycleValue(String label) {
    return switch (label) {
      'Alle 4 Monate' => 'quarterly',
      'Halbjaehrlich' => 'half_yearly',
      'Jaehrlich' => 'yearly',
      _ => 'monthly',
    };
  }

  void _prefillFromUser(AirmiusUser user) {
    final nameParts = _splitName(user);
    _fillIfEmpty(_firstName, user.firstName ?? nameParts.$1);
    _fillIfEmpty(_lastName, user.lastName ?? nameParts.$2);
    _fillIfEmpty(_birthDate, _formatDate(user.birthDate));
    if (_gender.text.trim().isEmpty && _membershipGenderOptions.contains(user.gender)) {
      _gender.text = user.gender!;
    }
    _fillIfEmpty(_email, user.email);
    _fillIfEmpty(_country, user.country ?? 'DE');
    _fillIfEmpty(_street, user.street);
    _fillIfEmpty(_houseNumber, user.houseNumber);
    _fillIfEmpty(_postalCode, user.postalCode);
    _fillIfEmpty(_city, user.city);
    _fillIfEmpty(_state, user.state);
    _fillIfEmpty(_guardianEmail, user.guardianEmail);
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
    if (today.month < birthDate.month || (today.month == birthDate.month && today.day < birthDate.day)) {
      age -= 1;
    }
    return age >= 18;
  }
}

const _membershipGenderOptions = ['female', 'male', 'diverse', 'not_specified'];

class _FormSection extends StatelessWidget {
  const _FormSection({required this.step, required this.title, required this.children});

  final String step;
  final String title;
  final List<Widget> children;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          _StepHeader(step: step, title: title),
          const SizedBox(height: 14),
          for (var i = 0; i < children.length; i++) ...[
            children[i],
            if (i < children.length - 1) const SizedBox(height: 12),
          ],
        ],
      ),
    );
  }
}

class _StepHeader extends StatelessWidget {
  const _StepHeader({required this.step, required this.title});

  final String step;
  final String title;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Container(
          width: 30,
          height: 30,
          decoration: BoxDecoration(
            color: AirmiusColors.blue.withValues(alpha: 0.18),
            borderRadius: BorderRadius.circular(10),
            border: Border.all(color: AirmiusColors.blue.withValues(alpha: 0.45)),
          ),
          child: Center(child: Text(step, style: const TextStyle(color: AirmiusColors.blue, fontWeight: FontWeight.w900))),
        ),
        const SizedBox(width: 10),
        Expanded(child: Text(title, style: const TextStyle(color: AirmiusColors.text, fontSize: 16, fontWeight: FontWeight.w900))),
      ],
    );
  }
}

class _SelectField extends StatelessWidget {
  const _SelectField({required this.label, required this.value, required this.items, required this.onChanged});

  final String label;
  final String value;
  final List<String> items;
  final ValueChanged<String> onChanged;

  @override
  Widget build(BuildContext context) {
    return DropdownButtonFormField<String>(
      initialValue: value,
      dropdownColor: AirmiusColors.cardSoft,
      style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w800),
      decoration: InputDecoration(labelText: label),
      items: items.map((item) => DropdownMenuItem(value: item, child: Text(item))).toList(),
      onChanged: (value) {
        if (value != null) onChanged(value);
      },
    );
  }
}

class _ProgressPanel extends StatelessWidget {
  const _ProgressPanel({required this.done});

  final int done;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(children: [const Expanded(child: Eyebrow('Fortschritt')), Text('$done / 6', style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900))]),
          const SizedBox(height: 12),
          ClipRRect(
            borderRadius: BorderRadius.circular(99),
            child: LinearProgressIndicator(
              value: done / 6,
              minHeight: 9,
              backgroundColor: AirmiusColors.cardSoft,
              valueColor: const AlwaysStoppedAnimation<Color>(AirmiusColors.blue),
            ),
          ),
          const SizedBox(height: 10),
          Text(done == 6 ? 'Bereit zum Senden.' : 'Bitte Regeln und Datenschutz bestätigen.', style: const TextStyle(color: AirmiusColors.muted, height: 1.35)),
        ],
      ),
    );
  }
}

class _UploadTile extends StatelessWidget {
  const _UploadTile({required this.selected, required this.onTap});

  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(16),
      child: Container(
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(
          color: AirmiusColors.cardSoft,
          borderRadius: BorderRadius.circular(16),
          border: Border.all(color: selected ? AirmiusColors.green : AirmiusColors.border),
        ),
        child: Row(
          children: [
            Icon(selected ? Icons.check_circle_outline : Icons.upload_file_outlined, color: selected ? AirmiusColors.green : AirmiusColors.blue),
            const SizedBox(width: 12),
            const Expanded(child: Text('Dokument hochladen', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900))),
            Text(selected ? 'Ausgewählt' : 'Datei', style: const TextStyle(color: AirmiusColors.muted, fontSize: 12)),
          ],
        ),
      ),
    );
  }
}

class _CheckLine extends StatelessWidget {
  const _CheckLine({required this.label, required this.checked, required this.onChanged});

  final String label;
  final bool checked;
  final ValueChanged<bool> onChanged;

  @override
  Widget build(BuildContext context) {
    return CheckboxListTile(
      value: checked,
      onChanged: (value) => onChanged(value ?? false),
      contentPadding: EdgeInsets.zero,
      activeColor: AirmiusColors.blue,
      title: Text(label, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w700, height: 1.3)),
    );
  }
}
