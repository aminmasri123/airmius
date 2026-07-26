import 'package:flutter/material.dart';
import 'package:permission_handler/permission_handler.dart';

import '../core/airmius_l10n.dart';
import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

class PermissionOnboardingScreen extends StatefulWidget {
  const PermissionOnboardingScreen({required this.onComplete, super.key});

  final Future<void> Function() onComplete;

  @override
  State<PermissionOnboardingScreen> createState() =>
      _PermissionOnboardingScreenState();
}

class _PermissionOnboardingScreenState
    extends State<PermissionOnboardingScreen> {
  final Map<Permission, PermissionStatus> _statuses = {};
  bool _requesting = false;

  @override
  void initState() {
    super.initState();
    _refreshStatuses();
  }

  Future<void> _refreshStatuses() async {
    final statuses = <Permission, PermissionStatus>{};
    for (final permission in _permissions) {
      statuses[permission] = await permission.status;
    }
    if (!mounted) return;
    setState(
      () => _statuses
        ..clear()
        ..addAll(statuses),
    );
  }

  Future<void> _requestAll() async {
    if (_requesting) return;
    setState(() => _requesting = true);
    try {
      for (final permission in _permissions) {
        if (!mounted) return;
        final status = await permission.status;
        if (!status.isGranted && !status.isPermanentlyDenied) {
          _statuses[permission] = await permission.request();
          if (mounted) setState(() {});
        }
      }
    } finally {
      if (mounted) setState(() => _requesting = false);
    }
  }

  Future<void> _request(Permission permission) async {
    final current = await permission.status;
    if (current.isPermanentlyDenied || current.isRestricted) {
      await openAppSettings();
      await _refreshStatuses();
      return;
    }
    final status = await permission.request();
    if (!mounted) return;
    setState(() => _statuses[permission] = status);
  }

  Future<void> _complete() async {
    if (_requesting) return;
    setState(() => _requesting = true);
    try {
      await widget.onComplete();
    } finally {
      if (mounted) setState(() => _requesting = false);
    }
  }

  static const _permissions = [
    Permission.notification,
    Permission.camera,
    Permission.locationWhenInUse,
  ];

  @override
  Widget build(BuildContext context) {
    final scope = AirmiusScope.of(context);
    final theme = Theme.of(context);
    final text = theme.textTheme.bodyLarge?.color ?? AirmiusColors.text;
    final muted = theme.textTheme.bodyMedium?.color ?? AirmiusColors.muted;
    return Scaffold(
      backgroundColor: theme.scaffoldBackgroundColor,
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(20),
          child: Center(
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 620),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const SizedBox(height: 12),
                  const AirmiusLogo(),
                  const SizedBox(height: 24),
                  Text(
                    scope.t('permissions.welcomeTitle'),
                    style: TextStyle(
                      color: text,
                      fontSize: 28,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                  const SizedBox(height: 8),
                  Text(
                    scope.t('permissions.welcomeBody'),
                    style: TextStyle(color: muted, height: 1.45),
                  ),
                  const SizedBox(height: 22),
                  _PermissionCard(
                    icon: Icons.notifications_active_outlined,
                    color: airmiusSemanticColor(context, AirmiusColors.blue),
                    title: scope.t('permissions.notificationsTitle'),
                    body: scope.t('permissions.notificationsBody'),
                    status: _statuses[Permission.notification],
                    onPressed: () => _request(Permission.notification),
                    scope: scope,
                  ),
                  const SizedBox(height: 12),
                  _PermissionCard(
                    icon: Icons.camera_alt_outlined,
                    color: airmiusSemanticColor(context, AirmiusColors.pink),
                    title: scope.t('permissions.cameraTitle'),
                    body: scope.t('permissions.cameraBody'),
                    status: _statuses[Permission.camera],
                    onPressed: () => _request(Permission.camera),
                    scope: scope,
                  ),
                  const SizedBox(height: 12),
                  _PermissionCard(
                    icon: Icons.location_on_outlined,
                    color: airmiusSemanticColor(context, AirmiusColors.green),
                    title: scope.t('permissions.locationTitle'),
                    body: scope.t('permissions.locationBody'),
                    status: _statuses[Permission.locationWhenInUse],
                    onPressed: () => _request(Permission.locationWhenInUse),
                    scope: scope,
                  ),
                  const SizedBox(height: 22),
                  AirmiusButton(
                    label: _requesting
                        ? scope.t('permissions.pleaseWait')
                        : scope.t('permissions.allow'),
                    icon: Icons.verified_user_outlined,
                    onPressed: _requesting ? null : _requestAll,
                  ),
                  const SizedBox(height: 10),
                  AirmiusButton(
                    label: scope.t('permissions.continue'),
                    icon: Icons.arrow_forward,
                    secondary: true,
                    onPressed: _requesting ? null : _complete,
                  ),
                  const SizedBox(height: 12),
                  Text(
                    scope.t('permissions.optional'),
                    textAlign: TextAlign.center,
                    style: TextStyle(color: muted, fontSize: 12, height: 1.4),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}

class _PermissionCard extends StatelessWidget {
  const _PermissionCard({
    required this.icon,
    required this.color,
    required this.title,
    required this.body,
    required this.status,
    required this.onPressed,
    required this.scope,
  });

  final IconData icon;
  final Color color;
  final String title;
  final String body;
  final PermissionStatus? status;
  final VoidCallback onPressed;
  final AirmiusScope scope;

  @override
  Widget build(BuildContext context) {
    final granted = status?.isGranted == true;
    final settings =
        status?.isPermanentlyDenied == true || status?.isRestricted == true;
    final text = airmiusTextColor(context);
    final muted = airmiusMutedColor(context);
    final semantic = airmiusSemanticColor(context, color);
    final success = airmiusSemanticColor(context, AirmiusColors.green);
    return Semantics(
      container: true,
      label: '$title. $body',
      child: AirmiusPanel(
        borderColor: granted ? success : semantic.withValues(alpha: .45),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Container(
              width: 48,
              height: 48,
              decoration: BoxDecoration(
                color: semantic.withValues(alpha: .14),
                borderRadius: BorderRadius.circular(15),
              ),
              child: Icon(icon, color: semantic),
            ),
            const SizedBox(width: 14),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    title,
                    style: TextStyle(color: text, fontWeight: FontWeight.w900),
                  ),
                  const SizedBox(height: 4),
                  Text(body, style: TextStyle(color: muted, height: 1.35)),
                  const SizedBox(height: 10),
                  TextButton.icon(
                    onPressed: granted ? null : onPressed,
                    icon: Icon(
                      granted ? Icons.check_circle : Icons.settings_outlined,
                      size: 18,
                    ),
                    label: Text(
                      granted
                          ? scope.t('permissions.allowed')
                          : settings
                          ? scope.t('permissions.settings')
                          : scope.t('permissions.allowOne'),
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}
