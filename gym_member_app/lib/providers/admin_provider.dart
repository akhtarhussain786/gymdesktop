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

  // Getters
  AdminDashboardData? get dashboardData => _dashboardData;
  List<AdminMemberItem> get members => _members;
  AdminMemberDetail? get memberDetail => _memberDetail;
  List<AdminGymRate> get rates => _rates;
  AdminGymQr? get gymQr => _gymQr;

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

  // 1. Fetch Admin Dashboard KPIs
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
      if (expiryDate != null && expiryDate.isNotEmpty) fields['expiry_date'] = expiryDate;

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

  Future<void> addStaff({
    required String fullname,
    required String phone,
    String? email,
    required String designation,
    String gender = 'Male',
    String? address,
    double salary = 0.0,
    String? password,
  }) async {
    await ApiService.post(
      ApiConfig.adminStaffs,
      body: {
        'action': 'add',
        'fullname': fullname,
        'contact': phone,
        'email': email ?? '',
        'designation': designation,
        'gender': gender,
        'address': address ?? '',
        'salary': salary,
        'password': password ?? '123456',
      },
      isAdmin: true,
    );
    await fetchStaffs();
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
      if (data != null && data['attendance'] is List) {
        _attendanceLogs = (data['attendance'] as List).map((e) => AdminDailyAttendanceItem.fromJson(e as Map<String, dynamic>)).toList();
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
        _totalExpenses = (data['total_amount'] is num) ? (data['total_amount'] as num).toDouble() : 0.0;
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
      if (data != null && data['equipment'] is List) {
        _equipmentList = (data['equipment'] as List).map((e) => AdminEquipmentItem.fromJson(e as Map<String, dynamic>)).toList();
        _equipmentValuation = (data['total_valuation'] is num) ? (data['total_valuation'] as num).toDouble() : 0.0;
        _equipmentUnits = (data['total_quantity'] is int) ? data['total_quantity'] : 0;
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
      if (data != null && data['workout_plans'] is List) {
        _workoutPlans = (data['workout_plans'] as List).map((e) => AdminWorkoutItem.fromJson(e as Map<String, dynamic>)).toList();
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
    await ApiService.post(
      ApiConfig.adminWorkouts,
      body: {
        'action': 'add',
        'name': name,
        'goal': goal,
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
      if (data != null && data['tenant'] != null) {
        _settingsData = AdminSettingsData.fromJson(data['tenant']);
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
        'primary_color': primaryColor ?? '#3b82f6',
        'secondary_color': secondaryColor ?? '#10b981',
      };
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
      final data = await ApiService.get(ApiConfig.adminSubscription, isAdmin: true);
      if (data != null) {
        if (data['current_subscription'] != null) {
          _saasSubscription = AdminSaasSubscriptionInfo.fromJson(data['current_subscription'] as Map<String, dynamic>);
        }
        if (data['plans'] is List) {
          _saasPlans = (data['plans'] as List).map((e) => AdminSaasPlanItem.fromJson(e as Map<String, dynamic>)).toList();
        }
        if (data['payment_history'] is List) {
          _saasHistory = (data['payment_history'] as List).map((e) => AdminSaasPaymentHistoryItem.fromJson(e as Map<String, dynamic>)).toList();
        }
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

  Future<bool> verifySaasOrder(String orderId) async {
    _isActionLoading = true;
    notifyListeners();
    try {
      final res = await ApiService.post(
        ApiConfig.adminSubscription,
        body: {
          'action': 'verify_order',
          'order_id': orderId,
        },
        isAdmin: true,
      );
      await fetchSaasSubscription();
      await fetchDashboard(refresh: true);
      return res != null && res['verified'] == true;
    } catch (_) {
      return false;
    } finally {
      _isActionLoading = false;
      notifyListeners();
    }
  }
}

