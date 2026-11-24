import 'dart:convert';
import 'dart:math';
import 'airmius_api_client.dart';

/// Guest state is kept for the lifetime of the marketplace session.
/// Quotes and orders are always calculated by the server.
class GuestMarketplaceTransport implements AirmiusApiTransport {
  GuestMarketplaceTransport(this.inner);
  final AirmiusApiTransport inner;
  final Map<int, int> _items = {};
  final Map<int, AirmiusJson> _wishlist = {};
  final Map<int, AirmiusJson> _orders = {};
  String? _checkoutKey;

  AirmiusApiResponse _response(AirmiusJson body) =>
      AirmiusApiResponse(statusCode: 200, body: jsonEncode(body));

  Future<AirmiusApiResponse> _public(
    AirmiusApiRequest request,
    String path, {
    String? method,
    AirmiusJson? body,
    AirmiusHeaders extraHeaders = const {},
  }) => inner.send(
    AirmiusApiRequest(
      method: method ?? request.method,
      path: path,
      body: body ?? request.body,
      query: request.query,
      headers: {...request.headers, ...extraHeaders}..remove('Authorization'),
    ),
  );

  List<AirmiusJson> _entries(Map<int, int> items) => [
    for (final entry in items.entries)
      {'product_id': entry.key, 'quantity': entry.value},
  ];

  AirmiusJson _guestOrder(AirmiusJson order) => {
    ...order,
    'payment_action': {
      ...?order['payment_action'] as Map<String, dynamic>?,
      'can_change_method': false,
    },
    'support': {
      ...?order['support'] as Map<String, dynamic>?,
      'can_cancel': false,
      'can_report_issue': false,
      'can_request_return': false,
    },
  };

  @override
  Future<AirmiusApiResponse> send(AirmiusApiRequest request) async {
    final path = request.path;
    if (path.startsWith('/api/v1/commerce/products')) {
      final wish = RegExp(
        r'^/api/v1/commerce/products/(\d+)/wishlist$',
      ).firstMatch(path);
      if (wish != null) {
        final id = int.parse(wish[1]!);
        if (request.method == 'DELETE') {
          _wishlist.remove(id);
        } else {
          final response = await _public(
            request,
            '/api/v1/public/commerce/products/$id',
            method: 'GET',
          );
          if (!response.ok) return response;
          _wishlist[id] = {
            ...Map<String, dynamic>.from(response.json['data'] as Map),
            'viewer': {'is_wishlisted': true},
          };
        }
        return _response({
          'data': {
            'is_wishlisted': _wishlist.containsKey(id),
            'wishlist_count': _wishlist.containsKey(id) ? 1 : 0,
          },
        });
      }
      final response = await _public(
        request,
        path.replaceFirst('/commerce/', '/public/commerce/'),
      );
      if (!response.ok) return response;
      final json = response.json;
      AirmiusJson decorate(Map item) => {
        ...Map<String, dynamic>.from(item),
        'viewer': {'is_wishlisted': _wishlist.containsKey(item['id'])},
      };
      return _response({
        ...json,
        'data': json['data'] is List
            ? (json['data'] as List)
                  .map((item) => decorate(item as Map))
                  .toList()
            : json['data'] is Map
            ? decorate(json['data'] as Map)
            : json['data'],
      });
    }
    if (path == '/api/v1/commerce/wishlist') {
      return _response({'data': _wishlist.values.toList()});
    }
    if (path == '/api/v1/commerce/orders') {
      return _response({'data': _orders.values.toList()});
    }
    final orderMatch = RegExp(
      r'^/api/v1/commerce/orders/(\d+)$',
    ).firstMatch(path);
    if (orderMatch != null) {
      final id = int.parse(orderMatch[1]!);
      final token = _orders[id]?['guest_access_token'];
      if (token != null) {
        final response = await _public(
          request,
          '/api/v1/public/commerce/orders/$id/$token',
        );
        if (!response.ok) return response;
        final order = _guestOrder(
          Map<String, dynamic>.from(response.json['data'] as Map),
        );
        _orders[id] = {...order, 'guest_access_token': token};
        return _response({'data': _orders[id]});
      }
    }
    if (path == '/api/v1/commerce/cart/checkout') {
      _checkoutKey ??= List.generate(
        24,
        (_) => Random.secure().nextInt(256).toRadixString(16).padLeft(2, '0'),
      ).join();
      final response = await _public(
        request,
        '/api/v1/public/commerce/cart/checkout',
        body: {...?request.body, 'items': _entries(_items)},
        extraHeaders: {'Idempotency-Key': _checkoutKey!},
      );
      if (!response.ok) {
        if (response.statusCode == 422) _checkoutKey = null;
        return response;
      }
      final order = _guestOrder(
        Map<String, dynamic>.from(response.json['data'] as Map),
      );
      _orders[order['id'] as int] = order;
      _items.clear();
      _checkoutKey = null;
      return _response({'data': order});
    }
    if (path.startsWith('/api/v1/commerce/cart')) {
      final proposed = Map<int, int>.from(_items);
      final match = RegExp(r'/items/(\d+)$').firstMatch(path);
      if (match != null) {
        final id = int.parse(match[1]!);
        final quantity = request.body?['quantity'] as int? ?? 1;
        if (request.method == 'DELETE') {
          proposed.remove(id);
        } else {
          proposed[id] = request.method == 'POST'
              ? (proposed[id] ?? 0) + quantity
              : quantity;
        }
      }
      final response = await _public(
        request,
        '/api/v1/public/commerce/cart',
        method: 'POST',
        body: {...request.query, 'items': _entries(proposed)},
      );
      if (!response.ok) return response;
      if (match != null) {
        _items
          ..clear()
          ..addAll(proposed);
        _checkoutKey = null;
        return _response({'data': (response.json['data'] as Map)['cart']});
      }
      return response;
    }
    return const AirmiusApiResponse(
      statusCode: 403,
      body: '{"message":"Bitte melde dich für diese Kontofunktion an."}',
    );
  }
}
