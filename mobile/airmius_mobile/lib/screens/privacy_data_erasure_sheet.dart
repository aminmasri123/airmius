import 'package:flutter/material.dart';

import '../core/airmius_api_client.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

class DataErasureSheet extends StatefulWidget {
  const DataErasureSheet({
    super.key,
    required this.client,
    required this.usesSocialLogin,
    required this.accountEmail,
    required this.categoryKeys,
  });

  final AirmiusApiClient client;
  final bool usesSocialLogin;
  final String accountEmail;
  final List<String> categoryKeys;

  @override
  State<DataErasureSheet> createState() => _DataErasureSheetState();
}

class _DataErasureSheetState extends State<DataErasureSheet> {
  late final TextEditingController _identity;
  final _code = TextEditingController();
  final _selected = <String>{};
  bool _codeSent = false;
  bool _busy = false;

  @override
  void initState() {
    super.initState();
    _identity = TextEditingController(
      text: widget.usesSocialLogin ? widget.accountEmail : '',
    );
  }

  @override
  void dispose() {
    _identity.dispose();
    _code.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final inset = MediaQuery.viewInsetsOf(context).bottom;
    return SafeArea(
      top: false,
      child: SingleChildScrollView(
        padding: EdgeInsets.fromLTRB(18, 4, 18, inset + 24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text(
              t('privacy.eraseData'),
              style: const TextStyle(fontSize: 22, fontWeight: FontWeight.w900),
            ),
            const SizedBox(height: 8),
            Text(
              t('privacy.eraseWarning'),
              style: TextStyle(color: airmiusMutedColor(context), height: 1.4),
            ),
            const SizedBox(height: 16),
            AirmiusPanel(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Row(
                    children: [
                      Expanded(child: Eyebrow(t('privacy.eraseSelectAreas'))),
                      TextButton(
                        onPressed: _busy ? null : _toggleAll,
                        child: Text(
                          _selected.length == widget.categoryKeys.length
                              ? t('privacy.eraseClearAll')
                              : t('privacy.eraseSelectAll'),
                        ),
                      ),
                    ],
                  ),
                  ...widget.categoryKeys.map(
                    (key) => CheckboxListTile(
                      contentPadding: EdgeInsets.zero,
                      value: _selected.contains(key),
                      onChanged: _busy
                          ? null
                          : (value) => _setCategory(key, value == true),
                      title: Text(
                        t('privacy.eraseCategory.$key'),
                        style: const TextStyle(fontWeight: FontWeight.w800),
                      ),
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 14),
            AirmiusPanel(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Eyebrow(t('privacy.eraseConfirmIdentity')),
                  const SizedBox(height: 8),
                  Text(
                    t(
                      widget.usesSocialLogin
                          ? 'privacy.eraseEmailIdentityHint'
                          : 'privacy.erasePasswordIdentityHint',
                    ),
                    style: TextStyle(
                      color: airmiusMutedColor(context),
                      height: 1.35,
                    ),
                  ),
                  const SizedBox(height: 10),
                  TextField(
                    controller: _identity,
                    obscureText: !widget.usesSocialLogin,
                    keyboardType: widget.usesSocialLogin
                        ? TextInputType.emailAddress
                        : TextInputType.visiblePassword,
                    enabled: !_busy,
                    decoration: InputDecoration(
                      labelText: widget.usesSocialLogin
                          ? t('privacy.email')
                          : t('account.currentPassword'),
                    ),
                  ),
                  const SizedBox(height: 12),
                  AirmiusButton(
                    label: _codeSent
                        ? t('privacy.eraseNewCode')
                        : t('privacy.eraseRequestCode'),
                    icon: Icons.mark_email_read_outlined,
                    onPressed: _busy ? null : _requestCode,
                  ),
                ],
              ),
            ),
            if (_codeSent) ...[
              const SizedBox(height: 14),
              AirmiusPanel(
                borderColor: Theme.of(
                  context,
                ).colorScheme.error.withValues(alpha: .5),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Eyebrow(t('privacy.eraseFinalStep')),
                    const SizedBox(height: 8),
                    Text(
                      t('privacy.eraseCodeHint'),
                      style: TextStyle(
                        color: airmiusMutedColor(context),
                        height: 1.35,
                      ),
                    ),
                    const SizedBox(height: 12),
                    TextField(
                      controller: _code,
                      enabled: !_busy,
                      keyboardType: TextInputType.number,
                      textInputAction: TextInputAction.done,
                      maxLength: 6,
                      decoration: InputDecoration(
                        labelText: t('privacy.eraseCode'),
                        counterText: '',
                      ),
                    ),
                    const SizedBox(height: 12),
                    AirmiusButton(
                      label: t('privacy.eraseConfirm'),
                      icon: Icons.delete_forever_outlined,
                      danger: true,
                      onPressed: _busy ? null : _erase,
                    ),
                  ],
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }

  void _toggleAll() {
    setState(() {
      if (_selected.length == widget.categoryKeys.length) {
        _selected.clear();
      } else {
        _selected
          ..clear()
          ..addAll(widget.categoryKeys);
      }
      _invalidateCode();
    });
  }

  void _setCategory(String key, bool selected) {
    setState(() {
      if (selected) {
        _selected.add(key);
      } else {
        _selected.remove(key);
      }
      _invalidateCode();
    });
  }

  void _invalidateCode() {
    _codeSent = false;
    _code.clear();
  }

  Future<void> _requestCode() async {
    final t = AirmiusScope.of(context).t;
    if (_selected.isEmpty) {
      _toast(t('privacy.eraseSelectionRequired'));
      return;
    }
    if (_identity.text.trim().isEmpty) {
      _toast(t('privacy.eraseIdentityRequired'));
      return;
    }

    setState(() => _busy = true);
    try {
      await widget.client.requestDataErasureCode(
        identity: _identity.text,
        categories: _selected.toList(growable: false),
      );
      if (!mounted) return;
      setState(() => _codeSent = true);
      _toast(t('privacy.eraseCodeSent'));
    } catch (error) {
      if (mounted) _toast(_errorMessage(error));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _erase() async {
    final t = AirmiusScope.of(context).t;
    if (_code.text.trim().isEmpty) {
      _toast(t('privacy.eraseCodeRequired'));
      return;
    }

    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(t('privacy.eraseConfirm')),
        content: Text(t('privacy.eraseFinalWarning')),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: Text(t('cancel')),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, true),
            child: Text(t('privacy.eraseConfirm')),
          ),
        ],
      ),
    );
    if (confirmed != true || !mounted) return;

    setState(() => _busy = true);
    try {
      await widget.client.erasePersonalData(
        code: _code.text,
        categories: _selected.toList(growable: false),
      );
      if (!mounted) return;
      _toast(t('privacy.eraseCompleted'));
      Navigator.pop(context);
    } catch (error) {
      if (mounted) _toast(_errorMessage(error));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  String _errorMessage(Object error) => error is AirmiusApiException
      ? error.userMessage
      : AirmiusScope.of(context).t('common.errorDetails');

  void _toast(String message) {
    ScaffoldMessenger.of(
      context,
    ).showSnackBar(SnackBar(content: Text(message)));
  }
}
