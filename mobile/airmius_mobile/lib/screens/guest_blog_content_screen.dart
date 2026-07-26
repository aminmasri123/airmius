import 'package:flutter/material.dart';

import 'blog_media_center_screen.dart';

/// Compatibility route for older guest-blog links.
///
/// Published articles and categories are owned by the editorial API. The
/// former local article list was static sample content and is not shown.
class GuestBlogContentScreen extends StatelessWidget {
  const GuestBlogContentScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return const BlogMediaCenterScreen();
  }
}
