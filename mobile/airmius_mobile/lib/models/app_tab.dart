enum AppTab { dashboard, clubs, feed, updates, profile }

extension AppTabLabel on AppTab {
  String get i18nKey => switch (this) {
        AppTab.dashboard => 'dashboard',
        AppTab.clubs => 'clubs',
        AppTab.feed => 'feed.title',
        AppTab.updates => 'updates',
        AppTab.profile => 'profile',
      };
}

