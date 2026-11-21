import 'dart:convert';

import 'package:file_picker/file_picker.dart';
import 'package:http/http.dart' as http;

import 'airmius_api_client.dart';

Future<AirmiusJson> saveCommerceCampaignWithImage(
  AirmiusApiClient api,
  AirmiusJson payload,
  PlatformFile image, {
  int? campaignId,
  http.Client? transport,
}) async {
  final path = '/api/v1/commerce/seller/campaigns${campaignId == null ? '' : '/$campaignId'}';
  final base = Uri.parse(api.baseUrl);
  var prefix = base.path.replaceFirst(RegExp(r'/+$'), '');
  if (prefix == '/api' || prefix == '/api/v1') prefix = '';
  final request = http.MultipartRequest(
    'POST',
    base.replace(path: '$prefix$path', query: '', fragment: ''),
  )..headers.addAll({
      'Accept': 'application/json',
      'X-Airmius-Locale': api.locale,
      if (api.token != null) 'Authorization': 'Bearer ${api.token}',
    });
  if (campaignId != null) request.fields['_method'] = 'PUT';
  for (final entry in payload.entries) {
    if (entry.value == null || entry.key == 'creative_image_upload') continue;
    request.fields[entry.key] = entry.value is bool
        ? (entry.value == true ? '1' : '0')
        : entry.value.toString();
  }
  if (image.bytes != null) {
    request.files.add(http.MultipartFile.fromBytes(
      'creative_image_upload', image.bytes!, filename: image.name,
    ));
  } else if (image.path != null) {
    request.files.add(await http.MultipartFile.fromPath(
      'creative_image_upload', image.path!, filename: image.name,
    ));
  } else {
    throw StateError('Selected image could not be read');
  }
  final client = transport ?? http.Client();
  try {
    final response = await http.Response.fromStream(
      await client.send(request).timeout(const Duration(seconds: 90)),
    );
    if (response.statusCode < 200 || response.statusCode >= 300) {
      throw AirmiusApiException(
        statusCode: response.statusCode, body: response.body, path: path,
      );
    }
    final decoded = jsonDecode(response.body);
    if (decoded is! AirmiusJson) throw StateError('Invalid campaign response');
    return decoded;
  } finally {
    if (transport == null) client.close();
  }
}
