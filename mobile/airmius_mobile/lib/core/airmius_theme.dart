import 'package:flutter/material.dart';

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

enum AirmiusThemePalette {
  dark,
  air,
  champion,
  sprint,
  arena,
  trail,
}

extension AirmiusThemePaletteInfo on AirmiusThemePalette {
  String get key => switch (this) {
        AirmiusThemePalette.dark => 'dark',
        AirmiusThemePalette.air => 'air',
        AirmiusThemePalette.champion => 'champion',
        AirmiusThemePalette.sprint => 'sprint',
        AirmiusThemePalette.arena => 'arena',
        AirmiusThemePalette.trail => 'trail',
      };

  String get label => switch (this) {
        AirmiusThemePalette.dark => 'Dark',
        AirmiusThemePalette.air => 'Air',
        AirmiusThemePalette.champion => 'Champion',
        AirmiusThemePalette.sprint => 'Sprint',
        AirmiusThemePalette.arena => 'Arena',
        AirmiusThemePalette.trail => 'Trail',
      };

  String get description => switch (this) {
        AirmiusThemePalette.dark => 'Dunkel, klar und kontrastreich.',
        AirmiusThemePalette.air => 'Klar, leicht und fokussiert.',
        AirmiusThemePalette.champion => 'Goldene Energie fuer Gewinner.',
        AirmiusThemePalette.sprint => 'Frisch, schnell und aktiv.',
        AirmiusThemePalette.arena => 'Ruhig, robust und professionell.',
        AirmiusThemePalette.trail => 'Natuerlich, ausdauernd und bodenstaendig.',
      };

  Color get primary => switch (this) {
        AirmiusThemePalette.dark => AirmiusColors.blue,
        AirmiusThemePalette.air => const Color(0xFF0EA5E9),
        AirmiusThemePalette.champion => const Color(0xFFB45309),
        AirmiusThemePalette.sprint => const Color(0xFF059669),
        AirmiusThemePalette.arena => const Color(0xFF334155),
        AirmiusThemePalette.trail => const Color(0xFF4D7C0F),
      };

  Color get secondary => switch (this) {
        AirmiusThemePalette.dark => AirmiusColors.green,
        AirmiusThemePalette.air => const Color(0xFF10B981),
        AirmiusThemePalette.champion => const Color(0xFFF59E0B),
        AirmiusThemePalette.sprint => const Color(0xFF10B981),
        AirmiusThemePalette.arena => const Color(0xFF64748B),
        AirmiusThemePalette.trail => const Color(0xFF65A30D),
      };

  Color get lightBackground => switch (this) {
        AirmiusThemePalette.dark => AirmiusColors.lightBg,
        AirmiusThemePalette.air => const Color(0xFFF7FBFF),
        AirmiusThemePalette.champion => const Color(0xFFFFFAF0),
        AirmiusThemePalette.sprint => const Color(0xFFF5FFF9),
        AirmiusThemePalette.arena => const Color(0xFFF8FAFC),
        AirmiusThemePalette.trail => const Color(0xFFF6F8F2),
      };

  Color get lightSurface => switch (this) {
        AirmiusThemePalette.dark => AirmiusColors.lightCard,
        AirmiusThemePalette.air => const Color(0xFFFFFFFF),
        AirmiusThemePalette.champion => const Color(0xFFFFFDF7),
        AirmiusThemePalette.sprint => const Color(0xFFFBFFFD),
        AirmiusThemePalette.arena => const Color(0xFFFFFFFF),
        AirmiusThemePalette.trail => const Color(0xFFFBFDF7),
      };

  Color get lightSurfaceSoft => switch (this) {
        AirmiusThemePalette.dark => const Color(0xFFEAF4FB),
        AirmiusThemePalette.air => const Color(0xFFEAF4FB),
        AirmiusThemePalette.champion => const Color(0xFFFFF1C2),
        AirmiusThemePalette.sprint => const Color(0xFFDCFCE7),
        AirmiusThemePalette.arena => const Color(0xFFE2E8F0),
        AirmiusThemePalette.trail => const Color(0xFFEDF5DF),
      };

  Color get lightInput => switch (this) {
        AirmiusThemePalette.dark => AirmiusColors.lightInput,
        AirmiusThemePalette.air => const Color(0xFFFFFFFF),
        AirmiusThemePalette.champion => const Color(0xFFFFFDF7),
        AirmiusThemePalette.sprint => const Color(0xFFFBFFFD),
        AirmiusThemePalette.arena => const Color(0xFFFFFFFF),
        AirmiusThemePalette.trail => const Color(0xFFFBFDF7),
      };

  Color get lightText => switch (this) {
        AirmiusThemePalette.dark => const Color(0xFF102033),
        AirmiusThemePalette.air => const Color(0xFF102033),
        AirmiusThemePalette.champion => const Color(0xFF2F2412),
        AirmiusThemePalette.sprint => const Color(0xFF0B2F24),
        AirmiusThemePalette.arena => const Color(0xFF1E293B),
        AirmiusThemePalette.trail => const Color(0xFF24301B),
      };

  Color get lightMutedText => switch (this) {
        AirmiusThemePalette.dark => const Color(0xFF5D6B7E),
        AirmiusThemePalette.air => const Color(0xFF5D6B7E),
        AirmiusThemePalette.champion => const Color(0xFF7A5A22),
        AirmiusThemePalette.sprint => const Color(0xFF437063),
        AirmiusThemePalette.arena => const Color(0xFF64748B),
        AirmiusThemePalette.trail => const Color(0xFF657252),
      };

  Color get lightBorder => switch (this) {
        AirmiusThemePalette.dark => AirmiusColors.lightBorder,
        AirmiusThemePalette.air => const Color(0xFFD7E4EF),
        AirmiusThemePalette.champion => const Color(0xFFF2D89B),
        AirmiusThemePalette.sprint => const Color(0xFFBFE8D8),
        AirmiusThemePalette.arena => const Color(0xFFCBD5E1),
        AirmiusThemePalette.trail => const Color(0xFFD6DFC6),
      };

  Color get darkBackground => switch (this) {
        AirmiusThemePalette.dark => AirmiusColors.bg,
        AirmiusThemePalette.air => const Color(0xFF07131D),
        AirmiusThemePalette.champion => const Color(0xFF1C1206),
        AirmiusThemePalette.sprint => const Color(0xFF061A13),
        AirmiusThemePalette.arena => const Color(0xFF0E141D),
        AirmiusThemePalette.trail => const Color(0xFF101707),
      };

  Color get darkHeader => switch (this) {
        AirmiusThemePalette.dark => AirmiusColors.header,
        AirmiusThemePalette.air => const Color(0xFF081A28),
        AirmiusThemePalette.champion => const Color(0xFF261807),
        AirmiusThemePalette.sprint => const Color(0xFF071F17),
        AirmiusThemePalette.arena => const Color(0xFF111827),
        AirmiusThemePalette.trail => const Color(0xFF17210A),
      };

  Color get darkSurface => Color.lerp(AirmiusColors.card, primary, 0.10) ?? AirmiusColors.card;

  Color get darkSurfaceSoft => Color.lerp(AirmiusColors.cardSoft, primary, 0.16) ?? AirmiusColors.cardSoft;

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

  static ThemeData light([AirmiusThemePalette palette = AirmiusThemePalette.dark]) {
    final scheme = ColorScheme.fromSeed(
      seedColor: palette.primary,
      brightness: Brightness.light,
    );

    return ThemeData(
      useMaterial3: true,
      brightness: Brightness.light,
      colorScheme: scheme.copyWith(
        surface: palette.lightSurface,
        primary: palette.primary,
        secondary: palette.secondary,
        error: AirmiusColors.red,
      ),
      scaffoldBackgroundColor: palette.lightBackground,
      dividerColor: palette.lightBorder,
      fontFamily: 'Roboto',
      textTheme: TextTheme(
        headlineLarge: TextStyle(color: palette.lightText, fontWeight: FontWeight.w900, decoration: TextDecoration.none),
        headlineMedium: TextStyle(color: palette.lightText, fontWeight: FontWeight.w900, decoration: TextDecoration.none),
        titleLarge: TextStyle(color: palette.lightText, fontWeight: FontWeight.w900, decoration: TextDecoration.none),
        titleMedium: TextStyle(color: palette.lightText, fontWeight: FontWeight.w800, decoration: TextDecoration.none),
        bodyLarge: TextStyle(color: palette.lightText, decoration: TextDecoration.none),
        bodyMedium: TextStyle(color: palette.lightMutedText, decoration: TextDecoration.none),
        labelLarge: const TextStyle(decoration: TextDecoration.none),
        labelMedium: const TextStyle(decoration: TextDecoration.none),
        labelSmall: const TextStyle(decoration: TextDecoration.none),
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: palette.lightInput,
        labelStyle: TextStyle(color: palette.lightText, fontWeight: FontWeight.w800),
        hintStyle: TextStyle(color: palette.lightMutedText),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(16),
          borderSide: BorderSide(color: palette.lightBorder),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(16),
          borderSide: BorderSide(color: palette.primary, width: 1.4),
        ),
      ),
      appBarTheme: AppBarTheme(
        backgroundColor: palette.lightSurface,
        foregroundColor: palette.lightText,
        surfaceTintColor: Colors.transparent,
      ),
    );
  }

  static ThemeData dark([AirmiusThemePalette palette = AirmiusThemePalette.dark]) {
    final scheme = ColorScheme.fromSeed(
      seedColor: palette.primary,
      brightness: Brightness.dark,
    );

    return ThemeData(
      useMaterial3: true,
      brightness: Brightness.dark,
      colorScheme: scheme.copyWith(
        surface: palette.darkSurface,
        primary: palette.primary,
        secondary: palette.secondary,
        error: AirmiusColors.red,
      ),
      scaffoldBackgroundColor: palette.darkBackground,
      dividerColor: AirmiusColors.border,
      fontFamily: 'Roboto',
      textTheme: const TextTheme(
        headlineLarge: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900, decoration: TextDecoration.none),
        headlineMedium: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900, decoration: TextDecoration.none),
        titleLarge: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900, decoration: TextDecoration.none),
        titleMedium: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w800, decoration: TextDecoration.none),
        bodyLarge: TextStyle(color: AirmiusColors.text, decoration: TextDecoration.none),
        bodyMedium: TextStyle(color: AirmiusColors.muted, decoration: TextDecoration.none),
        labelLarge: TextStyle(decoration: TextDecoration.none),
        labelMedium: TextStyle(decoration: TextDecoration.none),
        labelSmall: TextStyle(decoration: TextDecoration.none),
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: palette.darkSurfaceSoft,
        labelStyle: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w800),
        hintStyle: const TextStyle(color: AirmiusColors.mutedSoft),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(16),
          borderSide: const BorderSide(color: AirmiusColors.border),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(16),
          borderSide: BorderSide(color: palette.primary, width: 1.4),
        ),
      ),
      appBarTheme: AppBarTheme(
        backgroundColor: palette.darkHeader,
        foregroundColor: AirmiusColors.text,
        surfaceTintColor: Colors.transparent,
      ),
    );
  }
}


