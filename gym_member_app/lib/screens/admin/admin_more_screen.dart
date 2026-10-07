import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:provider/provider.dart';
import '../../core/theme/app_colors.dart';
import '../../core/theme/theme_provider.dart';
import '../../providers/auth_provider.dart';
import '../gym_lookup_screen.dart';
import '../login_screen.dart';
import 'admin_add_member_screen.dart';
import 'admin_gym_qr_screen.dart';

class AdminMoreScreen extends StatelessWidget {
  const AdminMoreScreen({super.key});

  void _handleLogout(BuildContext context) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        backgroundColor: AppColors.card(ctx),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
        title: Text(
          'Sign Out',
          style: GoogleFonts.outfit(fontWeight: FontWeight.w800, color: AppColors.textPrimary(ctx)),
        ),
        content: Text(
          'Are you sure you want to log out from this Admin session?',
          style: GoogleFonts.plusJakartaSans(color: AppColors.textMuted(ctx)),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx, false),
            child: Text('Cancel', style: GoogleFonts.plusJakartaSans(color: AppColors.textMuted(ctx))),
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
    final themeProvider = context.watch<ThemeProvider>();
    final tenant = auth.currentTenant;
    final adminUser = auth.adminUser;
    final isDark = Theme.of(context).brightness == Brightness.dark;

    final name = adminUser?['fullname'] ?? adminUser?['username'] ?? 'Gym Admin';
    final email = adminUser?['email'] ?? '';
    final phone = adminUser?['phone'] ?? '';
    final role = (adminUser?['role'] ?? auth.userRole).toString().toUpperCase();

    return Scaffold(
      backgroundColor: AppColors.bg(context),
      appBar: AppBar(
        title: Text(
          'Admin Settings & Gym Info',
          style: GoogleFonts.outfit(fontWeight: FontWeight.w900, fontSize: 18),
        ),
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(20),
        child: Center(
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 500),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                // 1. Admin Profile Card
                Container(
                  padding: const EdgeInsets.all(20),
                  decoration: BoxDecoration(
                    color: AppColors.card(context),
                    borderRadius: BorderRadius.circular(22),
                    border: Border.all(color: AppColors.border(context)),
                    boxShadow: [
                      BoxShadow(
                        color: Colors.black.withValues(alpha: 0.2),
                        blurRadius: 15,
                        offset: const Offset(0, 6),
                      ),
                    ],
                  ),
                  child: Row(
                    children: [
                      Container(
                        width: 64,
                        height: 64,
                        decoration: BoxDecoration(
                          shape: BoxShape.circle,
                          color: AppColors.lime.withValues(alpha: 0.15),
                          border: Border.all(color: AppColors.lime, width: 2),
                        ),
                        child: Center(
                          child: Text(
                            name.isNotEmpty ? name[0].toUpperCase() : 'A',
                            style: GoogleFonts.outfit(
                              fontSize: 26,
                              fontWeight: FontWeight.w900,
                              color: AppColors.lime,
                            ),
                          ),
                        ),
                      ),
                      const SizedBox(width: 16),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Row(
                              children: [
                                Expanded(
                                  child: Text(
                                    name,
                                    style: GoogleFonts.outfit(
                                      fontSize: 18,
                                      fontWeight: FontWeight.w800,
                                      color: AppColors.textPrimary(context),
                                    ),
                                    overflow: TextOverflow.ellipsis,
                                  ),
                                ),
                              ],
                            ),
                            const SizedBox(height: 4),
                            Container(
                              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
                              decoration: BoxDecoration(
                                color: AppColors.lime.withValues(alpha: 0.15),
                                borderRadius: BorderRadius.circular(6),
                                border: Border.all(color: AppColors.limeBorder),
                              ),
                              child: Text(
                                role,
                                style: GoogleFonts.plusJakartaSans(
                                  fontSize: 10,
                                  fontWeight: FontWeight.w800,
                                  color: AppColors.lime,
                                ),
                              ),
                            ),
                            if (email.isNotEmpty) ...[
                              const SizedBox(height: 4),
                              Text(
                                email,
                                style: GoogleFonts.plusJakartaSans(
                                  fontSize: 12,
                                  color: AppColors.textMuted(context),
                                ),
                                overflow: TextOverflow.ellipsis,
                              ),
                            ],
                            if (phone.isNotEmpty) ...[
                              const SizedBox(height: 2),
                              Text(
                                phone,
                                style: GoogleFonts.plusJakartaSans(
                                  fontSize: 11.5,
                                  color: AppColors.textMuted(context),
                                ),
                                overflow: TextOverflow.ellipsis,
                              ),
                            ],
                          ],
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 20),

                // 2. Gym Details
                if (tenant != null) ...[
                  Text(
                    'GYM FACILITY DETAILS',
                    style: GoogleFonts.plusJakartaSans(
                      fontSize: 11,
                      fontWeight: FontWeight.w800,
                      color: AppColors.textMuted(context),
                      letterSpacing: 0.6,
                    ),
                  ),
                  const SizedBox(height: 10),
                  Container(
                    padding: const EdgeInsets.all(18),
                    decoration: BoxDecoration(
                      color: AppColors.card(context),
                      borderRadius: BorderRadius.circular(20),
                      border: Border.all(color: AppColors.border(context)),
                    ),
                    child: Column(
                      children: [
                        _detailTile('Gym Name', tenant.gymName),
                        const Divider(height: 16),
                        _detailTile('Gym Code', tenant.gymCode),
                        const Divider(height: 16),
                        _detailTile('Currency', tenant.currency.isNotEmpty ? tenant.currency : '₹'),
                        if (tenant.phone.isNotEmpty) ...[
                          const Divider(height: 16),
                          _detailTile('Contact Phone', tenant.phone),
                        ],
                        if (tenant.address.isNotEmpty) ...[
                          const Divider(height: 16),
                          _detailTile('Address', tenant.address),
                        ],
                      ],
                    ),
                  ),
                  const SizedBox(height: 20),
                ],

                // 3. Quick Navigation Options
                Text(
                  'ADMIN TOOLS',
                  style: GoogleFonts.plusJakartaSans(
                    fontSize: 11,
                    fontWeight: FontWeight.w800,
                    color: AppColors.textMuted(context),
                    letterSpacing: 0.6,
                  ),
                ),
                const SizedBox(height: 10),

                _menuItem(
                  icon: Icons.person_add_alt_1_rounded,
                  title: 'Register New Member',
                  subtitle: 'Photo upload + fee entry',
                  color: AppColors.lime,
                  onTap: () {
                    Navigator.of(context).push(
                      MaterialPageRoute(builder: (_) => const AdminAddMemberScreen()),
                    );
                  },
                ),
                const SizedBox(height: 8),

                _menuItem(
                  icon: Icons.qr_code_2_rounded,
                  title: 'Gym UPI QR Code',
                  subtitle: 'Display counter payment QR',
                  color: AppColors.cyan,
                  onTap: () {
                    Navigator.of(context).push(
                      MaterialPageRoute(builder: (_) => const AdminGymQrScreen(isModal: true)),
                    );
                  },
                ),
                const SizedBox(height: 8),

                _menuItem(
                  icon: isDark ? Icons.light_mode_rounded : Icons.dark_mode_rounded,
                  title: 'Theme Mode',
                  subtitle: isDark ? 'Currently Dark Theme' : 'Currently Light Theme',
                  color: const Color(0xFFF4C430),
                  onTap: () => themeProvider.toggleDarkMode(),
                ),
                const SizedBox(height: 8),

                _menuItem(
                  icon: Icons.swap_horiz_rounded,
                  title: 'Switch Gym Tenant',
                  subtitle: 'Connect to another branch/gym',
                  color: AppColors.textPrimary(context),
                  onTap: () => _handleChangeGym(context),
                ),
                const SizedBox(height: 8),

                _menuItem(
                  icon: Icons.logout_rounded,
                  title: 'Log Out',
                  subtitle: 'Sign out from admin console',
                  color: AppColors.danger,
                  onTap: () => _handleLogout(context),
                ),
                const SizedBox(height: 30),

                // Version tag
                Center(
                  child: Text(
                    'FITISIFY OS • Admin Mobile Console v1.0.1\nPowered by NexoraLab Technologies',
                    style: GoogleFonts.plusJakartaSans(
                      fontSize: 11,
                      fontWeight: FontWeight.w600,
                      color: AppColors.textMuted(context),
                      height: 1.4,
                    ),
                    textAlign: TextAlign.center,
                  ),
                ),
                const SizedBox(height: 40),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Widget _detailTile(String label, String value) {
    return Builder(
      builder: (ctx) {
        return Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            Text(
              label,
              style: GoogleFonts.plusJakartaSans(
                fontSize: 12,
                fontWeight: FontWeight.w600,
                color: AppColors.textMuted(ctx),
              ),
            ),
            Flexible(
              child: Text(
                value,
                style: GoogleFonts.plusJakartaSans(
                  fontSize: 13,
                  fontWeight: FontWeight.w800,
                  color: AppColors.textPrimary(ctx),
                ),
                textAlign: TextAlign.end,
              ),
            ),
          ],
        );
      },
    );
  }

  Widget _menuItem({
    required IconData icon,
    required String title,
    required String subtitle,
    required Color color,
    required VoidCallback onTap,
  }) {
    return Builder(
      builder: (ctx) {
        return InkWell(
          onTap: onTap,
          borderRadius: BorderRadius.circular(16),
          child: Container(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
            decoration: BoxDecoration(
              color: AppColors.card(ctx),
              borderRadius: BorderRadius.circular(16),
              border: Border.all(color: AppColors.border(ctx)),
            ),
            child: Row(
              children: [
                Container(
                  padding: const EdgeInsets.all(10),
                  decoration: BoxDecoration(
                    color: color.withValues(alpha: 0.12),
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: Icon(icon, color: color, size: 20),
                ),
                const SizedBox(width: 14),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        title,
                        style: GoogleFonts.plusJakartaSans(
                          fontSize: 14,
                          fontWeight: FontWeight.w800,
                          color: AppColors.textPrimary(ctx),
                        ),
                      ),
                      Text(
                        subtitle,
                        style: GoogleFonts.plusJakartaSans(
                          fontSize: 11.5,
                          color: AppColors.textMuted(ctx),
                        ),
                      ),
                    ],
                  ),
                ),
                Icon(Icons.chevron_right_rounded, color: AppColors.textMuted(ctx), size: 20),
              ],
            ),
          ),
        );
      },
    );
  }
}
