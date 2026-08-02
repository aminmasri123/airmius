import 'package:flutter/material.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

class InputKeyboardAccessibilityParitySuiteScreen extends StatefulWidget {
  const InputKeyboardAccessibilityParitySuiteScreen({super.key});

  @override
  State<InputKeyboardAccessibilityParitySuiteScreen> createState() =>
      _InputKeyboardAccessibilityParitySuiteScreenState();
}

class _InputKeyboardAccessibilityParitySuiteScreenState
    extends State<InputKeyboardAccessibilityParitySuiteScreen> {
  String _fieldGroup = 'Mitgliedsantrag';
  String _keyboard = 'Text';
  bool _showValidation = true;
  bool _largeTouchTargets = true;
  bool _screenReaderHints = true;

  static const _groups = [
    'Mitgliedsantrag',
    'Account',
    'Zahlung',
    'Club Admin',
    'Training',
  ];
  static const _keyboards = [
    'Text',
    'E-Mail',
    'Telefon',
    'Datum',
    'Nummer',
    'Passwort',
  ];

  static const _fields = <_InputFieldPattern>[
    _InputFieldPattern(
      group: 'Mitgliedsantrag',
      title: 'Personendaten',
      body:
          'Vorname, Nachname, Geburtsdatum, Geschlecht, Guardian-Felder und Pflichtfeldmarkierung als mobile Formularsektion.',
      inputType: 'Text + Datum',
      icon: Icons.person_outline,
      status: 'Required',
      primary: 'Felder prüfen',
      secondary: 'Fokus',
      color: AirmiusColors.blue,
    ),
    _InputFieldPattern(
      group: 'Mitgliedsantrag',
      title: 'Adresse und Kontakt',
      body:
          'Land, Straße, Hausnummer, PLZ, Stadt, Bundesland, E-Mail und Telefon mit passenden Tastaturen und Validierung.',
      inputType: 'Adresse',
      icon: Icons.home_outlined,
      status: 'Validated',
      primary: 'Adresse',
      secondary: 'Autofill',
      color: AirmiusColors.green,
    ),
    _InputFieldPattern(
      group: 'Account',
      title: 'Login und Registrierung',
      body:
          'E-Mail, Passwort, Passwort bestätigen, 2FA-Code, Recovery-Code und Fehlermeldungen mit sicherem Fokus.',
      inputType: 'E-Mail + Passwort',
      icon: Icons.lock_outline,
      status: 'Secure',
      primary: 'Loginfelder',
      secondary: '2FA',
      color: AirmiusColors.blue,
    ),
    _InputFieldPattern(
      group: 'Account',
      title: 'Profil bearbeiten',
      body:
          'Name, Bio, Sprache, Sichtbarkeit, Sportprofil und optionale Felder als klare mobile Eingabesektionen.',
      inputType: 'Profil',
      icon: Icons.account_circle_outlined,
      status: 'Profile',
      primary: 'Profil',
      secondary: 'Sichtbarkeit',
      color: AirmiusColors.green,
    ),
    _InputFieldPattern(
      group: 'Zahlung',
      title: 'IBAN und Zahlungsdaten',
      body:
          'IBAN, BIC, Kontoinhaber, Beitrag, Zahlungsrhythmus, Barzahlung und Überweisung mit Formatierung und Fehlertext.',
      inputType: 'Bank',
      icon: Icons.payments_outlined,
      status: 'Payment',
      primary: 'IBAN',
      secondary: 'SEPA',
      color: AirmiusColors.amber,
    ),
    _InputFieldPattern(
      group: 'Zahlung',
      title: 'Checkout Eingaben',
      body:
          'Rechnungsadresse, Banktransfer, Coupon, Bestellnotiz und Zahlungsstatus mit Mobile-Keyboards und Retry-Zustand.',
      inputType: 'Checkout',
      icon: Icons.receipt_long_outlined,
      status: 'Checkout',
      primary: 'Checkout',
      secondary: 'Coupon',
      color: AirmiusColors.green,
    ),
    _InputFieldPattern(
      group: 'Club Admin',
      title: 'Mitgliedschaftsformular konfigurieren',
      body:
          'Vereine wählen Pflichtfelder, optionale Felder, Uploadpflicht, Datenschutztexte und Zahlungsfelder als Admin-Form.',
      inputType: 'Builder',
      icon: Icons.format_list_bulleted_outlined,
      status: 'Admin',
      primary: 'Builder',
      secondary: 'Pflichtfelder',
      color: AirmiusColors.blue,
    ),
    _InputFieldPattern(
      group: 'Club Admin',
      title: 'Beitragsregel bearbeiten',
      body:
          'Betrag, Frequenz, Zahlungsart, Fälligkeit, Mahnung, Rabatt, Dokument und Sichtbarkeit mit validierten Eingaben.',
      inputType: 'Number + Select',
      icon: Icons.rule_folder_outlined,
      status: 'Rules',
      primary: 'Regel',
      secondary: 'Validierung',
      color: AirmiusColors.amber,
    ),
    _InputFieldPattern(
      group: 'Training',
      title: 'Training Log erfassen',
      body:
          'Dauer, Distanz, Wiederholungen, Gewicht, Puls, Notiz, RPE und Medienanhang mit numerischen Tastaturen.',
      inputType: 'Sportwerte',
      icon: Icons.fitness_center_outlined,
      status: 'Log',
      primary: 'Log',
      secondary: 'Einheiten',
      color: AirmiusColors.green,
    ),
  ];

  List<_InputFieldPattern> get _visibleFields =>
      _fields.where((field) => field.group == _fieldGroup).toList();

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      appBar: AppBar(
        backgroundColor:
            Theme.of(context).appBarTheme.backgroundColor ??
            airmiusSurfaceColor(context),
        surfaceTintColor: Colors.transparent,
        title: const AirmiusLogo(compact: true),
      ),
      body: SafeArea(
        child: PageFrame(
          title: 'Input Keyboard Accessibility Parity',
          subtitle:
              'Mobile Eingaben, Tastaturen, Validierung und Accessibility.',
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              _Hero(
                group: _fieldGroup,
                keyboard: _keyboard,
                validation: _showValidation,
                accessibility: _screenReaderHints,
              ),
              const SizedBox(height: 16),
              _ChoicePanel(
                title: 'Formularbereich',
                items: _groups,
                active: _fieldGroup,
                color: airmiusAccentColor(context),
                onChanged: (value) => setState(() => _fieldGroup = value),
              ),
              const SizedBox(height: 16),
              _ChoicePanel(
                title: 'Mobile Keyboard',
                items: _keyboards,
                active: _keyboard,
                color: Theme.of(context).colorScheme.secondary,
                onChanged: (value) => setState(() => _keyboard = value),
              ),
              const SizedBox(height: 16),
              _RulesPanel(
                showValidation: _showValidation,
                largeTouchTargets: _largeTouchTargets,
                screenReaderHints: _screenReaderHints,
                onValidation: (value) =>
                    setState(() => _showValidation = value),
                onTouch: (value) => setState(() => _largeTouchTargets = value),
                onScreenReader: (value) =>
                    setState(() => _screenReaderHints = value),
              ),
              const SizedBox(height: 16),
              _InputPreview(
                keyboard: _keyboard,
                validation: _showValidation,
                largeTouchTargets: _largeTouchTargets,
                screenReaderHints: _screenReaderHints,
              ),
              const SizedBox(height: 16),
              for (final field in _visibleFields) ...[
                _InputFieldCard(field: field, showValidation: _showValidation),
                const SizedBox(height: 12),
              ],
              if (_visibleFields.isEmpty)
                const EmptyPanel(
                  'Keine Eingabemuster für diesen Bereich sichtbar.',
                ),
              const SizedBox(height: 4),
              _Checklist(
                onOpen: () => openUiAction(
                  context,
                  title: 'Input Accessibility Parity',
                  body:
                      'Mobile Keyboards, Pflichtfelder, Masken, Validierung, Fokusreihenfolge, Touch Targets, Screenreader-Hinweise und Autofill sind als UI-Muster vorbereitet.',
                  status: 'Input UX',
                  icon: Icons.accessibility_new_outlined,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _Hero extends StatelessWidget {
  const _Hero({
    required this.group,
    required this.keyboard,
    required this.validation,
    required this.accessibility,
  });

  final String group;
  final String keyboard;
  final bool validation;
  final bool accessibility;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      gradient: true,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Eyebrow('INPUT UX'),
          const SizedBox(height: 8),
          Text(
            'Formulare müssen sich auf dem Handy leicht anfühlen.',
            style: TextStyle(
              color: airmiusTextColor(context),
              fontSize: 24,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 8),
          Text(
            'Flutter übersetzt Web-Formulare in mobile Eingaben mit passender Tastatur, Masken, Pflichtfeldern, Fokus, Autofill, Fehlertext und Accessibility.',
            style: TextStyle(
              color: airmiusMutedColor(context),
              height: 1.45,
              fontWeight: FontWeight.w700,
            ),
          ),
          const SizedBox(height: 16),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              _Metric(value: group, label: 'Bereich'),
              _Metric(value: keyboard, label: 'Keyboard'),
              _Metric(value: validation ? 'An' : 'Aus', label: 'Validierung'),
              _Metric(value: accessibility ? 'An' : 'Aus', label: 'A11y'),
            ],
          ),
        ],
      ),
    );
  }
}

class _ChoicePanel extends StatelessWidget {
  const _ChoicePanel({
    required this.title,
    required this.items,
    required this.active,
    required this.color,
    required this.onChanged,
  });

  final String title;
  final List<String> items;
  final String active;
  final Color color;
  final ValueChanged<String> onChanged;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      title: title,
      children: [
        Wrap(
          spacing: 8,
          runSpacing: 8,
          children: items
              .map(
                (item) => ChoiceChip(
                  selected: active == item,
                  label: Text(item),
                  onSelected: (_) => onChanged(item),
                  selectedColor: color.withValues(alpha: .24),
                  backgroundColor: airmiusSurfaceSoftColor(context),
                  side: BorderSide(
                    color: active == item ? color : airmiusBorderColor(context),
                  ),
                  labelStyle: TextStyle(
                    color: active == item
                        ? airmiusTextColor(context)
                        : airmiusMutedColor(context),
                    fontWeight: FontWeight.w900,
                  ),
                ),
              )
              .toList(),
        ),
      ],
    );
  }
}

class _RulesPanel extends StatelessWidget {
  const _RulesPanel({
    required this.showValidation,
    required this.largeTouchTargets,
    required this.screenReaderHints,
    required this.onValidation,
    required this.onTouch,
    required this.onScreenReader,
  });

  final bool showValidation;
  final bool largeTouchTargets;
  final bool screenReaderHints;
  final ValueChanged<bool> onValidation;
  final ValueChanged<bool> onTouch;
  final ValueChanged<bool> onScreenReader;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      title: 'Mobile Formularregeln',
      subtitle:
          'Diese Regeln machen Web-Formulare als Flutter-Eingaben ergonomisch.',
      children: [
        _SwitchLine(
          title: 'Validierung direkt am Feld anzeigen',
          value: showValidation,
          onChanged: onValidation,
        ),
        _SwitchLine(
          title: 'Große Touch Targets verwenden',
          value: largeTouchTargets,
          onChanged: onTouch,
        ),
        _SwitchLine(
          title: 'Screenreader-Hinweise vorbereiten',
          value: screenReaderHints,
          onChanged: onScreenReader,
        ),
      ],
    );
  }
}

class _InputPreview extends StatelessWidget {
  const _InputPreview({
    required this.keyboard,
    required this.validation,
    required this.largeTouchTargets,
    required this.screenReaderHints,
  });

  final String keyboard;
  final bool validation;
  final bool largeTouchTargets;
  final bool screenReaderHints;

  @override
  Widget build(BuildContext context) {
    final color = validation
        ? Theme.of(context).colorScheme.secondary
        : airmiusAccentColor(context);
    return AirmiusPanel(
      borderColor: color,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(_iconForKeyboard(keyboard), color: color, size: 30),
              const SizedBox(width: 12),
              Expanded(
                child: Text(
                  'Keyboard: $keyboard',
                  style: TextStyle(
                    color: airmiusTextColor(context),
                    fontSize: 18,
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ),
              StatusPill(largeTouchTargets ? '48dp+' : 'Kompakt', color: color),
            ],
          ),
          const SizedBox(height: 14),
          TextField(
            readOnly: true,
            decoration: InputDecoration(
              labelText: _labelForKeyboard(keyboard),
              hintText: _hintForKeyboard(keyboard),
              suffixIcon: Icon(_iconForKeyboard(keyboard), color: color),
              errorText: validation
                  ? null
                  : 'Dieses Feld braucht eine gültige Eingabe.',
              helperText: screenReaderHints
                  ? 'Screenreader: ${_labelForKeyboard(keyboard)}, Pflichtfeld, ${_hintForKeyboard(keyboard)}'
                  : null,
            ),
          ),
          const SizedBox(height: 14),
          Wrap(
            spacing: 10,
            runSpacing: 10,
            children: [
              AirmiusButton(
                label: 'Eingabe testen',
                icon: Icons.keyboard_outlined,
                onPressed: () => openUiAction(
                  context,
                  title: 'Mobile Eingabe',
                  body:
                      'Keyboard $keyboard, Touch Targets $largeTouchTargets, Validierung $validation und Screenreader $screenReaderHints als Formularmuster.',
                  status: 'Input',
                  icon: Icons.keyboard_outlined,
                ),
              ),
              AirmiusButton(
                label: 'Accessibility',
                icon: Icons.accessibility_new_outlined,
                secondary: true,
                onPressed: () => openUiAction(
                  context,
                  title: 'Accessibility Check',
                  body:
                      'Label, Hint, Error, Fokusreihenfolge, Touch Target, Kontrast, Autofill und Screenreader für $keyboard prüfen.',
                  status: 'A11y',
                  icon: Icons.accessibility_new_outlined,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _InputFieldCard extends StatelessWidget {
  const _InputFieldCard({required this.field, required this.showValidation});

  final _InputFieldPattern field;
  final bool showValidation;

  @override
  Widget build(BuildContext context) {
    final color = airmiusSemanticColor(context, field.color);
    return AirmiusPanel(
      borderColor: color.withValues(alpha: .55),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                width: 48,
                height: 48,
                decoration: BoxDecoration(
                  color: color.withValues(alpha: .14),
                  borderRadius: BorderRadius.circular(16),
                  border: Border.all(color: color.withValues(alpha: .55)),
                ),
                child: Icon(field.icon, color: color),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      field.title,
                      style: TextStyle(
                        color: airmiusTextColor(context),
                        fontSize: 17,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(height: 5),
                    Text(
                      field.inputType,
                      style: TextStyle(
                        color: airmiusAccentColor(context),
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ],
                ),
              ),
              StatusPill(field.status, color: color),
            ],
          ),
          const SizedBox(height: 12),
          Text(
            field.body,
            style: TextStyle(
              color: airmiusMutedColor(context),
              height: 1.42,
              fontWeight: FontWeight.w700,
            ),
          ),
          if (showValidation) ...[
            const SizedBox(height: 10),
            Text(
              'Validierung: Pflichtfeld, Format, Länge, API-Fehler und Offline-Draft werden am Feld sichtbar.',
              style: TextStyle(
                color: Theme.of(context).colorScheme.secondary,
                height: 1.35,
                fontWeight: FontWeight.w900,
              ),
            ),
          ],
          const SizedBox(height: 14),
          Wrap(
            spacing: 10,
            runSpacing: 10,
            children: [
              AirmiusButton(
                label: field.primary,
                icon: field.icon,
                onPressed: () => openUiAction(
                  context,
                  title: field.primary,
                  body: '${field.title}: ${field.body}',
                  status: field.status,
                  icon: field.icon,
                ),
              ),
              AirmiusButton(
                label: field.secondary,
                icon: Icons.tune_outlined,
                secondary: true,
                onPressed: () => openUiAction(
                  context,
                  title: field.secondary,
                  body:
                      'Keyboard, Maske, Fokus, Autofill, Screenreader, Pflichtfeld und API-Fehler für ${field.title}.',
                  status: 'Input Detail',
                  icon: Icons.tune_outlined,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _Checklist extends StatelessWidget {
  const _Checklist({required this.onOpen});

  final VoidCallback onOpen;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      title: 'Input-/Accessibility-Parität',
      subtitle: 'Was aus Web-Formularen mobil übersetzt wird.',
      children: [
        const _CheckLine(
          'Jedes Feld bekommt passende Tastatur, Label, Hint, Fehlertext und Fokusverhalten.',
        ),
        const _CheckLine(
          'Pflichtfelder, optionale Felder, Uploads und API-Fehler bleiben direkt im Formular sichtbar.',
        ),
        const _CheckLine(
          'Touch Targets, Kontrast, Screenreader und Autofill sind als UI-Regeln vorbereitet.',
        ),
        const _CheckLine(
          'Mitgliedsantrag, Zahlung, Account, Club Admin und Training nutzen gemeinsame Eingabemuster.',
        ),
        const SizedBox(height: 12),
        AirmiusButton(
          label: 'Input-Parität markieren',
          icon: Icons.fact_check_outlined,
          onPressed: onOpen,
        ),
      ],
    );
  }
}

class _SwitchLine extends StatelessWidget {
  const _SwitchLine({
    required this.title,
    required this.value,
    required this.onChanged,
  });

  final String title;
  final bool value;
  final ValueChanged<bool> onChanged;

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(top: 10),
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
      decoration: BoxDecoration(
        color: airmiusInputColor(context),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: airmiusBorderColor(context)),
      ),
      child: Row(
        children: [
          Expanded(
            child: Text(
              title,
              style: TextStyle(
                color: airmiusTextColor(context),
                fontWeight: FontWeight.w900,
              ),
            ),
          ),
          Switch(
            value: value,
            activeThumbColor: Theme.of(context).colorScheme.secondary,
            onChanged: onChanged,
          ),
        ],
      ),
    );
  }
}

class _CheckLine extends StatelessWidget {
  const _CheckLine(this.text);

  final String text;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(top: 8),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(
            Icons.check_circle_outline,
            color: Theme.of(context).colorScheme.secondary,
            size: 19,
          ),
          const SizedBox(width: 8),
          Expanded(
            child: Text(
              text,
              style: TextStyle(
                color: airmiusMutedColor(context),
                fontWeight: FontWeight.w700,
                height: 1.35,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _Metric extends StatelessWidget {
  const _Metric({required this.value, required this.label});

  final String value;
  final String label;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
      decoration: BoxDecoration(
        color: airmiusSurfaceColor(context).withValues(alpha: .55),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: airmiusBorderColor(context)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            value,
            style: TextStyle(
              color: airmiusTextColor(context),
              fontSize: 18,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 2),
          Text(
            label,
            style: TextStyle(
              color: airmiusMutedColor(context),
              fontWeight: FontWeight.w700,
            ),
          ),
        ],
      ),
    );
  }
}

class _InputFieldPattern {
  const _InputFieldPattern({
    required this.group,
    required this.title,
    required this.body,
    required this.inputType,
    required this.icon,
    required this.status,
    required this.primary,
    required this.secondary,
    required this.color,
  });

  final String group;
  final String title;
  final String body;
  final String inputType;
  final IconData icon;
  final String status;
  final String primary;
  final String secondary;
  final Color color;
}

IconData _iconForKeyboard(String keyboard) {
  if (keyboard == 'E-Mail') return Icons.mail_outline;
  if (keyboard == 'Telefon') return Icons.phone_outlined;
  if (keyboard == 'Datum') return Icons.calendar_today_outlined;
  if (keyboard == 'Nummer') return Icons.pin_outlined;
  if (keyboard == 'Passwort') return Icons.password_outlined;
  return Icons.keyboard_outlined;
}

String _labelForKeyboard(String keyboard) {
  if (keyboard == 'E-Mail') return 'E-Mail Adresse';
  if (keyboard == 'Telefon') return 'Telefonnummer';
  if (keyboard == 'Datum') return 'Geburtsdatum';
  if (keyboard == 'Nummer') return 'Betrag oder Zahl';
  if (keyboard == 'Passwort') return 'Passwort';
  return 'Textfeld';
}

String _hintForKeyboard(String keyboard) {
  if (keyboard == 'E-Mail') return 'name@example.com';
  if (keyboard == 'Telefon') return '+49 170 0000000';
  if (keyboard == 'Datum') return 'TT.MM.JJJJ';
  if (keyboard == 'Nummer') return '0,00';
  if (keyboard == 'Passwort') return 'Mindestens 8 Zeichen';
  return 'Eingabe';
}
