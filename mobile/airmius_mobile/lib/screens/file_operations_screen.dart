import 'package:flutter/material.dart';

import '../core/api_contract.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';

class FileOperationsScreen extends StatefulWidget {
  const FileOperationsScreen({
    super.key,
    this.initialTab = 'Uploads',
    this.onOpenUploader,
  });

  final String initialTab;
  final VoidCallback? onOpenUploader;

  @override
  State<FileOperationsScreen> createState() => _FileOperationsScreenState();
}

class _FileOperationsScreenState extends State<FileOperationsScreen> {
  String _tab = 'uploads';
  String _query = '';

  @override
  void initState() {
    super.initState();
    if (_tabs.contains(widget.initialTab)) _tab = widget.initialTab;
  }

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final operations = _filtered(
      _tab == 'all'
          ? _operations
          : _operations.where((item) => item.tab == _tab).toList(),
      t,
    );
    return Scaffold(
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      appBar: AppBar(
        backgroundColor:
            Theme.of(context).appBarTheme.backgroundColor ??
            airmiusSurfaceColor(context),
        surfaceTintColor: Colors.transparent,
        title: Text(
          t('filesOps.title'),
          style: TextStyle(fontWeight: FontWeight.w900),
        ),
      ),
      body: PageFrame(
        title: t('filesOps.title'),
        subtitle: t('filesOps.subtitle'),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            AirmiusPanel(
              borderColor: airmiusAccentColor(context).withValues(alpha: .45),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Eyebrow(t('filesOps.eyebrow')),
                  const SizedBox(height: 8),
                  Text(
                    t('filesOps.body'),
                    style: TextStyle(
                      color: airmiusMutedColor(context),
                      height: 1.4,
                    ),
                  ),
                  const SizedBox(height: 14),
                  SearchBox(
                    hint: t('filesOps.search'),
                    onChanged: (value) =>
                        setState(() => _query = value.trim().toLowerCase()),
                  ),
                  const SizedBox(height: 14),
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: [
                      for (final tab in _tabs)
                        ChoiceChip(
                          label: Text(_tabLabel(tab, t)),
                          selected: _tab == tab,
                          onSelected: (_) => setState(() => _tab = tab),
                          selectedColor: airmiusAccentColor(
                            context,
                          ).withValues(alpha: .24),
                          backgroundColor: airmiusSurfaceSoftColor(context),
                          side: BorderSide(
                            color: _tab == tab
                                ? airmiusAccentColor(context)
                                : airmiusBorderColor(context),
                          ),
                          labelStyle: TextStyle(
                            color: _tab == tab
                                ? airmiusTextColor(context)
                                : airmiusMutedColor(context),
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                    ],
                  ),
                ],
              ),
            ),
            const SizedBox(height: 16),
            for (final operation in operations) ...[
              _FileOperationCard(
                operation: operation,
                t: t,
                onOpenUploader: widget.onOpenUploader,
              ),
              const SizedBox(height: 12),
            ],
            if (operations.isEmpty) EmptyPanel(t('filesOps.empty')),
          ],
        ),
      ),
    );
  }

  List<_FileOperation> _filtered(
    List<_FileOperation> source,
    String Function(String) t,
  ) {
    if (_query.isEmpty) return source;
    return source
        .where(
          (item) =>
              '${t(item.titleKey)} ${t(item.bodyKey)} ${AirmiusApiContract.mobileApiPath(item.endpoint)} ${item.method}'
                  .toLowerCase()
                  .contains(_query),
        )
        .toList();
  }
}

String _tabLabel(String tab, String Function(String) t) =>
    t('filesOps.tab.$tab');

class _FileOperationCard extends StatelessWidget {
  const _FileOperationCard({
    required this.operation,
    required this.t,
    this.onOpenUploader,
  });

  final _FileOperation operation;
  final String Function(String) t;
  final VoidCallback? onOpenUploader;

  @override
  Widget build(BuildContext context) {
    final endpoint = AirmiusApiContract.mobileApiPath(operation.endpoint);
    final operationColor = operation.color == AirmiusColors.blue
        ? airmiusAccentColor(context)
        : operation.color;
    return AirmiusPanel(
      borderColor: operation.danger
          ? AirmiusColors.red.withValues(alpha: .45)
          : airmiusBorderColor(context),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                width: 48,
                height: 48,
                decoration: BoxDecoration(
                  color: operationColor.withValues(alpha: .13),
                  borderRadius: BorderRadius.circular(16),
                  border: Border.all(
                    color: operationColor.withValues(alpha: .5),
                  ),
                ),
                child: Icon(operation.icon, color: operationColor),
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      t(operation.titleKey),
                      style: TextStyle(
                        color: airmiusTextColor(context),
                        fontWeight: FontWeight.w900,
                        fontSize: 17,
                      ),
                    ),
                    const SizedBox(height: 5),
                    Text(
                      t(operation.bodyKey),
                      style: TextStyle(
                        color: airmiusMutedColor(context),
                        height: 1.35,
                      ),
                    ),
                  ],
                ),
              ),
              StatusPill(_tabLabel(operation.tab, t), color: operationColor),
            ],
          ),
          const SizedBox(height: 14),
          Container(
            width: double.infinity,
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(
              color: Theme.of(context).scaffoldBackgroundColor,
              borderRadius: BorderRadius.circular(14),
              border: Border.all(color: airmiusBorderColor(context)),
            ),
            child: Text(
              '${operation.method} $endpoint',
              style: TextStyle(
                color: AirmiusColors.green,
                fontSize: 12,
                fontWeight: FontWeight.w900,
              ),
            ),
          ),
          const SizedBox(height: 14),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              if (operation.actionKey == 'filesOps.uploadAction' &&
                  onOpenUploader != null)
                AirmiusButton(
                  label: t(operation.actionKey),
                  icon: operation.icon,
                  danger: operation.danger,
                  onPressed: onOpenUploader,
                ),
              AirmiusButton(
                label: t('filesOps.apiContext'),
                icon: Icons.api_outlined,
                secondary: true,
                onPressed: () => showDialog<void>(
                  context: context,
                  builder: (dialogContext) => AlertDialog(
                    backgroundColor: airmiusSurfaceColor(dialogContext),
                    title: Text(
                      '${t(operation.titleKey)} API',
                      style: TextStyle(
                        color: airmiusTextColor(dialogContext),
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    content: Text(
                      '${t('filesOps.apiBody')}\n\n${operation.method} $endpoint',
                      style: TextStyle(color: airmiusMutedColor(dialogContext)),
                    ),
                    actions: [
                      TextButton(
                        onPressed: () => Navigator.pop(dialogContext),
                        child: Text(t('files.cancel')),
                      ),
                    ],
                  ),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _FileOperation {
  const _FileOperation({
    required this.tab,
    required this.titleKey,
    required this.bodyKey,
    required this.method,
    required this.endpoint,
    required this.icon,
    required this.actionKey,
    required this.color,
    this.danger = false,
  });
  final String tab;
  final String titleKey;
  final String bodyKey;
  final String method;
  final String endpoint;
  final IconData icon;
  final String actionKey;
  final Color color;
  final bool danger;
}

const _tabs = [
  'uploads',
  'club',
  'application',
  'team',
  'rules',
  'all',
];

final _operations = <_FileOperation>[
  _FileOperation(
    tab: 'uploads',
    titleKey: 'files.uploadTitle',
    bodyKey: 'filesOps.uploadBody',
    method: 'POST',
    endpoint: ApiContract.uploads,
    icon: Icons.upload_file_outlined,
    actionKey: 'filesOps.uploadAction',
    color: AirmiusColors.blue,
  ),
  _FileOperation(
    tab: 'uploads',
    titleKey: 'filesOps.updateTitle',
    bodyKey: 'filesOps.updateBody',
    method: 'PATCH',
    endpoint: ApiContract.upload(1),
    icon: Icons.edit_outlined,
    actionKey: 'filesOps.updateAction',
    color: AirmiusColors.blue,
  ),
  _FileOperation(
    tab: 'uploads',
    titleKey: 'filesOps.deleteTitle',
    bodyKey: 'filesOps.deleteBody',
    method: 'DELETE',
    endpoint: ApiContract.upload(1),
    icon: Icons.delete_outline,
    actionKey: 'files.fileDelete',
    color: AirmiusColors.red,
    danger: true,
  ),
  _FileOperation(
    tab: 'club',
    titleKey: 'filesOps.linkClubTitle',
    bodyKey: 'filesOps.linkClubBody',
    method: 'POST',
    endpoint: ApiContract.clubDocuments(1),
    icon: Icons.folder_shared_outlined,
    actionKey: 'filesOps.linkAction',
    color: AirmiusColors.amber,
  ),
  _FileOperation(
    tab: 'club',
    titleKey: 'filesOps.unlinkClubTitle',
    bodyKey: 'filesOps.unlinkClubBody',
    method: 'DELETE',
    endpoint: ApiContract.clubDocument(1, 1),
    icon: Icons.link_off_outlined,
    actionKey: 'filesOps.unlinkAction',
    color: AirmiusColors.red,
    danger: true,
  ),
  _FileOperation(
    tab: 'application',
    titleKey: 'filesOps.linkApplicationTitle',
    bodyKey: 'filesOps.linkApplicationBody',
    method: 'POST',
    endpoint: ApiContract.clubMembershipDocuments(1),
    icon: Icons.assignment_outlined,
    actionKey: 'filesOps.linkApplicationAction',
    color: AirmiusColors.green,
  ),
  _FileOperation(
    tab: 'application',
    titleKey: 'filesOps.unlinkApplicationTitle',
    bodyKey: 'filesOps.unlinkApplicationBody',
    method: 'DELETE',
    endpoint: ApiContract.clubMembershipDocument(1, 1),
    icon: Icons.assignment_return_outlined,
    actionKey: 'filesOps.unlinkApplicationAction',
    color: AirmiusColors.red,
    danger: true,
  ),
  _FileOperation(
    tab: 'team',
    titleKey: 'filesOps.linkTeamTitle',
    bodyKey: 'filesOps.linkTeamBody',
    method: 'POST',
    endpoint: ApiContract.teamFiles(1),
    icon: Icons.groups_2_outlined,
    actionKey: 'filesOps.linkTeamAction',
    color: AirmiusColors.blue,
  ),
  _FileOperation(
    tab: 'team',
    titleKey: 'filesOps.unlinkTeamTitle',
    bodyKey: 'filesOps.unlinkTeamBody',
    method: 'DELETE',
    endpoint: ApiContract.teamFile(1, 1),
    icon: Icons.group_remove_outlined,
    actionKey: 'filesOps.unlinkTeamAction',
    color: AirmiusColors.red,
    danger: true,
  ),
  _FileOperation(
    tab: 'rules',
    titleKey: 'filesOps.privacyTitle',
    bodyKey: 'filesOps.privacyBody',
    method: 'PUT',
    endpoint: ApiContract.clubDocumentPurpose(1, 1, 'privacy'),
    icon: Icons.privacy_tip_outlined,
    actionKey: 'filesOps.setPurposeAction',
    color: AirmiusColors.amber,
  ),
  _FileOperation(
    tab: 'rules',
    titleKey: 'filesOps.feesTitle',
    bodyKey: 'filesOps.feesBody',
    method: 'PUT',
    endpoint: ApiContract.clubDocumentPurpose(1, 1, 'fees'),
    icon: Icons.payments_outlined,
    actionKey: 'filesOps.setRuleAction',
    color: AirmiusColors.amber,
  ),
];
