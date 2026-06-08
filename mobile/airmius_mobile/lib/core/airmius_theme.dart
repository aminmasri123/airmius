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

class AirmiusTheme {
  const AirmiusTheme._();

  static ThemeData light() {
    final scheme = ColorScheme.fromSeed(
      seedColor: AirmiusColors.blue,
      brightness: Brightness.light,
    );

    return ThemeData(
      useMaterial3: true,
      brightness: Brightness.light,
      colorScheme: scheme.copyWith(
        surface: AirmiusColors.lightCard,
        primary: AirmiusColors.blueDeep,
        secondary: AirmiusColors.green,
        error: AirmiusColors.red,
      ),
      scaffoldBackgroundColor: AirmiusColors.lightBg,
      fontFamily: 'Roboto',
      appBarTheme: const AppBarTheme(
        backgroundColor: AirmiusColors.lightHeader,
        foregroundColor: AirmiusColors.lightText,
        surfaceTintColor: Colors.transparent,
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: AirmiusColors.lightInput,
        labelStyle: const TextStyle(color: AirmiusColors.lightText, fontWeight: FontWeight.w800),
        hintStyle: const TextStyle(color: AirmiusColors.lightMuted),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(16),
          borderSide: const BorderSide(color: AirmiusColors.lightBorder),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(16),
          borderSide: const BorderSide(color: AirmiusColors.blueDeep, width: 1.4),
        ),
      ),
    );
  }

  static ThemeData dark() {
    final scheme = ColorScheme.fromSeed(
      seedColor: AirmiusColors.blue,
      brightness: Brightness.dark,
    );

    return ThemeData(
      useMaterial3: true,
      brightness: Brightness.dark,
      colorScheme: scheme.copyWith(
        surface: AirmiusColors.card,
        primary: AirmiusColors.blue,
        secondary: AirmiusColors.green,
        error: AirmiusColors.red,
      ),
      scaffoldBackgroundColor: AirmiusColors.bg,
      fontFamily: 'Roboto',
      textTheme: const TextTheme(
        headlineLarge: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900),
        headlineMedium: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900),
        titleLarge: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w900),
        titleMedium: TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w800),
        bodyLarge: TextStyle(color: AirmiusColors.text),
        bodyMedium: TextStyle(color: AirmiusColors.muted),
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: AirmiusColors.input,
        labelStyle: const TextStyle(color: AirmiusColors.text, fontWeight: FontWeight.w800),
        hintStyle: const TextStyle(color: AirmiusColors.mutedSoft),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(16),
          borderSide: const BorderSide(color: AirmiusColors.border),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(16),
          borderSide: const BorderSide(color: AirmiusColors.blue, width: 1.4),
        ),
      ),
    );
  }
}


