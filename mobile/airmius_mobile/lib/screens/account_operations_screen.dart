import 'package:flutter/material.dart';

import '../core/api_contract.dart';
import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

class AccountOperationsScreen extends StatefulWidget {
  const AccountOperationsScreen({super.key, this.initialTab = 'Auth'});

  final String initialTab;

  @override
  State<AccountOperationsScreen> createState() => _AccountOperationsScreenState();
}

class _AccountOperationsScreenState extends State<AccountOperationsScreen> {
  String _tab = 'Auth';
  bool _rememberDevice = true;
  bool _twoFactor = false;
  bool _exportBeforeDelete = true;

  @override
  void initState() {
    super.initState();
    if (_tabs.contains(widget.initialTab)) _tab = widget.initialTab;
  }

  @override
  Widget build(BuildContext context) {
    final items = _tab == 'Alle' ? _operations : _operations.where((item) => item.tab == _tab).toList();
    return Scaffold(
      backgroundColor: AirmiusColors.bg,
      appBar: AppBar(backgroundColor: AirmiusColors.header, surfaceTintColor: Colors.transparent, title: const Text('Konto Ops', style: TextStyle(fontWeight: FontWeight.w900))),
      body: PageFrame(
        title: 'Konto Ops',
        subtitle: 'Login, Registrierung, OAuth, Passwort, 2FA, Sessions, Export und Löschung',
        child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          AirmiusPanel(borderColor: AirmiusColors.blue.withValues(alpha: .42), child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            const Eyebrow('Konto & Sicherheit'),
            const SizedBox(height: 8),
            const Text('Die Web-Auth-Flows werden mobil nicht als WebView gedacht, sondern als native Schritte mit klaren Sicherheits-, Datenschutz- und Fehlerzustaenden.', style: TextStyle(color: AirmiusColors.muted, height: 1.4)),
            const SizedBox(height: 12),
            SwitchListTile(value: _rememberDevice, onChanged: (value) => setState(() => _rememberDevice = value), activeThumbColor: AirmiusColors.blue, contentPadding: EdgeInsets.zero, title: const Text('Gerät merken', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), subtitle: const Text('Session, Token und biometrische Freigabe später koppeln.', style: TextStyle(color: AirmiusColors.muted))),
            SwitchListTile(value: _twoFactor, onChanged: (value) => setState(() => _twoFactor = value), activeThumbColor: AirmiusColors.green, contentPadding: EdgeInsets.zero, title: const Text('2FA aktiv', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), subtitle: const Text('Authenticator-Code oder Recovery-Code für sensible Aktionen.', style: TextStyle(color: AirmiusColors.muted))),
            SwitchListTile(value: _exportBeforeDelete, onChanged: (value) => setState(() => _exportBeforeDelete = value), activeThumbColor: AirmiusColors.amber, contentPadding: EdgeInsets.zero, title: const Text('Export vor Löschung', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), subtitle: const Text('DSGVO-Datenexport vor finaler Kontolöschung anbieten.', style: TextStyle(color: AirmiusColors.muted))),
            const SizedBox(height: 10),
            Wrap(spacing: 8, runSpacing: 8, children: [for (final tab in _tabs) ChoiceChip(label: Text(tab), selected: _tab == tab, onSelected: (_) => setState(() => _tab = tab), selectedColor: AirmiusColors.blue.withValues(alpha: .22), backgroundColor: AirmiusColors.panelSoft, side: BorderSide(color: _tab == tab ? AirmiusColors.blue : AirmiusColors.border), labelStyle: TextStyle(color: _tab == tab ? AirmiusColors.text : AirmiusColors.muted, fontWeight: FontWeight.w900))]),
          ])),
          const SizedBox(height: 16),
          Row(children: const [Expanded(child: MetricCard(value: '82%', label: 'Profil')), SizedBox(width: 10), Expanded(child: MetricCard(value: '2FA', label: 'Sicherheit')), SizedBox(width: 10), Expanded(child: MetricCard(value: 'DSGVO', label: 'Export'))]),
          const SizedBox(height: 16),
          for (final item in items) ...[_AccountOperationCard(item: item), const SizedBox(height: 12)],
        ]),
      ),
    );
  }
}

class _AccountOperationCard extends StatelessWidget {
  const _AccountOperationCard({required this.item});
  final _AccountOperation item;

  @override
  Widget build(BuildContext context) => AirmiusPanel(borderColor: item.danger ? AirmiusColors.red.withValues(alpha: .45) : AirmiusColors.border, child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
    Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
      Container(width: 48, height: 48, decoration: BoxDecoration(color: item.color.withValues(alpha: .13), borderRadius: BorderRadius.circular(16), border: Border.all(color: item.color.withValues(alpha: .45))), child: Icon(item.icon, color: item.color)),
      const SizedBox(width: 12),
      Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text(item.title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900, fontSize: 16)), const SizedBox(height: 5), Text(item.body, style: const TextStyle(color: AirmiusColors.muted, height: 1.35))])),
      StatusPill(item.tab, color: item.color),
    ]),
    const SizedBox(height: 12),
    Container(width: double.infinity, padding: const EdgeInsets.all(12), decoration: BoxDecoration(color: AirmiusColors.bg, borderRadius: BorderRadius.circular(14), border: Border.all(color: AirmiusColors.border)), child: Text('${item.method} ${item.endpoint}', style: const TextStyle(color: AirmiusColors.green, fontSize: 12, fontWeight: FontWeight.w900))),
    const SizedBox(height: 12),
    Wrap(spacing: 8, runSpacing: 8, children: [
      AirmiusButton(label: item.action, icon: item.icon, danger: item.danger, onPressed: () => openUiAction(context, title: item.title, body: '${item.body}\n\nEndpoint: ${item.method} ${item.endpoint}', status: item.tab, icon: item.icon)),
      AirmiusButton(label: 'Security Log', icon: Icons.history_outlined, secondary: true, onPressed: () => openUiAction(context, title: '${item.title} Security Log', body: 'Session, Device, IP-Hinweis, Tokenstatus, Fehlercode, User und Audit anzeigen.', status: 'Audit', icon: Icons.history_outlined)),
    ]),
  ]));
}

class _AccountOperation {
  const _AccountOperation({required this.tab, required this.title, required this.body, required this.method, required this.endpoint, required this.icon, required this.action, required this.color, this.danger = false});
  final String tab;
  final String title;
  final String body;
  final String method;
  final String endpoint;
  final IconData icon;
  final String action;
  final Color color;
  final bool danger;
}

const _tabs = ['Auth', 'OAuth', 'Passwort', '2FA', 'Profil', 'Konto', 'Alle'];

final _operations = <_AccountOperation>[
  _AccountOperation(tab: 'Auth', title: 'Login', body: 'E-Mail, Passwort, Device-Meta und Token/sessionbasierten Login starten.', method: 'POST', endpoint: ApiContract.authLogin, icon: Icons.login_outlined, action: 'Login', color: AirmiusColors.green),
  _AccountOperation(tab: 'Auth', title: 'Registrieren', body: 'Konto mit Datenschutz, AGB, E-Mail-Verifizierung und Profilstart anlegen.', method: 'POST', endpoint: ApiContract.authRegister, icon: Icons.person_add_alt_1_outlined, action: 'Registrieren', color: AirmiusColors.blue),
  _AccountOperation(tab: 'Auth', title: 'Logout', body: 'Mobile Session beenden und lokale Tokens löschen.', method: 'POST', endpoint: ApiContract.authLogout, icon: Icons.logout_outlined, action: 'Logout', color: AirmiusColors.amber),
  _AccountOperation(tab: 'OAuth', title: 'Google Redirect', body: 'OAuth-Provider nativ starten und Web-Redirect-Konzept mobil kapseln.', method: 'GET', endpoint: ApiContract.authProviderRedirect('google'), icon: Icons.account_circle_outlined, action: 'Google', color: AirmiusColors.blue),
  _AccountOperation(tab: 'OAuth', title: 'Microsoft Redirect', body: 'Microsoft OAuth mit Account-Linking und Fehlerstatus starten.', method: 'GET', endpoint: ApiContract.authProviderRedirect('microsoft'), icon: Icons.work_outline, action: 'Microsoft', color: AirmiusColors.blue),
  _AccountOperation(tab: 'OAuth', title: 'Provider Callback', body: 'OAuth Callback aus Deep Link auswerten und Konto verknuepfen.', method: 'GET', endpoint: ApiContract.authProviderCallback('google'), icon: Icons.link_outlined, action: 'Callback', color: AirmiusColors.green),
  _AccountOperation(tab: 'Passwort', title: 'Reset-Link senden', body: 'Passwort vergessen, E-Mail validieren und Reset-Link senden.', method: 'POST', endpoint: ApiContract.authForgotPassword, icon: Icons.mark_email_read_outlined, action: 'Reset senden', color: AirmiusColors.amber),
  _AccountOperation(tab: 'Passwort', title: 'Passwort zurücksetzen', body: 'Token, E-Mail und neues Passwort verarbeiten.', method: 'POST', endpoint: ApiContract.authResetPassword, icon: Icons.lock_reset_outlined, action: 'Zurücksetzen', color: AirmiusColors.green),
  _AccountOperation(tab: 'Passwort', title: 'Passwort bestätigen', body: 'Sensible Aktionen mit Passwortbestätigung absichern.', method: 'POST', endpoint: ApiContract.authConfirmPassword, icon: Icons.password_outlined, action: 'Bestätigen', color: AirmiusColors.blue),
  _AccountOperation(tab: '2FA', title: '2FA aktivieren', body: 'Authenticator einrichten, QR/Secret zeigen und Recovery-Codes erzeugen.', method: 'POST', endpoint: ApiContract.authTwoFactor, icon: Icons.verified_user_outlined, action: 'Aktivieren', color: AirmiusColors.green),
  _AccountOperation(tab: '2FA', title: '2FA deaktivieren', body: 'Zwei-Faktor-Anmeldung nach Passwortbestätigung deaktivieren.', method: 'DELETE', endpoint: ApiContract.authTwoFactor, icon: Icons.gpp_bad_outlined, action: 'Deaktivieren', color: AirmiusColors.red, danger: true),
  _AccountOperation(tab: 'Profil', title: 'E-Mail-Verifizierung senden', body: 'Verifizierungslink erneut senden und UI optimistisch aktualisieren.', method: 'POST', endpoint: ApiContract.authEmailVerification, icon: Icons.send_outlined, action: 'Senden', color: AirmiusColors.blue),
  _AccountOperation(tab: 'Profil', title: 'Profil speichern', body: 'Name, Foto, Bio, Sportprofil und Sichtbarkeit speichern.', method: 'PUT', endpoint: ApiContract.accountProfile, icon: Icons.manage_accounts_outlined, action: 'Speichern', color: AirmiusColors.green),
  _AccountOperation(tab: 'Konto', title: 'Datenexport anfordern', body: 'Profil, Mitgliedschaften, Zahlungen, Medien und Audit-Daten exportieren.', method: 'POST', endpoint: ApiContract.accountExport, icon: Icons.download_outlined, action: 'Export', color: AirmiusColors.blue),
  _AccountOperation(tab: 'Konto', title: 'Konto löschen', body: 'Warnung, Löschcode, Frist, Exporthinweis und finale Bestätigung abbilden.', method: 'DELETE', endpoint: ApiContract.accountDelete, icon: Icons.delete_forever_outlined, action: 'Löschen', color: AirmiusColors.red, danger: true),
];
