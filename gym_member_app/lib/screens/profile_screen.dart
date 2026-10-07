import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../core/theme/app_colors.dart';
import '../providers/auth_provider.dart';
import '../providers/member_data_provider.dart';
import '../widgets/branded_button.dart';
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
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      builder: (ctx) => StatefulBuilder(
        builder: (ctx, setModalState) => Padding(
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
                'Change Password',
                style: Theme.of(ctx).textTheme.titleMedium?.copyWith(
                  fontWeight: FontWeight.w800,
                  fontSize: 18,
                ),
              ),
              const SizedBox(height: 16),
              TextField(
                controller: currentPassController,
                obscureText: true,
                decoration: const InputDecoration(
                  labelText: 'Current Password',
                  prefixIcon: Icon(Icons.lock_outline),
                ),
              ),
              const SizedBox(height: 12),
              TextField(
                controller: newPassController,
                obscureText: true,
                decoration: const InputDecoration(
                  labelText: 'New Password (min 6 characters)',
                  prefixIcon: Icon(Icons.lock_reset),
                ),
              ),
              const SizedBox(height: 12),
              TextField(
                controller: confirmPassController,
                obscureText: true,
                decoration: const InputDecoration(
                  labelText: 'Confirm New Password',
                  prefixIcon: Icon(Icons.lock_reset),
                ),
              ),
              if (errorText != null) ...[
                const SizedBox(height: 12),
                Text(errorText!, style: const TextStyle(color: Colors.red, fontSize: 13)),
              ],
              const SizedBox(height: 20),
              BrandedButton(
                text: 'Update Password',
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
                        const SnackBar(content: Text('Password updated successfully!')),
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
    final theme = Theme.of(context);
    final auth = context.watch<AuthProvider>();
    final member = auth.currentMember;
    final isDark = theme.brightness == Brightness.dark;

    if (member == null) {
      return const Center(child: CircularProgressIndicator());
    }

    return Scaffold(
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(20),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Profile Header Card
            Container(
              padding: const EdgeInsets.all(20),
              decoration: BoxDecoration(
                color: isDark ? AppColors.darkCard : AppColors.lightCard,
                borderRadius: BorderRadius.circular(20),
                border: Border.all(color: theme.dividerTheme.color ?? Colors.grey.withValues(alpha: 0.2)),
              ),
              child: Row(
                children: [
                  CircleAvatar(
                    radius: 32,
                    backgroundColor: theme.primaryColor.withValues(alpha: 0.15),
                    child: Text(
                      member.fullname.isNotEmpty ? member.fullname[0].toUpperCase() : 'M',
                      style: TextStyle(
                        fontSize: 28,
                        fontWeight: FontWeight.w800,
                        color: theme.primaryColor,
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
                          style: theme.textTheme.titleMedium?.copyWith(
                            fontWeight: FontWeight.w800,
                            fontSize: 18,
                          ),
                        ),
                        const SizedBox(height: 2),
                        Text(
                          'ID: ${member.memberId} • @${member.username}',
                          style: TextStyle(
                            color: theme.primaryColor,
                            fontWeight: FontWeight.w600,
                            fontSize: 13,
                          ),
                        ),
                        const SizedBox(height: 4),
                        Text(
                          member.services,
                          style: theme.textTheme.bodyMedium?.copyWith(fontSize: 12),
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
              'Personal Details',
              style: theme.textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w800),
            ),
            const SizedBox(height: 10),
            _buildDetailItem(context, 'Email Address', member.email.isNotEmpty ? member.email : 'Not Provided', Icons.email_outlined),
            _buildDetailItem(context, 'Phone Number', member.phone.isNotEmpty ? member.phone : 'Not Provided', Icons.phone_outlined),
            _buildDetailItem(context, 'Gender', member.gender, Icons.person_outline),
            _buildDetailItem(context, 'Home Address', member.address.isNotEmpty ? member.address : 'Not Provided', Icons.location_on_outlined),
            _buildDetailItem(context, 'Membership Validity', '${member.startDate} to ${member.expiryDate} (${member.daysRemaining} days left)', Icons.calendar_today_outlined),
            const SizedBox(height: 24),

            // Modules & Options List
            Text(
              'Gym Modules & Services',
              style: theme.textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w800),
            ),
            const SizedBox(height: 10),
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
              'Personal Trainer Details',
              'Assigned trainer information & timings',
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
              'Security',
              style: theme.textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w800),
            ),
            const SizedBox(height: 10),
            _buildNavTile(
              context,
              'Change Password',
              'Update your member login password',
              Icons.lock_reset_rounded,
              _showChangePasswordSheet,
            ),
            const SizedBox(height: 30),
          ],
        ),
      ),
    );
  }

  Widget _buildDetailItem(BuildContext context, String label, String value, IconData icon) {
    final theme = Theme.of(context);
    final isDark = theme.brightness == Brightness.dark;

    return Container(
      margin: const EdgeInsets.only(bottom: 8),
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
      decoration: BoxDecoration(
        color: isDark ? AppColors.darkCard : AppColors.lightCard,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: theme.dividerTheme.color ?? Colors.grey.withValues(alpha: 0.2)),
      ),
      child: Row(
        children: [
          Icon(icon, size: 20, color: Colors.grey),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(label, style: const TextStyle(color: Colors.grey, fontSize: 11)),
                const SizedBox(height: 2),
                Text(value, style: theme.textTheme.bodyMedium?.copyWith(fontWeight: FontWeight.w600)),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildNavTile(BuildContext context, String title, String subtitle, IconData icon, VoidCallback onTap) {
    final theme = Theme.of(context);
    final isDark = theme.brightness == Brightness.dark;

    return Container(
      margin: const EdgeInsets.only(bottom: 8),
      decoration: BoxDecoration(
        color: isDark ? AppColors.darkCard : AppColors.lightCard,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: theme.dividerTheme.color ?? Colors.grey.withValues(alpha: 0.2)),
      ),
      child: ListTile(
        leading: Icon(icon, color: theme.primaryColor),
        title: Text(title, style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 14)),
        subtitle: Text(subtitle, style: const TextStyle(fontSize: 12)),
        trailing: const Icon(Icons.arrow_forward_ios_rounded, size: 14),
        onTap: onTap,
      ),
    );
  }
}
