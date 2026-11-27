import 'package:flutter/material.dart';
import '../core/airmius_l10n.dart';

const _labels = {
  'de': [
    'Zahlungseingang bestätigen',
    'Tatsächliche Zahlungsart',
    'Überweisung',
    'Barzahlung',
    'Ich bestätige, dass der vollständige Betrag eingegangen ist.',
    'Abbrechen',
  ],
  'en': [
    'Confirm payment received',
    'Actual payment method',
    'Bank transfer',
    'Cash',
    'I confirm that the full amount has been received.',
    'Cancel',
  ],
  'fr': [
    'Confirmer le paiement reçu',
    'Mode de paiement réel',
    'Virement bancaire',
    'Espèces',
    'Je confirme avoir reçu le montant intégral.',
    'Annuler',
  ],
  'ar': [
    'تأكيد استلام الدفع',
    'طريقة الدفع الفعلية',
    'تحويل بنكي',
    'نقداً',
    'أؤكد استلام المبلغ بالكامل.',
    'إلغاء',
  ],
};

String commercePaymentLabel(BuildContext context) =>
    (_labels[AirmiusScope.of(context).language.locale.languageCode] ??
    _labels['en']!)[0];

String commerceReceivedMethodLabel(BuildContext context, String method) {
  final labels =
      _labels[AirmiusScope.of(context).language.locale.languageCode] ??
      _labels['en']!;
  return '${labels[1]}: ${method == 'cash' ? labels[3] : labels[2]}';
}

Future<Map<String, dynamic>?> confirmCommercePayment(
  BuildContext context, {
  required String orderLabel,
  required String amountLabel,
}) async {
  final labels =
      _labels[AirmiusScope.of(context).language.locale.languageCode] ??
      _labels['en']!;
  String? method;
  var confirmed = false;
  return showDialog<Map<String, dynamic>>(
    context: context,
    builder: (dialogContext) => StatefulBuilder(
      builder: (context, update) => AlertDialog(
        title: Text(labels[0]),
        content: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Text('$orderLabel · $amountLabel'),
              const SizedBox(height: 16),
              DropdownButtonFormField<String>(
                initialValue: method,
                decoration: InputDecoration(labelText: labels[1]),
                items: [
                  DropdownMenuItem(
                    value: 'bank_transfer',
                    child: Text(labels[2]),
                  ),
                  DropdownMenuItem(value: 'cash', child: Text(labels[3])),
                ],
                onChanged: (value) => update(() => method = value),
              ),
              CheckboxListTile(
                contentPadding: EdgeInsets.zero,
                title: Text(labels[4]),
                value: confirmed,
                onChanged: (value) => update(() => confirmed = value == true),
              ),
            ],
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext),
            child: Text(labels[5]),
          ),
          FilledButton(
            onPressed: method == null || !confirmed
                ? null
                : () => Navigator.pop(dialogContext, {
                    'payment_method': method,
                    'confirmed': true,
                  }),
            child: Text(labels[0]),
          ),
        ],
      ),
    ),
  );
}
