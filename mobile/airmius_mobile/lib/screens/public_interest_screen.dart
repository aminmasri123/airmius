import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';

import '../core/airmius_api_client.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';
import '../widgets/airmius_widgets.dart';
import 'public_location_submission_screen.dart';

/// Guest-safe interest form backed by the public, rate-limited contact API.
class PublicInterestScreen extends StatefulWidget {
  const PublicInterestScreen({
    super.key,
    required this.topic,
    required this.kind,
    required this.icon,
  });

  final String topic;
  final String kind;
  final IconData icon;

  @override
  State<PublicInterestScreen> createState() => _PublicInterestScreenState();
}

class _PublicInterestScreenState extends State<PublicInterestScreen> {
  final _form = GlobalKey<FormState>();
  final _name = TextEditingController();
  final _email = TextEditingController();
  final _message = TextEditingController();
  bool _privacyAccepted = false;
  bool _sending = false;
  bool _sent = false;

  @override
  void dispose() {
    _name.dispose();
    _email.dispose();
    _message.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (_sending ||
        !_privacyAccepted ||
        _form.currentState?.validate() != true) {
      return;
    }
    setState(() => _sending = true);
    try {
      await AirmiusServicesScope.of(context)
          .clientForSession(AirmiusServicesScope.of(context).authState.session)
          .sendPublicContact({
            'name': _name.text.trim(),
            'email': _email.text.trim(),
            'subject': widget.topic,
            'category': widget.kind,
            'message': _message.text.trim(),
            'privacy_consent': true,
            'platform': kIsWeb ? 'web' : defaultTargetPlatform.name,
          });
      if (!mounted) return;
      setState(() => _sent = true);
    } on AirmiusApiException catch (error) {
      if (mounted) _showError(error.userMessage);
    } catch (_) {
      if (mounted) {
        _showError(AirmiusScope.of(context).t('publicInterest.sendFailed'));
      }
    } finally {
      if (mounted) setState(() => _sending = false);
    }
  }

  void _showError(String message) {
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(SnackBar(content: Text(message)));
  }

  void _startNewRequest() {
    _name.clear();
    _email.clear();
    _message.clear();
    setState(() {
      _privacyAccepted = false;
      _sent = false;
    });
  }

  String _kindLabel(AirmiusScope scope) => switch (widget.kind) {
    'marketplace_interest' => scope.t('publicInterest.kindMarketplace'),
    'club_interest' => scope.t('publicInterest.kindClub'),
    'partner_interest' => scope.t('publicInterest.kindPartner'),
    'learning_interest' => scope.t('publicInterest.kindLearning'),
    'Public' => scope.t('publicInterest.kindPublic'),
    _ => scope.t('publicInterest.kindDefault'),
  };

  @override
  Widget build(BuildContext context) {
    final scope = AirmiusScope.of(context);
    final t = scope.t;
    final theme = Theme.of(context);
    return Scaffold(
      appBar: AppBar(
        title: Text(
          widget.topic,
          style: const TextStyle(fontWeight: FontWeight.w900),
        ),
      ),
      body: PageFrame(
        title: widget.topic,
        subtitle: t('publicInterest.subtitle'),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            AirmiusPanel(
              gradient: true,
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Icon(widget.icon, color: theme.colorScheme.primary, size: 34),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Text(
                      '${widget.topic} · ${_kindLabel(scope)}',
                      style: theme.textTheme.titleLarge?.copyWith(
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 14),
            if (_sent) ...[
              AirmiusPanel(
                borderColor: theme.colorScheme.secondary,
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Icon(
                      Icons.mark_email_read_outlined,
                      color: theme.colorScheme.secondary,
                      size: 30,
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            t('publicInterest.sent'),
                            style: theme.textTheme.titleMedium?.copyWith(
                              fontWeight: FontWeight.w900,
                            ),
                          ),
                          const SizedBox(height: 4),
                          Text(t('publicInterest.sentBody')),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 14),
              AirmiusButton(
                label: t('publicInterest.newRequest'),
                icon: Icons.add_comment_outlined,
                onPressed: _startNewRequest,
              ),
            ] else ...[
              Form(
                key: _form,
                child: AirmiusPanel(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      Text(
                        t('publicInterest.subtitle'),
                        style: theme.textTheme.titleMedium?.copyWith(
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                      const SizedBox(height: 13),
                      TextFormField(
                        controller: _name,
                        textInputAction: TextInputAction.next,
                        maxLength: 100,
                        decoration: InputDecoration(
                          labelText: t('publicInterest.name'),
                        ),
                        validator: (value) =>
                            value == null || value.trim().isEmpty
                            ? t('publicInterest.required')
                            : null,
                      ),
                      const SizedBox(height: 4),
                      TextFormField(
                        controller: _email,
                        keyboardType: TextInputType.emailAddress,
                        textInputAction: TextInputAction.next,
                        maxLength: 150,
                        decoration: InputDecoration(
                          labelText: t('publicInterest.email'),
                        ),
                        validator: (value) {
                          final email = value?.trim() ?? '';
                          if (email.isEmpty) {
                            return t('publicInterest.required');
                          }
                          if (!RegExp(
                            r'^[^@\s]+@[^@\s]+\.[^@\s]+$',
                          ).hasMatch(email)) {
                            return t('publicInterest.invalidEmail');
                          }
                          return null;
                        },
                      ),
                      const SizedBox(height: 4),
                      TextFormField(
                        controller: _message,
                        minLines: 5,
                        maxLines: 10,
                        maxLength: 2000,
                        decoration: InputDecoration(
                          labelText: t('publicInterest.message'),
                          alignLabelWithHint: true,
                        ),
                        validator: (value) => (value?.trim().length ?? 0) < 10
                            ? t('publicInterest.messageTooShort')
                            : null,
                      ),
                      SwitchListTile.adaptive(
                        contentPadding: EdgeInsets.zero,
                        value: _privacyAccepted,
                        onChanged: (value) =>
                            setState(() => _privacyAccepted = value),
                        title: Text(t('publicInterest.privacy')),
                      ),
                      const SizedBox(height: 8),
                      AirmiusButton(
                        label: _sending
                            ? t('publicInterest.sending')
                            : t('publicInterest.send'),
                        icon: _sending
                            ? Icons.hourglass_top_outlined
                            : Icons.send_outlined,
                        onPressed: _sending || !_privacyAccepted
                            ? null
                            : _submit,
                      ),
                    ],
                  ),
                ),
              ),
              const SizedBox(height: 14),
              AirmiusPanel(
                child: AirmiusButton(
                  label: t('publicInterest.suggestLocation'),
                  icon: Icons.add_location_alt_outlined,
                  secondary: true,
                  onPressed: () => Navigator.of(context).push(
                    MaterialPageRoute<void>(
                      builder: (_) => const PublicLocationSubmissionScreen(),
                    ),
                  ),
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }
}
