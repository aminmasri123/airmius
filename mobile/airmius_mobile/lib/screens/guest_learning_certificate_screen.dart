import 'package:flutter/material.dart';

import '../core/airmius_api_client.dart';
import '../core/airmius_api_models.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';
import '../widgets/airmius_widgets.dart';
import 'certificate_verification_screen.dart';
import 'public_interest_screen.dart';
import 'support_helpdesk_screen.dart';

/// Public learning catalogue and certificate entry point.
///
/// Course cards are loaded from the published API. A guest can request course
/// information or verify a certificate, but cannot access protected lessons
/// or enrollment data from this screen.
class GuestLearningCertificateScreen extends StatefulWidget {
  const GuestLearningCertificateScreen({super.key});

  @override
  State<GuestLearningCertificateScreen> createState() =>
      _GuestLearningCertificateScreenState();
}

class _GuestLearningCertificateScreenState
    extends State<GuestLearningCertificateScreen> {
  Future<_PublicLearningBundle>? _future;
  final _certificateCode = TextEditingController();
  String _query = '';
  String _category = '';

  String t(String key) => AirmiusScope.of(context).t(key);

  AirmiusApiClient get _client {
    final services = AirmiusServicesScope.of(context);
    return services.clientForSession(services.authState.session);
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _future ??= _load();
  }

  @override
  void dispose() {
    _certificateCode.dispose();
    super.dispose();
  }

  Future<_PublicLearningBundle> _load() async {
    final response = await _client.publicLearningCourses();
    final data = response['data'];
    final facets = response['facets'];
    final items = data is List
        ? data
              .whereType<Map>()
              .map((item) => Map<String, dynamic>.from(item))
              .toList()
        : <JsonMap>[];
    final categories = facets is Map && facets['categories'] is List
        ? (facets['categories'] as List)
              .map((item) => item.toString().trim())
              .where((item) => item.isNotEmpty)
              .toList()
        : items
              .map((item) => _text(item['category']))
              .where((item) => item.isNotEmpty)
              .toSet()
              .toList();
    return _PublicLearningBundle(items: items, categories: categories);
  }

  void _reload() => setState(() => _future = _load());

  void _verifyCertificate() {
    final code = _certificateCode.text.trim();
    if (code.isEmpty) {
      _toast(t('guestLearning.codeRequired'));
      return;
    }
    Navigator.of(context).push(
      MaterialPageRoute<void>(
        builder: (_) => CertificateVerificationScreen(code: code),
      ),
    );
  }

  void _toast(String message) {
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(SnackBar(content: Text(message)));
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text(
          t('guestLearning.title'),
          style: const TextStyle(fontWeight: FontWeight.w900),
        ),
        actions: [
          IconButton(
            tooltip: t('guestLearning.reload'),
            onPressed: _reload,
            icon: const Icon(Icons.refresh_outlined),
          ),
        ],
      ),
      body: FutureBuilder<_PublicLearningBundle>(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.connectionState == ConnectionState.waiting) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snapshot.hasError) {
            return PageFrame(
              title: t('guestLearning.title'),
              subtitle: t('guestLearning.subtitle'),
              child: AirmiusPanel(
                child: Column(
                  children: [
                    Text(
                      t('guestLearning.loadError'),
                      textAlign: TextAlign.center,
                    ),
                    const SizedBox(height: 12),
                    AirmiusButton(
                      label: t('guestLearning.retry'),
                      icon: Icons.refresh_outlined,
                      onPressed: _reload,
                    ),
                  ],
                ),
              ),
            );
          }

          final bundle = snapshot.data ?? const _PublicLearningBundle();
          final visible = bundle.items.where((item) {
            final text = [
              item['title'],
              item['subtitle'],
              item['description'],
              item['category'],
              item['sport_type'],
              item['level'],
            ].map(_text).join(' ').toLowerCase();
            return (_query.trim().isEmpty ||
                    text.contains(_query.trim().toLowerCase())) &&
                (_category.isEmpty || item['category'] == _category);
          }).toList();

          return PageFrame(
            title: t('guestLearning.title'),
            subtitle: t('guestLearning.subtitle'),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                AirmiusPanel(
                  gradient: true,
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      Eyebrow(t('guestLearning.eyebrow')),
                      const SizedBox(height: 8),
                      Text(
                        t('guestLearning.intro'),
                        style: Theme.of(context).textTheme.titleMedium
                            ?.copyWith(fontWeight: FontWeight.w800),
                      ),
                      const SizedBox(height: 14),
                      Row(
                        children: [
                          Expanded(
                            child: MetricCard(
                              value: '${bundle.items.length}',
                              label: t('guestLearning.courses'),
                            ),
                          ),
                          const SizedBox(width: 10),
                          Expanded(
                            child: MetricCard(
                              value:
                                  '${bundle.items.where((item) => item['is_free'] == true).length}',
                              label: t('guestLearning.free'),
                            ),
                          ),
                        ],
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 14),
                SearchBox(
                  hint: t('guestLearning.search'),
                  onChanged: (value) => setState(() => _query = value),
                ),
                if (bundle.categories.isNotEmpty) ...[
                  const SizedBox(height: 12),
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: [
                      _CategoryChip(
                        label: t('guestLearning.all'),
                        selected: _category.isEmpty,
                        onTap: () => setState(() => _category = ''),
                      ),
                      ...bundle.categories.map(
                        (category) => _CategoryChip(
                          label: category,
                          selected: _category == category,
                          onTap: () => setState(() => _category = category),
                        ),
                      ),
                    ],
                  ),
                ],
                const SizedBox(height: 14),
                if (visible.isEmpty)
                  AirmiusPanel(
                    child: Text(
                      bundle.items.isEmpty
                          ? t('guestLearning.empty')
                          : t('guestLearning.noMatch'),
                      textAlign: TextAlign.center,
                    ),
                  )
                else
                  ...visible.map(
                    (course) => Padding(
                      padding: const EdgeInsets.only(bottom: 12),
                      child: _CourseCard(
                        course: course,
                        onInterest: () => Navigator.of(context).push(
                          MaterialPageRoute<void>(
                            builder: (_) => PublicInterestScreen(
                              topic: _text(course['title']),
                              kind: 'learning_interest',
                              icon: Icons.school_outlined,
                            ),
                          ),
                        ),
                      ),
                    ),
                  ),
                const SizedBox(height: 2),
                AirmiusPanel(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      Text(
                        t('guestLearning.verifyTitle'),
                        style: Theme.of(context).textTheme.titleMedium
                            ?.copyWith(fontWeight: FontWeight.w900),
                      ),
                      const SizedBox(height: 6),
                      Text(t('guestLearning.verifyBody')),
                      const SizedBox(height: 12),
                      AirmiusTextField(
                        label: t('guestLearning.code'),
                        hint: t('guestLearning.codeHint'),
                        controller: _certificateCode,
                        icon: Icons.verified_outlined,
                        autocorrect: false,
                        textInputAction: TextInputAction.done,
                        onSubmitted: (_) => _verifyCertificate(),
                      ),
                      const SizedBox(height: 10),
                      AirmiusButton(
                        label: t('guestLearning.verify'),
                        icon: Icons.fact_check_outlined,
                        secondary: true,
                        onPressed: _verifyCertificate,
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 12),
                AirmiusButton(
                  label: t('guestLearning.support'),
                  icon: Icons.support_agent_outlined,
                  secondary: true,
                  onPressed: () => Navigator.of(context).push(
                    MaterialPageRoute<void>(
                      builder: (_) => const SupportHelpdeskScreen(),
                    ),
                  ),
                ),
              ],
            ),
          );
        },
      ),
    );
  }

  String _text(Object? value) => value?.toString().trim() ?? '';
}

class _PublicLearningBundle {
  const _PublicLearningBundle({
    this.items = const [],
    this.categories = const [],
  });

  final List<JsonMap> items;
  final List<String> categories;
}

class _CourseCard extends StatelessWidget {
  const _CourseCard({required this.course, required this.onInterest});

  final JsonMap course;
  final VoidCallback onInterest;

  String _text(Object? value, [String fallback = '']) {
    final text = value?.toString().trim() ?? '';
    return text.isEmpty ? fallback : text;
  }

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final title = _text(course['title'], t('guestLearning.untitled'));
    final tutor = course['tutor'] is Map ? _text(course['tutor']['name']) : '';
    final price = course['is_free'] == true
        ? t('guestLearning.free')
        : course['price_cents'] is num
        ? '${((course['price_cents'] as num) / 100).toStringAsFixed(2)} ${_text(course['currency'], 'EUR')}'
        : t('guestLearning.priceOnRequest');
    return AirmiusPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                width: 52,
                height: 52,
                decoration: BoxDecoration(
                  color: Theme.of(
                    context,
                  ).colorScheme.primary.withValues(alpha: 0.12),
                  borderRadius: BorderRadius.circular(15),
                ),
                child: Icon(
                  Icons.school_outlined,
                  color: Theme.of(context).colorScheme.primary,
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      title,
                      style: const TextStyle(fontWeight: FontWeight.w900),
                    ),
                    if (tutor.isNotEmpty) ...[
                      const SizedBox(height: 4),
                      Text(
                        tutor,
                        style: TextStyle(
                          color: Theme.of(context).colorScheme.primary,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                    ],
                  ],
                ),
              ),
              StatusPill(price, color: Theme.of(context).colorScheme.secondary),
            ],
          ),
          if (_text(course['subtitle']).isNotEmpty) ...[
            const SizedBox(height: 10),
            Text(
              _text(course['subtitle']),
              style: const TextStyle(fontWeight: FontWeight.w700),
            ),
          ],
          if (_text(course['description']).isNotEmpty) ...[
            const SizedBox(height: 6),
            Text(_text(course['description'])),
          ],
          const SizedBox(height: 10),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              if (_text(course['level']).isNotEmpty)
                StatusPill(_text(course['level'])),
              StatusPill(
                '${course['lessons_count'] ?? 0} ${t('guestLearning.lessons')}',
              ),
              AirmiusButton(
                label: t('guestLearning.interest'),
                icon: Icons.send_outlined,
                secondary: true,
                onPressed: onInterest,
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _CategoryChip extends StatelessWidget {
  const _CategoryChip({
    required this.label,
    required this.selected,
    required this.onTap,
  });

  final String label;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return FilterChip(
      label: Text(label),
      selected: selected,
      onSelected: (_) => onTap(),
      showCheckmark: false,
    );
  }
}
