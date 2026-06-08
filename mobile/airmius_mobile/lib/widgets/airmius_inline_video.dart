import 'package:flutter/material.dart';
import 'package:video_player/video_player.dart';

import '../core/airmius_theme.dart';
import 'airmius_widgets.dart';

class AirmiusInlineVideo extends StatefulWidget {
  const AirmiusInlineVideo({
    super.key,
    required this.url,
    this.thumbnailUrl,
    this.height,
    this.borderRadius = 18,
    this.title,
  });

  final String url;
  final String? thumbnailUrl;
  final double? height;
  final double borderRadius;
  final String? title;

  @override
  State<AirmiusInlineVideo> createState() => _AirmiusInlineVideoState();
}

class _AirmiusInlineVideoState extends State<AirmiusInlineVideo> {
  late final VideoPlayerController _controller;
  bool _ready = false;
  bool _failed = false;

  @override
  void initState() {
    super.initState();
    _controller = VideoPlayerController.networkUrl(Uri.parse(widget.url));
    _controller.initialize().then((_) {
      if (!mounted) return;
      setState(() => _ready = true);
    }).catchError((_) {
      if (!mounted) return;
      setState(() => _failed = true);
    });
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final content = _ready && !_failed
        ? AspectRatio(
            aspectRatio: _controller.value.aspectRatio == 0 ? 16 / 9 : _controller.value.aspectRatio,
            child: VideoPlayer(_controller),
          )
        : _VideoPoster(thumbnailUrl: widget.thumbnailUrl, title: widget.title, failed: _failed);

    return ClipRRect(
      borderRadius: BorderRadius.circular(widget.borderRadius),
      child: Container(
        height: widget.height ?? 320,
        color: Colors.black,
        child: Stack(
          fit: StackFit.expand,
          children: [
            if (_ready && !_failed)
              Center(child: content)
            else
              Positioned.fill(child: content),
            if (_ready && !_failed)
              Positioned.fill(
                child: Material(
                  color: Colors.transparent,
                  child: InkWell(
                    onTap: () {
                      setState(() {
                        _controller.value.isPlaying ? _controller.pause() : _controller.play();
                      });
                    },
                    child: Center(
                      child: AnimatedOpacity(
                        opacity: _controller.value.isPlaying ? 0 : 1,
                        duration: const Duration(milliseconds: 160),
                        child: const Icon(Icons.play_circle_fill, color: Colors.white, size: 62),
                      ),
                    ),
                  ),
                ),
              ),
            if (_ready && !_failed)
              Positioned(
                left: 10,
                right: 10,
                bottom: 10,
                child: Row(
                  children: [
                    IconButton.filled(
                      onPressed: () {
                        setState(() {
                          _controller.value.isPlaying ? _controller.pause() : _controller.play();
                        });
                      },
                      icon: Icon(_controller.value.isPlaying ? Icons.pause : Icons.play_arrow),
                      iconSize: 18,
                      style: IconButton.styleFrom(backgroundColor: Colors.black.withValues(alpha: 0.58), foregroundColor: Colors.white),
                    ),
                    const SizedBox(width: 8),
                    Expanded(
                      child: VideoProgressIndicator(
                        _controller,
                        allowScrubbing: true,
                        colors: const VideoProgressColors(
                          playedColor: AirmiusColors.blue,
                          bufferedColor: Colors.white38,
                          backgroundColor: Colors.white24,
                        ),
                      ),
                    ),
                  ],
                ),
              ),
          ],
        ),
      ),
    );
  }
}

class _VideoPoster extends StatelessWidget {
  const _VideoPoster({required this.thumbnailUrl, required this.title, required this.failed});

  final String? thumbnailUrl;
  final String? title;
  final bool failed;

  @override
  Widget build(BuildContext context) {
    return Stack(
      fit: StackFit.expand,
      children: [
        if (thumbnailUrl != null)
          AirmiusMediaImage(url: thumbnailUrl!, borderRadius: 0)
        else
          Container(
            decoration: BoxDecoration(
              gradient: LinearGradient(
                begin: Alignment.topLeft,
                end: Alignment.bottomRight,
                colors: [
                  AirmiusColors.blue.withValues(alpha: 0.36),
                  AirmiusColors.cardSoft,
                  AirmiusColors.pink.withValues(alpha: 0.24),
                ],
              ),
            ),
          ),
        Container(color: Colors.black.withValues(alpha: 0.22)),
        Center(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(failed ? Icons.error_outline : Icons.play_circle_fill, color: Colors.white, size: 58),
              if (title != null) ...[
                const SizedBox(height: 8),
                Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 16),
                  child: Text(title!, maxLines: 2, overflow: TextOverflow.ellipsis, textAlign: TextAlign.center, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w900)),
                ),
              ],
            ],
          ),
        ),
      ],
    );
  }
}
