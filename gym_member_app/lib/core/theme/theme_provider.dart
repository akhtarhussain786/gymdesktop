import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'app_colors.dart';
import 'app_theme.dart';

class ThemeProvider extends ChangeNotifier {
  static const String _keyDarkMode = 'is_dark_mode';

  Color _primaryColor = AppColors.defaultPrimary;
  Color _secondaryColor = AppColors.defaultSecondary;
  bool _isDarkMode = true; // Default to modern dark mode

  Color get primaryColor => _primaryColor;
  Color get secondaryColor => _secondaryColor;
  bool get isDarkMode => _isDarkMode;

  ThemeProvider() {
    _loadPreference();
  }

  Future<void> _loadPreference() async {
    final prefs = await SharedPreferences.getInstance();
    _isDarkMode = prefs.getBool(_keyDarkMode) ?? true;
    notifyListeners();
  }

  void updateBranding({String? primaryHex, String? secondaryHex}) {
    bool changed = false;
    if (primaryHex != null && primaryHex.isNotEmpty) {
      final newPrimary = AppColors.fromHex(primaryHex, fallback: _primaryColor);
      if (newPrimary != _primaryColor) {
        _primaryColor = newPrimary;
        changed = true;
      }
    }

    if (secondaryHex != null && secondaryHex.isNotEmpty) {
      final newSecondary = AppColors.fromHex(secondaryHex, fallback: _secondaryColor);
      if (newSecondary != _secondaryColor) {
        _secondaryColor = newSecondary;
        changed = true;
      }
    }

    if (changed) {
      notifyListeners();
    }
  }

  void toggleDarkMode() async {
    _isDarkMode = !_isDarkMode;
    notifyListeners();
    final prefs = await SharedPreferences.getInstance();
    await prefs.setBool(_keyDarkMode, _isDarkMode);
  }

  ThemeData get themeData => AppTheme.buildTheme(
        primaryColor: _primaryColor,
        secondaryColor: _secondaryColor,
        isDark: _isDarkMode,
      );
}
