import 'dart:convert';

import 'package:file_picker/file_picker.dart';
import 'package:http/http.dart' as http;

import 'airmius_api_client.dart';
import 'airmius_api_models.dart';

class AirmiusStoryUploadService {
  const AirmiusStoryUploadService(this.client);

  final AirmiusApiClient client;

  Future<AirmiusStory?> pickAndUpload({
    required String visibility,
    String? caption,
    int? clubId,
    int? teamId,
  }) async {
    final picked = await FilePicker.platform.pickFiles(
      type: FileType.custom,
      allowedExtensions: ['jpg', 'jpeg', 'png', 'webp', 'gif', 'mp4', 'mov', 'webm', 'ogg'],
      withData: true,
    );
    final file = picked?.files.single;
    final bytes = file?.bytes;
    if (file == null || bytes == null) return null;

    final request = http.MultipartRequest('POST', _uri('/api/v1/stories'))
      ..headers.addAll({
        'Accept': 'application/json',
        'X-Airmius-Locale': client.locale,
        if (client.token != null && client.token!.isNotEmpty) 'Authorization': 'Bearer ${client.token}',
      })
      ..fields['visibility'] = visibility
      ..fields['publisher_type'] = 'user';

    if (caption != null && caption.trim().isNotEmpty) {
      request.fields['caption'] = caption.trim();
    }
    if (clubId != null) {
      request.fields['club_id'] = '$clubId';
      request.fields['publisher_type'] = 'club';
    }
    if (teamId != null) {
      request.fields['team_id'] = '$teamId';
      request.fields['publisher_type'] = 'team';
    }

    request.files.add(http.MultipartFile.fromBytes('media', bytes, filename: file.name));

    final streamed = await request.send();
    final body = await streamed.stream.bytesToString();
    if (streamed.statusCode < 200 || streamed.statusCode >= 300) {
      throw AirmiusApiException(statusCode: streamed.statusCode, body: body, path: '/api/v1/stories');
    }

    final decoded = jsonDecode(body);
    if (decoded is JsonMap && decoded['data'] is JsonMap) {
      return AirmiusStory.fromJson(_normalizeMultipartMediaUrls(decoded['data'] as JsonMap));
    }
    if (decoded is JsonMap) return AirmiusStory.fromJson(_normalizeMultipartMediaUrls(decoded));
    throw AirmiusApiException(statusCode: streamed.statusCode, body: body, path: '/api/v1/stories');
  }

  Uri _uri(String path) {
    final base = Uri.parse(client.baseUrl);
    final normalized = path.startsWith('/') ? path.substring(1) : path;
    return base.replace(path: '${base.path.endsWith('/') ? base.path : '${base.path}/'}$normalized');
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
        normalized == 'path' ||
        normalized == 'image';
  }

  String _absoluteMediaUrl(String value) {
    final trimmed = value.trim();
    if (trimmed.isEmpty || trimmed.startsWith('data:image/') || trimmed.startsWith('http://') || trimmed.startsWith('https://')) return trimmed;
    final base = Uri.tryParse(client.baseUrl);
    if (base == null || !base.hasScheme || base.host.isEmpty) return trimmed;
    if (trimmed.startsWith('/')) return base.replace(path: trimmed, query: null, fragment: null).toString();
    final cleanPath = trimmed.replaceFirst(RegExp(r'^/+'), '');
    final path = cleanPath.startsWith('storage/') || cleanPath.startsWith('build/') || cleanPath.startsWith('images/') ? '/$cleanPath' : '/storage/$cleanPath';
    return base.replace(path: path, query: null, fragment: null).toString();
  }
}
