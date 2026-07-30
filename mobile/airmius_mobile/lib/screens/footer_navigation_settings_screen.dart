import 'package:flutter/material.dart';

import '../core/airmius_l10n.dart';
import '../core/airmius_preferences.dart';
import '../core/airmius_services_scope.dart';
import '../core/airmius_theme.dart';
import '../models/footer_navigation_destination.dart';
import '../widgets/airmius_widgets.dart';

class FooterNavigationSettingsScreen extends StatefulWidget {
  const FooterNavigationSettingsScreen({super.key, this.preferences});

  final AirmiusPreferences? preferences;

  @override
  State<FooterNavigationSettingsScreen> createState() =>
      _FooterNavigationSettingsScreenState();
}

class _FooterNavigationSettingsScreenState
    extends State<FooterNavigationSettingsScreen> {
  late final AirmiusPreferences _preferences;
  List<FooterNavigationDestination> _available = const [];
  List<FooterNavigationDestination> _selected =
      FooterNavigationDestination.defaultDestinations;
  bool _loading = true;
  bool _saving = false;

  @override
  void initState() {
    super.initState();
    _preferences = widget.preferences ?? AirmiusPreferences();
    WidgetsBinding.instance.addPostFrameCallback((_) => _load());
  }

  Future<void> _load() async {
    final user = AirmiusServicesScope.of(context).authState.user;
    if (user == null) {
      if (mounted) setState(() => _loading = false);
      return;
    }
    final stored = await _preferences.readFooterNavigation(user.id);
    if (!mounted) return;
    final available = FooterNavigationDestination.values
        .where((destination) => destination.isAvailableTo(user))
        .toList(growable: false);
    setState(() {
      _available = available;
      _selected = sanitizeFooterNavigation(
        destinations: stored ?? FooterNavigationDestination.defaultDestinations,
        user: user,
      );
      _loading = false;
    });
  }

  void _add(FooterNavigationDestination destination) {
    if (_selected.length >= 5 || _selected.contains(destination)) return;
    setState(() => _selected = [..._selected, destination]);
  }

  void _remove(FooterNavigationDestination destination) {
    if (_selected.length <= 3) return;
    setState(
      () => _selected = _selected
          .where((current) => current != destination)
          .toList(growable: false),
    );
  }

  void _reorder(int oldIndex, int newIndex) {
    setState(() {
      final reordered = [..._selected];
      final destination = reordered.removeAt(oldIndex);
      reordered.insert(newIndex, destination);
      _selected = reordered;
    });
  }

  void _reset() {
    final user = AirmiusServicesScope.of(context).authState.user;
    setState(
      () => _selected = sanitizeFooterNavigation(
        destinations: FooterNavigationDestination.defaultDestinations,
        user: user,
      ),
    );
  }

  Future<void> _save() async {
    final user = AirmiusServicesScope.of(context).authState.user;
    if (user == null || _selected.length < 3 || _selected.length > 5) return;
    setState(() => _saving = true);
    await _preferences.writeFooterNavigation(user.id, _selected);
    if (!mounted) return;
    Navigator.of(context).pop(true);
  }

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final accent = Theme.of(context).colorScheme.primary;
    final text = airmiusTextColor(context);
    final muted = airmiusMutedColor(context);
    final unselected = _available
        .where((destination) => !_selected.contains(destination))
        .toList(growable: false);

    return Scaffold(
      appBar: AppBar(
        title: Text(
          t('footerNav.settingsTitle'),
          style: const TextStyle(fontWeight: FontWeight.w900),
        ),
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : PageFrame(
              title: t('footerNav.settingsTitle'),
              subtitle: t('footerNav.settingsBody'),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  AirmiusPanel(
                    gradient: true,
                    child: Row(
                      children: [
                        IconBadge(
                          icon: Icons.view_week_outlined,
                          color: accent,
                        ),
                        const SizedBox(width: 12),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                t('footerNav.settingsHeadline'),
                                style: TextStyle(
                                  color: text,
                                  fontSize: 19,
                                  fontWeight: FontWeight.w900,
                                ),
                              ),
                              const SizedBox(height: 5),
                              Text(
                                t('footerNav.settingsBody'),
                                style: TextStyle(color: muted, height: 1.35),
                              ),
                            ],
                          ),
                        ),
                        const SizedBox(width: 10),
                        StatusPill('${_selected.length}/5', color: accent),
                      ],
                    ),
                  ),
                  const SizedBox(height: 14),
                  AirmiusPanel(
                    title: t('footerNav.selected'),
                    subtitle: t('footerNav.reorderHint'),
                    child: ReorderableListView.builder(
                      shrinkWrap: true,
                      physics: const NeverScrollableScrollPhysics(),
                      buildDefaultDragHandles: false,
                      itemCount: _selected.length,
                      onReorderItem: _reorder,
                      itemBuilder: (context, index) {
                        final destination = _selected[index];
                        return _DestinationTile(
                          key: ValueKey(destination.storageKey),
                          destination: destination,
                          index: index,
                          canRemove: _selected.length > 3,
                          selected: true,
                          onPressed: () => _remove(destination),
                        );
                      },
                    ),
                  ),
                  const SizedBox(height: 14),
                  AirmiusPanel(
                    title: t('footerNav.available'),
                    subtitle: _selected.length >= 5
                        ? t('footerNav.maximumReached')
                        : t('footerNav.addHint'),
                    child: unselected.isEmpty
                        ? Padding(
                            padding: const EdgeInsets.only(top: 8),
                            child: Text(
                              t('footerNav.allSelected'),
                              style: TextStyle(color: muted),
                            ),
                          )
                        : Column(
                            children: [
                              for (
                                var index = 0;
                                index < unselected.length;
                                index++
                              )
                                _DestinationTile(
                                  destination: unselected[index],
                                  selected: false,
                                  canRemove: _selected.length < 5,
                                  onPressed: () => _add(unselected[index]),
                                ),
                            ],
                          ),
                  ),
                  const SizedBox(height: 14),
                  OutlinedButton.icon(
                    onPressed: _saving ? null : _reset,
                    icon: const Icon(Icons.restart_alt),
                    label: Text(t('footerNav.restoreDefault')),
                  ),
                  const SizedBox(height: 10),
                  FilledButton.icon(
                    onPressed: _saving ? null : _save,
                    icon: _saving
                        ? const SizedBox.square(
                            dimension: 18,
                            child: CircularProgressIndicator(strokeWidth: 2),
                          )
                        : const Icon(Icons.save_outlined),
                    label: Text(t('footerNav.save')),
                  ),
                ],
              ),
            ),
    );
  }
}

class _DestinationTile extends StatelessWidget {
  const _DestinationTile({
    super.key,
    required this.destination,
    required this.selected,
    required this.canRemove,
    required this.onPressed,
    this.index,
  });

  final FooterNavigationDestination destination;
  final bool selected;
  final bool canRemove;
  final VoidCallback onPressed;
  final int? index;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final accent = Theme.of(context).colorScheme.primary;
    final muted = airmiusMutedColor(context);

    return Padding(
      padding: const EdgeInsets.only(top: 8),
      child: Material(
        color: accent.withValues(alpha: selected ? 0.08 : 0.035),
        borderRadius: BorderRadius.circular(14),
        child: ListTile(
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(14),
            side: BorderSide(color: airmiusBorderColor(context)),
          ),
          leading: Icon(destination.icon, color: accent),
          title: Text(
            t(destination.labelKey),
            style: const TextStyle(fontWeight: FontWeight.w800),
          ),
          trailing: selected
              ? Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    IconButton(
                      tooltip: t('footerNav.remove'),
                      onPressed: canRemove ? onPressed : null,
                      icon: const Icon(Icons.remove_circle_outline),
                    ),
                    ReorderableDragStartListener(
                      index: index!,
                      child: Tooltip(
                        message: t('footerNav.reorder'),
                        child: Padding(
                          padding: const EdgeInsets.all(10),
                          child: Icon(Icons.drag_handle, color: muted),
                        ),
                      ),
                    ),
                  ],
                )
              : IconButton(
                  tooltip: t('footerNav.add'),
                  onPressed: canRemove ? onPressed : null,
                  icon: const Icon(Icons.add_circle_outline),
                ),
        ),
      ),
    );
  }
}
