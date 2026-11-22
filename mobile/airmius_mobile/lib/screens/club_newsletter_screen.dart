import 'package:flutter/material.dart';

import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';
import 'club_newsletter_subscribers_screen.dart';

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
  String _status = 'none';
  String? _emailError;
  String? _submitError;

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
    _name.dispose();
    _email.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
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
      final data = response['data'];
      setState(() {
        _sending = false;
        _status = data is Map<String, dynamic>
            ? '${data['status'] ?? 'pending'}'
            : 'pending';
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _sending = false;
        _submitError = t('guestClubs.newsletterError');
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
      final data = response['data'];
      if (!mounted) return;
      setState(() {
        _status = data is Map<String, dynamic>
            ? '${data['status'] ?? 'none'}'
            : 'none';
      });
    } catch (_) {
      // The form remains available if the optional status lookup fails.
    } finally {
      if (mounted) setState(() => _loadingStatus = false);
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
          ] else if (_status == 'pending') ...[
            _StatusPanel(
              icon: Icons.mark_email_unread_outlined,
              title: t('guestClubs.newsletterPendingTitle'),
              body: t('guestClubs.newsletterPendingBody'),
              color: Theme.of(context).colorScheme.primary,
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
              onPressed: _sending || !_consent ? null : _submit,
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
            style: Theme.of(
              context,
            ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w800),
          ),
          const SizedBox(height: 8),
          Text(body),
        ],
      ),
    ),
  );
}
