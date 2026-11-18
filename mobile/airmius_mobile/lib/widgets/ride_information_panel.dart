import 'package:flutter/material.dart';

import '../core/airmius_api_models.dart';
import '../core/airmius_l10n.dart';
import 'airmius_widgets.dart';
import 'ride_pricing.dart';

/// The notification and ride list use the same current, authorized details.
class RideInformationPanel extends StatelessWidget {
  const RideInformationPanel({
    super.key,
    required this.ride,
    this.showHeader = true,
  });
  final JsonMap ride;
  final bool showHeader;

  String text(String key) => '${ride[key] ?? ''}'.trim();
  bool get privateDetails =>
      ride['is_driver'] == true ||
      ride['is_joined'] == true ||
      ride['can_update'] == true;

  String address(String prefix) {
    final street = privateDetails
        ? [
            text('${prefix}_street'),
            text('${prefix}_house_number'),
          ].where((s) => s.isNotEmpty).join(' ')
        : '';
    final locality = [
      text('${prefix}_postal_code'),
      text('${prefix}_city'),
    ].where((s) => s.isNotEmpty).join(' ');
    if (privateDetails &&
        street.isEmpty &&
        text('${prefix}_private_label').isNotEmpty) {
      return text('${prefix}_private_label');
    }
    final parts = [
      street,
      locality,
      text('${prefix}_country'),
    ].where((s) => s.isNotEmpty).toList();
    if (parts.isNotEmpty) return parts.join('\n');
    return text('${prefix}_public_label').isNotEmpty
        ? text('${prefix}_public_label')
        : text(prefix == 'pickup' ? 'from' : 'to');
  }

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final departure = DateTime.tryParse(text('departure_time'))?.toLocal();
    final date = departure == null
        ? ''
        : '${MaterialLocalizations.of(context).formatFullDate(departure)} · ${MaterialLocalizations.of(context).formatTimeOfDay(TimeOfDay.fromDateTime(departure), alwaysUse24HourFormat: MediaQuery.alwaysUse24HourFormatOf(context))}';
    final driver = ride['driver'] as Map?;
    final club = ride['club'] as Map?;
    final team = ride['team'] as Map?;
    final event = ride['event'] as Map?;
    return AirmiusPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          RidePriceSummary(pricing: ridePricing(ride)),
          const Divider(height: 28),
          if (showHeader) ...[
            Text(
              '${text('from')} → ${text('to')}',
              style: Theme.of(context).textTheme.titleLarge,
            ),
            const SizedBox(height: 12),
            _Information(
              label: t('rides.driver'),
              value: '${driver?['name'] ?? ''}',
              icon: Icons.person_outline,
            ),
          ],
          _Information(
            label: t('rides.departure'),
            value: date,
            icon: Icons.schedule,
          ),
          _Information(
            label: t('rides.seatsIncludingDriver'),
            value: t('rides.seatCount')
                .replaceAll('{used}', '${ride['participants_count'] ?? 0}')
                .replaceAll('{total}', '${ride['seats'] ?? 0}'),
            icon: Icons.event_seat_outlined,
          ),
          _Information(
            label: t('rides.freeSeats'),
            value: '${ride['available_seats'] ?? 0}',
            icon: Icons.airline_seat_recline_normal,
          ),
          if (ride['is_joined'] == true && ride['is_driver'] != true)
            _Information(
              label: t('rides.joined'),
              value: t('rides.requestApproved'),
              icon: Icons.check_circle_outline,
            ),
          if (ride['has_pending_request'] == true)
            _Information(
              label: t('rides.pending'),
              value: t('rides.driverControlHint'),
              icon: Icons.hourglass_top,
            ),
          const Divider(height: 28),
          _Information(
            label: t('rides.from'),
            value: address('pickup'),
            icon: Icons.trip_origin,
          ),
          _Information(
            label: t('rides.pickupName'),
            value: text('pickup_name'),
            icon: Icons.place_outlined,
          ),
          if (privateDetails)
            _Information(
              label: t('rides.pickupNote'),
              value: text('pickup_note'),
              icon: Icons.info_outline,
            ),
          _Information(
            label: t('rides.destinationAddress'),
            value: address('destination'),
            icon: Icons.flag_outlined,
          ),
          if (!privateDetails)
            Padding(
              padding: const EdgeInsets.symmetric(vertical: 8),
              child: Text(t('rides.privateAfterApproval')),
            ),
          if (privateDetails)
            _Information(
              label: t('rides.contact'),
              value: text('contact_details'),
              icon: Icons.contact_phone_outlined,
            ),
          const Divider(height: 28),
          _Information(
            label: t('rides.visibility'),
            value: t(
              'rides.visibility.${text('visibility').isEmpty ? 'public' : text('visibility')}',
            ),
            icon: Icons.visibility_outlined,
          ),
          _Information(
            label: t('rides.club'),
            value: '${club?['name'] ?? ''}',
            icon: Icons.groups_outlined,
          ),
          _Information(
            label: t('rides.team'),
            value: '${team?['name'] ?? ''}',
            icon: Icons.group_outlined,
          ),
          _Information(
            label: t('rides.event'),
            value: '${event?['title'] ?? ''}',
            icon: Icons.event_outlined,
          ),
        ],
      ),
    );
  }
}

class _Information extends StatelessWidget {
  const _Information({
    required this.label,
    required this.value,
    required this.icon,
  });
  final String label, value;
  final IconData icon;

  @override
  Widget build(BuildContext context) => value.trim().isEmpty
      ? const SizedBox.shrink()
      : Padding(
          padding: const EdgeInsets.symmetric(vertical: 8),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Icon(icon, size: 22),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(label, style: Theme.of(context).textTheme.labelMedium),
                    const SizedBox(height: 4),
                    SelectableText(
                      value,
                      style: Theme.of(context).textTheme.bodyLarge,
                    ),
                  ],
                ),
              ),
            ],
          ),
        );
}
