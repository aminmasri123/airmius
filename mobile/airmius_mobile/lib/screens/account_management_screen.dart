import 'dart:convert';

import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;

import '../core/airmius_api_client.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';
import '../widgets/airmius_widgets.dart';
import 'email_verification_screen.dart';
import 'two_factor_security_screen.dart';

class AccountManagementScreen extends StatefulWidget {
  const AccountManagementScreen({super.key});

  @override
  State<AccountManagementScreen> createState() =>
      _AccountManagementScreenState();
}

class _AccountManagementScreenState extends State<AccountManagementScreen> {
  final _currentPassword = TextEditingController();
  final _newPassword = TextEditingController();
  final _passwordConfirmation = TextEditingController();
  final _deletionPassword = TextEditingController();
  final _deletionCode = TextEditingController();

  List<_AccountSession> _sessions = const [];
  bool _loadingSessions = true;
  bool _busy = false;
  bool _deletionCodeSent = false;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _loadSessions());
  }

  @override
  void dispose() {
    _currentPassword.dispose();
    _newPassword.dispose();
    _passwordConfirmation.dispose();
    _deletionPassword.dispose();
    _deletionCode.dispose();
    super.dispose();
  }

  AirmiusApiClient get _client {
    final services = AirmiusServicesScope.of(context);
    return services.clientForSession(services.authState.session);
  }

  @override
  Widget build(BuildContext context) {
    final user = AirmiusServicesScope.of(context).authState.user;
    final avatarUrl = resolveAirmiusImageUrl(user?.avatarUrl);
    final t = AirmiusScope.of(context).t;
    final theme = Theme.of(context);
    final scheme = theme.colorScheme;
    return Scaffold(
      backgroundColor: theme.scaffoldBackgroundColor,
      appBar: AppBar(title: Text(t('account.title'))),
      body: SafeArea(
        child: ListView(
          padding: const EdgeInsets.fromLTRB(16, 16, 16, 32),
          children: [
            AirmiusPanel(
              title: t('account.securityTitle'),
              gradient: true,
              child: Column(
                children: [
                  ListTile(
                    contentPadding: EdgeInsets.zero,
                    leading: Icon(
                      user?.emailVerified == true
                          ? Icons.mark_email_read_outlined
                          : Icons.mark_email_unread_outlined,
                      color: user?.emailVerified == true
                          ? scheme.tertiary
                          : scheme.secondary,
                    ),
                    title: Text(
                      t('account.emailStatus'),
                      style: const TextStyle(fontWeight: FontWeight.w900),
                    ),
                    subtitle: Text(
                      t(
                        user?.emailVerified == true
                            ? 'account.statusVerified'
                            : 'account.statusUnverified',
                      ),
                    ),
                    trailing: user?.emailVerified == true
                        ? Icon(Icons.check_circle, color: scheme.tertiary)
                        : TextButton(
                            onPressed: _busy
                                ? null
                                : () => _openSecurityScreen(
                                    const EmailVerificationScreen(),
                                  ),
                            child: Text(t('account.manage')),
                          ),
                  ),
                  const Divider(),
                  ListTile(
                    contentPadding: EdgeInsets.zero,
                    leading: Icon(
                      user?.twoFactorEnabled == true
                          ? Icons.verified_user
                          : Icons.security_outlined,
                      color: user?.twoFactorEnabled == true
                          ? scheme.tertiary
                          : scheme.secondary,
                    ),
                    title: Text(
                      t('account.twoFactor'),
                      style: const TextStyle(fontWeight: FontWeight.w900),
                    ),
                    subtitle: Text(
                      t(
                        user?.twoFactorEnabled == true
                            ? 'account.statusEnabled'
                            : 'account.statusDisabled',
                      ),
                    ),
                    trailing: TextButton(
                      onPressed: _busy
                          ? null
                          : () => _openSecurityScreen(
                              const TwoFactorSecurityScreen(),
                            ),
                      child: Text(t('account.manage')),
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 14),
            AirmiusPanel(
              title: t('account.profilePhoto'),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Row(
                    children: [
                      CircleAvatar(
                        radius: 34,
                        backgroundImage: avatarUrl == null
                            ? null
                            : NetworkImage(avatarUrl),
                        child: avatarUrl == null
                            ? const Icon(Icons.person_outline, size: 34)
                            : null,
                      ),
                      const SizedBox(width: 14),
                      Expanded(
                        child: Text(
                          user?.name ?? t('account.profile'),
                          style: theme.textTheme.titleMedium?.copyWith(
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 14),
                  Wrap(
                    spacing: 10,
                    runSpacing: 10,
                    children: [
                      AirmiusButton(
                        label: t('account.choosePhoto'),
                        icon: Icons.photo_library_outlined,
                        onPressed: _busy ? null : _pickAndUploadPhoto,
                      ),
                      AirmiusButton(
                        label: t('account.removePhoto'),
                        icon: Icons.delete_outline,
                        secondary: true,
                        onPressed: _busy ? null : _removePhoto,
                      ),
                    ],
                  ),
                ],
              ),
            ),
            const SizedBox(height: 14),
            AirmiusPanel(
              title: t('account.changePassword'),
              child: Column(
                children: [
                  AirmiusTextField(
                    label: t('account.currentPassword'),
                    hint: t('account.currentPassword'),
                    icon: Icons.lock_outline,
                    controller: _currentPassword,
                    obscureText: true,
                  ),
                  const SizedBox(height: 10),
                  AirmiusTextField(
                    label: t('account.newPassword'),
                    hint: t('account.newPasswordHint'),
                    icon: Icons.password_outlined,
                    controller: _newPassword,
                    obscureText: true,
                  ),
                  const SizedBox(height: 10),
                  AirmiusTextField(
                    label: t('account.confirmPassword'),
                    hint: t('account.confirmPasswordHint'),
                    icon: Icons.check_circle_outline,
                    controller: _passwordConfirmation,
                    obscureText: true,
                  ),
                  const SizedBox(height: 12),
                  Align(
                    alignment: Alignment.centerLeft,
                    child: AirmiusButton(
                      label: t('account.savePassword'),
                      icon: Icons.save_outlined,
                      onPressed: _busy ? null : _changePassword,
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 14),
            AirmiusPanel(
              title: t('account.activeSessions'),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  if (_loadingSessions)
                    const Center(
                      child: Padding(
                        padding: EdgeInsets.all(16),
                        child: CircularProgressIndicator(),
                      ),
                    )
                  else if (_sessions.isEmpty)
                    Text(t('account.noSessions'))
                  else
                    for (final session in _sessions)
                      ListTile(
                        contentPadding: EdgeInsets.zero,
                        leading: Icon(
                          session.current
                              ? Icons.phone_android
                              : Icons.devices_other,
                          color: session.current
                              ? scheme.tertiary
                              : scheme.primary,
                        ),
                        title: Text(
                          session.deviceName.isEmpty
                              ? t('account.unknownDevice')
                              : session.deviceName,
                          style: theme.textTheme.titleSmall?.copyWith(
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                        subtitle: Text(
                          session.current
                              ? t('account.thisDevice')
                              : session.lastUsedLabel(t),
                        ),
                        trailing: IconButton(
                          tooltip: t('account.endSession'),
                          onPressed: _busy ? null : () => _endSession(session),
                          icon: Icon(
                            Icons.logout_outlined,
                            color: scheme.error,
                          ),
                        ),
                      ),
                  const SizedBox(height: 8),
                  Wrap(
                    spacing: 10,
                    runSpacing: 10,
                    children: [
                      AirmiusButton(
                        label: t('account.refreshSessions'),
                        icon: Icons.refresh,
                        secondary: true,
                        onPressed: _busy ? null : _loadSessions,
                      ),
                      AirmiusButton(
                        label: t('account.signOutOthers'),
                        icon: Icons.phonelink_erase,
                        danger: true,
                        onPressed: _busy ? null : _endOtherSessions,
                      ),
                    ],
                  ),
                ],
              ),
            ),
            const SizedBox(height: 14),
            AirmiusPanel(
              title: t('account.deleteAccount'),
              borderColor: scheme.error.withValues(alpha: .55),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Text(t('account.deleteAccountDescription')),
                  const SizedBox(height: 12),
                  AirmiusTextField(
                    label: t('account.identityPassword'),
                    hint: t('account.identityHint'),
                    icon: Icons.lock_outline,
                    controller: _deletionPassword,
                    obscureText: true,
                  ),
                  if (_deletionCodeSent) ...[
                    const SizedBox(height: 10),
                    AirmiusTextField(
                      label: t('account.confirmationCode'),
                      hint: t('account.confirmationCodeHint'),
                      icon: Icons.pin_outlined,
                      controller: _deletionCode,
                    ),
                  ],
                  const SizedBox(height: 12),
                  AirmiusButton(
                    label: _deletionCodeSent
                        ? t('account.deleteAccountFinal')
                        : t('account.requestDeletionCode'),
                    icon: Icons.delete_forever_outlined,
                    danger: true,
                    onPressed: _busy
                        ? null
                        : (_deletionCodeSent
                              ? _deleteAccount
                              : _requestDeletionCode),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  Future<void> _loadSessions() async {
    if (mounted) setState(() => _loadingSessions = true);
    try {
      final response = await _client.accountSessions();
      final raw = response['data'];
      final sessions = raw is List
          ? raw
                .whereType<Map>()
                .map(
                  (item) =>
                      _AccountSession.fromJson(Map<String, dynamic>.from(item)),
                )
                .toList()
          : <_AccountSession>[];
      if (mounted) setState(() => _sessions = sessions);
    } catch (error) {
      _showError(error);
    } finally {
      if (mounted) setState(() => _loadingSessions = false);
    }
  }

  Future<void> _openSecurityScreen(Widget screen) async {
    await Navigator.of(
      context,
    ).push(MaterialPageRoute<void>(builder: (_) => screen));
    if (mounted) setState(() {});
  }

  Future<void> _changePassword() async {
    final t = AirmiusScope.of(context).t;
    if (_newPassword.text != _passwordConfirmation.text) {
      _toast(t('account.passwordMismatch'));
      return;
    }
    await _run(() async {
      await _client.updatePassword(
        currentPassword: _currentPassword.text,
        password: _newPassword.text,
        passwordConfirmation: _passwordConfirmation.text,
      );
      _currentPassword.clear();
      _newPassword.clear();
      _passwordConfirmation.clear();
      _toast(t('account.passwordChanged'));
      await _loadSessions();
    });
  }

  Future<void> _pickAndUploadPhoto() async {
    final t = AirmiusScope.of(context).t;
    final result = await FilePicker.platform.pickFiles(
      type: FileType.image,
      withData: true,
    );
    final file = result?.files.single;
    if (file == null) return;
    await _run(() async {
      final services = AirmiusServicesScope.of(context);
      final session = services.authState.session;
      if (session == null) throw StateError(t('account.noSessionError'));
      final base = Uri.parse(_client.baseUrl);
      final path =
          '${base.path.endsWith('/') ? base.path : '${base.path}/'}api/v1/me/profile-photo';
      final request =
          http.MultipartRequest(
              'POST',
              base.replace(path: path, query: null, fragment: null),
            )
            ..headers['Authorization'] = 'Bearer ${session.token}'
            ..headers['Accept'] = 'application/json';
      if (file.bytes != null) {
        request.files.add(
          http.MultipartFile.fromBytes(
            'photo',
            file.bytes!,
            filename: file.name,
          ),
        );
      } else if (file.path != null) {
        request.files.add(
          await http.MultipartFile.fromPath(
            'photo',
            file.path!,
            filename: file.name,
          ),
        );
      } else {
        throw StateError(t('account.photoReadError'));
      }
      final response = await http.Response.fromStream(await request.send());
      if (response.statusCode < 200 || response.statusCode >= 300) {
        throw AirmiusApiException(
          statusCode: response.statusCode,
          body: response.body,
          path: '/api/v1/me/profile-photo',
        );
      }
      await services.authState.refreshUser();
      _toast(t('account.photoUpdated'));
    });
  }

  Future<void> _removePhoto() async {
    final t = AirmiusScope.of(context).t;
    final authState = AirmiusServicesScope.of(context).authState;
    await _run(() async {
      await _client.deleteProfilePhoto();
      await authState.refreshUser();
      _toast(t('account.photoRemoved'));
    });
  }

  Future<void> _endSession(_AccountSession session) async {
    final t = AirmiusScope.of(context).t;
    final authState = AirmiusServicesScope.of(context).authState;
    final confirmed = await _confirm(
      '${t('account.endSession')}?',
      session.current
          ? t('account.sessionCurrentBody')
          : '${t('account.sessionOtherBodyPrefix')}${session.deviceName}${t('account.sessionOtherBodySuffix')}',
    );
    if (!confirmed) return;
    await _run(() async {
      await _client.endAccountSession(session.id);
      if (session.current) {
        await authState.signOut();
        if (mounted) Navigator.of(context).popUntil((route) => route.isFirst);
      } else {
        await _loadSessions();
      }
    });
  }

  Future<void> _endOtherSessions() async {
    final t = AirmiusScope.of(context).t;
    await _run(() async {
      await _client.endOtherAccountSessions();
      _toast(t('account.sessionsEnded'));
      await _loadSessions();
    });
  }

  Future<void> _requestDeletionCode() async {
    final t = AirmiusScope.of(context).t;
    if (_deletionPassword.text.trim().isEmpty) {
      _toast(t('account.deletionPasswordRequired'));
      return;
    }
    await _run(() async {
      await _client.requestAccountDeletionCode(
        password: _deletionPassword.text.trim(),
      );
      if (mounted) setState(() => _deletionCodeSent = true);
      _toast(t('account.deletionCodeSent'));
    });
  }

  Future<void> _deleteAccount() async {
    final t = AirmiusScope.of(context).t;
    final authState = AirmiusServicesScope.of(context).authState;
    if (_deletionCode.text.trim().isEmpty) {
      _toast(t('account.deletionCodeRequired'));
      return;
    }
    final confirmed = await _confirm(
      '${t('account.deleteAccountFinal')}?',
      t('account.deleteWarning'),
    );
    if (!confirmed) return;
    await _run(() async {
      await _client.deleteAccount(code: _deletionCode.text.trim());
      await authState.signOut();
      if (mounted) Navigator.of(context).popUntil((route) => route.isFirst);
    });
  }

  Future<void> _run(Future<void> Function() action) async {
    if (_busy) return;
    setState(() => _busy = true);
    try {
      await action();
    } catch (error) {
      _showError(error);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  void _showError(Object error) {
    var message = AirmiusScope.of(context).t('account.error');
    if (error is AirmiusApiException) {
      try {
        final decoded = jsonDecode(error.body);
        if (decoded is Map && decoded['message'] != null) {
          message = decoded['message'].toString();
        }
        if (decoded is Map &&
            decoded['errors'] is Map &&
            (decoded['errors'] as Map).isNotEmpty) {
          final value = (decoded['errors'] as Map).values.first;
          if (value is List && value.isNotEmpty) {
            message = value.first.toString();
          }
        }
      } catch (_) {}
    }
    _toast(message);
  }

  void _toast(String message) {
    if (!mounted) return;
    ScaffoldMessenger.of(
      context,
    ).showSnackBar(SnackBar(content: Text(message)));
  }

  Future<bool> _confirm(String title, String body) async {
    return await showDialog<bool>(
          context: context,
          builder: (context) => AlertDialog(
            title: Text(title),
            content: Text(body),
            actions: [
              TextButton(
                onPressed: () => Navigator.pop(context, false),
                child: Text(AirmiusScope.of(context).t('account.cancel')),
              ),
              FilledButton(
                onPressed: () => Navigator.pop(context, true),
                child: Text(AirmiusScope.of(context).t('account.confirm')),
              ),
            ],
          ),
        ) ??
        false;
  }
}

class _AccountSession {
  const _AccountSession({
    required this.id,
    required this.deviceName,
    required this.current,
    this.lastUsedAt,
  });

  factory _AccountSession.fromJson(Map<String, dynamic> json) =>
      _AccountSession(
        id: (json['id'] as num?)?.toInt() ?? 0,
        deviceName: json['device_name']?.toString() ?? '',
        current: json['current'] == true,
        lastUsedAt: DateTime.tryParse(json['last_used_at']?.toString() ?? ''),
      );

  final int id;
  final String deviceName;
  final bool current;
  final DateTime? lastUsedAt;

  String lastUsedLabel(String Function(String) t) {
    if (lastUsedAt == null) return t('account.lastUsedNever');
    final date = lastUsedAt!.toLocal();
    final value =
        '${date.day.toString().padLeft(2, '0')}.${date.month.toString().padLeft(2, '0')}.${date.year}';
    return '${t('account.lastUsed')} $value';
  }
}
