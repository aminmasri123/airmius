import 'package:flutter/material.dart';

import '../core/airmius_l10n.dart';
import '../core/airmius_theme.dart';
import '../core/airmius_theme_mode_scope.dart';
import 'airmius_web_image_stub.dart'
    if (dart.library.html) 'airmius_web_image_web.dart';

enum AirmiusLogoVariant {
  mark,
  wordmark,
  full,
}

class AirmiusLogo extends StatelessWidget {
  const AirmiusLogo({
    super.key,
    this.compact = false,
    this.size,
    this.markOnly = false,
    this.variant = AirmiusLogoVariant.wordmark,
    this.forceDark,
  });

  final bool compact;
  final double? size;
  final bool markOnly;
  final AirmiusLogoVariant variant;
  final bool? forceDark;

  @override
  Widget build(BuildContext context) {
    final resolvedSize = size ?? (compact ? 30.0 : 42.0);
    final selectedVariant = markOnly ? AirmiusLogoVariant.mark : variant;
    final useDarkUiLogo = forceDark ?? _shouldUseDarkUiLogo(context);
    final assetPath = switch (selectedVariant) {
      AirmiusLogoVariant.mark => 'assets/images/airmius-mark.png',
      AirmiusLogoVariant.wordmark => useDarkUiLogo ? 'assets/images/airmius-wordmark-dark.png' : 'assets/images/airmius-wordmark-light.png',
      AirmiusLogoVariant.full => useDarkUiLogo ? 'assets/images/airmius-full-dark.png' : 'assets/images/airmius-full-light.png',
    };
    final fallbackAssetPath = useDarkUiLogo ? 'assets/images/airmius-wordmark-dark.png' : 'assets/images/airmius-wordmark-light.png';

    return Semantics(
      label: 'Airmius Logo',
      image: true,
      child: Image.asset(
        assetPath,
        height: resolvedSize,
        width: selectedVariant == AirmiusLogoVariant.mark ? resolvedSize : null,
        fit: BoxFit.contain,
        errorBuilder: (_, __, ___) => Image.asset(
          fallbackAssetPath,
          height: resolvedSize,
          width: selectedVariant == AirmiusLogoVariant.mark ? resolvedSize : null,
          fit: BoxFit.contain,
          errorBuilder: (_, __, ___) => const Icon(Icons.auto_awesome, color: AirmiusColors.blue),
        ),
      ),
    );
  }

  bool _shouldUseDarkUiLogo(BuildContext context) {
    try {
      final mode = AirmiusThemeModeScope.of(context).mode;
      return switch (mode) {
        ThemeMode.dark => true,
        ThemeMode.light => false,
        ThemeMode.system => Theme.of(context).brightness == Brightness.dark,
      };
    } on StateError {
      return Theme.of(context).brightness == Brightness.dark;
    }
  }
}

class AirmiusPanel extends StatelessWidget {
  const AirmiusPanel({
    super.key,
    this.child,
    this.children,
    this.title,
    this.subtitle,
    this.body,
    this.minHeight,
    this.onTap,
    this.padding = const EdgeInsets.all(16),
    this.gradient = false,
    this.borderColor,
  });

  final Widget? child;
  final List<Widget>? children;
  final String? title;
  final String? subtitle;
  final String? body;
  final double? minHeight;
  final VoidCallback? onTap;
  final EdgeInsetsGeometry padding;
  final bool gradient;
  final Color? borderColor;

  @override
  Widget build(BuildContext context) {
    final content = child ??
        (children == null
            ? null
            : Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: children!,
              ));

    final showHeader = title != null || subtitle != null || body != null;
    final panelBody = showHeader
        ? Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              if (title != null) Text(title!, style: const TextStyle(color: AirmiusColors.text, fontSize: 18, fontWeight: FontWeight.w900)),
              if (subtitle != null) ...[
                const SizedBox(height: 4),
                Text(subtitle!, style: const TextStyle(color: AirmiusColors.muted, fontWeight: FontWeight.w700)),
              ] else if (body != null) ...[
                const SizedBox(height: 4),
                Text(body!, style: const TextStyle(color: AirmiusColors.muted, fontWeight: FontWeight.w700)),
              ],
              if (content != null) ...[
                const SizedBox(height: 10),
                content,
              ],
            ],
          )
        : content;

    final constrainedPanelBody = minHeight == null
        ? panelBody
        : ConstrainedBox(
            constraints: BoxConstraints(minHeight: minHeight!),
            child: panelBody,
          );

    final box = Container(
      width: double.infinity,
      padding: padding,
      decoration: BoxDecoration(
        color: AirmiusColors.card,
        gradient: gradient
            ? LinearGradient(
                begin: Alignment.topLeft,
                end: Alignment.bottomRight,
                colors: [
                  AirmiusColors.cardSoft,
                  AirmiusColors.card,
                  AirmiusColors.blue.withValues(alpha: 0.10),
                ],
              )
            : null,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: borderColor ?? AirmiusColors.border),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.20),
            blurRadius: 20,
            offset: const Offset(0, 10),
          ),
        ],
      ),
      child: constrainedPanelBody,
    );

    if (onTap == null) return box;
    return InkWell(onTap: onTap, borderRadius: BorderRadius.circular(18), child: box);
  }
}

class PageFrame extends StatelessWidget {
  const PageFrame({
    super.key,
    required this.title,
    required this.subtitle,
    Widget? child,
    Widget? body,
    this.trailing,
    this.actions,
    this.showHeader = false,
  })  : child = child ?? body ?? const SizedBox.shrink(),
        assert(child == null || body == null, 'Provide exactly one of child or body to PageFrame.'),
        assert(child != null || body != null, 'Provide either child or body to PageFrame.');

  final String title;
  final String subtitle;
  final Widget child;
  final Widget? trailing;
  final List<Widget>? actions;
  final bool showHeader;

  @override
  Widget build(BuildContext context) {
    final trailingWidgets = <Widget>[
      if (trailing != null) trailing!,
      if (actions != null)
        ...actions!
            .map((action) => Padding(padding: const EdgeInsets.only(left: 8), child: action))
            .toList(),
    ];

    return CustomScrollView(
      slivers: [
        SliverPadding(
          padding: const EdgeInsets.fromLTRB(16, 12, 16, 20),
          sliver: SliverToBoxAdapter(
            child: Center(
              child: ConstrainedBox(
                constraints: const BoxConstraints(maxWidth: 740),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    if (showHeader) ...[
                      Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  title,
                                  style: const TextStyle(
                                    color: AirmiusColors.text,
                                    fontSize: 26,
                                    fontWeight: FontWeight.w900,
                                    height: 1.05,
                                  ),
                                ),
                                const SizedBox(height: 4),
                                Text(
                                  subtitle,
                                  style: const TextStyle(
                                    color: AirmiusColors.muted,
                                    fontWeight: FontWeight.w600,
                                  ),
                                ),
                              ],
                            ),
                          ),
                          if (trailingWidgets.isNotEmpty)
                            Row(mainAxisSize: MainAxisSize.min, children: trailingWidgets),
                        ],
                      ),
                      const SizedBox(height: 16),
                    ],
                    child,
                  ],
                ),
              ),
            ),
          ),
        ),
      ],
    );
  }
}

class AirmiusLogoMark extends StatelessWidget {
  const AirmiusLogoMark({super.key, this.size = 34});

  final double size;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: size,
      height: size,
      child: AirmiusLogo(markOnly: true, size: size),
    );
  }
}

class IconBadge extends StatelessWidget {
  const IconBadge({
    super.key,
    required this.icon,
    required this.color,
  });

  final IconData icon;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 44,
      height: 44,
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.14),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: color.withValues(alpha: 0.45)),
      ),
      child: Icon(icon, color: color, size: 23),
    );
  }
}

class SectionLabel extends StatelessWidget {
  const SectionLabel(this.text, {super.key});

  final String text;

  @override
  Widget build(BuildContext context) {
    return Text(
      text.toUpperCase(),
      style: const TextStyle(
        color: AirmiusColors.blue,
        fontSize: 12,
        letterSpacing: 0.7,
        fontWeight: FontWeight.w900,
      ),
    );
  }
}

class GridWrap extends StatelessWidget {
  const GridWrap({super.key, required this.children});

  final List<Widget> children;

  @override
  Widget build(BuildContext context) {
    return Wrap(spacing: 12, runSpacing: 12, children: children);
  }
}

class Metric extends StatelessWidget {
  const Metric({super.key, required this.value, required this.label});

  final String value;
  final String label;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      padding: const EdgeInsets.all(12),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(value, style: const TextStyle(color: AirmiusColors.text, fontSize: 22, fontWeight: FontWeight.w900)),
          const SizedBox(height: 4),
          Text(label, style: const TextStyle(color: AirmiusColors.muted, fontSize: 11, fontWeight: FontWeight.w700)),
        ],
      ),
    );
  }
}

class PageTitle extends StatelessWidget {
  const PageTitle({
    super.key,
    required this.title,
    required this.subtitle,
  });

  final String title;
  final String subtitle;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(title, style: const TextStyle(color: AirmiusColors.text, fontSize: 26, fontWeight: FontWeight.w900, height: 1.05)),
        const SizedBox(height: 4),
        Text(subtitle, style: const TextStyle(color: AirmiusColors.muted, fontWeight: FontWeight.w600)),
      ],
    );
  }
}

class EmptyPanel extends StatelessWidget {
  const EmptyPanel(this.message, {super.key});

  final String message;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Text(
          message,
          style: const TextStyle(color: AirmiusColors.muted, fontWeight: FontWeight.w700),
        ),
      ),
    );
  }
}

void openUiAction(
  BuildContext context, {
  required String title,
  String? body,
  String? message,
  String status = 'UI bereit',
  IconData icon = Icons.info_outline,
}) {
  final resolvedBody = (body == null || body.trim().isEmpty)
      ? (message == null || message.trim().isEmpty
          ? 'UI-Aktion vorbereiten und in der App sichtbar halten.'
          : message)
      : body;

  Navigator.of(context).push(
    MaterialPageRoute(
      builder: (_) => _OpenUiActionResultScreen(
        title: title,
        body: resolvedBody,
        status: status,
        icon: icon,
      ),
    ),
  );
}

class _OpenUiActionResultScreen extends StatefulWidget {
  const _OpenUiActionResultScreen({
    required this.title,
    required this.body,
    required this.status,
    required this.icon,
  });

  final String title;
  final String body;
  final String status;
  final IconData icon;

  @override
  State<_OpenUiActionResultScreen> createState() => _OpenUiActionResultScreenState();
}

class _OpenUiActionResultScreenState extends State<_OpenUiActionResultScreen> {
  bool _saveAsDraft = true;
  bool _notify = true;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        backgroundColor: AirmiusColors.header,
        surfaceTintColor: Colors.transparent,
        title: Text(widget.title, style: const TextStyle(fontWeight: FontWeight.w900)),
      ),
      body: PageFrame(
        title: widget.title,
        subtitle: widget.body,
        child: AirmiusPanel(
          gradient: true,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Icon(widget.icon, color: AirmiusColors.blue, size: 36),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Eyebrow('UI-Aktion'),
                        const SizedBox(height: 8),
                        Text(widget.body, style: const TextStyle(color: AirmiusColors.muted, height: 1.35)),
                        const SizedBox(height: 10),
                        Wrap(
                          spacing: 8,
                          runSpacing: 8,
                          children: [
                            StatusPill(widget.status),
                            const StatusPill('UI bereit'),
                            const StatusPill('API spaeter'),
                          ],
                        ),
                      ],
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 14),
              SwitchListTile.adaptive(
                value: _saveAsDraft,
                onChanged: (value) => setState(() => _saveAsDraft = value),
                activeColor: AirmiusColors.blue,
                contentPadding: EdgeInsets.zero,
                title: const Text('Als Entwurf vormerken', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
                subtitle: const Text('UI bleibt lokal sichtbar, Server-Sync ist später geplant.', style: TextStyle(color: AirmiusColors.muted)),
              ),
              SwitchListTile.adaptive(
                value: _notify,
                onChanged: (value) => setState(() => _notify = value),
                activeColor: AirmiusColors.amber,
                contentPadding: EdgeInsets.zero,
                title: const Text('Benachrichtigung ausloesen', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
                subtitle: const Text('Push/Inbox-Signal optional vormerken.', style: TextStyle(color: AirmiusColors.muted)),
              ),
              const SizedBox(height: 12),
              AirmiusButton(
                label: 'Aktion vormerken',
                icon: Icons.check_circle_outline,
                onPressed: () => Navigator.of(context).pop(),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class AirmiusTopBar extends StatelessWidget implements PreferredSizeWidget {
  const AirmiusTopBar({
    super.key,
    required this.title,
    this.onSearch,
    this.onMessages,
    this.onNotifications,
    this.userLabel,
    this.userImageUrl,
    this.onOpenProfile,
    this.onOpenSettings,
    this.onSignOut,
  });

  final String title;
  final VoidCallback? onSearch;
  final VoidCallback? onMessages;
  final VoidCallback? onNotifications;
  final String? userLabel;
  final String? userImageUrl;
  final VoidCallback? onOpenProfile;
  final VoidCallback? onOpenSettings;
  final VoidCallback? onSignOut;

  @override
  Size get preferredSize => const Size.fromHeight(64);

  @override
  Widget build(BuildContext context) {
    final fallbackLabel = (userLabel == null || userLabel!.trim().isEmpty) ? 'GK' : userLabel!.trim();

    return AppBar(
      backgroundColor: AirmiusColors.header,
      surfaceTintColor: Colors.transparent,
      elevation: 0,
      centerTitle: false,
      leading: Builder(
        builder: (context) => IconButton(
          icon: const Icon(Icons.menu, color: AirmiusColors.text),
          onPressed: () => Scaffold.of(context).openDrawer(),
        ),
      ),
      titleSpacing: 0,
      title: Row(
        children: [
          const AirmiusLogo(compact: true),
          const SizedBox(width: 10),
          Expanded(child: Text(title, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900))),
        ],
      ),
      actions: [
        IconButton(
          tooltip: 'Suche',
          onPressed: onSearch,
          icon: const Icon(Icons.search, color: AirmiusColors.muted),
        ),
        IconButton(
          tooltip: 'Nachrichten',
          onPressed: onMessages,
          icon: const Icon(Icons.chat_bubble_outline, color: AirmiusColors.muted),
        ),
        IconButton(
          tooltip: 'Benachrichtigungen',
          onPressed: onNotifications,
          icon: const Icon(Icons.notifications_none, color: AirmiusColors.muted),
        ),
        if (onOpenProfile != null || onOpenSettings != null || onSignOut != null)
          _ProfileMenuBubble(
            userLabel: fallbackLabel,
            userImageUrl: userImageUrl,
            onOpenProfile: onOpenProfile,
            onOpenSettings: onOpenSettings,
            onSignOut: onSignOut,
          )
        else
          UserBubble(label: fallbackLabel, imageUrl: userImageUrl),
        const SizedBox(width: 12),
      ],
    );
  }
}

class UserBubble extends StatelessWidget {
  const UserBubble({
    super.key,
    required this.label,
    this.imageUrl,
    this.small = false,
    this.onTap,
  });

  final String label;
  final String? imageUrl;
  final bool small;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final imageUrl = _normalizedImageUrl;
    final size = small ? 32.0 : 40.0;
    final initials = Text(
      _shortLabel,
      style: TextStyle(
        color: AirmiusColors.text,
        fontWeight: FontWeight.w900,
        fontSize: small ? 11 : 13,
      ),
    );
    final bubble = Container(
      width: size,
      height: size,
      decoration: BoxDecoration(
        color: AirmiusColors.blue.withValues(alpha: 0.28),
        shape: BoxShape.circle,
        border: Border.all(color: AirmiusColors.blue.withValues(alpha: 0.35)),
      ),
      clipBehavior: Clip.antiAlias,
      child: imageUrl == null
          ? Center(child: initials)
          : Image.network(
              imageUrl,
              fit: BoxFit.cover,
              webHtmlElementStrategy: WebHtmlElementStrategy.prefer,
              errorBuilder: (_, __, ___) => Center(child: initials),
            ),
    );
    if (onTap == null) return bubble;
    return InkWell(
      onTap: onTap,
      customBorder: const CircleBorder(),
      child: bubble,
    );
  }

  String? get _normalizedImageUrl {
    return resolveAirmiusImageUrl(imageUrl);
  }

  String get _shortLabel {
    return initialsFromName(label, fallback: '??');
  }
}

String initialsFromName(String? name, {String fallback = '?'}) {
  final parts =
      name?.trim().split(RegExp(r'\s+')).where((part) => part.isNotEmpty).toList() ??
          const <String>[];
  if (parts.isEmpty) return fallback;
  if (parts.length == 1) {
    final part = parts.first;
    return (part.length <= 2 ? part : part.substring(0, 2)).toUpperCase();
  }
  return '${parts.first[0]}${parts.last[0]}'.toUpperCase();
}

String? resolveAirmiusImageUrl(String? imageUrl) {
  final value = imageUrl?.trim();
  if (value == null || value.isEmpty) return null;
  if (value.startsWith('data:image/')) return value;
  final uri = Uri.tryParse(value);
  if (uri != null && uri.hasScheme && uri.hasAuthority) return value;

  const origin = String.fromEnvironment('AIRMIUS_API_BASE_URL', defaultValue: 'https://airmius.com');
  final base = Uri.tryParse(origin);
  if (base == null || !base.hasScheme || base.host.isEmpty) return null;
  if (value.startsWith('/')) return base.replace(path: _withAirmiusBasePath(base, value), query: null, fragment: null).toString();
  final cleanPath = value.replaceFirst(RegExp(r'^/+'), '');
  final path = cleanPath.startsWith('storage/') || cleanPath.startsWith('build/') || cleanPath.startsWith('images/') ? '/$cleanPath' : '/storage/$cleanPath';
  return base.replace(path: _withAirmiusBasePath(base, path), query: null, fragment: null).toString();
}

String _withAirmiusBasePath(Uri base, String path) {
  final cleanBase = base.path == '/' ? '' : base.path.replaceFirst(RegExp(r'/$'), '');
  if (cleanBase.isEmpty || path.startsWith('$cleanBase/')) return path;
  return '$cleanBase$path';
}

enum _ProfileAction {
  openProfile,
  openSettings,
  signOut,
}

class _ProfileMenuBubble extends StatelessWidget {
  const _ProfileMenuBubble({
    required this.userLabel,
    required this.userImageUrl,
    required this.onOpenProfile,
    required this.onOpenSettings,
    required this.onSignOut,
  });

  final String userLabel;
  final String? userImageUrl;
  final VoidCallback? onOpenProfile;
  final VoidCallback? onOpenSettings;
  final VoidCallback? onSignOut;

  @override
  Widget build(BuildContext context) {
    final items = <PopupMenuEntry<_ProfileAction>>[];
    if (onOpenProfile != null) {
      items.add(_buildItem(
        action: _ProfileAction.openProfile,
        icon: Icons.person_outline,
        text: 'Profil',
      ));
    }
    if (onOpenSettings != null) {
      items.add(_buildItem(
        action: _ProfileAction.openSettings,
        icon: Icons.settings_outlined,
        text: 'Einstellungen',
      ));
    }
    if (onSignOut != null) {
      if (items.isNotEmpty) {
        items.add(const PopupMenuDivider());
      }
      items.add(_buildItem(
        action: _ProfileAction.signOut,
        icon: Icons.logout_outlined,
        text: 'Abmelden',
      ));
    }

    return PopupMenuButton<_ProfileAction>(
      tooltip: 'Benutzer',
      offset: const Offset(0, 48),
      icon: UserBubble(label: userLabel, imageUrl: userImageUrl),
      itemBuilder: (_) => items,
      onSelected: (value) {
        switch (value) {
          case _ProfileAction.openProfile:
            onOpenProfile?.call();
            break;
          case _ProfileAction.openSettings:
            onOpenSettings?.call();
            break;
          case _ProfileAction.signOut:
            onSignOut?.call();
            break;
        }
      },
    );
  }

  PopupMenuItem<_ProfileAction> _buildItem({
    required _ProfileAction action,
    required IconData icon,
    required String text,
  }) {
    return PopupMenuItem<_ProfileAction>(
      value: action,
      child: Row(
        children: [
          Icon(icon, size: 18, color: AirmiusColors.text),
          const SizedBox(width: 12),
          Text(text, style: const TextStyle(fontWeight: FontWeight.w800)),
        ],
      ),
    );
  }
}

class Eyebrow extends StatelessWidget {
  const Eyebrow(this.text, {super.key});

  final String text;

  @override
  Widget build(BuildContext context) {
    return Text(
      text.toUpperCase(),
      style: const TextStyle(color: AirmiusColors.blue, fontSize: 12, letterSpacing: 0.7, fontWeight: FontWeight.w900),
    );
  }
}

class AirmiusButton extends StatelessWidget {
  AirmiusButton({
    super.key,
    required this.label,
    required this.icon,
    required this.onPressed,
    this.danger = false,
    this.secondary = false,
  });

  final String label;
  final IconData icon;
  final VoidCallback? onPressed;
  final bool danger;
  final bool secondary;

  @override
  Widget build(BuildContext context) {
    final color = danger ? AirmiusColors.red : AirmiusColors.blue;
    if (secondary || danger) {
      return OutlinedButton.icon(
        onPressed: onPressed,
        icon: Icon(icon, size: 18),
        label: Text(label, style: const TextStyle(fontWeight: FontWeight.w900)),
        style: OutlinedButton.styleFrom(
          foregroundColor: color,
          side: BorderSide(color: color.withValues(alpha: 0.65)),
          backgroundColor: color.withValues(alpha: 0.08),
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 13),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
        ),
      );
    }

    return FilledButton.icon(
      onPressed: onPressed,
      icon: Icon(icon, size: 18),
      label: Text(label, style: const TextStyle(fontWeight: FontWeight.w900)),
      style: FilledButton.styleFrom(
        backgroundColor: AirmiusColors.blue,
        foregroundColor: Colors.white,
        disabledBackgroundColor: AirmiusColors.cardSoft,
        disabledForegroundColor: AirmiusColors.mutedSoft,
        padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 15),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(15)),
      ),
    );
  }
}

class AirmiusTextField extends StatelessWidget {
  const AirmiusTextField({
    super.key,
    required this.label,
    this.hint,
    this.icon,
    this.maxLines = 1,
    this.controller,
    this.focusNode,
    this.onChanged,
  });

  final String label;
  final String? hint;
  final IconData? icon;
  final int maxLines;
  final TextEditingController? controller;
  final FocusNode? focusNode;
  final ValueChanged<String>? onChanged;

  @override
  Widget build(BuildContext context) {
    return TextField(
      controller: controller,
      focusNode: focusNode,
      onChanged: onChanged,
      maxLines: maxLines,
      style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w700),
      decoration: InputDecoration(
        labelText: label,
        hintText: hint,
        prefixIcon: icon == null ? null : Icon(icon, color: AirmiusColors.muted),
      ),
    );
  }
}

class SearchBox extends StatelessWidget {
  const SearchBox({super.key, required this.hint, required this.onChanged});

  final String hint;
  final ValueChanged<String> onChanged;

  @override
  Widget build(BuildContext context) {
    return TextField(
      onChanged: onChanged,
      style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w800),
      decoration: InputDecoration(
        hintText: hint,
        prefixIcon: const Icon(Icons.search, color: AirmiusColors.muted),
        filled: true,
        fillColor: AirmiusColors.cardSoft,
        enabledBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(18), borderSide: const BorderSide(color: AirmiusColors.border)),
        focusedBorder: OutlineInputBorder(borderRadius: BorderRadius.circular(18), borderSide: const BorderSide(color: AirmiusColors.blue)),
      ),
    );
  }
}

class MetricCard extends StatelessWidget {
  const MetricCard({super.key, required this.value, required this.label});

  final String value;
  final String label;

  @override
  Widget build(BuildContext context) {
    return AirmiusPanel(
      padding: const EdgeInsets.all(14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(value, style: const TextStyle(color: AirmiusColors.text, fontSize: 24, fontWeight: FontWeight.w900)),
          const SizedBox(height: 4),
          Text(label, style: const TextStyle(color: AirmiusColors.muted, fontSize: 12, fontWeight: FontWeight.w700)),
        ],
      ),
    );
  }
}

class AirmiusAvatar extends StatelessWidget {
  const AirmiusAvatar(this.name, {super.key, this.large = false, this.imageUrl});

  final String name;
  final bool large;
  final String? imageUrl;

  @override
  Widget build(BuildContext context) {
    final initials = initialsFromName(name);
    final radius = large ? 20.0 : 16.0;
    final resolvedImageUrl = _imageUrl;
    return Container(
      width: large ? 64 : 52,
      height: large ? 64 : 52,
      decoration: BoxDecoration(
        color: AirmiusColors.blue.withValues(alpha: 0.22),
        borderRadius: BorderRadius.circular(radius),
        border: Border.all(color: AirmiusColors.blue.withValues(alpha: 0.35)),
      ),
      child: ClipRRect(
        borderRadius: BorderRadius.circular(radius),
        child: resolvedImageUrl == null
            ? Center(
                child: Text(
                  initials,
                  style: TextStyle(
                    color: AirmiusColors.text,
                    fontSize: large ? 28 : 22,
                    fontWeight: FontWeight.w900,
                  ),
                ),
              )
            : Image.network(
                resolvedImageUrl,
                fit: BoxFit.cover,
                webHtmlElementStrategy: WebHtmlElementStrategy.prefer,
                errorBuilder: (_, __, ___) => Center(
                  child: Text(
                    initials,
                    style: TextStyle(
                      color: AirmiusColors.text,
                      fontSize: large ? 28 : 22,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                ),
              ),
      ),
    );
  }

  String? get _imageUrl {
    return resolveAirmiusImageUrl(imageUrl);
  }
}

class AirmiusMediaImage extends StatelessWidget {
  const AirmiusMediaImage({
    super.key,
    required this.url,
    this.height,
    this.aspectRatio,
    this.borderRadius = 16,
    this.fallback,
    this.fallbackUrls = const [],
  });

  final String url;
  final List<String> fallbackUrls;
  final double? height;
  final double? aspectRatio;
  final double borderRadius;
  final Widget? fallback;

  @override
  Widget build(BuildContext context) {
    return ClipRRect(
      borderRadius: BorderRadius.circular(borderRadius),
      child: Container(
        color: AirmiusColors.cardSoft,
        child: height != null
            ? SizedBox(width: double.infinity, height: height, child: _AirmiusNetworkImageWithFallbacks(url: url, fallbackUrls: fallbackUrls, fallback: fallback))
            : AspectRatio(aspectRatio: aspectRatio ?? 16 / 9, child: _AirmiusNetworkImageWithFallbacks(url: url, fallbackUrls: fallbackUrls, fallback: fallback)),
      ),
    );
  }

}

class _AirmiusNetworkImageWithFallbacks extends StatefulWidget {
  const _AirmiusNetworkImageWithFallbacks({required this.url, this.fallbackUrls = const [], this.fallback});

  final String url;
  final List<String> fallbackUrls;
  final Widget? fallback;

  @override
  State<_AirmiusNetworkImageWithFallbacks> createState() => _AirmiusNetworkImageWithFallbacksState();
}

class _AirmiusNetworkImageWithFallbacksState extends State<_AirmiusNetworkImageWithFallbacks> {
  late List<String> _candidates;
  int _index = 0;

  @override
  void initState() {
    super.initState();
    _candidates = _imageUrlCandidates(widget.url, widget.fallbackUrls);
  }

  @override
  void didUpdateWidget(_AirmiusNetworkImageWithFallbacks oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.url != widget.url || oldWidget.fallbackUrls != widget.fallbackUrls) {
      _candidates = _imageUrlCandidates(widget.url, widget.fallbackUrls);
      _index = 0;
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_candidates.isEmpty) return widget.fallback ?? const _MediaFallback();
    final htmlImage = airmiusHtmlImage(_candidates, fit: BoxFit.cover);
    if (htmlImage != null) return htmlImage;

    return Image.network(
      _candidates[_index],
      width: double.infinity,
      height: double.infinity,
      fit: BoxFit.cover,
      webHtmlElementStrategy: WebHtmlElementStrategy.prefer,
      errorBuilder: (_, __, ___) {
        if (_index + 1 < _candidates.length) {
          WidgetsBinding.instance.addPostFrameCallback((_) {
            if (mounted) setState(() => _index += 1);
          });
          return const _MediaFallback();
        }
        return widget.fallback ?? const _MediaFallback();
      },
    );
  }
}

List<String> _imageUrlCandidates(String imageUrl, [List<String> fallbackUrls = const []]) {
  final urls = <String>[];
  void add(String? value) {
    final url = value?.trim();
    if (url == null || url.isEmpty || urls.contains(url)) return;
    urls.add(url);
  }

  final normalized = resolveAirmiusImageUrl(imageUrl);
  add(normalized);
  add(imageUrl);
  for (final fallbackUrl in fallbackUrls) {
    add(resolveAirmiusImageUrl(fallbackUrl));
    add(fallbackUrl);
  }

  final uri = Uri.tryParse(normalized ?? imageUrl);
  if (uri != null && uri.path.contains('/storage/')) {
    final currentOrigin = Uri.base;
    if (currentOrigin.hasScheme && currentOrigin.host.isNotEmpty) {
      add(currentOrigin.replace(path: _withAirmiusBasePath(currentOrigin, uri.path), query: uri.query.isEmpty ? null : uri.query, fragment: null).toString());
    }
  }

  return urls;
}

class _MediaFallback extends StatelessWidget {
  const _MediaFallback();

  @override
  Widget build(BuildContext context) {
    return const Center(
      child: Padding(
        padding: EdgeInsets.all(18),
        child: Icon(Icons.image_not_supported_outlined, color: AirmiusColors.muted),
      ),
    );
  }
}

class StatusPill extends StatelessWidget {
  const StatusPill(this.label, {super.key, this.color = AirmiusColors.blue});

  final String label;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.15),
        borderRadius: BorderRadius.circular(99),
        border: Border.all(color: color.withValues(alpha: 0.45)),
      ),
      child: Text(label, style: TextStyle(color: color, fontSize: 12, fontWeight: FontWeight.w900)),
    );
  }
}

class LanguageChooser extends StatelessWidget {
  const LanguageChooser({super.key});

  @override
  Widget build(BuildContext context) {
    final scope = AirmiusScope.of(context);
    return AirmiusPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(scope.t('language'), style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
          const SizedBox(height: 10),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              for (final language in AirmiusLanguage.values)
                ChoiceChip(
                  selected: scope.language == language,
                  label: Text(language.code),
                  onSelected: (_) => scope.setLanguage(language),
                  selectedColor: AirmiusColors.blue.withValues(alpha: 0.22),
                  backgroundColor: AirmiusColors.cardSoft,
                  side: BorderSide(color: scope.language == language ? AirmiusColors.blue : AirmiusColors.border),
                  labelStyle: TextStyle(color: scope.language == language ? AirmiusColors.blue : AirmiusColors.muted, fontWeight: FontWeight.w900),
                ),
            ],
          ),
        ],
      ),
    );
  }
}

class AirmiusThemeChooser extends StatelessWidget {
  const AirmiusThemeChooser({super.key});

  @override
  Widget build(BuildContext context) {
    final scope = AirmiusThemeModeScope.of(context);
    return AirmiusPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text('Design', style: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
          const SizedBox(height: 6),
          const Text(
            'Logo und Theme folgen dem Airmius-Prinzip fuer Dunkel, Normal und System.',
            style: TextStyle(color: AirmiusColors.muted, fontSize: 12, height: 1.35),
          ),
          const SizedBox(height: 10),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              _ThemeChoice(mode: ThemeMode.dark, active: scope.mode, label: 'Dunkel'),
              _ThemeChoice(mode: ThemeMode.light, active: scope.mode, label: 'Normal'),
              _ThemeChoice(mode: ThemeMode.system, active: scope.mode, label: 'System'),
            ],
          ),
        ],
      ),
    );
  }
}

class _ThemeChoice extends StatelessWidget {
  const _ThemeChoice({
    required this.mode,
    required this.active,
    required this.label,
  });

  final ThemeMode mode;
  final ThemeMode active;
  final String label;

  @override
  Widget build(BuildContext context) {
    final scope = AirmiusThemeModeScope.of(context);
    return ChoiceChip(
      selected: active == mode,
      label: Text(label),
      onSelected: (_) => scope.setMode(mode),
      selectedColor: AirmiusColors.blue.withValues(alpha: 0.22),
      backgroundColor: AirmiusColors.cardSoft,
      side: BorderSide(color: active == mode ? AirmiusColors.blue : AirmiusColors.border),
      labelStyle: TextStyle(color: active == mode ? AirmiusColors.blue : AirmiusColors.muted, fontWeight: FontWeight.w900),
    );
  }
}

Future<bool> confirmDanger(
  BuildContext context, [
  String? legacyTitle,
  String? legacyMessage,
  String confirmLabel = 'Zurueckziehen',
  VoidCallback? onConfirm,
]) async {
  final resolvedTitle = (legacyTitle ?? '').trim();
  final resolvedMessage = (legacyMessage ?? '').trim();

  final result = await showDialog<bool>(
    context: context,
    builder: (dialogContext) => AlertDialog(
      backgroundColor: AirmiusColors.card,
      surfaceTintColor: Colors.transparent,
      title: Text(resolvedTitle, style: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900)),
      content: Text(resolvedMessage, style: const TextStyle(color: AirmiusColors.muted, height: 1.4)),
      actions: [
        TextButton(onPressed: () => Navigator.pop(dialogContext, false), child: const Text('Abbrechen')),
        FilledButton(
          onPressed: () => Navigator.pop(dialogContext, true),
          style: FilledButton.styleFrom(backgroundColor: AirmiusColors.red, foregroundColor: Colors.white),
          child: Text(confirmLabel),
        ),
      ],
    ),
  );

  final confirmed = result ?? false;
  if (confirmed) {
    onConfirm?.call();
  }
  return confirmed;
}

