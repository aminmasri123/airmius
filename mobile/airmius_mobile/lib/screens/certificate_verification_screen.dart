import 'package:flutter/material.dart';

import '../core/airmius_api_client.dart';
import '../core/airmius_api_models.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';
import '../widgets/airmius_widgets.dart';
import 'public_interest_screen.dart';

/// Public certificate verification backed by the read-only, rate-limited API.
///
/// The screen intentionally exposes only the public certificate fields. It
/// does not offer the authenticated PDF download route or any operations
/// controls, so a guest can verify a certificate without gaining account
/// access.
class CertificateVerificationScreen extends StatefulWidget {
  const CertificateVerificationScreen({super.key, this.code = ''});

  final String code;

  @override
  State<CertificateVerificationScreen> createState() =>
      _CertificateVerificationScreenState();
}

class _CertificateVerificationScreenState
    extends State<CertificateVerificationScreen> {
  late final TextEditingController _code;
  JsonMap? _certificate;
  String? _error;
  bool _loading = false;

  String t(String key) => AirmiusScope.of(context).t(key);

  AirmiusApiClient get _client {
    final services = AirmiusServicesScope.of(context);
    return services.clientForSession(services.authState.session);
  }

  @override
  void initState() {
    super.initState();
    _code = TextEditingController(text: widget.code);
  }

  @override
  void dispose() {
    _code.dispose();
    super.dispose();
  }

  Future<void> _verify() async {
    final code = _code.text.trim();
    if (code.isEmpty || _loading) {
      setState(() => _error = t('certificate.required'));
      return;
    }

    FocusManager.instance.primaryFocus?.unfocus();
    setState(() {
      _loading = true;
      _error = null;
      _certificate = null;
    });

    try {
      final response = await _client.publicCertificate(code);
      final data = response['data'];
      if (data is! Map) {
        throw const AirmiusApiException(
          statusCode: 404,
          body: '{"message":"Certificate not found."}',
          path: '/api/v1/public/learning/certificates',
        );
      }
      final certificate = Map<String, dynamic>.from(data);
      if (certificate['code'] == null || certificate['course_title'] == null) {
        throw const AirmiusApiException(
          statusCode: 404,
          body: '{"message":"Certificate not found."}',
          path: '/api/v1/public/learning/certificates',
        );
      }
      if (mounted) setState(() => _certificate = certificate);
    } on AirmiusApiException catch (error) {
      if (!mounted) return;
      setState(() {
        _error = error.statusCode == 404
            ? t('certificate.notFound')
            : t('certificate.error');
      });
    } catch (_) {
      if (mounted) setState(() => _error = t('certificate.error'));
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  String _value(Object? value, [String fallback = '—']) {
    final text = value?.toString().trim() ?? '';
    return text.isEmpty ? fallback : text;
  }

  String _date(Object? value) {
    final raw = value?.toString() ?? '';
    final parsed = DateTime.tryParse(raw)?.toLocal();
    if (parsed == null) return _value(value);
    return '${parsed.day.toString().padLeft(2, '0')}.${parsed.month.toString().padLeft(2, '0')}.${parsed.year}';
  }

  @override
  Widget build(BuildContext context) {
    final certificate = _certificate;
    final theme = Theme.of(context);
    return Scaffold(
      appBar: AppBar(
        title: Text(
          t('certificate.title'),
          style: const TextStyle(fontWeight: FontWeight.w900),
        ),
      ),
      body: PageFrame(
        title: t('certificate.title'),
        subtitle: t('certificate.subtitle'),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            AirmiusPanel(
              gradient: true,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Eyebrow(t('certificate.eyebrow')),
                  const SizedBox(height: 8),
                  Text(
                    t('certificate.intro'),
                    style: theme.textTheme.titleMedium?.copyWith(
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  const SizedBox(height: 14),
                  AirmiusTextField(
                    controller: _code,
                    label: t('certificate.code'),
                    hint: t('certificate.codeHint'),
                    icon: Icons.verified_outlined,
                    textInputAction: TextInputAction.done,
                    onSubmitted: (_) => _verify(),
                    autocorrect: false,
                    enabled: !_loading,
                  ),
                  const SizedBox(height: 12),
                  AirmiusButton(
                    label: _loading
                        ? t('certificate.checking')
                        : t('certificate.check'),
                    icon: _loading
                        ? Icons.hourglass_top_outlined
                        : Icons.fact_check_outlined,
                    onPressed: _loading ? null : _verify,
                  ),
                ],
              ),
            ),
            if (_error != null) ...[
              const SizedBox(height: 14),
              AirmiusPanel(
                borderColor: theme.colorScheme.error,
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Icon(Icons.error_outline, color: theme.colorScheme.error),
                    const SizedBox(width: 10),
                    Expanded(child: Text(_error!)),
                  ],
                ),
              ),
            ],
            if (certificate != null) ...[
              const SizedBox(height: 14),
              AirmiusPanel(
                borderColor: theme.colorScheme.secondary,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Row(
                      children: [
                        Icon(
                          Icons.verified_outlined,
                          color: theme.colorScheme.secondary,
                          size: 30,
                        ),
                        const SizedBox(width: 10),
                        Expanded(
                          child: Text(
                            t('certificate.valid'),
                            style: theme.textTheme.titleLarge?.copyWith(
                              fontWeight: FontWeight.w900,
                            ),
                          ),
                        ),
                        StatusPill(
                          '${_value(certificate['progress_percent'], '100')}%',
                          color: theme.colorScheme.secondary,
                        ),
                      ],
                    ),
                    const SizedBox(height: 16),
                    _CertificateLine(
                      title: t('certificate.code'),
                      body: _value(certificate['code']),
                      icon: Icons.tag_outlined,
                    ),
                    _CertificateLine(
                      title: t('certificate.student'),
                      body: _value(certificate['student_name']),
                      icon: Icons.person_outline,
                    ),
                    _CertificateLine(
                      title: t('certificate.course'),
                      body: _value(certificate['course_title']),
                      icon: Icons.school_outlined,
                    ),
                    if (_value(certificate['course_subtitle']) != '—')
                      _CertificateLine(
                        title: t('certificate.courseSubtitle'),
                        body: _value(certificate['course_subtitle']),
                        icon: Icons.subject_outlined,
                      ),
                    _CertificateLine(
                      title: t('certificate.issued'),
                      body: _date(certificate['issued_at']),
                      icon: Icons.event_available_outlined,
                    ),
                    _CertificateLine(
                      title: t('certificate.tutor'),
                      body: _value(
                        (certificate['tutor'] is Map
                            ? certificate['tutor']['name']
                            : certificate['tutor']),
                      ),
                      icon: Icons.person_pin_outlined,
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 14),
              AirmiusPanel(
                child: AirmiusButton(
                  label: t('certificate.report'),
                  icon: Icons.report_outlined,
                  secondary: true,
                  onPressed: () => Navigator.of(context).push(
                    MaterialPageRoute<void>(
                      builder: (_) => PublicInterestScreen(
                        topic:
                            '${t('certificate.reportTopic')} ${_value(certificate['code'])}',
                        kind: 'certificate_report',
                        icon: Icons.report_outlined,
                      ),
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

class _CertificateLine extends StatelessWidget {
  const _CertificateLine({
    required this.title,
    required this.body,
    required this.icon,
  });

  final String title;
  final String body;
  final IconData icon;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(top: 12),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, color: Theme.of(context).colorScheme.primary),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: const TextStyle(fontWeight: FontWeight.w900),
                ),
                const SizedBox(height: 3),
                Text(body),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
