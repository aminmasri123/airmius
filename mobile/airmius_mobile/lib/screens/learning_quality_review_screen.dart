import 'package:flutter/material.dart';

import '../core/airmius_api_client.dart';
import '../core/airmius_services_scope.dart';

class LearningQualityReviewScreen extends StatefulWidget {
  const LearningQualityReviewScreen({super.key});

  @override
  State<LearningQualityReviewScreen> createState() =>
      _LearningQualityReviewScreenState();
}

class _LearningQualityReviewScreenState
    extends State<LearningQualityReviewScreen> {
  Future<AirmiusJson>? _future;
  String _query = '';
  int _page = 1;

  AirmiusApiClient get _client {
    final services = AirmiusServicesScope.of(context);
    return services.clientForSession(services.authState.session);
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (AirmiusServicesScope.of(
          context,
        ).authState.user?.can('subscriptions.manage') ==
        true) {
      _future ??= _client.learningQualityCourses();
    }
  }

  void _reload() => setState(() {
    _future = _client.learningQualityCourses(query: _query, page: _page);
  });

  Future<void> _review(AirmiusJson course) async {
    final saved = await showDialog<bool>(
      context: context,
      builder: (_) => _QualityDialog(course: course, client: _client),
    );
    if (saved == true && mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Course quality status saved.')),
      );
      _reload();
    }
  }

  @override
  Widget build(BuildContext context) {
    final allowed =
        AirmiusServicesScope.of(
          context,
        ).authState.user?.can('subscriptions.manage') ==
        true;
    return Scaffold(
      appBar: AppBar(title: const Text('Course quality review')),
      body: !allowed
          ? const Center(child: Text('Access denied'))
          : Column(
              children: [
                Padding(
                  padding: const EdgeInsets.all(16),
                  child: TextField(
                    maxLength: 120,
                    decoration: const InputDecoration(
                      labelText: 'Search courses',
                      prefixIcon: Icon(Icons.search),
                    ),
                    textInputAction: TextInputAction.search,
                    onSubmitted: (value) {
                      _query = value;
                      _page = 1;
                      _reload();
                    },
                  ),
                ),
                Expanded(
                  child: FutureBuilder<AirmiusJson>(
                    future: _future,
                    builder: (context, snapshot) {
                      if (snapshot.connectionState == ConnectionState.waiting) {
                        return const Center(child: CircularProgressIndicator());
                      }
                      if (snapshot.hasError) {
                        return Center(
                          child: SingleChildScrollView(
                            child: Column(
                              children: [
                                Text(_error(snapshot.error!)),
                                TextButton(
                                  onPressed: _reload,
                                  child: const Text('Retry'),
                                ),
                              ],
                            ),
                          ),
                        );
                      }
                      final data = snapshot.data!;
                      final courses = (data['data'] as List)
                          .cast<Map<String, dynamic>>();
                      return Column(
                        children: [
                          Expanded(
                            child: courses.isEmpty
                                ? const Center(child: Text('No courses found'))
                                : ListView.builder(
                                    itemCount: courses.length,
                                    itemBuilder: (context, index) {
                                      final course = courses[index];
                                      return ListTile(
                                        title: Text(course['title'] as String),
                                        subtitle: Text(
                                          '${course['tutor']?['name'] ?? ''}\n${_statuses[course['quality_status']] ?? _statuses['pending']}',
                                        ),
                                        trailing: const Icon(
                                          Icons.rate_review_outlined,
                                        ),
                                        onTap: () => _review(course),
                                      );
                                    },
                                  ),
                          ),
                          SafeArea(
                            top: false,
                            child: Row(
                              mainAxisAlignment: MainAxisAlignment.center,
                              children: [
                                IconButton(
                                  tooltip: 'Previous page',
                                  icon: const Icon(Icons.chevron_left),
                                  onPressed: _page > 1
                                      ? () {
                                          _page--;
                                          _reload();
                                        }
                                      : null,
                                ),
                                Text('$_page / ${data['last_page']}'),
                                IconButton(
                                  tooltip: 'Next page',
                                  icon: const Icon(Icons.chevron_right),
                                  onPressed: data['next_page_url'] != null
                                      ? () {
                                          _page++;
                                          _reload();
                                        }
                                      : null,
                                ),
                              ],
                            ),
                          ),
                        ],
                      );
                    },
                  ),
                ),
              ],
            ),
    );
  }
}

const _statuses = {
  'pending': 'Pending',
  'approved': 'Approved',
  'changes_requested': 'Changes requested',
  'rejected': 'Rejected',
};

String _error(Object error) => error is AirmiusApiException
    ? error.userMessage
    : 'Could not complete the request. Please try again.';

class _QualityDialog extends StatefulWidget {
  const _QualityDialog({required this.course, required this.client});
  final AirmiusJson course;
  final AirmiusApiClient client;

  @override
  State<_QualityDialog> createState() => _QualityDialogState();
}

class _QualityDialogState extends State<_QualityDialog> {
  late final TextEditingController _note;
  late String _status;
  late bool _featured;
  bool _saving = false;
  String? _failure;

  @override
  void initState() {
    super.initState();
    _note = TextEditingController(
      text: widget.course['quality_note'] as String? ?? '',
    );
    _status = widget.course['quality_status'] as String? ?? 'pending';
    _featured = widget.course['featured_at'] != null;
  }

  @override
  void dispose() {
    _note.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    setState(() {
      _saving = true;
      _failure = null;
    });
    try {
      await widget.client.updateLearningCourseQuality(
        widget.course['id'] as int,
        {
          'quality_status': _status,
          'quality_note': _note.text,
          'featured': _featured,
        },
      );
      if (mounted) Navigator.pop(context, true);
    } catch (error) {
      if (mounted) {
        setState(() {
          _failure = _error(error);
          _saving = false;
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) => PopScope(
    canPop: !_saving,
    child: AlertDialog(
      title: Text(widget.course['title'] as String),
      content: SizedBox(
        width: 480,
        child: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              if (widget.course['description'] != null)
                Text(widget.course['description'] as String),
              const SizedBox(height: 16),
              DropdownButtonFormField<String>(
                initialValue: _status,
                isExpanded: true,
                decoration: const InputDecoration(labelText: 'Quality status'),
                items: _statuses.entries
                    .map(
                      (entry) => DropdownMenuItem(
                        value: entry.key,
                        child: Text(entry.value),
                      ),
                    )
                    .toList(),
                onChanged: _saving
                    ? null
                    : (value) => setState(() => _status = value!),
              ),
              TextField(
                controller: _note,
                enabled: !_saving,
                maxLength: 5000,
                minLines: 3,
                maxLines: 6,
                decoration: const InputDecoration(labelText: 'Review note'),
              ),
              SwitchListTile(
                contentPadding: EdgeInsets.zero,
                title: const Text('Featured'),
                value: _featured,
                onChanged: _saving
                    ? null
                    : (value) => setState(() => _featured = value),
              ),
              if (_failure != null)
                Text(
                  _failure!,
                  style: TextStyle(color: Theme.of(context).colorScheme.error),
                ),
            ],
          ),
        ),
      ),
      actions: [
        TextButton(
          onPressed: _saving ? null : () => Navigator.pop(context, false),
          child: const Text('Cancel'),
        ),
        FilledButton(
          onPressed: _saving ? null : _save,
          child: Text(_saving ? 'Saving...' : 'Save'),
        ),
      ],
    ),
  );
}
