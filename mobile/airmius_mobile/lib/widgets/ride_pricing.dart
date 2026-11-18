import 'dart:math';
import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import '../core/airmius_api_models.dart';
import '../core/airmius_l10n.dart';
import '../core/ride_pricing_copy.dart';

JsonMap ridePricing(JsonMap ride) => ride['pricing'] is Map
    ? Map<String, dynamic>.from(ride['pricing'] as Map)
    : {};

String ridePriceLabel(BuildContext context, JsonMap pricing) {
  if (pricing['mode'] == 'free') return ridePricingText(context, 'free');
  if (pricing['mode'] != 'contribution') {
    return ridePricingText(context, 'unspecified');
  }
  final digits = (pricing['digits'] as num? ?? 2).toInt();
  final amount =
      num.tryParse('${pricing['amount']}') ??
      (pricing['amount_minor'] as num? ?? 0) / pow(10, digits);
  final formatted = NumberFormat.currency(
    locale: AirmiusScope.of(context).language.locale.toString(),
    name: '${pricing['currency']}',
    symbol: '${pricing['currency']}',
    decimalDigits: digits,
  ).format(amount);
  return '$formatted ${ridePricingText(context, 'per_person')}';
}

class RidePriceSummary extends StatelessWidget {
  const RidePriceSummary({super.key, required this.pricing});
  final JsonMap pricing;

  @override
  Widget build(BuildContext context) => Column(
    crossAxisAlignment: CrossAxisAlignment.stretch,
    children: [
      Text(
        ridePriceLabel(context, pricing),
        style: Theme.of(context).textTheme.titleMedium,
      ),
      if (pricing['requires_confirmation'] == true) ...[
        const SizedBox(height: 8),
        Text(
          ridePricingText(context, 'changed'),
          style: TextStyle(color: Theme.of(context).colorScheme.error),
        ),
        Text(
          '${ridePricingText(context, 'agreed')}: ${ridePriceLabel(context, pricing['agreed'] is Map ? Map<String, dynamic>.from(pricing['agreed']) : {})}',
        ),
        Text(ridePricingText(context, 'update_warning')),
      ],
      if (pricing['mode'] == 'contribution') ...[
        const SizedBox(height: 8),
        Text(ridePricingText(context, 'all_included')),
        Text(ridePricingText(context, 'direct_payment')),
      ],
    ],
  );
}

Future<String?> showRidePricingReview(
  BuildContext context,
  JsonMap ride, {
  bool changed = false,
  TextEditingController? message,
}) {
  var acknowledged = false;
  return showDialog<String>(
    context: context,
    builder: (context) => StatefulBuilder(
      builder: (context, setState) => AlertDialog(
        title: Text(ridePricingText(context, changed ? 'changed' : 'review')),
        content: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text('${ride['from']} → ${ride['to']}'),
              const SizedBox(height: 12),
              RidePriceSummary(pricing: ridePricing(ride)),
              CheckboxListTile(
                contentPadding: EdgeInsets.zero,
                value: acknowledged,
                controlAffinity: ListTileControlAffinity.leading,
                title: Text(ridePricingText(context, 'acknowledge')),
                onChanged: (value) =>
                    setState(() => acknowledged = value == true),
              ),
              if (message != null)
                TextField(
                  controller: message,
                  maxLength: 500,
                  maxLines: 3,
                  decoration: InputDecoration(
                    labelText: AirmiusScope.of(
                      context,
                    ).t('rides.messageOptional'),
                  ),
                ),
              if (changed) Text(ridePricingText(context, 'decline_hint')),
            ],
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: Text(AirmiusScope.of(context).t('cancel')),
          ),
          if (changed)
            TextButton(
              onPressed: () => Navigator.pop(context, 'decline'),
              child: Text(ridePricingText(context, 'decline')),
            ),
          FilledButton(
            onPressed: acknowledged
                ? () => Navigator.pop(context, 'confirm')
                : null,
            child: Text(
              changed
                  ? ridePricingText(context, 'confirm')
                  : AirmiusScope.of(context).t('rides.sendRequest'),
            ),
          ),
        ],
      ),
    ),
  );
}

class RideCostEditor extends StatelessWidget {
  const RideCostEditor({
    super.key,
    required this.catalog,
    required this.mode,
    required this.currency,
    required this.amount,
    required this.onMode,
    required this.onCurrency,
    this.editing = false,
  });
  final JsonMap catalog;
  final String? mode, currency;
  final TextEditingController amount;
  final ValueChanged<String?> onMode, onCurrency;
  final bool editing;

  @override
  Widget build(BuildContext context) {
    String t(String key) => ridePricingText(context, key);
    final currencies = catalog['currencies'] is Map
        ? catalog['currencies'] as Map
        : const {};
    final codes = currencies.keys.map((key) => '$key').toList()..sort();
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        DropdownButtonFormField<String>(
          key: const ValueKey('ride-cost-mode'),
          initialValue: mode,
          decoration: InputDecoration(labelText: t('mode')),
          items: ['free', 'contribution']
              .map(
                (value) =>
                    DropdownMenuItem(value: value, child: Text(t(value))),
              )
              .toList(),
          validator: (value) => value == null ? t('choose_mode') : null,
          onChanged: onMode,
        ),
        if (mode == 'contribution') ...[
          const SizedBox(height: 12),
          TextFormField(
            key: const ValueKey('ride-cost-amount'),
            controller: amount,
            keyboardType: const TextInputType.numberWithOptions(decimal: true),
            decoration: InputDecoration(labelText: t('amount')),
            validator: (value) =>
                value == null || value.trim().isEmpty ? t('amount') : null,
          ),
          const SizedBox(height: 12),
          _RideCurrencyField(
            codes: codes,
            value: currency,
            onChanged: onCurrency,
          ),
          const SizedBox(height: 8),
          Text(t('country_hint')),
          Text(t('all_included')),
          Text(t('direct_payment')),
          if (currencies.isEmpty) Text(t('unavailable_catalog')),
        ],
        if (editing)
          Padding(
            padding: const EdgeInsets.only(top: 8),
            child: Text(t('update_warning')),
          ),
      ],
    );
  }
}

class _RideCurrencyField extends StatefulWidget {
  const _RideCurrencyField({
    required this.codes,
    required this.value,
    required this.onChanged,
  });
  final List<String> codes;
  final String? value;
  final ValueChanged<String?> onChanged;
  @override
  State<_RideCurrencyField> createState() => _RideCurrencyFieldState();
}

class _RideCurrencyFieldState extends State<_RideCurrencyField> {
  late final _controller = TextEditingController(text: widget.value ?? '');
  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  void didUpdateWidget(covariant _RideCurrencyField oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.value != widget.value) _controller.text = widget.value ?? '';
  }

  @override
  Widget build(BuildContext context) => FormField<String>(
    initialValue: widget.value,
    validator: (value) =>
        widget.codes.contains(value) && _controller.text.trim() == value
        ? null
        : ridePricingText(context, 'currency'),
    builder: (field) => DropdownMenu<String>(
      key: const ValueKey('ride-cost-currency'),
      controller: _controller,
      initialSelection: widget.value,
      expandedInsets: EdgeInsets.zero,
      label: Text(ridePricingText(context, 'currency')),
      enableFilter: true,
      enableSearch: true,
      requestFocusOnTap: true,
      menuHeight: 250,
      errorText: field.errorText,
      dropdownMenuEntries: widget.codes
          .map((code) => DropdownMenuEntry(value: code, label: code))
          .toList(),
      onSelected: (value) {
        field.didChange(value);
        widget.onChanged(value);
      },
    ),
  );
}
