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
    final value = '${token['value'] ?? ''}';
    final expires = '${token['expires_at'] ?? ''}';
    final memberNumber = '${member['member_number'] ?? ''}'.trim();
    final qrData = value;
    return AirmiusPanel(
      borderColor: scheme.primary.withValues(alpha: .55),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              CircleAvatar(
                backgroundColor: scheme.primary.withValues(alpha: .16),
                child: Icon(Icons.badge_outlined, color: scheme.primary),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      '${member['name'] ?? ''}',
                      style: TextStyle(
                        color: airmiusTextColor(context),
                        fontSize: 20,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    Text(
                      '${club['name'] ?? ''} · ${member['role'] ?? ''}',
                      style: TextStyle(color: airmiusMutedColor(context)),
                    ),
                    if (memberNumber.isNotEmpty) ...[
                      const SizedBox(height: 4),
                      Text(
                        '${t('memberCard.memberNumber')}: $memberNumber',
                        style: TextStyle(
                          color: airmiusTextColor(context),
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                    ],
                  ],
                ),
              ),
              StatusPill(
                '${member['membership_status'] ?? ''}',
                color: scheme.tertiary,
              ),
            ],
          ),
          const SizedBox(height: 18),
          Text(
            t('memberCard.code'),
            style: TextStyle(
              color: airmiusMutedColor(context),
              fontWeight: FontWeight.w800,
            ),
          ),
          const SizedBox(height: 6),
          SelectableText(
            value,
            textAlign: TextAlign.center,
            style: TextStyle(
              color: airmiusTextColor(context),
              fontSize: 22,
              fontWeight: FontWeight.w900,
              letterSpacing: 1.2,
            ),
          ),
          const SizedBox(height: 6),
          Text(
            t('memberCard.expires').replaceFirst('{date}', expires),
            textAlign: TextAlign.center,
            style: TextStyle(color: airmiusMutedColor(context), fontSize: 12),
          ),
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
                    size: 188,
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
              style: TextStyle(color: airmiusMutedColor(context), height: 1.35),
            ),
          ],
          const SizedBox(height: 14),
          Wrap(
            spacing: 10,
            runSpacing: 10,
            children: [
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
                label: _busy ? t('memberCard.loading') : t('memberCard.rotate'),
                icon: Icons.refresh_outlined,
                onPressed: _busy ? null : () => _loadCard(rotate: true),
              ),
            ],
          ),
        ],
      ),
    );
  }

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
}
