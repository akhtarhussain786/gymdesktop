import 'package:flutter/material.dart';
import '../core/config/api_config.dart';
import '../core/network/api_service.dart';
import '../models/dashboard_data.dart';

class DashboardProvider extends ChangeNotifier {
  DashboardData? _dashboardData;
  bool _isLoading = false;
  String? _errorMessage;
  // Bumped by clear(): a reply that arrives after logout must not refill the cache for the next account
  int _generation = 0;

  DashboardData? get dashboardData => _dashboardData;
  bool get isLoading => _isLoading;
  String? get errorMessage => _errorMessage;

  Future<void> fetchDashboard({bool refresh = false}) async {
    if (_dashboardData != null && !refresh) return;

    final generation = _generation;
    _isLoading = true;
    _errorMessage = null;
    notifyListeners();

    try {
      final data = await ApiService.get(ApiConfig.dashboard);
      if (generation != _generation) return;
      _dashboardData = DashboardData.fromJson(data);
      _isLoading = false;
      notifyListeners();
    } on ApiException catch (e) {
      if (generation != _generation) return;
      _errorMessage = e.message;
      _isLoading = false;
      notifyListeners();
    } catch (e) {
      if (generation != _generation) return;
      _errorMessage = 'Failed to load dashboard data. Please pull down to retry.';
      _isLoading = false;
      notifyListeners();
    }
  }

  void clear() {
    _generation++;
    _dashboardData = null;
    _errorMessage = null;
    _isLoading = false;
    notifyListeners();
  }
}
