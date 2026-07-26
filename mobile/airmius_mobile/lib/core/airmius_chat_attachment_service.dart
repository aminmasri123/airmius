import 'dart:convert';

import 'package:file_picker/file_picker.dart';
import 'package:http/http.dart' as http;
import 'package:http_parser/http_parser.dart';

import 'airmius_api_client.dart';

/// Sends a chat message and its selected files in one authenticated multipart
/// request. Chat attachments must not be uploaded through a public URL or
/// queued as plain JSON because the server scopes them to the conversation.
class AirmiusChatAttachmentService {
  const AirmiusChatAttachmentService({
    required this.baseUrl,
    required this.token,
    this.locale = 'de',
  });

  final String baseUrl;
  final String? token;
  final String locale;

  Future<AirmiusJson> send({
    required int conversationId,
    String? message,
    List<PlatformFile> attachments = const [],
  }) async {
    final origin = Uri.tryParse(baseUrl);
    if (origin == null || !origin.hasScheme || origin.host.isEmpty) {
      throw const FormatException('Invalid Airmius API base URL');
    }
    final uri = origin.replace(
      path: _joinPath(
        origin.path,
        '/api/v1/chat/conversations/$conversationId/messages',
      ),
      query: null,
      fragment: null,
    );
    final request = http.MultipartRequest('POST', uri)
      ..headers['Accept'] = 'application/json'
      ..headers['X-Airmius-Locale'] = locale;
    if (token != null && token!.trim().isNotEmpty) {
      request.headers['Authorization'] = 'Bearer ${token!.trim()}';
    }
    if (message != null && message.trim().isNotEmpty) {
      request.fields['message'] = message.trim();
    }
    for (final file in attachments) {
      final name = file.name.trim().isEmpty ? 'attachment' : file.name.trim();
      final mediaType = _mediaType(name);
      if (file.bytes != null) {
        request.files.add(
          http.MultipartFile.fromBytes(
            'attachments[]',
            file.bytes!,
            filename: name,
            contentType: mediaType,
          ),
        );
      } else if (file.path != null && file.path!.trim().isNotEmpty) {
        request.files.add(
          await http.MultipartFile.fromPath(
            'attachments[]',
            file.path!,
            filename: name,
            contentType: mediaType,
          ),
        );
      }
    }
    if (request.fields.isEmpty && request.files.isEmpty) {
      throw const FormatException('A chat message or attachment is required');
    }

    final streamed = await request.send();
    final body = await streamed.stream.bytesToString();
    if (streamed.statusCode < 200 || streamed.statusCode >= 300) {
      throw AirmiusApiException(
        statusCode: streamed.statusCode,
        body: body,
        path: '/api/v1/chat/conversations/$conversationId/messages',
      );
    }
    final decoded = jsonDecode(body);
    return decoded is Map<String, dynamic>
        ? decoded
        : <String, dynamic>{'data': decoded};
  }

  String _joinPath(String base, String suffix) {
    final cleanBase = base == '/' ? '' : base.replaceFirst(RegExp(r'/+$'), '');
    return '$cleanBase${suffix.startsWith('/') ? suffix : '/$suffix'}';
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
      'txt' => MediaType('text', 'plain'),
      'csv' => MediaType('text', 'csv'),
      'mp4' => MediaType('video', 'mp4'),
      'mov' => MediaType('video', 'quicktime'),
      _ => null,
    };
  }
}
