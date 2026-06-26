import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';

import 'core/airmius_l10n.dart';
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
import 'screens/profile_completion_gate_screen.dart';
import 'screens/shell_screen.dart';

class AirmiusApp extends StatefulWidget {
  const AirmiusApp({super.key});

  @override
  State<AirmiusApp> createState() => _AirmiusAppState();
}

class _AirmiusAppState extends State<AirmiusApp> {
  static const _apiBaseUrl = String.fromEnvironment(
    'AIRMIUS_API_BASE_URL',
    defaultValue: 'https://airmius.com',
  );

  AirmiusLanguage _language = AirmiusLanguage.de;
  ThemeMode _themeMode = ThemeMode.dark;
  final GlobalKey<NavigatorState> _navigatorKey = GlobalKey<NavigatorState>();
  final AirmiusPreferences _preferences = AirmiusPreferences();
  final AirmiusDeepLinkInbox _deepLinkInbox = AirmiusDeepLinkInbox();
  final AirmiusExternalAuthLauncher _externalAuthLauncher = const AirmiusExternalAuthLauncher();
  late final AirmiusServiceContainer _services = AirmiusServiceContainer(
    environment: const AirmiusAppEnvironment(apiBaseUrl: _apiBaseUrl, locale: 'de'),
    transport: const AirmiusHttpTransport(baseUrl: _apiBaseUrl),
  );

  @override
  void initState() {
    super.initState();
    unawaited(_restorePreferences());
    unawaited(_restoreAuth());
    _deepLinkInbox.start(_openNativeDeepLink);
    unawaited(_deepLinkInbox.restoreInitialLink(_openNativeDeepLink));
  }

  Future<void> _restoreAuth() async {
    await _services.authState.restore();
    await _completeInitialWebSocialLogin();
  }

  Future<void> _restorePreferences() async {
    final language = await _preferences.readLanguage();
    final themeMode = await _preferences.readThemeMode();
    if (!mounted) return;
    setState(() {
      if (language != null) {
        _language = language;
      }
      if (themeMode != null) {
        _themeMode = themeMode;
      }
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
            child: MaterialApp(
            navigatorKey: _navigatorKey,
            title: 'Airmius',
            debugShowCheckedModeBanner: false,
            theme: AirmiusTheme.light(),
            darkTheme: AirmiusTheme.dark(),
            themeMode: _themeMode,
            locale: _language.locale,
            supportedLocales: AirmiusLanguage.values.map((language) => language.locale).toList(),
            localizationsDelegates: const [
              GlobalMaterialLocalizations.delegate,
              GlobalCupertinoLocalizations.delegate,
              GlobalWidgetsLocalizations.delegate,
            ],
            builder: (context, child) {
              return Directionality(
                textDirection: _language.isRtl ? TextDirection.rtl : TextDirection.ltr,
                child: DefaultTextStyle.merge(
                  style: const TextStyle(decoration: TextDecoration.none),
                  child: child ?? const SizedBox.shrink(),
                ),
              );
            },
                    home: _homeForAuthState(),
            ),
          ),
        ),
      ),
    );
  }

  Widget _homeForAuthState() {
    final authState = _services.authState;
    final user = authState.user;
    if (authState.phase == AirmiusAuthPhase.authenticated) {
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
      path: '${base.path.endsWith('/') ? base.path.substring(0, base.path.length - 1) : base.path}$path',
      queryParameters: {
        'mobile': '1',
        'locale': _language.code.toLowerCase(),
        if (kIsWeb) 'return_url': _webReturnUrl(),
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
    final locale = params['locale'] ?? _language.code.toLowerCase();
    unawaited(_services.authState.signInWithToken(token: token, locale: locale));
    return true;
  }

  bool _isSocialLoginCallback(Uri uri) {
    if (uri.scheme == 'airmius' && uri.host == 'auth' && uri.path == '/callback') {
      return true;
    }

    if (kIsWeb && _socialLoginParameters(uri)['token']?.isNotEmpty == true && ['127.0.0.1', 'localhost'].contains(uri.host)) {
      return true;
    }

    return (uri.scheme == 'https' || uri.scheme == 'http') && uri.host == 'app.airmius.com' && uri.path == '/auth/callback';
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

    replaceBrowserUrl(Uri.base.replace(queryParameters: const {}, fragment: '').toString());
  }
}
