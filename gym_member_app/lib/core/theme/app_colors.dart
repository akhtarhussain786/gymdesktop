import 'package:flutter/material.dart';

class AppColors {
  // Master Dark-Tech Palette from public_html
  static const Color defaultPrimary = Color(0xFFC7FF2E); // Electric Lime
  static const Color defaultSecondary = Color(0xFF00D9FF); // Cyan
  static const Color defaultAccent = Color(0xFFF4C430); // Gold

  // Lime Specific Highlights & Glows
  static const Color lime = Color(0xFFC7FF2E);
  static const Color limeGlow = Color(0x61C7FF2E);
  static const Color limeSubtle = Color(0x14C7FF2E);
  static const Color limeBorder = Color(0x38C7FF2E);
  static const Color limeHover = Color(0xFFB8F522);

  // Cyan Highlights
  static const Color cyan = Color(0xFF00D9FF);
  static const Color cyanGlow = Color(0x5900D9FF);
  static const Color cyanSubtle = Color(0x1400D9FF);

  // Dark Canvas & Surface Layers (Deep Space)
  static const Color darkBg = Color(0xFF05080D); // Deep Space
  static const Color darkBgDeep = Color(0xFF070A0F); // Canvas Deep
  static const Color darkCard = Color(0xFF111720); // Surface Elevated Card
  static const Color darkCardElevated = Color(0xFF151B23); // Surface Elevated 2
  static const Color darkSurfaceGlass = Color(0xD90D1219); // Frosted Glass
  static const Color darkBorder = Color(0x14FFFFFF); // rgba(255,255,255,0.08)
  static const Color darkBorderHighlight = Color(0x40C7FF2E);

  // Typography Colors (Dark)
  static const Color darkTextPrimary = Color(0xFFF8FAFC);
  static const Color darkTextSecondary = Color(0xFF94A3B8);
  static const Color darkTextMuted = Color(0xFF64748B);
  static const Color darkTextDim = Color(0xFF475569);

  // Light Theme palette (Enterprise clean fallback)
  static const Color lightBg = Color(0xFFF1F5F9);
  static const Color lightBgDeep = Color(0xFFFFFFFF);
  static const Color lightCard = Color(0xFFFFFFFF);
  static const Color lightCardElevated = Color(0xFFF8FAFC);
  static const Color lightBorder = Color(0xFFE2E8F0);
  static const Color lightTextPrimary = Color(0xFF0F172A);
  static const Color lightTextSecondary = Color(0xFF475569);
  static const Color lightTextMuted = Color(0xFF64748B);

  // Semantic Status Colors
  static const Color success = Color(0xFF10B981); // Emerald
  static const Color warning = Color(0xFFF59E0B); // Amber
  static const Color danger = Color(0xFFFF5A36); // Coral Red
  static const Color info = Color(0xFF3B82F6); // Blue

  // Dynamic Theme-Aware Helpers
  static Color bg(BuildContext context) => Theme.of(context).brightness == Brightness.dark ? darkBg : lightBg;
  static Color bgDeep(BuildContext context) => Theme.of(context).brightness == Brightness.dark ? darkBgDeep : lightBgDeep;
  static Color card(BuildContext context) => Theme.of(context).brightness == Brightness.dark ? darkCard : lightCard;
  static Color cardElevated(BuildContext context) => Theme.of(context).brightness == Brightness.dark ? darkCardElevated : lightCardElevated;
  static Color border(BuildContext context) => Theme.of(context).brightness == Brightness.dark ? darkBorder : lightBorder;
  static Color textPrimary(BuildContext context) => Theme.of(context).brightness == Brightness.dark ? darkTextPrimary : lightTextPrimary;
  static Color textSecondary(BuildContext context) => Theme.of(context).brightness == Brightness.dark ? darkTextSecondary : lightTextSecondary;
  static Color textMuted(BuildContext context) => Theme.of(context).brightness == Brightness.dark ? darkTextMuted : lightTextMuted;
  static Color primaryText(BuildContext context) => Theme.of(context).brightness == Brightness.dark ? lime : const Color(0xFF15803D);
  static Color accentText(BuildContext context) => Theme.of(context).brightness == Brightness.dark ? cyan : const Color(0xFF0284C7);

  // Hex string parser helper
  static Color fromHex(String? hexString, {Color fallback = defaultPrimary}) {
    if (hexString == null || hexString.isEmpty) return fallback;
    final buffer = StringBuffer();
    if (hexString.length == 6 || hexString.length == 7) buffer.write('ff');
    buffer.write(hexString.replaceFirst('#', ''));
    try {
      return Color(int.parse(buffer.toString(), radix: 16));
    } catch (_) {
      return fallback;
    }
  }
}
