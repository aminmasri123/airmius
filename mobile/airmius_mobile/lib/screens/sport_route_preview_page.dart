import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:latlong2/latlong.dart';
import '../widgets/assisted_sport_location.dart';

List<LatLng> sportRoutePoints(Map<String, dynamic> proposal) {
  final geometry = proposal['route_geometry'];
  if (geometry is! Map || geometry['type'] != 'LineString') return [];
  final coordinates = geometry['coordinates'];
  if (coordinates is! List) return [];
  final points = <LatLng>[];
  for (final coordinate in coordinates) {
    if (coordinate is! List || coordinate.length < 2) return [];
    final lng = coordinate[0];
    final lat = coordinate[1];
    if (lng is! num ||
        lat is! num ||
        !lng.isFinite ||
        !lat.isFinite ||
        lng.abs() > 180 ||
        lat.abs() > 90) {
      return [];
    }
    points.add(LatLng(lat.toDouble(), lng.toDouble()));
  }
  return points;
}

bool isUsableSportRoute(Map<String, dynamic> proposal) {
  final metrics = proposal['metrics'];
  if (metrics is! Map || metrics['routing_status'] != 'routed') return false;
  final points = sportRoutePoints(proposal);
  return points.length >= 2 && points.any((point) => point != points.first);
}

class SportRoutePreviewPage extends StatefulWidget {
  const SportRoutePreviewPage({
    super.key,
    required this.proposal,
    required this.onSave,
  });
  final Map<String, dynamic> proposal;
  final Future<void> Function() onSave;

  @override
  State<SportRoutePreviewPage> createState() => _SportRoutePreviewPageState();
}

class _SportRoutePreviewPageState extends State<SportRoutePreviewPage> {
  bool _saving = false;
  String? _error;

  Future<void> _save() async {
    if (_saving || !isUsableSportRoute(widget.proposal)) return;
    setState(() {
      _saving = true;
      _error = null;
    });
    try {
      await widget.onSave();
      if (mounted) {
        setState(() => _saving = false);
        Navigator.pop(context, true);
      }
    } catch (_) {
      if (mounted) {
        setState(() {
          _saving = false;
          _error = locationCopy(
            context,
            'Die Route konnte nicht gespeichert werden. Deine Vorschau bleibt erhalten. Bitte versuche es erneut.',
            'Could not save the route. Your preview is still here. Please try again.',
            'Impossible d’enregistrer le parcours. Votre aperçu est conservé. Réessayez.',
            'تعذر حفظ المسار. المعاينة محفوظة هنا. حاول مرة أخرى.',
          );
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final points = sportRoutePoints(widget.proposal);
    final valid = isUsableSportRoute(widget.proposal);
    final distance = (widget.proposal['distance_meters'] as num? ?? 0) / 1000;
    final minutes =
        ((widget.proposal['estimated_duration_seconds'] as num? ?? 0) / 60)
            .round();
    return PopScope(
      canPop: !_saving,
      child: Scaffold(
        appBar: AppBar(
          title: Text(
            locationCopy(
              context,
              'Strecke prüfen',
              'Review route',
              'Vérifier le parcours',
              'مراجعة المسار',
            ),
          ),
        ),
        body: SafeArea(
          child: Column(
            children: [
              Padding(
                padding: const EdgeInsets.all(20),
                child: Column(
                  children: [
                    Text(
                      widget.proposal['title']?.toString() ?? '',
                      style: Theme.of(context).textTheme.titleLarge,
                    ),
                    const SizedBox(height: 8),
                    Text(
                      '${distance.toStringAsFixed(1)} km · ${locationCopy(context, 'ca.', 'approx.', 'env.', 'حوالي')} $minutes min',
                    ),
                  ],
                ),
              ),
              Expanded(
                child: valid
                    ? FlutterMap(
                        options: MapOptions(
                          initialCameraFit: CameraFit.bounds(
                            bounds: LatLngBounds.fromPoints(points),
                            padding: const EdgeInsets.all(48),
                            maxZoom: 16,
                          ),
                        ),
                        children: [
                          TileLayer(
                            urlTemplate:
                                'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
                            userAgentPackageName: 'com.airmius.app',
                            maxZoom: 19,
                          ),
                          PolylineLayer(
                            polylines: [
                              Polyline(
                                points: points,
                                strokeWidth: 5,
                                color: Colors.blue,
                              ),
                            ],
                          ),
                          MarkerLayer(
                            markers: [
                              Marker(
                                point: points.first,
                                child: const Icon(
                                  Icons.trip_origin,
                                  color: Colors.green,
                                  size: 32,
                                ),
                              ),
                              Marker(
                                point: points.last,
                                child: const Icon(
                                  Icons.flag,
                                  color: Colors.red,
                                  size: 32,
                                ),
                              ),
                            ],
                          ),
                          const RichAttributionWidget(
                            attributions: [
                              TextSourceAttribution(
                                'OpenStreetMap contributors',
                              ),
                            ],
                          ),
                        ],
                      )
                    : Center(
                        child: Text(
                          locationCopy(
                            context,
                            'Keine Wegstrecke verfügbar.',
                            'No route available.',
                            'Aucun parcours disponible.',
                            'لا يوجد مسار متاح.',
                          ),
                        ),
                      ),
              ),
              Padding(
                padding: const EdgeInsets.all(20),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    if (_error != null) ...[
                      Text(
                        _error!,
                        style: TextStyle(
                          color: Theme.of(context).colorScheme.error,
                        ),
                      ),
                      const SizedBox(height: 12),
                    ],
                    FilledButton.icon(
                      onPressed: valid && !_saving ? _save : null,
                      icon: const Icon(Icons.save_outlined),
                      label: Text(
                        _saving
                            ? locationCopy(
                                context,
                                'Wird gespeichert …',
                                'Saving …',
                                'Enregistrement …',
                                'جارٍ الحفظ …',
                              )
                            : locationCopy(
                                context,
                                'Route speichern',
                                'Save route',
                                'Enregistrer le parcours',
                                'حفظ المسار',
                              ),
                      ),
                    ),
                    const SizedBox(height: 8),
                    TextButton(
                      onPressed: _saving
                          ? null
                          : () => Navigator.pop(context, false),
                      child: Text(
                        locationCopy(
                          context,
                          'Route anpassen',
                          'Adjust route',
                          'Modifier le parcours',
                          'تعديل المسار',
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
