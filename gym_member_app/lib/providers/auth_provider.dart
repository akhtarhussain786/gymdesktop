import 'package:flutter/material.dart';
import '../core/config/api_config.dart';
import '../core/network/api_service.dart';
import '../core/storage/secure_storage_service.dart';
import '../models/gym_tenant.dart';
import '../models/member_user.dart';

enum AuthStatus {
  initial,
  gymLookupPending,
  gymIdentified,
  authenticated,
  unauthenticated,
  error,
}

class AuthProvider extends ChangeNotifier {
  AuthStatus _status = AuthStatus.initial;
  GymTenant? _currentTenant;
  MemberUser? _currentMember;
  String? _authToken;
  String? _errorMessage;
  bool _isLoading = false;

  AuthStatus get status => _status;
  GymTenant? get currentTenant => _currentTenant;
  MemberUser? get currentMember => _currentMember;
  String? get authToken => _authToken;
  String? get errorMessage => _errorMessage;
  bool get isLoading => _isLoading;
  bool get isAuthenticated => _status == AuthStatus.authenticated && _currentMember != null;

  AuthProvider() {
    ApiService.onSessionExpired = _handleSessionExpiry;
    checkExistingSession();
  }

  void _handleSessionExpiry() {
    logout(silent: true);
  }

  // Check stored session on startup
  Future<void> checkExistingSession() async {
    _isLoading = true;
    notifyListeners();

    try {
      final token = await SecureStorageService.getToken();
      final gymCode = await SecureStorageService.getCurrentGymCode();

      if (token != null && token.isNotEmpty && gymCode != null && gymCode.isNotEmpty) {
        _authToken = token;
        // Verify gym and fetch dashboard to restore session
        final gymData = await ApiService.get(ApiConfig.gymLookup, queryParams: {'code': gymCode});
        if (gymData != null) {
          _currentTenant = GymTenant.fromJson(gymData);
          final dashboardData = await ApiService.get(ApiConfig.dashboard);
          if (dashboardData != null) {
            _currentMember = MemberUser.fromJson(dashboardData['member'] ?? {});
            _status = AuthStatus.authenticated;
            _isLoading = false;
            notifyListeners();
            return;
          }
        }
      }

      // If no active auth, check if gym is remembered
      if (gymCode != null && gymCode.isNotEmpty) {
        final gymData = await ApiService.get(ApiConfig.gymLookup, queryParams: {'code': gymCode});
        if (gymData != null) {
          _currentTenant = GymTenant.fromJson(gymData);
          _status = AuthStatus.gymIdentified;
          _isLoading = false;
          notifyListeners();
          return;
        }
      }
    } catch (_) {
      // Fallback to gym lookup screen
    }

    _status = AuthStatus.unauthenticated;
    _isLoading = false;
    notifyListeners();
  }

  // 1. Gym Identification Flow: Validate Gym Code
  Future<bool> lookupGym(String gymCode) async {
    _isLoading = true;
    _errorMessage = null;
    notifyListeners();

    try {
      final data = await ApiService.get(ApiConfig.gymLookup, queryParams: {'code': gymCode.trim()});
      _currentTenant = GymTenant.fromJson(data);
      await SecureStorageService.saveCurrentGymCode(_currentTenant!.gymCode);
      await SecureStorageService.saveActiveTenantId(_currentTenant!.id);

      // Add to saved gyms list for switcher
      final saved = await SecureStorageService.getSavedGymTenants();
      final exists = saved.any((g) => g['id'] == _currentTenant!.id);
      if (!exists) {
        saved.add(_currentTenant!.toJson());
        await SecureStorageService.saveGymTenants(saved);
      }

      _status = AuthStatus.gymIdentified;
      _isLoading = false;
      notifyListeners();
      return true;
    } on ApiException catch (e) {
      _errorMessage = e.message;
      _status = AuthStatus.error;
      _isLoading = false;
      notifyListeners();
      return false;
    } catch (e) {
      _errorMessage = 'Unable to connect to gym server. Please check your network.';
      _status = AuthStatus.error;
      _isLoading = false;
      notifyListeners();
      return false;
    }
  }

  // Reset to "Find Your Gym"
  void changeGym() {
    _currentTenant = null;
    _currentMember = null;
    _authToken = null;
    _status = AuthStatus.unauthenticated;
    _errorMessage = null;
    notifyListeners();
  }

  // 2. Member Login
  Future<bool> login({
    required String loginId,
    required String password,
    String? deviceId,
    String? deviceName,
  }) async {
    if (_currentTenant == null) {
      _errorMessage = 'No gym selected. Please find your gym first.';
      notifyListeners();
      return false;
    }

    _isLoading = true;
    _errorMessage = null;
    notifyListeners();

    try {
      final body = {
        'gym_code': _currentTenant!.gymCode,
        'login_id': loginId.trim(),
        'password': password,
        'device_id': deviceId ?? 'flutter_device',
        'device_name': deviceName ?? 'Mobile App',
        'platform': 'flutter',
      };

      final data = await ApiService.post(ApiConfig.login, body: body, gymCode: _currentTenant!.gymCode);
      _authToken = data['token'];
      await SecureStorageService.saveToken(_authToken!);
      await SecureStorageService.saveActiveTenantId(_currentTenant!.id);

      _currentMember = MemberUser.fromJson(data['member']);
      _currentTenant = GymTenant.fromJson(data['tenant']);

      _status = AuthStatus.authenticated;
      _isLoading = false;
      notifyListeners();
      return true;
    } on ApiException catch (e) {
      _errorMessage = e.message;
      _isLoading = false;
      notifyListeners();
      return false;
    } catch (e) {
      _errorMessage = 'Login failed. Please check your network and credentials.';
      _isLoading = false;
      notifyListeners();
      return false;
    }
  }

  // 3. Switch Gym Tenant (Multi-Gym Member Support)
  Future<bool> switchGym(String targetGymCode) async {
    _isLoading = true;
    notifyListeners();

    // Clear previous tenant's session & tokens
    await SecureStorageService.clearSession();
    _authToken = null;
    _currentMember = null;

    final success = await lookupGym(targetGymCode);
    return success;
  }

  // 4. Logout
  Future<void> logout({bool silent = false}) async {
    if (!silent) {
      try {
        await ApiService.post(ApiConfig.logout);
      } catch (_) {}
    }

    await SecureStorageService.clearSession();
    _authToken = null;
    _currentMember = null;
    _status = (_currentTenant != null) ? AuthStatus.gymIdentified : AuthStatus.unauthenticated;
    notifyListeners();
  }
}
