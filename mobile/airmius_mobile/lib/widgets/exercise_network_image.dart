import 'package:flutter/material.dart';
import '../core/airmius_services_scope.dart';

/// Keep credentials on the configured API origin and training-image routes only.
Map<String, String> exerciseImageHeaders(
  String url,
  String apiBaseUrl,
  String? token,
) {
  final base = Uri.tryParse(apiBaseUrl);
  if (base == null ||
      !['http', 'https'].contains(base.scheme) ||
      base.host.isEmpty) {
    return const {};
  }
  final target = Uri.tryParse(url);
  final image = target == null ? null : base.resolveUri(target);
  if (image == null ||
      token == null ||
      token.isEmpty ||
      !['http', 'https'].contains(image.scheme) ||
      image.userInfo.isNotEmpty ||
      image.origin != base.origin ||
      !RegExp(
        r'^/api/v1/training/(?:exercises|items)/\d+/images/[^/]+$',
      ).hasMatch(image.path)) {
    return const {};
  }
  return {'Authorization': 'Bearer $token'};
}

class ExerciseNetworkImage extends StatelessWidget {
  const ExerciseNetworkImage({
    super.key,
    required this.url,
    required this.height,
    this.excludeFromSemantics = false,
    this.width,
    this.fit = BoxFit.contain,
    this.errorBuilder,
  });
  final String url;
  final double height;
  final bool excludeFromSemantics;
  final double? width;
  final BoxFit fit;
  final ImageErrorWidgetBuilder? errorBuilder;

  @override
  Widget build(BuildContext context) {
    final services = context
        .dependOnInheritedWidgetOfExactType<AirmiusServicesScope>()
        ?.container;
    final baseUrl = services?.environment.apiBaseUrl;
    var resolved = baseUrl == null
        ? url
        : Uri.parse(baseUrl).resolve(url).toString();
    final token = services?.authState.session?.token;
    final headers = baseUrl == null
        ? const <String, String>{}
        : exerciseImageHeaders(resolved, baseUrl, token);
    if (headers.isNotEmpty) {
      // NetworkImage's cache key is the URL, not its Authorization header.
      final uri = Uri.parse(resolved);
      resolved = uri
          .replace(
            queryParameters: {
              ...uri.queryParameters,
              '_viewer': '${services?.authState.user?.id}',
            },
          )
          .toString();
    }
    return Image.network(
      resolved,
      headers: headers,
      height: height,
      width: width,
      fit: fit,
      excludeFromSemantics: excludeFromSemantics,
      errorBuilder:
          errorBuilder ??
          (_, _, _) => const SizedBox(
            height: 80,
            child: Icon(Icons.broken_image_outlined),
          ),
    );
  }
}
