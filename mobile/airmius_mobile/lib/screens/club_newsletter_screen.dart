import 'dart:async';

import 'package:flutter/material.dart';

import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';
import 'club_newsletter_subscribers_screen.dart';
import 'support_helpdesk_screen.dart';

class ClubNewsletterScreen extends StatefulWidget {
  const ClubNewsletterScreen({
    super.key,
    required this.clubId,
    this.canManage = false,
  });

  final int clubId;
  final bool canManage;

  @override
  State<ClubNewsletterScreen> createState() => _ClubNewsletterScreenState();
}

class _ClubNewsletterScreenState extends State<ClubNewsletterScreen> {
  final _name = TextEditingController();
  final _email = TextEditingController();
  bool _consent = false;
  bool _sending = false;
  bool _loadingStatus = false;
  bool _initialized = false;
  bool _canUnsubscribe = false;
  String _status = 'none';
  String? _emailError;
  String? _submitError;
  Timer? _resendTimer;
  bool _waitingToResend = false;
  bool _supportRequired = false;
  bool _deliveryFailed = false;

  String t(String key) => AirmiusScope.of(context).t(key);

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (_initialized) return;
    _initialized = true;
    _email.text = AirmiusServicesScope.of(context).authState.user?.email ?? '';
    if (AirmiusServicesScope.of(context).authState.user != null) {
      _loadStatus();
    }
  }

  @override
  void dispose() {
    _resendTimer?.cancel();
    _name.dispose();
    _email.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (_loadingStatus ||
        _sending ||
        (_status == 'pending' && (_waitingToResend || _supportRequired))) {
      return;
    }
    final email = _email.text.trim();
    if (!RegExp(r'^[^\s@]+@[^\s@]+\.[^\s@]+$').hasMatch(email)) {
      setState(() => _emailError = t('guestClubs.newsletterEmailError'));
      return;
    }
    setState(() {
      _sending = true;
      _emailError = null;
      _submitError = null;
    });
    try {
      final services = AirmiusServicesScope.of(context);
      final response = await services
          .clientForSession(services.authState.session)
          .subscribeClubNewsletter(
            clubId: widget.clubId,
            email: email,
            name: _name.text.trim(),
          );
      if (!mounted) return;
      setState(() {
        _sending = false;
        _updateStatus(response['data']);
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _sending = false;
        _submitError = t('guestClubs.newsletterError');
      });
    }
  }

  Future<void> _unsubscribe() async {
    final services = AirmiusServicesScope.of(context);
    final session = services.authState.session;
    if (_sending || !_canUnsubscribe || session == null) return;
    setState(() {
      _sending = true;
      _submitError = null;
    });
    try {
      final response = await services
          .clientForSession(session)
          .unsubscribeClubNewsletter(widget.clubId);
      if (!mounted) return;
      final data = response['data'];
      if (data is! Map<String, dynamic> || data['status'] is! String) {
        throw const FormatException('Missing newsletter status');
      }
      setState(() {
        _sending = false;
        _consent = false;
        _updateStatus(data);
        _canUnsubscribe = _status == 'confirmed';
      });
      if (_status == 'unsubscribed') {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(t('guestClubs.newsletterUnsubscribed'))),
        );
      }
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _sending = false;
        _submitError = t('guestClubs.newsletterUnsubscribeError');
      });
    }
  }

  Future<void> _loadStatus() async {
    setState(() => _loadingStatus = true);
    try {
      final services = AirmiusServicesScope.of(context);
      final response = await services
          .clientForSession(services.authState.session)
          .clubNewsletterStatus(widget.clubId);
      if (!mounted) return;
      setState(() {
        _updateStatus(response['data']);
        _canUnsubscribe = _status == 'confirmed';
      });
    } catch (_) {
      // The form remains available if the optional status lookup fails.
    } finally {
      if (mounted) setState(() => _loadingStatus = false);
    }
  }

  void _updateStatus(Object? data) {
    if (data is! Map<String, dynamic>) return;
    _status = data['status'] as String? ?? 'none';
    _supportRequired = data['support_required'] == true;
    _deliveryFailed = data['confirmation_delivery_failed'] == true;
    if (data['email'] case final String email) _email.text = email;
    _resendTimer?.cancel();
    final resendAt = DateTime.tryParse('${data['can_resend_at'] ?? ''}');
    final seconds = data['retry_after_seconds'];
    final delay = seconds is int
        ? Duration(seconds: seconds)
        : resendAt?.difference(DateTime.now()) ?? Duration.zero;
    _waitingToResend = _status == 'pending' && delay > Duration.zero;
    if (_waitingToResend) {
      _resendTimer = Timer(delay, () {
        if (mounted) setState(() => _waitingToResend = false);
      });
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(
      title: Text(t('guestClubs.newsletterTitle')),
      actions: [
        if (widget.canManage)
          IconButton(
            tooltip: ClubNewsletterSubscribersScreen.title(context),
            icon: const Icon(Icons.people_outline),
            onPressed: () => Navigator.of(context).push(
              MaterialPageRoute<void>(
                builder: (_) =>
                    ClubNewsletterSubscribersScreen(clubId: widget.clubId),
              ),
            ),
          ),
      ],
    ),
    body: SafeArea(
      child: ListView(
        padding: const EdgeInsets.all(20),
        children: [
          if (_loadingStatus) ...[
            const LinearProgressIndicator(),
            const SizedBox(height: 20),
          ],
          if (_status == 'confirmed') ...[
            _StatusPanel(
              icon: Icons.check_circle_outline,
              title: t('guestClubs.newsletterConfirmedTitle'),
              body: t('guestClubs.newsletterConfirmedBody'),
              color: Theme.of(context).colorScheme.secondary,
            ),
            if (_canUnsubscribe) ...[
              const SizedBox(height: 16),
              OutlinedButton.icon(
                onPressed: _sending ? null : _unsubscribe,
                icon: const Icon(Icons.unsubscribe_outlined),
                label: Text(t('guestClubs.newsletterUnsubscribe')),
              ),
              if (_submitError case final error?)
                Text(
                  error,
                  style: TextStyle(color: Theme.of(context).colorScheme.error),
                ),
            ],
          ] else if (_status == 'pending') ...[
            _StatusPanel(
              icon: Icons.mark_email_unread_outlined,
              title: t('guestClubs.newsletterPendingTitle'),
              body: t(
                _supportRequired
                    ? 'guestClubs.newsletterResendUnavailable'
                    : _deliveryFailed
                    ? 'guestClubs.newsletterDeliveryFailed'
                    : 'guestClubs.newsletterCheckInbox',
              ),
              color: Theme.of(context).colorScheme.primary,
            ),
            const SizedBox(height: 16),
            if (_supportRequired)
              FilledButton(
                onPressed: () => Navigator.of(context).push(
                  MaterialPageRoute<void>(
                    builder: (_) => const SupportHelpdeskScreen(),
                  ),
                ),
                child: Text(t('guestClubs.newsletterContactSupport')),
              )
            else
              FilledButton(
                onPressed: _sending || _waitingToResend ? null : _submit,
                child: Text(
                  t(
                    _waitingToResend
                        ? 'guestClubs.newsletterResendWait'
                        : 'guestClubs.newsletterResend',
                  ),
                ),
              ),
            if (_submitError case final error?)
              Text(
                error,
                style: TextStyle(color: Theme.of(context).colorScheme.error),
              ),
          ] else ...[
            Text(
              t('guestClubs.newsletterBody'),
              style: Theme.of(context).textTheme.bodyLarge,
            ),
            const SizedBox(height: 24),
            TextField(
              controller: _name,
              enabled: !_sending,
              autofillHints: const [AutofillHints.name],
              textInputAction: TextInputAction.next,
              decoration: InputDecoration(
                labelText: t('guestClubs.newsletterName'),
              ),
            ),
            const SizedBox(height: 18),
            TextField(
              controller: _email,
              enabled: !_sending,
              keyboardType: TextInputType.emailAddress,
              autofillHints: const [AutofillHints.email],
              textInputAction: TextInputAction.done,
              onChanged: (_) {
                if (_emailError != null) setState(() => _emailError = null);
              },
              decoration: InputDecoration(
                labelText: t('guestClubs.newsletterEmail'),
                errorText: _emailError,
              ),
            ),
            const SizedBox(height: 16),
            CheckboxListTile(
              value: _consent,
              contentPadding: EdgeInsets.zero,
              controlAffinity: ListTileControlAffinity.leading,
              title: Text(t('guestClubs.newsletterConsent')),
              onChanged: _sending
                  ? null
                  : (value) => setState(() => _consent = value ?? false),
            ),
            if (_submitError != null) ...[
              const SizedBox(height: 12),
              Text(
                _submitError!,
                style: TextStyle(color: Theme.of(context).colorScheme.error),
              ),
            ],
            const SizedBox(height: 24),
            FilledButton(
              onPressed: _loadingStatus || _sending || !_consent
                  ? null
                  : _submit,
              child: Text(
                _sending
                    ? t('guestClubs.newsletterSending')
                    : t('guestClubs.newsletterSubmit'),
              ),
            ),
          ],
        ],
      ),
    ),
  );
}

class _StatusPanel extends StatelessWidget {
  const _StatusPanel({
    required this.icon,
    required this.title,
    required this.body,
    required this.color,
  });

  final IconData icon;
  final String title;
  final String body;
  final Color color;

  @override
  Widget build(BuildContext context) => Card(
    child: Padding(
      padding: const EdgeInsets.all(20),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, color: color, size: 34),
          const SizedBox(height: 12),
          Text(
            title,
            style: Theme.of(context).textTheme.titleLarge
                ?.copyWith(fontWeight: FontWeight.w800),
          ),
          const SizedBox(height: 8),
          Text(body),
        ],
      ),
    ),
  );
}
