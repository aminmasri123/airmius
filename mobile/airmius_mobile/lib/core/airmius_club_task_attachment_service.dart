import 'dart:convert';

import 'package:file_picker/file_picker.dart';
import 'package:http/http.dart' as http;
import 'package:http_parser/http_parser.dart';

import 'airmius_api_client.dart';

class AirmiusClubTaskAttachmentService {
  const AirmiusClubTaskAttachmentService({
    required this.baseUrl,
    required this.token,
    this.locale = 'de',
  });

  final String baseUrl;
  final String? token;
  final String locale;

  Future<AirmiusJson> upload({
    required int clubId,
    required int taskId,
    required List<PlatformFile> attachments,
  }) async {
    if (attachments.isEmpty) {
      throw const FormatException('At least one attachment is required');
    }
    final uri = _uri('/api/v1/clubs/$clubId/tasks/$taskId/attachments');
    final request = http.MultipartRequest('POST', uri)
      ..headers['Accept'] = 'application/json'
      ..headers['X-Airmius-Locale'] = locale;
    if (token != null && token!.trim().isNotEmpty) {
      request.headers['Authorization'] = 'Bearer ${token!.trim()}';
    }
    for (final file in attachments) {
      final name = file.name.trim().isEmpty ? 'attachment' : file.name.trim();
      if (file.bytes != null) {
        request.files.add(
          http.MultipartFile.fromBytes(
            'attachments[]',
            file.bytes!,
            filename: name,
            contentType: _mediaType(name),
          ),
        );
      } else if (file.path != null && file.path!.trim().isNotEmpty) {
        request.files.add(
          await http.MultipartFile.fromPath(
            'attachments[]',
            file.path!,
            filename: name,
            contentType: _mediaType(name),
          ),
        );
      }
    }

    final streamed = await request.send();
    final body = await streamed.stream.bytesToString();
    if (streamed.statusCode < 200 || streamed.statusCode >= 300) {
      throw AirmiusApiException(
        statusCode: streamed.statusCode,
        body: body,
        path: uri.path,
      );
    }
    final decoded = jsonDecode(body);
    return decoded is Map<String, dynamic>
        ? decoded
        : <String, dynamic>{'data': decoded};
  }

  Uri _uri(String path) {
    final base = Uri.parse(baseUrl);
    var prefix = base.path.replaceFirst(RegExp(r'/+$'), '');
    if (prefix == '/api' || prefix == '/api/v1') prefix = '';
    return base.replace(
      host: base.host.toLowerCase() == 'app.airmius.com'
          ? 'airmius.com'
          : base.host,
      path: '$prefix$path',
      query: '',
      fragment: '',
    );
  }

  MediaType? _mediaType(String name) {
    final extension = name.contains('.')
        ? name.split('.').last.toLowerCase()
        : '';
    return switch (extension) {
      'jpg' || 'jpeg' => MediaType('image', 'jpeg'),
      'png' => MediaType('image', 'png'),
      'gif' => MediaType('image', 'gif'),
      'webp' => MediaType('image', 'webp'),
      'pdf' => MediaType('application', 'pdf'),
      'csv' => MediaType('text', 'csv'),
      'txt' => MediaType('text', 'plain'),
      'mp4' => MediaType('video', 'mp4'),
      'mov' => MediaType('video', 'quicktime'),
      _ => null,
    };
  }
}
