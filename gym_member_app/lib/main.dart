import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'core/theme/theme_provider.dart';
import 'providers/admin_provider.dart';
import 'providers/auth_provider.dart';
import 'providers/dashboard_provider.dart';
import 'providers/member_data_provider.dart';
import 'screens/admin/admin_notifications_screen.dart';
import 'screens/notifications_screen.dart';
import 'screens/splash_screen.dart';

import 'core/services/push_notification_service.dart';

void main() {
  WidgetsFlutterBinding.ensureInitialized();

  final auth = AuthProvider();
  final dashboard = DashboardProvider();
  final memberData = MemberDataProvider();
  final admin = AdminProvider();

  // Providers live for the whole app, so a logout / gym change must drop every cached module
  auth.onSessionCleared = () {
    dashboard.clear();
    memberData.clearAll();
    admin.clearAll();
  };

  PushNotificationService.onNotificationClick = (data) {
    if (!auth.isAuthenticated) return;
    PushNotificationService.navigatorKey.currentState?.push(
      MaterialPageRoute(
        builder: (_) => auth.isAdmin ? const AdminNotificationsScreen() : const NotificationsScreen(),
      ),
    );
  };

  runApp(
    MultiProvider(
      providers: [
        ChangeNotifierProvider(create: (_) => ThemeProvider()),
        ChangeNotifierProvider.value(value: auth),
        ChangeNotifierProvider.value(value: dashboard),
        ChangeNotifierProvider.value(value: memberData),
        ChangeNotifierProvider.value(value: admin),
      ],
      child: const GymMemberApp(),
    ),
  );

  // Firebase + the notification permission prompt must not hold back the first frame
  PushNotificationService.initialize();
}

class GymMemberApp extends StatelessWidget {
  const GymMemberApp({super.key});

  @override
  Widget build(BuildContext context) {
    final themeProvider = context.watch<ThemeProvider>();
    final auth = context.watch<AuthProvider>();

    return MaterialApp(
      title: auth.currentTenant?.gymName ?? 'FITISIFY OS',
      debugShowCheckedModeBanner: false,
      navigatorKey: PushNotificationService.navigatorKey,
      theme: themeProvider.themeData,
      home: const SplashScreen(),
    );
  }
}
