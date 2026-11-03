import 'package:flutter/material.dart';
import 'package:flutter_map/flutter_map.dart';
import 'package:geolocator/geolocator.dart';
import 'package:latlong2/latlong.dart';
import '../core/airmius_l10n.dart';

String locationCopy(BuildContext context, String de, String en, String fr, String ar) =>
    switch (AirmiusScope.of(context).language.code.toLowerCase()) { 'de' => de, 'fr' => fr, 'ar' => ar, _ => en };

class AssistedSportLocation extends StatefulWidget {
  const AssistedSportLocation({super.key, required this.title, required this.latitude, required this.longitude, this.name});
  final String title;
  final TextEditingController latitude, longitude;
  final TextEditingController? name;
  @override
  State<AssistedSportLocation> createState() => _AssistedSportLocationState();
}

class _AssistedSportLocationState extends State<AssistedSportLocation> {
  bool _busy = false;
  String? _error;
  LatLng? get _point {
    final lat = double.tryParse(widget.latitude.text.replaceAll(',', '.'));
    final lng = double.tryParse(widget.longitude.text.replaceAll(',', '.'));
    return lat != null && lng != null && lat.isFinite && lng.isFinite && lat.abs() <= 90 && lng.abs() <= 180 ? LatLng(lat, lng) : null;
  }
  void _select(LatLng point) => setState(() {
    widget.latitude.text = point.latitude.toStringAsFixed(6);
    widget.longitude.text = point.longitude.toStringAsFixed(6);
    _error = null;
  });
  Future<void> _locate() async {
    setState(() { _busy = true; _error = null; });
    try {
      if (!await Geolocator.isLocationServiceEnabled()) throw StateError('location');
      var permission = await Geolocator.checkPermission();
      if (permission == LocationPermission.denied) permission = await Geolocator.requestPermission();
      if (permission != LocationPermission.always && permission != LocationPermission.whileInUse) throw StateError('permission');
      final position = await Geolocator.getCurrentPosition(locationSettings: const LocationSettings(timeLimit: Duration(seconds: 15)));
      if (mounted) _select(LatLng(position.latitude, position.longitude));
    } catch (_) {
      if (mounted) setState(() => _error = AirmiusScope.of(context).t('sportMap.locationUnavailable'));
    } finally { if (mounted) setState(() => _busy = false); }
  }
  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return Card(child: Padding(padding: const EdgeInsets.all(20), child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
      Text(widget.title, style: Theme.of(context).textTheme.titleMedium),
      const SizedBox(height: 12),
      Text(_point == null
        ? locationCopy(context, 'Wähle den Ort auf der Karte oder nutze deinen Standort.', 'Choose a point on the map or use your location.', 'Choisissez un point sur la carte ou votre position.', 'اختر مكاناً على الخريطة أو استخدم موقعك.')
        : locationCopy(context, 'Ort ausgewählt. Du kannst ihn auf der Karte prüfen oder ändern.', 'Location selected. Check or change it on the map.', 'Lieu sélectionné. Vérifiez-le ou modifiez-le sur la carte.', 'تم اختيار المكان. يمكنك مراجعته أو تغييره على الخريطة.')),
      const SizedBox(height: 12),
      FilledButton.tonalIcon(icon: const Icon(Icons.map_outlined),
        label: Text(locationCopy(context, 'Auf Karte auswählen', 'Choose on map', 'Choisir sur la carte', 'اختيار على الخريطة')),
        onPressed: _busy ? null : () async {
          final point = await Navigator.of(context).push<LatLng>(MaterialPageRoute(builder: (_) => SportPointPicker(initial: _point, title: widget.title)));
          if (point != null && mounted) _select(point);
        }),
      OutlinedButton.icon(icon: const Icon(Icons.my_location), label: Text(t(_busy ? 'sportMap.locating' : 'sportMap.useCurrentLocation')),
        onPressed: _busy ? null : _locate),
      if (_error != null) ...[
        Text(_error!, style: TextStyle(color: Theme.of(context).colorScheme.error)),
        TextButton(onPressed: () => Geolocator.openLocationSettings(), child: Text(locationCopy(context, 'Standort einschalten', 'Enable location', 'Activer la localisation', 'تفعيل الموقع'))),
        TextButton(onPressed: () => Geolocator.openAppSettings(), child: Text(locationCopy(context, 'App-Berechtigungen öffnen', 'Open app permissions', 'Autorisations de l’application', 'فتح أذونات التطبيق'))),
      ],
      if (widget.name != null) ...[
        const SizedBox(height: 16),
        TextField(controller: widget.name, maxLength: 120,
          decoration: InputDecoration(labelText: t('sportMap.pointName'), hintText: locationCopy(context, 'z. B. Eingang zum Stadtpark', 'e.g. park entrance', 'p. ex. entrée du parc', 'مثلاً مدخل الحديقة'))),
      ],
      ExpansionTile(tilePadding: EdgeInsets.zero,
        title: Text(locationCopy(context, 'Koordinaten manuell (optional)', 'Coordinates (optional)', 'Coordonnées (facultatif)', 'الإحداثيات (اختياري)')),
        children: [
          for (final entry in [MapEntry('sportMap.latitude', widget.latitude), MapEntry('sportMap.longitude', widget.longitude)])
            Padding(padding: const EdgeInsets.only(bottom: 20), child: TextField(controller: entry.value,
              onChanged: (_) => setState(() {}), keyboardType: const TextInputType.numberWithOptions(decimal: true, signed: true),
              decoration: InputDecoration(labelText: t(entry.key)))),
        ]),
    ])));
  }
}

class SportPointPicker extends StatefulWidget {
  const SportPointPicker({super.key, this.initial, required this.title});
  final LatLng? initial;
  final String title;
  @override
  State<SportPointPicker> createState() => _SportPointPickerState();
}

class _SportPointPickerState extends State<SportPointPicker> {
  late LatLng? _point = widget.initial;
  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: Text(widget.title)),
    body: SafeArea(child: Column(children: [
      Padding(padding: const EdgeInsets.all(16), child: Text(locationCopy(context,
        'Verschiebe und vergrößere die Karte. Tippe auf den gewünschten Ort und bestätige deine Auswahl.',
        'Move and zoom the map. Tap the desired location and confirm.',
        'Déplacez et zoomez la carte. Touchez le lieu souhaité puis confirmez.',
        'حرّك الخريطة وكبّرها، ثم اضغط على المكان المطلوب وأكّد الاختيار.'))),
      Expanded(child: FlutterMap(options: MapOptions(initialCenter: _point ?? const LatLng(20, 0), initialZoom: _point == null ? 2 : 16,
        onTap: (_, point) => setState(() => _point = point)), children: [
        TileLayer(urlTemplate: 'https://tile.openstreetmap.org/{z}/{x}/{y}.png', userAgentPackageName: 'com.airmius.app', maxZoom: 19),
        if (_point != null) MarkerLayer(markers: [Marker(point: _point!, child: Icon(Icons.location_on, size: 40, color: Theme.of(context).colorScheme.primary))]),
        const RichAttributionWidget(attributions: [TextSourceAttribution('OpenStreetMap contributors')]),
      ])),
      Padding(padding: const EdgeInsets.all(20), child: SizedBox(width: double.infinity, child: FilledButton(
        onPressed: _point == null ? null : () => Navigator.pop(context, _point),
        child: Text(locationCopy(context, 'Ort übernehmen', 'Use location', 'Utiliser ce lieu', 'اعتماد المكان'))))),
    ])),
  );
}

class SportGpsAssistance extends StatefulWidget {
  const SportGpsAssistance({super.key, required this.onStart});
  final VoidCallback onStart;
  @override
  State<SportGpsAssistance> createState() => _SportGpsAssistanceState();
}

class _SportGpsAssistanceState extends State<SportGpsAssistance> with WidgetsBindingObserver {
  bool _checking = true, _enabled = false;
  LocationPermission? _permission;
  bool get _ready => _enabled && (_permission == LocationPermission.always || _permission == LocationPermission.whileInUse);
  @override
  void initState() { super.initState(); WidgetsBinding.instance.addObserver(this); _check(); }
  @override
  void dispose() { WidgetsBinding.instance.removeObserver(this); super.dispose(); }
  @override
  void didChangeAppLifecycleState(AppLifecycleState state) { if (state == AppLifecycleState.resumed) _check(); }
  Future<void> _check() async {
    try {
      final enabled = await Geolocator.isLocationServiceEnabled();
      final permission = await Geolocator.checkPermission();
      if (mounted) setState(() { _enabled = enabled; _permission = permission; });
    } catch (_) { if (mounted) setState(() { _enabled = false; _permission = null; }); }
    finally { if (mounted) setState(() => _checking = false); }
  }
  Future<void> _fix() async {
    setState(() => _checking = true);
    try {
      if (!_enabled) { await Geolocator.openLocationSettings(); }
      else if (_permission == LocationPermission.deniedForever) { await Geolocator.openAppSettings(); }
      else { await Geolocator.requestPermission(); }
    } catch (_) {
      // The next check keeps the assistance visible if the OS action failed.
    } finally { await _check(); }
  }
  @override
  Widget build(BuildContext context) => Card(child: Padding(padding: const EdgeInsets.all(20), child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
    if (_checking) const LinearProgressIndicator()
    else if (!_ready) ...[
      Text(locationCopy(context, 'Für eine neue GPS-Aufzeichnung ist die Standortfreigabe erforderlich.', 'Location access is needed to record a new GPS activity.', 'La localisation est nécessaire pour enregistrer une activité GPS.', 'يلزم السماح بالموقع لتسجيل نشاط GPS جديد.')),
      const SizedBox(height: 12),
      FilledButton(onPressed: _fix, child: Text(!_enabled
        ? locationCopy(context, 'Standort einschalten', 'Enable location', 'Activer la localisation', 'تفعيل الموقع')
        : locationCopy(context, 'Standortzugriff erlauben', 'Allow location access', 'Autoriser la localisation', 'السماح بالموقع'))),
    ],
    if (!_checking && _ready) FilledButton.icon(onPressed: widget.onStart, icon: const Icon(Icons.directions_run),
      label: Text(AirmiusScope.of(context).t('freeRun.startAction'))),
    TextButton(onPressed: () => Geolocator.openAppSettings(), child: Text(locationCopy(context,
      'Standortberechtigung verwalten', 'Manage location permission', 'Gérer l’autorisation de localisation', 'إدارة إذن الموقع'))),
  ])));
}
