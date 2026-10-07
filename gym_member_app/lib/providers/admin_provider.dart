import 'dart:typed_data';
import 'package:flutter/material.dart';
import '../core/config/api_config.dart';
import '../core/network/api_service.dart';
import '../models/admin_models.dart';

class AdminProvider extends ChangeNotifier {
  AdminDashboardData? _dashboardData;
  List<AdminMemberItem> _members = [];
  AdminMemberDetail? _memberDetail;
  List<AdminGymRate> _rates = [];
  AdminGymQr? _gymQr;

  bool _isDashboardLoading = false;
  bool _isMembersLoading = false;
  bool _isDetailLoading = false;
  bool _isActionLoading = false;

  String? _errorMessage;
  String _activeFilter = 'all';
  String _searchQuery = '';
  String _activeSort = 'recent';
  int _totalMembers = 0;
  int _currentPage = 1;

  // Getters
  AdminDashboardData? get dashboardData => _dashboardData;
  List<AdminMemberItem> get members => _members;
  AdminMemberDetail? get memberDetail => _memberDetail;
  List<AdminGymRate> get rates => _rates;
  AdminGymQr? get gymQr => _gymQr;

  bool get isDashboardLoading => _isDashboardLoading;
  bool get isMembersLoading => _isMembersLoading;
  bool get isDetailLoading => _isDetailLoading;
  bool get isActionLoading => _isActionLoading;
  String? get errorMessage => _errorMessage;

  String get activeFilter => _activeFilter;
  String get searchQuery => _searchQuery;
  String get activeSort => _activeSort;
  int get totalMembers => _totalMembers;
  int get currentPage => _currentPage;

  // 1. Fetch Admin Dashboard KPIs & Top Dues
  Future<void> fetchDashboard({bool refresh = false}) async {
    if (_dashboardData != null && !refresh && !_isDashboardLoading) return;

    _isDashboardLoading = true;
    _errorMessage = null;
    notifyListeners();

    try {
      final data = await ApiService.get(ApiConfig.adminDashboard, isAdmin: true);
      if (data != null) {
        _dashboardData = AdminDashboardData.fromJson(data);
      }
    } on ApiException catch (e) {
      _errorMessage = e.message;
    } catch (e) {
      _errorMessage = 'Failed to load admin dashboard: ${e.toString()}';
    } finally {
      _isDashboardLoading = false;
      notifyListeners();
    }
  }

  // 2. Fetch Gym Members with Filter & Search
  Future<void> fetchMembers({
    String? search,
    String? filter,
    String? sort,
    int page = 1,
    bool refresh = false,
  }) async {
    if (search != null) _searchQuery = search;
    if (filter != null) _activeFilter = filter;
    if (sort != null) _activeSort = sort;
    _currentPage = page;

    _isMembersLoading = true;
    _errorMessage = null;
    notifyListeners();

    try {
      final queryParams = <String, String>{
        'page': _currentPage.toString(),
        'limit': '50',
        'filter': _activeFilter,
        'sort': _activeSort,
      };
      if (_searchQuery.trim().isNotEmpty) {
        queryParams['search'] = _searchQuery.trim();
      }

      final data = await ApiService.get(
        ApiConfig.adminMembers,
        queryParams: queryParams,
        isAdmin: true,
      );

      if (data != null && data['members'] is List) {
        _members = (data['members'] as List)
            .map((item) => AdminMemberItem.fromJson(item as Map<String, dynamic>))
            .toList();
        if (data['pagination'] != null) {
          _totalMembers = (data['pagination']['total'] is int)
              ? data['pagination']['total']
              : int.tryParse('${data['pagination']['total']}') ?? _members.length;
        }
      }
    } on ApiException catch (e) {
      _errorMessage = e.message;
    } catch (e) {
      _errorMessage = 'Failed to load members: ${e.toString()}';
    } finally {
      _isMembersLoading = false;
      notifyListeners();
    }
  }

  // 3. Fetch Single Member Detail with Dues Breakdown & Invoices
  Future<void> fetchMemberDetail(int memberId) async {
    _isDetailLoading = true;
    _memberDetail = null;
    _errorMessage = null;
    notifyListeners();

    try {
      final data = await ApiService.get(
        ApiConfig.adminMemberDetail,
        queryParams: {'id': memberId.toString()},
        isAdmin: true,
      );

      if (data != null) {
        _memberDetail = AdminMemberDetail.fromJson(data);
      }
    } on ApiException catch (e) {
      _errorMessage = e.message;
    } catch (e) {
      _errorMessage = 'Failed to load member profile: ${e.toString()}';
    } finally {
      _isDetailLoading = false;
      notifyListeners();
    }
  }

  // 4. Fetch Gym Packages / Rates
  Future<void> fetchRates() async {
    try {
      final data = await ApiService.get(ApiConfig.adminRates, isAdmin: true);
      if (data != null && data['rates'] is List) {
        _rates = (data['rates'] as List)
            .map((e) => AdminGymRate.fromJson(e as Map<String, dynamic>))
            .toList();
        notifyListeners();
      }
    } catch (_) {}
  }

  // 5. Fetch Gym UPI QR Payload
  Future<AdminGymQr?> fetchGymQr({double? amount, String? note}) async {
    try {
      final queryParams = <String, String>{};
      if (amount != null && amount > 0) {
        queryParams['amount'] = amount.toStringAsFixed(2);
      }
      if (note != null && note.isNotEmpty) {
        queryParams['note'] = note;
      }

      final data = await ApiService.get(
        ApiConfig.adminGymQr,
        queryParams: queryParams.isNotEmpty ? queryParams : null,
        isAdmin: true,
      );

      if (data != null) {
        _gymQr = AdminGymQr.fromJson(data);
        notifyListeners();
        return _gymQr;
      }
    } catch (_) {}
    return null;
  }

  // 6. Add New Member with Photo & Initial Payment
  Future<Map<String, dynamic>?> addMember({
    required String fullname,
    required String phone,
    String? email,
    String? address,
    String gender = 'Male',
    required String services,
    required int planMonths,
    required double totalAmount,
    required double paidAmount,
    double dueAmount = 0.0,
    String? dueDate,
    String paymentMethod = 'Cash',
    String? password,
    String? dor,
    String? photoPath,
    Uint8List? photoBytes,
  }) async {
    _isActionLoading = true;
    _errorMessage = null;
    notifyListeners();

    try {
      final fields = <String, String>{
        'fullname': fullname.trim(),
        'phone': phone.trim(),
        'gender': gender,
        'services': services,
        'plan_months': planMonths.toString(),
        'total_amount': totalAmount.toStringAsFixed(2),
        'paid_amount': paidAmount.toStringAsFixed(2),
        'due_amount': dueAmount.toStringAsFixed(2),
        'payment_method': paymentMethod,
      };

      if (email != null && email.trim().isNotEmpty) fields['email'] = email.trim();
      if (address != null && address.trim().isNotEmpty) fields['address'] = address.trim();
      if (dueDate != null && dueDate.isNotEmpty) fields['due_date'] = dueDate;
      if (password != null && password.isNotEmpty) fields['password'] = password;
      if (dor != null && dor.isNotEmpty) fields['dor'] = dor;

      dynamic data;
      if (photoPath != null || photoBytes != null) {
        data = await ApiService.multipartPost(
          ApiConfig.adminAddMember,
          fields: fields,
          fileField: 'photo',
          filePath: photoPath,
          fileBytes: photoBytes,
          fileName: 'member_${DateTime.now().millisecondsSinceEpoch}.jpg',
          isAdmin: true,
        );
      } else {
        data = await ApiService.post(
          ApiConfig.adminAddMember,
          body: fields,
          isAdmin: true,
        );
      }

      // Refresh Dashboard & Members
      fetchDashboard(refresh: true);
      fetchMembers(refresh: true);

      _isActionLoading = false;
      notifyListeners();
      return (data is Map<String, dynamic>) ? data : Map<String, dynamic>.from(data ?? {});
    } on ApiException catch (e) {
      _errorMessage = e.message;
      _isActionLoading = false;
      notifyListeners();
      rethrow;
    } catch (e) {
      _errorMessage = 'Failed to add member: ${e.toString()}';
      _isActionLoading = false;
      notifyListeners();
      throw ApiException(_errorMessage!);
    }
  }

  // 7. Collect Payment / Clear Dues / Instant Renewal
  Future<Map<String, dynamic>?> collectPayment({
    required int memberId,
    required double amountCollected,
    String paymentMethod = 'Cash',
    String paymentType = 'due_clearance', // 'due_clearance' or 'renewal'
    String? newDueDate,
    String? notes,
    int? planMonths,
    String? services,
    double? totalPlanFee,
  }) async {
    _isActionLoading = true;
    _errorMessage = null;
    notifyListeners();

    try {
      final body = <String, dynamic>{
        'member_id': memberId,
        'amount_collected': amountCollected,
        'payment_method': paymentMethod,
        'payment_type': paymentType,
      };

      if (newDueDate != null && newDueDate.isNotEmpty) body['new_due_date'] = newDueDate;
      if (notes != null && notes.isNotEmpty) body['notes'] = notes;
      if (planMonths != null) body['plan_months'] = planMonths;
      if (services != null && services.isNotEmpty) body['services'] = services;
      if (totalPlanFee != null) body['total_plan_fee'] = totalPlanFee;

      final data = await ApiService.post(
        ApiConfig.adminCollectPayment,
        body: body,
        isAdmin: true,
      );

      // Refresh Detail & Dashboard
      fetchMemberDetail(memberId);
      fetchDashboard(refresh: true);
      fetchMembers(refresh: true);

      _isActionLoading = false;
      notifyListeners();
      return (data is Map<String, dynamic>) ? data : Map<String, dynamic>.from(data ?? {});
    } on ApiException catch (e) {
      _errorMessage = e.message;
      _isActionLoading = false;
      notifyListeners();
      rethrow;
    } catch (e) {
      _errorMessage = 'Failed to record payment: ${e.toString()}';
      _isActionLoading = false;
      notifyListeners();
      throw ApiException(_errorMessage!);
    }
  }
}
