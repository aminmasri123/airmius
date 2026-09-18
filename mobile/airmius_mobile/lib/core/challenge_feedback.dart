import 'airmius_api_client.dart';
import 'airmius_l10n.dart';

String challengeRetryLabel(AirmiusLanguage language) => switch (language) {
  AirmiusLanguage.de => 'Erneut versuchen',
  AirmiusLanguage.en => 'Try again',
  AirmiusLanguage.fr => 'Réessayer',
  AirmiusLanguage.ar => 'حاول مجددًا',
};

String challengeErrorMessage(Object? error, AirmiusLanguage language) {
  final status = error is AirmiusApiException ? error.statusCode : null;
  final messages = switch (status) {
    401 => [
      'Bitte melde dich erneut an.',
      'Please sign in again.',
      'Reconnecte-toi.',
      'يرجى تسجيل الدخول مجددًا.',
    ],
    403 => [
      'Du hast für diese Aktion keine Berechtigung.',
      'You do not have permission for this action.',
      'Tu n’as pas accès à cette action.',
      'ليس لديك إذن لتنفيذ هذا الإجراء.',
    ],
    404 => [
      'Diese Challenge ist nicht mehr verfügbar.',
      'This challenge is no longer available.',
      'Ce défi n’est plus disponible.',
      'هذا التحدي لم يعد متاحًا.',
    ],
    422 => [
      'Bitte prüfe deine Eingaben und ob die Challenge noch aktiv ist.',
      'Please check your entries and whether the challenge is still active.',
      'Vérifie tes données et si le défi est toujours actif.',
      'تحقق من بياناتك ومن أن التحدي لا يزال نشطًا.',
    ],
    429 => [
      'Bitte warte einen Moment und versuche es dann erneut.',
      'Please wait a moment, then try again.',
      'Patiente un instant, puis réessaie.',
      'انتظر قليلًا ثم حاول مجددًا.',
    ],
    _ => [
      'Das hat gerade nicht geklappt. Prüfe deine Internetverbindung und versuche es erneut.',
      'That did not work. Check your internet connection and try again.',
      'Cela n’a pas fonctionné. Vérifie ta connexion Internet et réessaie.',
      'لم تنجح العملية. تحقق من اتصال الإنترنت وحاول مجددًا.',
    ],
  };
  return messages[switch (language) {
    AirmiusLanguage.de => 0,
    AirmiusLanguage.en => 1,
    AirmiusLanguage.fr => 2,
    AirmiusLanguage.ar => 3,
  }];
}
