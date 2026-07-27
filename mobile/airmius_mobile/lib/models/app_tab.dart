enum AppTab { training, clubs, feed, nutrition, profile }

extension AppTabLabel on AppTab {
  String get i18nKey => switch (this) {
        AppTab.training => 'training.nav',
        AppTab.clubs => 'clubs',
        AppTab.feed => 'feed.title',
        AppTab.nutrition => 'nutrition.title',
        AppTab.profile => 'profile',
      };
}
