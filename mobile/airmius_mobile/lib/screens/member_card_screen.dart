import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:qr_flutter/qr_flutter.dart';

import '../core/airmius_api_client.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';
import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'member_card_qr_scanner_screen.dart';

class MemberCardScreen extends StatefulWidget {
  const MemberCardScreen({
    super.key,
    this.initialClubId,
    this.initialVerifyToken,
    this.startScanner = false,
  });

  final int? initialClubId;
  final String? initialVerifyToken;
  final bool startScanner;

  @override
  State<MemberCardScreen> createState() => _MemberCardScreenState();
}

class _MemberCardScreenState extends State<MemberCardScreen> {
  List<Map<String, dynamic>> _clubs = const [];
  Map<String, dynamic>? _selectedClub;
  Map<String, dynamic>? _card;
  final _verifyController = TextEditingController();
  final _eventController = TextEditingController();
  String? _error;
  String? _verifyMessage;
  bool _loading = true;
  bool _busy = false;
  bool _verifying = false;
  bool _initialVerifyConsumed = false;
  bool _initialScannerConsumed = false;

  AirmiusApiClient get _client {
    final services = AirmiusServicesScope.of(context);
    return services.clientForSession(services.authState.session);
  }

  String t(String key) => AirmiusScope.of(context).t(key);

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) => _loadClubs());
  }

  @override
  void dispose() {
    _verifyController.dispose();
    _eventController.dispose();
    super.dispose();
  }

  Future<void> _loadClubs() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final response = await _client.clubs(mine: true);
      final rows = _maps(response['data'])
          .where((club) => club['is_member'] == true || _canVerifyCards(club))
          .toList();
      if (!mounted) return;
      setState(() {
        _clubs = rows;
        _selectedClub = rows.cast<Map<String, dynamic>?>().firstWhere(
          (club) => club?['id'] == widget.initialClubId,
          orElse: () => rows.isEmpty ? null : rows.first,
        );
        _loading = false;
      });
      final verifyToken = widget.initialVerifyToken?.trim();
      if (verifyToken != null && verifyToken.isNotEmpty) {
        _verifyController.text = verifyToken;
      }
      if (!widget.startScanner && _selectedClub?['is_member'] == true) {
        await _loadCard();
      }
      if (!_initialVerifyConsumed &&
          verifyToken != null &&
          verifyToken.isNotEmpty &&
          _canVerifyCards(_selectedClub)) {
        _initialVerifyConsumed = true;
        await _verifyCard();
      }
      if (!_initialScannerConsumed &&
          widget.startScanner &&
          _canVerifyCards(_selectedClub)) {
        _initialScannerConsumed = true;
        await _scanCard();
      }
    } on AirmiusApiException catch (error) {
      if (mounted) {
        setState(() {
          _error = error.userMessage;
          _loading = false;
        });
      }
    } catch (_) {
      if (mounted) {
        setState(() {
          _error = t('memberCard.invalid');
          _loading = false;
        });
      }
    }
  }

  Future<void> _loadCard({bool rotate = false}) async {
    final clubId = _int(_selectedClub?['id']);
    if (clubId == null) return;
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      final response = rotate
          ? await _client.rotateClubMemberCard(clubId)
          : await _client.clubMemberCard(clubId);
      if (!mounted) return;
      setState(() {
        _card = _map(response['data']);
        _busy = false;
      });
    } on AirmiusApiException catch (error) {
      if (mounted) {
        setState(() {
          _error = error.userMessage;
          _busy = false;
        });
      }
    } catch (_) {
      if (mounted) {
        setState(() {
          _error = t('memberCard.invalid');
          _busy = false;
        });
      }
    }
  }

  Future<void> _verifyCard() async {
    final clubId = _int(_selectedClub?['id']);
    final token = _verifyController.text.trim();
    if (clubId == null || token.isEmpty || _verifying) return;
    setState(() {
      _verifying = true;
      _verifyMessage = null;
    });
    try {
      final response = await _client.verifyClubMemberCard(clubId, {
        'token': token,
        if (_eventController.text.trim().isNotEmpty)
          'event_id': int.tryParse(_eventController.text.trim()),
      });
      final data = _map(response['data']);
      if (!mounted) return;
      final event = _map(data['event']);
      setState(() {
        _verifyMessage = event.isNotEmpty
            ? '${t('memberCard.verified')} · ${t('memberCard.checkInSuccess')}'
            : t('memberCard.verified');
        _verifying = false;
        _verifyController.clear();
      });
    } on AirmiusApiException catch (error) {
      if (mounted) {
        setState(() {
          _verifyMessage = error.userMessage;
          _verifying = false;
        });
      }
    } catch (_) {
      if (mounted) {
        setState(() {
          _verifyMessage = t('memberCard.invalid');
          _verifying = false;
        });
      }
    }
  }

  Future<void> _editDesign() async {
    final clubId = _int(_selectedClub?['id']);
    final card = _card;
    if (clubId == null || card == null || _busy) return;
    final payload = await showDialog<Map<String, dynamic>>(
      context: context,
      builder: (_) => _MemberCardDesignDialog(card: card),
    );
    if (payload == null || !mounted) return;
    setState(() {
      _busy = true;
      _error = null;
    });
    try {
      final response = await _client.updateClubMemberCardDesign(
        clubId,
        payload,
      );
      if (!mounted) return;
      setState(() {
        _card = _map(response['data']);
        _busy = false;
      });
    } on AirmiusApiException catch (error) {
      if (mounted) {
        setState(() {
          _error = error.userMessage;
          _busy = false;
        });
      }
    } catch (_) {
      if (mounted) {
        setState(() {
          _error = t('memberCard.invalid');
          _busy = false;
        });
      }
    }
  }

  Future<void> _scanCard() async {
    final clubId = _int(_selectedClub?['id']);
    if (clubId == null || _verifying) return;
    final token = await Navigator.of(context).push<String>(
      MaterialPageRoute(
        builder: (_) => MemberCardQrScannerScreen(clubId: clubId),
      ),
    );
    if (!mounted || token == null || token.isEmpty) return;
    _verifyController.text = token;
    await _verifyCard();
  }

  @override
  Widget build(BuildContext context) {
    final scheme = Theme.of(context).colorScheme;
    return Scaffold(
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      appBar: AppBar(title: Text(t('memberCard.title'))),
      body: SafeArea(
        child: _loading
            ? Center(child: Text(t('memberCard.loading')))
            : RefreshIndicator(
                onRefresh: _loadClubs,
                child: ListView(
                  padding: const EdgeInsets.fromLTRB(16, 16, 16, 32),
                  children: [
                    AirmiusPanel(
                      gradient: true,
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: [
                          Eyebrow(t('memberCard.title').toUpperCase()),
                          const SizedBox(height: 8),
                          Text(
                            t('memberCard.subtitle'),
                            style: TextStyle(
                              color: airmiusTextColor(context),
                              fontSize: 22,
                              fontWeight: FontWeight.w900,
                              height: 1.12,
                            ),
                          ),
                          const SizedBox(height: 8),
                          Text(
                            t('memberCard.minimalData'),
                            style: TextStyle(
                              color: airmiusMutedColor(context),
                              height: 1.4,
                            ),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 14),
                    if (_clubs.isEmpty)
                      AirmiusPanel(
                        child: Text(
                          t('memberCard.noClubs'),
                          style: TextStyle(color: airmiusTextColor(context)),
                        ),
                      )
                    else ...[
                      _clubSelector(scheme),
                      const SizedBox(height: 14),
                      if (_card != null) _cardPanel(scheme),
                      if (_error != null) ...[
                        const SizedBox(height: 12),
                        _messagePanel(_error!, scheme.error),
                      ],
                      if (_canVerifyCards(_selectedClub)) ...[
                        const SizedBox(height: 14),
                        _verifyPanel(scheme),
                      ],
                    ],
                  ],
                ),
              ),
      ),
    );
  }

  Widget _clubSelector(ColorScheme scheme) => AirmiusPanel(
    title: t('memberCard.selectClub'),
    child: DropdownButtonFormField<int>(
      initialValue: _int(_selectedClub?['id']),
      isExpanded: true,
      decoration: const InputDecoration(),
      items: _clubs
          .map(
            (club) => DropdownMenuItem<int>(
              value: _int(club['id']),
              child: Text('${club['name'] ?? ''}'),
            ),
          )
          .toList(),
      onChanged: _busy
          ? null
          : (value) async {
              final club = _clubs.firstWhere(
                (item) => _int(item['id']) == value,
                orElse: () => _clubs.first,
              );
              setState(() {
                _selectedClub = club;
                _card = null;
                _verifyMessage = null;
              });
              if (club['is_member'] == true) await _loadCard();
            },
    ),
  );

  bool _canVerifyCards(Map<String, dynamic>? club) =>
      club?['can_verify_member_cards'] == true ||
      club?['can_manage_members'] == true;

  Widget _cardPanel(ColorScheme scheme) {
    final club = _map(_card?['club']);
    final member = _map(_card?['member']);
    final token = _map(_card?['token']);
    final design = _map(_card?['design']);
    final value = '${token['value'] ?? ''}';
    final expires = '${token['expires_at'] ?? ''}';
    final memberNumber = '${member['member_number'] ?? ''}'.trim();
    final qrData = value;
    final accent = _color(design['accent_color'], scheme.primary);
    final background = _color(
      design['background_color'],
      const Color(0xFF17253A),
    );
    final textColor = _color(design['text_color'], Colors.white);
    final showProfilePhoto = design['show_profile_photo'] != false;
    final showMemberNumber = design['show_member_number'] != false;
    final style = '${design['style'] ?? 'classic'}';
    final logoUrl = '${club['logo_url'] ?? ''}'.trim();
    return AirmiusPanel(
      padding: EdgeInsets.zero,
      borderColor: accent.withValues(alpha: .55),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Container(
            padding: const EdgeInsets.all(18),
            decoration: BoxDecoration(
              color: background,
              borderRadius: BorderRadius.circular(24),
              border: Border.all(color: accent.withValues(alpha: .45)),
              gradient: style == 'sport'
                  ? LinearGradient(
                      colors: [
                        background,
                        Color.alphaBlend(
                          accent.withValues(alpha: .35),
                          background,
                        ),
                      ],
                      begin: Alignment.topLeft,
                      end: Alignment.bottomRight,
                    )
                  : null,
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    if (showProfilePhoto) ...[
                      _clubLogo(logoUrl, accent),
                      const SizedBox(width: 12),
                    ],
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            '${member['name'] ?? ''}',
                            style: TextStyle(
                              color: textColor,
                              fontSize: 21,
                              fontWeight: FontWeight.w900,
                              height: 1.08,
                            ),
                          ),
                          const SizedBox(height: 4),
                          Text(
                            '${club['name'] ?? ''} · ${member['role'] ?? ''}',
                            style: TextStyle(
                              color: textColor.withValues(alpha: .75),
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                          if (memberNumber.isNotEmpty && showMemberNumber) ...[
                            const SizedBox(height: 8),
                            _cardBadge(
                              '${t('memberCard.memberNumber')}: $memberNumber',
                              accent,
                              textColor,
                            ),
                          ],
                        ],
                      ),
                    ),
                    _cardBadge(
                      '${member['membership_status'] ?? ''}',
                      accent,
                      textColor,
                    ),
                  ],
                ),
                const SizedBox(height: 18),
                if (style != 'minimal') ...[
                  Text(
                    t('memberCard.code'),
                    style: TextStyle(
                      color: textColor.withValues(alpha: .72),
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  const SizedBox(height: 6),
                  SelectableText(
                    value,
                    textAlign: TextAlign.center,
                    style: TextStyle(
                      color: textColor,
                      fontSize: 18,
                      fontWeight: FontWeight.w900,
                      letterSpacing: 1,
                    ),
                  ),
                  const SizedBox(height: 6),
                  Text(
                    t('memberCard.expires').replaceFirst('{date}', expires),
                    textAlign: TextAlign.center,
                    style: TextStyle(
                      color: textColor.withValues(alpha: .72),
                      fontSize: 12,
                    ),
                  ),
                ],
                if (qrData.isNotEmpty) ...[
                  const SizedBox(height: 16),
                  Center(
                    child: Semantics(
                      label: t('memberCard.qrCode'),
                      child: Container(
                        key: const ValueKey('memberCardQr'),
                        padding: const EdgeInsets.all(10),
                        decoration: BoxDecoration(
                          color: Colors.white,
                          borderRadius: BorderRadius.circular(18),
                        ),
                        child: QrImageView(
                          data: qrData,
                          size: style == 'minimal' ? 168 : 188,
                          backgroundColor: Colors.white,
                          semanticsLabel: t('memberCard.qrCode'),
                        ),
                      ),
                    ),
                  ),
                  const SizedBox(height: 8),
                  Text(
                    t('memberCard.qrHint'),
                    textAlign: TextAlign.center,
                    style: TextStyle(
                      color: textColor.withValues(alpha: .72),
                      height: 1.35,
                    ),
                  ),
                ],
              ],
            ),
          ),
          Padding(
            padding: const EdgeInsets.all(16),
            child: Wrap(
              spacing: 10,
              runSpacing: 10,
              children: [
                AirmiusButton(
                  label: t('memberCard.design'),
                  icon: Icons.palette_outlined,
                  secondary: true,
                  onPressed: _busy ? null : _editDesign,
                ),
                AirmiusButton(
                  label: t('memberCard.copy'),
                  icon: Icons.copy_outlined,
                  secondary: true,
                  onPressed: value.isEmpty
                      ? null
                      : () async {
                          await Clipboard.setData(ClipboardData(text: value));
                          if (mounted) {
                            ScaffoldMessenger.of(context).showSnackBar(
                              SnackBar(content: Text(t('memberCard.copied'))),
                            );
                          }
                        },
                ),
                AirmiusButton(
                  label: _busy
                      ? t('memberCard.loading')
                      : t('memberCard.rotate'),
                  icon: Icons.refresh_outlined,
                  onPressed: _busy ? null : () => _loadCard(rotate: true),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _clubLogo(String logoUrl, Color accent) {
    if (logoUrl.isNotEmpty) {
      return ClipRRect(
        borderRadius: BorderRadius.circular(18),
        child: Image.network(
          logoUrl,
          width: 54,
          height: 54,
          fit: BoxFit.cover,
          errorBuilder: (_, _, _) => _logoFallback(accent),
        ),
      );
    }

    return _logoFallback(accent);
  }

  Widget _logoFallback(Color accent) => Container(
    width: 54,
    height: 54,
    decoration: BoxDecoration(
      color: accent.withValues(alpha: .22),
      borderRadius: BorderRadius.circular(18),
      border: Border.all(color: accent.withValues(alpha: .45)),
    ),
    child: Icon(Icons.shield_outlined, color: accent),
  );

  Widget _cardBadge(String label, Color accent, Color textColor) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
    decoration: BoxDecoration(
      color: accent.withValues(alpha: .2),
      borderRadius: BorderRadius.circular(999),
      border: Border.all(color: accent.withValues(alpha: .45)),
    ),
    child: Text(
      label,
      style: TextStyle(
        color: textColor,
        fontSize: 12,
        fontWeight: FontWeight.w900,
      ),
    ),
  );

  Widget _verifyPanel(ColorScheme scheme) => AirmiusPanel(
    borderColor: scheme.secondary.withValues(alpha: .55),
    title: t('memberCard.verifyTitle'),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Text(
          t('memberCard.managerOnly'),
          style: TextStyle(color: airmiusMutedColor(context), height: 1.35),
        ),
        const SizedBox(height: 12),
        TextField(
          controller: _verifyController,
          autocorrect: false,
          enableSuggestions: false,
          decoration: InputDecoration(
            labelText: t('memberCard.verifyHint'),
            prefixIcon: const Icon(Icons.qr_code_scanner_outlined),
          ),
        ),
        const SizedBox(height: 10),
        TextField(
          controller: _eventController,
          keyboardType: TextInputType.number,
          decoration: InputDecoration(
            labelText: t('memberCard.eventOptional'),
            prefixIcon: const Icon(Icons.event_available_outlined),
          ),
        ),
        const SizedBox(height: 12),
        AirmiusButton(
          label: t('memberCard.scan'),
          icon: Icons.qr_code_scanner_outlined,
          secondary: true,
          onPressed: _verifying ? null : _scanCard,
        ),
        const SizedBox(height: 10),
        AirmiusButton(
          label: _verifying ? t('memberCard.loading') : t('memberCard.verify'),
          icon: Icons.verified_outlined,
          onPressed: _verifying ? null : _verifyCard,
        ),
        if (_verifyMessage != null) ...[
          const SizedBox(height: 10),
          Text(
            _verifyMessage!,
            style: TextStyle(
              color: scheme.tertiary,
              fontWeight: FontWeight.w800,
            ),
          ),
        ],
      ],
    ),
  );

  Widget _messagePanel(String message, Color color) => AirmiusPanel(
    borderColor: color.withValues(alpha: .55),
    child: Text(message, style: TextStyle(color: color)),
  );

  static Map<String, dynamic> _map(Object? value) =>
      value is Map ? Map<String, dynamic>.from(value) : <String, dynamic>{};

  static List<Map<String, dynamic>> _maps(Object? value) =>
      value is List ? value.whereType<Map>().map(_map).toList() : const [];

  static int? _int(Object? value) =>
      value is num ? value.toInt() : int.tryParse('$value');

  static Color _color(Object? value, Color fallback) {
    final text = '$value';
    if (!RegExp(r'^#[0-9A-Fa-f]{6}$').hasMatch(text)) return fallback;
    return Color(int.parse('FF${text.substring(1)}', radix: 16));
  }
}

class _MemberCardDesignDialog extends StatefulWidget {
  const _MemberCardDesignDialog({required this.card});

  final Map<String, dynamic> card;

  @override
  State<_MemberCardDesignDialog> createState() =>
      _MemberCardDesignDialogState();
}

class _MemberCardDesignDialogState extends State<_MemberCardDesignDialog> {
  static const _accentOptions = ['#60A5FA', '#34D399', '#F59E0B', '#F87171'];
  static const _backgroundOptions = [
    '#17253A',
    '#111827',
    '#064E3B',
    '#3B1D52',
  ];
  static const _textOptions = ['#FFFFFF', '#F8FAFC', '#111827'];

  late String _accentColor;
  late String _backgroundColor;
  late String _textColor;
  late String _style;
  late bool _showProfilePhoto;
  late bool _showMemberNumber;

  String t(String key) => AirmiusScope.of(context).t(key);

  @override
  void initState() {
    super.initState();
    final design = _map(widget.card['design']);
    _accentColor = _hex(design['accent_color'], _accentOptions.first);
    _backgroundColor = _hex(
      design['background_color'],
      _backgroundOptions.first,
    );
    _textColor = _hex(design['text_color'], _textOptions.first);
    _style = ['classic', 'sport', 'minimal'].contains(design['style'])
        ? '${design['style']}'
        : 'classic';
    _showProfilePhoto = design['show_profile_photo'] != false;
    _showMemberNumber = design['show_member_number'] != false;
  }

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      title: Text(t('memberCard.designTitle')),
      content: SingleChildScrollView(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            _preview(),
            const SizedBox(height: 16),
            _stylePicker(),
            const SizedBox(height: 14),
            _colorPicker(
              t('memberCard.accentColor'),
              _accentOptions,
              _accentColor,
              (value) {
                setState(() => _accentColor = value);
              },
            ),
            const SizedBox(height: 14),
            _colorPicker(
              t('memberCard.backgroundColor'),
              _backgroundOptions,
              _backgroundColor,
              (value) => setState(() => _backgroundColor = value),
            ),
            const SizedBox(height: 14),
            _colorPicker(
              t('memberCard.textColor'),
              _textOptions,
              _textColor,
              (value) => setState(() => _textColor = value),
            ),
            const SizedBox(height: 10),
            SwitchListTile(
              contentPadding: EdgeInsets.zero,
              value: _showProfilePhoto,
              onChanged: (value) => setState(() => _showProfilePhoto = value),
              title: Text(t('memberCard.showProfilePhoto')),
            ),
            SwitchListTile(
              contentPadding: EdgeInsets.zero,
              value: _showMemberNumber,
              onChanged: (value) => setState(() => _showMemberNumber = value),
              title: Text(t('memberCard.showMemberNumber')),
            ),
          ],
        ),
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.of(context).pop(),
          child: Text(t('common.cancel')),
        ),
        FilledButton(
          onPressed: () => Navigator.of(context).pop({
            'accent_color': _accentColor,
            'background_color': _backgroundColor,
            'text_color': _textColor,
            'style': _style,
            'show_profile_photo': _showProfilePhoto,
            'show_member_number': _showMemberNumber,
          }),
          child: Text(t('common.save')),
        ),
      ],
    );
  }

  Widget _preview() {
    final club = _map(widget.card['club']);
    final member = _map(widget.card['member']);
    final accent = _color(_accentColor, Colors.blue);
    final background = _color(_backgroundColor, const Color(0xFF17253A));
    final textColor = _color(_textColor, Colors.white);
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: background,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: accent.withValues(alpha: .55)),
      ),
      child: Row(
        children: [
          if (_showProfilePhoto) ...[
            CircleAvatar(
              backgroundColor: accent.withValues(alpha: .22),
              child: Icon(Icons.shield_outlined, color: accent),
            ),
            const SizedBox(width: 10),
          ],
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  '${member['name'] ?? ''}',
                  style: TextStyle(
                    color: textColor,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                Text(
                  '${club['name'] ?? ''}',
                  style: TextStyle(color: textColor.withValues(alpha: .72)),
                ),
              ],
            ),
          ),
          Icon(Icons.qr_code_2_outlined, color: accent),
        ],
      ),
    );
  }

  Widget _stylePicker() => DropdownButtonFormField<String>(
    initialValue: _style,
    decoration: InputDecoration(labelText: t('memberCard.cardStyle')),
    items: ['classic', 'sport', 'minimal']
        .map(
          (style) => DropdownMenuItem(
            value: style,
            child: Text(t('memberCard.style.$style')),
          ),
        )
        .toList(),
    onChanged: (value) {
      if (value != null) setState(() => _style = value);
    },
  );

  Widget _colorPicker(
    String label,
    List<String> options,
    String selected,
    ValueChanged<String> onSelected,
  ) => Column(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      Text(label, style: const TextStyle(fontWeight: FontWeight.w800)),
      const SizedBox(height: 8),
      Wrap(
        spacing: 10,
        runSpacing: 10,
        children: [
          for (final option in options)
            InkWell(
              borderRadius: BorderRadius.circular(999),
              onTap: () => onSelected(option),
              child: Container(
                width: 38,
                height: 38,
                decoration: BoxDecoration(
                  color: _color(option, Colors.blue),
                  shape: BoxShape.circle,
                  border: Border.all(
                    color: selected == option
                        ? Theme.of(context).colorScheme.primary
                        : Colors.white.withValues(alpha: .4),
                    width: selected == option ? 3 : 1,
                  ),
                ),
                child: selected == option
                    ? const Icon(Icons.check, color: Colors.white, size: 18)
                    : null,
              ),
            ),
        ],
      ),
    ],
  );

  static Map<String, dynamic> _map(Object? value) =>
      value is Map ? Map<String, dynamic>.from(value) : <String, dynamic>{};

  static String _hex(Object? value, String fallback) {
    final text = '$value'.toUpperCase();
    return RegExp(r'^#[0-9A-F]{6}$').hasMatch(text) ? text : fallback;
  }

  static Color _color(String value, Color fallback) {
    if (!RegExp(r'^#[0-9A-Fa-f]{6}$').hasMatch(value)) return fallback;
    return Color(int.parse('FF${value.substring(1)}', radix: 16));
  }
}
