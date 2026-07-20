import 'dart:convert';

import 'package:file_picker/file_picker.dart';
import 'package:http/http.dart' as http;
import 'package:http_parser/http_parser.dart';

import 'airmius_api_client.dart';
import 'airmius_api_models.dart';
import 'airmius_upload_retry_policy.dart';
import 'airmius_upload_image_prepare_stub.dart'
    if (dart.library.html) 'airmius_upload_image_prepare_web.dart';

class AirmiusPostUploadService {
  const AirmiusPostUploadService(
    this.client, {
    this.retryPolicy = const AirmiusUploadRetryPolicy(),
  });

  final AirmiusApiClient client;
  final AirmiusUploadRetryPolicy retryPolicy;

  Future<AirmiusPost> upload({
    required String content,
    required String visibility,
    required String postType,
    required String contentOrigin,
    int? clubId,
    int? teamId,
    int? sportId,
    List<int> sportSkillIds = const [],
    PlatformFile? image,
    List<PlatformFile> attachments = const [],
  }) async {
    final promotedImage = image ?? _firstImageAttachment(attachments);
    final remainingAttachments = promotedImage == null
        ? attachments
        : attachments
              .where((attachment) => !identical(attachment, promotedImage))
              .toList();
    final preparedImage = promotedImage == null
        ? null
        : await preparePostImageForUpload(promotedImage);
    if (preparedImage != null) {
      String imagePath;
      try {
        imagePath = await _uploadPostImage(preparedImage);
      } on AirmiusApiException catch (error) {
        if (error.statusCode != 404 && error.statusCode != 405) {
          rethrow;
        }
        imagePath = await _uploadGenericFile(
          preparedImage,
          clubId: clubId,
          teamId: teamId,
        );
      }

      return _upload(
        content: content,
        visibility: visibility,
        postType: postType,
        contentOrigin: contentOrigin,
        clubId: clubId,
        teamId: teamId,
        sportId: sportId,
        sportSkillIds: sportSkillIds,
        imagePath: imagePath,
        attachments: remainingAttachments,
      );
    }

    return _upload(
      content: content,
      visibility: visibility,
      postType: postType,
      contentOrigin: contentOrigin,
      clubId: clubId,
      teamId: teamId,
      sportId: sportId,
      sportSkillIds: sportSkillIds,
      image: null,
      attachments: remainingAttachments,
    );
  }

  Future<String> _uploadGenericFile(
    PlatformFile file, {
    int? clubId,
    int? teamId,
  }) async {
    return retryPolicy.run((_) async {
      final request = http.MultipartRequest('POST', _uri('/api/v1/uploads'))
        ..headers.addAll({
          'Accept': 'application/json',
          'X-Airmius-Locale': client.locale,
          if (client.token != null && client.token!.isNotEmpty)
            'Authorization': 'Bearer ${client.token}',
        });

      if (teamId != null) {
        request.fields['scope'] = 'team';
        request.fields['team_id'] = '$teamId';
      } else if (clubId != null) {
        request.fields['scope'] = 'club';
        request.fields['club_id'] = '$clubId';
      } else {
        request.fields['scope'] = 'user';
      }

      await _addFile(request, 'file', file);

      final streamed = await request.send();
      final body = await streamed.stream.bytesToString();
      if (streamed.statusCode < 200 || streamed.statusCode >= 300) {
        throw AirmiusApiException(
          statusCode: streamed.statusCode,
          body: _diagnosticErrorBody(body: body, request: request, image: file),
          path: '/api/v1/uploads',
        );
      }

      final decoded = jsonDecode(body);
      if (decoded is JsonMap) {
        final data = decoded['data'];
        final path = data is JsonMap ? data['path'] : decoded['path'];
        if (path is String && path.trim().isNotEmpty) return path.trim();

        final url = data is JsonMap ? data['url'] : decoded['url'];
        final pathFromUrl = _uploadPathFromUrl(url);
        if (pathFromUrl != null) return pathFromUrl;
        final rawUrl = url?.toString().trim();
        if (rawUrl != null && rawUrl.startsWith('http')) return rawUrl;
      }

      throw AirmiusApiException(
        statusCode: 422,
        body: jsonEncode({
          'message':
              'Upload erfolgreich, aber die API-Antwort enthielt keinen auswertbaren Bildpfad.',
          'upload_response': body,
        }),
        path: '/api/v1/uploads',
      );
    });
  }

  String? _uploadPathFromUrl(Object? value) {
    final raw = value?.toString().trim();
    if (raw == null || raw.isEmpty) return null;

    final uri = Uri.tryParse(raw);
    final path = uri != null && uri.hasScheme ? uri.path : raw;
    final cleanPath = path.replaceFirst(RegExp(r'^/+'), '');
    if (cleanPath.isEmpty || cleanPath.contains('..')) return null;
    return cleanPath;
  }

  PlatformFile? _firstImageAttachment(List<PlatformFile> attachments) {
    for (final attachment in attachments) {
      if (_isImageFile(attachment)) return attachment;
    }
    return null;
  }

  bool _isImageFile(PlatformFile file) {
    final extension =
        file.extension?.toLowerCase() ??
        file.name.split('.').last.toLowerCase();
    return const {'jpg', 'jpeg', 'png', 'webp', 'gif'}.contains(extension);
  }

  Future<AirmiusPost> _upload({
    required String content,
    required String visibility,
    required String postType,
    required String contentOrigin,
    int? clubId,
    int? teamId,
    int? sportId,
    List<int> sportSkillIds = const [],
    PlatformFile? image,
    String? imagePath,
    List<PlatformFile> attachments = const [],
  }) async {
    final request = http.MultipartRequest('POST', _uri('/api/v1/feed'))
      ..headers.addAll({
        'Accept': 'application/json',
        'X-Airmius-Locale': client.locale,
        if (client.token != null && client.token!.isNotEmpty)
          'Authorization': 'Bearer ${client.token}',
      })
      ..fields['content'] = content
      ..fields['visibility'] = visibility
      ..fields['post_type'] = postType
      ..fields['content_origin'] = contentOrigin;

    if (clubId != null) request.fields['club_id'] = '$clubId';
    if (teamId != null) request.fields['team_id'] = '$teamId';
    if (sportId != null) request.fields['sport_id'] = '$sportId';
    if (imagePath != null && imagePath.trim().isNotEmpty) {
      request.fields['image'] = imagePath.trim();
    }
    for (var index = 0; index < sportSkillIds.length; index++) {
      request.fields['sport_skill_ids[$index]'] = '${sportSkillIds[index]}';
    }

    await _addFile(request, 'image', image);
    for (final attachment in attachments) {
      await _addFile(request, 'attachments[]', attachment);
    }

    final streamed = await request.send();
    final body = await streamed.stream.bytesToString();
    if (streamed.statusCode < 200 || streamed.statusCode >= 300) {
      throw AirmiusApiException(
        statusCode: streamed.statusCode,
        body: _diagnosticErrorBody(
          body: body,
          request: request,
          image: image,
          attachments: attachments,
        ),
        path: '/api/v1/feed',
      );
    }

    final decoded = jsonDecode(body);
    if (decoded is JsonMap && decoded['data'] is JsonMap) {
      return AirmiusPost.fromJson(
        _normalizeMultipartMediaUrls(decoded['data'] as JsonMap),
      );
    }
    if (decoded is JsonMap) {
      return AirmiusPost.fromJson(_normalizeMultipartMediaUrls(decoded));
    }
    throw AirmiusApiException(
      statusCode: streamed.statusCode,
      body: body,
      path: '/api/v1/feed',
    );
  }

  Future<String> _uploadPostImage(PlatformFile image) async {
    return retryPolicy.run((_) async {
      final request = http.MultipartRequest('POST', _uri('/api/v1/post-images'))
        ..headers.addAll({
          'Accept': 'application/json',
          'X-Airmius-Locale': client.locale,
          if (client.token != null && client.token!.isNotEmpty)
            'Authorization': 'Bearer ${client.token}',
        });

      await _addFile(request, 'image', image);

      final streamed = await request.send();
      final body = await streamed.stream.bytesToString();
      if (streamed.statusCode < 200 || streamed.statusCode >= 300) {
        throw AirmiusApiException(
          statusCode: streamed.statusCode,
          body: _diagnosticErrorBody(
            body: body,
            request: request,
            image: image,
          ),
          path: '/api/v1/post-images',
        );
      }

      final decoded = jsonDecode(body);
      if (decoded is JsonMap) {
        final data = decoded['data'];
        final path = data is JsonMap ? data['path'] : decoded['path'];
        if (path is String && path.trim().isNotEmpty) return path.trim();
      }

      throw AirmiusApiException(
        statusCode: streamed.statusCode,
        body: body,
        path: '/api/v1/post-images',
      );
    });
  }

  Future<AirmiusPost> update({
    required int postId,
    required String content,
    required String visibility,
    required String postType,
    required String contentOrigin,
    int? clubId,
    int? teamId,
    int? sportId,
    List<int> sportSkillIds = const [],
    PlatformFile? image,
    List<PlatformFile> attachments = const [],
  }) {
    return _sendMultipart(
      path: '/api/v1/posts/$postId',
      method: 'POST',
      fields: {
        '_method': 'PUT',
        'content': content,
        'visibility': visibility,
        'post_type': postType,
        'content_origin': contentOrigin,
        if (clubId != null) 'club_id': '$clubId',
        if (teamId != null) 'team_id': '$teamId',
        if (sportId != null) 'sport_id': '$sportId',
        for (var index = 0; index < sportSkillIds.length; index++)
          'sport_skill_ids[$index]': '${sportSkillIds[index]}',
      },
      image: image,
      attachments: attachments,
    );
  }

  Future<AirmiusPost> _sendMultipart({
    required String path,
    required String method,
    required Map<String, String> fields,
    PlatformFile? image,
    List<PlatformFile> attachments = const [],
  }) async {
    final request = http.MultipartRequest(method, _uri(path))
      ..headers.addAll({
        'Accept': 'application/json',
        'X-Airmius-Locale': client.locale,
        if (client.token != null && client.token!.isNotEmpty)
          'Authorization': 'Bearer ${client.token}',
      })
      ..fields.addAll(fields);

    await _addFile(request, 'image', image);
    for (final attachment in attachments) {
      await _addFile(request, 'attachments[]', attachment);
    }

    final streamed = await request.send();
    final body = await streamed.stream.bytesToString();
    if (streamed.statusCode < 200 || streamed.statusCode >= 300) {
      throw AirmiusApiException(
        statusCode: streamed.statusCode,
        body: _diagnosticErrorBody(
          body: body,
          request: request,
          image: image,
          attachments: attachments,
        ),
        path: path,
      );
    }

    final decoded = jsonDecode(body);
    if (decoded is JsonMap && decoded['data'] is JsonMap) {
      return AirmiusPost.fromJson(
        _normalizeMultipartMediaUrls(decoded['data'] as JsonMap),
      );
    }
    if (decoded is JsonMap) {
      return AirmiusPost.fromJson(_normalizeMultipartMediaUrls(decoded));
    }
    throw AirmiusApiException(
      statusCode: streamed.statusCode,
      body: body,
      path: path,
    );
  }

  Uri _uri(String path) {
    final base = Uri.parse(client.baseUrl);
    final normalized = path.startsWith('/') ? path.substring(1) : path;
    return base.replace(
      path:
          '${base.path.endsWith('/') ? base.path : '${base.path}/'}$normalized',
    );
  }

  JsonMap _normalizeMultipartMediaUrls(JsonMap json) {
    final normalized = _normalizeMediaUrls(json);
    return normalized is JsonMap ? normalized : json;
  }

  Object? _normalizeMediaUrls(Object? value) {
    if (value is List) return value.map(_normalizeMediaUrls).toList();
    if (value is JsonMap) {
      return value.map((key, item) {
        if (item is String && _isMediaPathKey(key)) {
          return MapEntry(key, _absoluteMediaUrl(item));
        }
        return MapEntry(key, _normalizeMediaUrls(item));
      });
    }
    return value;
  }

  bool _isMediaPathKey(String key) {
    final normalized = key.toLowerCase();
    return normalized.endsWith('_url') ||
        normalized.endsWith('_thumb') ||
        normalized.endsWith('_path') ||
        normalized == 'url' ||
        normalized == 'path';
  }

  String _absoluteMediaUrl(String value) {
    final trimmed = value.trim();
    if (trimmed.isEmpty ||
        trimmed.startsWith('data:image/') ||
        trimmed.startsWith('http://') ||
        trimmed.startsWith('https://')) {
      return trimmed;
    }
    final base = Uri.tryParse(client.baseUrl);
    if (base == null || !base.hasScheme || base.host.isEmpty) return trimmed;
    if (trimmed.startsWith('/')) {
      return base
          .replace(
            path: _withBasePath(base, trimmed),
            query: null,
            fragment: null,
          )
          .toString();
    }
    final cleanPath = trimmed.replaceFirst(RegExp(r'^/+'), '');
    final path =
        cleanPath.startsWith('storage/') ||
            cleanPath.startsWith('build/') ||
            cleanPath.startsWith('images/')
        ? '/$cleanPath'
        : '/storage/$cleanPath';
    return base
        .replace(path: _withBasePath(base, path), query: null, fragment: null)
        .toString();
  }

  String _withBasePath(Uri base, String path) {
    final cleanBase = base.path == '/'
        ? ''
        : base.path.replaceFirst(RegExp(r'/$'), '');
    if (cleanBase.isEmpty || path.startsWith('$cleanBase/')) return path;
    return '$cleanBase$path';
  }

  Future<void> _addFile(
    http.MultipartRequest request,
    String field,
    PlatformFile? file,
  ) async {
    if (file == null) return;
    final bytes = file.bytes;
    if (bytes != null && bytes.isNotEmpty) {
      request.files.add(
        http.MultipartFile.fromBytes(
          field,
          bytes,
          filename: file.name,
          contentType: _contentTypeFor(file),
        ),
      );
      return;
    }

    final path = file.path;
    if (path != null && path.trim().isNotEmpty) {
      request.files.add(
        await http.MultipartFile.fromPath(
          field,
          path,
          filename: file.name,
          contentType: _contentTypeFor(file),
        ),
      );
      return;
    }

    if (bytes == null || bytes.isEmpty) {
      throw AirmiusApiException(
        statusCode: 0,
        body:
            'Die ausgewählte Datei konnte von Flutter nicht gelesen werden. Bitte wähle das Bild erneut aus.',
        path: request.url.path,
      );
    }
  }

  MediaType _contentTypeFor(PlatformFile file) {
    final extension =
        file.extension?.toLowerCase() ??
        file.name.split('.').last.toLowerCase();
    return switch (extension) {
      'jpg' || 'jpeg' => MediaType('image', 'jpeg'),
      'png' => MediaType('image', 'png'),
      'webp' => MediaType('image', 'webp'),
      'gif' => MediaType('image', 'gif'),
      'mp4' => MediaType('video', 'mp4'),
      'mov' => MediaType('video', 'quicktime'),
      'webm' => MediaType('video', 'webm'),
      'ogg' => MediaType('video', 'ogg'),
      'pdf' => MediaType('application', 'pdf'),
      'doc' => MediaType('application', 'msword'),
      'docx' => MediaType(
        'application',
        'vnd.openxmlformats-officedocument.wordprocessingml.document',
      ),
      'xls' => MediaType('application', 'vnd.ms-excel'),
      'xlsx' => MediaType(
        'application',
        'vnd.openxmlformats-officedocument.spreadsheetml.sheet',
      ),
      'txt' => MediaType('text', 'plain'),
      'zip' => MediaType('application', 'zip'),
      _ => MediaType('application', 'octet-stream'),
    };
  }

  String _diagnosticErrorBody({
    required String body,
    required http.MultipartRequest request,
    PlatformFile? image,
    List<PlatformFile> attachments = const [],
  }) {
    final debug = {
      'fields': request.fields,
      'image': image == null ? null : _fileDebug(image),
      'attachments': attachments.map(_fileDebug).toList(),
      'sent_files': request.files
          .map(
            (file) => {
              'field': file.field,
              'filename': file.filename,
              'content_type': file.contentType.toString(),
              'length': file.length,
            },
          )
          .toList(),
    };

    try {
      final decoded = jsonDecode(body);
      if (decoded is Map<String, dynamic>) {
        return jsonEncode({...decoded, 'flutter_upload_debug': debug});
      }
    } catch (_) {
      // Keep the raw body below.
    }

    return jsonEncode({
      'message': body.trim().isNotEmpty
          ? body.trim()
          : 'Der Server hat den Upload abgelehnt, aber keine Fehlerdetails gesendet.',
      'flutter_upload_debug': debug,
    });
  }

  Map<String, Object?> _fileDebug(PlatformFile file) {
    final bytes = file.bytes;
    return {
      'name': file.name,
      'extension': file.extension,
      'size': file.size,
      'bytes_length': bytes?.length,
      'path_available': file.path != null && file.path!.trim().isNotEmpty,
      'content_type': _contentTypeFor(file).toString(),
    };
  }
}
