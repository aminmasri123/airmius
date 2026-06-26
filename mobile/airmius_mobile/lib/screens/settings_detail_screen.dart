import 'package:flutter/material.dart';
import 'account_operations_screen.dart';

import '../core/airmius_theme.dart';
import '../core/airmius_api_client.dart';
import '../core/airmius_services_scope.dart';
import '../widgets/airmius_widgets.dart';
import 'ui_action_result_screen.dart';

class SettingsDetailScreen extends StatefulWidget {
  const SettingsDetailScreen({super.key, required this.section, required this.status});

  final String section;
  final String status;

  @override
  State<SettingsDetailScreen> createState() => _SettingsDetailScreenState();
}

class _SettingsDetailScreenState extends State<SettingsDetailScreen> {
  String _language = 'Deutsch';
  String _visibility = 'Verein';
  bool _push = true;
  bool _email = true;
  bool _twoFactor = true;
  bool _biometric = false;
  bool _dataExport = false;
  bool _deleteRequested = false;
  bool _deletionCodeRequested = false;
  bool _deletingAccount = false;
  final _deletePasswordController = TextEditingController();
  final _deleteCodeController = TextEditingController();

  @override
  void dispose() {
    _deletePasswordController.dispose();
    _deleteCodeController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
        floatingActionButton: FloatingActionButton.extended(backgroundColor: const Color(0xFF1D5FA8), foregroundColor: Colors.white, icon: const Icon(Icons.manage_accounts_outlined), label: const Text('Security Ops', style: TextStyle(fontWeight: FontWeight.w900)), onPressed: () => Navigator.of(context).push(MaterialPageRoute(builder: (_) => AccountOperationsScreen(initialTab: '2FA')))),
        
      appBar: AppBar(backgroundColor: AirmiusColors.header, surfaceTintColor: Colors.transparent, title: const Text('Einstellung', style: TextStyle(fontWeight: FontWeight.w900))),
      body: PageFrame(
        title: widget.section,
        subtitle: 'Mobile Konto-, Datenschutz-, Push- und Sicherheitsoptionen',
        trailing: StatusPill(widget.status),
        child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          AirmiusPanel(gradient: true, child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            const Eyebrow('Konto'),
            const SizedBox(height: 12),
            const AirmiusTextField(label: 'Anzeigename', hint: 'ZBB Konto', icon: Icons.person_outline),
            const SizedBox(height: 10),
            const AirmiusTextField(label: 'Sportprofil', hint: 'Laufen, Tennis, Fitness', icon: Icons.sports_outlined),
            const SizedBox(height: 10),
            DropdownButtonFormField<String>(
              value: _language,
              dropdownColor: AirmiusColors.card,
              decoration: _fieldDecoration('Sprache'),
              items: const ['Deutsch', 'English', 'Francais', '???????'].map((item) => DropdownMenuItem(value: item, child: Text(item))).toList(),
              onChanged: (value) => setState(() => _language = value ?? _language),
            ),
          ])),
          const SizedBox(height: 14),
          Row(children: const [Expanded(child: MetricCard(value: '82%', label: 'Profil')), SizedBox(width: 10), Expanded(child: MetricCard(value: '2FA', label: 'Sicher')), SizedBox(width: 10), Expanded(child: MetricCard(value: 'DE', label: 'Sprache'))]),
          const SizedBox(height: 14),
          AirmiusPanel(child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            const Eyebrow('Sichtbarkeit'),
            const SizedBox(height: 10),
            Wrap(spacing: 8, runSpacing: 8, children: [
              for (final item in const ['Privat', 'Verein', 'Oeffentlich'])
                ChoiceChip(
                  selected: _visibility == item,
                  label: Text(item),
                  onSelected: (_) => setState(() => _visibility = item),
                  selectedColor: AirmiusColors.blue.withValues(alpha: 0.22),
                  backgroundColor: AirmiusColors.cardSoft,
                  side: BorderSide(color: _visibility == item ? AirmiusColors.blue : AirmiusColors.border),
                  labelStyle: TextStyle(color: _visibility == item ? AirmiusColors.blue : AirmiusColors.muted, fontWeight: FontWeight.w900),
                ),
            ]),
            const SizedBox(height: 8),
            SwitchListTile(value: _dataExport, onChanged: (value) => setState(() => _dataExport = value), activeColor: AirmiusColors.blue, contentPadding: EdgeInsets.zero, title: const Text('Datenexport vorbereiten', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), subtitle: const Text('Profil, Mitgliedschaften, Zahlungen und Medien exportieren.', style: TextStyle(color: AirmiusColors.muted))),
          ])),
          const SizedBox(height: 14),
          AirmiusPanel(child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            const Eyebrow('Benachrichtigungen'),
            const SizedBox(height: 8),
            SwitchListTile(value: _push, onChanged: (value) => setState(() => _push = value), activeColor: AirmiusColors.blue, contentPadding: EdgeInsets.zero, title: const Text('Push aktiv', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), subtitle: const Text('Events, Chat, Zahlungen und Mitgliedsanfragen.', style: TextStyle(color: AirmiusColors.muted))),
            SwitchListTile(value: _email, onChanged: (value) => setState(() => _email = value), activeColor: AirmiusColors.blue, contentPadding: EdgeInsets.zero, title: const Text('E-Mail aktiv', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), subtitle: const Text('Wichtige Konto- und Vereinsinformationen per Mail.', style: TextStyle(color: AirmiusColors.muted))),
          ])),
          const SizedBox(height: 14),
          AirmiusPanel(borderColor: AirmiusColors.green.withValues(alpha: 0.45), child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            const Eyebrow('Sicherheit'),
            const SizedBox(height: 8),
            SwitchListTile(value: _twoFactor, onChanged: (value) => setState(() => _twoFactor = value), activeColor: AirmiusColors.blue, contentPadding: EdgeInsets.zero, title: const Text('Zwei-Faktor-Anmeldung', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), subtitle: const Text('Code bei sensiblen Logins und Adminrechten verlangen.', style: TextStyle(color: AirmiusColors.muted))),
            SwitchListTile(value: _biometric, onChanged: (value) => setState(() => _biometric = value), activeColor: AirmiusColors.blue, contentPadding: EdgeInsets.zero, title: const Text('Biometrische Entsperrung', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), subtitle: const Text('Native App-Entsperrung per Fingerabdruck oder Face ID vorbereiten.', style: TextStyle(color: AirmiusColors.muted))),
          ])),
          const SizedBox(height: 14),
          AirmiusPanel(borderColor: AirmiusColors.red.withValues(alpha: 0.45), child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
            const Eyebrow('Kontoaktion'),
            const SizedBox(height: 8),
            SwitchListTile(value: _deleteRequested, onChanged: (value) => setState(() => _deleteRequested = value), activeColor: AirmiusColors.red, contentPadding: EdgeInsets.zero, title: const Text('Kontoloeschung anfragen', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)), subtitle: const Text('Erst nach Warnung, Frist und API-Bestaetigung final.', style: TextStyle(color: AirmiusColors.muted))),
            const SizedBox(height: 10),
            if (_deleteRequested) ...[
              const Text('Fordere zuerst einen Loeschcode an. Bei Passwort-Login gib dein Passwort ein, bei Social Login deine Konto-E-Mail.', style: TextStyle(color: AirmiusColors.muted, height: 1.35, fontWeight: FontWeight.w700)),
              const SizedBox(height: 10),
              AirmiusTextField(label: 'Passwort oder E-Mail', hint: 'Zur Identitaetsbestaetigung', icon: Icons.lock_outline, controller: _deletePasswordController, obscureText: true),
              const SizedBox(height: 10),
              AirmiusButton(label: _deletionCodeRequested ? 'Loeschcode erneut senden' : 'Loeschcode senden', icon: Icons.mark_email_read_outlined, danger: true, onPressed: _deletingAccount ? null : _requestDeletionCode),
              if (_deletionCodeRequested) ...[
                const SizedBox(height: 12),
                AirmiusTextField(label: 'Loeschcode', hint: 'Code aus der E-Mail', icon: Icons.password_outlined, controller: _deleteCodeController),
                const SizedBox(height: 10),
                AirmiusButton(label: _deletingAccount ? 'Konto wird geloescht...' : 'Konto endgueltig loeschen', icon: Icons.delete_forever_outlined, danger: true, onPressed: _deletingAccount ? null : _confirmDeleteAccount),
              ],
            ],
          ])),
          const SizedBox(height: 14),
          AirmiusButton(label: 'Einstellungen speichern', icon: Icons.save_outlined, onPressed: () => openUiAction(context, title: 'Einstellungen speichern', body: 'Diese Aktion ist in der Mobile-App vorbereitet und wird spaeter ueber die Laravel-API synchronisiert.', status: 'UI bereit', icon: Icons.save_outlined)),
        ]),
      ),
    );
  }

  Future<void> _requestDeletionCode() async {
    final password = _deletePasswordController.text.trim();
    if (password.isEmpty) {
      _toast('Bitte Passwort oder Konto-E-Mail eingeben.');
      return;
    }

    setState(() => _deletingAccount = true);
    try {
      final services = AirmiusServicesScope.of(context);
      await services.clientForSession(services.authState.session).requestAccountDeletionCode(password: password);
      if (!mounted) return;
      setState(() {
        _deletionCodeRequested = true;
        _deletingAccount = false;
      });
      _toast('Loeschcode wurde per E-Mail gesendet.');
    } catch (error) {
      if (!mounted) return;
      setState(() => _deletingAccount = false);
      _toast(_errorMessage(error));
    }
  }

  Future<void> _confirmDeleteAccount() async {
    final code = _deleteCodeController.text.trim();
    if (code.isEmpty) {
      _toast('Bitte den Loeschcode eingeben.');
      return;
    }

    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        backgroundColor: AirmiusColors.card,
        title: const Text('Konto endgueltig loeschen?', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
        content: const Text('Diese Aktion loescht dein Konto dauerhaft. Danach wirst du aus der Flutter-App abgemeldet.', style: TextStyle(color: AirmiusColors.muted, height: 1.35)),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context, false), child: const Text('Abbrechen')),
          FilledButton(style: FilledButton.styleFrom(backgroundColor: AirmiusColors.red), onPressed: () => Navigator.pop(context, true), child: const Text('Loeschen')),
        ],
      ),
    );
    if (confirmed != true) return;

    setState(() => _deletingAccount = true);
    try {
      final services = AirmiusServicesScope.of(context);
      await services.clientForSession(services.authState.session).deleteAccount(code: code);
      await services.authState.signOut();
      if (!mounted) return;
      Navigator.of(context).popUntil((route) => route.isFirst);
    } catch (error) {
      if (!mounted) return;
      setState(() => _deletingAccount = false);
      _toast(_errorMessage(error));
    }
  }

  String _errorMessage(Object error) {
    if (error is AirmiusApiException) return error.userMessage;
    return error.toString();
  }

  void _toast(String message) {
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(message)));
  }

  InputDecoration _fieldDecoration(String label) {
    return InputDecoration(
      labelText: label,
      labelStyle: const TextStyle(color: AirmiusColors.muted, fontWeight: FontWeight.w800),
      filled: true,
      fillColor: AirmiusColors.input,
      border: OutlineInputBorder(borderRadius: BorderRadius.circular(14), borderSide: const BorderSide(color: AirmiusColors.border)),
      enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(14), borderSide: const BorderSide(color: AirmiusColors.border)),
      focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(14), borderSide: const BorderSide(color: AirmiusColors.blue, width: 1.4)),
    );
  }
}

