import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;

import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';

class RegistrationLegalDialog extends StatefulWidget {
  const RegistrationLegalDialog({super.key, required this.privacy});

  final bool privacy;

  @override
  State<RegistrationLegalDialog> createState() =>
      _RegistrationLegalDialogState();
}

class _RegistrationLegalDialogState extends State<RegistrationLegalDialog> {
  Future<Map<String, dynamic>>? _content;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _content ??= _load();
  }

  Future<Map<String, dynamic>> _load() async {
    final origin = AirmiusServicesScope.of(context).environment.apiBaseUrl;
    final uri = Uri.parse(origin).replace(
      path: widget.privacy ? '/datenschutz' : '/agb',
      queryParameters: {
        'format': 'json',
        'locale': Localizations.localeOf(context).languageCode,
      },
      fragment: '',
    );
    final response = await http.get(uri).timeout(const Duration(seconds: 15));
    if (response.statusCode != 200) {
      throw StateError('Legal document unavailable');
    }
    final data = jsonDecode(utf8.decode(response.bodyBytes));
    if (data is! Map<String, dynamic> ||
        data['sections'] is! List ||
        (data['sections'] as List).isEmpty) {
      throw StateError('Invalid legal document');
    }
    return data;
  }

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return AlertDialog(
      title: Text(t(widget.privacy ? 'legalHub.privacy' : 'legalHub.terms')),
      content: SizedBox(
        width: 600,
        height: MediaQuery.sizeOf(context).height * 0.6,
        child: FutureBuilder<Map<String, dynamic>>(
          future: _content,
          builder: (context, snapshot) {
            if (snapshot.hasError) {
              return Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Text(t('legalHub.openError')),
                  TextButton(
                    onPressed: () => setState(() => _content = _load()),
                    child: Text(t('common.retry')),
                  ),
                ],
              );
            }
            final data = snapshot.data;
            if (data == null) {
              return const Center(child: CircularProgressIndicator());
            }
            return SelectionArea(
              child: SingleChildScrollView(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    if (data['note'] is String) Text(data['note'] as String),
                    for (final section in data['sections'] as List) ...[
                      const SizedBox(height: 18),
                      Text(
                        section['title'] as String,
                        style: Theme.of(context).textTheme.titleMedium,
                      ),
                      for (final paragraph in section['body'] as List)
                        Padding(
                          padding: const EdgeInsets.only(top: 8),
                          child: Text(paragraph as String),
                        ),
                    ],
                  ],
                ),
              ),
            );
          },
        ),
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.pop(context),
          child: Text(t('common.close')),
        ),
      ],
    );
  }
}
