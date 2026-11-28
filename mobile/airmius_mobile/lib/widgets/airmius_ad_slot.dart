import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

import '../core/airmius_api_client.dart';
import '../core/airmius_api_models.dart';
import '../core/airmius_external_url.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';

/// All placements use the same server-side eligibility and consent rules.
class AirmiusAdSlot extends StatefulWidget {
  const AirmiusAdSlot({
    super.key,
    required this.placement,
    this.revision = 0,
    this.guest = false,
    this.excludePosts = const [],
    this.promotedPostBuilder,
    this.feedSession,
  });

  final String placement;
  final int revision;
  final bool guest;
  final List<int> excludePosts;
  final String? feedSession;
  final Widget Function(AirmiusPost)? promotedPostBuilder;

  @override
  State<AirmiusAdSlot> createState() => _AirmiusAdSlotState();
}

class _AirmiusAdSlotState extends State<AirmiusAdSlot>
    with WidgetsBindingObserver {
  AirmiusJson? _ad;
  AirmiusPost? _promotedPost;
  Timer? _refreshTimer;
  Timer? _expiryTimer;
  String? _identity;
  bool _initialized = false;
  bool _wasVisible = false;
  bool _foreground = true;
  final _visibilityKey = GlobalKey();
  DateTime? _visibleSince;
  bool _impressionSent = false;
  bool _opening = false;
  int _request = 0;

  AirmiusApiClient get _client {
    final services = AirmiusServicesScope.of(context);
    return services.clientForSession(
      widget.guest ? null : services.authState.session,
    );
  }

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _refreshTimer = Timer.periodic(
      const Duration(milliseconds: 250),
      (_) => _measureVisibility(),
    );
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    final identity = '${_client.token}:${_client.locale}:${widget.guest}';
    final visible =
        ModalRoute.of(context)?.isCurrent != false &&
        TickerMode.valuesOf(context).enabled;
    final becameVisible = visible && !_wasVisible;
    _wasVisible = visible;
    if (!_initialized || identity != _identity || becameVisible) {
      _initialized = true;
      _identity = identity;
      _load();
    }
  }

  @override
  void didUpdateWidget(AirmiusAdSlot oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.placement != widget.placement ||
        oldWidget.revision != widget.revision ||
        oldWidget.guest != widget.guest) {
      _load();
    }
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    _foreground = state == AppLifecycleState.resumed;
    if (_foreground) _load();
  }

  void _measureVisibility() {
    if (!mounted || _impressionSent || _ad?['impression_token'] == null) return;
    final box = _visibilityKey.currentContext?.findRenderObject();
    if (!_foreground ||
        ModalRoute.of(context)?.isCurrent == false ||
        box is! RenderBox ||
        !box.hasSize) {
      _visibleSince = null;
      return;
    }
    final rect = box.localToGlobal(Offset.zero) & box.size;
    final viewport = Offset.zero & MediaQuery.sizeOf(context);
    final intersection = rect.intersect(viewport);
    final visible =
        rect.width * rect.height > 0 &&
        intersection.width > 0 &&
        intersection.height > 0 &&
        intersection.width * intersection.height >=
            rect.width * rect.height * 0.5;
    if (!visible) {
      _visibleSince = null;
      return;
    }
    _visibleSince ??= DateTime.now();
    if (DateTime.now().difference(_visibleSince!).inMilliseconds >= 1000) {
      _impressionSent = true;
      _client.confirmAdImpression('${_ad!['impression_token']}').catchError((
        Object _,
      ) {
        return <String, dynamic>{};
      });
    }
  }

  Future<void> _load() async {
    if (_ad != null) return;
    if (!mounted ||
        !_foreground ||
        ModalRoute.of(context)?.isCurrent == false ||
        !TickerMode.valuesOf(context).enabled) {
      return;
    }
    final request = ++_request;
    _expiryTimer?.cancel();
    if (_ad != null) setState(() => _ad = null);
    try {
      final response = await _client.publicActiveAd(
        placement: widget.placement,
        excludePosts: widget.excludePosts,
        feedSession: widget.feedSession,
        device: kIsWeb
            ? null
            : switch (defaultTargetPlatform) {
                TargetPlatform.android || TargetPlatform.iOS =>
                  MediaQuery.sizeOf(context).shortestSide >= 600
                      ? 'tablet'
                      : 'mobile',
                _ => 'desktop',
              },
      );
      if (!mounted || request != _request) return;
      // An empty delivery response is not a campaign, even when API metadata is present.
      if (response['id'] == null || response['click_url'] == null) return;
      final postId = response['promoted_post_id'];
      _promotedPost = null;
      if (postId != null) {
        final postResponse = await _client.feedPost(int.parse('$postId'));
        if (!mounted || request != _request) return;
        _promotedPost = AirmiusPost.fromJson(
          Map<String, dynamic>.from(postResponse['data'] as Map),
        );
      }
      final rawExpiry = response['ends_at'];
      final expiry = rawExpiry == null ? null : DateTime.tryParse('$rawExpiry');
      if (rawExpiry != null &&
          (expiry == null || !expiry.isAfter(DateTime.now()))) {
        return;
      }
      setState(() => _ad = response);
      if (expiry != null) {
        _expiryTimer = Timer(expiry.difference(DateTime.now()), () {
          if (!mounted) return;
          setState(() => _ad = null);
          _load();
        });
      }
    } catch (_) {
      // A failed ad request must never block the feed or other page content.
      if (mounted && request == _request) setState(() => _ad = null);
    }
  }

  Future<void> _open() async {
    final ad = _ad;
    if (ad == null || _opening) return;
    _opening = true;
    try {
      var destination = '${ad['click_url']}';
      if (ad['impression_token'] != null &&
          AirmiusServicesScope.of(context).authState.session != null) {
        final result = await _client.clickDeliveredAd(
          '${ad['impression_token']}',
        );
        destination = '${result['target_url'] ?? ''}';
      }
      if (!mounted) return;
      final uri = safeExternalHttpUrl(destination, httpsOnly: false);
      if (uri != null &&
          await launchUrl(uri, mode: LaunchMode.externalApplication)) {
        return;
      }
    } catch (_) {
      // Show the same friendly failure for unsupported URLs and launcher errors.
    } finally {
      _opening = false;
    }
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(AirmiusScope.of(context).t('publicGrowth.adOpenFailed')),
      ),
    );
  }

  @override
  void dispose() {
    ++_request;
    _refreshTimer?.cancel();
    _expiryTimer?.cancel();
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final ad = _ad;
    if (ad == null) return const SizedBox.shrink();
    final t = AirmiusScope.of(context).t;
    if (_promotedPost != null && widget.promotedPostBuilder != null) {
      return Column(
        key: _visibilityKey,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Padding(
            padding: const EdgeInsets.all(12),
            child: Text(t('publicGrowth.sponsored')),
          ),
          widget.promotedPostBuilder!(_promotedPost!),
        ],
      );
    }
    final ratio = switch (ad['creative_format']) {
      'feed_square' => 1.0,
      'feed_portrait' => 4 / 5,
      'story_vertical' => 9 / 16,
      _ => 1200 / 628,
    };
    final headline = '${ad['headline'] ?? ad['name'] ?? ''}';
    final body = '${ad['primary_text'] ?? ad['description'] ?? ''}';
    final image = '${ad['image_url'] ?? ''}';
    return Padding(
      key: _visibilityKey,
      padding: const EdgeInsets.only(bottom: 14),
      child: Card(
        clipBehavior: Clip.antiAlias,
        child: InkWell(
          onTap: _open,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Padding(
                padding: const EdgeInsets.all(12),
                child: Text(
                  t('publicGrowth.sponsored'),
                  style: Theme.of(context).textTheme.labelSmall,
                ),
              ),
              if (image.isNotEmpty)
                AspectRatio(
                  aspectRatio: ratio,
                  child: Image.network(
                    image,
                    fit: BoxFit.contain,
                    errorBuilder: (_, _, _) =>
                        const Center(child: Icon(Icons.campaign_outlined)),
                  ),
                ),
              Padding(
                padding: const EdgeInsets.all(14),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      headline,
                      style: Theme.of(context).textTheme.titleMedium,
                    ),
                    if (body.isNotEmpty) ...[
                      const SizedBox(height: 8),
                      Text(body),
                    ],
                    const SizedBox(height: 12),
                    FilledButton(
                      onPressed: _open,
                      child: Text(
                        '${ad['cta_label'] ?? t('publicGrowth.sponsored')}',
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
