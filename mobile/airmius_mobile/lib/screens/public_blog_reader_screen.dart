import 'package:flutter/material.dart';

import 'blog_media_center_screen.dart';

/// Compatibility entry point for older public routes.
///
/// The public reader now uses the same API-backed blog center as the member
/// shell, so guests never see fabricated articles or UI-only actions.
class PublicBlogReaderScreen extends StatelessWidget {
  const PublicBlogReaderScreen({super.key, this.initialCategory = 'Alle'});

  final String initialCategory;

  @override
  Widget build(BuildContext context) => const BlogMediaCenterScreen();
}
