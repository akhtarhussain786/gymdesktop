import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:provider/provider.dart';
import '../../core/theme/app_colors.dart';
import '../../providers/auth_provider.dart';
import '../gym_lookup_screen.dart';
import '../login_screen.dart';
import 'admin_announcements_screen.dart';
import 'admin_attendance_screen.dart';
import 'admin_classes_screen.dart';
import 'admin_equipment_screen.dart';
import 'admin_expenses_screen.dart';
import 'admin_fitness_plans_screen.dart';
import 'admin_gym_qr_screen.dart';
import 'admin_inquiries_screen.dart';
import 'admin_rates_screen.dart';
import 'admin_reports_screen.dart';
import 'admin_settings_screen.dart';
import 'admin_staffs_screen.dart';

class AdminMoreScreen extends StatelessWidget {
  const AdminMoreScreen({super.key});

  void _handleLogout(BuildContext context) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        backgroundColor: const Color(0xFF1E1E2C),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
        title: Text(
          'Sign Out',
          style: GoogleFonts.outfit(fontWeight: FontWeight.w800, color: Colors.white),
        ),
        content: Text(
          'Are you sure you want to log out from this Admin session?',
          style: GoogleFonts.plusJakartaSans(color: Colors.white70),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx, false),
            child: Text('Cancel', style: GoogleFonts.plusJakartaSans(color: Colors.white60)),
          ),
          ElevatedButton(
            style: ElevatedButton.styleFrom(
              backgroundColor: AppColors.danger,
              foregroundColor: Colors.white,
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
            ),
            onPressed: () => Navigator.pop(ctx, true),
            child: const Text('Log Out'),
          ),
        ],
      ),
    );

    if (confirmed == true && context.mounted) {
      final auth = context.read<AuthProvider>();
      await auth.logout();
      if (context.mounted) {
        Navigator.of(context).pushAndRemoveUntil(
          MaterialPageRoute(builder: (_) => const LoginScreen()),
          (route) => false,
        );
      }
    }
  }

  void _handleChangeGym(BuildContext context) {
    context.read<AuthProvider>().changeGym();
    Navigator.of(context).pushAndRemoveUntil(
      MaterialPageRoute(builder: (_) => const GymLookupScreen()),
      (route) => false,
    );
  }

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthProvider>();
    final tenant = auth.currentTenant;
    final adminUser = auth.adminUser;

    final name = adminUser?['fullname'] ?? adminUser?['username'] ?? 'Gym Admin';
    final role = (adminUser?['role'] ?? auth.userRole).toString().toUpperCase();


    return Scaffold(
      backgroundColor: const Color(0xFF13131A),
      appBar: AppBar(
        backgroundColor: const Color(0xFF1E1E2C),
        elevation: 0,
        title: Text(
          'Admin Console & Tools',
          style: GoogleFonts.outfit(fontWeight: FontWeight.w900, fontSize: 18, color: Colors.white),
        ),
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            // 1. Admin Profile Banner
            Container(
              padding: const EdgeInsets.all(18),
              decoration: BoxDecoration(
                color: const Color(0xFF1E1E2C),
                borderRadius: BorderRadius.circular(20),
                border: Border.all(color: Colors.white.withOpacity(0.06)),
              ),
              child: Row(
                children: [
                  Container(
                    width: 56,
                    height: 56,
                    decoration: BoxDecoration(
                      shape: BoxShape.circle,
                      color: const Color(0xFF6C5CE7).withOpacity(0.2),
                      border: Border.all(color: const Color(0xFF6C5CE7), width: 2),
                    ),
                    child: Center(
                      child: Text(
                        name.isNotEmpty ? name[0].toUpperCase() : 'A',
                        style: GoogleFonts.outfit(
                          fontSize: 22,
                          fontWeight: FontWeight.w900,
                          color: const Color(0xFF6C5CE7),
                        ),
                      ),
                    ),
                  ),
                  const SizedBox(width: 14),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          name,
                          style: GoogleFonts.outfit(
                            fontSize: 17,
                            fontWeight: FontWeight.w800,
                            color: Colors.white,
                          ),
                          overflow: TextOverflow.ellipsis,
                        ),
                        const SizedBox(height: 4),
                        Row(
                          children: [
                            Container(
                              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                              decoration: BoxDecoration(
                                color: const Color(0xFF6C5CE7).withOpacity(0.2),
                                borderRadius: BorderRadius.circular(6),
                              ),
                              child: Text(
                                role,
                                style: GoogleFonts.plusJakartaSans(
                                  fontSize: 10,
                                  fontWeight: FontWeight.w800,
                                  color: const Color(0xFF6C5CE7),
                                ),
                              ),
                            ),
                            if (tenant != null) ...[
                              const SizedBox(width: 8),
                              Expanded(
                                child: Text(
                                  tenant.gymName,
                                  style: GoogleFonts.plusJakartaSans(fontSize: 12, color: Colors.white60),
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
            ),
            const SizedBox(height: 20),

            // 2. OPERATIONS & ATTENDANCE
            _sectionHeader('OPERATIONS & ATTENDANCE'),
            _menuTile(
              context,
              icon: Icons.how_to_reg_rounded,
              title: 'Live Attendance & Check-in',
              subtitle: 'Daily check-in logs & instant entry',
              color: const Color(0xFF00CEC9),
              onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const AdminAttendanceScreen())),
            ),
            _menuTile(
              context,
              icon: Icons.badge_rounded,
              title: 'Staff & Trainers Directory',
              subtitle: 'Manage coaches, staff roles & salaries',
              color: const Color(0xFF6C5CE7),
              onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const AdminStaffsScreen())),
            ),
            _menuTile(
              context,
              icon: Icons.alarm_rounded,
              title: 'Classes & Studio Schedules',
              subtitle: 'Yoga, Zumba, CrossFit booking slots',
              color: const Color(0xFFFDCB6E),
              onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const AdminClassesScreen())),
            ),
            const SizedBox(height: 16),

            // 3. FINANCE & INVENTORY
            _sectionHeader('FINANCE, PACKAGES & INVENTORY'),
            _menuTile(
              context,
              icon: Icons.payments_rounded,
              title: 'Expenses Management',
              subtitle: 'Monthly expense logs & category breakdown',
              color: const Color(0xFFFF7675),
              onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const AdminExpensesScreen())),
            ),
            _menuTile(
              context,
              icon: Icons.fitness_center_rounded,
              title: 'Equipment Inventory',
              subtitle: 'Asset valuation, quantity & vendor tracking',
              color: const Color(0xFFE58E26),
              onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const AdminEquipmentScreen())),
            ),
            _menuTile(
              context,
              icon: Icons.card_membership_rounded,
              title: 'Membership Packages & Rates',
              subtitle: 'Configure packages and monthly pricing',
              color: const Color(0xFF00B894),
              onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const AdminRatesScreen())),
            ),
            _menuTile(
              context,
              icon: Icons.bar_chart_rounded,
              title: 'Financial Reports & Analytics',
              subtitle: 'P&L, revenue trends & member growth',
              color: const Color(0xFF0984E3),
              onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const AdminReportsScreen())),
            ),
            const SizedBox(height: 16),

            // 4. MEMBER SERVICES & FITNESS
            _sectionHeader('MEMBER SERVICES & FITNESS'),
            _menuTile(
              context,
              icon: Icons.sports_gymnastics_rounded,
              title: 'Workout & Diet Plans',
              subtitle: 'Create routines, meal plans & assign to members',
              color: const Color(0xFFA29BFE),
              onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const AdminFitnessPlansScreen())),
            ),
            _menuTile(
              context,
              icon: Icons.mark_chat_read_rounded,
              title: 'Member Support & Requests',
              subtitle: 'View tickets and reply to member inquiries',
              color: const Color(0xFF00CEC9),
              onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const AdminInquiriesScreen())),
            ),
            _menuTile(
              context,
              icon: Icons.campaign_rounded,
              title: 'Announcements & Broadcasts',
              subtitle: 'Publish notices to all member apps',
              color: const Color(0xFFE84393),
              onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const AdminAnnouncementsScreen())),
            ),
            const SizedBox(height: 16),

            // 5. SETTINGS & SYSTEM
            _sectionHeader('GYM SETTINGS & SYSTEM'),
            _menuTile(
              context,
              icon: Icons.settings_rounded,
              title: 'Gym Branding & UPI ID',
              subtitle: 'Update gym address, phone & payment UPI',
              color: const Color(0xFF6C5CE7),
              onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const AdminSettingsScreen())),
            ),
            _menuTile(
              context,
              icon: Icons.qr_code_2_rounded,
              title: 'Gym UPI QR Code',
              subtitle: 'Display instant counter payment QR',
              color: const Color(0xFF00CEC9),
              onTap: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const AdminGymQrScreen(isModal: true))),
            ),
            _menuTile(
              context,
              icon: Icons.swap_horiz_rounded,
              title: 'Switch Gym Tenant',
              subtitle: 'Connect to another branch/gym',
              color: Colors.white70,
              onTap: () => _handleChangeGym(context),
            ),
            _menuTile(
              context,
              icon: Icons.logout_rounded,
              title: 'Log Out',
              subtitle: 'Sign out from admin console',
              color: const Color(0xFFFF7675),
              onTap: () => _handleLogout(context),
            ),
            const SizedBox(height: 30),
          ],
        ),
      ),
    );
  }

  Widget _sectionHeader(String title) {
    return Padding(
      padding: const EdgeInsets.only(left: 4, bottom: 8, top: 4),
      child: Text(
        title,
        style: GoogleFonts.plusJakartaSans(
          fontSize: 11,
          fontWeight: FontWeight.w800,
          color: Colors.white38,
          letterSpacing: 0.8,
        ),
      ),
    );
  }

  Widget _menuTile(
    BuildContext context, {
    required IconData icon,
    required String title,
    required String subtitle,
    required Color color,
    required VoidCallback onTap,
  }) {
    return Container(
      margin: const EdgeInsets.only(bottom: 8),
      decoration: BoxDecoration(
        color: const Color(0xFF1E1E2C),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: Colors.white.withOpacity(0.04)),
      ),
      child: Material(
        color: Colors.transparent,
        child: InkWell(
          borderRadius: BorderRadius.circular(16),
          onTap: onTap,
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
            child: Row(
              children: [
                Container(
                  padding: const EdgeInsets.all(10),
                  decoration: BoxDecoration(
                    color: color.withOpacity(0.15),
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: Icon(icon, color: color, size: 22),
                ),
                const SizedBox(width: 14),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        title,
                        style: GoogleFonts.outfit(
                          fontSize: 15,
                          fontWeight: FontWeight.w700,
                          color: Colors.white,
                        ),
                      ),
                      const SizedBox(height: 2),
                      Text(
                        subtitle,
                        style: GoogleFonts.plusJakartaSans(
                          fontSize: 12,
                          color: Colors.white54,
                        ),
                      ),
                    ],
                  ),
                ),
                const Icon(Icons.arrow_forward_ios_rounded, size: 14, color: Colors.white24),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
