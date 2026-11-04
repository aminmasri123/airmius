import 'dart:convert';
import '../lib/core/airmius_api_client.dart';
import '../lib/core/airmius_api_models.dart';
import '../lib/core/airmius_deep_links.dart';
class Transport extends AirmiusApiTransport {
  Transport(this.link);
  final String link;
  @override
  Future<AirmiusApiResponse> send(AirmiusApiRequest request) async => AirmiusApiResponse(statusCode: 200, body: jsonEncode({'data': [{'id': 1, 'type': 'role.application_review_requested', 'data': {'mobile_url': link, 'application_id': 4, 'application_type': 'trainer'}}]}));
}
Future<void> main() async {
  for (final link in ['/admin/trainer-applications?application_id=4', 'https://airmius.com/admin/trainer-applications?application_id=4', 'airmius://admin/trainer-applications?application_id=4']) {
    final response = await AirmiusApiClient(transport: Transport(link), baseUrl: 'https://airmius.com').notifications();
    final notification = AirmiusNotification.fromJson((response['data'] as List).first as JsonMap);
    final target = const AirmiusDeepLinkResolver().resolve(notification.actionUrl!);
    if (target.type != AirmiusDeepLinkTargetType.adminTrainerApplications || target.id != 4 || !target.requiresAuth) throw StateError('API pipeline failed: ${notification.actionUrl} -> ${target.path}');
    print('OK API -> notification -> authenticated trainer application 4: $link');
  }
}
