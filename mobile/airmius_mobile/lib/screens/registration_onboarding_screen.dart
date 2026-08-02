import 'package:flutter/material.dart';

import '../core/airmius_api_client.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';
import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'clubs_screen.dart';

class RegistrationOnboardingScreen extends StatelessWidget {
  const RegistrationOnboardingScreen({super.key, required this.accountType});

  final String accountType;

  Future<void> _startSetup(BuildContext context) async {
    final result = await Navigator.push<bool>(
      context,
      MaterialPageRoute<bool>(
        fullscreenDialog: true,
        builder: (_) => accountType == 'club'
            ? const ClubCreateWizardScreen()
            : const TrainerRegistrationFormScreen(),
      ),
    );
    if (result == true && context.mounted) {
      Navigator.pop(context, true);
    }
  }

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final isClub = accountType == 'club';
    return Scaffold(
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      appBar: AppBar(
        title: Text(t('registrationOnboarding.title')),
        surfaceTintColor: Colors.transparent,
      ),
      body: PageFrame(
        title: isClub
            ? t('registrationOnboarding.clubTitle')
            : t('registrationOnboarding.trainerTitle'),
        subtitle: t('registrationOnboarding.subtitle'),
        child: AirmiusPanel(
          gradient: true,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Icon(
                isClub ? Icons.apartment_outlined : Icons.sports_outlined,
                size: 48,
                color: airmiusAccentColor(context),
              ),
              const SizedBox(height: 14),
              Text(
                isClub
                    ? t('registrationOnboarding.clubBody')
                    : t('registrationOnboarding.trainerBody'),
                style: TextStyle(
                  color: airmiusTextColor(context),
                  height: 1.4,
                  fontWeight: FontWeight.w700,
                ),
              ),
              const SizedBox(height: 18),
              AirmiusButton(
                label: t('registrationOnboarding.startNow'),
                icon: Icons.arrow_forward_outlined,
                onPressed: () => _startSetup(context),
              ),
              const SizedBox(height: 10),
              AirmiusButton(
                label: t('registrationOnboarding.later'),
                icon: Icons.schedule_outlined,
                secondary: true,
                onPressed: () => Navigator.pop(context, false),
              ),
              const SizedBox(height: 10),
              Text(
                t('registrationOnboarding.laterHint'),
                textAlign: TextAlign.center,
                style: TextStyle(color: airmiusMutedColor(context)),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class TrainerRegistrationFormScreen extends StatefulWidget {
  const TrainerRegistrationFormScreen({super.key});

  @override
  State<TrainerRegistrationFormScreen> createState() =>
      _TrainerRegistrationFormScreenState();
}

class _TrainerRegistrationFormScreenState
    extends State<TrainerRegistrationFormScreen> {
  final _specialties = TextEditingController();
  final _experience = TextEditingController();
  final _certification = TextEditingController();
  final _message = TextEditingController();
  bool _saving = false;
  String? _error;

  @override
  void dispose() {
    _specialties.dispose();
    _experience.dispose();
    _certification.dispose();
    _message.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    final t = AirmiusScope.of(context).t;
    if (_specialties.text.trim().isEmpty || _experience.text.trim().isEmpty) {
      setState(() => _error = t('registrationOnboarding.trainerRequired'));
      return;
    }
    setState(() {
      _saving = true;
      _error = null;
    });
    try {
      final client = AirmiusServicesScope.of(
        context,
      ).clientForSession(AirmiusServicesScope.of(context).authState.session);
      await client.submitRoleApplication(
        type: 'trainer',
        message: _message.text.trim(),
        applicationData: {
          'specialties': _specialties.text.trim(),
          'experience': _experience.text.trim(),
          'certification': _certification.text.trim(),
        },
      );
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(t('registrationOnboarding.trainerSubmitted'))),
      );
      Navigator.pop(context, true);
    } on AirmiusApiException catch (error) {
      if (mounted) setState(() => _error = error.userMessage);
    } catch (_) {
      if (mounted) setState(() => _error = t('common.errorDetails'));
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return Scaffold(
      appBar: AppBar(
        title: Text(t('registrationOnboarding.trainerTitle')),
        surfaceTintColor: Colors.transparent,
      ),
      body: PageFrame(
        title: t('registrationOnboarding.trainerTitle'),
        subtitle: t('registrationOnboarding.subtitle'),
        child: AirmiusPanel(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              AirmiusTextField(
                label: t('registrationOnboarding.specialties'),
                controller: _specialties,
              ),
              const SizedBox(height: 12),
              AirmiusTextField(
                label: t('registrationOnboarding.experience'),
                controller: _experience,
                maxLines: 4,
              ),
              const SizedBox(height: 12),
              AirmiusTextField(
                label: t('registrationOnboarding.certification'),
                controller: _certification,
              ),
              const SizedBox(height: 12),
              AirmiusTextField(
                label: t('registrationOnboarding.message'),
                controller: _message,
                maxLines: 4,
              ),
              if (_error != null) ...[
                const SizedBox(height: 12),
                Text(
                  _error!,
                  style: const TextStyle(
                    color: AirmiusColors.red,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ],
              const SizedBox(height: 16),
              AirmiusButton(
                label: _saving
                    ? t('registrationOnboarding.saving')
                    : t('registrationOnboarding.submitTrainer'),
                icon: Icons.send_outlined,
                onPressed: _saving ? null : _submit,
              ),
            ],
          ),
        ),
      ),
    );
  }
}
