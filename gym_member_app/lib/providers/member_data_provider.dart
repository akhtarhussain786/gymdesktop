import 'package:flutter/material.dart';
import '../core/config/api_config.dart';
import '../core/network/api_service.dart';
import '../models/membership_data.dart';
import '../models/attendance_data.dart';
import '../models/payment_data.dart';
import '../models/workout_plan.dart';
import '../models/diet_plan.dart';
import '../models/trainer_data.dart';
import '../models/notice_data.dart';
import '../models/support_data.dart';

/// Member-app data modules. Each one has its own loading/error state so a failure in one tab
/// (e.g. a 403 because workouts are not in the gym's plan) never shows up on another tab.
enum MemberModule { membership, attendance, payments, workouts, diet, trainer, notices, support }

/// Outcome of a member action (check-in / check-out) with the message to show.
class MemberActionResult {
  final bool success;
  final String message;

  const MemberActionResult(this.success, this.message);
}

class MemberDataProvider extends ChangeNotifier {
  MembershipData? _membership;
  AttendanceData? _attendance;
  PaymentData? _payments;
  WorkoutModuleData? _workouts;
  DietModuleData? _diet;
  TrainerModuleData? _trainer;
  NoticeModuleData? _notices;
  SupportModuleData? _support;

  final Map<MemberModule, bool> _loadingByModule = {};
  final Map<MemberModule, String?> _errorByModule = {};
  bool _attendanceActionRunning = false;

  // Bumped by clearAll(): replies that arrive after logout / gym switch are dropped
  int _generation = 0;

  MembershipData? get membership => _membership;
  AttendanceData? get attendance => _attendance;
  PaymentData? get payments => _payments;
  WorkoutModuleData? get workouts => _workouts;
  DietModuleData? get diet => _diet;
  TrainerModuleData? get trainer => _trainer;
  NoticeModuleData? get notices => _notices;
  SupportModuleData? get support => _support;

  bool isLoading(MemberModule module) => _loadingByModule[module] == true;
  String? errorFor(MemberModule module) => _errorByModule[module];

  /// True while a check-in / check-out request is running.
  bool get attendanceActionRunning => _attendanceActionRunning;

  /// Any module loading (kept for older call sites).
  bool get loading => _loadingByModule.values.any((v) => v);

  /// First module error (kept for older call sites; prefer [errorFor]).
  String? get error {
    for (final e in _errorByModule.values) {
      if (e != null) return e;
    }
    return null;
  }

  Future<void> _load<T>(
    MemberModule module,
    Future<T> Function() request,
    void Function(T value) assign,
    String fallbackError,
  ) async {
    final generation = _generation;
    _loadingByModule[module] = true;
    _errorByModule[module] = null;
    notifyListeners();
    try {
      final value = await request();
      if (generation != _generation) return;
      assign(value);
    } on ApiException catch (e) {
      if (generation != _generation) return;
      _errorByModule[module] = e.message;
    } catch (_) {
      if (generation != _generation) return;
      _errorByModule[module] = fallbackError;
    } finally {
      if (generation == _generation) {
        _loadingByModule[module] = false;
        notifyListeners();
      }
    }
  }

  // 1. Fetch Membership Details
  Future<void> fetchMembership({bool refresh = false}) async {
    if (_membership != null && !refresh) return;
    await _load<MembershipData>(
      MemberModule.membership,
      () async => MembershipData.fromJson(await ApiService.get(ApiConfig.membership)),
      (v) => _membership = v,
      'Failed to load membership details.',
    );
  }

  // 2. Fetch Attendance Records
  Future<void> fetchAttendance({String? month, bool refresh = false}) async {
    if (_attendance != null && !refresh && month == null) return;
    final query = month != null ? {'month': month} : null;
    await _load<AttendanceData>(
      MemberModule.attendance,
      () async => AttendanceData.fromJson(await ApiService.get(ApiConfig.attendance, queryParams: query)),
      (v) => _attendance = v,
      'Failed to load attendance logs.',
    );
  }

  // Perform Check-In Action
  Future<MemberActionResult> checkIn() {
    return _attendanceAction('check_in', 'Check-in successful!', 'Failed to check in.');
  }

  // Perform Check-Out Action
  Future<MemberActionResult> checkOut() {
    return _attendanceAction('check_out', 'Check-out successful!', 'Failed to check out.');
  }

  Future<MemberActionResult> _attendanceAction(String action, String successMessage, String failureMessage) async {
    if (_attendanceActionRunning) {
      return const MemberActionResult(false, 'Please wait, your previous request is still being processed.');
    }
    _attendanceActionRunning = true;
    notifyListeners();
    try {
      // The server's message is in the response envelope, not in its data block
      final envelope = await ApiService.post(ApiConfig.attendance, body: {'action': action}, returnEnvelope: true);
      final message = (envelope is Map && envelope['message'] != null && envelope['message'].toString().isNotEmpty)
          ? envelope['message'].toString()
          : successMessage;
      await fetchAttendance(refresh: true);
      return MemberActionResult(true, message);
    } on ApiException catch (e) {
      return MemberActionResult(false, e.message);
    } catch (_) {
      return MemberActionResult(false, failureMessage);
    } finally {
      _attendanceActionRunning = false;
      notifyListeners();
    }
  }

  // 3. Fetch Payments & Invoices
  Future<void> fetchPayments({bool refresh = false}) async {
    if (_payments != null && !refresh) return;
    await _load<PaymentData>(
      MemberModule.payments,
      () async => PaymentData.fromJson(await ApiService.get(ApiConfig.payments)),
      (v) => _payments = v,
      'Failed to load payment history.',
    );
  }

  // 4. Fetch Workouts & Routines
  Future<void> fetchWorkouts({bool refresh = false}) async {
    if (_workouts != null && !refresh) return;
    await _load<WorkoutModuleData>(
      MemberModule.workouts,
      () async => WorkoutModuleData.fromJson(await ApiService.get(ApiConfig.workouts)),
      (v) => _workouts = v,
      'Failed to load workout plans.',
    );
  }

  // Toggle Workout Todo Checklist Item
  Future<void> toggleWorkoutTodo(int todoId) async {
    try {
      await ApiService.post(ApiConfig.workouts, body: {
        'action': 'toggle',
        'todo_id': todoId,
      });
      await fetchWorkouts(refresh: true);
    } catch (_) {}
  }

  // Add Workout Todo Item
  Future<bool> addWorkoutTodo(String desc) async {
    try {
      await ApiService.post(ApiConfig.workouts, body: {
        'action': 'add',
        'task_desc': desc,
      });
      await fetchWorkouts(refresh: true);
      return true;
    } catch (_) {
      return false;
    }
  }

  // 5. Fetch Diet Plans
  Future<void> fetchDiet({bool refresh = false}) async {
    if (_diet != null && !refresh) return;
    await _load<DietModuleData>(
      MemberModule.diet,
      () async => DietModuleData.fromJson(await ApiService.get(ApiConfig.diet)),
      (v) => _diet = v,
      'Failed to load diet routine.',
    );
  }

  // 6. Fetch Trainer Details
  Future<void> fetchTrainer({bool refresh = false}) async {
    if (_trainer != null && !refresh) return;
    await _load<TrainerModuleData>(
      MemberModule.trainer,
      () async => TrainerModuleData.fromJson(await ApiService.get(ApiConfig.trainer)),
      (v) => _trainer = v,
      'Failed to load trainer details.',
    );
  }

  // 7. Fetch Notices
  Future<void> fetchNotices({bool refresh = false}) async {
    if (_notices != null && !refresh) return;
    await _load<NoticeModuleData>(
      MemberModule.notices,
      () async => NoticeModuleData.fromJson(await ApiService.get(ApiConfig.notices)),
      (v) => _notices = v,
      'Failed to load notices.',
    );
  }

  // 8. Fetch Support Info & Submit Ticket
  Future<void> fetchSupport({bool refresh = false}) async {
    if (_support != null && !refresh) return;
    await _load<SupportModuleData>(
      MemberModule.support,
      () async => SupportModuleData.fromJson(await ApiService.get(ApiConfig.support)),
      (v) => _support = v,
      'Failed to load support information.',
    );
  }

  // Submit help inquiry
  Future<bool> submitInquiry(String subject, String message, String category) async {
    try {
      await ApiService.post(ApiConfig.support, body: {
        'subject': subject,
        'message': message,
        'category': category,
      });
      await fetchSupport(refresh: true);
      return true;
    } catch (_) {
      return false;
    }
  }

  // Change Password
  Future<String?> changePassword(String currentPass, String newPass, String confirmPass) async {
    try {
      await ApiService.post(ApiConfig.changePassword, body: {
        'current_password': currentPass,
        'new_password': newPass,
        'confirm_password': confirmPass,
      });
      return null; // Null means success
    } on ApiException catch (e) {
      return e.message;
    } catch (e) {
      return 'Failed to change password. Please try again.';
    }
  }

  // Update Profile
  Future<bool> updateProfile(Map<String, dynamic> data) async {
    try {
      await ApiService.post(ApiConfig.profile, body: data);
      return true;
    } catch (_) {
      return false;
    }
  }

  // Fetch Renewal Plans
  Future<Map<String, dynamic>?> fetchPlans() async {
    try {
      final res = await ApiService.get(ApiConfig.plans);
      return res as Map<String, dynamic>;
    } catch (_) {
      return null;
    }
  }

  // Generate Renewal Payment QR
  Future<Map<String, dynamic>?> generateRenewalQr({required int planId, required int months}) async {
    try {
      final res = await ApiService.post(ApiConfig.generateRenewalQr, body: {
        'plan_id': planId,
        'months': months
      });
      return res as Map<String, dynamic>;
    } catch (_) {
      return null;
    }
  }

  /// Records the UPI reference (UTR) of a payment made directly to the gym's UPI ID.
  /// The gym verifies it before the membership is extended; the price is computed by the server.
  /// Throws [ApiException] with the server's message when the submission is rejected.
  Future<Map<String, dynamic>> submitRenewalPayment({
    int? planId,
    required String planName,
    required int months,
    required String transactionRef,
  }) async {
    final body = <String, dynamic>{
      'plan_name': planName,
      'months': months,
      'transaction_ref': transactionRef,
    };
    if (planId != null && planId > 0) body['plan_id'] = planId;

    final res = await ApiService.post(ApiConfig.submitRenewalPayment, body: body);
    // The pending invoice now shows up in the member's payments
    fetchMembership(refresh: true);
    fetchPayments(refresh: true);
    return (res is Map) ? Map<String, dynamic>.from(res) : <String, dynamic>{};
  }

  /// Creates the renewal payment order (price calculated by the server).
  /// Throws [ApiException] with the server's message on failure.
  Future<Map<String, dynamic>> createPaymentOrder({required int planId, required int months}) async {
    final res = await ApiService.post(ApiConfig.createPaymentOrder, body: {
      'plan_id': planId,
      'months': months
    });
    if (res is! Map) {
      throw ApiException('Could not create payment order. Please retry.');
    }
    return Map<String, dynamic>.from(res);
  }

  // Check Payment Status with Cashfree & Idempotent Processing
  Future<Map<String, dynamic>?> checkPaymentStatus(String orderId) async {
    try {
      final res = await ApiService.post(ApiConfig.checkPaymentStatus, body: {
        'order_id': orderId
      });
      if (res is! Map) return null;
      final map = Map<String, dynamic>.from(res);
      // Polling runs every few seconds: refresh membership & invoices only once the payment is confirmed
      if (map['payment_status'] == 'PAID') {
        fetchMembership(refresh: true);
        fetchPayments(refresh: true);
      }
      return map;
    } catch (_) {
      return null;
    }
  }

  // Clear all cached state on logout / tenant switch
  void clearAll() {
    _generation++;
    _membership = null;
    _attendance = null;
    _payments = null;
    _workouts = null;
    _diet = null;
    _trainer = null;
    _notices = null;
    _support = null;
    _loadingByModule.clear();
    _errorByModule.clear();
    _attendanceActionRunning = false;
    notifyListeners();
  }
}
