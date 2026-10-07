import 'dart:async';
import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
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

    // Hold for 2.5 seconds then navigate smoothly based on Auth state
    Timer(const Duration(milliseconds: 2500), _navigateToNextScreen);
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  void _navigateToNextScreen() {
    if (!mounted) return;
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

                    // Sleek Loader
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
            bottom: 24,
            left: 0,
            right: 0,
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
        ],
      ),
    );
  }
}
