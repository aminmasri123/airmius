import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'package:http_parser/http_parser.dart';

import '../core/airmius_api_client.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';

class AdminMediaScreen extends StatefulWidget {
  const AdminMediaScreen({super.key});
  @override
  State<AdminMediaScreen> createState() => _MediaState();
}

class _MediaState extends State<AdminMediaScreen> {
  Future<AirmiusJson>? _future;
  final Map<String, TextEditingController> _sources = {};
  final List<TextEditingController> _slides = [];
  final Map<String, PlatformFile> _uploads = {};
  bool _busy = false;
  String? _error;
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

  void _disposeFields() {
    for (final field in [..._sources.values, ..._slides]) {
      field.dispose();
    }
    _sources.clear();
    _slides.clear();
  }

  @override
  void dispose() {
    _disposeFields();
    super.dispose();
  }

  Future<AirmiusJson> _load() async {
    final data = Map<String, dynamic>.from(
      (await _client.adminMediaGuidelines())['data'] as Map,
    );
    if (!mounted) return data;
    _disposeFields();
    for (final item in (data['visuals'] as List? ?? []).whereType<Map>()) {
      _sources['${item['key']}'] = TextEditingController(
        text: '${item['source'] ?? ''}',
      );
    }
    for (final item in (data['loginSlider'] as List? ?? []).whereType<Map>()) {
      _slides.add(TextEditingController(text: '${item['source'] ?? ''}'));
    }
    return data;
  }

  Future<void> _pick(String key) async {
    final result = await FilePicker.platform.pickFiles(
      type: FileType.custom,
      allowedExtensions: ['jpg', 'jpeg', 'png', 'webp'],
      withData: true,
    );
    if (!mounted || result == null) return;
    final file = result.files.single;
    if (file.bytes == null || file.size > 8 * 1024 * 1024) {
      setState(() => _error = t('adminNative.imageSize'));
      return;
    }
    setState(() {
      _uploads[key] = file;
      _error = null;
    });
  }

  Future<void> _save() async {
    setState(() {
      _busy = true;
      _error = null;
    });
    final transport = http.Client();
    try {
      final api = _client;
      final base = Uri.parse(api.baseUrl);
      var prefix = base.path.replaceFirst(RegExp(r'/+$'), '');
      if (prefix == '/api' || prefix == '/api/v1') prefix = '';
      const path = '/api/v1/admin/media-guidelines/visuals';
      final uri = base.replace(
        host: base.host == 'app.airmius.com' ? 'airmius.com' : base.host,
        path: '$prefix$path',
        query: '',
        fragment: '',
      );
      final request = http.MultipartRequest('POST', uri)
        ..headers.addAll({
          'Accept': 'application/json',
          'X-Airmius-Locale': api.locale,
          if (api.token != null) 'Authorization': 'Bearer ${api.token}',
        });
      for (final entry in _sources.entries) {
        request.fields['sources[${entry.key}]'] = entry.value.text.trim();
      }
      for (var index = 0; index < _slides.length; index++) {
        request.fields['login_slider_sources[$index]'] = _slides[index].text
            .trim();
      }
      for (final entry in _uploads.entries) {
        final extension = entry.value.extension?.toLowerCase();
        request.files.add(
          http.MultipartFile.fromBytes(
            entry.key.startsWith('slide_')
                ? 'login_slider_uploads[]'
                : 'uploads[${entry.key}]',
            entry.value.bytes!,
            filename: entry.value.name,
            contentType: MediaType(
              'image',
              extension == 'jpg' ? 'jpeg' : extension ?? 'jpeg',
            ),
          ),
        );
      }
      final response = await http.Response.fromStream(
        await transport.send(request).timeout(const Duration(seconds: 60)),
      ).timeout(const Duration(seconds: 60));
      if (response.statusCode < 200 || response.statusCode >= 300) {
        throw AirmiusApiException(
          statusCode: response.statusCode,
          body: response.body,
          path: path,
        );
      }
      if (mounted) {
        _uploads.clear();
        setState(() => _future = _load());
      }
    } catch (error) {
      if (mounted) {
        setState(
          () => _error = error is AirmiusApiException
              ? error.userMessage
              : t('adminNative.uploadFailed'),
        );
      }
    } finally {
      transport.close();
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: Text(t('adminNative.media'))),
    body: FutureBuilder<AirmiusJson>(
      future: _future,
      builder: (context, snapshot) {
        if (snapshot.connectionState != ConnectionState.done) {
          return const Center(child: CircularProgressIndicator());
        }
        if (snapshot.hasError) {
          return Center(
            child: Text(
              snapshot.error is AirmiusApiException
                  ? (snapshot.error as AirmiusApiException).userMessage
                  : t('platformAdmin.loadFailed'),
            ),
          );
        }
        final data = snapshot.data ?? {};
        return ListView(
          padding: const EdgeInsets.all(16),
          children: [
            if (_error != null)
              Text(
                _error!,
                style: TextStyle(color: Theme.of(context).colorScheme.error),
              ),
            Text(
              t('adminNative.loginSlides'),
              style: Theme.of(context).textTheme.titleLarge,
            ),
            for (var index = 0; index < _slides.length; index++)
              Padding(
                padding: const EdgeInsets.symmetric(vertical: 8),
                child: Row(
                  children: [
                    Expanded(
                      child: TextField(
                        controller: _slides[index],
                        enabled: !_busy,
                        decoration: InputDecoration(
                          labelText:
                              '${t('adminNative.imageSource')} ${index + 1}',
                        ),
                      ),
                    ),
                    IconButton(
                      tooltip: t('common.delete'),
                      onPressed: _busy
                          ? null
                          : () {
                              final field = _slides.removeAt(index);
                              setState(() {});
                              WidgetsBinding.instance.addPostFrameCallback(
                                (_) => field.dispose(),
                              );
                            },
                      icon: const Icon(Icons.delete_outline),
                    ),
                  ],
                ),
              ),
            Wrap(
              spacing: 8,
              children: [
                TextButton.icon(
                  onPressed: _busy
                      ? null
                      : () => setState(
                          () => _slides.add(TextEditingController()),
                        ),
                  icon: const Icon(Icons.add),
                  label: Text(t('adminNative.imageSource')),
                ),
                TextButton.icon(
                  onPressed: _busy
                      ? null
                      : () => _pick(
                          'slide_${DateTime.now().microsecondsSinceEpoch}',
                        ),
                  icon: const Icon(Icons.upload_outlined),
                  label: Text(t('adminNative.upload')),
                ),
              ],
            ),
            for (final entry in _uploads.entries.where(
              (entry) => entry.key.startsWith('slide_'),
            ))
              ListTile(
                title: Text(entry.value.name),
                trailing: IconButton(
                  tooltip: t('common.delete'),
                  onPressed: _busy
                      ? null
                      : () => setState(() => _uploads.remove(entry.key)),
                  icon: const Icon(Icons.close),
                ),
              ),
            const Divider(height: 32),
            for (final item
                in (data['visuals'] as List? ?? []).whereType<Map>()) ...[
              Text(
                '${item['label']}',
                style: Theme.of(context).textTheme.titleMedium,
              ),
              Text('${item['recommended_size']} · ${item['ratio']}'),
              if ('${item['url'] ?? ''}'.isNotEmpty)
                Padding(
                  padding: const EdgeInsets.symmetric(vertical: 12),
                  child: SizedBox(
                    height: 120,
                    child: Image.network(
                      '${item['url']}',
                      fit: BoxFit.contain,
                      errorBuilder: (_, _, _) =>
                          const Icon(Icons.broken_image_outlined),
                    ),
                  ),
                ),
              TextField(
                controller: _sources['${item['key']}'],
                enabled: !_busy,
                decoration: InputDecoration(
                  labelText: t('adminNative.imageSource'),
                ),
              ),
              TextButton.icon(
                onPressed: _busy ? null : () => _pick('${item['key']}'),
                icon: const Icon(Icons.upload_outlined),
                label: Text(
                  _uploads['${item['key']}']?.name ?? t('adminNative.upload'),
                ),
              ),
              const Divider(height: 32),
            ],
            FilledButton.icon(
              onPressed: _busy ? null : _save,
              icon: const Icon(Icons.save_outlined),
              label: Text(t('common.save')),
            ),
            if (_busy) const LinearProgressIndicator(),
            const SizedBox(height: 24),
            for (final item
                in (data['guidelines'] as List? ?? []).whereType<Map>())
              ExpansionTile(
                title: Text('${item['name']}'),
                subtitle: Text('${item['dimensions']} · ${item['formats']}'),
                children: [
                  ListTile(
                    title: Text('${item['note']}'),
                    subtitle: Text('${item['max_size']}'),
                  ),
                ],
              ),
          ],
        );
      },
    ),
  );
}
