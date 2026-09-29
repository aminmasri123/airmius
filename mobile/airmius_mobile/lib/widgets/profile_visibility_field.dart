import 'package:flutter/material.dart';
import '../core/airmius_l10n.dart';

String profileVisibilityLabel(String value, String Function(String) t) => t(
  switch (value) {
    'friends' => 'privacy.friendsOnly',
    'private' => 'privacy.private',
    _ => 'privacy.public',
  },
);

class ProfileVisibilityField extends StatelessWidget {
  const ProfileVisibilityField({super.key, required this.value, required this.onChanged});

  final String value;
  final ValueChanged<String>? onChanged;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return DropdownButtonFormField<String>(
      key: ValueKey(value),
      initialValue: value,
      isExpanded: true,
      decoration: InputDecoration(labelText: t('privacy.profileVisibility')),
      items: [
        for (final option in const ['public', 'private', 'friends'])
          DropdownMenuItem(value: option, child: Text(profileVisibilityLabel(option, t))),
      ],
      onChanged: onChanged == null ? null : (value) { if (value != null) onChanged!(value); },
    );
  }
}
