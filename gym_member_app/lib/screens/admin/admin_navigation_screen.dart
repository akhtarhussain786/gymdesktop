import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import '../../core/theme/app_colors.dart';
import 'admin_dashboard_screen.dart';
import 'admin_gym_qr_screen.dart';
import 'admin_members_screen.dart';
import 'admin_more_screen.dart';

class AdminNavigationScreen extends StatefulWidget {
  final int initialIndex;

  const AdminNavigationScreen({super.key, this.initialIndex = 0});

  @override
  State<AdminNavigationScreen> createState() => _AdminNavigationScreenState();
}

class _AdminNavigationScreenState extends State<AdminNavigationScreen> {
  late int _currentIndex;

  @override
  void initState() {
    super.initState();
    _currentIndex = widget.initialIndex;
  }

  void _onTabTapped(int index) {
    setState(() => _currentIndex = index);
  }

  @override
  Widget build(BuildContext context) {
    final screens = [
      AdminDashboardScreen(onNavigateTab: _onTabTapped),
      const AdminMembersScreen(),
      const AdminGymQrScreen(),
      const AdminMoreScreen(),
    ];

    return Scaffold(
      body: IndexedStack(
        index: _currentIndex,
        children: screens,
      ),
      bottomNavigationBar: Container(
        decoration: BoxDecoration(
          color: AppColors.card(context),
          border: Border(top: BorderSide(color: AppColors.border(context), width: 1)),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withValues(alpha: 0.3),
              blurRadius: 15,
              offset: const Offset(0, -4),
            ),
          ],
        ),
        child: SafeArea(
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 6),
            child: BottomNavigationBar(
              currentIndex: _currentIndex,
              onTap: _onTabTapped,
              type: BottomNavigationBarType.fixed,
              backgroundColor: Colors.transparent,
              elevation: 0,
              selectedItemColor: AppColors.lime,
              unselectedItemColor: AppColors.textMuted(context),
              selectedLabelStyle: GoogleFonts.plusJakartaSans(
                fontWeight: FontWeight.w900,
                fontSize: 11,
              ),
              unselectedLabelStyle: GoogleFonts.plusJakartaSans(
                fontWeight: FontWeight.w600,
                fontSize: 11,
              ),
              items: const [
                BottomNavigationBarItem(
                  icon: Icon(Icons.dashboard_rounded),
                  activeIcon: Icon(Icons.dashboard_rounded, color: AppColors.lime),
                  label: 'Dashboard',
                ),
                BottomNavigationBarItem(
                  icon: Icon(Icons.people_alt_rounded),
                  activeIcon: Icon(Icons.people_alt_rounded, color: AppColors.lime),
                  label: 'Members',
                ),
                BottomNavigationBarItem(
                  icon: Icon(Icons.qr_code_2_rounded),
                  activeIcon: Icon(Icons.qr_code_2_rounded, color: AppColors.lime),
                  label: 'Gym QR',
                ),
                BottomNavigationBarItem(
                  icon: Icon(Icons.more_horiz_rounded),
                  activeIcon: Icon(Icons.more_horiz_rounded, color: AppColors.lime),
                  label: 'Settings',
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
