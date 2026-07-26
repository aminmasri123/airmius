import 'package:flutter/material.dart';

import '../core/airmius_auth_state.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

class TwoFactorChallengeScreen extends StatefulWidget {
  const TwoFactorChallengeScreen({super.key, required this.authState});

  final AirmiusAuthState authState;

  @override
  State<TwoFactorChallengeScreen> createState() =>
      _TwoFactorChallengeScreenState();
}

class _TwoFactorChallengeScreenState extends State<TwoFactorChallengeScreen> {
  final _code = TextEditingController();
  bool _useRecoveryCode = false;

  @override
  void dispose() {
    _code.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final loading = widget.authState.phase == AirmiusAuthPhase.loading;

    return Scaffold(
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(20),
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 520),
              child: AirmiusPanel(
                gradient: true,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Icon(
                      Icons.phonelink_lock_outlined,
                      size: 56,
                      color: Theme.of(context).colorScheme.primary,
                    ),
                    const SizedBox(height: 16),
                    Text(
                      t('auth2fa.heading'),
                      textAlign: TextAlign.center,
                      style: Theme.of(context).textTheme.headlineSmall
                          ?.copyWith(fontWeight: FontWeight.w900),
                    ),
                    const SizedBox(height: 8),
                    Text(
                      t('auth2fa.description'),
                      textAlign: TextAlign.center,
                      style: Theme.of(
                        context,
                      ).textTheme.bodyMedium?.copyWith(height: 1.45),
                    ),
                    const SizedBox(height: 20),
                    AirmiusTextField(
                      label: t(
                        _useRecoveryCode
                            ? 'auth2fa.recoveryCode'
                            : 'auth2fa.code',
                      ),
                      hint: _useRecoveryCode ? 'xxxx-xxxx-xxxx' : '123456',
                      icon: _useRecoveryCode
                          ? Icons.key_outlined
                          : Icons.password_outlined,
                      controller: _code,
                      keyboardType: _useRecoveryCode
                          ? TextInputType.text
                          : TextInputType.number,
                    ),
                    if (widget.authState.error != null) ...[
                      const SizedBox(height: 12),
                      Semantics(
                        liveRegion: true,
                        child: Text(
                          widget.authState.error!,
                          style: const TextStyle(
                            color: AirmiusColors.red,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                      ),
                    ],
                    const SizedBox(height: 16),
                    AirmiusButton(
                      label: t('auth2fa.verify'),
                      icon: Icons.verified_user_outlined,
                      onPressed: loading
                          ? null
                          : () => widget.authState.completeTwoFactor(
                              value: _code.text,
                              recoveryCode: _useRecoveryCode,
                            ),
                    ),
                    const SizedBox(height: 8),
                    AirmiusButton(
                      label: t(
                        _useRecoveryCode
                            ? 'auth2fa.useAuthenticator'
                            : 'auth2fa.useRecovery',
                      ),
                      icon: _useRecoveryCode
                          ? Icons.phone_android_outlined
                          : Icons.key_outlined,
                      secondary: true,
                      onPressed: loading
                          ? null
                          : () {
                              _code.clear();
                              setState(
                                () => _useRecoveryCode = !_useRecoveryCode,
                              );
                            },
                    ),
                    const SizedBox(height: 8),
                    TextButton.icon(
                      onPressed: loading
                          ? null
                          : widget.authState.cancelTwoFactor,
                      icon: const Icon(Icons.arrow_back),
                      label: Text(t('auth2fa.cancel')),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}
