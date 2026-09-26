import 'package:flutter/material.dart';

import '../core/airmius_api_client.dart';
import '../core/airmius_api_models.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';
import '../core/airmius_theme.dart';
import '../models/club_summary.dart';
import '../widgets/airmius_widgets.dart';

class ClubSurveyScreen extends StatefulWidget {
  const ClubSurveyScreen({super.key, required this.club});

  final ClubSummary club;

  @override
  State<ClubSurveyScreen> createState() => _ClubSurveyScreenState();
}

class _ClubSurveyScreenState extends State<ClubSurveyScreen> {
  bool _loading = false;
  String? _error;
  List<AirmiusClubSurvey> _surveys = const [];
  int? _votingSurveyId;
  int? _closingSurveyId;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (!_loading && _surveys.isEmpty && _error == null) {
      _load();
    }
  }

  Future<void> _load() async {
    if (_loading) return;
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final surveys = await AirmiusServicesScope.of(
        context,
      ).repositories.clubs.surveys(widget.club.id);
      if (!mounted) return;
      setState(() {
        _surveys = surveys;
        _loading = false;
      });
    } on AirmiusApiException catch (error) {
      if (!mounted) return;
      setState(() {
        _loading = false;
        _error = error.userMessage;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _loading = false;
        _error = AirmiusScope.of(context).t('clubs.surveys.loadError');
      });
    }
  }

  Future<void> _vote(AirmiusClubSurvey survey, int optionId) async {
    if (!survey.isOpen || _votingSurveyId != null) return;
    setState(() => _votingSurveyId = survey.id);
    try {
      await AirmiusServicesScope.of(
        context,
      ).repositories.clubs.voteSurvey(widget.club.id, survey.id, optionId);
      await _reloadAfterAction();
      if (!mounted) return;
      _message(AirmiusScope.of(context).t('clubs.surveys.voteSaved'));
    } on AirmiusApiException catch (error) {
      if (mounted) _message(error.userMessage);
    } catch (_) {
      if (mounted) {
        _message(AirmiusScope.of(context).t('clubs.surveys.voteError'));
      }
    } finally {
      if (mounted) setState(() => _votingSurveyId = null);
    }
  }

  Future<void> _close(AirmiusClubSurvey survey) async {
    if (!survey.canClose || !survey.isOpen || _closingSurveyId != null) {
      return;
    }
    final scope = AirmiusScope.of(context);
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: Text(scope.t('clubs.surveys.closeTitle')),
        content: Text(scope.t('clubs.surveys.closeBody')),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext, false),
            child: Text(scope.t('clubs.surveys.back')),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(dialogContext, true),
            child: Text(scope.t('clubs.surveys.close')),
          ),
        ],
      ),
    );
    if (confirmed != true || !mounted) return;
    setState(() => _closingSurveyId = survey.id);
    try {
      await AirmiusServicesScope.of(
        context,
      ).repositories.clubs.closeSurvey(widget.club.id, survey.id);
      await _reloadAfterAction();
      if (mounted) _message(scope.t('clubs.surveys.closedMessage'));
    } on AirmiusApiException catch (error) {
      if (mounted) _message(error.userMessage);
    } catch (_) {
      if (mounted) _message(scope.t('clubs.surveys.closeError'));
    } finally {
      if (mounted) setState(() => _closingSurveyId = null);
    }
  }

  Future<void> _create([AirmiusClubSurvey? existing]) async {
    if (!widget.club.canEditSurveys && existing == null) return;
    if (existing != null && !existing.canEdit) return;
    final payload = await showDialog<JsonMap>(
      context: context,
      builder: (_) => _CreateClubSurveyDialog(
        teams: widget.club.teamList,
        existing: existing,
      ),
    );
    if (payload == null || !mounted) return;
    try {
      if (existing == null) {
        await AirmiusServicesScope.of(
          context,
        ).repositories.clubs.createSurvey(widget.club.id, payload);
      } else {
        await AirmiusServicesScope.of(context).repositories.clubs.updateSurvey(
          widget.club.id,
          existing.id,
          payload,
        );
      }
      await _reloadAfterAction();
      if (mounted) {
        _message(AirmiusScope.of(context).t('clubs.surveys.created'));
      }
    } on AirmiusApiException catch (error) {
      if (mounted) _message(error.userMessage);
    } catch (_) {
      if (mounted) {
        _message(AirmiusScope.of(context).t('clubs.surveys.createError'));
      }
    }
  }

  Future<void> _delete(AirmiusClubSurvey survey) async {
    if (!survey.canDelete) return;
    final scope = AirmiusScope.of(context);
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: Text(scope.t('common.delete')),
        content: Text(survey.question),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext, false),
            child: Text(scope.t('common.cancel')),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(dialogContext, true),
            child: Text(scope.t('common.delete')),
          ),
        ],
      ),
    );
    if (confirmed != true || !mounted) return;
    try {
      await AirmiusServicesScope.of(
        context,
      ).repositories.clubs.deleteSurvey(widget.club.id, survey.id);
      await _reloadAfterAction();
    } on AirmiusApiException catch (error) {
      if (mounted) _message(error.userMessage);
    }
  }

  Future<void> _reloadAfterAction() async {
    final surveys = await AirmiusServicesScope.of(
      context,
    ).repositories.clubs.surveys(widget.club.id);
    if (mounted) setState(() => _surveys = surveys);
  }

  void _message(String value) {
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(value)));
  }

  @override
  Widget build(BuildContext context) {
    final scope = AirmiusScope.of(context);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        AirmiusPanel(
          gradient: true,
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              IconBadge(
                icon: Icons.how_to_vote_outlined,
                color: airmiusAccentColor(context),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Eyebrow(scope.t('clubs.surveys.eyebrow')),
                    const SizedBox(height: 5),
                    Text(
                      scope.t('clubs.surveys.subtitle'),
                      style: TextStyle(
                        color: airmiusMutedColor(context),
                        height: 1.35,
                      ),
                    ),
                  ],
                ),
              ),
              if (widget.club.canEditSurveys)
                IconButton(
                  tooltip: scope.t('clubs.surveys.create'),
                  onPressed: _loading ? null : _create,
                  icon: const Icon(Icons.add_circle_outline),
                ),
            ],
          ),
        ),
        const SizedBox(height: 12),
        if (_loading)
          AirmiusPanel(
            child: LinearProgressIndicator(
              color: airmiusAccentColor(context),
              backgroundColor: airmiusSurfaceSoftColor(context),
            ),
          )
        else if (_error != null)
          AirmiusPanel(
            borderColor: AirmiusColors.red.withValues(alpha: .5),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Text(
                  _error!,
                  style: TextStyle(
                    color: AirmiusColors.red,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                const SizedBox(height: 10),
                AirmiusButton(
                  label: scope.t('clubs.surveys.retry'),
                  icon: Icons.refresh,
                  secondary: true,
                  onPressed: _load,
                ),
              ],
            ),
          )
        else if (_surveys.isEmpty)
          AirmiusPanel(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Text(
                  scope.t('clubs.surveys.empty'),
                  style: TextStyle(
                    color: airmiusMutedColor(context),
                    fontWeight: FontWeight.w700,
                  ),
                ),
                if (widget.club.canEditSurveys) ...[
                  const SizedBox(height: 10),
                  AirmiusButton(
                    label: scope.t('clubs.surveys.create'),
                    icon: Icons.add_outlined,
                    onPressed: _create,
                  ),
                ],
              ],
            ),
          )
        else
          for (final survey in _surveys) ...[
            _ClubSurveyCard(
              survey: survey,
              voting: _votingSurveyId == survey.id,
              closing: _closingSurveyId == survey.id,
              onVote: (optionId) => _vote(survey, optionId),
              onClose: () => _close(survey),
              onEdit: () => _create(survey),
              onDelete: () => _delete(survey),
            ),
            const SizedBox(height: 12),
          ],
      ],
    );
  }
}

class _ClubSurveyCard extends StatelessWidget {
  const _ClubSurveyCard({
    required this.survey,
    required this.voting,
    required this.closing,
    required this.onVote,
    required this.onClose,
    required this.onEdit,
    required this.onDelete,
  });

  final AirmiusClubSurvey survey;
  final bool voting;
  final bool closing;
  final ValueChanged<int> onVote;
  final VoidCallback onClose;
  final VoidCallback onEdit;
  final VoidCallback onDelete;

  @override
  Widget build(BuildContext context) {
    final scope = AirmiusScope.of(context);
    final total = survey.options.fold<int>(0, (sum, item) => sum + item.votes);
    final open = survey.isOpen;
    final audience = survey.audienceType == 'team'
        ? '${scope.t('clubs.surveys.team')}: ${survey.teamName ?? '-'}'
        : scope.t('clubs.surveys.allMembers');
    return AirmiusPanel(
      borderColor: open
          ? airmiusAccentColor(context).withValues(alpha: .45)
          : airmiusBorderColor(context),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: Text(
                  survey.question,
                  style: TextStyle(
                    color: airmiusTextColor(context),
                    fontSize: 17,
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ),
              StatusPill(
                open
                    ? scope.t('clubs.surveys.open')
                    : scope.t('clubs.surveys.closed'),
                color: open ? AirmiusColors.green : AirmiusColors.amber,
              ),
              if (survey.canClose && open)
                IconButton(
                  tooltip: scope.t('clubs.surveys.close'),
                  onPressed: closing ? null : onClose,
                  icon: const Icon(Icons.lock_outline),
                ),
              if ((survey.canEdit && open && survey.votes == 0) ||
                  (survey.canDelete && survey.votes == 0))
                PopupMenuButton<String>(
                  onSelected: (value) =>
                      value == 'edit' ? onEdit() : onDelete(),
                  itemBuilder: (_) => [
                    if (survey.canEdit && open && survey.votes == 0)
                      PopupMenuItem(
                        value: 'edit',
                        child: Text(scope.t('common.edit')),
                      ),
                    if (survey.canDelete && survey.votes == 0)
                      PopupMenuItem(
                        value: 'delete',
                        child: Text(scope.t('common.delete')),
                      ),
                  ],
                ),
            ],
          ),
          if (survey.description?.isNotEmpty == true) ...[
            const SizedBox(height: 6),
            Text(
              survey.description!,
              style: TextStyle(color: airmiusMutedColor(context), height: 1.35),
            ),
          ],
          const SizedBox(height: 8),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              StatusPill(audience, color: airmiusAccentColor(context)),
              StatusPill(
                '${survey.votes}/${survey.eligibleVoters} ${scope.t('clubs.surveys.votes')}',
                color: AirmiusColors.green,
              ),
              if (survey.quorum != null)
                StatusPill(
                  '${scope.t('clubs.surveys.quorum')} ${survey.quorum}% · ${survey.quorumReached ? scope.t('clubs.surveys.reached') : scope.t('clubs.surveys.pending')}',
                  color: survey.quorumReached
                      ? AirmiusColors.green
                      : AirmiusColors.amber,
                ),
            ],
          ),
          const SizedBox(height: 8),
          for (final option in survey.options) ...[
            RadioListTile<int>(
              value: option.id,
              // ignore: deprecated_member_use
              groupValue: survey.myOptionId,
              // ignore: deprecated_member_use
              onChanged: open && !voting
                  ? (value) {
                      if (value != null) onVote(value);
                    }
                  : null,
              dense: true,
              contentPadding: EdgeInsets.zero,
              title: Text(option.label),
              subtitle: total == 0
                  ? null
                  : Text(
                      '${option.votes} ${scope.t('clubs.surveys.votes')} · ${(option.votes * 100 / total).round()}%',
                    ),
            ),
          ],
          if (voting || closing) ...[
            const SizedBox(height: 6),
            LinearProgressIndicator(
              color: airmiusAccentColor(context),
              backgroundColor: airmiusSurfaceSoftColor(context),
            ),
          ],
        ],
      ),
    );
  }
}

class _CreateClubSurveyDialog extends StatefulWidget {
  const _CreateClubSurveyDialog({required this.teams, this.existing});

  final List<TeamSummary> teams;
  final AirmiusClubSurvey? existing;

  @override
  State<_CreateClubSurveyDialog> createState() =>
      _CreateClubSurveyDialogState();
}

class _CreateClubSurveyDialogState extends State<_CreateClubSurveyDialog> {
  final _formKey = GlobalKey<FormState>();
  final _question = TextEditingController();
  final _description = TextEditingController();
  final _quorum = TextEditingController();
  late final List<TextEditingController> _options;
  String _audienceType = 'all_members';
  int? _teamId;

  @override
  void initState() {
    super.initState();
    final existing = widget.existing;
    _question.text = existing?.question ?? '';
    _description.text = existing?.description ?? '';
    _quorum.text = existing?.quorum?.toString() ?? '';
    _audienceType = existing?.audienceType ?? 'all_members';
    _teamId = existing?.teamId;
    _options = existing == null
        ? [TextEditingController(), TextEditingController()]
        : existing.options
              .map((option) => TextEditingController(text: option.label))
              .toList();
  }

  @override
  void dispose() {
    _question.dispose();
    _description.dispose();
    _quorum.dispose();
    for (final option in _options) {
      option.dispose();
    }
    super.dispose();
  }

  void _addOption() {
    if (_options.length >= 6) return;
    setState(() => _options.add(TextEditingController()));
  }

  void _removeOption(int index) {
    if (_options.length <= 2) return;
    final controller = _options.removeAt(index);
    controller.dispose();
    setState(() {});
  }

  void _submit() {
    final scope = AirmiusScope.of(context);
    if (!_formKey.currentState!.validate()) return;
    if (_audienceType == 'team' && _teamId == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(scope.t('clubs.surveys.teamRequired'))),
      );
      return;
    }
    Navigator.of(context).pop<JsonMap>({
      'question': _question.text.trim(),
      'description': _description.text.trim().isEmpty
          ? null
          : _description.text.trim(),
      'audience_type': _audienceType,
      'team_id': _audienceType == 'team' ? _teamId : null,
      'quorum': _quorum.text.trim().isEmpty
          ? null
          : int.tryParse(_quorum.text.trim()),
      if (widget.existing?.closesAt != null)
        'closes_at': widget.existing!.closesAt!.toUtc().toIso8601String(),
      'options': _options.map((item) => item.text.trim()).toList(),
    });
  }

  @override
  Widget build(BuildContext context) {
    final scope = AirmiusScope.of(context);
    return AlertDialog(
      title: Text(scope.t('clubs.surveys.createTitle')),
      content: SizedBox(
        width: 540,
        child: Form(
          key: _formKey,
          child: SingleChildScrollView(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                TextFormField(
                  controller: _question,
                  autofocus: true,
                  maxLength: 255,
                  decoration: InputDecoration(
                    labelText: scope.t('clubs.surveys.question'),
                    hintText: scope.t('clubs.surveys.questionHint'),
                  ),
                  validator: (value) => value == null || value.trim().isEmpty
                      ? scope.t('clubs.surveys.questionRequired')
                      : null,
                ),
                TextFormField(
                  controller: _description,
                  maxLength: 2000,
                  maxLines: 3,
                  decoration: InputDecoration(
                    labelText: scope.t('clubs.surveys.description'),
                  ),
                ),
                DropdownButtonFormField<String>(
                  initialValue: _audienceType,
                  decoration: InputDecoration(
                    labelText: scope.t('clubs.surveys.audience'),
                  ),
                  items: [
                    DropdownMenuItem(
                      value: 'all_members',
                      child: Text(scope.t('clubs.surveys.allMembers')),
                    ),
                    if (widget.teams.isNotEmpty)
                      DropdownMenuItem(
                        value: 'team',
                        child: Text(scope.t('clubs.surveys.teamOnly')),
                      ),
                  ],
                  onChanged: (value) {
                    if (value == null) return;
                    setState(() {
                      _audienceType = value;
                      if (value != 'team') _teamId = null;
                    });
                  },
                ),
                if (_audienceType == 'team') ...[
                  const SizedBox(height: 10),
                  DropdownButtonFormField<int>(
                    initialValue: _teamId,
                    decoration: InputDecoration(
                      labelText: scope.t('clubs.surveys.team'),
                    ),
                    items: [
                      for (final team in widget.teams)
                        DropdownMenuItem(
                          value: team.id,
                          child: Text(team.name),
                        ),
                    ],
                    onChanged: (value) => setState(() => _teamId = value),
                  ),
                ],
                const SizedBox(height: 10),
                TextFormField(
                  controller: _quorum,
                  keyboardType: TextInputType.number,
                  decoration: InputDecoration(
                    labelText: scope.t('clubs.surveys.quorumOptional'),
                    suffixText: '%',
                  ),
                  validator: (value) {
                    final text = value?.trim() ?? '';
                    if (text.isEmpty) return null;
                    final number = int.tryParse(text);
                    return number == null || number < 1 || number > 100
                        ? scope.t('clubs.surveys.quorumError')
                        : null;
                  },
                ),
                const SizedBox(height: 10),
                for (var index = 0; index < _options.length; index++)
                  Padding(
                    padding: const EdgeInsets.only(bottom: 8),
                    child: TextFormField(
                      controller: _options[index],
                      maxLength: 255,
                      decoration: InputDecoration(
                        labelText:
                            '${scope.t('clubs.surveys.option')} ${index + 1}',
                        hintText: scope.t('clubs.surveys.optionHint'),
                        suffixIcon: _options.length > 2
                            ? IconButton(
                                tooltip: scope.t('clubs.surveys.removeOption'),
                                onPressed: () => _removeOption(index),
                                icon: const Icon(Icons.close),
                              )
                            : null,
                      ),
                      validator: (value) =>
                          value == null || value.trim().isEmpty
                          ? scope.t('clubs.surveys.optionRequired')
                          : null,
                    ),
                  ),
                Align(
                  alignment: AlignmentDirectional.centerStart,
                  child: TextButton.icon(
                    onPressed: _options.length >= 6 ? null : _addOption,
                    icon: const Icon(Icons.add),
                    label: Text(scope.t('clubs.surveys.addOption')),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.pop(context),
          child: Text(scope.t('clubs.surveys.back')),
        ),
        FilledButton.icon(
          onPressed: _submit,
          icon: const Icon(Icons.how_to_vote_outlined),
          label: Text(scope.t('clubs.surveys.create')),
        ),
      ],
    );
  }
}
