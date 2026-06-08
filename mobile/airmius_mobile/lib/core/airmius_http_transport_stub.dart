import 'airmius_api_client.dart';

class AirmiusHttpTransport implements AirmiusApiTransport {
  const AirmiusHttpTransport({required this.baseUrl});

  final String baseUrl;

  @override
  Future<AirmiusApiResponse> send(AirmiusApiRequest request) async {
    return const AirmiusApiResponse(statusCode: 501, body: '{"error":"http_transport_not_supported"}');
  }
}
