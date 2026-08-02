import 'package:flutter/material.dart';

/// Picks a readable foreground for a coloured control.
///
/// Several of the selectable palettes use a bright primary (for example Air
/// blue). Always putting white text on those colours fails WCAG contrast and
/// is especially hard to read for older users. Keeping this decision in the
/// theme means buttons and controls stay legible when a palette changes.
Color airmiusOnColor(Color background) {
  return background.computeLuminance() > 0.179 ? Colors.black : Colors.white;
}

/// Resolves the common surface/text tokens from the active Material theme.
///
/// Screens may still use the legacy [AirmiusColors] constants for semantic
/// accents, but these helpers keep neutral content readable when users switch
/// between light, dark and high-contrast palettes.
Color airmiusTextColor(BuildContext context) =>
    Theme.of(context).colorScheme.onSurface;

/// The active palette accent used for primary controls and navigation cues.
Color airmiusAccentColor(BuildContext context) =>
    Theme.of(context).colorScheme.primary;

Color airmiusMutedColor(BuildContext context) =>
    Theme.of(context).colorScheme.onSurfaceVariant;

/// A softer secondary label color for metadata and compact badges.
///
/// It intentionally derives from the active palette so light, dark and
/// high-contrast themes keep the same hierarchy without leaking the legacy
/// dark-only neutral constants into a bright surface.
Color airmiusMutedSoftColor(BuildContext context) =>
    Theme.of(context).colorScheme.onSurfaceVariant.withValues(alpha: 0.78);

Color airmiusSurfaceColor(BuildContext context) =>
    Theme.of(context).colorScheme.surface;

Color airmiusSurfaceSoftColor(BuildContext context) =>
    Theme.of(context).colorScheme.surfaceContainerHighest;

Color airmiusInputColor(BuildContext context) =>
    Theme.of(context).inputDecorationTheme.fillColor ??
    airmiusSurfaceSoftColor(context);

Color airmiusBorderColor(BuildContext context) =>
    Theme.of(context).dividerColor;

/// Maps legacy semantic colors used by older parity suites onto the active
/// Material palette. This keeps those screens readable when users switch
/// between light, dark and high-contrast themes without changing their
/// semantic meaning (success, warning, danger or accent).
Color airmiusSemanticColor(BuildContext context, Color semanticColor) {
  final scheme = Theme.of(context).colorScheme;
  if (semanticColor == AirmiusColors.text) return scheme.onSurface;
  if (semanticColor == AirmiusColors.muted) return scheme.onSurfaceVariant;
  if (semanticColor == AirmiusColors.border) {
    return Theme.of(context).dividerColor;
  }
  if (semanticColor == AirmiusColors.input ||
      semanticColor == AirmiusColors.cardSoft ||
      semanticColor == AirmiusColors.surface2) {
    return scheme.surfaceContainerHighest;
  }
  if (semanticColor == AirmiusColors.bg) {
    return Theme.of(context).scaffoldBackgroundColor;
  }
  if (semanticColor == AirmiusColors.header) {
    return Theme.of(context).appBarTheme.backgroundColor ?? scheme.surface;
  }
  if (semanticColor == AirmiusColors.green) return scheme.secondary;
  if (semanticColor == AirmiusColors.amber) return scheme.tertiary;
  if (semanticColor == AirmiusColors.red) return scheme.error;
  return scheme.primary;
}

class AirmiusColors {
  const AirmiusColors._();

  static const bg = Color(0xFF070B12);
  static const header = Color(0xFF0B111A);
  static const card = Color(0xFF111823);
  static const cardSoft = Color(0xFF151F2D);
  static const panelSoft = cardSoft;
  static const input = Color(0xFF101826);
  static const border = Color(0xFF25364F);
  static const borderStrong = Color(0xFF365476);
  static const blue = Color(0xFF5BA7FF);
  static const blueDeep = Color(0xFF1E5FAF);
  static const green = Color(0xFF40E7A2);
  static const red = Color(0xFFFF5D67);
  static const amber = Color(0xFFF7B955);
  static const pink = Color(0xFFF28DC5);
  static const text = Color(0xFFF7FAFF);
  static const muted = Color(0xFFA6B2C4);
  static const mutedSoft = Color(0xFF738399);
  static const surface2 = Color(0xFF162132);
  static const lightBg = Color(0xFFF4F7FB);
  static const lightHeader = Color(0xFFFFFFFF);
  static const lightCard = Color(0xFFFFFFFF);
  static const lightInput = Color(0xFFF7FAFF);
  static const lightBorder = Color(0xFFD7E2F1);
  static const lightText = Color(0xFF111827);
  static const lightMuted = Color(0xFF536174);
}

enum AirmiusThemePalette { dark, air, champion, sprint, arena, trail, contrast }

extension AirmiusThemePaletteInfo on AirmiusThemePalette {
  String get key => switch (this) {
    AirmiusThemePalette.dark => 'dark',
    AirmiusThemePalette.air => 'air',
    AirmiusThemePalette.champion => 'champion',
    AirmiusThemePalette.sprint => 'sprint',
    AirmiusThemePalette.arena => 'arena',
    AirmiusThemePalette.trail => 'trail',
    AirmiusThemePalette.contrast => 'contrast',
  };

  String get label => switch (this) {
    AirmiusThemePalette.dark => 'Dark',
    AirmiusThemePalette.air => 'Air',
    AirmiusThemePalette.champion => 'Champion',
    AirmiusThemePalette.sprint => 'Sprint',
    AirmiusThemePalette.arena => 'Arena',
    AirmiusThemePalette.trail => 'Trail',
    AirmiusThemePalette.contrast => 'Kontrast',
  };

  String get description => switch (this) {
    AirmiusThemePalette.dark => 'Dunkel, klar und kontrastreich.',
    AirmiusThemePalette.air => 'Klar, leicht und fokussiert.',
    AirmiusThemePalette.champion => 'Goldene Energie für Gewinner.',
    AirmiusThemePalette.sprint => 'Frisch, schnell und aktiv.',
    AirmiusThemePalette.arena => 'Ruhig, robust und professionell.',
    AirmiusThemePalette.trail => 'Natürlich, ausdauernd und bodenstaendig.',
    AirmiusThemePalette.contrast => 'Maximale Lesbarkeit und klare Konturen.',
  };

  Color get primary => switch (this) {
    AirmiusThemePalette.dark => AirmiusColors.blue,
    AirmiusThemePalette.air => const Color(0xFF0EA5E9),
    AirmiusThemePalette.champion => const Color(0xFFB45309),
    AirmiusThemePalette.sprint => const Color(0xFF059669),
    AirmiusThemePalette.arena => const Color(0xFF334155),
    AirmiusThemePalette.trail => const Color(0xFF4D7C0F),
    AirmiusThemePalette.contrast => const Color(0xFF005FCC),
  };

  Color get secondary => switch (this) {
    AirmiusThemePalette.dark => AirmiusColors.green,
    AirmiusThemePalette.air => const Color(0xFF10B981),
    AirmiusThemePalette.champion => const Color(0xFFF59E0B),
    AirmiusThemePalette.sprint => const Color(0xFF10B981),
    AirmiusThemePalette.arena => const Color(0xFF64748B),
    AirmiusThemePalette.trail => const Color(0xFF65A30D),
    AirmiusThemePalette.contrast => const Color(0xFF007A3D),
  };

  Color get lightBackground => switch (this) {
    AirmiusThemePalette.dark => AirmiusColors.lightBg,
    AirmiusThemePalette.air => const Color(0xFFF7FBFF),
    AirmiusThemePalette.champion => const Color(0xFFFFFAF0),
    AirmiusThemePalette.sprint => const Color(0xFFF5FFF9),
    AirmiusThemePalette.arena => const Color(0xFFF8FAFC),
    AirmiusThemePalette.trail => const Color(0xFFF6F8F2),
    AirmiusThemePalette.contrast => const Color(0xFFFFFFFF),
  };

  Color get lightSurface => switch (this) {
    AirmiusThemePalette.dark => AirmiusColors.lightCard,
    AirmiusThemePalette.air => const Color(0xFFFFFFFF),
    AirmiusThemePalette.champion => const Color(0xFFFFFDF7),
    AirmiusThemePalette.sprint => const Color(0xFFFBFFFD),
    AirmiusThemePalette.arena => const Color(0xFFFFFFFF),
    AirmiusThemePalette.trail => const Color(0xFFFBFDF7),
    AirmiusThemePalette.contrast => const Color(0xFFFFFFFF),
  };

  Color get lightSurfaceSoft => switch (this) {
    AirmiusThemePalette.dark => const Color(0xFFEAF4FB),
    AirmiusThemePalette.air => const Color(0xFFEAF4FB),
    AirmiusThemePalette.champion => const Color(0xFFFFF1C2),
    AirmiusThemePalette.sprint => const Color(0xFFDCFCE7),
    AirmiusThemePalette.arena => const Color(0xFFE2E8F0),
    AirmiusThemePalette.trail => const Color(0xFFEDF5DF),
    AirmiusThemePalette.contrast => const Color(0xFFF1F5F9),
  };

  Color get lightInput => switch (this) {
    AirmiusThemePalette.dark => AirmiusColors.lightInput,
    AirmiusThemePalette.air => const Color(0xFFFFFFFF),
    AirmiusThemePalette.champion => const Color(0xFFFFFDF7),
    AirmiusThemePalette.sprint => const Color(0xFFFBFFFD),
    AirmiusThemePalette.arena => const Color(0xFFFFFFFF),
    AirmiusThemePalette.trail => const Color(0xFFFBFDF7),
    AirmiusThemePalette.contrast => const Color(0xFFFFFFFF),
  };

  Color get lightText => switch (this) {
    AirmiusThemePalette.dark => const Color(0xFF102033),
    AirmiusThemePalette.air => const Color(0xFF102033),
    AirmiusThemePalette.champion => const Color(0xFF2F2412),
    AirmiusThemePalette.sprint => const Color(0xFF0B2F24),
    AirmiusThemePalette.arena => const Color(0xFF1E293B),
    AirmiusThemePalette.trail => const Color(0xFF24301B),
    AirmiusThemePalette.contrast => const Color(0xFF000000),
  };

  Color get lightMutedText => switch (this) {
    AirmiusThemePalette.dark => const Color(0xFF5D6B7E),
    AirmiusThemePalette.air => const Color(0xFF5D6B7E),
    AirmiusThemePalette.champion => const Color(0xFF7A5A22),
    AirmiusThemePalette.sprint => const Color(0xFF437063),
    AirmiusThemePalette.arena => const Color(0xFF64748B),
    AirmiusThemePalette.trail => const Color(0xFF657252),
    AirmiusThemePalette.contrast => const Color(0xFF334155),
  };

  Color get lightBorder => switch (this) {
    AirmiusThemePalette.dark => AirmiusColors.lightBorder,
    AirmiusThemePalette.air => const Color(0xFFD7E4EF),
    AirmiusThemePalette.champion => const Color(0xFFF2D89B),
    AirmiusThemePalette.sprint => const Color(0xFFBFE8D8),
    AirmiusThemePalette.arena => const Color(0xFFCBD5E1),
    AirmiusThemePalette.trail => const Color(0xFFD6DFC6),
    AirmiusThemePalette.contrast => const Color(0xFF64748B),
  };

  Color get darkBackground => switch (this) {
    AirmiusThemePalette.dark => AirmiusColors.bg,
    AirmiusThemePalette.air => const Color(0xFF07131D),
    AirmiusThemePalette.champion => const Color(0xFF1C1206),
    AirmiusThemePalette.sprint => const Color(0xFF061A13),
    AirmiusThemePalette.arena => const Color(0xFF0E141D),
    AirmiusThemePalette.trail => const Color(0xFF101707),
    AirmiusThemePalette.contrast => const Color(0xFF000000),
  };

  Color get darkHeader => switch (this) {
    AirmiusThemePalette.dark => AirmiusColors.header,
    AirmiusThemePalette.air => const Color(0xFF081A28),
    AirmiusThemePalette.champion => const Color(0xFF261807),
    AirmiusThemePalette.sprint => const Color(0xFF071F17),
    AirmiusThemePalette.arena => const Color(0xFF111827),
    AirmiusThemePalette.trail => const Color(0xFF17210A),
    AirmiusThemePalette.contrast => const Color(0xFF000000),
  };

  Color get darkSurface => this == AirmiusThemePalette.contrast
      ? const Color(0xFF0A0A0A)
      : Color.lerp(AirmiusColors.card, primary, 0.10) ?? AirmiusColors.card;

  Color get darkSurfaceSoft => this == AirmiusThemePalette.contrast
      ? const Color(0xFF171717)
      : Color.lerp(AirmiusColors.cardSoft, primary, 0.16) ??
            AirmiusColors.cardSoft;
}

AirmiusThemePalette airmiusThemePaletteFromKey(String? key) {
  for (final palette in AirmiusThemePalette.values) {
    if (palette.key == key) {
      return palette;
    }
  }
  return AirmiusThemePalette.dark;
}

class AirmiusTheme {
  const AirmiusTheme._();

  static ThemeData light([
    AirmiusThemePalette palette = AirmiusThemePalette.dark,
  ]) {
    final scheme =
        ColorScheme.fromSeed(
          seedColor: palette.primary,
          brightness: Brightness.light,
        ).copyWith(
          surface: palette.lightSurface,
          surfaceContainerHighest: palette.lightSurfaceSoft,
          primary: palette.primary,
          onPrimary: airmiusOnColor(palette.primary),
          secondary: palette.secondary,
          onSecondary: airmiusOnColor(palette.secondary),
          error: AirmiusColors.red,
          onError: Colors.white,
          onSurface: palette.lightText,
          onSurfaceVariant: palette.lightMutedText,
          outline: palette.lightBorder,
        );

    return ThemeData(
      useMaterial3: true,
      brightness: Brightness.light,
      colorScheme: scheme,
      scaffoldBackgroundColor: palette.lightBackground,
      dividerColor: palette.lightBorder,
      fontFamily: 'Roboto',
      textTheme: TextTheme(
        headlineLarge: TextStyle(
          color: palette.lightText,
          fontWeight: FontWeight.w900,
          decoration: TextDecoration.none,
        ),
        headlineMedium: TextStyle(
          color: palette.lightText,
          fontWeight: FontWeight.w900,
          decoration: TextDecoration.none,
        ),
        titleLarge: TextStyle(
          color: palette.lightText,
          fontWeight: FontWeight.w900,
          decoration: TextDecoration.none,
        ),
        titleMedium: TextStyle(
          color: palette.lightText,
          fontWeight: FontWeight.w800,
          decoration: TextDecoration.none,
        ),
        bodyLarge: TextStyle(
          color: palette.lightText,
          decoration: TextDecoration.none,
        ),
        bodyMedium: TextStyle(
          color: palette.lightMutedText,
          decoration: TextDecoration.none,
        ),
        bodySmall: TextStyle(
          color: palette.lightMutedText,
          decoration: TextDecoration.none,
        ),
        titleSmall: TextStyle(
          color: palette.lightText,
          fontWeight: FontWeight.w800,
          decoration: TextDecoration.none,
        ),
        labelLarge: TextStyle(
          color: palette.lightText,
          fontWeight: FontWeight.w800,
          decoration: TextDecoration.none,
        ),
        labelMedium: TextStyle(
          color: palette.lightMutedText,
          fontWeight: FontWeight.w700,
          decoration: TextDecoration.none,
        ),
        labelSmall: TextStyle(
          color: palette.lightMutedText,
          fontWeight: FontWeight.w700,
          decoration: TextDecoration.none,
        ),
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: palette.lightInput,
        contentPadding: const EdgeInsets.symmetric(
          horizontal: 16,
          vertical: 15,
        ),
        floatingLabelBehavior: FloatingLabelBehavior.auto,
        labelStyle: TextStyle(
          color: palette.lightText,
          fontWeight: FontWeight.w800,
        ),
        hintStyle: TextStyle(color: palette.lightMutedText),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(16),
          borderSide: BorderSide(color: palette.lightBorder),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(16),
          borderSide: BorderSide(color: palette.primary, width: 1.4),
        ),
        errorBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(16),
          borderSide: const BorderSide(color: AirmiusColors.red, width: 1.2),
        ),
        focusedErrorBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(16),
          borderSide: const BorderSide(color: AirmiusColors.red, width: 1.6),
        ),
        errorStyle: const TextStyle(
          color: AirmiusColors.red,
          fontWeight: FontWeight.w700,
        ),
      ),
      appBarTheme: AppBarTheme(
        backgroundColor: palette.lightSurface,
        foregroundColor: palette.lightText,
        surfaceTintColor: Colors.transparent,
      ),
      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          backgroundColor: palette.primary,
          foregroundColor: airmiusOnColor(palette.primary),
          minimumSize: const Size(48, 50),
          padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 14),
          textStyle: const TextStyle(fontSize: 16, fontWeight: FontWeight.w800),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(14),
          ),
        ),
      ),
      outlinedButtonTheme: OutlinedButtonThemeData(
        style: OutlinedButton.styleFrom(
          foregroundColor: palette.primary,
          minimumSize: const Size(48, 50),
          padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 14),
          textStyle: const TextStyle(fontSize: 16, fontWeight: FontWeight.w800),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(14),
          ),
        ),
      ),
      iconButtonTheme: IconButtonThemeData(
        style: ButtonStyle(
          minimumSize: const WidgetStatePropertyAll(Size(48, 48)),
          foregroundColor: WidgetStatePropertyAll(palette.lightText),
        ),
      ),
      navigationBarTheme: NavigationBarThemeData(
        height: 72,
        backgroundColor: palette.lightSurface,
        indicatorColor: palette.primary.withValues(alpha: 0.16),
        labelTextStyle: WidgetStatePropertyAll(
          TextStyle(color: palette.lightText, fontWeight: FontWeight.w800),
        ),
      ),
      snackBarTheme: SnackBarThemeData(
        backgroundColor: palette.lightText,
        contentTextStyle: TextStyle(
          color: palette.lightSurface,
          fontWeight: FontWeight.w700,
        ),
        actionTextColor: palette.primary,
        behavior: SnackBarBehavior.floating,
      ),
      bottomSheetTheme: BottomSheetThemeData(
        backgroundColor: palette.lightSurface,
        modalBackgroundColor: palette.lightSurface,
        showDragHandle: true,
      ),
      visualDensity: VisualDensity.standard,
      materialTapTargetSize: MaterialTapTargetSize.padded,
    );
  }

  static ThemeData dark([
    AirmiusThemePalette palette = AirmiusThemePalette.dark,
  ]) {
    final scheme =
        ColorScheme.fromSeed(
          seedColor: palette.primary,
          brightness: Brightness.dark,
        ).copyWith(
          surface: palette.darkSurface,
          surfaceContainerHighest: palette.darkSurfaceSoft,
          primary: palette.primary,
          onPrimary: airmiusOnColor(palette.primary),
          secondary: palette.secondary,
          onSecondary: airmiusOnColor(palette.secondary),
          error: AirmiusColors.red,
          onError: Colors.white,
          onSurface: AirmiusColors.text,
          onSurfaceVariant: AirmiusColors.muted,
          outline: AirmiusColors.border,
        );

    return ThemeData(
      useMaterial3: true,
      brightness: Brightness.dark,
      colorScheme: scheme,
      scaffoldBackgroundColor: palette.darkBackground,
      dividerColor: AirmiusColors.border,
      fontFamily: 'Roboto',
      textTheme: const TextTheme(
        headlineLarge: TextStyle(
          color: AirmiusColors.text,
          fontWeight: FontWeight.w900,
          decoration: TextDecoration.none,
        ),
        headlineMedium: TextStyle(
          color: AirmiusColors.text,
          fontWeight: FontWeight.w900,
          decoration: TextDecoration.none,
        ),
        titleLarge: TextStyle(
          color: AirmiusColors.text,
          fontWeight: FontWeight.w900,
          decoration: TextDecoration.none,
        ),
        titleMedium: TextStyle(
          color: AirmiusColors.text,
          fontWeight: FontWeight.w800,
          decoration: TextDecoration.none,
        ),
        bodyLarge: TextStyle(
          color: AirmiusColors.text,
          decoration: TextDecoration.none,
        ),
        bodyMedium: TextStyle(
          color: AirmiusColors.muted,
          decoration: TextDecoration.none,
        ),
        bodySmall: TextStyle(
          color: AirmiusColors.muted,
          decoration: TextDecoration.none,
        ),
        titleSmall: TextStyle(
          color: AirmiusColors.text,
          fontWeight: FontWeight.w800,
          decoration: TextDecoration.none,
        ),
        labelLarge: TextStyle(
          color: AirmiusColors.text,
          fontWeight: FontWeight.w800,
          decoration: TextDecoration.none,
        ),
        labelMedium: TextStyle(
          color: AirmiusColors.muted,
          fontWeight: FontWeight.w700,
          decoration: TextDecoration.none,
        ),
        labelSmall: TextStyle(
          color: AirmiusColors.muted,
          fontWeight: FontWeight.w700,
          decoration: TextDecoration.none,
        ),
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: palette.darkSurfaceSoft,
        contentPadding: const EdgeInsets.symmetric(
          horizontal: 16,
          vertical: 15,
        ),
        floatingLabelBehavior: FloatingLabelBehavior.auto,
        labelStyle: const TextStyle(
          color: AirmiusColors.text,
          fontWeight: FontWeight.w800,
        ),
        hintStyle: const TextStyle(color: AirmiusColors.mutedSoft),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(16),
          borderSide: const BorderSide(color: AirmiusColors.border),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(16),
          borderSide: BorderSide(color: palette.primary, width: 1.4),
        ),
        errorBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(16),
          borderSide: const BorderSide(color: AirmiusColors.red, width: 1.2),
        ),
        focusedErrorBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(16),
          borderSide: const BorderSide(color: AirmiusColors.red, width: 1.6),
        ),
        errorStyle: const TextStyle(
          color: AirmiusColors.red,
          fontWeight: FontWeight.w700,
        ),
      ),
      appBarTheme: AppBarTheme(
        backgroundColor: palette.darkHeader,
        foregroundColor: AirmiusColors.text,
        surfaceTintColor: Colors.transparent,
      ),
      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          backgroundColor: palette.primary,
          foregroundColor: airmiusOnColor(palette.primary),
          minimumSize: const Size(48, 50),
          padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 14),
          textStyle: const TextStyle(fontSize: 16, fontWeight: FontWeight.w800),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(14),
          ),
        ),
      ),
      outlinedButtonTheme: OutlinedButtonThemeData(
        style: OutlinedButton.styleFrom(
          foregroundColor: palette.primary,
          minimumSize: const Size(48, 50),
          padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 14),
          textStyle: const TextStyle(fontSize: 16, fontWeight: FontWeight.w800),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(14),
          ),
        ),
      ),
      iconButtonTheme: IconButtonThemeData(
        style: ButtonStyle(
          minimumSize: const WidgetStatePropertyAll(Size(48, 48)),
          foregroundColor: const WidgetStatePropertyAll(AirmiusColors.text),
        ),
      ),
      navigationBarTheme: NavigationBarThemeData(
        height: 72,
        backgroundColor: palette.darkHeader,
        indicatorColor: palette.primary.withValues(alpha: 0.22),
        labelTextStyle: const WidgetStatePropertyAll(
          TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w800),
        ),
      ),
      snackBarTheme: const SnackBarThemeData(
        backgroundColor: AirmiusColors.text,
        contentTextStyle: TextStyle(
          color: AirmiusColors.bg,
          fontWeight: FontWeight.w700,
        ),
        actionTextColor: AirmiusColors.blueDeep,
        behavior: SnackBarBehavior.floating,
      ),
      bottomSheetTheme: const BottomSheetThemeData(
        backgroundColor: AirmiusColors.card,
        modalBackgroundColor: AirmiusColors.card,
        showDragHandle: true,
      ),
      visualDensity: VisualDensity.standard,
      materialTapTargetSize: MaterialTapTargetSize.padded,
    );
  }
}
