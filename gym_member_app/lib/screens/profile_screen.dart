import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:provider/provider.dart';
import '../core/theme/app_colors.dart';
import '../providers/auth_provider.dart';
import '../providers/member_data_provider.dart';
import '../widgets/branded_button.dart';
import '../widgets/fitisify_logo_header.dart';
import 'diet_screen.dart';
import 'membership_screen.dart';
import 'notices_screen.dart';
import 'support_screen.dart';
import 'trainer_screen.dart';

class ProfileScreen extends StatefulWidget {
  const ProfileScreen({super.key});

  @override
  State<ProfileScreen> createState() => _ProfileScreenState();
}

class _ProfileScreenState extends State<ProfileScreen> {
  void _showChangePasswordSheet() {
    final currentPassController = TextEditingController();
    final newPassController = TextEditingController();
    final confirmPassController = TextEditingController();
    bool isLoading = false;
    String? errorText;

    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (ctx) => StatefulBuilder(
        builder: (ctx, setModalState) => Container(
          decoration: const BoxDecoration(
            color: AppColors.darkCard,
            borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
          ),
          padding: EdgeInsets.only(
            left: 24,
            right: 24,
            top: 24,
            bottom: MediaQuery.of(ctx).viewInsets.bottom + 24,
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'UPDATE PASSWORD',
                style: GoogleFonts.outfit(
                  color: AppColors.darkTextPrimary,
                  fontWeight: FontWeight.w800,
                  fontSize: 18,
                ),
              ),
              const SizedBox(height: 16),
              TextField(
                controller: currentPassController,
                obscureText: true,
                style: GoogleFonts.plusJakartaSans(color: AppColors.darkTextPrimary),
                decoration: const InputDecoration(
                  labelText: 'Current Password',
                  prefixIcon: Icon(Icons.lock_outline, color: AppColors.darkTextMuted),
                ),
              ),
              const SizedBox(height: 12),
              TextField(
                controller: newPassController,
                obscureText: true,
                style: GoogleFonts.plusJakartaSans(color: AppColors.darkTextPrimary),
                decoration: const InputDecoration(
                  labelText: 'New Password (min 6 characters)',
                  prefixIcon: Icon(Icons.lock_reset, color: AppColors.darkTextMuted),
                ),
              ),
              const SizedBox(height: 12),
              TextField(
                controller: confirmPassController,
                obscureText: true,
                style: GoogleFonts.plusJakartaSans(color: AppColors.darkTextPrimary),
                decoration: const InputDecoration(
                  labelText: 'Confirm New Password',
                  prefixIcon: Icon(Icons.lock_reset, color: AppColors.darkTextMuted),
                ),
              ),
              if (errorText != null) ...[
                const SizedBox(height: 12),
                Text(errorText!, style: const TextStyle(color: AppColors.danger, fontSize: 13)),
              ],
              const SizedBox(height: 20),
              BrandedButton(
                label: 'Save Password',
                isLoading: isLoading,
                onPressed: () async {
                  setModalState(() {
                    isLoading = true;
                    errorText = null;
                  });

                  final memberProvider = context.read<MemberDataProvider>();
                  final res = await memberProvider.changePassword(
                    currentPassController.text,
                    newPassController.text,
                    confirmPassController.text,
                  );

                  if (res == null) {
                    if (ctx.mounted) {
                      Navigator.pop(ctx);
                      ScaffoldMessenger.of(ctx).showSnackBar(
                        const SnackBar(
                          content: Text('Password updated successfully!'),
                          backgroundColor: AppColors.success,
                        ),
                      );
                    }
                  } else {
                    setModalState(() {
                      isLoading = false;
                      errorText = res;
                    });
                  }
                },
              ),
            ],
          ),
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthProvider>();
    final member = auth.currentMember;

    if (member == null) {
      return Scaffold(
        backgroundColor: AppColors.bg(context),
        body: const Center(
          child: CircularProgressIndicator(
            valueColor: AlwaysStoppedAnimation<Color>(AppColors.lime),
          ),
        ),
      );
    }

    return Scaffold(
      backgroundColor: AppColors.bg(context),
      appBar: Navigator.canPop(context)
          ? AppBar(
              backgroundColor: AppColors.bgDeep(context),
              elevation: 0,
              title: Text(
                'ATHLETE PROFILE',
                style: GoogleFonts.outfit(
                  color: AppColors.textPrimary(context),
                  fontWeight: FontWeight.w800,
                  fontSize: 18,
                  letterSpacing: 0.5,
                ),
              ),
            )
          : null,
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(20),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Profile Header Card
            Container(
              padding: const EdgeInsets.all(20),
              decoration: BoxDecoration(
                color: AppColors.card(context),
                borderRadius: BorderRadius.circular(20),
                border: Border.all(color: AppColors.limeBorder, width: 1.2),
                boxShadow: [
                  BoxShadow(
                    color: Colors.black.withValues(alpha: Theme.of(context).brightness == Brightness.dark ? 0.4 : 0.06),
                    blurRadius: 16,
                    offset: const Offset(0, 4),
                  ),
                ],
              ),
              child: Row(
                children: [
                  Container(
                    padding: const EdgeInsets.all(2),
                    decoration: BoxDecoration(
                      shape: BoxShape.circle,
                      border: Border.all(color: AppColors.lime, width: 2),
                    ),
                    child: CircleAvatar(
                      radius: 30,
                      backgroundColor: AppColors.cardElevated(context),
                      child: Text(
                        member.fullname.isNotEmpty ? member.fullname[0].toUpperCase() : 'A',
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
                        Text(
                          member.fullname,
                          style: GoogleFonts.outfit(
                            fontWeight: FontWeight.w800,
                            fontSize: 18,
                            color: AppColors.textPrimary(context),
                          ),
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                        ),
                        const SizedBox(height: 2),
                        Text(
                          'ID: ${member.memberId} • @${member.username}',
                          style: GoogleFonts.plusJakartaSans(
                            color: AppColors.lime,
                            fontWeight: FontWeight.w700,
                            fontSize: 12.5,
                          ),
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                        ),
                        const SizedBox(height: 4),
                        Text(
                          member.services,
                          style: GoogleFonts.plusJakartaSans(
                            color: AppColors.textSecondary(context),
                            fontSize: 12,
                          ),
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 24),

            // Member Info Section
            Text(
              'PERSONAL DETAILS',
              style: GoogleFonts.outfit(
                color: AppColors.textPrimary(context),
                fontWeight: FontWeight.w800,
                fontSize: 16,
                letterSpacing: 0.6,
              ),
            ),
            const SizedBox(height: 12),
            _buildDetailItem(context, 'Email Address', member.email.isNotEmpty ? member.email : 'Not Provided', Icons.email_outlined),
            _buildDetailItem(context, 'Phone Number', member.phone.isNotEmpty ? member.phone : 'Not Provided', Icons.phone_outlined),
            _buildDetailItem(context, 'Gender', member.gender, Icons.person_outline),
            _buildDetailItem(context, 'Home Address', member.address.isNotEmpty ? member.address : 'Not Provided', Icons.location_on_outlined),
            _buildDetailItem(context, 'Membership Validity', '${member.startDate} to ${member.expiryDate} (${member.daysRemaining} days left)', Icons.calendar_today_outlined),
            const SizedBox(height: 24),

            // Modules & Options List
            Text(
              'PORTAL MODULES & SERVICES',
              style: GoogleFonts.outfit(
                color: AppColors.textPrimary(context),
                fontWeight: FontWeight.w800,
                fontSize: 16,
                letterSpacing: 0.6,
              ),
            ),
            const SizedBox(height: 12),
            _buildNavTile(
              context,
              'Membership & Plan Benefits',
              'View current package, benefits & renewals',
              Icons.card_membership_rounded,
              () => Navigator.push(context, MaterialPageRoute(builder: (_) => const MembershipScreen())),
            ),
            _buildNavTile(
              context,
              'Nutrition & Diet Schedule',
              'Meal timings and prescribed calories',
              Icons.restaurant_rounded,
              () => Navigator.push(context, MaterialPageRoute(builder: (_) => const DietScreen())),
            ),
            _buildNavTile(
              context,
              'Personal Coach Details',
              'Assigned coach info & timings',
              Icons.sports_rounded,
              () => Navigator.push(context, MaterialPageRoute(builder: (_) => const TrainerScreen())),
            ),
            _buildNavTile(
              context,
              'Gym Notices & Announcements',
              'Updates and holiday notices',
              Icons.campaign_rounded,
              () => Navigator.push(context, MaterialPageRoute(builder: (_) => const NoticesScreen())),
            ),
            _buildNavTile(
              context,
              'Support & Gym Policies',
              'Timings, contact info & submit ticket',
              Icons.help_outline_rounded,
              () => Navigator.push(context, MaterialPageRoute(builder: (_) => const SupportScreen())),
            ),
            const SizedBox(height: 24),

            // Security Section
            Text(
              'SECURITY',
              style: GoogleFonts.outfit(
                color: AppColors.textPrimary(context),
                fontWeight: FontWeight.w800,
                fontSize: 16,
                letterSpacing: 0.6,
              ),
            ),
            const SizedBox(height: 12),
            _buildNavTile(
              context,
              'Change Password',
              'Update your member login password',
              Icons.lock_reset_rounded,
              _showChangePasswordSheet,
            ),
            const SizedBox(height: 24),
            Center(
              child: Column(
                children: [
                  const FitisifyLogoHeader(iconSize: 28, fontSize: 16),
                  const SizedBox(height: 4),
                  Text(
                    'Version 2.0 • Premium Gym Operating System',
                    style: GoogleFonts.plusJakartaSans(
                      fontSize: 11,
                      color: AppColors.textMuted(context),
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 30),
          ],
        ),
      ),
    );
  }

  Widget _buildDetailItem(BuildContext context, String label, String value, IconData icon) {
    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
      decoration: BoxDecoration(
        color: AppColors.card(context),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: AppColors.border(context)),
      ),
      child: Row(
        children: [
          Icon(icon, size: 20, color: AppColors.lime),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(label, style: GoogleFonts.plusJakartaSans(color: AppColors.textMuted(context), fontSize: 11, fontWeight: FontWeight.w700)),
                const SizedBox(height: 2),
                Text(value, style: GoogleFonts.plusJakartaSans(color: AppColors.textPrimary(context), fontWeight: FontWeight.w700, fontSize: 13.5)),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildNavTile(BuildContext context, String title, String subtitle, IconData icon, VoidCallback onTap) {
    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      decoration: BoxDecoration(
        color: AppColors.card(context),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: AppColors.border(context)),
      ),
      child: Material(
        color: Colors.transparent,
        child: ListTile(
        leading: Icon(icon, color: AppColors.lime),
        title: Text(
          title,
          style: GoogleFonts.plusJakartaSans(
            fontWeight: FontWeight.w800,
            fontSize: 14,
            color: AppColors.textPrimary(context),
          ),
        ),
        subtitle: Text(
          subtitle,
          style: GoogleFonts.plusJakartaSans(
            fontSize: 12,
            color: AppColors.textSecondary(context),
          ),
        ),
        trailing: Icon(Icons.arrow_forward_ios_rounded, size: 14, color: AppColors.textMuted(context)),
        onTap: onTap,
      ),
    ),
  );
  }
}
