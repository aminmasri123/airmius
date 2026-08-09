import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:airmius/core/airmius_l10n.dart';

void main() {
  test(
    'outfit admin forms and statuses stay translated in French and Arabic',
    () {
      const arabic = AirmiusScope(
        language: AirmiusLanguage.ar,
        setLanguage: _ignoreLanguage,
        child: SizedBox.shrink(),
      );
      const french = AirmiusScope(
        language: AirmiusLanguage.fr,
        setLanguage: _ignoreLanguage,
        child: SizedBox.shrink(),
      );

      expect(french.t('outfitAdmin.deliveryMonth'), 'Mois de livraison');
      expect(
        french.t('outfitAdmin.deleteSubscriptionBody'),
        contains('historique'),
      );
      expect(
        french.t('outfitAdmin.status.replacement_preparing'),
        contains('préparation'),
      );
      expect(arabic.t('outfitAdmin.deliveryMonth'), 'شهر التوصيل');
      expect(arabic.t('outfitAdmin.deleteSubscriptionBody'), contains('سجله'));
      expect(
        arabic.t('outfitAdmin.status.replacement_preparing'),
        contains('البديل'),
      );
      expect(french.t('mailAdmin.sendTest'), 'Envoyer un e-mail test');
      expect(arabic.t('mailAdmin.sendTest'), 'إرسال بريد تجريبي');
      expect(french.t('systemAdmin.loadFailed'), contains('Impossible'));
      expect(arabic.t('systemAdmin.loadFailed'), contains('تعذر'));
      expect(french.t('commerceOps.galleryUrls'), contains('galerie'));
      expect(arabic.t('commerceOps.galleryUrls'), contains('المعرض'));
      expect(french.t('commerceOps.deleteProductBody'), contains('archivés'));
      expect(arabic.t('commerceOps.deleteProductBody'), contains('تُؤرشف'));
      expect(french.t('events.repeat.weekly'), 'Chaque semaine');
      expect(arabic.t('events.repeat.weekly'), 'أسبوعيًا');
      expect(french.t('events.recurrenceDayRequired'), contains('jour'));
      expect(arabic.t('events.recurrenceEndError'), contains('نهاية'));
    },
  );

  test('keeps common outfit status copy translated in French and Arabic', () {
    const arabic = AirmiusScope(
      language: AirmiusLanguage.ar,
      setLanguage: _ignoreLanguage,
      child: SizedBox.shrink(),
    );
    const french = AirmiusScope(
      language: AirmiusLanguage.fr,
      setLanguage: _ignoreLanguage,
      child: SizedBox.shrink(),
    );

    expect(arabic.t('outfit.plan'), 'خطة الملابس');
    expect(arabic.t('outfit.subscription'), 'اشتراك الملابس');
    expect(french.t('outfit.plan'), 'Formule tenue');
    expect(french.t('outfit.subscription'), 'Abonnement tenue');
  });

  test('keeps file scope and error copy translated in French and Arabic', () {
    const arabic = AirmiusScope(
      language: AirmiusLanguage.ar,
      setLanguage: _ignoreLanguage,
      child: SizedBox.shrink(),
    );
    const french = AirmiusScope(
      language: AirmiusLanguage.fr,
      setLanguage: _ignoreLanguage,
      child: SizedBox.shrink(),
    );

    expect(french.t('files.scope.team'), 'Équipe');
    expect(french.t('files.readFailed'), contains('Impossible'));
    expect(arabic.t('files.scope.team'), 'الفريق');
    expect(arabic.t('files.readFailed'), contains('تعذر'));
    expect(french.t('filesOps.uploadAction'), 'Démarrer l’import');
    expect(arabic.t('filesOps.uploadAction'), 'بدء الرفع');
    expect(french.t('shared.download'), 'Télécharger');
    expect(arabic.t('shared.download'), 'تنزيل');
    expect(french.t('membership.statusTitle'), 'Ma demande d’adhésion');
    expect(arabic.t('membership.statusTitle'), 'طلب عضويتي');
    expect(french.t('application.send'), 'Envoyer la demande');
    expect(arabic.t('application.send'), 'إرسال الطلب');
  });

  test('keeps native language names suitable for the language picker', () {
    expect(AirmiusLanguage.fr.label, 'Français');
    expect(AirmiusLanguage.ar.label, 'العربية');
  });

  test('public recruiting copy is complete in every supported language', () {
    const scopes = [
      AirmiusScope(
        language: AirmiusLanguage.de,
        setLanguage: _ignoreLanguage,
        child: SizedBox.shrink(),
      ),
      AirmiusScope(
        language: AirmiusLanguage.en,
        setLanguage: _ignoreLanguage,
        child: SizedBox.shrink(),
      ),
      AirmiusScope(
        language: AirmiusLanguage.fr,
        setLanguage: _ignoreLanguage,
        child: SizedBox.shrink(),
      ),
      AirmiusScope(
        language: AirmiusLanguage.ar,
        setLanguage: _ignoreLanguage,
        child: SizedBox.shrink(),
      ),
    ];
    const keys = [
      'recruitingMobile.title',
      'recruitingMobile.search',
      'recruitingMobile.professional',
      'recruitingMobile.volunteer',
      'recruitingMobile.interestTitle',
      'recruitingMobile.privacy',
      'recruitingMobile.submitError',
      'recruitingMobile.sent',
    ];

    for (final scope in scopes) {
      for (final key in keys) {
        expect(scope.t(key), isNot(key));
        expect(scope.t(key).trim(), isNotEmpty);
      }
    }

    expect(scopes[2].t('recruitingMobile.title'), contains('bénévolat'));
    expect(scopes[3].t('recruitingMobile.title'), contains('التطوع'));
  });

  test('coverage status copy stays localized for the release audit', () {
    const french = AirmiusScope(
      language: AirmiusLanguage.fr,
      setLanguage: _ignoreLanguage,
      child: SizedBox.shrink(),
    );
    const arabic = AirmiusScope(
      language: AirmiusLanguage.ar,
      setLanguage: _ignoreLanguage,
      child: SizedBox.shrink(),
    );

    expect(french.t('release.coverageSubtitle'), contains('modules'));
    expect(arabic.t('release.coverageSubtitle'), contains('الوحدات'));
    expect(french.t('release.coverage.nativeReady'), contains('prête'));
    expect(arabic.t('release.coverage.opsLinked'), contains('العمليات'));
    expect(french.t('release.coverageBody'), contains('module'));
    expect(arabic.t('release.coverageBody'), contains('وحدة'));
    expect(french.t('release.coverage.apiLinked'), contains('API'));
    expect(arabic.t('release.coverage.apiLinked'), contains('API'));
    expect(french.t('release.coverageOperationsHub'), contains('opérations'));
    expect(arabic.t('release.coverageOperationsHub'), contains('العمليات'));
    expect(french.t('fitness.providerStrava'), contains('OAuth'));
    expect(arabic.t('fitness.providerGoogleFit'), contains('Google Fit'));
  });

  test('privacy center v2 copy is complete in every supported language', () {
    const scopes = [
      AirmiusScope(
        language: AirmiusLanguage.de,
        setLanguage: _ignoreLanguage,
        child: SizedBox.shrink(),
      ),
      AirmiusScope(
        language: AirmiusLanguage.en,
        setLanguage: _ignoreLanguage,
        child: SizedBox.shrink(),
      ),
      AirmiusScope(
        language: AirmiusLanguage.fr,
        setLanguage: _ignoreLanguage,
        child: SizedBox.shrink(),
      ),
      AirmiusScope(
        language: AirmiusLanguage.ar,
        setLanguage: _ignoreLanguage,
        child: SizedBox.shrink(),
      ),
    ];
    const keys = [
      'privacy.productAnalytics',
      'privacy.productAnalyticsHint',
      'privacy.connectedProviders',
      'privacy.connectedProvidersHint',
      'privacy.noConnectedProviders',
      'privacy.loginProvider',
      'privacy.sportProvider',
      'privacy.manageProviders',
    ];

    for (final scope in scopes) {
      for (final key in keys) {
        expect(scope.t(key), isNot(key));
        expect(scope.t(key).trim(), isNotEmpty);
      }
    }

    expect(scopes[2].t('privacy.connectedProviders'), contains('connectés'));
    expect(scopes[3].t('privacy.connectedProviders'), contains('المرتبطة'));
  });

  test(
    'auth errors follow the selected language and preserve status context',
    () {
      expect(
        airmiusAuthMessage(
          AirmiusLanguage.en,
          'auth.error.server',
          status: 503,
        ),
        'Server error (HTTP 503). Please try again later.',
      );
      expect(
        airmiusAuthMessage(AirmiusLanguage.fr, 'auth.error.invalidCredentials'),
        contains('mot de passe'),
      );
      expect(
        airmiusAuthMessage(AirmiusLanguage.ar, 'auth.error.network'),
        contains('الشبكة'),
      );
    },
  );

  test(
    'keeps German module labels in German instead of using English fallback',
    () {
      const german = AirmiusScope(
        language: AirmiusLanguage.de,
        setLanguage: _ignoreLanguage,
        child: SizedBox.shrink(),
      );
      const english = AirmiusScope(
        language: AirmiusLanguage.en,
        setLanguage: _ignoreLanguage,
        child: SizedBox.shrink(),
      );
      const french = AirmiusScope(
        language: AirmiusLanguage.fr,
        setLanguage: _ignoreLanguage,
        child: SizedBox.shrink(),
      );
      const arabic = AirmiusScope(
        language: AirmiusLanguage.ar,
        setLanguage: _ignoreLanguage,
        child: SizedBox.shrink(),
      );

      expect(german.copy('Arbeitsbereiche'), 'Arbeitsbereiche');
      expect(english.copy('Arbeitsbereiche'), 'Workspaces');
      expect(french.copy('Teams'), 'Équipes');
      expect(arabic.copy('Arbeitsbereiche'), 'مساحات العمل');
      expect(arabic.copy('Events & Training'), 'الفعاليات والتدريب');
      expect(arabic.copy('Commerce'), 'التجارة');
    },
  );

  test('account and security actions have readable translations', () {
    const keys = <String>[
      'account.title',
      'account.profilePhoto',
      'account.changePassword',
      'account.activeSessions',
      'account.deleteAccount',
      'account.requestDeletionCode',
      'account.confirmationCode',
      'account.passwordMismatch',
      'settings.notifications',
      'settings.notificationsBody',
      'notificationSettings.title',
      'notificationSettings.channel.push',
      'notificationSettings.save',
      'teams.title',
      'release.dataRights',
      'release.guardianConsent',
      'release.legalStatus',
      'release.privacyConsent',
      'release.reportModeration',
      'moderation.report.explanation',
      'moderation.report.reason',
      'moderation.report.reason.insult',
      'moderation.report.reason.bullying',
      'moderation.report.reason.hate',
      'moderation.report.reason.sexual',
      'moderation.report.reason.violence',
      'moderation.report.reason.threat',
      'moderation.report.reason.imageRights',
      'moderation.report.reason.spam',
      'moderation.report.reason.other',
      'moderation.report.details',
      'moderation.report.detailsHint',
      'moderation.report.cancel',
      'moderation.report.submit',
      'release.supportHelpdesk',
      'workspace.title',
      'workspace.context',
      'workspace.clubBody',
      'workspace.preferences',
      'workspace.preferencesBody',
      'workspace.openSettings',
      'workspace.permissionsUnknown',
      'workspace.permissionsCount',
      'workspace.audit',
      'adminHub.forbiddenBody',
      'clubEditor.loadFailed',
      'messages.team',
      'messages.event',
      'messages.support',
      'teamDetail.teamspace',
      'updates.headline',
      'updates.withdrawFailed',
      'search.ops',
      'search.filter.club',
      'search.apiError',
      'files.scope',
      'files.scope.mine',
      'files.uploadTitle',
      'files.folderDelete',
      'files.storage',
      'filesOps.title',
      'filesOps.tab.application',
      'filesOps.uploadBody',
      'filesPreview.required',
      'shared.title',
      'shared.expired',
      'uiAction.behavior',
      'uiAction.nextBackendBody',
      'membership.loadingClubs',
      'membership.selectClubFirst',
      'membership.sent',
      'membership.requiredFields',
      'membership.statusTitle',
      'membership.statusLoadFailed',
      'application.send',
      'application.cycle.fourMonthly',
      'membership.inboxLoadFailed',
      'membership.inboxActionFailed',
      'membership.documentOps',
      'membership.admin',
      'membership.adminHint',
      'membership.noManagedClub',
      'membership.completeManagement',
      'membership.completeManagementHint',
      'membership.newRequest',
      'membership.noOpenRequests',
      'membership.saving',
      'membership.approve',
      'membership.decline',
      'membership.operations',
      'membership.requestsEnabled',
      'membership.requestsDisabled',
      'membership.field.hidden',
      'membership.field.optional',
      'membership.contributionRulesHint',
      'membership.linkedDocuments',
      'membership.noDocuments',
      'deepLink.membership_application.title',
      'deepLink.membership_application.action',
      'deepLink.unknownPath',
      'deepLink.recognized',
      'deepLink.routingAudit',
      'deepLink.detailFailed',
      'deepLink.fallbackBody',
      'deepLink.authRequired',
      'deepLink.previewRequest',
      'deepLink.previewStatus',
      'deepLink.previewClub',
      'deepLink.previewType',
      'deepLink.previewStart',
      'deepLink.previewEvent',
      'deepLink.previewUnread',
      'deepLink.previewRead',
      'deepLink.retryLater',
      'feed.storyPrevious',
      'feed.storyNext',
      'auth.error.invalidCredentials',
      'auth.error.invalidInput',
      'auth.error.networkLogin',
      'auth.error.network',
      'auth.error.connectionLogin',
      'auth.error.connection',
      'auth.error.server',
      'auth.error.request',
      'auth.error.fallback',
      'auth.error.unexpected',
      'auth.error.tokenLogin',
      'auth.error.tokenRegister',
      'auth.error.tokenSocial',
      'auth.error.twoFactorMissing',
      'auth.error.accountExists',
      'authFlow.subtitle',
      'authFlow.title',
      'authFlow.accountOps',
      'authFlow.eyebrow',
      'authFlow.intro',
      'authFlow.register',
      'authFlow.social',
      'authFlow.password',
      'authFlow.twoFactor',
      'authFlow.email',
      'authFlow.profile',
      'authFlow.suspended',
      'authFlow.delete',
      'authFlow.passwordMismatch',
      'authFlow.genderRequired',
      'authFlow.dateChoose',
      'authFlow.street',
      'authFlow.number',
      'authFlow.postal',
      'authFlow.city',
      'authFlow.terms',
      'authFlow.guardianMinor',
      'authFlow.googleRegister',
      'authFlow.outlookRegister',
      'authFlow.loading',
      'authFlow.create',
      'authFlow.socialBody',
      'authFlow.oauthStatus',
      'authFlow.appleStatus',
      'authFlow.accountLinking',
      'authFlow.startGoogle',
      'authFlow.startApple',
      'authFlow.provider',
      'authFlow.ios',
      'authFlow.linking',
      'authFlow.profilePersonal',
      'authFlow.profilePersonalBody',
      'authFlow.profileSport',
      'authFlow.profileSportBody',
      'authFlow.open',
      'authFlow.profileVisibility',
      'authFlow.profileVisibilityBody',
      'authFlow.profileCheck',
      'authFlow.profileComplete',
      'authFlow.suspendedBody',
      'authFlow.status',
      'authFlow.supportDetails',
      'authFlow.contactSupport',
      'authFlow.deleteBody',
      'authFlow.deleteCode',
      'authFlow.deleteCodeHint',
      'authFlow.export',
      'authFlow.exportBody',
      'authFlow.finalDelete',
      'authFlow.finalDeleteBody',
      'authFlow.recommended',
      'authFlow.critical',
      'authFlow.sendDelete',
      'authFlow.deleteFinal',
      'profileGate.title',
      'profileGate.body',
      'profileGate.firstName',
      'profileGate.lastName',
      'profileGate.birthDate',
      'profileGate.birthDateHint',
      'profileGate.gender',
      'profileGate.gender.female',
      'profileGate.gender.male',
      'profileGate.gender.diverse',
      'profileGate.gender.notSpecified',
      'profileGate.country',
      'profileGate.countryHint',
      'profileGate.guardianEmail',
      'profileGate.validation',
      'profileGate.minorValidation',
      'profileGate.save',
      'profileGate.saving',
      'profileGate.signOut',
      'profile.edit.eyebrow',
      'profile.edit.personal',
      'profile.edit.bio',
      'profile.edit.bioHint',
      'profile.edit.save',
      'profile.edit.saving',
      'profile.edit.cancel',
      'profile.edit.validation',
      'profile.edit.birthCountryMissing',
      'clubEditor.title',
      'clubEditor.subtitle',
      'clubEditor.noManagedClub',
      'clubEditor.required',
      'clubEditor.saved',
      'clubEditor.saveFailed',
      'clubEditor.managedClub',
      'clubEditor.basicData',
      'clubEditor.country',
      'clubEditor.address',
      'clubEditor.street',
      'clubEditor.houseNumber',
      'clubEditor.region',
      'clubEditor.visibility',
      'clubEditor.listed',
      'clubEditor.listedHint',
      'clubEditor.teamsListed',
      'clubEditor.teamsListedHint',
      'clubEditor.clubPosting',
      'clubEditor.clubPostingHint',
      'clubEditor.teamPosting',
      'clubEditor.teamPostingHint',
      'clubEditor.membershipSettings',
      'clubEditor.openPublicProfile',
      'maturity.title',
      'maturity.subtitle',
      'maturity.reload',
      'maturity.score',
      'maturity.weeklyTrainings',
      'maturity.connections',
      'maturity.routes',
      'maturity.posts',
      'maturity.xp',
      'maturity.dimensions',
      'maturity.onboarding',
      'maturity.nextActions',
      'maturity.complete',
      'maturity.open',
      'maturity.empty',
      'maturity.loadFailed',
      'maturity.dimension.onboarding',
      'maturity.dimension.social',
      'maturity.dimension.training',
      'maturity.dimension.maps',
      'maturity.dimension.content',
      'maturity.dimension.safety',
      'maturity.dimension.xp',
      'mediaPolicy.title',
      'mediaPolicy.subtitle',
      'mediaPolicy.intro',
      'mediaPolicy.upload',
      'mediaPolicy.uploadHint',
      'mediaPolicy.consent',
      'mediaPolicy.consentHint',
      'mediaPolicy.guardian',
      'mediaPolicy.guardianHint',
      'mediaPolicy.report',
      'mediaPolicy.reportHint',
      'mediaPolicy.openFiles',
      'mediaPolicy.openPrivacy',
      'mediaPolicy.openGuardian',
      'mediaPolicy.openSupport',
      'mediaPolicy.serverRule',
      'publicInterest.titleSuffix',
      'publicInterest.subtitle',
      'publicInterest.name',
      'publicInterest.email',
      'publicInterest.message',
      'publicInterest.privacy',
      'publicInterest.required',
      'publicInterest.invalidEmail',
      'publicInterest.messageTooShort',
      'publicInterest.send',
      'publicInterest.sending',
      'publicInterest.sent',
      'publicInterest.sentBody',
      'publicInterest.sendFailed',
      'publicInterest.suggestLocation',
      'publicInterest.kindMarketplace',
      'publicInterest.kindClub',
      'publicInterest.kindPartner',
      'publicInterest.kindLearning',
      'publicInterest.kindPublic',
      'publicInterest.kindDefault',
      'publicLocation.title',
      'publicLocation.subtitle',
      'publicLocation.type',
      'publicLocation.club',
      'publicLocation.venue',
      'publicLocation.provider',
      'publicLocation.correction',
      'publicLocation.name',
      'publicLocation.nameHint',
      'publicLocation.address',
      'publicLocation.addressHint',
      'publicLocation.description',
      'publicLocation.descriptionHint',
      'publicLocation.contactName',
      'publicLocation.email',
      'publicLocation.visibility',
      'publicLocation.visibilityHint',
      'publicLocation.privacy',
      'publicLocation.send',
      'publicLocation.sendCorrection',
      'publicLocation.sending',
      'publicLocation.sent',
      'publicLocation.sentBody',
      'publicLocation.required',
      'publicLocation.invalidEmail',
      'publicLocation.messageTooShort',
      'publicLocation.sendFailed',
      'support.name',
      'support.email',
      'support.invalidEmail',
      'support.guestPrivacy',
      'support.guestSentBody',
      'guestPortal.title',
      'guestPortal.subtitle',
      'guestPortal.eyebrow',
      'guestPortal.headline',
      'guestPortal.body',
      'guestPortal.clubs',
      'guestPortal.features',
      'guestPortal.pricing',
      'guestPortal.contact',
      'guestPortal.topContent',
      'guestPortal.legal',
      'guestPortal.blog',
      'guestPortal.blogBody',
      'guestPortal.marketplace',
      'guestPortal.marketplaceBody',
      'guestPortal.learning',
      'guestPortal.learningBody',
      'guestPortal.jobs',
      'guestPortal.jobsBody',
      'guestPortal.sponsors',
      'guestPortal.sponsorsBody',
      'guestPortal.gamification',
      'guestPortal.gamificationBody',
      'guestPortal.agency',
      'guestPortal.agencyBody',
      'guestPortal.contactBody',
      'guestPortal.imprint',
      'guestPortal.privacy',
      'guestPortal.terms',
      'guestPortal.guidelines',
      'guestPortal.youth',
      'publicTop.title',
      'publicTop.subtitle',
      'publicTop.hero',
      'publicTop.all',
      'publicTop.blog',
      'publicTop.sponsors',
      'publicTop.reload',
      'publicTop.empty',
      'publicTop.openBlog',
      'publicTop.openSponsors',
      'certificate.title',
      'certificate.subtitle',
      'certificate.eyebrow',
      'certificate.intro',
      'certificate.code',
      'certificate.codeHint',
      'certificate.required',
      'certificate.check',
      'certificate.checking',
      'certificate.valid',
      'certificate.notFound',
      'certificate.error',
      'certificate.student',
      'certificate.course',
      'certificate.courseSubtitle',
      'certificate.issued',
      'certificate.tutor',
      'certificate.report',
      'certificate.reportTopic',
      'guestMarket.title',
      'guestMarket.subtitle',
      'guestMarket.eyebrow',
      'guestMarket.intro',
      'guestMarket.search',
      'guestMarket.reload',
      'guestMarket.retry',
      'guestMarket.all',
      'guestMarket.empty',
      'guestMarket.noMatch',
      'guestMarket.interest',
      'guestMarket.priceOnRequest',
      'guestMarket.loadError',
      'guestClubs.title',
      'guestClubs.subtitle',
      'guestClubs.eyebrow',
      'guestClubs.intro',
      'guestClubs.search',
      'guestClubs.location',
      'guestClubs.allSports',
      'guestClubs.reload',
      'guestClubs.retry',
      'guestClubs.empty',
      'guestClubs.loadError',
      'guestClubs.club',
      'guestClubs.official',
      'guestClubs.teams',
      'guestClubs.membership',
      'guestClubs.noMembership',
      'guestClubs.interested',
      'guestLearning.title',
      'guestLearning.subtitle',
      'guestLearning.eyebrow',
      'guestLearning.intro',
      'guestLearning.courses',
      'guestLearning.free',
      'guestLearning.search',
      'guestLearning.reload',
      'guestLearning.retry',
      'guestLearning.all',
      'guestLearning.empty',
      'guestLearning.noMatch',
      'guestLearning.interest',
      'guestLearning.lessons',
      'guestLearning.untitled',
      'guestLearning.priceOnRequest',
      'guestLearning.loadError',
      'guestLearning.verifyTitle',
      'guestLearning.verifyBody',
      'guestLearning.code',
      'guestLearning.codeHint',
      'guestLearning.codeRequired',
      'guestLearning.verify',
      'guestLearning.support',
      'publicDetail.eyebrow',
      'publicDetail.publicDataTitle',
      'publicDetail.publicDataBody',
      'publicDetail.safeTitle',
      'publicDetail.safeBody',
      'publicDetail.nextTitle',
      'publicDetail.nextBody',
      'publicDetail.openLegal',
      'publicDetail.contact',
      'publicDetail.openTopContent',
      'publicDetail.openBlog',
      'publicDetail.courseInterest',
      'publicDetail.verifyCertificate',
      'publicDetail.learningTopic',
      'publicDetail.contactProvider',
      'publicDetail.kindPublic',
      'publicDetail.kindLegal',
    ];
    for (final language in AirmiusLanguage.values) {
      final scope = AirmiusScope(
        language: language,
        setLanguage: _ignoreLanguage,
        child: const SizedBox.shrink(),
      );
      for (final key in keys) {
        final value = scope.t(key);
        expect(value, isNot(key), reason: '$language is missing $key');
        expect(value.trim(), isNotEmpty, reason: '$language has empty $key');
      }
    }
  });

  test('deep-link routing copy is translated in French and Arabic', () {
    const french = AirmiusScope(
      language: AirmiusLanguage.fr,
      setLanguage: _ignoreLanguage,
      child: SizedBox.shrink(),
    );
    const arabic = AirmiusScope(
      language: AirmiusLanguage.ar,
      setLanguage: _ignoreLanguage,
      child: SizedBox.shrink(),
    );

    expect(french.t('deepLink.event.body'), contains('L’application'));
    expect(
      french.t('deepLink.destination.event'),
      'Événements et entraînements',
    );
    expect(arabic.t('deepLink.event.body'), contains('التطبيق'));
    expect(arabic.t('deepLink.destination.event'), 'الفعاليات والتدريب');
    expect(french.t('deepLink.unknown.title'), 'Lien profond');
    expect(arabic.t('deepLink.unknown.title'), 'رابط عميق');
  });

  test('data-erasure safety copy is native in French and Arabic', () {
    const french = AirmiusScope(
      language: AirmiusLanguage.fr,
      setLanguage: _ignoreLanguage,
      child: SizedBox.shrink(),
    );
    const arabic = AirmiusScope(
      language: AirmiusLanguage.ar,
      setLanguage: _ignoreLanguage,
      child: SizedBox.shrink(),
    );

    expect(french.t('privacy.eraseData'), contains('Supprimer'));
    expect(french.t('privacy.eraseEmailIdentityHint'), contains('associé'));
    expect(arabic.t('privacy.eraseData'), contains('حذف'));
    expect(arabic.t('privacy.eraseEmailIdentityHint'), contains('حساب'));
  });
}

void _ignoreLanguage(AirmiusLanguage _) {}
