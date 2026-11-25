import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import '../widgets/airmius_form.dart';

import '../core/airmius_api_client.dart';
import '../core/airmius_api_models.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';
import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import '../models/club_summary.dart';
import 'legal_status_center_screen.dart';
import 'privacy_consent_center_screen.dart';

class SupportHelpdeskScreen extends StatefulWidget {
  const SupportHelpdeskScreen({super.key});

  @override
  State<SupportHelpdeskScreen> createState() => _SupportHelpdeskScreenState();
}

class _SupportHelpdeskScreenState extends State<SupportHelpdeskScreen>
    with _TicketActions {
  final _form = GlobalKey<FormState>();
  final _name = TextEditingController();
  final _email = TextEditingController();
  final _subject = TextEditingController();
  final _message = TextEditingController();
  String _category = 'technical';
  String _priority = 'normal';
  bool _includeDevice = true;
  bool _privacyAccepted = false;
  bool _sending = false;
  bool _sent = false;
  bool _ticketsLoading = false;
  bool _ticketsLoaded = false;
  bool _showHidden = false;

  String? _ticketsError;
  List<AirmiusSupportTicket> _tickets = const [];
  List<ClubSummary> _clubs = const [];
  int? _selectedClubId;

  @override
  void dispose() {
    _name.dispose();
    _email.dispose();
    _subject.dispose();
    _message.dispose();
    super.dispose();
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    final user = AirmiusServicesScope.of(context).authState.user;
    if (user != null && !_ticketsLoaded && !_ticketsLoading) _loadTickets();
  }

  Future<void> _loadTickets() async {
    setState(() {
      _ticketsLoading = true;
      _ticketsError = null;
    });
    try {
      final services = AirmiusServicesScope.of(context);
      final ticketsFuture = services
          .clientForSession(services.authState.session)
          .supportTickets(hidden: _showHidden);
      final clubsFuture = services.repositories.clubs.searchClubs(mine: true);
      final json = await ticketsFuture;
      final clubPage = await clubsFuture;
      final data = json['data'];
      final tickets = data is List
          ? data
                .whereType<JsonMap>()
                .map(AirmiusSupportTicket.fromJson)
                .toList()
          : const <AirmiusSupportTicket>[];
      if (mounted) {
        setState(() {
          _tickets = tickets;
          _clubs = clubPage.items.map(ClubSummary.fromAirmiusClub).toList();
          _ticketsLoaded = true;
          _ticketsLoading = false;
        });
      }
    } on AirmiusApiException catch (error) {
      if (mounted) {
        setState(() {
          _ticketsError = error.userMessage;
          _ticketsLoaded = true;
          _ticketsLoading = false;
        });
      }
    } catch (_) {
      if (mounted) {
        setState(() {
          _ticketsError = AirmiusScope.of(
            context,
          ).t('support.ticketsLoadError');
          _ticketsLoaded = true;
          _ticketsLoading = false;
        });
      }
    }
  }

  Future<void> _submit() async {
    if (_sending || _form.currentState?.validate() != true) return;
    final services = AirmiusServicesScope.of(context);
    final user = services.authState.user;
    if (user == null && !_privacyAccepted) return;
    setState(() => _sending = true);
    try {
      final payload = {
        'name': user?.name ?? _name.text.trim(),
        'email': user?.email ?? _email.text.trim(),
        'subject': _subject.text.trim(),
        'message': _message.text.trim(),
        'category': _category,
        'priority': _priority,
        if (_includeDevice) 'platform': _platformName(),
        if (user == null) 'privacy_consent': true,
      };
      final client = services.clientForSession(services.authState.session);
      if (user == null) {
        await client.sendPublicContact(payload);
      } else {
        final ticket = await client.createSupportTicket({
          'subject': payload['subject'],
          'message': payload['message'],
          'category': payload['category'],
          'priority': payload['priority'],
          'recipient': _selectedClubId == null ? 'airmius' : 'club',
          if (_selectedClubId != null) 'club_id': _selectedClubId,
        });
        final data = ticket['data'];
        if (data is JsonMap) {
          _tickets = [AirmiusSupportTicket.fromJson(data), ..._tickets];
        }
      }
      if (!mounted) return;
      _name.clear();
      _email.clear();
      _subject.clear();
      _message.clear();
      setState(() => _sent = true);
    } on AirmiusApiException catch (error) {
      if (mounted) _showError(error.userMessage);
    } catch (_) {
      if (mounted) {
        _showError(AirmiusScope.of(context).t('support.sendFailed'));
      }
    } finally {
      if (mounted) setState(() => _sending = false);
    }
  }

  Future<void> _openTicket(AirmiusSupportTicket ticket) async {
    await Navigator.of(context).push(
      MaterialPageRoute<void>(
        builder: (_) => SupportTicketDetailScreen(ticketId: ticket.id),
      ),
    );
    if (mounted) await _loadTickets();
  }

  void _showError(String message) {
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(SnackBar(content: Text(message)));
  }

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final theme = Theme.of(context);
    final isGuest = AirmiusServicesScope.of(context).authState.user == null;
    return Scaffold(
      appBar: AppBar(
        title: Text(
          t('support.title'),
          style: const TextStyle(fontWeight: FontWeight.w900),
        ),
      ),
      body: PageFrame(
        title: t('support.title'),
        subtitle: t('support.subtitle'),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            AirmiusPanel(
              gradient: true,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Eyebrow(t('support.eyebrow')),
                  const SizedBox(height: 7),
                  Text(
                    t('support.headline'),
                    style: theme.textTheme.headlineSmall?.copyWith(
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                  const SizedBox(height: 7),
                  Text(t('support.body')),
                ],
              ),
            ),
            const SizedBox(height: 14),
            if (_sent) ...[
              AirmiusPanel(
                borderColor: theme.colorScheme.secondary,
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Icon(
                      Icons.mark_email_read_outlined,
                      color: theme.colorScheme.secondary,
                      size: 30,
                    ),
                    const SizedBox(width: 11),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            t('support.sent'),
                            style: theme.textTheme.titleMedium?.copyWith(
                              fontWeight: FontWeight.w900,
                            ),
                          ),
                          const SizedBox(height: 4),
                          Text(
                            t(
                              isGuest
                                  ? 'support.guestSentBody'
                                  : 'support.sentBody',
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 14),
            ],
            if (!isGuest) ...[
              _SupportTicketList(
                tickets: _tickets,
                loading: _ticketsLoading,
                error: _ticketsError,
                onRetry: _loadTickets,
                onOpen: _openTicket,
                hiddenView: _showHidden,
                busy: _lifecycleBusy,
                onHiddenChanged: (hidden) {
                  setState(() => _showHidden = hidden);
                  _loadTickets();
                },
                onAction: (ticket, action) async {
                  if (await _ticketLifecycle(ticket.id, action) && mounted) {
                    await _loadTickets();
                  }
                },
              ),
              const SizedBox(height: 14),
            ],
            AirmiusForm(
              key: _form,
              child: AirmiusPanel(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Text(
                      t('support.formTitle'),
                      style: theme.textTheme.titleLarge?.copyWith(
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(height: 13),
                    if (isGuest) ...[
                      TextFormField(
                        controller: _name,
                        textInputAction: TextInputAction.next,
                        decoration: InputDecoration(
                          labelText: t('support.name'),
                        ),
                        validator: (value) =>
                            value == null || value.trim().isEmpty
                            ? t('support.required')
                            : null,
                      ),
                      const SizedBox(height: 12),
                      TextFormField(
                        controller: _email,
                        keyboardType: TextInputType.emailAddress,
                        textInputAction: TextInputAction.next,
                        decoration: InputDecoration(
                          labelText: t('support.email'),
                        ),
                        validator: (value) {
                          final email = value?.trim() ?? '';
                          if (email.isEmpty) return t('support.required');
                          if (!RegExp(
                            r'^[^@\s]+@[^@\s]+\.[^@\s]+$',
                          ).hasMatch(email)) {
                            return t('support.invalidEmail');
                          }
                          return null;
                        },
                      ),
                      const SizedBox(height: 12),
                    ],
                    if (!isGuest) ...[
                      DropdownButtonFormField<int?>(
                        key: ValueKey(_selectedClubId),
                        initialValue: _selectedClubId,
                        isExpanded: true,
                        decoration: InputDecoration(
                          labelText: t('support.recipient'),
                        ),
                        items: [
                          DropdownMenuItem<int?>(
                            value: null,
                            child: Text(t('support.airmiusSupport')),
                          ),
                          ..._clubs.map(
                            (club) => DropdownMenuItem<int?>(
                              value: club.id,
                              child: Text(
                                "${t('support.contactClub')}: ${club.name}",
                              ),
                            ),
                          ),
                        ],
                        onChanged: (value) =>
                            setState(() => _selectedClubId = value),
                      ),
                      const SizedBox(height: 12),
                    ],
                    DropdownButtonFormField<String>(
                      initialValue: _category,
                      isExpanded: true,
                      decoration: InputDecoration(
                        labelText: t('support.category'),
                      ),
                      items:
                          const [
                                'technical',
                                'club',
                                'membership',
                                'payment',
                                'privacy',
                                'safety',
                              ]
                              .map(
                                (value) => DropdownMenuItem(
                                  value: value,
                                  child: Text(t('support.category.$value')),
                                ),
                              )
                              .toList(),
                      onChanged: (value) => setState(() => _category = value!),
                    ),
                    const SizedBox(height: 12),
                    if (_category == 'safety') ...[
                      Text(
                        t('support.safetyHint'),
                        style: TextStyle(color: theme.colorScheme.secondary),
                      ),
                      const SizedBox(height: 12),
                    ],
                    DropdownButtonFormField<String>(
                      initialValue: _priority,
                      isExpanded: true,
                      decoration: InputDecoration(
                        labelText: t('support.priority'),
                      ),
                      items: const ['low', 'normal', 'high', 'urgent']
                          .map(
                            (value) => DropdownMenuItem(
                              value: value,
                              child: Text(t('support.priority.$value')),
                            ),
                          )
                          .toList(),
                      onChanged: (value) => setState(() => _priority = value!),
                    ),
                    const SizedBox(height: 12),
                    TextFormField(
                      controller: _subject,
                      maxLength: 160,
                      decoration: InputDecoration(
                        labelText: t('support.subject'),
                      ),
                      validator: (value) =>
                          value == null || value.trim().isEmpty
                          ? t('support.required')
                          : null,
                    ),
                    const SizedBox(height: 4),
                    TextFormField(
                      controller: _message,
                      minLines: 5,
                      maxLines: 10,
                      maxLength: 2000,
                      decoration: InputDecoration(
                        labelText: t('support.message'),
                        alignLabelWithHint: true,
                      ),
                      validator: (value) =>
                          value == null || value.trim().length < 10
                          ? t('support.messageTooShort')
                          : null,
                    ),
                    Material(
                      type: MaterialType.transparency,
                      child: SwitchListTile.adaptive(
                        contentPadding: EdgeInsets.zero,
                        value: _includeDevice,
                        title: Text(t('support.includeDevice')),
                        subtitle: Text(t('support.includeDeviceBody')),
                        onChanged: (value) =>
                            setState(() => _includeDevice = value),
                      ),
                    ),
                    if (isGuest)
                      Material(
                        type: MaterialType.transparency,
                        child: SwitchListTile.adaptive(
                          contentPadding: EdgeInsets.zero,
                          value: _privacyAccepted,
                          title: Text(t('support.guestPrivacy')),
                          onChanged: (value) =>
                              setState(() => _privacyAccepted = value),
                        ),
                      ),
                    const SizedBox(height: 10),
                    AirmiusButton(
                      label: _sending
                          ? t('support.sending')
                          : t('support.send'),
                      icon: Icons.send_outlined,
                      onPressed: _sending || (isGuest && !_privacyAccepted)
                          ? null
                          : _submit,
                    ),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 14),
            AirmiusPanel(
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Icon(
                    Icons.privacy_tip_outlined,
                    color: theme.colorScheme.primary,
                    size: 28,
                  ),
                  const SizedBox(width: 11),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          t('support.privacyTitle'),
                          style: theme.textTheme.titleMedium?.copyWith(
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                        const SizedBox(height: 4),
                        Text(t('support.privacyBody')),
                        const SizedBox(height: 8),
                        TextButton.icon(
                          onPressed: () => Navigator.push(
                            context,
                            MaterialPageRoute(
                              builder: (_) => isGuest
                                  ? const LegalStatusCenterScreen()
                                  : const PrivacyConsentCenterScreen(),
                            ),
                          ),
                          icon: const Icon(Icons.manage_search_outlined),
                          label: Text(t('support.dataRights')),
                        ),
                      ],
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

class _SupportTicketList extends StatelessWidget {
  const _SupportTicketList({
    required this.tickets,
    required this.loading,
    required this.error,
    required this.onRetry,
    required this.onOpen,
    required this.hiddenView,
    required this.busy,
    required this.onHiddenChanged,
    required this.onAction,
  });

  final List<AirmiusSupportTicket> tickets;
  final bool loading;
  final String? error;
  final VoidCallback onRetry;
  final ValueChanged<AirmiusSupportTicket> onOpen;
  final bool hiddenView;
  final bool busy;
  final ValueChanged<bool> onHiddenChanged;
  final void Function(AirmiusSupportTicket, String) onAction;

  String _statusLabel(AirmiusScope scope, String status) => switch (status) {
    'open' => scope.t('support.ticketStatus.open'),
    'in_progress' => scope.t('support.ticketStatus.inProgress'),
    'waiting_user' => scope.t('support.ticketStatus.waiting'),
    'resolved' => scope.t('support.ticketStatus.resolved'),
    'closed' => scope.t('support.ticketStatus.closed'),
    _ => status,
  };

  @override
  Widget build(BuildContext context) {
    final scope = AirmiusScope.of(context);
    return AirmiusPanel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Eyebrow(scope.t('support.ticketsTitle')),
          SwitchListTile(
            contentPadding: EdgeInsets.zero,
            title: Text(scope.t('support.lifecycle.hidden_list')),
            value: hiddenView,
            onChanged: loading || busy ? null : onHiddenChanged,
          ),
          const SizedBox(height: 8),
          if (loading)
            const LinearProgressIndicator()
          else if (error != null)
            Row(
              children: [
                Expanded(child: Text(error!)),
                IconButton(
                  tooltip: scope.t('support.ticketsRetry'),
                  onPressed: onRetry,
                  icon: const Icon(Icons.refresh_outlined),
                ),
              ],
            )
          else if (tickets.isEmpty)
            Text(
              scope.t('support.ticketsEmpty'),
              style: TextStyle(color: airmiusMutedColor(context)),
            )
          else
            for (final ticket in tickets) ...[
              ListTile(
                contentPadding: EdgeInsets.zero,
                leading: const Icon(Icons.confirmation_number_outlined),
                title: Text(
                  ticket.subject,
                  style: const TextStyle(fontWeight: FontWeight.w800),
                ),
                subtitle: Text(
                  [
                    ticket.withdrawnAt != null
                        ? scope.t('support.lifecycle.withdrawn')
                        : _statusLabel(scope, ticket.status),
                    ticket.category,
                    if (ticket.clubName?.isNotEmpty == true) ticket.clubName!,
                  ].join(' · '),
                ),
                trailing: Text('#${ticket.id}'),
                onTap: busy ? null : () => onOpen(ticket),
              ),
              Wrap(
                children: [
                  if (ticket.canWithdraw)
                    TextButton(
                      onPressed: busy
                          ? null
                          : () => onAction(ticket, 'withdraw'),
                      child: Text(scope.t('support.lifecycle.withdraw')),
                    ),
                  if (ticket.canHide)
                    TextButton(
                      onPressed: busy
                          ? null
                          : () => onAction(
                              ticket,
                              ticket.requesterHiddenAt != null
                                  ? 'restore'
                                  : 'hide',
                            ),
                      child: Text(
                        scope.t(
                          'support.lifecycle.${ticket.requesterHiddenAt != null ? 'restore' : 'hide'}',
                        ),
                      ),
                    ),
                ],
              ),
              if (ticket != tickets.last) const Divider(height: 1),
            ],
        ],
      ),
    );
  }
}

String _platformName() => switch (defaultTargetPlatform) {
  TargetPlatform.android => 'android',
  TargetPlatform.iOS => 'ios',
  TargetPlatform.macOS => 'macos',
  TargetPlatform.windows => 'windows',
  TargetPlatform.linux => 'linux',
  TargetPlatform.fuchsia => 'fuchsia',
};

mixin _TicketActions<T extends StatefulWidget> on State<T> {
  bool _lifecycleBusy = false;
  Future<bool> _ticketLifecycle(int ticketId, String action) async {
    if (_lifecycleBusy) return false;
    final t = AirmiusScope.of(context).t;
    if (action != 'restore') {
      final confirmed = await showDialog<bool>(
        context: context,
        builder: (context) => AlertDialog(
          title: Text(t('support.lifecycle.$action')),
          content: Text(t('support.lifecycle.${action}_confirm')),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(context, false),
              child: Text(t('common.close')),
            ),
            FilledButton(
              onPressed: () => Navigator.pop(context, true),
              child: Text(t('support.lifecycle.$action')),
            ),
          ],
        ),
      );
      if (confirmed != true || !mounted) return false;
    }
    setState(() => _lifecycleBusy = true);
    try {
      final services = AirmiusServicesScope.of(context);
      final client = services.clientForSession(services.authState.session);
      final result = action == 'withdraw'
          ? await client.withdrawSupportTicket(ticketId)
          : await client.setSupportTicketVisibility(
              ticketId,
              hidden: action == 'hide',
            );
      if (!mounted) return false;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            result['message']?.toString() ??
                t(
                  'support.lifecycle.${action == 'withdraw'
                      ? 'withdrawn_notice'
                      : action == 'hide'
                      ? 'hidden_notice'
                      : 'restored_notice'}',
                ),
          ),
        ),
      );
      return true;
    } on AirmiusApiException catch (error) {
      if (mounted)
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(error.userMessage)));
      return false;
    } finally {
      if (mounted) setState(() => _lifecycleBusy = false);
    }
  }
}

class SupportTicketDetailScreen extends StatefulWidget {
  const SupportTicketDetailScreen({super.key, required this.ticketId});
  final int ticketId;
  @override
  State<SupportTicketDetailScreen> createState() => _SupportTicketDetailState();
}

class _SupportTicketDetailState extends State<SupportTicketDetailScreen>
    with _TicketActions {
  final reply = TextEditingController();
  Future<JsonMap>? _detail;
  bool _sending = false;

  AirmiusApiClient get _client {
    final services = AirmiusServicesScope.of(context);
    return services.clientForSession(services.authState.session);
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _detail ??= _client.supportTicket(widget.ticketId);
  }

  @override
  void dispose() {
    reply.dispose();
    super.dispose();
  }

  Future<void> _send() async {
    final body = reply.text.trim();
    if (_sending || body.length < 2) return;
    setState(() => _sending = true);
    try {
      await _client.replySupportTicket(widget.ticketId, body);
      if (!mounted) return;
      reply.clear();
      setState(() => _detail = _client.supportTicket(widget.ticketId));
    } on AirmiusApiException catch (error) {
      if (mounted)
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(error.userMessage)));
    } finally {
      if (mounted) setState(() => _sending = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return FutureBuilder<JsonMap>(
      future: _detail,
      builder: (context, snapshot) {
        final data = snapshot.data?['data'] as JsonMap?;
        final messages = (data?['messages'] as List? ?? [])
            .whereType<JsonMap>();
        final error = snapshot.error;
        return Scaffold(
          appBar: AppBar(
            title: Text(data?['subject']?.toString() ?? t('support.title')),
          ),
          body: SafeArea(
            child: error != null
                ? Center(
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Text(
                          error is AirmiusApiException
                              ? error.userMessage
                              : t('support.ticketsLoadError'),
                        ),
                        TextButton(
                          onPressed: () => setState(
                            () => _detail = _client.supportTicket(
                              widget.ticketId,
                            ),
                          ),
                          child: Text(t('support.ticketsRetry')),
                        ),
                      ],
                    ),
                  )
                : data == null
                ? const Center(child: CircularProgressIndicator())
                : ListView(
                    padding: const EdgeInsets.all(16),
                    children: [
                      Text(
                        t('support.initialMessage'),
                        style: const TextStyle(fontWeight: FontWeight.w800),
                      ),
                      const SizedBox(height: 4),
                      Text('${data['message'] ?? ''}'),
                      if (data['withdrawn_at'] != null)
                        Text(t('support.lifecycle.withdrawn_notice')),
                      const Divider(height: 28),
                      for (final message in messages) ...[
                        Text(
                          message['is_staff'] == true
                              ? t('support.supportAuthor')
                              : t('support.youAuthor'),
                          style: const TextStyle(fontWeight: FontWeight.w800),
                        ),
                        const SizedBox(height: 3),
                        Text('${message['body'] ?? ''}'),
                        const Divider(height: 22),
                      ],
                      if (data['can_reply'] == true) ...[
                        TextField(
                          controller: reply,
                          minLines: 2,
                          maxLines: 5,
                          maxLength: 10000,
                          decoration: InputDecoration(
                            labelText: t('support.reply'),
                          ),
                        ),
                        Text(
                          t('support.attachmentsUnavailable'),
                          style: TextStyle(
                            color: airmiusMutedColor(context),
                            fontSize: 12,
                          ),
                        ),
                      ],

                      if (data['can_reply'] == true)
                        FilledButton.icon(
                          onPressed: _sending || _lifecycleBusy ? null : _send,
                          icon: const Icon(Icons.send_outlined),
                          label: Text(t('support.sendReply')),
                        ),
                      for (final action in [
                        if (data['can_withdraw'] == true) 'withdraw',
                        if (data['can_hide'] == true)
                          data['requester_hidden_at'] != null
                              ? 'restore'
                              : 'hide',
                      ])
                        TextButton(
                          onPressed: _sending || _lifecycleBusy
                              ? null
                              : () async {
                                  if (await _ticketLifecycle(
                                        widget.ticketId,
                                        action,
                                      ) &&
                                      context.mounted)
                                    Navigator.pop(context);
                                },
                          child: Text(t('support.lifecycle.$action')),
                        ),
                    ],
                  ),
          ),
        );
      },
    );
  }
}
