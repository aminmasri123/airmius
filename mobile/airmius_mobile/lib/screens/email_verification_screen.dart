import 'package:flutter/material.dart';

import '../core/airmius_api_client.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';
import '../widgets/airmius_widgets.dart';

class EmailVerificationScreen extends StatefulWidget {
  const EmailVerificationScreen({
    super.key,
    this.userId,
    this.hash,
    this.query = const {},
  });

  final int? userId;
  final String? hash;
  final Map<String, String> query;

  @override
  State<EmailVerificationScreen> createState() =>
      _EmailVerificationScreenState();
}

class _EmailVerificationScreenState extends State<EmailVerificationScreen> {
  bool _busy = false;
  bool _verified = false;
  bool _sent = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    if (widget.userId != null && widget.hash?.isNotEmpty == true) {
      WidgetsBinding.instance.addPostFrameCallback((_) => _verifyLink());
    }
  }

  @override
  Widget build(BuildContext context) {
    final services = AirmiusServicesScope.of(context);
    final authState = services.authState;
    final user = authState.user;
    final verified = _verified || user?.emailVerified == true;
    final t = AirmiusScope.of(context).t;

    return Scaffold(
      appBar: AppBar(title: Text(t('emailVerification.title'))),
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(20),
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 560),
              child: AirmiusPanel(
                gradient: true,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Icon(
                      verified
                          ? Icons.mark_email_read_outlined
                          : Icons.outgoing_mail,
                      size: 58,
                      color: verified
                          ? Theme.of(context).colorScheme.secondary
                          : Theme.of(context).colorScheme.primary,
                    ),
                    const SizedBox(height: 16),
                    Text(
                      t(
                        verified
                            ? 'emailVerification.verified'
                            : 'emailVerification.heading',
                      ),
                      textAlign: TextAlign.center,
                      style: Theme.of(context).textTheme.headlineSmall
                          ?.copyWith(fontWeight: FontWeight.w900),
                    ),
                    const SizedBox(height: 8),
                    Text(
                      t(
                        verified
                            ? 'emailVerification.verifiedDescription'
                            : 'emailVerification.description',
                      ),
                      textAlign: TextAlign.center,
                      style: Theme.of(
                        context,
                      ).textTheme.bodyMedium?.copyWith(height: 1.45),
                    ),
                    if (user?.email.isNotEmpty == true) ...[
                      const SizedBox(height: 10),
                      Text(
                        user!.email,
                        textAlign: TextAlign.center,
                        style: const TextStyle(fontWeight: FontWeight.w900),
                      ),
                    ],
                    if (_busy) ...[
                      const SizedBox(height: 18),
                      const Center(child: CircularProgressIndicator()),
                    ],
                    if (_sent) ...[
                      const SizedBox(height: 14),
                      Semantics(
                        liveRegion: true,
                        child: Text(
                          t('emailVerification.sent'),
                          textAlign: TextAlign.center,
                          style: TextStyle(
                            color: Theme.of(context).colorScheme.secondary,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                      ),
                    ],
                    if (_error != null) ...[
                      const SizedBox(height: 14),
                      Semantics(
                        liveRegion: true,
                        child: Text(
                          _error!,
                          textAlign: TextAlign.center,
                          style: TextStyle(
                            color: Theme.of(context).colorScheme.error,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                      ),
                    ],
                    const SizedBox(height: 18),
                    if (!verified) ...[
                      AirmiusButton(
                        label: t('emailVerification.resend'),
                        icon: Icons.forward_to_inbox_outlined,
                        onPressed: _busy ? null : _resend,
                      ),
                      const SizedBox(height: 8),
                      AirmiusButton(
                        label: t('emailVerification.refresh'),
                        icon: Icons.refresh,
                        secondary: true,
                        onPressed: _busy ? null : authState.refreshUser,
                      ),
                    ],
                    const SizedBox(height: 8),
                    TextButton.icon(
                      onPressed: _busy ? null : authState.signOut,
                      icon: const Icon(Icons.logout_outlined),
                      label: Text(t('emailVerification.signOut')),
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

  Future<void> _verifyLink() async {
    await _run(() async {
      final client = AirmiusServicesScope.of(context).clientForSession(null);
      await client.verifyEmailLink(
        userId: widget.userId!,
        hash: widget.hash!,
        query: widget.query,
      );
      if (!mounted) return;
      setState(() => _verified = true);
      await AirmiusServicesScope.of(context).authState.refreshUser();
    });
  }

  Future<void> _resend() async {
    final services = AirmiusServicesScope.of(context);
    await _run(() async {
      await services
          .clientForSession(services.authState.session)
          .resendEmailVerification();
      if (mounted) setState(() => _sent = true);
    });
  }

  Future<void> _run(Future<void> Function() action) async {
    if (_busy) return;
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      await action();
    } on AirmiusApiException catch (error) {
      if (mounted) setState(() => _error = error.userMessage);
    } catch (_) {
      if (mounted) {
        setState(
          () => _error = AirmiusScope.of(context).t('emailVerification.error'),
        );
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }
}
