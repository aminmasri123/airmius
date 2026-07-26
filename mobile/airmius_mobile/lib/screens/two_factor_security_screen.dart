import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../core/airmius_api_client.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';
import '../widgets/airmius_widgets.dart';

class TwoFactorSecurityScreen extends StatefulWidget {
  const TwoFactorSecurityScreen({super.key});

  @override
  State<TwoFactorSecurityScreen> createState() =>
      _TwoFactorSecurityScreenState();
}

class _TwoFactorSecurityScreenState extends State<TwoFactorSecurityScreen> {
  final _confirmationCode = TextEditingController();
  bool _loading = true;
  bool _busy = false;
  bool _enabled = false;
  bool _pending = false;
  String? _setupKey;
  List<String> _recoveryCodes = const [];

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _load());
  }

  @override
  void dispose() {
    _confirmationCode.dispose();
    super.dispose();
  }

  AirmiusApiClient get _client {
    final services = AirmiusServicesScope.of(context);
    return services.clientForSession(services.authState.session);
  }

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return Scaffold(
      appBar: AppBar(title: Text(t('security2fa.title'))),
      body: SafeArea(
        child: ListView(
          padding: const EdgeInsets.fromLTRB(16, 16, 16, 32),
          children: [
            AirmiusPanel(
              gradient: true,
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  IconBadge(
                    icon: _enabled
                        ? Icons.verified_user
                        : Icons.security_outlined,
                    color: _enabled
                        ? Theme.of(context).colorScheme.secondary
                        : Theme.of(context).colorScheme.tertiary,
                  ),
                  const SizedBox(width: 14),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          t('security2fa.panelTitle'),
                          style: Theme.of(context).textTheme.titleLarge
                              ?.copyWith(fontWeight: FontWeight.w900),
                        ),
                        const SizedBox(height: 6),
                        Text(
                          t('security2fa.intro'),
                          style: Theme.of(
                            context,
                          ).textTheme.bodyMedium?.copyWith(height: 1.45),
                        ),
                        const SizedBox(height: 10),
                        StatusPill(
                          t(
                            _enabled
                                ? 'security2fa.enabled'
                                : _pending
                                ? 'security2fa.pending'
                                : 'security2fa.disabled',
                          ),
                          color: _enabled
                              ? Theme.of(context).colorScheme.secondary
                              : Theme.of(context).colorScheme.tertiary,
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 14),
            if (_loading)
              const AirmiusPanel(
                child: Center(
                  child: Padding(
                    padding: EdgeInsets.all(20),
                    child: CircularProgressIndicator(),
                  ),
                ),
              )
            else if (!_enabled && !_pending)
              AirmiusPanel(
                child: AirmiusButton(
                  label: t('security2fa.enable'),
                  icon: Icons.add_moderator_outlined,
                  onPressed: _busy ? null : _enable,
                ),
              )
            else if (_pending)
              _setupPanel(t)
            else
              _enabledPanel(t),
          ],
        ),
      ),
    );
  }

  Widget _setupPanel(String Function(String) t) {
    return AirmiusPanel(
      title: t('security2fa.setupTitle'),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(t('security2fa.setupDescription')),
          const SizedBox(height: 14),
          SelectableText(
            _setupKey ?? '',
            textAlign: TextAlign.center,
            style: const TextStyle(
              fontSize: 20,
              fontWeight: FontWeight.w900,
              letterSpacing: 2,
            ),
          ),
          const SizedBox(height: 10),
          AirmiusButton(
            label: t('security2fa.copyKey'),
            icon: Icons.copy_outlined,
            secondary: true,
            onPressed: _setupKey == null
                ? null
                : () => _copy(_setupKey!, t('security2fa.copied')),
          ),
          const SizedBox(height: 14),
          AirmiusTextField(
            label: t('security2fa.confirmCode'),
            hint: '123456',
            icon: Icons.password_outlined,
            controller: _confirmationCode,
            keyboardType: TextInputType.number,
          ),
          const SizedBox(height: 12),
          AirmiusButton(
            label: t('security2fa.confirm'),
            icon: Icons.verified_user_outlined,
            onPressed: _busy ? null : _confirm,
          ),
        ],
      ),
    );
  }

  Widget _enabledPanel(String Function(String) t) {
    return Column(
      children: [
        if (_recoveryCodes.isNotEmpty) ...[
          AirmiusPanel(
            title: t('security2fa.recoveryTitle'),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Text(t('security2fa.recoveryDescription')),
                const SizedBox(height: 12),
                for (final code in _recoveryCodes)
                  Padding(
                    padding: const EdgeInsets.symmetric(vertical: 4),
                    child: SelectableText(
                      code,
                      textAlign: TextAlign.center,
                      style: const TextStyle(
                        fontWeight: FontWeight.w900,
                        letterSpacing: 1.2,
                      ),
                    ),
                  ),
                const SizedBox(height: 10),
                AirmiusButton(
                  label: t('security2fa.copyAll'),
                  icon: Icons.copy_all_outlined,
                  secondary: true,
                  onPressed: () =>
                      _copy(_recoveryCodes.join('\n'), t('security2fa.copied')),
                ),
              ],
            ),
          ),
          const SizedBox(height: 14),
        ],
        AirmiusPanel(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              AirmiusButton(
                label: t('security2fa.regenerate'),
                icon: Icons.autorenew,
                secondary: true,
                onPressed: _busy ? null : _regenerate,
              ),
              const SizedBox(height: 10),
              AirmiusButton(
                label: t('security2fa.disable'),
                icon: Icons.no_encryption_outlined,
                danger: true,
                onPressed: _busy ? null : _disable,
              ),
            ],
          ),
        ),
      ],
    );
  }

  Future<void> _load() async {
    try {
      final response = await _client.twoFactorStatus();
      _apply(response);
    } catch (error) {
      _showError(error);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _enable() async {
    final password = await _requestPassword();
    if (password == null) return;
    await _run(() async {
      final response = await _client.enableTwoFactor(currentPassword: password);
      _apply(response);
    });
  }

  Future<void> _confirm() async {
    if (_confirmationCode.text.trim().isEmpty) return;
    final authState = AirmiusServicesScope.of(context).authState;
    await _run(() async {
      final response = await _client.confirmTwoFactor(
        code: _confirmationCode.text.trim(),
      );
      _confirmationCode.clear();
      _apply(response);
      await authState.refreshUser();
    });
  }

  Future<void> _regenerate() async {
    final password = await _requestPassword();
    if (password == null) return;
    await _run(() async {
      _apply(
        await _client.regenerateTwoFactorRecoveryCodes(
          currentPassword: password,
        ),
      );
    });
  }

  Future<void> _disable() async {
    final password = await _requestPassword();
    if (password == null) return;
    if (!mounted) return;
    final authState = AirmiusServicesScope.of(context).authState;
    await _run(() async {
      _apply(await _client.disableTwoFactor(currentPassword: password));
      await authState.refreshUser();
    });
  }

  void _apply(Map<String, dynamic> response) {
    final data = response['data'];
    if (data is! Map) return;
    final map = Map<String, dynamic>.from(data);
    if (!mounted) return;
    setState(() {
      _enabled = map['enabled'] == true;
      _pending = map['pending_confirmation'] == true;
      _setupKey = map['setup_key']?.toString();
      final codes = map['recovery_codes'];
      _recoveryCodes = codes is List
          ? codes.map((item) => item.toString()).toList()
          : const [];
    });
  }

  Future<String?> _requestPassword() async {
    final controller = TextEditingController();
    final t = AirmiusScope.of(context).t;
    final result = await showDialog<String>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(t('security2fa.passwordPromptTitle')),
        content: TextField(
          controller: controller,
          obscureText: true,
          autofocus: true,
          decoration: InputDecoration(
            labelText: t('security2fa.currentPassword'),
            prefixIcon: const Icon(Icons.lock_outline),
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: Text(t('auth2fa.cancel')),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, controller.text),
            child: Text(t('security2fa.continue')),
          ),
        ],
      ),
    );
    controller.dispose();
    return result?.trim().isEmpty == true ? null : result;
  }

  Future<void> _run(Future<void> Function() action) async {
    if (_busy) return;
    setState(() => _busy = true);
    try {
      await action();
    } catch (error) {
      _showError(error);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  void _showError(Object error) {
    if (!mounted) return;
    final message = error is AirmiusApiException
        ? error.userMessage
        : AirmiusScope.of(context).t('security2fa.statusError');
    ScaffoldMessenger.of(
      context,
    ).showSnackBar(SnackBar(content: Text(message)));
  }

  Future<void> _copy(String value, String message) async {
    await Clipboard.setData(ClipboardData(text: value));
    if (!mounted) return;
    ScaffoldMessenger.of(
      context,
    ).showSnackBar(SnackBar(content: Text(message)));
  }
}
