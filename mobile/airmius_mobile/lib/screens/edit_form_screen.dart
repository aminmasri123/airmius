import 'package:flutter/material.dart';

import '../core/airmius_services_scope.dart';
import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

class EditFormScreen extends StatefulWidget {
  const EditFormScreen({super.key, required this.title, required this.subtitle, this.mode = EditFormMode.basic});

  final String title;
  final String subtitle;
  final EditFormMode mode;

  @override
  State<EditFormScreen> createState() => _EditFormScreenState();
}

enum EditFormMode { basic, profile, privacy, payment, admin, file, chat, event }

class _EditFormScreenState extends State<EditFormScreen> {
  bool _enabled = true;
  bool _publicVisible = true;
  String _status = 'Aktiv';
  String _role = 'Mitglied';
  String _gender = '';
  bool _profileInitialized = false;
  bool _saving = false;
  String? _error;
  late final TextEditingController _firstNameController;
  late final TextEditingController _lastNameController;
  late final TextEditingController _bioController;

  @override
  void initState() {
    super.initState();
    _firstNameController = TextEditingController();
    _lastNameController = TextEditingController();
    _bioController = TextEditingController();
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();

    if (widget.mode != EditFormMode.profile || _profileInitialized) {
      return;
    }

    final user = AirmiusServicesScope.of(context).authState.user;
    _firstNameController.text = user?.firstName?.trim().isNotEmpty == true ? user!.firstName!.trim() : '';
    _lastNameController.text = user?.lastName?.trim().isNotEmpty == true ? user!.lastName!.trim() : '';
    _bioController.text = user?.bio?.trim() ?? '';

    final gender = user?.gender?.trim() ?? '';
    if (_gender.isEmpty && _genderOptions.contains(gender)) {
      _gender = gender;
    }

    _profileInitialized = true;
  }

  @override
  void dispose() {
    _firstNameController.dispose();
    _lastNameController.dispose();
    _bioController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        backgroundColor: AirmiusColors.header,
        surfaceTintColor: Colors.transparent,
        title: Text(widget.title, style: const TextStyle(fontWeight: FontWeight.w900)),
      ),
      body: PageFrame(
        title: widget.title,
        subtitle: widget.subtitle,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            AirmiusPanel(
              gradient: true,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Eyebrow('Bearbeiten'),
                  const SizedBox(height: 8),
                  Text(widget.title, style: const TextStyle(color: AirmiusColors.text, fontSize: 24, fontWeight: FontWeight.w900)),
                  const SizedBox(height: 8),
                  Text(widget.subtitle, style: const TextStyle(color: AirmiusColors.muted, height: 1.35)),
                ],
              ),
            ),
            const SizedBox(height: 12),
            ..._fieldsForMode(),
            if (_error != null) ...[
              const SizedBox(height: 12),
              Text(_error!, style: const TextStyle(color: AirmiusColors.red, fontWeight: FontWeight.w800)),
            ],
            const SizedBox(height: 16),
            AirmiusButton(
              label: _saving ? 'Speichere...' : 'Speichern',
              icon: Icons.save_outlined,
              onPressed: _saving ? null : _save,
            ),
            const SizedBox(height: 10),
            AirmiusButton(label: 'Abbrechen', icon: Icons.close_outlined, secondary: true, onPressed: _saving ? null : () => Navigator.pop(context)),
          ],
        ),
      ),
    );
  }

  List<Widget> _fieldsForMode() {
    return switch (widget.mode) {
      EditFormMode.profile => _profileFields(),
      EditFormMode.privacy => _privacyFields(),
      EditFormMode.payment => _paymentFields(),
      EditFormMode.admin => _adminFields(),
      EditFormMode.file => _fileFields(),
      EditFormMode.chat => _chatFields(),
      EditFormMode.event => _eventFields(),
      EditFormMode.basic => _basicFields(),
    };
  }

  List<Widget> _basicFields() {
    return const [
      AirmiusPanel(child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
        AirmiusTextField(label: 'Titel', hint: 'Name oder Bezeichnung'),
        SizedBox(height: 12),
        AirmiusTextField(label: 'Beschreibung', hint: 'Kurzbeschreibung', maxLines: 4),
      ])),
    ];
  }

  List<Widget> _profileFields() {
    return [
      AirmiusPanel(child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
        const Eyebrow('Persoenliche Daten'),
        const SizedBox(height: 12),
        AirmiusTextField(label: 'Vorname', hint: 'Vorname', controller: _firstNameController),
        const SizedBox(height: 12),
        AirmiusTextField(label: 'Nachname', hint: 'Nachname', controller: _lastNameController),
        const SizedBox(height: 12),
        DropdownButtonFormField<String>(
          initialValue: _gender.isEmpty ? null : _gender,
          dropdownColor: AirmiusColors.cardSoft,
          decoration: const InputDecoration(
            labelText: 'Geschlecht',
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
        const SizedBox(height: 12),
        AirmiusTextField(label: 'Bio', hint: 'Sport, Verein, Ziele...', controller: _bioController, maxLines: 3),
      ])),
      const SizedBox(height: 12),
      const AirmiusPanel(child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
        Eyebrow('Sportprofil'),
        SizedBox(height: 12),
        AirmiusTextField(label: 'Hauptsportart', hint: 'Laufen, Tennis, Fitness...'),
        SizedBox(height: 12),
        AirmiusTextField(label: 'Level', hint: 'Einsteiger, Fortgeschritten, Trainer'),
      ])),
    ];
  }

  List<Widget> _privacyFields() {
    return [
      AirmiusPanel(child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
        const Eyebrow('Sichtbarkeit'),
        SwitchListTile(
          value: _publicVisible,
          onChanged: (value) => setState(() => _publicVisible = value),
          title: const Text('Profil sichtbar', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
          subtitle: const Text('Andere Nutzer können dein Profil finden.', style: TextStyle(color: AirmiusColors.muted)),
          activeThumbColor: AirmiusColors.blue,
          contentPadding: EdgeInsets.zero,
        ),
        SwitchListTile(
          value: _enabled,
          onChanged: (value) => setState(() => _enabled = value),
          title: const Text('Benachrichtigungen erlauben', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
          subtitle: const Text('Push, E-Mail und Vereinsupdates.', style: TextStyle(color: AirmiusColors.muted)),
          activeThumbColor: AirmiusColors.blue,
          contentPadding: EdgeInsets.zero,
        ),
      ])),
      const SizedBox(height: 12),
      const AirmiusPanel(child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
        Eyebrow('Datenrechte'),
        SizedBox(height: 10),
        AirmiusTextField(label: 'Export-Kommentar', hint: 'Optionaler Hinweis für Datenexport oder Löschanfrage', maxLines: 3),
      ])),
    ];
  }

  List<Widget> _paymentFields() {
    return const [
      AirmiusPanel(child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
        Eyebrow('Zahlung'),
        SizedBox(height: 12),
        AirmiusTextField(label: 'Zahlmethode', hint: 'Überweisung, Bar, SEPA'),
        SizedBox(height: 12),
        AirmiusTextField(label: 'IBAN', hint: 'DE...'),
        SizedBox(height: 12),
        AirmiusTextField(label: 'Rechnungsadresse', hint: 'Adresse für Rechnungen', maxLines: 3),
      ])),
    ];
  }

  List<Widget> _adminFields() {
    return [
      AirmiusPanel(child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
        const Eyebrow('Admin-Aktion'),
        const SizedBox(height: 12),
        DropdownButtonFormField<String>(
          initialValue: _status,
          dropdownColor: AirmiusColors.cardSoft,
          decoration: const InputDecoration(labelText: 'Status'),
          items: const ['Aktiv', 'In Prüfung', 'Gesperrt', 'Abgelehnt'].map((item) => DropdownMenuItem(value: item, child: Text(item))).toList(),
          onChanged: (value) => setState(() => _status = value ?? _status),
        ),
        const SizedBox(height: 12),
        DropdownButtonFormField<String>(
          initialValue: _role,
          dropdownColor: AirmiusColors.cardSoft,
          decoration: const InputDecoration(labelText: 'Rolle'),
          items: const ['Mitglied', 'Trainer', 'Club Admin', 'System Admin'].map((item) => DropdownMenuItem(value: item, child: Text(item))).toList(),
          onChanged: (value) => setState(() => _role = value ?? _role),
        ),
        const SizedBox(height: 12),
        const AirmiusTextField(label: 'Interne Notiz', hint: 'Warum wird der Status geändert?', maxLines: 3),
      ])),
    ];
  }

  List<Widget> _fileFields() {
    return const [
      AirmiusPanel(child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
        Eyebrow('Datei'),
        SizedBox(height: 12),
        AirmiusTextField(label: 'Dateiname', hint: 'Datenschutz.pdf'),
        SizedBox(height: 12),
        AirmiusTextField(label: 'Kategorie', hint: 'Vereinsdokumente'),
        SizedBox(height: 12),
        AirmiusTextField(label: 'Verknuepfung', hint: 'Mitgliedsantrag, Beitragsregel, Team'),
      ])),
    ];
  }

  List<Widget> _chatFields() {
    return const [
      AirmiusPanel(child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
        Eyebrow('Nachricht'),
        SizedBox(height: 12),
        AirmiusTextField(label: 'Antwort', hint: 'Nachricht schreiben...', maxLines: 4),
      ])),
    ];
  }

  List<Widget> _eventFields() {
    return const [
      AirmiusPanel(child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
        Eyebrow('Termin'),
        SizedBox(height: 12),
        AirmiusTextField(label: 'Titel', hint: 'Intervalltraining'),
        SizedBox(height: 12),
        AirmiusTextField(label: 'Ort', hint: 'Sportplatz'),
        SizedBox(height: 12),
        AirmiusTextField(label: 'Notiz', hint: 'Teilnehmerinfo, Material, Absagegrund...', maxLines: 3),
      ])),
    ];
  }

  Future<void> _save() async {
    if (widget.mode != EditFormMode.profile) {
      Navigator.pop(context);
      return;
    }

    final services = AirmiusServicesScope.of(context);
    final authState = services.authState;
    final user = authState.user;
    final firstName = _firstNameController.text.trim();
    final lastName = _lastNameController.text.trim();
    final country = (user?.country ?? 'DE').trim().toUpperCase();
    final birthDate = user?.birthDate;

    if (firstName.isEmpty || lastName.isEmpty || _gender.isEmpty) {
      setState(() => _error = 'Bitte Vorname, Nachname und Geschlecht ausfuellen.');
      return;
    }

    if (birthDate == null || country.length != 2) {
      setState(() => _error = 'Bitte vervollstaendige zuerst Geburtsdatum und Land.');
      return;
    }

    setState(() {
      _saving = true;
      _error = null;
    });

    await authState.completeProfile(
      payload: {
        'first_name': firstName,
        'last_name': lastName,
        'birth_date': _dateText(birthDate),
        'gender': _gender,
        'country': country,
        'bio': _bioController.text.trim(),
        'guardian_email': user?.guardianEmail ?? '',
      },
    );

    if (!mounted) return;

    final error = authState.error;
    if (error != null && error.isNotEmpty) {
      setState(() {
        _saving = false;
        _error = error;
      });
      return;
    }

    Navigator.pop(context);
  }
}

const _genderOptions = ['female', 'male', 'diverse', 'not_specified'];

String _dateText(DateTime date) {
  final month = date.month.toString().padLeft(2, '0');
  final day = date.day.toString().padLeft(2, '0');
  return '${date.year}-$month-$day';
}
