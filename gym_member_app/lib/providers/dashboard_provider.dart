import 'package:flutter/material.dart';
import '../core/config/api_config.dart';
import '../core/network/api_service.dart';
import '../models/dashboard_data.dart';

class DashboardProvider extends ChangeNotifier {
  DashboardData? _dashboardData;
  bool _isLoading = false;
  String? _errorMessage;

  DashboardData? get dashboardData => _dashboardData;
  bool get isLoading => _isLoading;
  String? get errorMessage => _errorMessage;

  Future<void> fetchDashboard({bool refresh = false}) async {
    if (_dashboardData != null && !refresh) return;

    _isLoading = true;
    _errorMessage = null;
    notifyListeners();

    try {
      final data = await ApiService.get(ApiConfig.dashboard);
      _dashboardData = DashboardData.fromJson(data);
      _isLoading = false;
      notifyListeners();
    } on ApiException catch (e) {
      _errorMessage = e.message;
      _isLoading = false;
      notifyListeners();
    } catch (e) {
      _errorMessage = 'Failed to load dashboard data. Please pull down to retry.';
      _isLoading = false;
      notifyListeners();
    }
  }

  void clear() {
    _dashboardData = null;
    _errorMessage = null;
    _isLoading = false;
    notifyListeners();
  }
}
