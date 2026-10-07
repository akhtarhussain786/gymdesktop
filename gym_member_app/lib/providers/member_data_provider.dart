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

class MemberDataProvider extends ChangeNotifier {
  MembershipData? _membership;
  AttendanceData? _attendance;
  PaymentData? _payments;
  WorkoutModuleData? _workouts;
  DietModuleData? _diet;
  TrainerModuleData? _trainer;
  NoticeModuleData? _notices;
  SupportModuleData? _support;

  bool _loading = false;
  String? _error;

  MembershipData? get membership => _membership;
  AttendanceData? get attendance => _attendance;
  PaymentData? get payments => _payments;
  WorkoutModuleData? get workouts => _workouts;
  DietModuleData? get diet => _diet;
  TrainerModuleData? get trainer => _trainer;
  NoticeModuleData? get notices => _notices;
  SupportModuleData? get support => _support;

  bool get loading => _loading;
  String? get error => _error;

  // 1. Fetch Membership Details
  Future<void> fetchMembership({bool refresh = false}) async {
    if (_membership != null && !refresh) return;
    _loading = true;
    _error = null;
    notifyListeners();
    try {
      final data = await ApiService.get(ApiConfig.membership);
      _membership = MembershipData.fromJson(data);
      _loading = false;
      notifyListeners();
    } on ApiException catch (e) {
      _error = e.message;
      _loading = false;
      notifyListeners();
    } catch (e) {
      _error = 'Failed to load membership details.';
      _loading = false;
      notifyListeners();
    }
  }

  // 2. Fetch Attendance Records
  Future<void> fetchAttendance({String? month, bool refresh = false}) async {
    if (_attendance != null && !refresh && month == null) return;
    _loading = true;
    _error = null;
    notifyListeners();
    try {
      final query = month != null ? {'month': month} : null;
      final data = await ApiService.get(ApiConfig.attendance, queryParams: query);
      _attendance = AttendanceData.fromJson(data);
      _loading = false;
      notifyListeners();
    } on ApiException catch (e) {
      _error = e.message;
      _loading = false;
      notifyListeners();
    } catch (e) {
      _error = 'Failed to load attendance logs.';
      _loading = false;
      notifyListeners();
    }
  }

  // 3. Fetch Payments & Invoices
  Future<void> fetchPayments({bool refresh = false}) async {
    if (_payments != null && !refresh) return;
    _loading = true;
    _error = null;
    notifyListeners();
    try {
      final data = await ApiService.get(ApiConfig.payments);
      _payments = PaymentData.fromJson(data);
      _loading = false;
      notifyListeners();
    } on ApiException catch (e) {
      _error = e.message;
      _loading = false;
      notifyListeners();
    } catch (e) {
      _error = 'Failed to load payment history.';
      _loading = false;
      notifyListeners();
    }
  }

  // 4. Fetch Workouts & Routines
  Future<void> fetchWorkouts({bool refresh = false}) async {
    if (_workouts != null && !refresh) return;
    _loading = true;
    _error = null;
    notifyListeners();
    try {
      final data = await ApiService.get(ApiConfig.workouts);
      _workouts = WorkoutModuleData.fromJson(data);
      _loading = false;
      notifyListeners();
    } on ApiException catch (e) {
      _error = e.message;
      _loading = false;
      notifyListeners();
    } catch (e) {
      _error = 'Failed to load workout plans.';
      _loading = false;
      notifyListeners();
    }
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
    _loading = true;
    _error = null;
    notifyListeners();
    try {
      final data = await ApiService.get(ApiConfig.diet);
      _diet = DietModuleData.fromJson(data);
      _loading = false;
      notifyListeners();
    } on ApiException catch (e) {
      _error = e.message;
      _loading = false;
      notifyListeners();
    } catch (e) {
      _error = 'Failed to load diet routine.';
      _loading = false;
      notifyListeners();
    }
  }

  // 6. Fetch Trainer Details
  Future<void> fetchTrainer({bool refresh = false}) async {
    if (_trainer != null && !refresh) return;
    _loading = true;
    _error = null;
    notifyListeners();
    try {
      final data = await ApiService.get(ApiConfig.trainer);
      _trainer = TrainerModuleData.fromJson(data);
      _loading = false;
      notifyListeners();
    } on ApiException catch (e) {
      _error = e.message;
      _loading = false;
      notifyListeners();
    } catch (e) {
      _error = 'Failed to load trainer details.';
      _loading = false;
      notifyListeners();
    }
  }

  // 7. Fetch Notices
  Future<void> fetchNotices({bool refresh = false}) async {
    if (_notices != null && !refresh) return;
    _loading = true;
    _error = null;
    notifyListeners();
    try {
      final data = await ApiService.get(ApiConfig.notices);
      _notices = NoticeModuleData.fromJson(data);
      _loading = false;
      notifyListeners();
    } on ApiException catch (e) {
      _error = e.message;
      _loading = false;
      notifyListeners();
    } catch (e) {
      _error = 'Failed to load notices.';
      _loading = false;
      notifyListeners();
    }
  }

  // 8. Fetch Support Info & Submit Ticket
  Future<void> fetchSupport({bool refresh = false}) async {
    if (_support != null && !refresh) return;
    _loading = true;
    _error = null;
    notifyListeners();
    try {
      final data = await ApiService.get(ApiConfig.support);
      _support = SupportModuleData.fromJson(data);
      _loading = false;
      notifyListeners();
    } on ApiException catch (e) {
      _error = e.message;
      _loading = false;
      notifyListeners();
    } catch (e) {
      _error = 'Failed to load support information.';
      _loading = false;
      notifyListeners();
    }
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

  // Clear all cached state on logout / tenant switch
  void clearAll() {
    _membership = null;
    _attendance = null;
    _payments = null;
    _workouts = null;
    _diet = null;
    _trainer = null;
    _notices = null;
    _support = null;
    _error = null;
    _loading = false;
    notifyListeners();
  }
}
