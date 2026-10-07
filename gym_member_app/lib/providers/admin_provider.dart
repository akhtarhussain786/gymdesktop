import 'dart:io';
import 'package:flutter/foundation.dart';
import '../core/config/api_config.dart';
import '../core/network/api_service.dart';
import '../models/admin_dashboard_data.dart';
import '../models/admin_member_item.dart';

class AdminProvider extends ChangeNotifier {
  AdminDashboardData? _dashboardData;
  List<AdminMemberItem> _members = [];
  List<Map<String, dynamic>> _rates = [];
  Map<String, dynamic>? _selectedMemberDetail;
  Map<String, dynamic>? _gymQrData;

  bool _isLoadingDashboard = false;
  bool _isLoadingMembers = false;
  bool _isLoadingDetail = false;
  bool _isSubmitting = false;
  String? _errorMessage;

  String _searchQuery = '';
  String _activeFilter = 'all'; // all, dues, expiring, expired, active

  AdminDashboardData? get dashboardData => _dashboardData;
  List<AdminMemberItem> get members => _members;
  List<Map<String, dynamic>> get rates => _rates;
  Map<String, dynamic>? get selectedMemberDetail => _selectedMemberDetail;
  Map<String, dynamic>? get gymQrData => _gymQrData;

  bool get isLoadingDashboard => _isLoadingDashboard;
  bool get isLoadingMembers => _isLoadingMembers;
  bool get isLoadingDetail => _isLoadingDetail;
  bool get isSubmitting => _isSubmitting;
  String? get errorMessage => _errorMessage;

  String get searchQuery => _searchQuery;
  String get activeFilter => _activeFilter;

  // 1. Fetch Admin Dashboard
  Future<void> loadDashboard() async {
    _isLoadingDashboard = true;
    _errorMessage = null;
    notifyListeners();

    try {
      final data = await ApiService.adminGet(ApiConfig.adminDashboard);
      if (data != null) {
        _dashboardData = AdminDashboardData.fromJson(data);
      }
    } on ApiException catch (e) {
      _errorMessage = e.message;
    } catch (e) {
      _errorMessage = 'Failed to load dashboard: $e';
    } finally {
      _isLoadingDashboard = false;
      notifyListeners();
    }
  }

  // 2. Universal Search & Filter Members
  Future<void> searchMembers({String? query, String? filter, bool silent = false}) async {
    if (query != null) _searchQuery = query;
    if (filter != null) _activeFilter = filter;

    if (!silent) {
      _isLoadingMembers = true;
      _errorMessage = null;
      notifyListeners();
    }

    try {
      final queryParams = <String, String>{
        'search': _searchQuery.trim(),
        'filter': _activeFilter,
        'limit': '100',
      };

      final data = await ApiService.adminGet(ApiConfig.adminMembers, queryParams: queryParams);
      if (data != null && data['members'] is List) {
        _members = (data['members'] as List)
            .map((e) => AdminMemberItem.fromJson(Map<String, dynamic>.from(e)))
            .toList();
      }
    } on ApiException catch (e) {
      _errorMessage = e.message;
    } catch (e) {
      _errorMessage = 'Failed to search members: $e';
    } finally {
      _isLoadingMembers = false;
      notifyListeners();
    }
  }

  // 3. Fetch Single Member Detail
  Future<Map<String, dynamic>?> loadMemberDetail(int memberId) async {
    _isLoadingDetail = true;
    _errorMessage = null;
    notifyListeners();

    try {
      final data = await ApiService.adminGet(ApiConfig.adminMemberDetail, queryParams: {'id': memberId.toString()});
      _selectedMemberDetail = data;
      return data;
    } on ApiException catch (e) {
      _errorMessage = e.message;
      return null;
    } catch (e) {
      _errorMessage = 'Failed to load member details: $e';
      return null;
    } finally {
      _isLoadingDetail = false;
      notifyListeners();
    }
  }

  // 4. Fetch Rates / Packages
  Future<void> loadRates() async {
    try {
      final data = await ApiService.adminGet(ApiConfig.adminRates);
      if (data != null && data['rates'] is List) {
        _rates = List<Map<String, dynamic>>.from(data['rates']);
        notifyListeners();
      }
    } catch (_) {}
  }

  // 5. Add Member with Photo & Dues Calculation
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
    required double dueAmount,
    String? dueDate,
    String paymentMethod = 'Cash',
    File? photoFile,
    Uint8List? photoBytes,
    String? photoFileName,
  }) async {
    _isSubmitting = true;
    _errorMessage = null;
    notifyListeners();

    try {
      final fields = <String, String>{
        'fullname': fullname.trim(),
        'phone': phone.trim(),
        'email': email?.trim() ?? '',
        'address': address?.trim() ?? '',
        'gender': gender,
        'services': services,
        'plan_months': planMonths.toString(),
        'total_amount': totalAmount.toStringAsFixed(2),
        'paid_amount': paidAmount.toStringAsFixed(2),
        'due_amount': dueAmount.toStringAsFixed(2),
        'payment_method': paymentMethod,
      };

      if (dueDate != null && dueDate.isNotEmpty) {
        fields['due_date'] = dueDate;
      }

      final res = await ApiService.adminMultipartPost(
        ApiConfig.adminAddMember,
        fields: fields,
        file: photoFile,
        fileBytes: photoBytes,
        fileName: photoFileName,
      );

      // Refresh dashboard & members list
      loadDashboard();
      searchMembers(silent: true);

      return res;
    } on ApiException catch (e) {
      _errorMessage = e.message;
      return null;
    } catch (e) {
      _errorMessage = 'Failed to create member: $e';
      return null;
    } finally {
      _isSubmitting = false;
      notifyListeners();
    }
  }

  // 6. Collect Cash / UPI Payment & Due Clearance
  Future<Map<String, dynamic>?> collectPayment({
    required int memberId,
    required double amount,
    String paymentMethod = 'Cash',
    String paymentType = 'due_clearance',
    String? newDueDate,
    String? notes,
  }) async {
    _isSubmitting = true;
    _errorMessage = null;
    notifyListeners();

    try {
      final body = {
        'member_id': memberId,
        'amount_collected': amount,
        'payment_method': paymentMethod,
        'payment_type': paymentType,
        'new_due_date': newDueDate,
        'notes': notes ?? 'Payment recorded via Admin App',
      };

      final data = await ApiService.adminPost(ApiConfig.adminCollectPayment, body: body);

      // Refresh detail and lists
      await loadMemberDetail(memberId);
      loadDashboard();
      searchMembers(silent: true);

      return data;
    } on ApiException catch (e) {
      _errorMessage = e.message;
      return null;
    } catch (e) {
      _errorMessage = 'Failed to record payment: $e';
      return null;
    } finally {
      _isSubmitting = false;
      notifyListeners();
    }
  }

  // 7. Load Gym UPI QR Code Payload
  Future<void> loadGymQr() async {
    try {
      final data = await ApiService.adminGet(ApiConfig.adminGymQr);
      if (data != null) {
        _gymQrData = Map<String, dynamic>.from(data);
        notifyListeners();
      }
    } catch (_) {}
  }
}
