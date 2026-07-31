import 'dart:async';

import 'package:flutter/material.dart';

import '../core/airmius_api_client.dart';
import '../core/airmius_api_models.dart';
import '../core/airmius_auth_state.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';
import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

class ProfileCompletionGateScreen extends StatefulWidget {
  const ProfileCompletionGateScreen({super.key, required this.authState});

  final AirmiusAuthState authState;

  @override
  State<ProfileCompletionGateScreen> createState() =>
      _ProfileCompletionGateScreenState();
}

class _ProfileCompletionGateScreenState
    extends State<ProfileCompletionGateScreen> {
  late final TextEditingController _firstNameController;
  late final TextEditingController _lastNameController;
  late final TextEditingController _birthDateController;
  late final TextEditingController _countryController;
  late final TextEditingController _guardianEmailController;
  String _gender = '';
  String _accountType = 'athlete';
  String? _localError;

  @override
  void initState() {
    super.initState();
    final user = widget.authState.user;
    _firstNameController = TextEditingController(
      text: user?.firstName ?? _splitName(user).$1 ?? '',
    );
    _lastNameController = TextEditingController(
      text: user?.lastName ?? _splitName(user).$2 ?? '',
    );
    _birthDateController = TextEditingController(
      text: _dateText(user?.birthDate),
    );
    _countryController = TextEditingController(
      text: (user?.country ?? 'DE').toUpperCase(),
    );
    _guardianEmailController = TextEditingController(
      text: user?.guardianEmail ?? '',
    );
    _gender = _genderValues.contains(user?.gender) ? user!.gender! : '';
  }

  @override
  void dispose() {
    _firstNameController.dispose();
    _lastNameController.dispose();
    _birthDateController.dispose();
    _countryController.dispose();
    _guardianEmailController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final scope = AirmiusScope.of(context);
    final loading = widget.authState.phase == AirmiusAuthPhase.loading;
    final birthDate = DateTime.tryParse(_birthDateController.text.trim());
    final minor = birthDate != null && _isMinor(birthDate);
    final error = _localError ?? widget.authState.error;

    return Scaffold(
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      body: SafeArea(
        child: Center(
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 560),
            child: ListView(
              padding: const EdgeInsets.all(20),
              shrinkWrap: true,
              children: [
                const SizedBox(height: 12),
                Icon(
                  Icons.shield_outlined,
                  color: Theme.of(context).colorScheme.primary,
                  size: 48,
                ),
                const SizedBox(height: 18),
                Text(
                  scope.t('profileGate.title'),
                  textAlign: TextAlign.center,
                  style: TextStyle(
                    color: airmiusTextColor(context),
                    fontSize: 28,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 8),
                Text(
                  scope.t('profileGate.body'),
                  textAlign: TextAlign.center,
                  style: TextStyle(
                    color: airmiusMutedColor(context),
                    height: 1.4,
                  ),
                ),
                const SizedBox(height: 22),
                AirmiusPanel(
                  child: Column(
                    children: [
                      Align(
                        alignment: AlignmentDirectional.centerStart,
                        child: Text(
                          scope.t('accountType.question'),
                          style: TextStyle(
                            color: airmiusTextColor(context),
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                      ),
                      const SizedBox(height: 10),
                      Align(
                        alignment: AlignmentDirectional.centerStart,
                        child: Wrap(
                          spacing: 8,
                          runSpacing: 8,
                          children: [
                            for (final type in const [
                              ('athlete', Icons.directions_run_outlined),
                              ('coach', Icons.sports_outlined),
                              ('club', Icons.apartment_outlined),
                              ('sponsor', Icons.handshake_outlined),
                            ])
                              ChoiceChip(
                                selected: _accountType == type.$1,
                                avatar: Icon(type.$2, size: 18),
                                label: Text(scope.t('accountType.${type.$1}')),
                                onSelected: loading
                                    ? null
                                    : (_) => setState(
                                        () => _accountType = type.$1,
                                      ),
                              ),
                          ],
                        ),
                      ),
                      const SizedBox(height: 16),
                      AirmiusTextField(
                        label: scope.t('profileGate.firstName'),
                        icon: Icons.person_outline,
                        controller: _firstNameController,
                      ),
                      const SizedBox(height: 12),
                      AirmiusTextField(
                        label: scope.t('profileGate.lastName'),
                        icon: Icons.badge_outlined,
                        controller: _lastNameController,
                      ),
                      const SizedBox(height: 12),
                      AirmiusTextField(
                        label: scope.t('profileGate.birthDate'),
                        hint: scope.t('profileGate.birthDateHint'),
                        icon: Icons.cake_outlined,
                        controller: _birthDateController,
                        keyboardType: TextInputType.datetime,
                        suffixIcon: IconButton(
                          onPressed: loading ? null : _pickBirthDate,
                          icon: Icon(
                            Icons.calendar_month_outlined,
                            color: Theme.of(context).colorScheme.primary,
                          ),
                        ),
                        onChanged: (_) => setState(() {}),
                      ),
                      const SizedBox(height: 12),
                      DropdownButtonFormField<String>(
                        initialValue: _gender.isEmpty ? null : _gender,
                        dropdownColor: airmiusSurfaceSoftColor(context),
                        decoration: InputDecoration(
                          labelText: scope.t('profileGate.gender'),
                          prefixIcon: Icon(
                            Icons.wc_outlined,
                            color: airmiusMutedColor(context),
                          ),
                        ),
                        style: TextStyle(
                          color: airmiusTextColor(context),
                          fontWeight: FontWeight.w800,
                        ),
                        items: [
                          DropdownMenuItem(
                            value: 'female',
                            child: Text(scope.t('profileGate.gender.female')),
                          ),
                          DropdownMenuItem(
                            value: 'male',
                            child: Text(scope.t('profileGate.gender.male')),
                          ),
                          DropdownMenuItem(
                            value: 'diverse',
                            child: Text(scope.t('profileGate.gender.diverse')),
                          ),
                          DropdownMenuItem(
                            value: 'not_specified',
                            child: Text(
                              scope.t('profileGate.gender.notSpecified'),
                            ),
                          ),
                        ],
                        onChanged: loading
                            ? null
                            : (value) => setState(() => _gender = value ?? ''),
                      ),
                      const SizedBox(height: 12),
                      AirmiusTextField(
                        label: scope.t('profileGate.country'),
                        hint: scope.t('profileGate.countryHint'),
                        icon: Icons.public_outlined,
                        controller: _countryController,
                      ),
                      if (minor) ...[
                        const SizedBox(height: 12),
                        AirmiusTextField(
                          label: scope.t('profileGate.guardianEmail'),
                          icon: Icons.family_restroom_outlined,
                          controller: _guardianEmailController,
                          keyboardType: TextInputType.emailAddress,
                        ),
                      ],
                      if (error != null && error.isNotEmpty) ...[
                        const SizedBox(height: 14),
                        Text(
                          error,
                          style: TextStyle(
                            color: AirmiusColors.red,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                      ],
                    ],
                  ),
                ),
                const SizedBox(height: 18),
                AirmiusButton(
                  label: loading
                      ? scope.t('profileGate.saving')
                      : scope.t('profileGate.save'),
                  icon: Icons.save_outlined,
                  onPressed: loading ? null : _save,
                ),
                const SizedBox(height: 10),
                AirmiusButton(
                  label: scope.t('profileGate.signOut'),
                  icon: Icons.logout_outlined,
                  secondary: true,
                  onPressed: loading ? null : widget.authState.signOut,
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Future<void> _pickBirthDate() async {
    final initial =
        DateTime.tryParse(_birthDateController.text.trim()) ??
        DateTime(DateTime.now().year - 18, 1, 1);
    final picked = await showDatePicker(
      context: context,
      initialDate: initial,
      firstDate: DateTime(1900),
      lastDate: DateTime.now(),
    );
    if (picked == null) return;
    setState(() => _birthDateController.text = _dateText(picked));
  }

  Future<void> _save() async {
    final birthDate = DateTime.tryParse(_birthDateController.text.trim());
    final country = _countryController.text.trim().toUpperCase();
    if (_firstNameController.text.trim().isEmpty ||
        _lastNameController.text.trim().isEmpty ||
        birthDate == null ||
        _gender.isEmpty ||
        country.length != 2) {
      setState(
        () =>
            _localError = AirmiusScope.of(context).t('profileGate.validation'),
      );
      return;
    }
    if (_isMinor(birthDate) && _guardianEmailController.text.trim().isEmpty) {
      setState(
        () => _localError = AirmiusScope.of(
          context,
        ).t('profileGate.minorValidation'),
      );
      return;
    }

    setState(() => _localError = null);
    await widget.authState.completeProfile(
      payload: {
        'first_name': _firstNameController.text.trim(),
        'account_type': _accountType,
        'last_name': _lastNameController.text.trim(),
        'birth_date': _dateText(birthDate),
        'gender': _gender,
        'country': country,
        'guardian_email': _guardianEmailController.text.trim(),
      },
    );
  }
}

class GuardianConsentPendingScreen extends StatefulWidget {
  const GuardianConsentPendingScreen({super.key, required this.authState});

  final AirmiusAuthState authState;

  @override
  State<GuardianConsentPendingScreen> createState() =>
      _GuardianConsentPendingScreenState();
}

class _GuardianConsentPendingScreenState
    extends State<GuardianConsentPendingScreen> {
  AirmiusApiClient? _client;
  AirmiusGuardianConsentStatus? _status;
  Timer? _timer;
  int _seconds = 0;
  bool _loading = true;
  bool _sending = false;
  String? _error;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (_client != null) return;
    final services = AirmiusServicesScope.of(context);
    _client = services.clientForSession(services.authState.session);
    unawaited(_loadStatus());
  }

  @override
  void dispose() {
    _timer?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final scope = AirmiusScope.of(context);
    final email =
        _status?.guardianEmail ?? widget.authState.user?.guardianEmail;
    return Scaffold(
      body: SafeArea(
        child: Center(
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 520),
            child: ListView(
              padding: const EdgeInsets.all(20),
              shrinkWrap: true,
              children: [
                AirmiusPanel(
                  gradient: true,
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Container(
                        width: 76,
                        height: 76,
                        decoration: BoxDecoration(
                          color: Theme.of(
                            context,
                          ).colorScheme.primary.withValues(alpha: 0.13),
                          shape: BoxShape.circle,
                        ),
                        child: Icon(
                          Icons.mark_email_unread_outlined,
                          color: Theme.of(context).colorScheme.primary,
                          size: 42,
                        ),
                      ),
                      const SizedBox(height: 18),
                      Text(
                        scope.t('guardian.pendingTitle'),
                        textAlign: TextAlign.center,
                        style: Theme.of(context).textTheme.headlineSmall
                            ?.copyWith(fontWeight: FontWeight.w900),
                      ),
                      const SizedBox(height: 10),
                      Text(
                        email == null || email.isEmpty
                            ? scope.t('guardian.pendingBody')
                            : scope
                                  .t('guardian.pendingBodyWithEmail')
                                  .replaceFirst('{email}', email),
                        textAlign: TextAlign.center,
                        style: Theme.of(
                          context,
                        ).textTheme.bodyLarge?.copyWith(height: 1.45),
                      ),
                      if (_loading) ...[
                        const SizedBox(height: 18),
                        const LinearProgressIndicator(minHeight: 3),
                      ],
                      if (_error != null) ...[
                        const SizedBox(height: 14),
                        Text(
                          _error!,
                          textAlign: TextAlign.center,
                          style: TextStyle(
                            color: Theme.of(context).colorScheme.error,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                      ],
                      const SizedBox(height: 22),
                      AirmiusButton(
                        label: scope.t('guardian.statusRefresh'),
                        icon: Icons.refresh_outlined,
                        onPressed: _loading ? null : _refresh,
                      ),
                      const SizedBox(height: 10),
                      AirmiusButton(
                        label: _seconds > 0
                            ? scope
                                  .t('guardian.resendOwnIn')
                                  .replaceFirst('{seconds}', '$_seconds')
                            : scope.t('guardian.resendOwn'),
                        icon: Icons.outgoing_mail,
                        secondary: true,
                        onPressed: _loading || _sending || _seconds > 0
                            ? null
                            : _resend,
                      ),
                      const SizedBox(height: 10),
                      AirmiusButton(
                        label: scope.t('guardian.signOut'),
                        icon: Icons.logout_outlined,
                        secondary: true,
                        onPressed: _sending ? null : widget.authState.signOut,
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 14),
                AirmiusPanel(
                  child: Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Icon(
                        Icons.shield_outlined,
                        color: Theme.of(context).colorScheme.primary,
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Text(
                          scope.t('guardian.safetyBody'),
                          style: Theme.of(
                            context,
                          ).textTheme.bodyMedium?.copyWith(height: 1.4),
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Future<void> _loadStatus() async {
    if (_client == null) return;
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final response = await _client!.guardianConsentStatus();
      final status = AirmiusGuardianConsentStatus.fromJson(response);
      if (!mounted) return;
      setState(() {
        _status = status;
        _seconds = status.resendAvailableIn;
      });
      _startCountdown();
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _error = error is AirmiusApiException
            ? error.userMessage
            : AirmiusScope.of(context).t('guardian.pendingLoadError');
      });
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _refresh() async {
    await _loadStatus();
    await widget.authState.refreshUser();
  }

  Future<void> _resend() async {
    setState(() {
      _sending = true;
      _error = null;
    });
    try {
      final response = await _client!.resendOwnGuardianConsent();
      final status = AirmiusGuardianConsentStatus.fromJson(response);
      if (!mounted) return;
      setState(() {
        _status = status;
        _seconds = status.resendAvailableIn;
      });
      _startCountdown();
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(AirmiusScope.of(context).t('guardian.pendingResent')),
        ),
      );
    } catch (error) {
      if (!mounted) return;
      setState(() {
        _error = error is AirmiusApiException
            ? error.userMessage
            : AirmiusScope.of(context).t('guardian.actionError');
      });
    } finally {
      if (mounted) setState(() => _sending = false);
    }
  }

  void _startCountdown() {
    _timer?.cancel();
    if (_seconds <= 0) return;
    _timer = Timer.periodic(const Duration(seconds: 1), (timer) {
      if (!mounted) return;
      if (_seconds <= 1) {
        timer.cancel();
        setState(() => _seconds = 0);
      } else {
        setState(() => _seconds--);
      }
    });
  }
}

const _genderValues = ['female', 'male', 'diverse', 'not_specified'];

(String?, String?) _splitName(AirmiusUser? user) {
  final name = user?.name.trim() ?? '';
  if (name.isEmpty) return (null, null);
  final parts = name.split(RegExp(r'\s+'));
  return (parts.first, parts.length > 1 ? parts.sublist(1).join(' ') : null);
}

String _dateText(DateTime? date) {
  if (date == null) return '';
  final month = date.month.toString().padLeft(2, '0');
  final day = date.day.toString().padLeft(2, '0');
  return '${date.year}-$month-$day';
}

bool _isMinor(DateTime birthDate) {
  final today = DateTime.now();
  var age = today.year - birthDate.year;
  if (today.month < birthDate.month ||
      (today.month == birthDate.month && today.day < birthDate.day)) {
    age--;
  }
  return age < 16;
}
