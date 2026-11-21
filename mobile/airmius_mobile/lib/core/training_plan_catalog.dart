import 'airmius_api_models.dart';

/// Read every authorized page so local search/status filters never stop at 30 plans.
Future<JsonMap> loadTrainingPlanCatalog(Future<JsonMap> Function(int page) fetch) =>
    loadTrainingCatalog(fetch);

Future<JsonMap> loadTrainingCatalog(
  Future<JsonMap> Function(int page) fetch,
) async {
  JsonMap result = {};
  final items = <dynamic>[];
  var page = 1;
  while (true) {
    final response = await fetch(page);
    result = {...response};
    items.addAll(
      response['data'] is List ? response['data'] as List : const [],
    );
    final meta = response['meta'];
    final last = meta is Map
        ? int.tryParse('${meta['last_page']}') ?? page
        : page;
    if (page >= last) break;
    page++;
  }
  return {...result, 'data': items};
}
