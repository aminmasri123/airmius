import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';

import 'core/airmius_l10n.dart';
import 'core/airmius_accessibility_scope.dart';
import 'core/airmius_auth_state.dart';
import 'core/airmius_deep_link_inbox.dart';
import 'core/airmius_external_auth_launcher.dart';
import 'core/airmius_http_transport.dart';
import 'core/airmius_preferences.dart';
import 'core/airmius_service_container.dart';
import 'core/airmius_services_scope.dart';
import 'core/airmius_theme.dart';
import 'core/airmius_theme_mode_scope.dart';
import 'core/airmius_web_location.dart';
import 'navigation/airmius_deep_link_navigator.dart';
import 'screens/login_screen.dart';
import 'screens/email_verification_screen.dart';
import 'screens/permission_onboarding_screen.dart';
import 'screens/profile_completion_gate_screen.dart';
import 'screens/shell_screen.dart';
import 'screens/two_factor_challenge_screen.dart';

class AirmiusApp extends StatefulWidget {
  const AirmiusApp({super.key});

  @override
  State<AirmiusApp> createState() => _AirmiusAppState();
}

class _AirmiusAppState extends State<AirmiusApp> {
  static const _configuredApiBaseUrl = String.fromEnvironment(
    'AIRMIUS_API_BASE_URL',
    defaultValue: '',
  );
  static String get _apiBaseUrl {
    final configured = _configuredApiBaseUrl.trim();
    if (configured.isNotEmpty) return configured;
    if (kIsWeb && (Uri.base.scheme == 'http' || Uri.base.scheme == 'https')) {
      return Uri.base.origin;
    }
    return kReleaseMode ? 'https://app.airmius.com' : 'http://localhost';
  }

  AirmiusLanguage _language = AirmiusLanguage.de;
  ThemeMode _themeMode = ThemeMode.dark;
  AirmiusThemePalette _themePalette = AirmiusThemePalette.dark;
  AirmiusTextSize _textSize = AirmiusTextSize.normal;
  final GlobalKey<NavigatorState> _navigatorKey = GlobalKey<NavigatorState>();
  final AirmiusPreferences _preferences = AirmiusPreferences();
  final AirmiusDeepLinkInbox _deepLinkInbox = AirmiusDeepLinkInbox();
  final AirmiusExternalAuthLauncher _externalAuthLauncher =
      const AirmiusExternalAuthLauncher();
  bool _authRestoreComplete = false;
  bool _preferencesRestoreComplete = false;
  bool _permissionOnboardingComplete = false;
  String? _pendingNativeDeepLink;
  String? _lastHandledSocialLink;
  late final AirmiusServiceContainer _services = AirmiusServiceContainer(
    environment: AirmiusAppEnvironment(apiBaseUrl: _apiBaseUrl, locale: 'de'),
    transport: AirmiusHttpTransport(baseUrl: _apiBaseUrl),
  );

  @override
  void initState() {
    super.initState();
    unawaited(_restorePreferences());
    _deepLinkInbox.start(_receiveNativeDeepLink);
    unawaited(_restoreAuth());
  }

  Future<void> _restoreAuth() async {
    await _services.authState.restore();
    _authRestoreComplete = true;

    final pendingLink = _pendingNativeDeepLink;
    _pendingNativeDeepLink = null;
    if (pendingLink != null) {
      _openNativeDeepLink(pendingLink);
    }

    await _deepLinkInbox.restoreInitialLink(_receiveNativeDeepLink);
    await _completeInitialWebSocialLogin();
  }

  void _receiveNativeDeepLink(String link) {
    if (!_authRestoreComplete) {
      _pendingNativeDeepLink = link;
      return;
    }

    _openNativeDeepLink(link);
  }

  Future<void> _restorePreferences() async {
    final language = await _preferences.readLanguage();
    final themeMode = await _preferences.readThemeMode();
    final themePalette = await _preferences.readThemePalette();
    final textSize = await _preferences.readTextSize();
    final permissionOnboardingComplete = await _preferences
        .readPermissionOnboardingComplete();
    if (!mounted) return;
    setState(() {
      _preferencesRestoreComplete = true;
      _permissionOnboardingComplete = permissionOnboardingComplete;
      if (language != null) {
        _language = language;
      }
      if (themeMode != null) {
        _themeMode = themeMode;
      }
      if (themePalette != null) {
        _themePalette = themePalette;
      }
      _textSize = textSize;
    });
  }

  @override
  Widget build(BuildContext context) {
    return AirmiusServicesScope(
      container: _services,
      child: AirmiusScope(
        language: _language,
        setLanguage: (language) {
          setState(() => _language = language);
          unawaited(_preferences.writeLanguage(language));
          _services.authState.updateLocale(language.code.toLowerCase());
        },
        child: AnimatedBuilder(
          animation: _services.authState,
          builder: (context, _) => AirmiusThemeModeScope(
            mode: _themeMode,
            setMode: (mode) {
              setState(() => _themeMode = mode);
              unawaited(_preferences.writeThemeMode(mode));
            },
            palette: _themePalette,
            setPalette: (palette) {
              setState(() => _themePalette = palette);
              unawaited(_preferences.writeThemePalette(palette));
            },
            child: AirmiusAccessibilityScope(
              textSize: _textSize,
              setTextSize: (textSize) {
                setState(() => _textSize = textSize);
                unawaited(_preferences.writeTextSize(textSize));
              },
              child: MaterialApp(
                navigatorKey: _navigatorKey,
                title: 'Airmius',
                debugShowCheckedModeBanner: false,
                theme: AirmiusTheme.light(_themePalette),
                darkTheme: AirmiusTheme.dark(_themePalette),
                themeMode: _themeMode,
                locale: _language.locale,
                supportedLocales: AirmiusLanguage.values
                    .map((language) => language.locale)
                    .toList(),
                localizationsDelegates: const [
                  GlobalMaterialLocalizations.delegate,
                  GlobalCupertinoLocalizations.delegate,
                  GlobalWidgetsLocalizations.delegate,
                ],
                builder: (context, child) {
                  final mediaQuery = MediaQuery.of(context);
                  final systemScale = mediaQuery.textScaler.scale(1);
                  final effectiveScale = (systemScale * _textSize.scale).clamp(
                    0.9,
                    1.6,
                  );
                  return Directionality(
                    textDirection: _language.isRtl
                        ? TextDirection.rtl
                        : TextDirection.ltr,
                    child: MediaQuery(
                      data: mediaQuery.copyWith(
                        textScaler: TextScaler.linear(effectiveScale),
                      ),
                      child: DefaultTextStyle.merge(
                        style: const TextStyle(decoration: TextDecoration.none),
                        child: child ?? const SizedBox.shrink(),
                      ),
                    ),
                  );
                },
                home: _homeForAuthState(),
              ),
            ),
          ),
        ),
      ),
    );
  }

  Widget _homeForAuthState() {
    if (!_preferencesRestoreComplete) {
      return const Scaffold(body: Center(child: CircularProgressIndicator()));
    }
    final isMobile =
        !kIsWeb &&
        (defaultTargetPlatform == TargetPlatform.android ||
            defaultTargetPlatform == TargetPlatform.iOS);
    if (isMobile && !_permissionOnboardingComplete) {
      return PermissionOnboardingScreen(
        onComplete: () async {
          await _preferences.writePermissionOnboardingComplete(true);
          if (!mounted) return;
          setState(() => _permissionOnboardingComplete = true);
        },
      );
    }
    final authState = _services.authState;
    final user = authState.user;
    if (authState.phase == AirmiusAuthPhase.twoFactorRequired) {
      return TwoFactorChallengeScreen(authState: authState);
    }
    if (authState.phase == AirmiusAuthPhase.authenticated) {
      if (user?.emailVerified == false) {
        return const EmailVerificationScreen();
      }
      if (user?.isProfileIncomplete == true) {
        return ProfileCompletionGateScreen(authState: authState);
      }
      if (user?.requiresGuardianConsent == true) {
        return GuardianConsentPendingScreen(authState: authState);
      }
      return const ShellScreen();
    }

    return LoginScreen(
      authState: _services.authState,
      onLogin: (email, password) => _services.authState.signIn(
        email: email,
        password: password,
        locale: _language.code.toLowerCase(),
      ),
      onSocialLogin: _openSocialLogin,
    );
  }

  void _openNativeDeepLink(String link) {
    if (_completeSocialLogin(link)) {
      return;
    }

    WidgetsBinding.instance.addPostFrameCallback((_) {
      final context = _navigatorKey.currentContext;
      if (context == null) {
        return;
      }
      AirmiusDeepLinkNavigator.open(context, link);
    });
  }

  void _openSocialLogin(String provider) {
    final base = Uri.parse(_apiBaseUrl);
    final path = '/auth/$provider/redirect';
    final url = base.replace(
      path:
          '${base.path.endsWith('/') ? base.path.substring(0, base.path.length - 1) : base.path}$path',
      queryParameters: {
        'mobile': '1',
        'locale': _language.code.toLowerCase(),
        'return_url': kIsWeb ? _webReturnUrl() : 'airmius://auth/callback',
      },
    );

    unawaited(_externalAuthLauncher.open(url.toString()));
  }

  bool _completeSocialLogin(String link) {
    final uri = Uri.tryParse(link);
    if (uri == null || !_isSocialLoginCallback(uri)) {
      return false;
    }

    final params = _socialLoginParameters(uri);
    final token = params['token'] ?? '';
    if (token.isNotEmpty && _lastHandledSocialLink == link) {
      return true;
    }
    _lastHandledSocialLink = link;
    final locale = params['locale'] ?? _language.code.toLowerCase();
    unawaited(
      _services.authState.signInWithToken(token: token, locale: locale),
    );
    return true;
  }

  bool _isSocialLoginCallback(Uri uri) {
    if (uri.scheme == 'airmius' &&
        uri.host == 'auth' &&
        uri.path == '/callback') {
      return true;
    }

    if (kIsWeb &&
        _socialLoginParameters(uri)['token']?.isNotEmpty == true &&
        ['127.0.0.1', 'localhost'].contains(uri.host)) {
      return true;
    }

    return (uri.scheme == 'https' || uri.scheme == 'http') &&
        uri.host == 'app.airmius.com' &&
        uri.path == '/auth/callback';
  }

  Map<String, String> _socialLoginParameters(Uri uri) {
    final params = <String, String>{...uri.queryParameters};
    var fragment = uri.fragment.trim();
    if (fragment.startsWith('?') || fragment.startsWith('&')) {
      fragment = fragment.substring(1);
    }
    if (fragment.isNotEmpty) {
      params.addAll(Uri.splitQueryString(fragment));
    }

    return params;
  }

  Future<void> _completeInitialWebSocialLogin() async {
    if (!kIsWeb) {
      return;
    }

    await Future<void>.delayed(Duration.zero);
    if (_completeSocialLogin(Uri.base.toString())) {
      _clearWebAuthQuery();
    }
  }

  String _webReturnUrl() {
    final base = Uri.base;
    return base.replace(queryParameters: const {}, fragment: '').toString();
  }

  void _clearWebAuthQuery() {
    if (!kIsWeb) return;

    replaceBrowserUrl(
      Uri.base.replace(queryParameters: const {}, fragment: '').toString(),
    );
  }
}
