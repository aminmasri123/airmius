import 'dart:convert';
import 'package:file_picker/file_picker.dart';
import 'package:http/http.dart' as http;
import 'package:http_parser/http_parser.dart';
import 'airmius_api_client.dart';

const learningVideoExtensions = ['mp4', 'webm', 'mov', 'm4v', 'mkv', 'avi'];
const learningFileExtensions = [
  'png',
  'jpg',
  'jpeg',
  'webp',
  'gif',
  'pdf',
  'doc',
  'docx',
  'ppt',
  'pptx',
  'xls',
  'xlsx',
  'csv',
  'txt',
  ...learningVideoExtensions,
  'mp3',
  'wav',
  'ogg',
  'm4a',
];

Future<AirmiusJson> uploadLearningLessonAsset({
  required AirmiusApiClient api,
  required int courseId,
  required PlatformFile file,
  required String purpose,
  void Function(double)? onProgress,
  http.Client? transport,
}) async {
  final extension = file.extension?.toLowerCase();
  final allowed = purpose == 'assessment_image' ? ['jpg','jpeg','png','webp','gif'] : purpose == 'lesson_video'
      ? learningVideoExtensions
      : learningFileExtensions;
  if (!['lesson_video', 'lesson_attachment', 'assessment_image'].contains(purpose) ||
      !allowed.contains(extension) ||
      file.size <= 0 ||
      file.size > (purpose == 'assessment_image' ? 10 : 500) * 1024 * 1024) {
    throw ArgumentError('Unsupported learning file');
  }
  final base = Uri.parse(api.baseUrl);
  var prefix = base.path.replaceFirst(RegExp(r'/+$'), '');
  if (prefix == '/api' || prefix == '/api/v1') prefix = '';
  final path = '/api/v1/learning-studio/courses/$courseId/uploads';
  final request =
      http.MultipartRequest(
          'POST',
          base.replace(path: '$prefix$path', query: '', fragment: ''),
        )
        ..headers.addAll({
          'Accept': 'application/json',
          'X-Airmius-Locale': api.locale,
          if (api.token != null) 'Authorization': 'Bearer ${api.token}',
        })
        ..fields['purpose'] = purpose;
  final mime = switch (extension) {
    'png' || 'gif' || 'webp' => 'image/$extension',
    'jpg' || 'jpeg' => 'image/jpeg',
    'mp4' || 'm4v' => 'video/mp4',
    'webm' => 'video/webm',
    'mov' => 'video/quicktime',
    'mkv' => 'video/x-matroska',
    'avi' => 'video/x-msvideo',
    'pdf' => 'application/pdf',
    'txt' => 'text/plain',
    'csv' => 'text/csv',
    'mp3' => 'audio/mpeg',
    'wav' => 'audio/wav',
    'ogg' => 'audio/ogg',
    'm4a' => 'audio/mp4',
    _ => 'application/octet-stream',
  };
  final stream =
      file.readStream ??
      (file.bytes != null ? Stream<List<int>>.value(file.bytes!) : null);
  if (stream != null) {
    var sent = 0;
    request.files.add(
      http.MultipartFile(
        'file',
        stream.map((chunk) {
          sent += chunk.length;
          onProgress?.call((sent / file.size).clamp(0, 1));
          return chunk;
        }),
        file.size,
        filename: file.name,
        contentType: MediaType.parse(mime),
      ),
    );
  } else if (file.path != null) {
    request.files.add(
      await http.MultipartFile.fromPath(
        'file',
        file.path!,
        filename: file.name,
        contentType: MediaType.parse(mime),
      ),
    );
  } else {
    throw StateError('File is unreadable');
  }
  final client = transport ?? http.Client();
  try {
    final response = await http.Response.fromStream(
      await client.send(request).timeout(const Duration(minutes: 10)),
    );
    if (response.statusCode < 200 || response.statusCode >= 300) {
      throw AirmiusApiException(
        statusCode: response.statusCode,
        body: response.body,
        path: path,
      );
    }
    final result = Map<String, dynamic>.from(jsonDecode(response.body) as Map);
    if (result['url'] is! String || (result['url'] as String).isEmpty) {
      throw const FormatException('Missing asset URL');
    }
    return result;
  } finally {
    if (transport == null) client.close();
  }
}
