import 'dart:async';
import 'package:flutter/foundation.dart';
import '../core/config/api_config.dart';
import '../core/network/api_service.dart';
import '../core/services/push_notification_service.dart';
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

enum _RestoreOutcome { valid, invalid, offline }

class AuthProvider extends ChangeNotifier {
  AuthStatus _status = AuthStatus.initial;
  GymTenant? _currentTenant;
  MemberUser? _currentMember;
  Map<String, dynamic>? _adminUser;
  String _userRole = 'member';
  String? _authToken;
  String? _errorMessage;
  bool _isLoading = false;
  List<Map<String, dynamic>> _availableGyms = [];

  bool _isRestoring = false;
  bool _restoreFailedOffline = false;
  Future<void>? _restoreFuture;
  bool _loggingOut = false;

  /// Invoked whenever the signed-in account or gym changes, so app-wide caches
  /// (dashboard, member data, admin data) never leak into the next account.
  VoidCallback? onSessionCleared;

  AuthStatus get status => _status;
  GymTenant? get currentTenant => _currentTenant;
  MemberUser? get currentMember => _currentMember;
  Map<String, dynamic>? get adminUser => _adminUser;
  String get userRole => _userRole;
  String? get authToken => _authToken;
  String? get errorMessage => _errorMessage;
  bool get isLoading => _isLoading;
  bool get isAdmin => ['gym_admin', 'staff', 'super_admin', 'trainer'].contains(_userRole.toLowerCase());
  bool get isAuthenticated => _status == AuthStatus.authenticated && (_currentMember != null || isAdmin);
  List<Map<String, dynamic>> get availableGyms => _availableGyms;

  /// True while the stored session is being verified at app start.
  bool get isRestoring => _isRestoring;

  /// True when a stored session could not be verified because the server was unreachable.
  /// The session is kept; the user can retry.
  bool get restoreFailedOffline => _restoreFailedOffline;

  /// Completes when the current session restore attempt has finished.
  Future<void> get sessionRestore => _restoreFuture ?? Future.value();

  Future<List<Map<String, dynamic>>> fetchAvailableGyms({String? query}) async {
    try {
      final queryParams = (query != null && query.trim().isNotEmpty) ? {'q': query.trim()} : null;
      final data = await ApiService.get(ApiConfig.listGyms, queryParams: queryParams);
      if (data is List) {
        _availableGyms = List<Map<String, dynamic>>.from(data);
        notifyListeners();
        return _availableGyms;
      }
    } catch (_) {}
    return _availableGyms;
  }

  AuthProvider() {
    ApiService.onSessionExpired = _handleSessionExpiry;
    checkExistingSession();
  }

  void _handleSessionExpiry() {
    logout(silent: true);
  }

  // Check stored session on startup (or when the user taps Retry after an offline start)
  Future<void> checkExistingSession() {
    if (_isRestoring && _restoreFuture != null) return _restoreFuture!;
    _restoreFuture = _restoreSession();
    return _restoreFuture!;
  }

  Future<void> _restoreSession() async {
    _isRestoring = true;
    _restoreFailedOffline = false;
    _isLoading = true;
    notifyListeners();

    try {
      final token = await SecureStorageService.getToken();
      final gymCode = await SecureStorageService.getCurrentGymCode();
      final savedRole = await SecureStorageService.getUserRole();
      final savedUserData = await SecureStorageService.getUserData();

      if (gymCode != null && gymCode.isNotEmpty) {
        await _refreshTenant(gymCode);
      }

      if (token != null && token.isNotEmpty && gymCode != null && gymCode.isNotEmpty && _currentTenant != null) {
        _authToken = token;
        ApiService.activeToken = token;
        ApiService.resetSessionExpiryGuard();
        _userRole = savedRole ?? 'member';
        _adminUser = savedUserData;

        final outcome = await _verifySession();
        if (outcome == _RestoreOutcome.valid) {
          _status = AuthStatus.authenticated;
          _errorMessage = null;
          PushNotificationService.syncDeviceToken();
          return;
        }
        if (outcome == _RestoreOutcome.offline) {
          // Network / server trouble is not a logout: keep the stored session and let the user retry.
          _restoreFailedOffline = true;
          _status = AuthStatus.error;
          _errorMessage = 'Unable to reach the gym server. Please check your connection and try again.';
          return;
        }

        // The server rejected the stored token (401/403): this session is over.
        await SecureStorageService.clearSession();
        _authToken = null;
        ApiService.activeToken = null;
        _currentMember = null;
        _adminUser = null;
        _userRole = 'member';
      }

      _status = (_currentTenant != null) ? AuthStatus.gymIdentified : AuthStatus.unauthenticated;
    } catch (_) {
      _status = (_currentTenant != null) ? AuthStatus.gymIdentified : AuthStatus.unauthenticated;
    } finally {
      _isRestoring = false;
      _isLoading = false;
      notifyListeners();
    }
  }

  // Refresh the remembered gym; when offline fall back to the last saved copy of it.
  Future<void> _refreshTenant(String gymCode) async {
    try {
      final gymData = await ApiService.get(
        ApiConfig.gymLookup,
        queryParams: {'code': gymCode},
        timeout: const Duration(seconds: 12),
      );
      if (gymData is Map) {
        _currentTenant = GymTenant.fromJson(Map<String, dynamic>.from(gymData));
      }
    } catch (e) {
      final offline = e is! ApiException || e.isNetworkOrServerError;
      if (!offline) return;
      try {
        final saved = await SecureStorageService.getSavedGymTenants();
        for (final g in saved) {
          if ((g['gym_code'] ?? '').toString().toUpperCase() == gymCode.toUpperCase()) {
            _currentTenant = GymTenant.fromJson(g);
            break;
          }
        }
      } catch (_) {}
    }
  }

  // Only 401/403 mean the stored session is invalid; anything else is retried and then kept.
  Future<_RestoreOutcome> _verifySession() async {
    for (var attempt = 0; attempt < 2; attempt++) {
      if (attempt > 0) {
        await Future.delayed(const Duration(seconds: 2));
      }
      try {
        if (isAdmin) {
          final adminDash = await ApiService.get(
            ApiConfig.adminDashboard,
            isAdmin: true,
            handleUnauthorized: false,
            timeout: const Duration(seconds: 15),
          );
          if (adminDash != null) return _RestoreOutcome.valid;
        } else {
          final dashboardData = await ApiService.get(
            ApiConfig.dashboard,
            handleUnauthorized: false,
            timeout: const Duration(seconds: 15),
          );
          if (dashboardData is Map) {
            _currentMember = await _buildRestoredMember(Map<String, dynamic>.from(dashboardData));
            return _RestoreOutcome.valid;
          }
        }
      } on ApiException catch (e) {
        if (e.statusCode == 401 || e.statusCode == 403) return _RestoreOutcome.invalid;
      } catch (_) {}
    }
    return _RestoreOutcome.offline;
  }

  // The dashboard's member block lacks gender/address/weights, so merge in the full profile.
  Future<MemberUser> _buildRestoredMember(Map<String, dynamic> dashboard) async {
    final merged = <String, dynamic>{};
    if (dashboard['member'] is Map) {
      merged.addAll(Map<String, dynamic>.from(dashboard['member']));
    }
    if (merged['member_id'] == null && merged['id'] != null) {
      merged['member_id'] = merged['id'];
    }
    final membership = dashboard['membership'];
    if (membership is Map) {
      merged['membership_status'] = membership['status'];
      merged['start_date'] = membership['start_date'];
      merged['expiry_date'] = membership['expiry_date'];
      merged['days_remaining'] = membership['days_remaining'];
      merged['plan_months'] = membership['plan_duration_months'];
    }
    final attendance = dashboard['attendance'];
    if (attendance is Map && attendance['total_lifetime_sessions'] != null) {
      merged['attendance_count'] = attendance['total_lifetime_sessions'];
    }

    try {
      final profile = await ApiService.get(
        ApiConfig.profile,
        handleUnauthorized: false,
        timeout: const Duration(seconds: 10),
      );
      if (profile is Map) {
        const keys = [
          'member_id', 'fullname', 'username', 'email', 'phone', 'gender', 'address', 'avatar',
          'services', 'plan_months', 'current_weight', 'initial_weight', 'body_type',
        ];
        for (final key in keys) {
          if (profile[key] != null) merged[key] = profile[key];
        }
      }
    } catch (_) {}

    merged.removeWhere((key, value) => value == null);
    return MemberUser.fromJson(merged);
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

  // Reset to "Find Your Gym" (also ends any signed-in session for the old gym)
  Future<void> changeGym() async {
    final oldToken = _authToken ?? ApiService.activeToken;
    final oldRole = _userRole;
    final oldGymCode = _currentTenant?.gymCode;

    _currentTenant = null;
    _currentMember = null;
    _adminUser = null;
    _userRole = 'member';
    _authToken = null;
    ApiService.activeToken = null;
    _restoreFailedOffline = false;
    _status = AuthStatus.unauthenticated;
    _errorMessage = null;
    onSessionCleared?.call();
    notifyListeners();

    if (oldToken != null && oldToken.isNotEmpty) {
      await SecureStorageService.clearSession();
      unawaited(_revokeToken(oldToken, oldRole, oldGymCode));
    }
  }

  // 2. Dual Login: Member OR Gym Admin/Staff
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
      // A stable per-install id: the server replaces older sessions of the same device only,
      // so signing in on a second phone no longer signs the first one out.
      final resolvedDeviceId = (deviceId != null && deviceId.isNotEmpty)
          ? deviceId
          : await PushNotificationService.getDeviceId();

      final body = {
        'gym_code': _currentTenant!.gymCode,
        'login_id': loginId.trim(),
        'password': password,
        'device_id': resolvedDeviceId,
        'device_name': deviceName ?? 'Mobile App',
        'platform': defaultTargetPlatform == TargetPlatform.iOS ? 'ios' : 'android',
      };

      // A wrong password answers 401: that is a login error, not an expired session.
      final data = await ApiService.post(
        ApiConfig.login,
        body: body,
        gymCode: _currentTenant!.gymCode,
        handleUnauthorized: false,
      );
      _authToken = data['token'];
      ApiService.activeToken = _authToken;
      ApiService.resetSessionExpiryGuard();
      await SecureStorageService.saveToken(_authToken!);
      await SecureStorageService.saveActiveTenantId(_currentTenant!.id);

      final returnedRole = (data['role'] ?? 'member').toString().toLowerCase();
      _userRole = returnedRole;
      await SecureStorageService.saveUserRole(_userRole);

      // Never show the previous account's cached data to this one
      onSessionCleared?.call();

      if (isAdmin) {
        // Admin / Staff Login
        _adminUser = data['user'] is Map<String, dynamic> ? data['user'] : Map<String, dynamic>.from(data['user'] ?? {});
        _currentMember = null;
        if (data['tenant'] != null) {
          _currentTenant = GymTenant.fromJson(data['tenant']);
        }
        if (_adminUser != null) {
          await SecureStorageService.saveUserData(_adminUser!);
        }
      } else {
        // Regular Member Login
        _adminUser = null;
        if (data['member'] != null) {
          _currentMember = MemberUser.fromJson(data['member']);
        }
        if (data['tenant'] != null) {
          _currentTenant = GymTenant.fromJson(data['tenant']);
        }
      }

      _restoreFailedOffline = false;
      _status = AuthStatus.authenticated;
      _isLoading = false;
      notifyListeners();

      // Automatically sync FCM push notification device token
      PushNotificationService.syncDeviceToken();

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

    final oldToken = _authToken ?? ApiService.activeToken;
    final oldRole = _userRole;
    final oldGymCode = _currentTenant?.gymCode;

    // Clear previous tenant's session & tokens locally first, then revoke the old token in the background
    await SecureStorageService.clearSession();
    _authToken = null;
    ApiService.activeToken = null;
    _currentMember = null;
    _adminUser = null;
    _userRole = 'member';
    _restoreFailedOffline = false;
    onSessionCleared?.call();

    if (oldToken != null && oldToken.isNotEmpty) {
      unawaited(_revokeToken(oldToken, oldRole, oldGymCode));
    }

    final success = await lookupGym(targetGymCode);
    return success;
  }

  // 4. Logout
  Future<void> logout({bool silent = false}) async {
    // Re-entry guard: a 401 from a request made during logout must not start another logout
    if (_loggingOut) return;
    _loggingOut = true;
    try {
      final oldToken = _authToken ?? ApiService.activeToken ?? await SecureStorageService.getToken();
      final oldRole = _userRole;
      final oldGymCode = _currentTenant?.gymCode;

      await SecureStorageService.clearSession();
      _authToken = null;
      ApiService.activeToken = null;
      _currentMember = null;
      _adminUser = null;
      _userRole = 'member';
      _restoreFailedOffline = false;
      _status = (_currentTenant != null) ? AuthStatus.gymIdentified : AuthStatus.unauthenticated;
      onSessionCleared?.call();
      notifyListeners();

      // After a 401 (silent) the token is already dead on the server
      if (!silent && oldToken != null && oldToken.isNotEmpty) {
        unawaited(_revokeToken(oldToken, oldRole, oldGymCode));
      }
    } finally {
      _loggingOut = false;
    }
  }

  // Best effort: unlink this device's push token and delete the session on the server.
  Future<void> _revokeToken(String token, String role, String? gymCode) async {
    await PushNotificationService.unregisterDeviceToken(authToken: token, role: role, gymCode: gymCode);
    try {
      await ApiService.post(
        ApiConfig.logout,
        gymCode: gymCode,
        bearerToken: token,
        handleUnauthorized: false,
        timeout: const Duration(seconds: 15),
      );
    } catch (_) {}
  }

  void updateCurrentTenant({
    String? gymName,
    String? phone,
    String? email,
    String? address,
    String? currency,
    String? upiId,
  }) {
    if (_currentTenant != null) {
      _currentTenant = GymTenant(
        id: _currentTenant!.id,
        gymCode: _currentTenant!.gymCode,
        gymName: gymName ?? _currentTenant!.gymName,
        slug: _currentTenant!.slug,
        logo: _currentTenant!.logo,
        address: address ?? _currentTenant!.address,
        phone: phone ?? _currentTenant!.phone,
        email: email ?? _currentTenant!.email,
        currency: currency ?? _currentTenant!.currency,
        timezone: _currentTenant!.timezone,
        primaryColor: _currentTenant!.primaryColor,
        secondaryColor: _currentTenant!.secondaryColor,
        planName: _currentTenant!.planName,
        upiId: upiId ?? _currentTenant!.upiId,
        branches: _currentTenant!.branches,
        features: _currentTenant!.features,
      );
      notifyListeners();
    }
  }
}
