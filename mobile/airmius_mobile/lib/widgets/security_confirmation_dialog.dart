import 'package:flutter/material.dart';

import '../core/airmius_api_client.dart';
import '../screens/password_recovery_screen.dart';

class SecurityConfirmationDialog extends StatefulWidget {
  const SecurityConfirmationDialog({
    super.key,
    required this.client,
    required this.purpose,
    required this.onConfirm,
  });
  final AirmiusApiClient client;
  final String purpose;
  final Future<void> Function(AirmiusJson) onConfirm;

  @override
  State<SecurityConfirmationDialog> createState() =>
      _SecurityConfirmationDialogState();
}

class _SecurityConfirmationDialogState
    extends State<SecurityConfirmationDialog> {
  final _password = TextEditingController();
  final _code = TextEditingController();
  bool _email = true;
  bool _busy = false;
  String? _token;
  String? _error;

  @override
  void dispose() {
    _password.dispose();
    _code.dispose();
    super.dispose();
  }

  Future<void> _send() async {
    if (_busy) return;
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      final response = await widget.client.requestTwoFactorChangeCode(
        widget.purpose,
      );
      if (!mounted) return;
      setState(() {
        _token = (response['data'] as Map)['challenge_token'] as String;
        _code.clear();
      });
    } catch (error) {
      if (mounted) {
        setState(
          () => _error = error is AirmiusApiException
              ? error.userMessage
              : 'Der Code konnte nicht versendet werden.',
        );
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _confirm() async {
    if (_busy) return;
    if (_email &&
        (_token == null || !RegExp(r'^\d{6}$').hasMatch(_code.text.trim()))) {
      setState(
        () => _error =
            'Bitte fordere einen E-Mail-Code an und gib die sechs Ziffern ein.',
      );
      return;
    }
    if (!_email && _password.text.isEmpty) {
      setState(
        () => _error =
            'Bitte gib dein Airmius-Passwort ein oder wähle den E-Mail-Code.',
      );
      return;
    }
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      await widget.onConfirm(
        _email
            ? {'challenge_token': _token, 'email_code': _code.text.trim()}
            : {'current_password': _password.text},
      );
      if (mounted) Navigator.pop(context);
    } catch (error) {
      if (mounted) {
        setState(
          () => _error = error is AirmiusApiException
              ? error.userMessage
              : 'Bestätigung fehlgeschlagen. Bitte erneut versuchen.',
        );
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) => PopScope(
    canPop: !_busy,
    child: AlertDialog(
      title: const Text('Sicherheitsänderung bestätigen'),
      content: SingleChildScrollView(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            const Text(
              'Bestätige, dass du diese Änderung selbst angefordert hast. Bei Google-Anmeldung kannst du den E-Mail-Code verwenden. Dein Google-Passwort wird nicht benötigt.',
            ),
            const SizedBox(height: 12),
            SegmentedButton<bool>(
              segments: const [
                ButtonSegment(value: true, label: Text('E-Mail-Code')),
                ButtonSegment(value: false, label: Text('Passwort')),
              ],
              selected: {_email},
              onSelectionChanged: _busy
                  ? null
                  : (value) => setState(() {
                      _email = value.first;
                      _error = null;
                    }),
            ),
            const SizedBox(height: 12),
            if (_email) ...[
              OutlinedButton(
                onPressed: _busy ? null : _send,
                child: Text(
                  _token == null
                      ? 'Code per E-Mail senden'
                      : 'Neuen Code senden',
                ),
              ),
              if (_token != null) ...[
                const Text(
                  'Code gesendet. Er gilt 10 Minuten und nur für diese Änderung. Ein neuer Code ersetzt den vorherigen.',
                ),
                TextField(
                  controller: _code,
                  enabled: !_busy,
                  keyboardType: TextInputType.number,
                  autofillHints: const [AutofillHints.oneTimeCode],
                  maxLength: 6,
                  decoration: const InputDecoration(labelText: 'E-Mail-Code'),
                  onSubmitted: (_) => _confirm(),
                ),
              ],
            ] else ...[
              TextField(
                controller: _password,
                enabled: !_busy,
                obscureText: true,
                decoration: const InputDecoration(
                  labelText: 'Airmius-Passwort',
                ),
                onSubmitted: (_) => _confirm(),
              ),
              TextButton(
                onPressed: _busy
                    ? null
                    : () {
                        final navigator = Navigator.of(context);
                        navigator.pop();
                        navigator.push(
                          MaterialPageRoute(
                            builder: (_) => const PasswordRecoveryScreen(),
                          ),
                        );
                      },
                child: const Text('Airmius-Passwort einrichten / vergessen'),
              ),
            ],
            if (_error != null)
              Text(
                _error!,
                style: TextStyle(color: Theme.of(context).colorScheme.error),
              ),
            if (_busy) const LinearProgressIndicator(),
          ],
        ),
      ),
      actions: [
        TextButton(
          onPressed: _busy ? null : () => Navigator.pop(context),
          child: const Text('Abbrechen'),
        ),
        FilledButton(
          onPressed: _busy ? null : _confirm,
          child: const Text('Bestätigen'),
        ),
      ],
    ),
  );
}
