import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';

import '../core/airmius_api_client.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';
import '../widgets/airmius_widgets.dart';

/// Guest-safe, moderated location suggestion flow.
class PublicLocationSubmissionScreen extends StatefulWidget {
  const PublicLocationSubmissionScreen({super.key});

  @override
  State<PublicLocationSubmissionScreen> createState() =>
      _PublicLocationSubmissionScreenState();
}

class _PublicLocationSubmissionScreenState
    extends State<PublicLocationSubmissionScreen> {
  final _form = GlobalKey<FormState>();
  final _name = TextEditingController();
  final _address = TextEditingController();
  final _description = TextEditingController();
  final _contactName = TextEditingController();
  final _email = TextEditingController();
  String _type = 'club';
  bool _privacyAccepted = false;
  bool _publicVisible = true;
  bool _sending = false;
  bool _sent = false;

  @override
  void dispose() {
    _name.dispose();
    _address.dispose();
    _description.dispose();
    _contactName.dispose();
    _email.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    final t = AirmiusScope.of(context).t;
    if (_sending ||
        !_privacyAccepted ||
        _form.currentState?.validate() != true) {
      return;
    }
    setState(() => _sending = true);
    try {
      final contact = _contactName.text.trim().isEmpty
          ? _name.text.trim()
          : _contactName.text.trim();
      await AirmiusServicesScope.of(context)
          .clientForSession(AirmiusServicesScope.of(context).authState.session)
          .sendPublicContact({
            'name': contact,
            'email': _email.text.trim(),
            'subject': t('publicLocation.title'),
            'category': 'location_$_type',
            'message': [
              '${t('publicLocation.name')}: ${_name.text.trim()}',
              '${t('publicLocation.address')}: ${_address.text.trim()}',
              '${t('publicLocation.description')}: ${_description.text.trim()}',
              '${t('publicLocation.visibility')}: $_publicVisible',
            ].join('\n'),
            'privacy_consent': true,
            'platform': kIsWeb ? 'web' : defaultTargetPlatform.name,
          });
      if (!mounted) return;
      setState(() => _sent = true);
    } on AirmiusApiException catch (error) {
      if (mounted) _showError(error.userMessage);
    } catch (_) {
      if (mounted) _showError(t('publicLocation.sendFailed'));
    } finally {
      if (mounted) setState(() => _sending = false);
    }
  }

  void _showError(String message) {
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(SnackBar(content: Text(message)));
  }

  String _typeLabel(String type, String Function(String) t) => switch (type) {
    'venue' => t('publicLocation.venue'),
    'provider' => t('publicLocation.provider'),
    'correction' => t('publicLocation.correction'),
    _ => t('publicLocation.club'),
  };

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final theme = Theme.of(context);
    return Scaffold(
      appBar: AppBar(
        title: Text(
          t('publicLocation.title'),
          style: const TextStyle(fontWeight: FontWeight.w900),
        ),
      ),
      body: PageFrame(
        title: t('publicLocation.title'),
        subtitle: t('publicLocation.subtitle'),
        child: Form(
          key: _form,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              if (_sent) ...[
                AirmiusPanel(
                  borderColor: theme.colorScheme.secondary,
                  child: ListTile(
                    contentPadding: EdgeInsets.zero,
                    leading: Icon(
                      Icons.mark_email_read_outlined,
                      color: theme.colorScheme.secondary,
                      size: 30,
                    ),
                    title: Text(
                      t('publicLocation.sent'),
                      style: const TextStyle(fontWeight: FontWeight.w900),
                    ),
                    subtitle: Text(t('publicLocation.sentBody')),
                  ),
                ),
                const SizedBox(height: 14),
              ],
              AirmiusPanel(
                gradient: true,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Text(
                      t('publicLocation.type'),
                      style: theme.textTheme.titleMedium?.copyWith(
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(height: 10),
                    _typeSelector(context, t),
                  ],
                ),
              ),
              const SizedBox(height: 14),
              AirmiusPanel(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    TextFormField(
                      controller: _name,
                      textInputAction: TextInputAction.next,
                      decoration: InputDecoration(
                        labelText: t('publicLocation.name'),
                        hintText: t('publicLocation.nameHint'),
                      ),
                      validator: (value) =>
                          value == null || value.trim().isEmpty
                          ? t('publicLocation.required')
                          : null,
                    ),
                    const SizedBox(height: 12),
                    TextFormField(
                      controller: _address,
                      textInputAction: TextInputAction.next,
                      decoration: InputDecoration(
                        labelText: t('publicLocation.address'),
                        hintText: t('publicLocation.addressHint'),
                      ),
                      validator: (value) =>
                          value == null || value.trim().isEmpty
                          ? t('publicLocation.required')
                          : null,
                    ),
                    const SizedBox(height: 12),
                    TextFormField(
                      controller: _description,
                      minLines: 4,
                      maxLines: 8,
                      maxLength: 1200,
                      decoration: InputDecoration(
                        labelText: t('publicLocation.description'),
                        hintText: t('publicLocation.descriptionHint'),
                        alignLabelWithHint: true,
                      ),
                      validator: (value) => (value?.trim().length ?? 0) < 10
                          ? t('publicLocation.messageTooShort')
                          : null,
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 14),
              AirmiusPanel(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    TextFormField(
                      controller: _contactName,
                      textInputAction: TextInputAction.next,
                      decoration: InputDecoration(
                        labelText: t('publicLocation.contactName'),
                      ),
                    ),
                    const SizedBox(height: 12),
                    TextFormField(
                      controller: _email,
                      keyboardType: TextInputType.emailAddress,
                      decoration: InputDecoration(
                        labelText: t('publicLocation.email'),
                      ),
                      validator: (value) {
                        final email = value?.trim() ?? '';
                        if (email.isEmpty) return t('publicLocation.required');
                        if (!RegExp(
                          r'^[^@\s]+@[^@\s]+\.[^@\s]+$',
                        ).hasMatch(email)) {
                          return t('publicLocation.invalidEmail');
                        }
                        return null;
                      },
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 14),
              AirmiusPanel(
                child: Column(
                  children: [
                    SwitchListTile.adaptive(
                      contentPadding: EdgeInsets.zero,
                      value: _publicVisible,
                      onChanged: (value) =>
                          setState(() => _publicVisible = value),
                      title: Text(t('publicLocation.visibility')),
                      subtitle: Text(t('publicLocation.visibilityHint')),
                    ),
                    SwitchListTile.adaptive(
                      contentPadding: EdgeInsets.zero,
                      value: _privacyAccepted,
                      onChanged: (value) =>
                          setState(() => _privacyAccepted = value),
                      title: Text(t('publicLocation.privacy')),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 14),
              AirmiusButton(
                label: _sending
                    ? t('publicLocation.sending')
                    : _type == 'correction'
                    ? t('publicLocation.sendCorrection')
                    : t('publicLocation.send'),
                icon: _sending
                    ? Icons.hourglass_top_outlined
                    : Icons.send_outlined,
                onPressed: _sending || !_privacyAccepted ? null : _submit,
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _typeSelector(BuildContext context, String Function(String) t) {
    const types = ['club', 'venue', 'provider', 'correction'];
    return LayoutBuilder(
      builder: (context, constraints) {
        // Four translated segments do not fit reliably at 390 px with large
        // Arabic or German text. Chips can wrap while preserving the same
        // one-tap selection model and visible selected state.
        if (constraints.maxWidth < 520) {
          return Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              for (final type in types)
                ChoiceChip(
                  label: Text(_typeLabel(type, t)),
                  selected: _type == type,
                  onSelected: (_) => setState(() => _type = type),
                ),
            ],
          );
        }

        return SegmentedButton<String>(
          segments: [
            for (final type in types)
              ButtonSegment<String>(
                value: type,
                label: Text(_typeLabel(type, t)),
              ),
          ],
          selected: {_type},
          onSelectionChanged: (value) => setState(() => _type = value.first),
        );
      },
    );
  }
}
