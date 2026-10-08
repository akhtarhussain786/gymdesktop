import 'dart:convert';
import 'dart:math';
import 'package:flutter/foundation.dart';
import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '../config/api_config.dart';
import '../network/api_service.dart';
import '../storage/secure_storage_service.dart';

/// Top-level background message handler required by FirebaseMessaging.
/// Runs in an isolated background isolate when app is in background or terminated.
@pragma('vm:entry-point')
Future<void> firebaseMessagingBackgroundHandler(RemoteMessage message) async {
  try {
    await Firebase.initializeApp();
  } catch (e) {
    debugPrint('FCM background init error: $e');
  }
  debugPrint('FCM Background message received: ${message.messageId} - ${message.notification?.title}');
}

class PushNotificationService {
  static final FirebaseMessaging _messaging = FirebaseMessaging.instance;
  static final FlutterLocalNotificationsPlugin _localNotifications = FlutterLocalNotificationsPlugin();

  static const String _channelId = 'gym_high_importance_channel';
  static const String _channelName = 'Gym Alerts & Broadcasts';
  static const String _channelDescription = 'High priority notifications with sound, vibration, and heads-up banner alerts.';

  static const AndroidNotificationChannel _androidChannel = AndroidNotificationChannel(
    _channelId,
    _channelName,
    description: _channelDescription,
    importance: Importance.max,
    playSound: true,
    enableVibration: true,
    showBadge: true,
  );

  static bool _initialized = false;
  static String? _cachedDeviceToken;

  /// Global callback when a notification is clicked to trigger in-app navigation
  static Function(Map<String, dynamic> data)? onNotificationClick;

  /// Initialize Firebase Cloud Messaging and Local Notifications
  static Future<void> initialize() async {
    if (_initialized) return;

    try {
      await Firebase.initializeApp();
      FirebaseMessaging.onBackgroundMessage(firebaseMessagingBackgroundHandler);

      // 1. Request Notification Permissions (iOS & Android 13+)
      final settings = await _messaging.requestPermission(
        alert: true,
        badge: true,
        sound: true,
        provisional: false,
        criticalAlert: true,
      );
      debugPrint('FCM Authorization status: ${settings.authorizationStatus}');

      // 2. Setup Android Notification Channel for Heads-up Alert Banners
      final androidPlugin = _localNotifications.resolvePlatformSpecificImplementation<AndroidFlutterLocalNotificationsPlugin>();
      if (androidPlugin != null) {
        await androidPlugin.createNotificationChannel(_androidChannel);
        await androidPlugin.requestNotificationsPermission();
      }

      // 3. Initialize Local Notifications Plugin
      const initializationSettingsAndroid = AndroidInitializationSettings('@mipmap/ic_launcher');
      const initializationSettingsDarwin = DarwinInitializationSettings(
        requestAlertPermission: true,
        requestBadgePermission: true,
        requestSoundPermission: true,
      );
      const initializationSettings = InitializationSettings(
        android: initializationSettingsAndroid,
        iOS: initializationSettingsDarwin,
      );

      await _localNotifications.initialize(
        initializationSettings,
        onDidReceiveNotificationResponse: (NotificationResponse response) {
          if (response.payload != null && response.payload!.isNotEmpty) {
            try {
              final payloadMap = jsonDecode(response.payload!);
              onNotificationClick?.call(payloadMap);
            } catch (_) {}
          }
        },
      );

      // 4. Foreground presentation options (Heads-up banner while app is in foreground)
      await _messaging.setForegroundNotificationPresentationOptions(
        alert: true,
        badge: true,
        sound: true,
      );

      // 5. Listen to incoming messages in FOREGROUND -> Display heads-up popup banner with sound & vibration
      FirebaseMessaging.onMessage.listen((RemoteMessage message) {
        debugPrint('FCM Foreground message received: ${message.notification?.title}');
        _showLocalNotification(message);
      });

      // 6. Handle notification click when app is opened from background
      FirebaseMessaging.onMessageOpenedApp.listen((RemoteMessage message) {
        debugPrint('FCM Notification opened from background: ${message.data}');
        onNotificationClick?.call(message.data);
      });

      // 7. Handle notification click when app was completely terminated
      final initialMessage = await _messaging.getInitialMessage();
      if (initialMessage != null) {
        debugPrint('FCM App opened from terminated state by message: ${initialMessage.data}');
        onNotificationClick?.call(initialMessage.data);
      }

      // 8. Listen for token refreshes
      _messaging.onTokenRefresh.listen((newToken) {
        _cachedDeviceToken = newToken;
        syncDeviceToken(customToken: newToken);
      });

      _initialized = true;
      debugPrint('PushNotificationService initialized successfully.');

      // Auto sync token if already logged in
      syncDeviceToken();
    } catch (e) {
      debugPrint('PushNotificationService init failed: $e');
    }
  }

  /// Displays high-priority heads-up notification with sound and vibration (like WhatsApp)
  static Future<void> _showLocalNotification(RemoteMessage message) async {
    final notification = message.notification;
    final title = notification?.title ?? message.data['title'] ?? 'New Gym Notification';
    final body = notification?.body ?? message.data['body'] ?? message.data['message'] ?? '';

    final androidDetails = AndroidNotificationDetails(
      _channelId,
      _channelName,
      channelDescription: _channelDescription,
      importance: Importance.max,
      priority: Priority.high,
      playSound: true,
      enableVibration: true,
      icon: '@mipmap/ic_launcher',
      styleInformation: BigTextStyleInformation(
        body,
        contentTitle: title,
        summaryText: 'FITISIFY Alert',
      ),
    );

    const darwinDetails = DarwinNotificationDetails(
      presentAlert: true,
      presentBadge: true,
      presentSound: true,
    );

    final notificationDetails = NotificationDetails(
      android: androidDetails,
      iOS: darwinDetails,
    );

    final notificationId = DateTime.now().millisecondsSinceEpoch.remainder(100000);
    await _localNotifications.show(
      notificationId,
      title,
      body,
      notificationDetails,
      payload: jsonEncode(message.data),
    );
  }

  /// Retrieve persistent unique device ID
  static Future<String> _getDeviceId() async {
    final prefs = await SharedPreferences.getInstance();
    String? deviceId = prefs.getString('app_device_uuid');
    if (deviceId == null || deviceId.isEmpty) {
      final random = Random();
      final part1 = DateTime.now().millisecondsSinceEpoch.toRadixString(16);
      final part2 = random.nextInt(0xFFFFFF).toRadixString(16);
      deviceId = 'dev_${part1}_$part2';
      await prefs.setString('app_device_uuid', deviceId);
    }
    return deviceId;
  }

  /// Synchronize FCM Device Token with the backend API
  static Future<void> syncDeviceToken({String? customToken}) async {
    try {
      final token = customToken ?? await _messaging.getToken();
      if (token == null || token.isEmpty) return;
      _cachedDeviceToken = token;

      final authToken = await SecureStorageService.getToken();
      if (authToken == null || authToken.isEmpty) return; // User not logged in yet

      final userRole = await SecureStorageService.getUserRole();
      final isAdmin = ['gym_admin', 'staff', 'super_admin', 'trainer'].contains((userRole ?? '').toLowerCase());
      final endpoint = isAdmin ? ApiConfig.adminDeviceToken : ApiConfig.deviceToken;

      final deviceId = await _getDeviceId();

      final payload = {
        'action': 'register',
        'device_token': token,
        'device_id': deviceId,
        'platform': defaultTargetPlatform == TargetPlatform.iOS ? 'ios' : 'android',
      };

      await ApiService.post(
        endpoint,
        body: payload,
        isAdmin: isAdmin,
      );
      debugPrint('Device push token synced successfully with backend for ${isAdmin ? "Admin" : "Member"}.');
    } catch (e) {
      debugPrint('Failed to sync device push token: $e');
    }
  }

  /// Unregister device token upon user logout
  static Future<void> unregisterDeviceToken() async {
    try {
      final deviceId = await _getDeviceId();
      final authToken = await SecureStorageService.getToken();
      if (authToken == null || authToken.isEmpty) return;

      final userRole = await SecureStorageService.getUserRole();
      final isAdmin = ['gym_admin', 'staff', 'super_admin', 'trainer'].contains((userRole ?? '').toLowerCase());
      final endpoint = isAdmin ? ApiConfig.adminDeviceToken : ApiConfig.deviceToken;

      await ApiService.post(
        endpoint,
        body: {
          'action': 'unregister',
          'device_id': deviceId,
        },
        isAdmin: isAdmin,
      );
      debugPrint('Device push token unregistered.');
    } catch (e) {
      debugPrint('Failed to unregister device push token: $e');
    }
  }
}
