import 'dart:typed_data';
import 'package:flutter/material.dart';
import '../core/config/api_config.dart';
import '../core/network/api_service.dart';
import '../models/admin_models.dart';
import '../models/admin_transaction_models.dart';

class AdminProvider extends ChangeNotifier {
  AdminDashboardData? _dashboardData;
  List<AdminMemberItem> _members = [];
  AdminMemberDetail? _memberDetail;
  List<AdminGymRate> _rates = [];
  AdminGymQr? _gymQr;

  // Transaction history state
  AdminTransactionSummary? _transactionSummary;
  List<AdminTransactionItem> _transactions = [];
  List<StaffFilterOption> _staffFilterOptions = [];
  bool _isTransactionsLoading = false;
  bool _isTransactionsPaginationLoading = false;
  int _txnCurrentPage = 1;
  int _txnTotalPages = 1;
  int _txnTotalCount = 0;
  bool _txnHasMore = false;

  // New features state
  List<AdminStaffItem> _staffs = [];
  List<AdminDailyAttendanceItem> _attendanceLogs = [];
  List<AdminExpenseItem> _expenses = [];
  double _totalExpenses = 0.0;
  List<AdminEquipmentItem> _equipmentList = [];
  double _equipmentValuation = 0.0;
  int _equipmentUnits = 0;
  List<AdminWorkoutItem> _workoutPlans = [];
  List<AdminDietItem> _dietPlans = [];
  List<AdminClassItem> _classes = [];
  List<AdminAnnouncementItem> _announcements = [];
  List<AdminInquiryItem> _inquiries = [];
  int _openInquiriesCount = 0;
  AdminReportsData? _reportsData;
  AdminSettingsData? _settingsData;
  AdminSaasSubscriptionInfo? _saasSubscription;
  List<AdminSaasPlanItem> _saasPlans = [];
  List<AdminSaasPaymentHistoryItem> _saasHistory = [];

  bool _isDashboardLoading = false;
  bool _isMembersLoading = false;
  bool _isDetailLoading = false;
  bool _isActionLoading = false;
  bool _isSectionLoading = false;
  bool _isSaasLoading = false;

  String? _errorMessage;
  String _activeFilter = 'all';
  String _searchQuery = '';
  String _activeSort = 'recent';
  int _totalMembers = 0;
  int _currentPage = 1;
  int _totalMemberPages = 1;
  bool _isMembersLoadingMore = false;
  int _membersRequestSeq = 0;
  static const int _membersPageSize = 50;

  // Every member (all pages) for pickers such as "assign plan" / "mark attendance"
  List<AdminMemberItem> _allMembers = [];
  bool _isAllMembersLoading = false;

  // Raw settings as loaded from the server, so a save re-sends the gym's own values (never defaults)
  Map<String, dynamic>? _settingsRaw;

  // Bumped by clearAll(): replies that arrive after logout / gym switch are dropped
  int _generation = 0;

  // Getters
  AdminDashboardData? get dashboardData => _dashboardData;
  List<AdminMemberItem> get members => _members;
  AdminMemberDetail? get memberDetail => _memberDetail;
  List<AdminGymRate> get rates => _rates;
  AdminGymQr? get gymQr => _gymQr;

  // Transaction history getters
  AdminTransactionSummary? get transactionSummary => _transactionSummary;
  List<AdminTransactionItem> get transactions => _transactions;
  List<StaffFilterOption> get staffFilterOptions => _staffFilterOptions;
  bool get isTransactionsLoading => _isTransactionsLoading;
  bool get isTransactionsPaginationLoading => _isTransactionsPaginationLoading;
  int get txnCurrentPage => _txnCurrentPage;
  int get txnTotalPages => _txnTotalPages;
  int get txnTotalCount => _txnTotalCount;
  bool get txnHasMore => _txnHasMore;

  List<AdminStaffItem> get staffs => _staffs;
  List<AdminDailyAttendanceItem> get attendanceLogs => _attendanceLogs;
  List<AdminExpenseItem> get expenses => _expenses;
  double get totalExpenses => _totalExpenses;
  List<AdminEquipmentItem> get equipmentList => _equipmentList;
  double get equipmentValuation => _equipmentValuation;
  int get equipmentUnits => _equipmentUnits;
  List<AdminWorkoutItem> get workoutPlans => _workoutPlans;
  List<AdminDietItem> get dietPlans => _dietPlans;
  List<AdminClassItem> get classes => _classes;
  List<AdminAnnouncementItem> get announcements => _announcements;
  List<AdminInquiryItem> get inquiries => _inquiries;
  int get openInquiriesCount => _openInquiriesCount;
  AdminReportsData? get reportsData => _reportsData;
  AdminSettingsData? get settingsData => _settingsData;

  AdminSaasSubscriptionInfo? get saasSubscription => _saasSubscription;
  List<AdminSaasPlanItem> get saasPlans => _saasPlans;
  List<AdminSaasPaymentHistoryItem> get saasHistory => _saasHistory;
  bool get isSaasLoading => _isSaasLoading;

  bool get isDashboardLoading => _isDashboardLoading;
  bool get isMembersLoading => _isMembersLoading;
  bool get isDetailLoading => _isDetailLoading;
  bool get isActionLoading => _isActionLoading;
  bool get isSectionLoading => _isSectionLoading;
  String? get errorMessage => _errorMessage;

  String get activeFilter => _activeFilter;
  String get searchQuery => _searchQuery;
  String get activeSort => _activeSort;
  int get totalMembers => _totalMembers;
  int get currentPage => _currentPage;
  bool get hasMoreMembers => _currentPage < _totalMemberPages;
  bool get isMembersLoadingMore => _isMembersLoadingMore;
  List<AdminMemberItem> get allMembers => _allMembers;
  bool get isAllMembersLoading => _isAllMembersLoading;

  /// Drops every cached admin module (logout / gym switch / new login).
  void clearAll() {
    _generation++;
    _dashboardData = null;
    _members = [];
    _memberDetail = null;
    _rates = [];
    _gymQr = null;
    _transactionSummary = null;
    _transactions = [];
    _staffFilterOptions = [];
    _isTransactionsLoading = false;
    _isTransactionsPaginationLoading = false;
    _txnCurrentPage = 1;
    _txnTotalPages = 1;
    _txnTotalCount = 0;
    _txnHasMore = false;
    _staffs = [];
    _attendanceLogs = [];
    _expenses = [];
    _totalExpenses = 0.0;
    _equipmentList = [];
    _equipmentValuation = 0.0;
    _equipmentUnits = 0;
    _workoutPlans = [];
    _dietPlans = [];
    _classes = [];
    _announcements = [];
    _inquiries = [];
    _openInquiriesCount = 0;
    _reportsData = null;
    _settingsData = null;
    _settingsRaw = null;
    _saasSubscription = null;
    _saasPlans = [];
    _saasHistory = [];
    _isDashboardLoading = false;
    _isMembersLoading = false;
    _isDetailLoading = false;
    _isActionLoading = false;
    _isSectionLoading = false;
    _isSaasLoading = false;
    _errorMessage = null;
    _activeFilter = 'all';
    _searchQuery = '';
    _activeSort = 'recent';
    _totalMembers = 0;
    _currentPage = 1;
    _totalMemberPages = 1;
    _isMembersLoadingMore = false;
    _membersRequestSeq++;
    _allMembers = [];
    _isAllMembersLoading = false;
    notifyListeners();
  }

  static int _asInt(dynamic v, int fallback) => v is int ? v : (v is num ? v.toInt() : int.tryParse('$v') ?? fallback);
  static double _asDouble(dynamic v, double fallback) => v is num ? v.toDouble() : double.tryParse('$v') ?? fallback;

  // 1. Fetch Admin Dashboard KPIs
  Future<void> fetchDashboard({bool refresh = false}) async {
    if (_dashboardData != null && !refresh && !_isDashboardLoading) return;

    final generation = _generation;
    _isDashboardLoading = true;
    _errorMessage = null;
    notifyListeners();

    try {
      final data = await ApiService.get(ApiConfig.adminDashboard, isAdmin: true);
      if (generation != _generation) return;
      if (data != null) {
        _dashboardData = AdminDashboardData.fromJson(data);
      }
    } on ApiException catch (e) {
      if (generation != _generation) return;
      _errorMessage = e.message;
    } catch (e) {
      if (generation != _generation) return;
      _errorMessage = 'Failed to load admin dashboard: ${e.toString()}';
    } finally {
      if (generation == _generation) {
        _isDashboardLoading = false;
        notifyListeners();
      }
    }
  }

  // 2. Fetch Gym Members with Filter & Search (first page; use loadMoreMembers() for the next pages)
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

    // Search fires on every keystroke: only the newest request may update the list
    final seq = ++_membersRequestSeq;
    _isMembersLoading = true;
    _isMembersLoadingMore = false;
    _errorMessage = null;
    notifyListeners();

    try {
      final data = await _requestMembersPage(page);
      if (seq != _membersRequestSeq) return;
      if (data != null) {
        _members = data.items;
        _currentPage = data.page;
        _totalMemberPages = data.totalPages;
        _totalMembers = data.total;
      }
    } on ApiException catch (e) {
      if (seq != _membersRequestSeq) return;
      _errorMessage = e.message;
    } catch (e) {
      if (seq != _membersRequestSeq) return;
      _errorMessage = 'Failed to load members: ${e.toString()}';
    } finally {
      if (seq == _membersRequestSeq) {
        _isMembersLoading = false;
        notifyListeners();
      }
    }
  }

  /// Appends the next page of the current filter/search to [members].
  Future<void> loadMoreMembers() async {
    if (_isMembersLoading || _isMembersLoadingMore || !hasMoreMembers) return;
    final seq = _membersRequestSeq;
    _isMembersLoadingMore = true;
    notifyListeners();
    try {
      final data = await _requestMembersPage(_currentPage + 1);
      if (seq != _membersRequestSeq) return;
      if (data != null) {
        final known = _members.map((m) => m.memberId).toSet();
        _members = [..._members, ...data.items.where((m) => !known.contains(m.memberId))];
        _currentPage = data.page;
        _totalMemberPages = data.totalPages;
        _totalMembers = data.total;
      }
    } catch (e) {
      if (seq != _membersRequestSeq) return;
      _errorMessage = e is ApiException ? e.message : 'Failed to load more members.';
    } finally {
      if (seq == _membersRequestSeq) {
        _isMembersLoadingMore = false;
        notifyListeners();
      }
    }
  }

  Future<({List<AdminMemberItem> items, int page, int totalPages, int total})?> _requestMembersPage(
    int page, {
    String? filter,
    String? sort,
    String? search,
    int limit = _membersPageSize,
  }) async {
    final queryParams = <String, String>{
      'page': page.toString(),
      'limit': limit.toString(),
      'filter': filter ?? _activeFilter,
      'sort': sort ?? _activeSort,
    };
    final query = search ?? _searchQuery;
    if (query.trim().isNotEmpty) {
      queryParams['search'] = query.trim();
    }

    final data = await ApiService.get(
      ApiConfig.adminMembers,
      queryParams: queryParams,
      isAdmin: true,
    );
    if (data is! Map || data['members'] is! List) return null;

    final items = (data['members'] as List)
        .map((item) => AdminMemberItem.fromJson(item as Map<String, dynamic>))
        .toList();
    final pagination = data['pagination'] is Map ? data['pagination'] as Map : const {};
    final total = _asInt(pagination['total'], items.length);
    final currentPage = _asInt(pagination['page'], page);
    final totalPages = pagination.containsKey('total_pages')
        ? _asInt(pagination['total_pages'], currentPage)
        : (items.length >= limit ? currentPage + 1 : currentPage);
    return (items: items, page: currentPage, totalPages: totalPages, total: total);
  }

  /// Loads every member (all pages, A-Z) for pickers that must offer the whole member list.
  Future<List<AdminMemberItem>> fetchAllMembers() async {
    if (_isAllMembersLoading) return _allMembers;
    final generation = _generation;
    _isAllMembersLoading = true;
    notifyListeners();
    try {
      final collected = <AdminMemberItem>[];
      final seen = <int>{};
      var page = 1;
      // Hard stop keeps a broken pagination answer from looping forever (100 x 100 members)
      while (page <= 100) {
        final data = await _requestMembersPage(page, filter: 'all', sort: 'name_asc', search: '', limit: 100);
        if (generation != _generation) return const [];
        if (data == null) break;
        for (final m in data.items) {
          if (seen.add(m.memberId)) collected.add(m);
        }
        if (data.items.isEmpty || page >= data.totalPages) break;
        page++;
      }
      _allMembers = collected;
    } catch (_) {
      // Keep whatever was loaded before
    } finally {
      if (generation == _generation) {
        _isAllMembersLoading = false;
        notifyListeners();
      }
    }
    return _allMembers;
  }

  // 3. Fetch Single Member Detail
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

  Future<void> addRate({required String name, required double charge, String type = 'Monthly'}) async {
    await ApiService.post(
      ApiConfig.adminRates,
      body: {'action': 'add', 'name': name, 'charge': charge, 'type': type},
      isAdmin: true,
    );
    await fetchRates();
  }

  Future<void> deleteRate(int id) async {
    await ApiService.post(
      ApiConfig.adminRates,
      body: {'action': 'delete', 'id': id},
      isAdmin: true,
    );
    await fetchRates();
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

  // 6. Add New Member
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
    String? expiryDate,
    String? transactionRef,
    String? notes,
    String? photoPath,
    Uint8List? photoBytes,
    String? username,
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
      // No password -> the server generates a random temporary one and returns it
      if (password != null && password.isNotEmpty) fields['password'] = password;
      if (username != null && username.trim().isNotEmpty) fields['username'] = username.trim();
      if (dor != null && dor.isNotEmpty) fields['dor'] = dor;
      if (expiryDate != null && expiryDate.isNotEmpty) fields['expiry_date'] = expiryDate;
      if (transactionRef != null && transactionRef.trim().isNotEmpty) fields['transaction_ref'] = transactionRef.trim();
      if (notes != null && notes.trim().isNotEmpty) fields['notes'] = notes.trim();

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

  // 7. Update & Delete Member
  Future<void> updateMember({
    required int memberId,
    required String fullname,
    required String phone,
    String? email,
    String? address,
    String gender = 'Male',
    required String services,
    required int planMonths,
    String status = 'Active',
    int? trainerId,
    String? expiryDate,
    String? dor,
    double? currWeight,
    String? currBodytype,
    String? photoBase64,
  }) async {
    _isActionLoading = true;
    notifyListeners();
    try {
      final body = <String, dynamic>{
        'action': 'update',
        'member_id': memberId,
        'fullname': fullname,
        'contact': phone,
        'gender': gender,
        'services': services,
        'plan': planMonths,
        'status': status,
      };
      if (email != null) body['email'] = email;
      if (address != null) body['address'] = address;
      if (trainerId != null) body['trainer_id'] = trainerId;
      if (expiryDate != null) body['expiry_date'] = expiryDate;
      if (dor != null) body['dor'] = dor;
      if (currWeight != null) body['curr_weight'] = currWeight;
      if (currBodytype != null) body['curr_bodytype'] = currBodytype;
      if (photoBase64 != null) body['photo_base64'] = photoBase64;

      await ApiService.post(ApiConfig.adminEditMember, body: body, isAdmin: true);
      await fetchMembers(refresh: true);
      await fetchMemberDetail(memberId);
    } finally {
      _isActionLoading = false;
      notifyListeners();
    }
  }

  Future<void> deleteMember(int memberId) async {
    _isActionLoading = true;
    notifyListeners();
    try {
      await ApiService.post(
        ApiConfig.adminEditMember,
        body: {'action': 'delete', 'member_id': memberId},
        isAdmin: true,
      );
      await fetchMembers(refresh: true);
      await fetchDashboard(refresh: true);
    } finally {
      _isActionLoading = false;
      notifyListeners();
    }
  }

  // 8. Collect Payment / Settle Dues
  Future<Map<String, dynamic>?> collectPayment({
    required int memberId,
    required double amountCollected,
    String paymentMethod = 'Cash',
    String paymentType = 'due_clearance',
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

  // 9. Staff & Trainers
  Future<void> fetchStaffs() async {
    _isSectionLoading = true;
    notifyListeners();
    try {
      final data = await ApiService.get(ApiConfig.adminStaffs, isAdmin: true);
      if (data != null && data['staffs'] is List) {
        _staffs = (data['staffs'] as List).map((e) => AdminStaffItem.fromJson(e as Map<String, dynamic>)).toList();
      }
    } catch (_) {} finally {
      _isSectionLoading = false;
      notifyListeners();
    }
  }

  /// Adds a staff member. Username and password are optional: when left empty the server
  /// generates them. Returns the server's data (may contain the generated username / temporary password).
  Future<Map<String, dynamic>> addStaff({
    required String fullname,
    required String phone,
    String? email,
    required String designation,
    String gender = 'Male',
    String? address,
    double salary = 0.0,
    String? username,
    String? password,
  }) async {
    final body = <String, dynamic>{
      'action': 'add',
      'fullname': fullname,
      'contact': phone,
      'email': email ?? '',
      'designation': designation,
      'gender': gender,
      'address': address ?? '',
      'salary': salary,
    };
    if (username != null && username.trim().isNotEmpty) body['username'] = username.trim();
    if (password != null && password.isNotEmpty) body['password'] = password;

    final envelope = await ApiService.post(
      ApiConfig.adminStaffs,
      body: body,
      isAdmin: true,
      returnEnvelope: true,
    );
    await fetchStaffs();
    final result = <String, dynamic>{};
    if (envelope is Map) {
      if (envelope['data'] is Map) result.addAll(Map<String, dynamic>.from(envelope['data']));
      if (envelope['message'] != null) result['message'] = envelope['message'];
    }
    return result;
  }

  Future<void> deleteStaff(int id) async {
    await ApiService.post(
      ApiConfig.adminStaffs,
      body: {'action': 'delete', 'id': id},
      isAdmin: true,
    );
    await fetchStaffs();
  }

  // 10. Attendance Management
  Future<void> fetchAttendanceLogs({String? date}) async {
    _isSectionLoading = true;
    notifyListeners();
    try {
      final queryParams = <String, String>{};
      if (date != null && date.isNotEmpty) queryParams['date'] = date;
      final data = await ApiService.get(ApiConfig.adminAttendance, queryParams: queryParams.isNotEmpty ? queryParams : null, isAdmin: true);
      final rows = (data is Map) ? (data['records'] is List ? data['records'] : data['attendance']) : null;
      if (rows is List) {
        _attendanceLogs = rows.map((e) => AdminDailyAttendanceItem.fromJson(Map<String, dynamic>.from(e as Map))).toList();
      }
    } catch (_) {} finally {
      _isSectionLoading = false;
      notifyListeners();
    }
  }

  Future<void> markAttendance({required int memberId, String action = 'checkin', String? qrCode}) async {
    await ApiService.post(
      ApiConfig.adminAttendance,
      body: {'action': action, 'member_id': memberId, 'qr_code': qrCode ?? ''},
      isAdmin: true,
    );
    await fetchAttendanceLogs();
    await fetchDashboard(refresh: true);
  }

  // 11. Expenses Management
  Future<void> fetchExpenses({String? month, String? category}) async {
    _isSectionLoading = true;
    notifyListeners();
    try {
      final queryParams = <String, String>{};
      if (month != null && month.isNotEmpty) queryParams['month'] = month;
      if (category != null && category.isNotEmpty) queryParams['category'] = category;
      final data = await ApiService.get(ApiConfig.adminExpenses, queryParams: queryParams.isNotEmpty ? queryParams : null, isAdmin: true);
      if (data != null && data['expenses'] is List) {
        _expenses = (data['expenses'] as List).map((e) => AdminExpenseItem.fromJson(e as Map<String, dynamic>)).toList();
        _totalExpenses = _asDouble(data['total_expense'] ?? data['total_amount'], 0.0);
      }
    } catch (_) {} finally {
      _isSectionLoading = false;
      notifyListeners();
    }
  }

  Future<void> addExpense({
    required String title,
    required double amount,
    required String category,
    required String expenseDate,
    String? notes,
  }) async {
    await ApiService.post(
      ApiConfig.adminExpenses,
      body: {
        'action': 'add',
        'title': title,
        'amount': amount,
        'category': category,
        'expense_date': expenseDate,
        'notes': notes ?? '',
      },
      isAdmin: true,
    );
    await fetchExpenses();
    await fetchDashboard(refresh: true);
  }

  Future<void> deleteExpense(int id) async {
    await ApiService.post(
      ApiConfig.adminExpenses,
      body: {'action': 'delete', 'id': id},
      isAdmin: true,
    );
    await fetchExpenses();
    await fetchDashboard(refresh: true);
  }

  // 12. Equipment Inventory
  Future<void> fetchEquipment() async {
    _isSectionLoading = true;
    notifyListeners();
    try {
      final data = await ApiService.get(ApiConfig.adminEquipment, isAdmin: true);
      final rows = (data is Map) ? (data['equipments'] is List ? data['equipments'] : data['equipment']) : null;
      if (rows is List) {
        _equipmentList = rows.map((e) => AdminEquipmentItem.fromJson(Map<String, dynamic>.from(e as Map))).toList();
        _equipmentValuation = _asDouble(data['total_valuation'], 0.0);
        _equipmentUnits = _asInt(data['total_units'] ?? data['total_quantity'], 0);
      }
    } catch (_) {} finally {
      _isSectionLoading = false;
      notifyListeners();
    }
  }

  Future<void> addEquipment({
    required String name,
    required int quantity,
    required double amount,
    String? vendor,
    String? contact,
    String? date,
    String? description,
  }) async {
    await ApiService.post(
      ApiConfig.adminEquipment,
      body: {
        'action': 'add',
        'name': name,
        'quantity': quantity,
        'amount': amount,
        'vendor': vendor ?? '',
        'contact': contact ?? '',
        'date': date ?? '',
        'description': description ?? '',
      },
      isAdmin: true,
    );
    await fetchEquipment();
  }

  Future<void> deleteEquipment(int id) async {
    await ApiService.post(
      ApiConfig.adminEquipment,
      body: {'action': 'delete', 'id': id},
      isAdmin: true,
    );
    await fetchEquipment();
  }

  // 13. Workout & Diet Plans
  Future<void> fetchWorkouts() async {
    _isSectionLoading = true;
    notifyListeners();
    try {
      final data = await ApiService.get(ApiConfig.adminWorkouts, isAdmin: true);
      final rows = (data is Map) ? (data['plans'] is List ? data['plans'] : data['workout_plans']) : null;
      if (rows is List) {
        _workoutPlans = rows.map((e) => AdminWorkoutItem.fromJson(Map<String, dynamic>.from(e as Map))).toList();
      }
    } catch (_) {} finally {
      _isSectionLoading = false;
      notifyListeners();
    }
  }

  Future<void> addWorkout({
    required String name,
    required String goal,
    required String daysJson,
    required String exercisesJson,
    String? description,
  }) async {
    // The server stores the routine as schedule_json (plain text); days/exercises are kept for older servers
    final schedule = [daysJson.trim(), exercisesJson.trim()].where((part) => part.isNotEmpty).join('\n\n');
    await ApiService.post(
      ApiConfig.adminWorkouts,
      body: {
        'action': 'add',
        'name': name,
        'goal': goal,
        'schedule_json': schedule,
        'days_json': daysJson,
        'exercises_json': exercisesJson,
        'description': description ?? '',
      },
      isAdmin: true,
    );
    await fetchWorkouts();
  }

  Future<void> assignWorkout({required int memberId, required int workoutPlanId, String? notes}) async {
    await ApiService.post(
      ApiConfig.adminWorkouts,
      body: {
        'action': 'assign',
        'member_id': memberId,
        'workout_plan_id': workoutPlanId,
        'notes': notes ?? '',
      },
      isAdmin: true,
    );
  }

  Future<void> deleteWorkout(int id) async {
    await ApiService.post(
      ApiConfig.adminWorkouts,
      body: {'action': 'delete', 'id': id},
      isAdmin: true,
    );
    await fetchWorkouts();
  }

  Future<void> fetchDiet() async {
    _isSectionLoading = true;
    notifyListeners();
    try {
      final data = await ApiService.get(ApiConfig.adminDiet, isAdmin: true);
      if (data != null && data['diet_plans'] is List) {
        _dietPlans = (data['diet_plans'] as List).map((e) => AdminDietItem.fromJson(e as Map<String, dynamic>)).toList();
      }
    } catch (_) {} finally {
      _isSectionLoading = false;
      notifyListeners();
    }
  }

  Future<void> addDiet({
    required String name,
    required String target,
    required int calories,
    required String meals,
    String? description,
  }) async {
    await ApiService.post(
      ApiConfig.adminDiet,
      body: {
        'action': 'add',
        'name': name,
        'target': target,
        'calories': calories,
        'meals': meals,
        'description': description ?? '',
      },
      isAdmin: true,
    );
    await fetchDiet();
  }

  Future<void> assignDiet({required int memberId, required int dietPlanId, String? notes}) async {
    await ApiService.post(
      ApiConfig.adminDiet,
      body: {
        'action': 'assign',
        'member_id': memberId,
        'diet_plan_id': dietPlanId,
        'notes': notes ?? '',
      },
      isAdmin: true,
    );
  }

  Future<void> deleteDiet(int id) async {
    await ApiService.post(
      ApiConfig.adminDiet,
      body: {'action': 'delete', 'id': id},
      isAdmin: true,
    );
    await fetchDiet();
  }

  // 14. Classes & Schedules
  Future<void> fetchClasses() async {
    _isSectionLoading = true;
    notifyListeners();
    try {
      final data = await ApiService.get(ApiConfig.adminClasses, isAdmin: true);
      if (data != null && data['classes'] is List) {
        _classes = (data['classes'] as List).map((e) => AdminClassItem.fromJson(e as Map<String, dynamic>)).toList();
      }
    } catch (_) {} finally {
      _isSectionLoading = false;
      notifyListeners();
    }
  }

  Future<void> addClass({
    required String title,
    int? trainerId,
    required String dayOfWeek,
    required String startTime,
    required String endTime,
    int capacity = 20,
    String room = 'Main Studio',
  }) async {
    await ApiService.post(
      ApiConfig.adminClasses,
      body: {
        'action': 'add',
        'title': title,
        'trainer_id': trainerId,
        'day_of_week': dayOfWeek,
        'start_time': startTime,
        'end_time': endTime,
        'capacity': capacity,
        'room': room,
      },
      isAdmin: true,
    );
    await fetchClasses();
  }

  Future<void> deleteClass(int id) async {
    await ApiService.post(
      ApiConfig.adminClasses,
      body: {'action': 'delete', 'id': id},
      isAdmin: true,
    );
    await fetchClasses();
  }

  // 15. Announcements
  Future<void> fetchAnnouncements() async {
    _isSectionLoading = true;
    notifyListeners();
    try {
      final data = await ApiService.get(ApiConfig.adminAnnouncements, isAdmin: true);
      if (data != null && data['announcements'] is List) {
        _announcements = (data['announcements'] as List).map((e) => AdminAnnouncementItem.fromJson(e as Map<String, dynamic>)).toList();
      }
    } catch (_) {} finally {
      _isSectionLoading = false;
      notifyListeners();
    }
  }

  Future<void> addAnnouncement({required String title, required String message, String? date}) async {
    await ApiService.post(
      ApiConfig.adminAnnouncements,
      body: {
        'action': 'add',
        'title': title,
        'message': message,
        'date': date ?? DateTime.now().toString().split(' ')[0],
      },
      isAdmin: true,
    );
    await fetchAnnouncements();
  }

  Future<void> deleteAnnouncement(int id) async {
    await ApiService.post(
      ApiConfig.adminAnnouncements,
      body: {'action': 'delete', 'id': id},
      isAdmin: true,
    );
    await fetchAnnouncements();
  }

  // 16. Inquiries / Member Support
  Future<void> fetchInquiries({String? status}) async {
    _isSectionLoading = true;
    notifyListeners();
    try {
      final queryParams = <String, String>{};
      if (status != null && status.isNotEmpty) queryParams['status'] = status;
      final data = await ApiService.get(ApiConfig.adminInquiries, queryParams: queryParams.isNotEmpty ? queryParams : null, isAdmin: true);
      if (data != null && data['inquiries'] is List) {
        _inquiries = (data['inquiries'] as List).map((e) => AdminInquiryItem.fromJson(e as Map<String, dynamic>)).toList();
        _openInquiriesCount = (data['open_count'] is int) ? data['open_count'] : 0;
      }
    } catch (_) {} finally {
      _isSectionLoading = false;
      notifyListeners();
    }
  }

  Future<void> replyInquiry({required int inquiryId, required String reply, String status = 'resolved'}) async {
    await ApiService.post(
      ApiConfig.adminInquiries,
      body: {
        'action': 'reply',
        'inquiry_id': inquiryId,
        'reply': reply,
        'status': status,
      },
      isAdmin: true,
    );
    await fetchInquiries();
  }

  // 17. Reports & Analytics
  Future<void> fetchReports({String? startDate, String? endDate}) async {
    _isSectionLoading = true;
    notifyListeners();
    try {
      final queryParams = <String, String>{};
      if (startDate != null) queryParams['start_date'] = startDate;
      if (endDate != null) queryParams['end_date'] = endDate;
      final data = await ApiService.get(ApiConfig.adminReports, queryParams: queryParams.isNotEmpty ? queryParams : null, isAdmin: true);
      if (data != null) {
        _reportsData = AdminReportsData.fromJson(data);
      }
    } catch (_) {} finally {
      _isSectionLoading = false;
      notifyListeners();
    }
  }

  // 18. Settings & Branding
  Future<void> fetchSettings() async {
    _isSectionLoading = true;
    notifyListeners();
    try {
      final data = await ApiService.get(ApiConfig.adminSettings, isAdmin: true);
      if (data != null && data['tenant'] is Map) {
        _settingsRaw = Map<String, dynamic>.from(data['tenant']);
        _settingsData = AdminSettingsData.fromJson(_settingsRaw!);
      }
    } catch (_) {} finally {
      _isSectionLoading = false;
      notifyListeners();
    }
  }

  Future<void> updateSettings({
    required String gymName,
    required String phone,
    String? email,
    String? address,
    String currency = '₹',
    String? upiId,
    String? primaryColor,
    String? secondaryColor,
    String? logoBase64,
  }) async {
    _isActionLoading = true;
    notifyListeners();
    try {
      final body = <String, dynamic>{
        'gym_name': gymName,
        'phone': phone,
        'email': email ?? '',
        'address': address ?? '',
        'currency': currency,
        'upi_id': upiId ?? '',
      };
      // Fields this form does not edit are re-sent exactly as loaded from the server (never app
      // defaults), so saving the form can't reset the gym's timezone or branding colours.
      final loaded = _settingsRaw ?? const <String, dynamic>{};
      final effectivePrimary = primaryColor ?? loaded['primary_color']?.toString();
      final effectiveSecondary = secondaryColor ?? loaded['secondary_color']?.toString();
      final effectiveTimezone = loaded['timezone']?.toString();
      if (effectivePrimary != null && effectivePrimary.isNotEmpty) body['primary_color'] = effectivePrimary;
      if (effectiveSecondary != null && effectiveSecondary.isNotEmpty) body['secondary_color'] = effectiveSecondary;
      if (effectiveTimezone != null && effectiveTimezone.isNotEmpty) body['timezone'] = effectiveTimezone;
      if (logoBase64 != null && logoBase64.isNotEmpty) {
        body['logo_base64'] = logoBase64;
      }
      await ApiService.post(ApiConfig.adminSettings, body: body, isAdmin: true);
      await fetchSettings();
      await fetchDashboard(refresh: true);
    } finally {
      _isActionLoading = false;
      notifyListeners();
    }
  }

  // 19. SaaS Subscription & Cashfree Renewals
  Future<void> fetchSaasSubscription() async {
    _isSaasLoading = true;
    notifyListeners();
    try {
      final generation = _generation;
      final data = await ApiService.get(ApiConfig.adminSubscription, isAdmin: true);
      if (generation != _generation) return;
      if (data is Map) {
        // Always overwrite: a missing key means "none", never "keep the previous gym's data"
        _saasSubscription = data['current_subscription'] is Map
            ? AdminSaasSubscriptionInfo.fromJson(Map<String, dynamic>.from(data['current_subscription']))
            : null;
        _saasPlans = data['plans'] is List
            ? (data['plans'] as List).map((e) => AdminSaasPlanItem.fromJson(Map<String, dynamic>.from(e as Map))).toList()
            : [];
        _saasHistory = data['payment_history'] is List
            ? (data['payment_history'] as List).map((e) => AdminSaasPaymentHistoryItem.fromJson(Map<String, dynamic>.from(e as Map))).toList()
            : [];
      }
    } catch (_) {} finally {
      _isSaasLoading = false;
      notifyListeners();
    }
  }

  Future<Map<String, dynamic>?> createSaasCashfreeOrder({
    required int planId,
    required String billingCycle,
    String? couponCode,
  }) async {
    _isActionLoading = true;
    notifyListeners();
    try {
      final res = await ApiService.post(
        ApiConfig.adminSubscription,
        body: {
          'action': 'create_order',
          'plan_id': planId,
          'billing_cycle': billingCycle,
          'coupon_code': couponCode ?? '',
        },
        isAdmin: true,
      );
      return res;
    } finally {
      _isActionLoading = false;
      notifyListeners();
    }
  }

  /// Asks the server to verify the order with Cashfree and activate the subscription.
  /// This server answer is the only thing that decides whether a SaaS payment succeeded.
  Future<bool> verifySaasOrder(String orderId) async {
    try {
      final res = await ApiService.post(
        ApiConfig.adminSubscription,
        body: {
          'action': 'verify_order',
          'order_id': orderId,
        },
        isAdmin: true,
      );
      final verified = res is Map && res['verified'] == true;
      if (verified) {
        await fetchSaasSubscription();
        await fetchDashboard(refresh: true);
      }
      return verified;
    } catch (_) {
      return false;
    }
  }

  // 20. Gym Owner Transaction History
  Future<void> fetchTransactions({
    String? search,
    String? dateFilter,
    String? startDate,
    String? endDate,
    String? paymentMode,
    String? paymentStatus,
    String? staffId,
    int page = 1,
    bool isRefresh = false,
  }) async {
    if (page == 1) {
      _isTransactionsLoading = true;
      _errorMessage = null;
      _txnCurrentPage = 1;
      if (isRefresh) {
        _transactions = [];
      }
    } else {
      _isTransactionsPaginationLoading = true;
    }
    notifyListeners();

    try {
      final queryParams = <String, String>{
        'page': page.toString(),
        'limit': '20',
      };

      if (search != null && search.trim().isNotEmpty) {
        queryParams['search'] = search.trim();
      }
      if (dateFilter != null && dateFilter.isNotEmpty && dateFilter != 'all') {
        queryParams['date_filter'] = dateFilter;
      }
      if (startDate != null && startDate.isNotEmpty) {
        queryParams['start_date'] = startDate;
      }
      if (endDate != null && endDate.isNotEmpty) {
        queryParams['end_date'] = endDate;
      }
      if (paymentMode != null && paymentMode.isNotEmpty && paymentMode != 'all') {
        queryParams['payment_mode'] = paymentMode;
      }
      if (paymentStatus != null && paymentStatus.isNotEmpty && paymentStatus != 'all') {
        queryParams['payment_status'] = paymentStatus;
      }
      if (staffId != null && staffId.isNotEmpty && staffId != 'all') {
        queryParams['staff_id'] = staffId;
      }

      final data = await ApiService.get(
        ApiConfig.adminTransactions,
        queryParams: queryParams,
        isAdmin: true,
      );

      if (data != null && data is Map<String, dynamic>) {
        if (data['summary'] is Map<String, dynamic>) {
          _transactionSummary = AdminTransactionSummary.fromJson(data['summary'] as Map<String, dynamic>);
        }

        final rawStaffOptions = data['staff_filter_options'] ?? data['staff_options'];
        if (rawStaffOptions is List) {
          _staffFilterOptions = rawStaffOptions
              .map((e) => StaffFilterOption.fromJson(e as Map<String, dynamic>))
              .toList();
        }

        final items = (data['transactions'] as List? ?? [])
            .map((e) => AdminTransactionItem.fromJson(e as Map<String, dynamic>))
            .toList();

        final pagination = data['pagination'] as Map<String, dynamic>? ?? {};
        _txnCurrentPage = pagination['current_page'] is int ? pagination['current_page'] : int.tryParse('${pagination['current_page']}') ?? page;
        _txnTotalPages = pagination['total_pages'] is int ? pagination['total_pages'] : int.tryParse('${pagination['total_pages']}') ?? 1;
        _txnTotalCount = (pagination['total_count'] ?? pagination['total_records']) is int
            ? (pagination['total_count'] ?? pagination['total_records'])
            : int.tryParse('${pagination['total_count'] ?? pagination['total_records']}') ?? items.length;
        _txnHasMore = pagination['has_more'] == true || _txnCurrentPage < _txnTotalPages;

        if (page == 1) {
          _transactions = items;
        } else {
          _transactions.addAll(items);
        }
      }
    } on ApiException catch (e) {
      _errorMessage = e.message;
    } catch (e) {
      _errorMessage = 'Failed to load transaction history: ${e.toString()}';
    } finally {
      _isTransactionsLoading = false;
      _isTransactionsPaginationLoading = false;
      notifyListeners();
    }
  }

  // 21. Fetch Member Registration Document Data
  Future<MemberRegistrationDocumentData?> fetchMemberRegistrationData(int memberId) async {
    _isActionLoading = true;
    notifyListeners();
    try {
      final data = await ApiService.get(
        ApiConfig.adminRegistrationData,
        queryParams: {'member_id': memberId.toString()},
        isAdmin: true,
      );

      if (data != null && data is Map<String, dynamic>) {
        return MemberRegistrationDocumentData.fromJson(data);
      }
      return null;
    } catch (e) {
      debugPrint('Error fetching registration data: $e');
      return null;
    } finally {
      _isActionLoading = false;
      notifyListeners();
    }
  }

  // 22. Record a Payment Transaction (Initial, Partial or Renewal)
  Future<bool> recordTransactionPayment({
    required int memberId,
    required double amount,
    required String paymentMode,
    String? remarks,
    String? planName,
    int? invoiceId,
  }) async {
    _isActionLoading = true;
    notifyListeners();
    try {
      final body = {
        'action': 'record_payment',
        'member_id': memberId,
        'amount': amount,
        'payment_mode': paymentMode,
        'remarks': remarks ?? '',
        'plan_name': planName ?? '',
        if (invoiceId != null) 'invoice_id': invoiceId,
      };

      final res = await ApiService.post(
        ApiConfig.adminTransactions,
        body: body,
        isAdmin: true,
      );

      if (res != null && res['success'] == true) {
        // Refresh transactions and dashboard
        await fetchTransactions(page: 1, isRefresh: true);
        await fetchDashboard(refresh: true);
        return true;
      }
      return false;
    } catch (e) {
      debugPrint('Error recording transaction payment: $e');
      return false;
    } finally {
      _isActionLoading = false;
      notifyListeners();
    }
  }
}

