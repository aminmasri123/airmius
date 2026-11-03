import 'package:flutter_test/flutter_test.dart';
import 'package:airmius/screens/sport_route_preview_page.dart';

void main() {
  Map<String, dynamic> proposal(String status, List<dynamic> coordinates) => {
    'metrics': {'routing_status': status},
    'route_geometry': {'type': 'LineString', 'coordinates': coordinates},
  };
  test('preview retains intermediate geometry and closes a round course', () {
    final route = proposal('routed', [
      [7, 49],
      [7.1, 49.1],
      [7.2, 49],
      [7, 49],
    ]);
    expect(isUsableSportRoute(route), isTrue);
    final points = sportRoutePoints(route);
    expect(points.length, 4);
    expect(points[1].longitude, 7.1);
    expect(points.last, points.first);
  });
  test('A to B is valid without returning to start', () {
    expect(
      isUsableSportRoute(
        proposal('routed', [
          [7, 49],
          [7.1, 49.1],
        ]),
      ),
      isTrue,
    );
  });
  test('estimated lines, empty and invalid routes cannot be saved', () {
    for (final route in [
      proposal('fallback', [
        [7, 49],
        [8, 50],
      ]),
      proposal('estimated', [
        [7, 49],
        [8, 50],
      ]),
      proposal('routed', []),
      proposal('routed', [
        [7, 49],
        [7, 49],
      ]),
      proposal('routed', [
        [7, 49],
        [7, 91],
      ]),
      proposal('routed', [
        [7, 49],
        [double.nan, 50],
      ]),
      proposal('routed', [
        [7, 49],
        ['invalid', 50],
      ]),
    ]) {
      expect(isUsableSportRoute(route), isFalse);
    }
  });
}
