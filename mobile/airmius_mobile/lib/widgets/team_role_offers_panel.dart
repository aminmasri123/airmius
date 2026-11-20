import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import '../core/airmius_api_models.dart';
import '../core/airmius_l10n.dart';
import '../core/team_role_offers_copy.dart';

class TeamRoleOffersPanel extends StatelessWidget {
  const TeamRoleOffersPanel({
    super.key,
    required this.offers,
    required this.language,
    required this.onRespond,
    this.busy = false,
    this.showHint = false,
  });
  final List<AirmiusTeamRoleOffer> offers;
  final AirmiusLanguage language;
  final void Function(AirmiusTeamRoleOffer, String) onRespond;
  final bool busy;
  final bool showHint;
  @override
  Widget build(BuildContext context) {
    if (offers.isEmpty && !showHint) return const SizedBox.shrink();
    String text(String key) => teamRoleOfferCopy(language, key);
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(text('title'), style: Theme.of(context).textTheme.titleMedium),
            Text(text('hint')),
            for (final offer in offers) ...[
              const Divider(),
              Text(
                '${offer.recipientName} · ${text(offer.role)}',
                style: const TextStyle(fontWeight: FontWeight.bold),
              ),
              Text('${text('active')}: ${text(offer.previousRole)}'),
              Text(text('pending')),
              Text(
                '${text('expires')}: ${DateTime.tryParse(offer.expiresAt) == null ? offer.expiresAt : DateFormat.yMd(language.locale.toLanguageTag()).format(DateTime.parse(offer.expiresAt).toLocal())}',
              ),
              Wrap(
                spacing: 8,
                children: [
                  if (offer.canRespond)
                    FilledButton(
                      onPressed: busy ? null : () => onRespond(offer, 'accept'),
                      child: Text(text('accept')),
                    ),
                  if (offer.canRespond)
                    OutlinedButton(
                      onPressed: busy
                          ? null
                          : () => onRespond(offer, 'decline'),
                      child: Text(text('decline')),
                    ),
                  if (offer.canCancel)
                    OutlinedButton(
                      onPressed: busy ? null : () => onRespond(offer, 'cancel'),
                      child: Text(text('cancel')),
                    ),
                ],
              ),
            ],
          ],
        ),
      ),
    );
  }
}
