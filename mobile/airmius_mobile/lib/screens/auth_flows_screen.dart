import 'package:flutter/material.dart';
import 'account_operations_screen.dart';

import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'ui_action_result_screen.dart';
import 'account_operations_screen.dart';

class AuthFlowsScreen extends StatefulWidget {
  const AuthFlowsScreen({super.key});

  @override
  State<AuthFlowsScreen> createState() => _AuthFlowsScreenState();
}

class _AuthFlowsScreenState extends State<AuthFlowsScreen> {
  String _flow = 'Registrieren';
  bool _terms = false;
  bool _guardian = false;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
        floatingActionButton: FloatingActionButton.extended(backgroundColor: const Color(0xFF1D5FA8), foregroundColor: Colors.white, icon: const Icon(Icons.manage_accounts_outlined), label: const Text('Konto Ops', style: TextStyle(fontWeight: FontWeight.w900)), onPressed: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => AccountOperationsScreen(initialTab: 'Auth')))),
        
      appBar: AppBar(
        backgroundColor: AirmiusColors.header,
        surfaceTintColor: Colors.transparent,
        title: const Text('Konto & Sicherheit', style: TextStyle(fontWeight: FontWeight.w900)),
      ),
      body: PageFrame(
        title: 'Konto & Sicherheit',
        subtitle: 'Registrierung, Passwort, 2FA, E-Mail und Profilabschluss',
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            AirmiusPanel(
              gradient: true,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Eyebrow('Auth'),
                  const SizedBox(height: 8),
                  const Text('Alle wichtigen Auth-Seiten der Web-App als native UI vorbereitet.', style: TextStyle(color: AirmiusColors.muted, height: 1.35)),
                  const SizedBox(height: 14),
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: [
                      for (final flow in const ['Registrieren', 'Social', 'Passwort', '2FA', 'E-Mail', 'Profil', 'Gesperrt', 'Loeschen'])
                        ChoiceChip(
                          selected: _flow == flow,
                          label: Text(flow),
                          onSelected: (_) => setState(() => _flow = flow),
                          selectedColor: AirmiusColors.blue.withValues(alpha: 0.22),
                          backgroundColor: AirmiusColors.cardSoft,
                          side: BorderSide(color: _flow == flow ? AirmiusColors.blue : AirmiusColors.border),
                          labelStyle: TextStyle(color: _flow == flow ? AirmiusColors.blue : AirmiusColors.muted, fontWeight: FontWeight.w900),
                        ),
                    ],
                  ),
                ],
              ),
            ),
            const SizedBox(height: 14),
            _content(),
          ],
        ),
      ),
    );
  }

  Widget _content() {
    return switch (_flow) {
      'Registrieren' => _register(),
      'Social' => _socialLogin(),
      'Passwort' => _password(),
      '2FA' => _twoFactor(),
      'E-Mail' => _emailVerify(),
      'Profil' => _profileCompletion(),
      'Gesperrt' => _suspended(),
      'Loeschen' => _deleteAccount(),
      _ => _register(),
    };
  }

  Widget _register() {
    return AirmiusPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const Eyebrow('Registrieren'),
          const SizedBox(height: 12),
          const AirmiusTextField(label: 'Name', hint: 'Vor- und Nachname', icon: Icons.person_outline),
          const SizedBox(height: 12),
          const AirmiusTextField(label: 'E-Mail', hint: 'konto@example.com', icon: Icons.mail_outline),
          const SizedBox(height: 12),
          const AirmiusTextField(label: 'Passwort', hint: 'Sicheres Passwort', icon: Icons.lock_outline),
          const SizedBox(height: 12),
          CheckboxListTile(
            value: _terms,
            onChanged: (value) => setState(() => _terms = value ?? false),
            activeColor: AirmiusColors.blue,
            contentPadding: EdgeInsets.zero,
            title: const Text('AGB und Datenschutz akzeptieren', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w800)),
          ),
          CheckboxListTile(
            value: _guardian,
            onChanged: (value) => setState(() => _guardian = value ?? false),
            activeColor: AirmiusColors.blue,
            contentPadding: EdgeInsets.zero,
            title: const Text('Minderjaehrig / Elternzustimmung benoetigt', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w800)),
          ),
          const SizedBox(height: 12),
          Wrap(
            spacing: 10,
            runSpacing: 10,
            children: const [
              _AuthAction(label: 'Mit Google fortfahren', icon: Icons.account_circle_outlined),
              _AuthAction(label: 'Mit Apple fortfahren', icon: Icons.phone_iphone_outlined),
            ],
          ),
          const SizedBox(height: 12),
          AirmiusButton(label: 'Konto erstellen', icon: Icons.person_add_alt, onPressed: _terms ? () => Navigator.push(context, MaterialPageRoute(builder: (_) => UiActionResultScreen(title: 'Konto erstellen', body: 'Registrierung, Datenschutz, E-Mail-Verifizierung und optional Guardian-Flow vorbereiten.', status: 'Register', icon: Icons.person_add_alt))) : null),
        ],
      ),
    );
  }

  Widget _socialLogin() {
    return const AirmiusPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Eyebrow('Social Login'),
          SizedBox(height: 8),
          Text('Die Web-App besitzt OAuth-Redirects. In der Mobile-App wird daraus ein nativer Provider-Flow mit Account-Linking, Datenschutz und Fehlerstatus.', style: TextStyle(color: AirmiusColors.muted, height: 1.35)),
          SizedBox(height: 12),
          _AuthStatusLine(icon: Icons.account_circle_outlined, title: 'Google', body: 'OAuth, E-Mail-Abgleich und Profilanlage.', status: 'Provider'),
          _AuthStatusLine(icon: Icons.phone_iphone_outlined, title: 'Apple', body: 'Sign in with Apple, Private Relay und Account-Linking.', status: 'iOS'),
          _AuthStatusLine(icon: Icons.link_outlined, title: 'Konto verknuepfen', body: 'Bestehende Airmius-Konten mit Provider verbinden.', status: 'Linking'),
          SizedBox(height: 12),
          _AuthAction(label: 'Google Login starten', icon: Icons.account_circle_outlined),
          SizedBox(height: 10),
          _AuthAction(label: 'Apple Login starten', icon: Icons.phone_iphone_outlined),
        ],
      ),
    );
  }

  Widget _password() {
    return const AirmiusPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Eyebrow('Passwort vergessen'),
          SizedBox(height: 12),
          AirmiusTextField(label: 'E-Mail', hint: 'konto@example.com', icon: Icons.mail_outline),
          SizedBox(height: 12),
          _AuthAction(label: 'Reset-Link senden', icon: Icons.mark_email_read_outlined),
          SizedBox(height: 16),
          Eyebrow('Passwort zuruecksetzen'),
          SizedBox(height: 12),
          AirmiusTextField(label: 'Code / Token', hint: 'Aus der E-Mail'),
          SizedBox(height: 12),
          AirmiusTextField(label: 'Neues Passwort', hint: 'Neues Passwort', icon: Icons.lock_outline),
        ],
      ),
    );
  }

  Widget _twoFactor() {
    return const AirmiusPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Eyebrow('Zwei-Faktor-Authentifizierung'),
          SizedBox(height: 8),
          Text('Code aus Authenticator-App oder Recovery-Code eingeben.', style: TextStyle(color: AirmiusColors.muted, height: 1.35)),
          SizedBox(height: 12),
          AirmiusTextField(label: '2FA Code', hint: '123456', icon: Icons.password_outlined),
          SizedBox(height: 12),
          AirmiusTextField(label: 'Recovery Code', hint: 'Optional'),
          SizedBox(height: 12),
          _AuthAction(label: 'Verifizieren', icon: Icons.verified_user_outlined),
        ],
      ),
    );
  }

  Widget _emailVerify() {
    return const AirmiusPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Eyebrow('E-Mail verifizieren'),
          SizedBox(height: 8),
          Text('Bitte bestaetige deine E-Mail-Adresse. Bei Bedarf kann eine neue Mail versendet werden.', style: TextStyle(color: AirmiusColors.muted, height: 1.35)),
          SizedBox(height: 12),
          _AuthStatusLine(icon: Icons.mail_outline, title: 'zbb.bop.it@gmail.com', body: 'Wartet auf Bestaetigung', status: 'Offen'),
          SizedBox(height: 12),
          _AuthAction(label: 'Verifizierungslink erneut senden', icon: Icons.send_outlined),
        ],
      ),
    );
  }

  Widget _profileCompletion() {
    return const AirmiusPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Eyebrow('Profil vervollstaendigen'),
          SizedBox(height: 12),
          _AuthStatusLine(icon: Icons.person_outline, title: 'Personendaten', body: 'Name, Geburtsdatum und Profilbild', status: '80%'),
          _AuthStatusLine(icon: Icons.directions_run, title: 'Sportprofil', body: 'Sportarten, Level, Ziele und Skills', status: 'Offen'),
          _AuthStatusLine(icon: Icons.privacy_tip_outlined, title: 'Sichtbarkeit', body: 'Profil, Vereine und Kontakte', status: 'Pruefen'),
          SizedBox(height: 12),
          _AuthAction(label: 'Profil abschliessen', icon: Icons.task_alt_outlined),
        ],
      ),
    );
  }

  Widget _suspended() {
    return const AirmiusPanel(
      borderColor: AirmiusColors.red,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Eyebrow('Konto eingeschraenkt'),
          SizedBox(height: 8),
          Text('Der Zugriff kann durch Moderation, fehlende Verifizierung oder Sicherheitsregeln eingeschraenkt sein.', style: TextStyle(color: AirmiusColors.muted, height: 1.35)),
          SizedBox(height: 12),
          _AuthStatusLine(icon: Icons.report_outlined, title: 'Status', body: 'Support kann Details pruefen.', status: 'Gesperrt'),
          SizedBox(height: 12),
          _AuthAction(label: 'Support kontaktieren', icon: Icons.support_agent_outlined),
        ],
      ),
    );
  }

  Widget _deleteAccount() {
    return const AirmiusPanel(
      borderColor: AirmiusColors.red,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Eyebrow('Konto loeschen'),
          SizedBox(height: 8),
          Text('Die Web-App sendet zuerst einen Loeschcode. Die native App zeigt Warnung, Code-Eingabe, Export-Hinweis und finale Bestaetigung.', style: TextStyle(color: AirmiusColors.muted, height: 1.35)),
          SizedBox(height: 12),
          AirmiusTextField(label: 'Loeschcode', hint: 'Code aus der E-Mail', icon: Icons.password_outlined),
          SizedBox(height: 12),
          _AuthStatusLine(icon: Icons.download_outlined, title: 'Datenexport', body: 'Profil, Mitgliedschaften, Zahlungen und Medien vor Loeschung exportieren.', status: 'Empfohlen'),
          _AuthStatusLine(icon: Icons.warning_amber_outlined, title: 'Endgueltige Loeschung', body: 'Konto wird erst nach API-Bestaetigung final geloescht.', status: 'Kritisch'),
          SizedBox(height: 12),
          _AuthAction(label: 'Loeschcode senden', icon: Icons.mark_email_read_outlined),
          SizedBox(height: 10),
          _AuthAction(label: 'Konto endgueltig loeschen', icon: Icons.delete_forever_outlined),
        ],
      ),
    );
  }
}

class _AuthAction extends StatelessWidget {
  const _AuthAction({required this.label, required this.icon});

  final String label;
  final IconData icon;

  @override
  Widget build(BuildContext context) {
    return AirmiusButton(label: label, icon: icon, onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => UiActionResultScreen(title: label, body: 'Auth-Aktion fuer $label vorbereiten und spaeter mit Laravel Auth/API verbinden.', status: 'Auth', icon: icon))));
  }
}

class _AuthStatusLine extends StatelessWidget {
  const _AuthStatusLine({required this.icon, required this.title, required this.body, required this.status});

  final IconData icon;
  final String title;
  final String body;
  final String status;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(top: 12),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, color: AirmiusColors.blue),
          const SizedBox(width: 12),
          Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text(title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), const SizedBox(height: 3), Text(body, style: const TextStyle(color: AirmiusColors.muted, height: 1.3))])),
          StatusPill(status),
        ],
      ),
    );
  }
}

