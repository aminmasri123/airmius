String trainingPlanCountLabel(
  String Function(String) t, {
  required int exercises,
  required int sets,
}) {
  final exerciseLabel = t(
    exercises == 1 ? 'workout.exerciseSingular' : 'workout.exercises',
  );
  final setLabel = t(
    sets == 1 ? 'workout.targetSetSingular' : 'workout.targetSets',
  );
  return '$exercises $exerciseLabel · $sets $setLabel';
}
