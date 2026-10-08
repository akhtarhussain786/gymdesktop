import 'dart:convert';
import 'dart:math';
import 'package:flutter/foundation.dart';
import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '../../firebase_options.dart';
import '../config/api_config.dart';
import '../network/api_service.dart';
import '../storage/secure_storage_service.dart';

/// Top-level background message handler required by FirebaseMessaging.
/// Runs in an isolated background isolate when app is in background or terminated.
@pragma('vm:entry-point')
Future<void> firebaseMessagingBackgroundHandler(RemoteMessage message) async {
  try {
    if (Firebase.apps.isEmpty) {
      if (kIsWeb || defaultTargetPlatform == TargetPlatform.windows) {
        await Firebase.initializeApp(options: DefaultFirebaseOptions.currentPlatform);
      } else {
        await Firebase.initializeApp();
      }
    }
  } catch (e) {
    debugPrint('[FCM Background] Firebase init error: $e');
  }
  debugPrint('[FCM Background] Message received: ${message.messageId} | Title: ${message.notification?.title ?? message.data['title']}');
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
  static String? _lastSyncStatus;

  /// Global callback when a notification is clicked to trigger in-app navigation
  static Function(Map<String, dynamic> data)? onNotificationClick;

  /// Retrieve active or cached device token
  static Future<String?> getDeviceToken() async {
    if (_cachedDeviceToken != null && _cachedDeviceToken!.isNotEmpty) {
      return _cachedDeviceToken;
    }
    try {
      if (!kIsWeb && (defaultTargetPlatform == TargetPlatform.android || defaultTargetPlatform == TargetPlatform.iOS)) {
        _cachedDeviceToken = await _messaging.getToken();
      }
    } catch (e) {
      debugPrint('[FCM] Error retrieving token: $e');
    }
    return _cachedDeviceToken;
  }

  /// Get runtime push diagnostics
  static Future<Map<String, dynamic>> getDiagnostics() async {
    final token = await getDeviceToken();
    final deviceId = await _getDeviceId();
    final userRole = await SecureStorageService.getUserRole();
    final gymCode = await SecureStorageService.getCurrentGymCode();
    final authToken = await SecureStorageService.getToken();

    return {
      'initialized': _initialized,
      'has_token': token != null && token.isNotEmpty,
      'device_token_preview': token != null ? '${token.substring(0, min(12, token.length))}...${token.substring(max(0, token.length - 8))}' : null,
      'device_id': deviceId,
      'platform': defaultTargetPlatform.name,
      'user_role': userRole ?? 'guest',
      'gym_code': gymCode,
      'is_authenticated': authToken != null && authToken.isNotEmpty,
      'last_sync_status': _lastSyncStatus ?? 'Not synced yet',
      'channel_id': _channelId
    };
  }

  /// Initialize Firebase Cloud Messaging and Local Notifications
  static Future<void> initialize() async {
    if (_initialized) return;

    try {
      // 1. Initialize Firebase Core safely
      if (Firebase.apps.isEmpty) {
        try {
          if (kIsWeb || defaultTargetPlatform == TargetPlatform.windows) {
            await Firebase.initializeApp(options: DefaultFirebaseOptions.currentPlatform);
          } else {
            await Firebase.initializeApp();
          }
        } catch (_) {
          await Firebase.initializeApp(options: DefaultFirebaseOptions.currentPlatform);
        }
      }

      // Register background handler for background/terminated push processing
      FirebaseMessaging.onBackgroundMessage(firebaseMessagingBackgroundHandler);

      // 2. Request Notification Permissions (iOS & Android 13+)
      final settings = await _messaging.requestPermission(
        alert: true,
        badge: true,
        sound: true,
        provisional: false,
        criticalAlert: true,
      );
      debugPrint('[FCM] Authorization status: ${settings.authorizationStatus}');

      // 3. Setup Android Notification Channel for Heads-up Alert Banners
      final androidPlugin = _localNotifications.resolvePlatformSpecificImplementation<AndroidFlutterLocalNotificationsPlugin>();
      if (androidPlugin != null) {
        await androidPlugin.createNotificationChannel(_androidChannel);
        await androidPlugin.requestNotificationsPermission();
      }

      // 4. Initialize Local Notifications Plugin
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

      // 5. Foreground presentation options (Heads-up banner while app is in foreground)
      await _messaging.setForegroundNotificationPresentationOptions(
        alert: true,
        badge: true,
        sound: true,
      );

      // 6. Listen to incoming messages in FOREGROUND -> Display heads-up popup banner with sound & vibration
      FirebaseMessaging.onMessage.listen((RemoteMessage message) {
        debugPrint('[FCM Foreground] Message received: ${message.notification?.title ?? message.data['title']}');
        _showLocalNotification(message);
      });

      // 7. Handle notification click when app is opened from background
      FirebaseMessaging.onMessageOpenedApp.listen((RemoteMessage message) {
        debugPrint('[FCM Opened] From background: ${message.data}');
        onNotificationClick?.call(message.data);
      });

      // 8. Handle notification click when app was completely terminated
      final initialMessage = await _messaging.getInitialMessage();
      if (initialMessage != null) {
        debugPrint('[FCM Initial] App opened from terminated state: ${initialMessage.data}');
        onNotificationClick?.call(initialMessage.data);
      }

      // 9. Listen for token refreshes
      _messaging.onTokenRefresh.listen((newToken) {
        debugPrint('[FCM Refresh] Token refreshed by Firebase: $newToken');
        _cachedDeviceToken = newToken;
        syncDeviceToken(customToken: newToken);
      });

      _initialized = true;
      debugPrint('[FCM] PushNotificationService initialized successfully.');

      // Auto sync device token with backend
      syncDeviceToken();
    } catch (e) {
      debugPrint('[FCM Init Error] $e');
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

  /// Trigger instant test notification on physical device
  static Future<void> showTestNotification() async {
    const androidDetails = AndroidNotificationDetails(
      _channelId,
      _channelName,
      channelDescription: _channelDescription,
      importance: Importance.max,
      priority: Priority.high,
      playSound: true,
      enableVibration: true,
      icon: '@mipmap/ic_launcher',
      styleInformation: BigTextStyleInformation(
        'This is a live test notification verifying high-importance floating banner and sound.',
        contentTitle: '🔔 WhatsApp Style Test Popup',
        summaryText: 'FITISIFY Alert',
      ),
    );

    const notificationDetails = NotificationDetails(
      android: androidDetails,
      iOS: DarwinNotificationDetails(presentAlert: true, presentSound: true, presentBadge: true),
    );

    await _localNotifications.show(
      8888,
      '🔔 WhatsApp Style Test Popup',
      'This is a live test notification verifying high-importance floating banner and sound.',
      notificationDetails,
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
      String? token = customToken;
      if (token == null || token.isEmpty) {
        try {
          token = await _messaging.getToken();
        } catch (e) {
          debugPrint('[FCM] getToken error: $e');
        }
      }

      if (token == null || token.isEmpty) {
        _lastSyncStatus = 'Empty token returned from Firebase';
        debugPrint('[FCM] getToken returned empty token.');
        return;
      }

      _cachedDeviceToken = token;
      debugPrint('[FCM] Active Device Token: $token');

      final authToken = await SecureStorageService.getToken();
      final userRole = await SecureStorageService.getUserRole() ?? 'member';
      final gymCode = await SecureStorageService.getCurrentGymCode();
      final isAdmin = ['gym_admin', 'staff', 'super_admin', 'trainer'].contains(userRole.toLowerCase());
      final endpoint = (isAdmin && authToken != null && authToken.isNotEmpty)
          ? ApiConfig.adminDeviceToken
          : ApiConfig.deviceToken;

      final deviceId = await _getDeviceId();

      final payload = {
        'action': 'register',
        'device_token': token,
        'device_id': deviceId,
        'platform': defaultTargetPlatform == TargetPlatform.iOS ? 'ios' : 'android',
        'gym_code': gymCode,
        'user_role': userRole,
      };

      final res = await ApiService.post(
        endpoint,
        body: payload,
        gymCode: gymCode,
        isAdmin: isAdmin && authToken != null && authToken.isNotEmpty,
      );

      _lastSyncStatus = 'Synced successfully at ${DateTime.now().toIso8601String()}';
      debugPrint('[FCM] Device push token synced successfully with backend ($endpoint). Response: $res');
    } catch (e) {
      _lastSyncStatus = 'Sync error: $e';
      debugPrint('[FCM] Failed to sync device push token: $e');
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
      _lastSyncStatus = 'Session unlinked on logout';
      debugPrint('[FCM] Device push token session unlinked on logout.');
    } catch (e) {
      debugPrint('[FCM] Failed to unregister device push token: $e');
    }
  }
}
