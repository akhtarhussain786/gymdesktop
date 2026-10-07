import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'core/theme/theme_provider.dart';
import 'providers/admin_provider.dart';
import 'providers/auth_provider.dart';
import 'providers/dashboard_provider.dart';
import 'providers/member_data_provider.dart';
import 'screens/admin/admin_navigation_screen.dart';
import 'screens/gym_lookup_screen.dart';
import 'screens/login_screen.dart';
import 'screens/main_navigation_screen.dart';

void main() {
  WidgetsFlutterBinding.ensureInitialized();
  runApp(
    MultiProvider(
      providers: [
        ChangeNotifierProvider(create: (_) => ThemeProvider()),
        ChangeNotifierProvider(create: (_) => AuthProvider()),
        ChangeNotifierProvider(create: (_) => DashboardProvider()),
        ChangeNotifierProvider(create: (_) => MemberDataProvider()),
        ChangeNotifierProvider(create: (_) => AdminProvider()),
      ],
      child: const GymMemberApp(),
    ),
  );
}

class GymMemberApp extends StatelessWidget {
  const GymMemberApp({super.key});

  @override
  Widget build(BuildContext context) {
    final themeProvider = context.watch<ThemeProvider>();
    final auth = context.watch<AuthProvider>();

    return MaterialApp(
      title: auth.currentTenant?.gymName ?? 'Fitisify Gym OS',
      debugShowCheckedModeBanner: false,
      theme: themeProvider.themeData,
      home: _resolveInitialScreen(auth),
    );
  }

  Widget _resolveInitialScreen(AuthProvider auth) {
    if (auth.status == AuthStatus.initial) {
      return const Scaffold(
        body: Center(
          child: CircularProgressIndicator(),
        ),
      );
    }
    if (auth.status == AuthStatus.authenticated) {
      // Role-based routing: Admin Console vs Member Dashboard
      if (auth.isAdmin) {
        return const AdminNavigationScreen();
      }
      return const MainNavigationScreen();
    }
    if (auth.status == AuthStatus.gymIdentified) {
      return const LoginScreen();
    }
    return const GymLookupScreen();
  }
}
