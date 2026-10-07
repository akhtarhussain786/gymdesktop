import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:provider/provider.dart';
import '../core/theme/app_colors.dart';
import '../core/theme/theme_provider.dart';
import '../providers/auth_provider.dart';
import 'attendance_screen.dart';
import 'dashboard_screen.dart';
import 'gym_lookup_screen.dart';
import 'gym_switcher_dialog.dart';
import 'login_screen.dart';
import 'payments_screen.dart';
import 'profile_screen.dart';
import 'workouts_screen.dart';

class MainNavigationScreen extends StatefulWidget {
  const MainNavigationScreen({super.key});

  @override
  State<MainNavigationScreen> createState() => _MainNavigationScreenState();
}

class _MainNavigationScreenState extends State<MainNavigationScreen> {
  int _currentIndex = 0;

  final List<Widget> _screens = const [
    DashboardScreen(),
    WorkoutsScreen(),
    AttendanceScreen(),
    PaymentsScreen(),
    ProfileScreen(),
  ];

  String _getTitle(String? gymName) {
    switch (_currentIndex) {
      case 0:
        return gymName ?? 'FITISIFY OS';
      case 1:
        return 'WORKOUT REGIMEN';
      case 2:
        return 'ATTENDANCE & SESSIONS';
      case 3:
        return 'PAYMENTS & INVOICES';
      case 4:
        return 'ATHLETE PROFILE';
      default:
        return gymName ?? 'FITISIFY OS';
    }
  }

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthProvider>();
    final themeProvider = context.watch<ThemeProvider>();
    final tenant = auth.currentTenant;

    if (auth.status != AuthStatus.authenticated) {
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (mounted) {
          Navigator.of(context).pushAndRemoveUntil(
            MaterialPageRoute(
              builder: (_) => auth.currentTenant != null
                  ? const LoginScreen()
                  : const GymLookupScreen(),
            ),
            (route) => false,
          );
        }
      });
    }

    final isDark = themeProvider.isDarkMode;

    return Scaffold(
      backgroundColor: AppColors.bg(context),
      appBar: AppBar(
        backgroundColor: AppColors.bgDeep(context),
        elevation: 0,
        surfaceTintColor: Colors.transparent,
        bottom: PreferredSize(
          preferredSize: const Size.fromHeight(1),
          child: Container(
            color: AppColors.border(context),
            height: 1,
          ),
        ),
        title: Row(
          children: [
            Container(
              width: 32,
              height: 32,
              decoration: BoxDecoration(
                color: AppColors.lime.withValues(alpha: 0.12),
                border: Border.all(color: AppColors.limeBorder, width: 1),
                borderRadius: BorderRadius.circular(8),
              ),
              child: const Icon(Icons.bolt_rounded, color: AppColors.lime, size: 18),
            ),
            const SizedBox(width: 10),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text(
                    _getTitle(tenant?.gymName),
                    style: GoogleFonts.outfit(
                      color: AppColors.textPrimary(context),
                      fontWeight: FontWeight.w800,
                      fontSize: 15,
                      letterSpacing: -0.2,
                    ),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                  ),
                  Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Container(
                        padding: const EdgeInsets.symmetric(horizontal: 5, vertical: 1),
                        decoration: BoxDecoration(
                          color: AppColors.lime.withValues(alpha: 0.12),
                          borderRadius: BorderRadius.circular(4),
                        ),
                        child: Text(
                          tenant?.gymCode ?? 'ACTIVE',
                          style: GoogleFonts.plusJakartaSans(
                            color: isDark ? AppColors.lime : const Color(0xFF0F172A),
                            fontSize: 9,
                            fontWeight: FontWeight.w800,
                            letterSpacing: 0.4,
                          ),
                        ),
                      ),
                      if (tenant != null && tenant.branches.isNotEmpty) ...[
                        const SizedBox(width: 5),
                        Flexible(
                          child: Text(
                            tenant.branches.first.branchName,
                            style: GoogleFonts.plusJakartaSans(
                              color: AppColors.textMuted(context),
                              fontSize: 9.5,
                            ),
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                          ),
                        ),
                      ],
                    ],
                  ),
                ],
              ),
            ),
          ],
        ),
        actions: [
          // Multi-Gym Switcher Icon
          IconButton(
            padding: const EdgeInsets.all(6),
            constraints: const BoxConstraints(minWidth: 32, minHeight: 32),
            icon: Icon(Icons.sync_alt_rounded, color: AppColors.textSecondary(context), size: 18),
            tooltip: 'Switch Gym',
            onPressed: () {
              showDialog(
                context: context,
                builder: (_) => const GymSwitcherDialog(),
              );
            },
          ),
          // Dark/Light Mode Toggle
          IconButton(
            padding: const EdgeInsets.all(6),
            constraints: const BoxConstraints(minWidth: 32, minHeight: 32),
            icon: Icon(
              isDark ? Icons.dark_mode_rounded : Icons.light_mode_rounded,
              color: isDark ? AppColors.lime : const Color(0xFFF59E0B),
              size: 18,
            ),
            tooltip: isDark ? 'Switch to Light Mode' : 'Switch to Dark Mode',
            onPressed: () {
              themeProvider.toggleDarkMode();
            },
          ),
          // Logout
          IconButton(
            padding: const EdgeInsets.all(6),
            constraints: const BoxConstraints(minWidth: 32, minHeight: 32),
            icon: const Icon(Icons.logout_rounded, color: AppColors.danger, size: 18),
            tooltip: 'Log Out',
            onPressed: () async {
              final nav = Navigator.of(context);
              final confirm = await showDialog<bool>(
                context: context,
                builder: (ctx) => AlertDialog(
                  backgroundColor: AppColors.darkCard,
                  title: Text(
                    'Sign Out',
                    style: GoogleFonts.outfit(
                      color: AppColors.darkTextPrimary,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  content: Text(
                    'Are you sure you want to sign out from ${tenant?.gymName}?',
                    style: GoogleFonts.plusJakartaSans(
                      color: AppColors.darkTextSecondary,
                      fontSize: 13.5,
                    ),
                  ),
                  actions: [
                    TextButton(
                      onPressed: () => Navigator.pop(ctx, false),
                      child: const Text('Cancel', style: TextStyle(color: AppColors.darkTextSecondary)),
                    ),
                    ElevatedButton(
                      style: ElevatedButton.styleFrom(
                        backgroundColor: AppColors.danger,
                        foregroundColor: Colors.white,
                      ),
                      onPressed: () => Navigator.pop(ctx, true),
                      child: const Text('Sign Out'),
                    ),
                  ],
                ),
              );

              if (confirm == true) {
                await auth.logout();
                nav.pushReplacement(
                  MaterialPageRoute(builder: (_) => const GymLookupScreen()),
                );
              }
            },
          ),
          const SizedBox(width: 6),
        ],
      ),
      body: IndexedStack(
        index: _currentIndex,
        children: _screens,
      ),
      bottomNavigationBar: Container(
        decoration: BoxDecoration(
          color: AppColors.bgDeep(context),
          border: Border(
            top: BorderSide(
              color: AppColors.border(context),
              width: 1,
            ),
          ),
        ),
        child: SafeArea(
          top: false,
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 6),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.spaceAround,
              children: [
                _buildNavItem(0, Icons.dashboard_outlined, Icons.dashboard_rounded, 'Home'),
                _buildNavItem(1, Icons.fitness_center_outlined, Icons.fitness_center_rounded, 'Workouts'),
                _buildNavItem(2, Icons.qr_code_scanner_rounded, Icons.qr_code_scanner_rounded, 'Check-in'),
                _buildNavItem(3, Icons.receipt_long_outlined, Icons.receipt_long_rounded, 'Billing'),
                _buildNavItem(4, Icons.person_outline_rounded, Icons.person_rounded, 'Profile'),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Widget _buildNavItem(int index, IconData icon, IconData activeIcon, String label) {
    final isSelected = _currentIndex == index;
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final activeColor = isDark ? AppColors.lime : const Color(0xFF0F172A);
    final activeBg = isDark ? AppColors.lime.withValues(alpha: 0.12) : const Color(0xFF0F172A).withValues(alpha: 0.08);
    final activeBorder = isDark ? AppColors.limeBorder : const Color(0xFF0F172A).withValues(alpha: 0.15);

    return InkWell(
      onTap: () {
        setState(() {
          _currentIndex = index;
        });
      },
      borderRadius: BorderRadius.circular(16),
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 200),
        curve: Curves.easeOutCubic,
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 6),
        decoration: BoxDecoration(
          color: isSelected ? activeBg : Colors.transparent,
          borderRadius: BorderRadius.circular(16),
          border: Border.all(
            color: isSelected ? activeBorder : Colors.transparent,
            width: 1,
          ),
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(
              isSelected ? activeIcon : icon,
              color: isSelected ? activeColor : AppColors.textMuted(context),
              size: 20,
            ),
            const SizedBox(height: 3),
            Text(
              label,
              style: GoogleFonts.plusJakartaSans(
                color: isSelected ? activeColor : AppColors.textMuted(context),
                fontSize: 10.5,
                fontWeight: isSelected ? FontWeight.w800 : FontWeight.w600,
              ),
            ),
          ],
        ),
      ),
    );
  }
}
