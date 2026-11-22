import 'dart:async';
import 'package:flutter/material.dart';
import '../core/airmius_api_client.dart';
import '../core/airmius_l10n.dart';
import '../core/ride_location_copy.dart';

class RideLocationField extends StatefulWidget {
  const RideLocationField({
    super.key,
    required this.client,
    required this.controller,
    required this.label,
    required this.onSelected,
    this.onChanged,
    this.area = false,
    this.required = false,
  });
  final AirmiusApiClient client;
  final TextEditingController controller;
  final String label;
  final bool area, required;
  final ValueChanged<Map<String, dynamic>> onSelected;
  final VoidCallback? onChanged;
  @override
  State<RideLocationField> createState() => _RideLocationFieldState();
}

class _RideLocationFieldState extends State<RideLocationField> {
  Timer? _timer;
  int _version = 0;
  bool _loading = false, _queried = false;
  String? _error;
  List<Map<String, dynamic>> _rows = [];
  String _lastText = '';
  @override
  void initState() {
    super.initState();
    _lastText = widget.controller.text;
    widget.controller.addListener(_externalChange);
  }

  void _externalChange() {
    if (widget.controller.text == _lastText) return;
    _lastText = widget.controller.text;
    _timer?.cancel();
    ++_version;
    if (mounted) {
      setState(() {
        _rows = [];
        _queried = false;
        _loading = false;
        _error = null;
      });
    }
  }

  @override
  void dispose() {
    _timer?.cancel();
    ++_version;
    widget.controller.removeListener(_externalChange);
    super.dispose();
  }

  void _changed(String text) {
    widget.onChanged?.call();
    _timer?.cancel();
    final version = ++_version;
    setState(() {
      _rows = [];
      _error = null;
      _queried = false;
      _loading = false;
    });
    if (text.trim().length < 3) return;
    _timer = Timer(const Duration(milliseconds: 450), () async {
      if (!mounted) return;
      setState(() => _loading = true);
      try {
        final response = await widget.client.rideLocations(
          text.trim(),
          area: widget.area,
          locale: AirmiusScope.of(context).language.name,
        );
        if (!mounted || version != _version) return;
        setState(() {
          _rows = response['data'] is List
              ? (response['data'] as List)
                    .whereType<Map>()
                    .map((row) => Map<String, dynamic>.from(row))
                    .toList()
              : [];
          _queried = true;
        });
      } catch (_) {
        if (mounted && version == _version) {
          setState(() => _error = rideLocationText(context, 'unavailable'));
        }
      } finally {
        if (mounted && version == _version) setState(() => _loading = false);
      }
    });
  }

  @override
  Widget build(BuildContext context) => Column(
    crossAxisAlignment: CrossAxisAlignment.stretch,
    children: [
      TextFormField(
        controller: widget.controller,
        maxLength: 200,
        decoration: InputDecoration(
          labelText: widget.label,
          prefixIcon: const Icon(Icons.search),
        ),
        onChanged: _changed,
        validator: widget.required
            ? (value) => value == null || value.trim().isEmpty
                  ? AirmiusScope.of(context).t('required')
                  : null
            : null,
      ),
      if (_loading) const LinearProgressIndicator(),
      if (_rows.isNotEmpty)
        ConstrainedBox(
          constraints: const BoxConstraints(maxHeight: 220),
          child: ListView(
            shrinkWrap: true,
            children: _rows
                .map(
                  (row) => ListTile(
                    title: Text('${row['label']}'),
                    onTap: () {
                      _timer?.cancel();
                      ++_version;
                      widget.controller.text = '${row['label']}';
                      setState(() {
                        _rows = [];
                        _queried = false;
                        _loading = false;
                      });
                      widget.onSelected(row);
                      FocusManager.instance.primaryFocus?.unfocus();
                    },
                  ),
                )
                .toList(),
          ),
        ),
      if (_queried && _rows.isEmpty) Text(rideLocationText(context, 'empty')),
      if (_error != null)
        Text(
          _error!,
          style: TextStyle(color: Theme.of(context).colorScheme.error),
        ),
    ],
  );
}
