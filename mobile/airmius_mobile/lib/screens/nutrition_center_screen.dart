import 'dart:convert';

import 'package:file_picker/file_picker.dart';
import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'package:http_parser/http_parser.dart';
import 'package:image_picker/image_picker.dart';

import '../core/airmius_api_client.dart';
import '../core/airmius_api_models.dart';
import '../core/airmius_l10n.dart';
import '../core/airmius_services_scope.dart';
import '../core/airmius_theme.dart';
import '../widgets/airmius_widgets.dart';
import 'nutrition_barcode_scanner_screen.dart';
import 'notification_preferences_screen.dart';

enum NutritionSection { overview, drink }

enum _MealImageSource { camera, gallery }

enum _NutritionCaptureAction { meal, food, barcode, photo }

class NutritionCenterScreen extends StatefulWidget {
  const NutritionCenterScreen({
    super.key,
    this.initialSection = NutritionSection.overview,
  });

  final NutritionSection initialSection;

  @override
  State<NutritionCenterScreen> createState() => _NutritionCenterScreenState();
}

class _NutritionCenterScreenState extends State<NutritionCenterScreen> {
  DateTime _selectedDate = _dateOnly(DateTime.now());
  Future<JsonMap>? _dayFuture;
  JsonMap? _dayData;
  bool _busy = false;
  late NutritionSection _section = widget.initialSection;
  static const _quickWaterAmounts = [150, 250, 500, 750];

  AirmiusApiClient get _client {
    final services = AirmiusServicesScope.of(context);
    return services.clientForSession(services.authState.session);
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _dayFuture ??= _loadDay();
  }

  Future<JsonMap> _loadDay() async {
    final response = await _client.nutrition(date: _isoDate(_selectedDate));
    final data = _map(response['data']);
    _dayData = data;
    return data;
  }

  void _reload() {
    setState(() {
      _dayData = null;
      _dayFuture = _loadDay();
    });
  }

  void _changeDay(int offset) {
    final next = _dateOnly(_selectedDate.add(Duration(days: offset)));
    if (next.isAfter(_dateOnly(DateTime.now()))) return;
    setState(() {
      _selectedDate = next;
      _dayData = null;
      _dayFuture = _loadDay();
    });
  }

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return Scaffold(
      appBar: AppBar(
        backgroundColor:
            Theme.of(context).appBarTheme.backgroundColor ??
            Theme.of(context).colorScheme.surface,
        surfaceTintColor: Colors.transparent,
        title: Text(
          t('nutrition.title'),
          style: const TextStyle(fontWeight: FontWeight.w900),
        ),
        actions: [
          IconButton(
            tooltip: t('nutrition.captureAction'),
            onPressed: _busy ? null : _openCaptureMenu,
            icon: const Icon(Icons.add_circle_outline),
          ),
          IconButton(
            tooltip: t('nutrition.reload'),
            onPressed: _busy ? null : _reload,
            icon: const Icon(Icons.refresh_outlined),
          ),
        ],
      ),
      body: PageFrame(
        title: t('nutrition.title'),
        subtitle: t('nutrition.subtitle'),
        child: FutureBuilder<JsonMap>(
          future: _dayFuture,
          builder: (context, snapshot) {
            if (snapshot.connectionState == ConnectionState.waiting) {
              return const _NutritionLoading();
            }
            if (snapshot.hasError) {
              return _NutritionError(error: snapshot.error, onRetry: _reload);
            }
            return _buildDay(_dayData ?? snapshot.data ?? const {});
          },
        ),
      ),
    );
  }

  Widget _buildDay(JsonMap data) {
    final t = AirmiusScope.of(context).t;
    final goal = _map(data['goal']);
    final summary = _map(data['summary']);
    final water = _map(data['water_recommendation']);
    final catalog = _map(data['catalog']);
    final meals = _maps(data['meals']);
    final weekly = _maps(data['weekly_summaries']);
    final tips = _strings(data['tips']);
    final recipes = _maps(data['recipes']);
    final aiCapabilities = _map(data['ai_capabilities']);
    final imageAnalysis = _map(aiCapabilities['nutrition_image_analysis']);
    final nutritionAccess = _map(data['nutrition_access']);
    final micronutrientsAccess = _map(nutritionAccess['micronutrients']);
    final calorieTarget = _integer(
      goal['daily_calories_target'],
      fallback: 2200,
    );
    final proteinTarget = _integer(goal['protein_target_g'], fallback: 120);
    final carbsTarget = _integer(goal['carbs_target_g'], fallback: 260);
    final fatTarget = _integer(goal['fat_target_g'], fallback: 75);
    final waterTarget = _integer(
      water['target_ml'],
      fallback: _integer(goal['water_target_ml'], fallback: 2500),
    );

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        _DateNavigator(
          selectedDate: _selectedDate,
          onPrevious: _busy ? null : () => _changeDay(-1),
          onNext:
              _busy ||
                  _dateOnly(
                    _selectedDate,
                  ).isAtSameMomentAs(_dateOnly(DateTime.now()))
              ? null
              : () => _changeDay(1),
        ),
        const SizedBox(height: 14),
        _NutritionSectionTabs(
          section: _section,
          onChanged: (section) => setState(() => _section = section),
        ),
        if (_section == NutritionSection.drink) ...[
          const SizedBox(height: 14),
          _QuickWaterPanel(
            consumedMl: _integer(summary['water_ml']),
            targetMl: waterTarget,
            amounts: _quickWaterAmounts,
            busy: _busy,
            onAdd: _logWaterAmount,
          ),
          const SizedBox(height: 14),
          if (_text(water['source_label']).isNotEmpty) ...[
            Text(
              _text(water['source_label']),
              style: TextStyle(color: airmiusMutedColor(context)),
            ),
            const SizedBox(height: 14),
          ],
          AirmiusButton(
            label: t('nutrition.customWaterAmount'),
            icon: Icons.edit_outlined,
            secondary: true,
            onPressed: _busy ? null : _addWater,
          ),
          const SizedBox(height: 10),
          AirmiusButton(
            label: t('nutrition.waterSettings'),
            icon: Icons.notifications_active_outlined,
            secondary: true,
            onPressed: _busy
                ? null
                : () => _editGoal(
                    goal: goal,
                    catalog: catalog,
                    focusWaterSettings: true,
                  ),
          ),
        ] else ...[
          const SizedBox(height: 14),
          AirmiusPanel(
            gradient: true,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Eyebrow(t('nutrition.dailyOverview')),
                const SizedBox(height: 8),
                Text(
                  t('nutrition.dailyOverviewHint'),
                  style: TextStyle(
                    color: airmiusMutedColor(context),
                    height: 1.35,
                  ),
                ),
                const SizedBox(height: 16),
                _MetricGrid(
                  values: [
                    (
                      '${_integer(summary['calories'])}',
                      '${t('nutrition.calories')} / $calorieTarget',
                    ),
                    (
                      '${_numberLabel(summary['protein_g'])} g',
                      '${t('nutrition.protein')} / $proteinTarget g',
                    ),
                    (
                      '${_numberLabel(summary['carbs_g'])} g',
                      '${t('nutrition.carbs')} / $carbsTarget g',
                    ),
                    (
                      '${_waterLitres(_integer(summary['water_ml']))} L',
                      '${t('nutrition.water')} / ${_waterLitres(waterTarget)} L',
                    ),
                  ],
                ),
              ],
            ),
          ),
          const SizedBox(height: 14),
          AirmiusButton(
            label: t('nutrition.addMeal'),
            icon: Icons.add_circle_outline,
            onPressed: _busy ? null : _openCaptureMenu,
          ),
          const SizedBox(height: 14),
          AirmiusPanel(
            title: t('nutrition.targets'),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                AirmiusButton(
                  label: t('nutrition.editTargets'),
                  icon: Icons.tune_outlined,
                  secondary: true,
                  onPressed: _busy
                      ? null
                      : () => _editGoal(goal: goal, catalog: catalog),
                ),
                const SizedBox(height: 16),
                _ProgressLine(
                  title: t('nutrition.calories'),
                  current: _number(summary['calories']),
                  target: calorieTarget.toDouble(),
                  label:
                      '${_integer(summary['calories'])} / $calorieTarget kcal',
                ),
                const SizedBox(height: 13),
                _ProgressLine(
                  title: t('nutrition.protein'),
                  current: _number(summary['protein_g']),
                  target: proteinTarget.toDouble(),
                  label:
                      '${_numberLabel(summary['protein_g'])} / $proteinTarget g',
                ),
                const SizedBox(height: 13),
                _ProgressLine(
                  title: t('nutrition.carbs'),
                  current: _number(summary['carbs_g']),
                  target: carbsTarget.toDouble(),
                  label: '${_numberLabel(summary['carbs_g'])} / $carbsTarget g',
                ),
                const SizedBox(height: 13),
                _ProgressLine(
                  title: t('nutrition.fat'),
                  current: _number(summary['fat_g']),
                  target: fatTarget.toDouble(),
                  label: '${_numberLabel(summary['fat_g'])} / $fatTarget g',
                ),
                const SizedBox(height: 13),
              ],
            ),
          ),
          const SizedBox(height: 14),
          if (!_truthy(imageAnalysis['available']) &&
              _text(imageAnalysis['access_reason']).isNotEmpty) ...[
            const SizedBox(height: 14),
            Text(
              _text(imageAnalysis['access_reason']),
              style: TextStyle(
                color: airmiusMutedColor(context),
                fontSize: 12,
                height: 1.35,
              ),
            ),
          ],
          if (!_truthy(micronutrientsAccess['available']) &&
              _text(micronutrientsAccess['access_reason']).isNotEmpty) ...[
            const SizedBox(height: 8),
            Text(
              _text(micronutrientsAccess['access_reason']),
              style: TextStyle(
                color: airmiusMutedColor(context),
                fontSize: 12,
                height: 1.35,
              ),
            ),
          ],
          const SizedBox(height: 14),
          _SectionHeading(title: t('nutrition.meals'), count: meals.length),
          const SizedBox(height: 10),
          if (meals.isEmpty)
            AirmiusPanel(
              child: Padding(
                padding: const EdgeInsets.all(12),
                child: Center(
                  child: Text(
                    t('nutrition.emptyMeals'),
                    textAlign: TextAlign.center,
                    style: TextStyle(color: airmiusMutedColor(context)),
                  ),
                ),
              ),
            )
          else
            for (final meal in meals) ...[
              _MealCard(
                meal: meal,
                mealTypeLabel: _catalogLabel(
                  context,
                  catalog['meal_types'],
                  _text(meal['meal_type']),
                  'nutrition.mealType',
                ),
                busy: _busy,
                onEdit: () => _editMeal(catalog: catalog, meal: meal),
                onDelete: () => _deleteMeal(meal),
              ),
              const SizedBox(height: 10),
            ],
          if (weekly.isNotEmpty) ...[
            const SizedBox(height: 4),
            _WeeklyPanel(days: weekly, calorieTarget: calorieTarget),
          ],
          if (tips.isNotEmpty || recipes.isNotEmpty) ...[
            const SizedBox(height: 14),
            _SuggestionsPanel(tips: tips, recipes: recipes),
          ],
        ],
      ],
    );
  }

  Future<void> _openCaptureMenu() async {
    final data = _dayData;
    if (data == null || _busy) return;

    final catalog = _map(data['catalog']);
    final goal = _map(data['goal']);
    final capabilities = _map(data['ai_capabilities']);
    final imageAnalysis = _map(capabilities['nutrition_image_analysis']);
    final photoAvailable = _truthy(imageAnalysis['available']);
    final action = await showModalBottomSheet<_NutritionCaptureAction>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      showDragHandle: true,
      builder: (context) => _NutritionCaptureSheet(
        photoAvailable: photoAvailable,
        photoAccessReason: _text(imageAnalysis['access_reason']),
      ),
    );
    if (!mounted || action == null) return;

    switch (action) {
      case _NutritionCaptureAction.meal:
        return _editMeal(catalog: catalog);
      case _NutritionCaptureAction.food:
        return _searchFood(catalog: catalog);
      case _NutritionCaptureAction.barcode:
        return _lookupBarcode(catalog: catalog);
      case _NutritionCaptureAction.photo:
        return _analyzeMealPhoto(
          catalog: catalog,
          goal: goal,
          capabilities: capabilities,
        );
    }
  }

  Future<void> _editMeal({
    required JsonMap catalog,
    JsonMap? meal,
    JsonMap? prefill,
  }) async {
    final payload = await showDialog<JsonMap>(
      context: context,
      builder: (_) => _MealEditorDialog(
        date: _isoDate(_selectedDate),
        meal: meal,
        prefill: prefill,
        mealTypes: _maps(catalog['meal_types']),
        sourceTypes: _maps(catalog['source_types']),
      ),
    );
    if (payload == null || !mounted) return;
    await _run(
      () async {
        final mealId = _integer(meal?['id']);
        if (mealId > 0) {
          await _client.updateNutritionMeal(mealId, payload);
        } else {
          await _client.createNutritionMeal(payload);
        }
      },
      successKey: meal == null
          ? 'nutrition.mealCreated'
          : 'nutrition.mealUpdated',
    );
  }

  Future<void> _editGoal({
    required JsonMap goal,
    required JsonMap catalog,
    bool focusWaterSettings = false,
  }) async {
    final payload = await showDialog<JsonMap>(
      context: context,
      builder: (_) => _GoalEditorDialog(
        goal: goal,
        goalTypes: _maps(catalog['goal_types']),
        dietStyles: _maps(catalog['diet_styles']),
        focusWaterSettings: focusWaterSettings,
      ),
    );
    if (payload == null || !mounted) return;
    var saved = false;
    await _run(() async {
      await _client.updateNutritionGoal(payload);
      saved = true;
    }, successKey: 'nutrition.goalUpdated');
    if (!saved ||
        !mounted ||
        _integer(payload['water_reminders_per_day']) == 0) {
      return;
    }
    final services = AirmiusServicesScope.of(context);
    if (await services.pushDevices.optInEnabled() || !mounted) return;
    final t = AirmiusScope.of(context).t;
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(
        SnackBar(
          content: Text(t('nutrition.waterPushHint')),
          action: SnackBarAction(
            label: t('nutrition.waterPushSettings'),
            onPressed: () => Navigator.of(context).push(
              MaterialPageRoute<void>(
                builder: (_) => const NotificationPreferencesScreen(),
              ),
            ),
          ),
        ),
      );
  }

  Future<void> _addWater() async {
    final amount = await showDialog<int>(
      context: context,
      builder: (_) => const _WaterDialog(),
    );
    if (amount == null || !mounted) return;
    await _logWaterAmount(amount);
  }

  Future<void> _logWaterAmount(int amount) async {
    if (amount <= 0 || _busy) return;
    final previousData = _dayData;
    final optimisticTotal =
        _integer(_map(previousData?['summary'])['water_ml']) + amount;
    final t = AirmiusScope.of(context).t;

    setState(() {
      _busy = true;
      if (previousData != null) {
        _dayData = _withWaterTotal(previousData, optimisticTotal);
      }
    });

    try {
      final response = await _client.logNutritionWater(
        date: _isoDate(_selectedDate),
        amountMl: amount,
      );
      if (!mounted) return;
      final data = _map(response['data']);
      final authoritativeTotal = _integer(
        data['water_total_ml'],
        fallback: optimisticTotal,
      );
      final entryId = _integer(data['id']);

      setState(() {
        final currentData = _dayData ?? previousData;
        if (currentData != null) {
          _dayData = _withWaterTotal(currentData, authoritativeTotal);
        }
      });

      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(t('nutrition.waterAdded')),
          duration: const Duration(seconds: 6),
          action: entryId > 0
              ? SnackBarAction(
                  label: t('nutrition.undo'),
                  onPressed: () =>
                      _undoWaterEntry(entryId: entryId, amount: amount),
                )
              : null,
        ),
      );
    } on AirmiusApiException catch (error) {
      if (!mounted) return;
      setState(() => _dayData = previousData);
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(error.userMessage)));
    } catch (_) {
      if (!mounted) return;
      setState(() => _dayData = previousData);
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(t('nutrition.saveError'))));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _undoWaterEntry({
    required int entryId,
    required int amount,
  }) async {
    if (_busy) return;
    final previousData = _dayData;
    final currentTotal = _integer(_map(previousData?['summary'])['water_ml']);
    final optimisticTotal = (currentTotal - amount).clamp(0, currentTotal);
    final t = AirmiusScope.of(context).t;

    setState(() {
      _busy = true;
      if (previousData != null) {
        _dayData = _withWaterTotal(previousData, optimisticTotal);
      }
    });

    try {
      await _client.deleteNutritionMeal(entryId);
      if (!mounted) return;
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(t('nutrition.waterRemoved'))));
    } on AirmiusApiException catch (error) {
      if (!mounted) return;
      setState(() => _dayData = previousData);
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(error.userMessage)));
    } catch (_) {
      if (!mounted) return;
      setState(() => _dayData = previousData);
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(t('nutrition.saveError'))));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _searchFood({required JsonMap catalog}) async {
    final food = await showDialog<JsonMap>(
      context: context,
      builder: (_) => _FoodSearchDialog(client: _client),
    );
    if (food == null || !mounted) return;
    await _editMeal(catalog: catalog, prefill: food);
  }

  Future<void> _lookupBarcode({required JsonMap catalog}) async {
    final food = await showDialog<JsonMap>(
      context: context,
      builder: (_) => _BarcodeDialog(client: _client),
    );
    if (food == null || !mounted) return;
    await _editMeal(catalog: catalog, prefill: {...food, 'source': 'barcode'});
  }

  Future<void> _analyzeMealPhoto({
    required JsonMap catalog,
    required JsonMap goal,
    required JsonMap capabilities,
  }) async {
    final t = AirmiusScope.of(context).t;
    final privacy = _map(capabilities['privacy']);
    final consent = await showDialog<JsonMap>(
      context: context,
      builder: (_) => _AiMealConsentDialog(
        mealTypes: _maps(catalog['meal_types']),
        maxImageKb: _integer(privacy['max_image_kb'], fallback: 5120),
        exifRemoved: _truthy(privacy['exif_removed']),
        storesUploads: _truthy(privacy['store_uploads']),
      ),
    );
    if (consent == null || !mounted) return;

    final file = await _pickMealImage();
    if (file == null || !mounted) return;
    final extension = _imageExtension(file);
    if (!const ['jpg', 'jpeg', 'png', 'webp'].contains(extension)) {
      _toast(t('nutrition.aiInvalidImage'));
      return;
    }
    final maxBytes = _integer(privacy['max_image_kb'], fallback: 5120) * 1024;
    if (file.size <= 0 || file.size > maxBytes) {
      _toast(
        t(
          'nutrition.aiImageTooLarge',
        ).replaceFirst('{size}', (maxBytes / 1024 / 1024).toStringAsFixed(0)),
      );
      return;
    }

    JsonMap? suggestion;
    setState(() => _busy = true);
    try {
      suggestion = await _uploadMealImage(
        file,
        mealType: _text(consent['meal_type']),
        dietStyle: _text(goal['diet_style']),
      );
    } on AirmiusApiException catch (error) {
      if (mounted) _toast(error.userMessage);
    } catch (error) {
      if (mounted) {
        _toast(
          '${t('nutrition.aiAnalysisError')} ${error is AirmiusApiException ? error.userMessage : t('common.errorDetails')}',
        );
      }
    } finally {
      if (mounted) setState(() => _busy = false);
    }
    if (suggestion == null || !mounted) return;

    final warnings = _strings(suggestion['warnings']);
    final notes = [
      _text(suggestion['notes']),
      if (warnings.isNotEmpty)
        '${t('nutrition.aiWarnings')}: ${warnings.join(' · ')}',
    ].where((value) => value.isNotEmpty).join('\n');
    await _editMeal(
      catalog: catalog,
      prefill: {
        ...suggestion,
        'meal_type': _text(consent['meal_type']),
        'source': 'photo_estimate',
        'notes': notes,
      },
    );
  }

  Future<PlatformFile?> _pickMealImage() async {
    final t = AirmiusScope.of(context).t;
    final source = await showModalBottomSheet<_MealImageSource>(
      context: context,
      backgroundColor: airmiusSurfaceColor(context),
      showDragHandle: true,
      builder: (context) => SafeArea(
        child: Padding(
          padding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text(
                t('nutrition.choosePhoto'),
                style: TextStyle(
                  color: airmiusTextColor(context),
                  fontSize: 20,
                  fontWeight: FontWeight.w900,
                ),
              ),
              const SizedBox(height: 12),
              _NutritionChoiceTile(
                icon: Icons.photo_camera_outlined,
                title: t('nutrition.takePhoto'),
                subtitle: t('permissions.cameraTitle'),
                onTap: () => Navigator.pop(context, _MealImageSource.camera),
              ),
              const SizedBox(height: 8),
              _NutritionChoiceTile(
                icon: Icons.photo_library_outlined,
                title: t('nutrition.choosePhoto'),
                subtitle: t('nutrition.image'),
                onTap: () => Navigator.pop(context, _MealImageSource.gallery),
              ),
            ],
          ),
        ),
      ),
    );
    if (source == null || !mounted) return null;

    if (source == _MealImageSource.gallery) {
      final picked = await FilePicker.platform.pickFiles(
        type: FileType.custom,
        allowedExtensions: const ['jpg', 'jpeg', 'png', 'webp'],
        allowMultiple: false,
        withData: true,
      );
      return picked?.files.single;
    }

    final picked = await ImagePicker().pickImage(
      source: ImageSource.camera,
      imageQuality: 88,
      maxWidth: 1800,
      maxHeight: 1800,
    );
    if (picked == null) return null;
    final bytes = await picked.readAsBytes();
    return PlatformFile(
      name: picked.name,
      size: bytes.length,
      bytes: bytes,
      path: picked.path,
    );
  }

  Future<JsonMap> _uploadMealImage(
    PlatformFile file, {
    required String mealType,
    required String dietStyle,
  }) async {
    final services = AirmiusServicesScope.of(context);
    final session = services.authState.session;
    if (session == null) {
      throw StateError(AirmiusScope.of(context).t('nutrition.noActiveSession'));
    }
    final base = Uri.parse(_client.baseUrl);
    final path =
        '${base.path.endsWith('/') ? base.path : '${base.path}/'}api/v1/nutrition/ai/meal-image';
    final request =
        http.MultipartRequest(
            'POST',
            base.replace(path: path, query: null, fragment: null),
          )
          ..headers['Authorization'] = 'Bearer ${session.token}'
          ..headers['Accept'] = 'application/json'
          ..headers['Accept-Language'] = _client.locale
          ..fields['ai_consent'] = '1'
          ..fields['meal_type'] = mealType
          ..fields['diet_style'] = dietStyle;
    final contentType = switch (_imageExtension(file)) {
      'png' => MediaType('image', 'png'),
      'webp' => MediaType('image', 'webp'),
      _ => MediaType('image', 'jpeg'),
    };
    if (file.bytes != null) {
      request.files.add(
        http.MultipartFile.fromBytes(
          'image',
          file.bytes!,
          filename: file.name,
          contentType: contentType,
        ),
      );
    } else if (file.path != null) {
      request.files.add(
        await http.MultipartFile.fromPath(
          'image',
          file.path!,
          filename: file.name,
          contentType: contentType,
        ),
      );
    } else {
      throw StateError(AirmiusScope.of(context).t('nutrition.imageUnreadable'));
    }
    final response = await http.Response.fromStream(await request.send());
    if (response.statusCode < 200 || response.statusCode >= 300) {
      throw AirmiusApiException(
        statusCode: response.statusCode,
        body: response.body,
        path: '/api/v1/nutrition/ai/meal-image',
      );
    }
    final decoded = jsonDecode(response.body);
    final json = decoded is Map
        ? decoded.map((key, value) => MapEntry('$key', value))
        : const <String, dynamic>{};
    return _map(json['data']);
  }

  Future<void> _deleteMeal(JsonMap meal) async {
    final t = AirmiusScope.of(context).t;
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        backgroundColor: airmiusSurfaceColor(context),
        title: Text(t('nutrition.deleteTitle')),
        content: Text(t('nutrition.deleteQuestion')),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext, false),
            child: Text(t('cancel')),
          ),
          FilledButton(
            style: FilledButton.styleFrom(
              backgroundColor: Theme.of(context).colorScheme.error,
            ),
            onPressed: () => Navigator.pop(dialogContext, true),
            child: Text(t('delete')),
          ),
        ],
      ),
    );
    if (confirmed != true || !mounted) return;
    final mealId = _integer(meal['id']);
    if (mealId <= 0 || _busy) return;

    final previousData = _dayData;
    setState(() {
      _busy = true;
      if (previousData != null) {
        _dayData = _withoutMeal(previousData, meal);
      }
    });

    try {
      await _client.deleteNutritionMeal(mealId);
      if (!mounted) return;
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(t('nutrition.mealDeleted'))));
    } on AirmiusApiException catch (error) {
      if (!mounted) return;
      setState(() => _dayData = previousData);
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(error.userMessage)));
    } catch (_) {
      if (!mounted) return;
      setState(() => _dayData = previousData);
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(t('nutrition.saveError'))));
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _run(
    Future<void> Function() operation, {
    required String successKey,
  }) async {
    final t = AirmiusScope.of(context).t;
    setState(() => _busy = true);
    try {
      await operation();
      if (!mounted) return;
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(t(successKey))));
      _reload();
    } on AirmiusApiException catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(error.userMessage)));
    } catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            '${t('nutrition.saveError')} ${error is AirmiusApiException ? error.userMessage : t('common.errorDetails')}',
          ),
        ),
      );
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  void _toast(String message) {
    ScaffoldMessenger.of(
      context,
    ).showSnackBar(SnackBar(content: Text(message)));
  }
}

class _AiMealConsentDialog extends StatefulWidget {
  const _AiMealConsentDialog({
    required this.mealTypes,
    required this.maxImageKb,
    required this.exifRemoved,
    required this.storesUploads,
  });

  final List<JsonMap> mealTypes;
  final int maxImageKb;
  final bool exifRemoved;
  final bool storesUploads;

  @override
  State<_AiMealConsentDialog> createState() => _AiMealConsentDialogState();
}

class _AiMealConsentDialogState extends State<_AiMealConsentDialog> {
  late String _mealType;
  bool _consent = false;

  @override
  void initState() {
    super.initState();
    _mealType = _firstKey(widget.mealTypes, 'snack');
  }

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return AlertDialog(
      title: Text(t('nutrition.aiConsentTitle')),
      content: SingleChildScrollView(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text(
              t('nutrition.aiConsentHint'),
              style: TextStyle(color: airmiusMutedColor(context), height: 1.4),
            ),
            const SizedBox(height: 14),
            DropdownButtonFormField<String>(
              initialValue: _mealType,
              decoration: InputDecoration(labelText: t('nutrition.mealType')),
              items: widget.mealTypes
                  .map(
                    (item) => DropdownMenuItem(
                      value: _text(item['key']),
                      child: Text(
                        _localizedCatalogLabel(
                          context,
                          item,
                          'nutrition.mealType',
                        ),
                      ),
                    ),
                  )
                  .toList(),
              onChanged: (value) {
                if (value != null) setState(() => _mealType = value);
              },
            ),
            const SizedBox(height: 14),
            _AiPrivacyLine(
              icon: Icons.hide_image_outlined,
              text: t(
                widget.storesUploads
                    ? 'nutrition.aiStored'
                    : 'nutrition.aiNotStored',
              ),
            ),
            _AiPrivacyLine(
              icon: Icons.location_off_outlined,
              text: t(
                widget.exifRemoved
                    ? 'nutrition.aiExifRemoved'
                    : 'nutrition.aiExifNotice',
              ),
            ),
            _AiPrivacyLine(
              icon: Icons.photo_size_select_large_outlined,
              text: t('nutrition.aiMaxSize').replaceFirst(
                '{size}',
                (widget.maxImageKb / 1024).toStringAsFixed(0),
              ),
            ),
            const SizedBox(height: 8),
            Material(
              color: Colors.transparent,
              child: CheckboxListTile(
                value: _consent,
                contentPadding: EdgeInsets.zero,
                controlAffinity: ListTileControlAffinity.leading,
                title: Text(
                  t('nutrition.aiConsentCheckbox'),
                  style: const TextStyle(fontWeight: FontWeight.w900),
                ),
                subtitle: Text(t('nutrition.aiEstimateNotice')),
                onChanged: (value) => setState(() => _consent = value == true),
              ),
            ),
          ],
        ),
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.pop(context),
          child: Text(t('cancel')),
        ),
        FilledButton.icon(
          onPressed: _consent
              ? () => Navigator.pop(context, {
                  'meal_type': _mealType,
                  'ai_consent': true,
                })
              : null,
          icon: const Icon(Icons.photo_library_outlined),
          label: Text(t('nutrition.choosePhoto')),
        ),
      ],
    );
  }
}

class _AiPrivacyLine extends StatelessWidget {
  const _AiPrivacyLine({required this.icon, required this.text});

  final IconData icon;
  final String text;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, color: Theme.of(context).colorScheme.secondary, size: 20),
          const SizedBox(width: 9),
          Expanded(
            child: Text(
              text,
              style: TextStyle(
                color: airmiusMutedColor(context),
                fontSize: 12,
                height: 1.35,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _NutritionCaptureSheet extends StatelessWidget {
  const _NutritionCaptureSheet({
    required this.photoAvailable,
    required this.photoAccessReason,
  });

  final bool photoAvailable;
  final String photoAccessReason;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return SingleChildScrollView(
      padding: EdgeInsets.fromLTRB(
        20,
        0,
        20,
        20 + MediaQuery.viewInsetsOf(context).bottom,
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        mainAxisSize: MainAxisSize.min,
        children: [
          Text(
            t('nutrition.captureTitle'),
            style: Theme.of(
              context,
            ).textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.w900),
          ),
          const SizedBox(height: 6),
          Text(
            t('nutrition.captureHint'),
            style: TextStyle(color: airmiusMutedColor(context), height: 1.35),
          ),
          const SizedBox(height: 18),
          _NutritionCaptureChoice(
            title: t('nutrition.addMeal'),
            subtitle: t('nutrition.addMealHint'),
            icon: Icons.restaurant_outlined,
            onTap: () => Navigator.pop(context, _NutritionCaptureAction.meal),
          ),
          const SizedBox(height: 10),
          _NutritionCaptureChoice(
            title: t('nutrition.searchFood'),
            subtitle: t('nutrition.searchFoodActionHint'),
            icon: Icons.search_outlined,
            onTap: () => Navigator.pop(context, _NutritionCaptureAction.food),
          ),
          const SizedBox(height: 10),
          _NutritionCaptureChoice(
            title: t('nutrition.barcode'),
            subtitle: t('nutrition.barcodeActionHint'),
            icon: Icons.qr_code_scanner_outlined,
            onTap: () =>
                Navigator.pop(context, _NutritionCaptureAction.barcode),
          ),
          const SizedBox(height: 10),
          _NutritionCaptureChoice(
            title: t(
              photoAvailable ? 'nutrition.aiPhoto' : 'nutrition.aiPhotoPro',
            ),
            subtitle: photoAvailable
                ? t('nutrition.aiPhotoActionHint')
                : photoAccessReason.isNotEmpty
                ? photoAccessReason
                : t('nutrition.aiPhotoProHint'),
            icon: photoAvailable
                ? Icons.auto_awesome_outlined
                : Icons.lock_outline,
            onTap: photoAvailable
                ? () => Navigator.pop(context, _NutritionCaptureAction.photo)
                : null,
          ),
        ],
      ),
    );
  }
}

class _NutritionCaptureChoice extends StatelessWidget {
  const _NutritionCaptureChoice({
    required this.title,
    required this.subtitle,
    required this.icon,
    required this.onTap,
  });

  final String title;
  final String subtitle;
  final IconData icon;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final enabled = onTap != null;
    final accent = airmiusAccentColor(context);
    final scheme = Theme.of(context).colorScheme;
    return Material(
      color: scheme.surfaceContainerHighest.withValues(
        alpha: enabled ? 0.72 : 0.38,
      ),
      borderRadius: BorderRadius.circular(18),
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 15),
          child: Row(
            children: [
              Container(
                width: 48,
                height: 48,
                decoration: BoxDecoration(
                  color: enabled
                      ? accent.withValues(alpha: 0.16)
                      : scheme.onSurface.withValues(alpha: 0.07),
                  borderRadius: BorderRadius.circular(15),
                ),
                child: Icon(
                  icon,
                  color: enabled ? accent : airmiusMutedColor(context),
                ),
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      title,
                      style: TextStyle(
                        fontWeight: FontWeight.w900,
                        fontSize: 16,
                        color: enabled
                            ? scheme.onSurface
                            : airmiusMutedColor(context),
                      ),
                    ),
                    const SizedBox(height: 3),
                    Text(
                      subtitle,
                      style: TextStyle(
                        color: airmiusMutedColor(context),
                        height: 1.3,
                        fontSize: 13,
                      ),
                    ),
                  ],
                ),
              ),
              if (enabled) ...[
                const SizedBox(width: 10),
                Icon(Icons.chevron_right, color: accent),
              ],
            ],
          ),
        ),
      ),
    );
  }
}

class _DateNavigator extends StatelessWidget {
  const _DateNavigator({
    required this.selectedDate,
    required this.onPrevious,
    required this.onNext,
  });

  final DateTime selectedDate;
  final VoidCallback? onPrevious;
  final VoidCallback? onNext;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final isToday = _dateOnly(
      selectedDate,
    ).isAtSameMomentAs(_dateOnly(DateTime.now()));
    return AirmiusPanel(
      child: Row(
        children: [
          IconButton(
            tooltip: t('nutrition.previousDay'),
            onPressed: onPrevious,
            icon: const Icon(Icons.chevron_left),
          ),
          Expanded(
            child: Column(
              children: [
                Text(
                  isToday ? t('nutrition.today') : _displayDate(selectedDate),
                  style: TextStyle(
                    color: airmiusTextColor(context),
                    fontSize: 18,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                Text(
                  t('nutrition.selectDayHint'),
                  style: TextStyle(
                    color: airmiusMutedColor(context),
                    fontSize: 12,
                  ),
                ),
              ],
            ),
          ),
          IconButton(
            tooltip: t('nutrition.nextDay'),
            onPressed: onNext,
            icon: const Icon(Icons.chevron_right),
          ),
        ],
      ),
    );
  }
}

class _MetricGrid extends StatelessWidget {
  const _MetricGrid({required this.values});

  final List<(String, String)> values;

  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(
      builder: (context, constraints) {
        final width = constraints.maxWidth >= 620
            ? (constraints.maxWidth - 30) / 4
            : (constraints.maxWidth - 10) / 2;
        return Wrap(
          spacing: 10,
          runSpacing: 10,
          children: [
            for (final value in values)
              SizedBox(
                width: width,
                child: MetricCard(value: value.$1, label: value.$2),
              ),
          ],
        );
      },
    );
  }
}

class _NutritionSectionTabs extends StatelessWidget {
  const _NutritionSectionTabs({required this.section, required this.onChanged});

  final NutritionSection section;
  final ValueChanged<NutritionSection> onChanged;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return SegmentedButton<NutritionSection>(
      segments: [
        ButtonSegment(
          value: NutritionSection.overview,
          icon: const Icon(Icons.restaurant_menu_outlined),
          label: Text(t('nutrition.title')),
        ),
        ButtonSegment(
          value: NutritionSection.drink,
          icon: const Icon(Icons.water_drop_outlined),
          label: Text(t('nutrition.drinkTab')),
        ),
      ],
      selected: {section},
      onSelectionChanged: (selection) => onChanged(selection.first),
    );
  }
}

class _NutritionChoiceTile extends StatelessWidget {
  const _NutritionChoiceTile({
    required this.icon,
    required this.title,
    required this.subtitle,
    required this.onTap,
  });

  final IconData icon;
  final String title;
  final String subtitle;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(14),
      child: Container(
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(
          color: airmiusSurfaceColor(context),
          borderRadius: BorderRadius.circular(14),
          border: Border.all(color: airmiusBorderColor(context)),
        ),
        child: Row(
          children: [
            Icon(icon, color: airmiusAccentColor(context)),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    title,
                    style: TextStyle(
                      color: airmiusTextColor(context),
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    subtitle,
                    style: TextStyle(
                      color: airmiusMutedColor(context),
                      fontSize: 12,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ],
              ),
            ),
            Icon(Icons.chevron_right, color: airmiusMutedColor(context)),
          ],
        ),
      ),
    );
  }
}

class _QuickWaterPanel extends StatelessWidget {
  const _QuickWaterPanel({
    required this.consumedMl,
    required this.targetMl,
    required this.amounts,
    required this.busy,
    required this.onAdd,
  });

  final int consumedMl;
  final int targetMl;
  final List<int> amounts;
  final bool busy;
  final ValueChanged<int> onAdd;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final progress = targetMl <= 0
        ? 0.0
        : (consumedMl / targetMl).clamp(0.0, 1.0);

    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: airmiusSurfaceColor(context).withValues(alpha: 0.7),
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: airmiusBorderColor(context)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              Container(
                height: 38,
                width: 38,
                decoration: BoxDecoration(
                  color: airmiusAccentColor(context).withValues(alpha: 0.14),
                  borderRadius: BorderRadius.circular(14),
                ),
                child: Icon(
                  Icons.water_drop_outlined,
                  color: airmiusAccentColor(context),
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      t('nutrition.addWater'),
                      style: TextStyle(
                        color: airmiusTextColor(context),
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    Text(
                      t('nutrition.waterProgress')
                          .replaceFirst('{current}', '$consumedMl')
                          .replaceFirst('{target}', '$targetMl'),
                      style: TextStyle(
                        color: airmiusMutedColor(context),
                        fontSize: 12,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          ClipRRect(
            borderRadius: BorderRadius.circular(99),
            child: LinearProgressIndicator(
              minHeight: 8,
              value: progress,
              backgroundColor: airmiusBorderColor(context),
              color: airmiusAccentColor(context),
            ),
          ),
          const SizedBox(height: 12),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              for (final amount in amounts)
                FilledButton.tonalIcon(
                  onPressed: busy ? null : () => onAdd(amount),
                  icon: const Icon(Icons.add, size: 18),
                  label: Text('$amount ml'),
                ),
            ],
          ),
        ],
      ),
    );
  }
}

class _ProgressLine extends StatelessWidget {
  const _ProgressLine({
    required this.title,
    required this.current,
    required this.target,
    required this.label,
  });

  final String title;
  final double current;
  final double target;
  final String label;

  @override
  Widget build(BuildContext context) {
    final progress = target <= 0 ? 0.0 : (current / target).clamp(0.0, 1.0);
    final progressColor = Theme.of(context).colorScheme.secondary;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Row(
          children: [
            Expanded(
              child: Text(
                title,
                style: TextStyle(
                  color: airmiusTextColor(context),
                  fontWeight: FontWeight.w900,
                ),
              ),
            ),
            Text(
              label,
              style: TextStyle(
                color: airmiusMutedColor(context),
                fontSize: 12,
                fontWeight: FontWeight.w700,
              ),
            ),
          ],
        ),
        const SizedBox(height: 7),
        ClipRRect(
          borderRadius: BorderRadius.circular(99),
          child: LinearProgressIndicator(
            value: progress,
            minHeight: 9,
            backgroundColor: airmiusSurfaceSoftColor(context),
            valueColor: AlwaysStoppedAnimation<Color>(progressColor),
          ),
        ),
      ],
    );
  }
}

class _SectionHeading extends StatelessWidget {
  const _SectionHeading({required this.title, required this.count});

  final String title;
  final int count;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Expanded(
          child: Text(
            title,
            style: TextStyle(
              color: airmiusTextColor(context),
              fontSize: 19,
              fontWeight: FontWeight.w900,
            ),
          ),
        ),
        StatusPill('$count'),
      ],
    );
  }
}

class _MealCard extends StatelessWidget {
  const _MealCard({
    required this.meal,
    required this.mealTypeLabel,
    required this.busy,
    required this.onEdit,
    required this.onDelete,
  });

  final JsonMap meal;
  final String mealTypeLabel;
  final bool busy;
  final VoidCallback onEdit;
  final VoidCallback onDelete;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final waterMl = _integer(meal['water_ml']);
    final micronutrients = _mealMicronutrients(meal).take(6).toList();
    return AirmiusPanel(
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 50,
            height: 50,
            decoration: BoxDecoration(
              color: airmiusSurfaceSoftColor(context),
              borderRadius: BorderRadius.circular(16),
              border: Border.all(color: airmiusBorderColor(context)),
            ),
            child: Icon(
              _mealIcon(_text(meal['meal_type'])),
              color: waterMl > 0
                  ? airmiusAccentColor(context)
                  : Theme.of(context).colorScheme.secondary,
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  _text(meal['title'], fallback: t('nutrition.untitledMeal')),
                  style: TextStyle(
                    color: airmiusTextColor(context),
                    fontSize: 16,
                    fontWeight: FontWeight.w900,
                  ),
                ),
                const SizedBox(height: 4),
                Wrap(
                  spacing: 7,
                  runSpacing: 7,
                  children: [
                    StatusPill(mealTypeLabel),
                    if (_integer(meal['calories']) > 0)
                      StatusPill(
                        '${_integer(meal['calories'])} kcal',
                        color: Theme.of(context).colorScheme.secondary,
                      ),
                    if (waterMl > 0)
                      StatusPill(
                        '$waterMl ml',
                        color: airmiusAccentColor(context),
                      ),
                  ],
                ),
                if (_text(meal['notes']).isNotEmpty) ...[
                  const SizedBox(height: 8),
                  Text(
                    _text(meal['notes']),
                    style: TextStyle(
                      color: airmiusMutedColor(context),
                      height: 1.3,
                    ),
                  ),
                ],
                if (_integer(meal['calories']) > 0) ...[
                  const SizedBox(height: 8),
                  Text(
                    '${t('nutrition.protein')} ${_numberLabel(meal['protein_g'])} g · '
                    '${t('nutrition.carbs')} ${_numberLabel(meal['carbs_g'])} g · '
                    '${t('nutrition.fat')} ${_numberLabel(meal['fat_g'])} g',
                    style: TextStyle(
                      color: airmiusMutedColor(context),
                      fontSize: 12,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ],
                if (micronutrients.isNotEmpty) ...[
                  const SizedBox(height: 8),
                  Wrap(
                    spacing: 6,
                    runSpacing: 6,
                    children: [
                      for (final item in micronutrients)
                        StatusPill(
                          '${_text(item['label'])} ${_numberLabel(item['amount'])} ${_text(item['unit'])}',
                          color: airmiusAccentColor(context),
                        ),
                    ],
                  ),
                ],
              ],
            ),
          ),
          PopupMenuButton<String>(
            tooltip: t('nutrition.mealActions'),
            enabled: !busy,
            onSelected: (value) => value == 'edit' ? onEdit() : onDelete(),
            itemBuilder: (_) => [
              PopupMenuItem(
                value: 'edit',
                child: ListTile(
                  contentPadding: EdgeInsets.zero,
                  leading: const Icon(Icons.edit_outlined),
                  title: Text(t('edit')),
                ),
              ),
              PopupMenuItem(
                value: 'delete',
                child: ListTile(
                  contentPadding: EdgeInsets.zero,
                  leading: Icon(
                    Icons.delete_outline,
                    color: Theme.of(context).colorScheme.error,
                  ),
                  title: Text(t('delete')),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _WeeklyPanel extends StatelessWidget {
  const _WeeklyPanel({required this.days, required this.calorieTarget});

  final List<JsonMap> days;
  final int calorieTarget;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return AirmiusPanel(
      title: t('nutrition.week'),
      child: SizedBox(
        height: 120,
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.end,
          children: [
            for (final day in days)
              Expanded(
                child: Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 3),
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.end,
                    children: [
                      Text(
                        '${_integer(day['calories'])}',
                        style: TextStyle(
                          color: airmiusMutedColor(context),
                          fontSize: 10,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                      const SizedBox(height: 5),
                      Flexible(
                        child: FractionallySizedBox(
                          heightFactor: calorieTarget <= 0
                              ? 0
                              : (_number(day['calories']) / calorieTarget)
                                    .clamp(0.05, 1.0),
                          child: Container(
                            decoration: BoxDecoration(
                              color: Theme.of(
                                context,
                              ).colorScheme.secondary.withValues(alpha: .75),
                              borderRadius: BorderRadius.circular(8),
                            ),
                          ),
                        ),
                      ),
                      const SizedBox(height: 5),
                      Text(
                        _text(day['label']),
                        style: TextStyle(
                          color: airmiusTextColor(context),
                          fontSize: 11,
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                    ],
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }
}

class _SuggestionsPanel extends StatelessWidget {
  const _SuggestionsPanel({required this.tips, required this.recipes});

  final List<String> tips;
  final List<JsonMap> recipes;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return AirmiusPanel(
      title: t('nutrition.suggestions'),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          for (final tip in tips.take(2))
            _SuggestionLine(icon: Icons.lightbulb_outline, text: tip),
          for (final recipe in recipes.take(2))
            _SuggestionLine(
              icon: Icons.menu_book_outlined,
              text: _text(
                recipe['title'] ?? recipe['name'],
                fallback: t('nutrition.recipe'),
              ),
            ),
        ],
      ),
    );
  }
}

class _SuggestionLine extends StatelessWidget {
  const _SuggestionLine({required this.icon, required this.text});

  final IconData icon;
  final String text;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(top: 9),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, color: Theme.of(context).colorScheme.tertiary, size: 20),
          const SizedBox(width: 10),
          Expanded(
            child: Text(
              text,
              style: TextStyle(color: airmiusMutedColor(context), height: 1.35),
            ),
          ),
        ],
      ),
    );
  }
}

class _MealEditorDialog extends StatefulWidget {
  const _MealEditorDialog({
    required this.date,
    required this.mealTypes,
    required this.sourceTypes,
    this.meal,
    this.prefill,
  });

  final String date;
  final List<JsonMap> mealTypes;
  final List<JsonMap> sourceTypes;
  final JsonMap? meal;
  final JsonMap? prefill;

  @override
  State<_MealEditorDialog> createState() => _MealEditorDialogState();
}

class _MealEditorDialogState extends State<_MealEditorDialog> {
  late final TextEditingController _title;
  late final TextEditingController _amount;
  late final TextEditingController _calories;
  late final TextEditingController _protein;
  late final TextEditingController _carbs;
  late final TextEditingController _fat;
  late final TextEditingController _fiber;
  late final TextEditingController _sugar;
  late final TextEditingController _water;
  late final TextEditingController _notes;
  late final List<JsonMap> _items;
  late final bool _hasPrefillAmount;
  late final bool _prefillIsDrink;
  late final String _prefillAmountUnit;
  late final double _prefillBaseAmount;
  late final Map<String, double> _prefillBaseNutrition;
  late String _mealType;
  late String _source;
  String? _error;
  bool _updatingAmount = false;

  @override
  void initState() {
    super.initState();
    final values = widget.meal ?? widget.prefill ?? const {};
    _title = TextEditingController(
      text: _text(values['title'] ?? values['name'] ?? values['product_name']),
    );
    _hasPrefillAmount = widget.meal == null && widget.prefill != null;
    _prefillIsDrink = _isDrinkProduct(values);
    _prefillAmountUnit = _prefillIsDrink ? 'ml' : 'g';
    _prefillBaseAmount = _servingAmount(values, _prefillAmountUnit);
    _prefillBaseNutrition = {
      'calories': _number(
        values['calories'] ??
            values['energy_kcal'] ??
            values['energy_kcal_serving'],
      ),
      'protein_g': _number(
        values['protein_g'] ?? values['proteins'] ?? values['proteins_serving'],
      ),
      'carbs_g': _number(
        values['carbs_g'] ??
            values['carbohydrates'] ??
            values['carbohydrates_serving'],
      ),
      'fat_g': _number(
        values['fat_g'] ?? values['fat'] ?? values['fat_serving'],
      ),
      'fiber_g': _number(values['fiber_g']),
      'sugar_g': _number(values['sugar_g']),
      'water_ml': _number(values['water_ml']),
    };
    _amount = TextEditingController(
      text: _hasPrefillAmount ? _inputNumber(_prefillBaseAmount) : '',
    );
    _calories = TextEditingController(
      text: _inputNumber(
        values['calories'] ??
            values['energy_kcal'] ??
            values['energy_kcal_serving'],
      ),
    );
    _protein = TextEditingController(
      text: _inputNumber(
        values['protein_g'] ?? values['proteins'] ?? values['proteins_serving'],
      ),
    );
    _carbs = TextEditingController(
      text: _inputNumber(
        values['carbs_g'] ??
            values['carbohydrates'] ??
            values['carbohydrates_serving'],
      ),
    );
    _fat = TextEditingController(
      text: _inputNumber(
        values['fat_g'] ?? values['fat'] ?? values['fat_serving'],
      ),
    );
    _fiber = TextEditingController(text: _inputNumber(values['fiber_g']));
    _sugar = TextEditingController(text: _inputNumber(values['sugar_g']));
    _water = TextEditingController(text: _inputNumber(values['water_ml']));
    _notes = TextEditingController(text: _text(values['notes']));
    _items = _maps(values['items']);
    _mealType = _text(
      values['meal_type'],
      fallback: _hasPrefillAmount && _prefillIsDrink
          ? 'drink'
          : _firstKey(widget.mealTypes, 'snack'),
    );
    _source = widget.prefill == null
        ? _text(
            values['source'],
            fallback: _firstKey(widget.sourceTypes, 'manual'),
          )
        : _sourceForPrefill(widget.prefill!);
    if (_hasPrefillAmount) {
      _amount.addListener(_applyPrefillAmount);
      _applyPrefillAmount();
    }
  }

  @override
  void dispose() {
    _title.dispose();
    _amount.dispose();
    _calories.dispose();
    _protein.dispose();
    _carbs.dispose();
    _fat.dispose();
    _fiber.dispose();
    _sugar.dispose();
    _water.dispose();
    _notes.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return AlertDialog(
      backgroundColor: airmiusSurfaceColor(context),
      title: Text(
        t(widget.meal == null ? 'nutrition.addMeal' : 'nutrition.editMeal'),
      ),
      content: SizedBox(
        width: 520,
        child: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              if (_source == 'photo_estimate') ...[
                AirmiusPanel(
                  borderColor: Theme.of(
                    context,
                  ).colorScheme.tertiary.withValues(alpha: .5),
                  child: Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Icon(
                        Icons.fact_check_outlined,
                        color: Theme.of(context).colorScheme.tertiary,
                      ),
                      const SizedBox(width: 10),
                      Expanded(
                        child: Text(
                          t('nutrition.aiReviewHint'),
                          style: TextStyle(
                            color: airmiusMutedColor(context),
                            height: 1.35,
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 12),
              ],
              TextField(
                controller: _title,
                autofocus: true,
                maxLength: 160,
                decoration: InputDecoration(
                  labelText: t('nutrition.mealTitle'),
                  errorText: _error,
                ),
                onChanged: (_) {
                  if (_error != null) setState(() => _error = null);
                },
              ),
              const SizedBox(height: 10),
              DropdownButtonFormField<String>(
                initialValue: _mealType,
                decoration: InputDecoration(labelText: t('nutrition.mealType')),
                items: widget.mealTypes
                    .map(
                      (item) => DropdownMenuItem(
                        value: _text(item['key']),
                        child: Text(
                          _localizedCatalogLabel(
                            context,
                            item,
                            'nutrition.mealType',
                          ),
                        ),
                      ),
                    )
                    .toList(),
                onChanged: (value) {
                  if (value != null) setState(() => _mealType = value);
                },
              ),
              const SizedBox(height: 10),
              if (_hasPrefillAmount) ...[
                _NumberField(
                  controller: _amount,
                  label: t('nutrition.consumedAmount'),
                  suffixText: _prefillAmountUnit,
                ),
                const SizedBox(height: 8),
                Text(
                  t('nutrition.consumedAmountHint').replaceFirst(
                    '{unit}',
                    _prefillAmountUnit,
                  ),
                  style: TextStyle(
                    color: airmiusMutedColor(context),
                    fontSize: 12,
                    height: 1.35,
                  ),
                ),
                const SizedBox(height: 10),
              ],
              _NumberField(
                controller: _calories,
                label: '${t('nutrition.calories')} (kcal)',
                integer: true,
              ),
              const SizedBox(height: 10),
              Row(
                children: [
                  Expanded(
                    child: _NumberField(
                      controller: _protein,
                      label: '${t('nutrition.protein')} (g)',
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: _NumberField(
                      controller: _carbs,
                      label: '${t('nutrition.carbs')} (g)',
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 10),
              Row(
                children: [
                  Expanded(
                    child: _NumberField(
                      controller: _fiber,
                      label: '${t('nutrition.fiber')} (g)',
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: _NumberField(
                      controller: _sugar,
                      label: '${t('nutrition.sugar')} (g)',
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 10),
              Row(
                children: [
                  Expanded(
                    child: _NumberField(
                      controller: _fat,
                      label: '${t('nutrition.fat')} (g)',
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: _NumberField(
                      controller: _water,
                      label: '${t('nutrition.water')} (ml)',
                      integer: true,
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 10),
              TextField(
                controller: _notes,
                minLines: 2,
                maxLines: 4,
                maxLength: 1200,
                decoration: InputDecoration(labelText: t('nutrition.notes')),
              ),
            ],
          ),
        ),
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.pop(context),
          child: Text(t('cancel')),
        ),
        FilledButton.icon(
          onPressed: _submit,
          icon: const Icon(Icons.save_outlined),
          label: Text(t('save')),
        ),
      ],
    );
  }

  void _submit() {
    final title = _title.text.trim();
    if (title.isEmpty) {
      setState(
        () => _error = AirmiusScope.of(context).t('nutrition.titleRequired'),
      );
      return;
    }
    Navigator.pop(context, <String, dynamic>{
      'eaten_on': widget.date,
      'meal_type': _mealType,
      'title': title,
      'calories': _integerFromInput(_calories.text),
      'protein_g': _numberFromInput(_protein.text),
      'carbs_g': _numberFromInput(_carbs.text),
      'fat_g': _numberFromInput(_fat.text),
      'fiber_g': _numberFromInput(_fiber.text),
      'sugar_g': _numberFromInput(_sugar.text),
      'water_ml': _integerFromInput(_water.text),
      'source': _source,
      'items': _hasPrefillAmount
          ? [
              ..._items,
              _prefillItem(title),
            ]
          : _items,
      'notes': _notes.text.trim().isEmpty ? null : _notes.text.trim(),
    });
  }

  JsonMap _prefillItem(String title) {
    final amount = _numberFromInput(_amount.text);
    final factor = _prefillBaseAmount > 0 && amount > 0
        ? amount / _prefillBaseAmount
        : 1.0;

    return {
      'name': title,
      'amount':
          '${_numberLabel(_numberFromInput(_amount.text))} $_prefillAmountUnit',
      'micronutrients': _scaledMicronutrients(
        _maps(widget.prefill?['micronutrients']),
        factor,
      ),
    };
  }

  List<JsonMap> _scaledMicronutrients(List<JsonMap> items, double factor) =>
      items
          .map((item) {
            final amount = _number(item['amount']) * factor;
            if (amount <= 0) return const <String, dynamic>{};

            return {
              'key': _text(item['key']),
              'label': _text(item['label']),
              'amount': (amount * 100).round() / 100,
              'unit': _text(item['unit']),
            };
          })
          .where((item) => item.isNotEmpty)
          .toList();

  void _applyPrefillAmount() {
    if (!_hasPrefillAmount || _updatingAmount) return;
    final amount = _numberFromInput(_amount.text);
    if (amount <= 0 || _prefillBaseAmount <= 0) return;
    final factor = amount / _prefillBaseAmount;
    _updatingAmount = true;
    _setScaled(_calories, _prefillBaseNutrition['calories'] ?? 0, factor, true);
    _setScaled(_protein, _prefillBaseNutrition['protein_g'] ?? 0, factor);
    _setScaled(_carbs, _prefillBaseNutrition['carbs_g'] ?? 0, factor);
    _setScaled(_fat, _prefillBaseNutrition['fat_g'] ?? 0, factor);
    _setScaled(_fiber, _prefillBaseNutrition['fiber_g'] ?? 0, factor);
    _setScaled(_sugar, _prefillBaseNutrition['sugar_g'] ?? 0, factor);
    if (_prefillIsDrink) {
      _water.text = _inputNumber(amount.round());
    } else {
      _setScaled(_water, _prefillBaseNutrition['water_ml'] ?? 0, factor, true);
    }
    _updatingAmount = false;
  }

  void _setScaled(
    TextEditingController controller,
    double base,
    double factor, [
    bool integer = false,
  ]) {
    if (base <= 0) return;
    final value = base * factor;
    if (integer) {
      controller.text = '${value.round()}';
      return;
    }
    final roundedToTenth = (value * 10).roundToDouble() / 10;
    controller.text = roundedToTenth == value.roundToDouble()
        ? '${value.round()}'
        : value.toStringAsFixed(1);
  }
}

class _GoalEditorDialog extends StatefulWidget {
  const _GoalEditorDialog({
    required this.goal,
    required this.goalTypes,
    required this.dietStyles,
    this.focusWaterSettings = false,
  });

  final JsonMap goal;
  final List<JsonMap> goalTypes;
  final List<JsonMap> dietStyles;
  final bool focusWaterSettings;

  @override
  State<_GoalEditorDialog> createState() => _GoalEditorDialogState();
}

class _GoalEditorDialogState extends State<_GoalEditorDialog> {
  late String _goalType;
  late String _dietStyle;
  late String _waterMode;
  late int _waterReminders;
  late int _waterReminderStart;
  late int _waterReminderEnd;
  late final TextEditingController _calories;
  late final TextEditingController _protein;
  late final TextEditingController _carbs;
  late final TextEditingController _fat;
  late final TextEditingController _water;
  late final TextEditingController _weight;

  @override
  void initState() {
    super.initState();
    _goalType = _text(
      widget.goal['goal_type'],
      fallback: _firstKey(widget.goalTypes, 'maintain'),
    );
    _dietStyle = _text(
      widget.goal['diet_style'],
      fallback: _firstKey(widget.dietStyles, 'balanced'),
    );
    _waterMode = _text(widget.goal['water_target_mode'], fallback: 'manual');
    _waterReminders = _integer(
      widget.goal['water_reminders_per_day'],
    ).clamp(0, 3);
    _waterReminderStart = _integer(
      widget.goal['water_reminder_start_hour'],
      fallback: 10,
    ).clamp(8, 12);
    _waterReminderEnd = _integer(
      widget.goal['water_reminder_end_hour'],
      fallback: 16,
    ).clamp(15, 21);
    _calories = TextEditingController(
      text: _inputNumber(widget.goal['daily_calories_target']),
    );
    _protein = TextEditingController(
      text: _inputNumber(widget.goal['protein_target_g']),
    );
    _carbs = TextEditingController(
      text: _inputNumber(widget.goal['carbs_target_g']),
    );
    _fat = TextEditingController(
      text: _inputNumber(widget.goal['fat_target_g']),
    );
    _water = TextEditingController(
      text: _inputNumber(widget.goal['water_target_ml']),
    );
    _weight = TextEditingController(
      text: _inputNumber(widget.goal['body_weight_kg']),
    );
  }

  @override
  void dispose() {
    _calories.dispose();
    _protein.dispose();
    _carbs.dispose();
    _fat.dispose();
    _water.dispose();
    _weight.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return AlertDialog(
      backgroundColor: airmiusSurfaceColor(context),
      title: Text(
        t(
          widget.focusWaterSettings
              ? 'nutrition.waterSettings'
              : 'nutrition.editTargets',
        ),
      ),
      content: SizedBox(
        width: 520,
        child: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              if (!widget.focusWaterSettings) ...[
                DropdownButtonFormField<String>(
                  isExpanded: true,
                  initialValue: _goalType,
                  decoration: InputDecoration(
                    labelText: t('nutrition.goalType'),
                  ),
                  items: widget.goalTypes
                      .map(
                        (item) => DropdownMenuItem(
                          value: _text(item['key']),
                          child: Text(
                            _localizedCatalogLabel(
                              context,
                              item,
                              'nutrition.goal',
                            ),
                          ),
                        ),
                      )
                      .toList(),
                  onChanged: (value) {
                    if (value != null) setState(() => _goalType = value);
                  },
                ),
                const SizedBox(height: 10),
                DropdownButtonFormField<String>(
                  isExpanded: true,
                  initialValue: _dietStyle,
                  decoration: InputDecoration(
                    labelText: t('nutrition.dietStyle'),
                  ),
                  items: widget.dietStyles
                      .map(
                        (item) => DropdownMenuItem(
                          value: _text(item['key']),
                          child: Text(
                            _localizedCatalogLabel(
                              context,
                              item,
                              'nutrition.diet',
                            ),
                          ),
                        ),
                      )
                      .toList(),
                  onChanged: (value) {
                    if (value != null) setState(() => _dietStyle = value);
                  },
                ),
                const SizedBox(height: 10),
                _NumberField(
                  controller: _calories,
                  label: '${t('nutrition.calories')} (kcal)',
                  integer: true,
                ),
                const SizedBox(height: 10),
                _NumberField(
                  controller: _protein,
                  label: '${t('nutrition.protein')} (g)',
                  integer: true,
                ),
                const SizedBox(height: 10),
                _NumberField(
                  controller: _carbs,
                  label: '${t('nutrition.carbs')} (g)',
                  integer: true,
                ),
                const SizedBox(height: 10),
                _NumberField(
                  controller: _fat,
                  label: '${t('nutrition.fat')} (g)',
                  integer: true,
                ),
                const SizedBox(height: 10),
              ],
              if (widget.focusWaterSettings) ...[
                DropdownButtonFormField<String>(
                  isExpanded: true,
                  initialValue: _waterMode,
                  decoration: InputDecoration(
                    labelText: t('nutrition.waterMode'),
                  ),
                  items: [
                    DropdownMenuItem(
                      value: 'auto',
                      child: Text(t('nutrition.waterAuto')),
                    ),
                    DropdownMenuItem(
                      value: 'manual',
                      child: Text(t('nutrition.waterManual')),
                    ),
                  ],
                  onChanged: (value) {
                    if (value != null) setState(() => _waterMode = value);
                  },
                ),
                const SizedBox(height: 10),
                if (_waterMode == 'manual')
                  _NumberField(
                    controller: _water,
                    label: '${t('nutrition.waterTarget')} (ml)',
                    integer: true,
                  )
                else ...[
                  _NumberField(
                    controller: _weight,
                    label: '${t('nutrition.bodyWeight')} (kg)',
                  ),
                  const SizedBox(height: 8),
                  Text(
                    t('nutrition.waterEstimate'),
                    style: Theme.of(context).textTheme.bodySmall,
                  ),
                ],
                const SizedBox(height: 14),
                SwitchListTile.adaptive(
                  contentPadding: EdgeInsets.zero,
                  title: Text(t('nutrition.waterReminders')),
                  value: _waterReminders > 0,
                  onChanged: (enabled) =>
                      setState(() => _waterReminders = enabled ? 2 : 0),
                ),
                if (_waterReminders > 0) ...[
                  DropdownButtonFormField<int>(
                    isExpanded: true,
                    initialValue: _waterReminders,
                    decoration: InputDecoration(
                      labelText: t('nutrition.waterReminders'),
                    ),
                    items: [
                      DropdownMenuItem(
                        value: 1,
                        child: Text(t('nutrition.waterRemindersOnce')),
                      ),
                      DropdownMenuItem(
                        value: 2,
                        child: Text(t('nutrition.waterRemindersTwice')),
                      ),
                      DropdownMenuItem(
                        value: 3,
                        child: Text(t('nutrition.waterRemindersThrice')),
                      ),
                    ],
                    onChanged: (value) {
                      if (value != null) {
                        setState(() => _waterReminders = value);
                      }
                    },
                  ),
                  const SizedBox(height: 10),
                  DropdownButtonFormField<int>(
                    isExpanded: true,
                    initialValue: _waterReminderStart,
                    decoration: InputDecoration(
                      labelText: t('nutrition.waterReminderStart'),
                    ),
                    items: [
                      for (var hour = 8; hour <= 12; hour++)
                        DropdownMenuItem(
                          value: hour,
                          child: Text('${hour.toString().padLeft(2, '0')}:00'),
                        ),
                    ],
                    onChanged: (value) {
                      if (value != null) {
                        setState(() => _waterReminderStart = value);
                      }
                    },
                  ),
                  const SizedBox(height: 10),
                  DropdownButtonFormField<int>(
                    isExpanded: true,
                    initialValue: _waterReminderEnd,
                    decoration: InputDecoration(
                      labelText: t('nutrition.waterReminderEnd'),
                    ),
                    items: [
                      for (var hour = 15; hour <= 21; hour++)
                        DropdownMenuItem(
                          value: hour,
                          child: Text('${hour.toString().padLeft(2, '0')}:00'),
                        ),
                    ],
                    onChanged: (value) {
                      if (value != null) {
                        setState(() => _waterReminderEnd = value);
                      }
                    },
                  ),
                ],
                const SizedBox(height: 6),
                Text(
                  t('nutrition.waterReminderHint'),
                  style: Theme.of(context).textTheme.bodySmall,
                ),
                if (_waterReminders > 0)
                  Text(
                    t('nutrition.waterPushHint'),
                    style: Theme.of(context).textTheme.bodySmall,
                  ),
              ],
            ],
          ),
        ),
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.pop(context),
          child: Text(t('cancel')),
        ),
        FilledButton.icon(
          onPressed: () => Navigator.pop(context, <String, dynamic>{
            'goal_type': _goalType,
            'diet_style': _dietStyle,
            'daily_calories_target': _integerFromInput(_calories.text),
            'protein_target_g': _integerFromInput(_protein.text),
            'carbs_target_g': _integerFromInput(_carbs.text),
            'fat_target_g': _integerFromInput(_fat.text),
            'water_target_ml': _integerFromInput(_water.text),
            'body_weight_kg': _nullableNumberFromInput(_weight.text),
            'water_target_mode': _waterMode,
            'water_reminders_per_day': _waterReminders,
            'water_reminder_start_hour': _waterReminderStart,
            'water_reminder_end_hour': _waterReminderEnd,
            'water_reminder_timezone': _waterReminderTimezone(),
          }),
          icon: const Icon(Icons.save_outlined),
          label: Text(t('save')),
        ),
      ],
    );
  }
}

String _waterReminderTimezone() {
  final minutes = DateTime.now().timeZoneOffset.inMinutes;
  final absolute = minutes.abs();
  return '${minutes < 0 ? '-' : '+'}${(absolute ~/ 60).toString().padLeft(2, '0')}:${(absolute % 60).toString().padLeft(2, '0')}';
}

class _WaterDialog extends StatefulWidget {
  const _WaterDialog();

  @override
  State<_WaterDialog> createState() => _WaterDialogState();
}

class _WaterDialogState extends State<_WaterDialog> {
  final _amount = TextEditingController(text: '250');

  @override
  void dispose() {
    _amount.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return AlertDialog(
      backgroundColor: airmiusSurfaceColor(context),
      title: Text(t('nutrition.addWater')),
      content: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Wrap(
            spacing: 8,
            children: [
              for (final amount in [250, 500, 750])
                ActionChip(
                  label: Text('$amount ml'),
                  onPressed: () => setState(() => _amount.text = '$amount'),
                ),
            ],
          ),
          const SizedBox(height: 12),
          _NumberField(
            controller: _amount,
            label: t('nutrition.waterAmount'),
            integer: true,
          ),
        ],
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.pop(context),
          child: Text(t('cancel')),
        ),
        FilledButton.icon(
          onPressed: () {
            final amount = _integerFromInput(_amount.text);
            if (amount > 0) Navigator.pop(context, amount);
          },
          icon: const Icon(Icons.water_drop_outlined),
          label: Text(t('nutrition.add')),
        ),
      ],
    );
  }
}

class _FoodSearchDialog extends StatefulWidget {
  const _FoodSearchDialog({required this.client});

  final AirmiusApiClient client;

  @override
  State<_FoodSearchDialog> createState() => _FoodSearchDialogState();
}

class _FoodSearchDialogState extends State<_FoodSearchDialog> {
  final _query = TextEditingController();
  List<JsonMap> _results = const [];
  bool _loading = false;
  String? _error;

  @override
  void dispose() {
    _query.dispose();
    super.dispose();
  }

  Future<void> _search() async {
    if (_query.text.trim().length < 2) return;
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final json = await widget.client.searchNutritionFoods(_query.text.trim());
      if (!mounted) return;
      setState(() => _results = _maps(json['data']));
    } catch (error) {
      if (!mounted) return;
      setState(
        () => _error = error is AirmiusApiException
            ? error.userMessage
            : AirmiusScope.of(context).t('common.errorDetails'),
      );
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return AlertDialog(
      backgroundColor: airmiusSurfaceColor(context),
      title: Text(t('nutrition.searchFood')),
      content: SizedBox(
        width: 560,
        height: 430,
        child: Column(
          children: [
            TextField(
              controller: _query,
              autofocus: true,
              textInputAction: TextInputAction.search,
              onSubmitted: (_) => _search(),
              decoration: InputDecoration(
                labelText: t('nutrition.foodQuery'),
                suffixIcon: IconButton(
                  tooltip: t('search.retry'),
                  onPressed: _loading ? null : _search,
                  icon: const Icon(Icons.search),
                ),
              ),
            ),
            const SizedBox(height: 12),
            if (_loading) const LinearProgressIndicator(),
            if (_error != null)
              Padding(
                padding: const EdgeInsets.all(12),
                child: Text(
                  _error!,
                  style: TextStyle(color: Theme.of(context).colorScheme.error),
                ),
              ),
            Expanded(
              child: _results.isEmpty && !_loading
                  ? Center(
                      child: Text(
                        t('nutrition.foodSearchHint'),
                        textAlign: TextAlign.center,
                        style: TextStyle(color: airmiusMutedColor(context)),
                      ),
                    )
                  : ListView.separated(
                      itemCount: _results.length,
                      separatorBuilder: (_, _) => const Divider(),
                      itemBuilder: (context, index) {
                        final food = _results[index];
                        final micronutrients = _maps(food['micronutrients']);

                        return ListTile(
                          contentPadding: EdgeInsets.zero,
                          leading: Icon(
                            Icons.restaurant_outlined,
                            color: Theme.of(context).colorScheme.secondary,
                          ),
                          title: Text(
                            _foodName(food),
                            style: const TextStyle(fontWeight: FontWeight.w900),
                          ),
                          subtitle: Text(
                            [
                              _foodMeta(food),
                              if (micronutrients.isNotEmpty)
                                t('nutrition.micronutrientsIncluded'),
                            ].where((item) => item.isNotEmpty).join(' · '),
                          ),
                          isThreeLine: micronutrients.isNotEmpty,
                          trailing: const Icon(Icons.chevron_right),
                          onTap: () => Navigator.pop(context, food),
                        );
                      },
                    ),
            ),
          ],
        ),
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.pop(context),
          child: Text(t('cancel')),
        ),
      ],
    );
  }
}

class _BarcodeDialog extends StatefulWidget {
  const _BarcodeDialog({required this.client});

  final AirmiusApiClient client;

  @override
  State<_BarcodeDialog> createState() => _BarcodeDialogState();
}

class _BarcodeDialogState extends State<_BarcodeDialog> {
  final _barcode = TextEditingController();
  bool _loading = false;
  String? _error;

  @override
  void dispose() {
    _barcode.dispose();
    super.dispose();
  }

  Future<void> _lookup() async {
    final value = _barcode.text.replaceAll(RegExp(r'[\s-]'), '');
    if (value.length < 6) return;
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final json = await widget.client.lookupNutritionBarcode(value);
      final data = _map(json['data']);
      if (!mounted) return;
      Navigator.pop(context, data);
    } on AirmiusApiException catch (error) {
      if (!mounted) return;
      setState(() => _error = error.userMessage);
    } catch (error) {
      if (!mounted) return;
      setState(
        () => _error = error is AirmiusApiException
            ? error.userMessage
            : AirmiusScope.of(context).t('common.errorDetails'),
      );
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _scan() async {
    final value = await Navigator.of(context).push<String>(
      MaterialPageRoute(builder: (_) => const NutritionBarcodeScannerScreen()),
    );
    if (!mounted || value == null || value.isEmpty) return;
    _barcode.text = value;
    await _lookup();
  }

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    return AlertDialog(
      backgroundColor: airmiusSurfaceColor(context),
      title: Text(t('nutrition.barcode')),
      content: SingleChildScrollView(
        child: SizedBox(
          width: 360,
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text(
                t('nutrition.barcodeHint'),
                style: TextStyle(color: airmiusMutedColor(context)),
              ),
              const SizedBox(height: 14),
              TextField(
                controller: _barcode,
                autofocus: true,
                keyboardType: TextInputType.number,
                textInputAction: TextInputAction.search,
                onSubmitted: (_) => _lookup(),
                decoration: InputDecoration(
                  labelText: t('nutrition.barcodeNumber'),
                  errorText: _error,
                ),
              ),
              const SizedBox(height: 14),
              OutlinedButton.icon(
                onPressed: _loading ? null : _scan,
                icon: const Icon(Icons.qr_code_scanner_outlined),
                label: Text(t('nutrition.scanBarcode')),
              ),
              const SizedBox(height: 10),
              FilledButton.icon(
                onPressed: _loading ? null : _lookup,
                icon: const Icon(Icons.search_outlined),
                label: Text(t('nutrition.lookup')),
              ),
              TextButton(
                onPressed: _loading ? null : () => Navigator.pop(context),
                child: Text(t('cancel')),
              ),
              if (_loading) ...[
                const SizedBox(height: 8),
                const LinearProgressIndicator(),
              ],
            ],
          ),
        ),
      ),
    );
  }
}

class _NumberField extends StatelessWidget {
  const _NumberField({
    required this.controller,
    required this.label,
    this.integer = false,
    this.suffixText,
  });

  final TextEditingController controller;
  final String label;
  final bool integer;
  final String? suffixText;

  @override
  Widget build(BuildContext context) {
    return TextField(
      controller: controller,
      keyboardType: TextInputType.numberWithOptions(decimal: !integer),
      decoration: InputDecoration(labelText: label, suffixText: suffixText),
    );
  }
}

class _NutritionLoading extends StatelessWidget {
  const _NutritionLoading();

  @override
  Widget build(BuildContext context) {
    return const AirmiusPanel(
      child: Padding(
        padding: EdgeInsets.all(28),
        child: Center(child: CircularProgressIndicator()),
      ),
    );
  }
}

class _NutritionError extends StatelessWidget {
  const _NutritionError({required this.error, required this.onRetry});

  final Object? error;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    final t = AirmiusScope.of(context).t;
    final message = error is AirmiusApiException
        ? (error as AirmiusApiException).userMessage
        : t('common.errorDetails');
    return AirmiusPanel(
      borderColor: Theme.of(context).colorScheme.error.withValues(alpha: .5),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Text(
            t('nutrition.loadError'),
            style: TextStyle(
              color: airmiusTextColor(context),
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 8),
          Text(message, style: TextStyle(color: airmiusMutedColor(context))),
          const SizedBox(height: 14),
          AirmiusButton(
            label: t('nutrition.reload'),
            icon: Icons.refresh_outlined,
            secondary: true,
            onPressed: onRetry,
          ),
        ],
      ),
    );
  }
}

JsonMap _map(Object? value) {
  if (value is JsonMap) return value;
  if (value is Map) {
    return value.map((key, item) => MapEntry('$key', item));
  }
  return const {};
}

List<JsonMap> _maps(Object? value) => value is List
    ? value.map(_map).where((item) => item.isNotEmpty).toList()
    : const [];

List<String> _strings(Object? value) => value is List
    ? value.map((item) => '$item').where((item) => item.isNotEmpty).toList()
    : const [];

bool _truthy(Object? value) =>
    value == true || value == 1 || '$value'.toLowerCase() == 'true';

String _text(Object? value, {String fallback = ''}) {
  final text = value?.toString().trim() ?? '';
  return text.isEmpty || text == 'null' ? fallback : text;
}

double _number(Object? value) =>
    value is num ? value.toDouble() : double.tryParse('$value') ?? 0;

int _integer(Object? value, {int fallback = 0}) =>
    value is num ? value.round() : int.tryParse('$value') ?? fallback;

int _integerFromInput(String value) =>
    double.tryParse(value.trim().replaceAll(',', '.'))?.round() ?? 0;

double _numberFromInput(String value) =>
    double.tryParse(value.trim().replaceAll(',', '.')) ?? 0;

double? _nullableNumberFromInput(String value) {
  if (value.trim().isEmpty) return null;
  return double.tryParse(value.trim().replaceAll(',', '.'));
}

String _numberLabel(Object? value) {
  final number = _number(value);
  return number == number.roundToDouble()
      ? '${number.round()}'
      : number.toStringAsFixed(1).replaceAll('.', ',');
}

String _inputNumber(Object? value) {
  if (value == null || '$value' == 'null') return '';
  final number = _number(value);
  return number == number.roundToDouble()
      ? '${number.round()}'
      : number.toStringAsFixed(1);
}

String _waterLitres(int ml) =>
    (ml / 1000).toStringAsFixed(ml % 1000 == 0 ? 0 : 1).replaceAll('.', ',');

JsonMap _withWaterTotal(JsonMap data, int waterMl) {
  return {
    ...data,
    'summary': {..._map(data['summary']), 'water_ml': waterMl},
  };
}

JsonMap _withoutMeal(JsonMap data, JsonMap meal) {
  final mealId = _integer(meal['id']);
  final summary = _summaryWithoutMeal(_map(data['summary']), meal);
  final mealDate = _text(meal['eaten_on'], fallback: _text(summary['date']));
  final weekly = _maps(data['weekly_summaries'])
      .map(
        (day) => _text(day['date']) == mealDate
            ? {
                ...day,
                for (final key in const [
                  'calories',
                  'protein_g',
                  'carbs_g',
                  'fat_g',
                  'water_ml',
                ])
                  key: summary[key],
              }
            : day,
      )
      .toList();

  return {
    ...data,
    'meals': _maps(
      data['meals'],
    ).where((entry) => _integer(entry['id']) != mealId).toList(),
    'summary': summary,
    'weekly_summaries': weekly,
  };
}

JsonMap _summaryWithoutMeal(JsonMap summary, JsonMap meal) {
  final calories = _integer(summary['calories']) - _integer(meal['calories']);
  final water = _integer(summary['water_ml']) - _integer(meal['water_ml']);

  double subtract(String key) {
    final value = _number(summary[key]) - _number(meal[key]);
    if (value <= 0) return 0;
    return (value * 10).round() / 10;
  }

  return {
    ...summary,
    'calories': calories < 0 ? 0 : calories,
    'protein_g': subtract('protein_g'),
    'carbs_g': subtract('carbs_g'),
    'fat_g': subtract('fat_g'),
    'water_ml': water < 0 ? 0 : water,
  };
}

DateTime _dateOnly(DateTime value) =>
    DateTime(value.year, value.month, value.day);

String _isoDate(DateTime value) =>
    '${value.year.toString().padLeft(4, '0')}-${value.month.toString().padLeft(2, '0')}-${value.day.toString().padLeft(2, '0')}';

String _displayDate(DateTime value) =>
    '${value.day.toString().padLeft(2, '0')}.${value.month.toString().padLeft(2, '0')}.${value.year}';

String _firstKey(List<JsonMap> items, String fallback) {
  if (items.isEmpty) return fallback;
  return _text(items.first['key'], fallback: fallback);
}

String _catalogItemLabel(JsonMap item) => _text(
  item['label'] ?? item['name'] ?? item['title'],
  fallback: _text(item['key']),
);

String _catalogLabel(
  BuildContext context,
  Object? items,
  String key,
  String prefix,
) {
  for (final item in _maps(items)) {
    if (_text(item['key']) == key) {
      return _localizedCatalogLabel(context, item, prefix);
    }
  }
  return key;
}

String _localizedCatalogLabel(
  BuildContext context,
  JsonMap item,
  String prefix,
) {
  final key = _text(item['key']);
  final translationKey = '$prefix.$key';
  final translated = AirmiusScope.of(context).t(translationKey);
  return translated == translationKey ? _catalogItemLabel(item) : translated;
}

String _sourceForPrefill(JsonMap food) {
  final source = _text(food['source']);
  if (source == 'barcode') return 'barcode';
  if (source == 'photo_estimate') return 'photo_estimate';
  return 'manual';
}

List<JsonMap> _mealMicronutrients(JsonMap meal) {
  final grouped = <String, JsonMap>{};

  for (final item in _maps(meal['items'])) {
    for (final nutrient in _maps(item['micronutrients'])) {
      final key = _text(nutrient['key'], fallback: _text(nutrient['label']));
      final amount = _number(nutrient['amount']);
      if (key.isEmpty || amount <= 0) continue;

      final existing = grouped[key];
      grouped[key] = {
        'key': key,
        'label': _text(nutrient['label'], fallback: key),
        'amount': ((amount + _number(existing?['amount'])) * 100).round() / 100,
        'unit': _text(nutrient['unit'], fallback: _text(existing?['unit'])),
      };
    }
  }

  return grouped.values.toList();
}

bool _isDrinkProduct(JsonMap food) {
  final fields = [
    food['meal_type'],
    food['serving_size'],
    food['quantity_label'],
    food['quantity'],
    food['category'],
    food['categories'],
    food['title'],
    food['name'],
    food['product_name'],
  ].map((value) => _text(value).toLowerCase()).join(' ');

  final liquidUnitPattern = RegExp(
    r'\b(ml|milliliter|millilitre|cl|l|liter|litre)\b',
  );
  if (liquidUnitPattern.hasMatch(fields)) {
    return true;
  }

  const drinkWords = [
    'drink',
    'beverage',
    'water',
    'wasser',
    'milch',
    'milk',
    'saft',
    'juice',
    'smoothie',
    'shake',
    'tee',
    'tea',
    'kaffee',
    'coffee',
    'cola',
    'limonade',
    'lemonade',
  ];
  return drinkWords.any(fields.contains);
}

double _servingAmount(JsonMap food, String fallbackUnit) {
  final serving = _text(food['serving_size']);
  final servingAmount = _amountFromText(serving, fallbackUnit);
  if (servingAmount > 0) return servingAmount;

  return 100;
}

double _amountFromText(String value, String fallbackUnit) {
  final match = RegExp(
    r'(\d+(?:[.,]\d+)?)\s*(ml|milliliter|millilitre|cl|l|liter|litre|g|gramm|gram|kg)?',
    caseSensitive: false,
  ).firstMatch(value);
  if (match == null) return 0;

  var amount = double.tryParse(match.group(1)!.replaceAll(',', '.')) ?? 0;
  final unit = (match.group(2) ?? fallbackUnit).toLowerCase();
  if (unit == 'l' || unit == 'liter' || unit == 'litre') amount *= 1000;
  if (unit == 'cl') amount *= 10;
  if (unit == 'kg') amount *= 1000;
  return amount;
}

String _imageExtension(PlatformFile file) {
  final explicit = (file.extension ?? '').toLowerCase();
  if (explicit.isNotEmpty) return explicit;
  final source = [file.name, file.path].whereType<String>().join('.');
  final match = RegExp(r'\.([a-zA-Z0-9]+)$').firstMatch(source);
  return match?.group(1)?.toLowerCase() ?? 'jpg';
}

IconData _mealIcon(String type) => switch (type) {
  'breakfast' => Icons.breakfast_dining_outlined,
  'lunch' => Icons.lunch_dining_outlined,
  'dinner' => Icons.dinner_dining_outlined,
  'drink' => Icons.water_drop_outlined,
  _ => Icons.restaurant_outlined,
};

String _foodName(JsonMap food) => _text(
  food['title'] ?? food['name'] ?? food['product_name'],
  fallback: 'Lebensmittel',
);

String _foodMeta(JsonMap food) {
  final brand = _text(food['brand'] ?? food['brands']);
  final calories = _integer(
    food['calories'] ?? food['energy_kcal'] ?? food['energy_kcal_serving'],
  );
  return [
    if (brand.isNotEmpty) brand,
    if (calories > 0) '$calories kcal',
  ].join(' · ');
}
