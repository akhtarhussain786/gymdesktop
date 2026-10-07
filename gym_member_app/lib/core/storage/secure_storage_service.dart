import 'dart:convert';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:shared_preferences/shared_preferences.dart';

class SecureStorageService {
  static const _storage = FlutterSecureStorage(
    aOptions: AndroidOptions(encryptedSharedPreferences: true),
  );

  static const String _keyToken = 'auth_token';
  static const String _keyCurrentGymCode = 'current_gym_code';
  static const String _keySavedGyms = 'saved_gym_tenants';
  static const String _keyActiveTenantId = 'active_tenant_id';

  // Save active bearer token
  static Future<void> saveToken(String token) async {
    await _storage.write(key: _keyToken, value: token);
  }

  // Get active bearer token
  static Future<String?> getToken() async {
    return await _storage.read(key: _keyToken);
  }

  // Save current selected gym code
  static Future<void> saveCurrentGymCode(String gymCode) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_keyCurrentGymCode, gymCode);
  }

  static Future<String?> getCurrentGymCode() async {
    final prefs = await SharedPreferences.getInstance();
    return prefs.getString(_keyCurrentGymCode);
  }

  // Save active tenant ID
  static Future<void> saveActiveTenantId(int tenantId) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setInt(_keyActiveTenantId, tenantId);
  }

  static Future<int?> getActiveTenantId() async {
    final prefs = await SharedPreferences.getInstance();
    return prefs.getInt(_keyActiveTenantId);
  }

  // Save cached gym tenants for Multi-Gym Switcher
  static Future<void> saveGymTenants(List<Map<String, dynamic>> gyms) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_keySavedGyms, jsonEncode(gyms));
  }

  static Future<List<Map<String, dynamic>>> getSavedGymTenants() async {
    final prefs = await SharedPreferences.getInstance();
    final raw = prefs.getString(_keySavedGyms);
    if (raw == null || raw.isEmpty) return [];
    try {
      final List<dynamic> list = jsonDecode(raw);
      return list.map((e) => Map<String, dynamic>.from(e)).toList();
    } catch (_) {
      return [];
    }
  }

  // Tenant-Safe Local Cache: Prefix key with tenantId
  static Future<void> setTenantCache(int tenantId, String key, String value) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString('tenant_${tenantId}_$key', value);
  }

  static Future<String?> getTenantCache(int tenantId, String key) async {
    final prefs = await SharedPreferences.getInstance();
    return prefs.getString('tenant_${tenantId}_$key');
  }

  // Purge all data for a specific tenant on logout or reset
  static Future<void> purgeTenantData(int tenantId) async {
    final prefs = await SharedPreferences.getInstance();
    final keys = prefs.getKeys().where((k) => k.startsWith('tenant_${tenantId}_')).toList();
    for (final k in keys) {
      await prefs.remove(k);
    }
  }

  // Clear current active session (logout)
  static Future<void> clearSession() async {
    await _storage.delete(key: _keyToken);
    final tenantId = await getActiveTenantId();
    if (tenantId != null) {
      await purgeTenantData(tenantId);
    }
  }

  // Reset entire application data
  static Future<void> resetAll() async {
    await _storage.deleteAll();
    final prefs = await SharedPreferences.getInstance();
    await prefs.clear();
  }
}
