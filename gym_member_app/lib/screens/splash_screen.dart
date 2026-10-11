import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../core/services/push_notification_service.dart';
import '../providers/auth_provider.dart';
import 'admin/admin_navigation_screen.dart';
import 'gym_lookup_screen.dart';
import '../widgets/fitisify_logo_header.dart';
import 'login_screen.dart';
import 'main_navigation_screen.dart';

class SplashScreen extends StatefulWidget {
  const SplashScreen({super.key});

  @override
  State<SplashScreen> createState() => _SplashScreenState();
}

class _SplashScreenState extends State<SplashScreen>
    with SingleTickerProviderStateMixin {
  late AnimationController _controller;
  late Animation<double> _fadeAnimation;
  late Animation<double> _scaleAnimation;
  bool _showOfflineRetry = false;
  bool _navigated = false;

  @override
  void initState() {
    super.initState();
    _controller = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 1200),
    );

    _fadeAnimation = CurvedAnimation(
      parent: _controller,
      curve: Curves.easeIn,
    );

    _scaleAnimation = Tween<double>(begin: 0.8, end: 1.0).animate(
      CurvedAnimation(
        parent: _controller,
        curve: Curves.easeOutBack,
      ),
    );

    _controller.forward();

    _waitForSessionAndNavigate();
  }

  // Route only after the stored session has been checked (keeps the logo up for a short minimum)
  Future<void> _waitForSessionAndNavigate() async {
    final auth = context.read<AuthProvider>();
    final minimumSplash = Future.delayed(const Duration(milliseconds: 1200));
    try {
      await auth.sessionRestore.timeout(const Duration(seconds: 45));
    } catch (_) {}
    await minimumSplash;
    if (!mounted) return;

    if (auth.isRestoring || auth.restoreFailedOffline) {
      setState(() => _showOfflineRetry = true);
      return;
    }
    _navigateToNextScreen();
  }

  Future<void> _retrySessionRestore() async {
    setState(() => _showOfflineRetry = false);
    context.read<AuthProvider>().checkExistingSession();
    await _waitForSessionAndNavigate();
  }

  Future<void> _signOutFromOfflineSession() async {
    setState(() => _showOfflineRetry = false);
    await context.read<AuthProvider>().logout(silent: true);
    if (mounted) _navigateToNextScreen();
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  void _navigateToNextScreen() {
    if (!mounted || _navigated) return;
    _navigated = true;
    final auth = context.read<AuthProvider>();

    Widget targetScreen;
    if (auth.status == AuthStatus.authenticated) {
      targetScreen = auth.isAdmin ? const AdminNavigationScreen() : const MainNavigationScreen();
    } else if (auth.status == AuthStatus.gymIdentified) {
      targetScreen = const LoginScreen();
    } else {
      targetScreen = const GymLookupScreen();
    }

    Navigator.of(context).pushReplacement(
      PageRouteBuilder(
        pageBuilder: (_, animation, secondaryAnimation) => targetScreen,
        transitionsBuilder: (_, animation, secondaryAnimation, child) {
          return FadeTransition(opacity: animation, child: child);
        },
        transitionDuration: const Duration(milliseconds: 600),
      ),
    );

    // Deliver a notification tap that launched the app once the real screen is in place
    WidgetsBinding.instance.addPostFrameCallback((_) => PushNotificationService.markAppReady());
  }

  @override
  Widget build(BuildContext context) {
    const limeAccent = Color(0xFFC7FF2E);
    const darkBg = Color(0xFF090D14);

    return Scaffold(
      backgroundColor: darkBg,
      body: Stack(
        children: [
          // Background Gradient Glow
          Positioned.fill(
            child: Container(
              decoration: const BoxDecoration(
                gradient: RadialGradient(
                  center: Alignment(0, -0.2),
                  radius: 0.8,
                  colors: [
                    Color(0x1ac7ff2e), // Subtle lime glow
                    Colors.transparent,
                  ],
                ),
              ),
            ),
          ),

          // Animated Content
          Center(
            child: FadeTransition(
              opacity: _fadeAnimation,
              child: ScaleTransition(
                scale: _scaleAnimation,
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    // Logo Card Container with Soft Glow
                    // App Logo Header (FITISIFY OS)
                    const FitisifyLogoHeader(
                      iconSize: 64,
                      fontSize: 30,
                    ),
                    const SizedBox(height: 10),

                    // Tagline
                    const Text(
                      'Next-Gen Gym Operating System',
                      style: TextStyle(
                        fontSize: 13,
                        fontWeight: FontWeight.w500,
                        letterSpacing: 0.5,
                        color: Color(0xFF94A3B8),
                      ),
                    ),
                    const SizedBox(height: 48),

                    // Sleek Loader (or Retry when the server could not be reached)
                    if (_showOfflineRetry)
                      Padding(
                        padding: const EdgeInsets.symmetric(horizontal: 32),
                        child: Column(
                          children: [
                            const Icon(Icons.wifi_off_rounded, color: Color(0xFF94A3B8), size: 28),
                            const SizedBox(height: 10),
                            Text(
                              context.read<AuthProvider>().errorMessage ??
                                  'Unable to reach the gym server. Please check your connection and try again.',
                              textAlign: TextAlign.center,
                              style: const TextStyle(fontSize: 13, color: Color(0xFF94A3B8), height: 1.4),
                            ),
                            const SizedBox(height: 16),
                            ElevatedButton.icon(
                              onPressed: _retrySessionRestore,
                              icon: const Icon(Icons.refresh_rounded, size: 18),
                              label: const Text('Retry', style: TextStyle(fontWeight: FontWeight.w800)),
                              style: ElevatedButton.styleFrom(
                                backgroundColor: limeAccent,
                                foregroundColor: darkBg,
                                padding: const EdgeInsets.symmetric(horizontal: 28, vertical: 12),
                              ),
                            ),
                            TextButton(
                              onPressed: _signOutFromOfflineSession,
                              child: const Text('Sign out', style: TextStyle(color: Color(0xFF94A3B8))),
                            ),
                          ],
                        ),
                      )
                    else
                      const SizedBox(
                        width: 24,
                        height: 24,
                        child: CircularProgressIndicator(
                          strokeWidth: 2.5,
                          valueColor: AlwaysStoppedAnimation<Color>(limeAccent),
                        ),
                      ),
                  ],
                ),
              ),
            ),
          ),

          // Footer Copyright / Powered By
          Positioned(
            bottom: 12,
            left: 0,
            right: 0,
            child: SafeArea(
              top: false,
              child: Center(
                child: Text(
                  'Powered by NexoraLab Technologies',
                  style: TextStyle(
                    fontSize: 11,
                    fontWeight: FontWeight.w600,
                    color: Colors.white.withValues(alpha: 0.35),
                    letterSpacing: 0.5,
                  ),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}
