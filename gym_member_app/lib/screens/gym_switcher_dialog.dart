import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:provider/provider.dart';
import '../core/storage/secure_storage_service.dart';
import '../core/theme/app_colors.dart';
import '../core/theme/theme_provider.dart';
import '../providers/auth_provider.dart';
import '../providers/dashboard_provider.dart';
import '../providers/member_data_provider.dart';
import 'login_screen.dart';

class GymSwitcherDialog extends StatefulWidget {
  const GymSwitcherDialog({super.key});

  @override
  State<GymSwitcherDialog> createState() => _GymSwitcherDialogState();
}

class _GymSwitcherDialogState extends State<GymSwitcherDialog> {
  List<Map<String, dynamic>> _savedGyms = [];
  final _newCodeController = TextEditingController();
  bool _isAdding = false;

  @override
  void initState() {
    super.initState();
    _loadSavedGyms();
  }

  Future<void> _loadSavedGyms() async {
    final gyms = await SecureStorageService.getSavedGymTenants();
    setState(() {
      _savedGyms = gyms;
    });
  }

  @override
  void dispose() {
    _newCodeController.dispose();
    super.dispose();
  }

  Future<void> _handleSwitch(String gymCode) async {
    Navigator.pop(context);

    final auth = context.read<AuthProvider>();
    final dashboard = context.read<DashboardProvider>();
    final memberData = context.read<MemberDataProvider>();
    final theme = context.read<ThemeProvider>();

    dashboard.clear();
    memberData.clearAll();

    final success = await auth.switchGym(gymCode);
    if (success && auth.currentTenant != null) {
      theme.updateBranding(
        primaryHex: auth.currentTenant!.primaryColor,
        secondaryHex: auth.currentTenant!.secondaryColor,
      );
    }

    if (mounted) {
      Navigator.of(context).pushAndRemoveUntil(
        MaterialPageRoute(builder: (_) => const LoginScreen()),
        (route) => false,
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthProvider>();
    final currentGymId = auth.currentTenant?.id;

    return AlertDialog(
      backgroundColor: AppColors.darkCard,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(22),
        side: const BorderSide(color: AppColors.darkBorder),
      ),
      title: Row(
        children: [
          Container(
            padding: const EdgeInsets.all(8),
            decoration: BoxDecoration(
              color: AppColors.lime.withValues(alpha: 0.12),
              borderRadius: BorderRadius.circular(10),
            ),
            child: const Icon(Icons.sync_alt_rounded, color: AppColors.lime, size: 20),
          ),
          const SizedBox(width: 12),
          Text(
            'GYM SWITCHER',
            style: GoogleFonts.outfit(
              color: AppColors.darkTextPrimary,
              fontWeight: FontWeight.w800,
              fontSize: 17,
              letterSpacing: 0.4,
            ),
          ),
        ],
      ),
      content: ConstrainedBox(
        constraints: const BoxConstraints(maxWidth: 380),
        child: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'Switch between your registered gym clubs or add a new membership code:',
                style: GoogleFonts.plusJakartaSans(
                  color: AppColors.darkTextSecondary,
                  fontSize: 13,
                ),
              ),
              const SizedBox(height: 16),

              // Saved Gyms List
              if (_savedGyms.isNotEmpty) ...[
                ..._savedGyms.map((g) {
                  final isCurrent = (g['id'] == currentGymId);
                  return Container(
                    margin: const EdgeInsets.only(bottom: 8),
                    decoration: BoxDecoration(
                      color: isCurrent
                          ? AppColors.lime.withValues(alpha: 0.1)
                          : AppColors.darkCardElevated,
                      borderRadius: BorderRadius.circular(14),
                      border: Border.all(
                        color: isCurrent ? AppColors.limeBorder : AppColors.darkBorder,
                      ),
                    ),
                    child: Material(
                      color: Colors.transparent,
                      child: ListTile(
                        leading: Icon(
                          Icons.storefront_rounded,
                          color: isCurrent ? AppColors.lime : AppColors.darkTextMuted,
                        ),
                        title: Text(
                          g['gym_name'] ?? 'Gym Club',
                          style: GoogleFonts.plusJakartaSans(
                            fontWeight: isCurrent ? FontWeight.w800 : FontWeight.w600,
                            fontSize: 14,
                            color: AppColors.darkTextPrimary,
                          ),
                        ),
                        subtitle: Text(
                          'Code: ${g['gym_code']}',
                          style: GoogleFonts.plusJakartaSans(fontSize: 12, color: AppColors.darkTextSecondary),
                        ),
                        trailing: isCurrent
                            ? const Icon(Icons.check_circle_rounded, color: AppColors.lime, size: 20)
                            : const Icon(Icons.arrow_forward_ios_rounded, size: 14, color: AppColors.darkTextMuted),
                        onTap: isCurrent ? null : () => _handleSwitch(g['gym_code']),
                      ),
                    ),
                  );
                }),
                const SizedBox(height: 12),
              ],

              // Add Another Gym
              if (!_isAdding)
                TextButton.icon(
                  onPressed: () {
                    setState(() {
                      _isAdding = true;
                    });
                  },
                  icon: const Icon(Icons.add_rounded, color: AppColors.lime),
                  label: Text(
                    'Add Another Gym Code',
                    style: GoogleFonts.plusJakartaSans(
                      color: AppColors.lime,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                )
              else ...[
                TextField(
                  controller: _newCodeController,
                  textCapitalization: TextCapitalization.characters,
                  style: GoogleFonts.plusJakartaSans(color: AppColors.darkTextPrimary),
                  decoration: InputDecoration(
                    labelText: 'Enter Gym Code',
                    hintText: 'e.g. GYM-PRO',
                    suffixIcon: IconButton(
                      icon: const Icon(Icons.arrow_forward_rounded, color: AppColors.lime),
                      onPressed: () {
                        final code = _newCodeController.text.trim();
                        if (code.isNotEmpty) _handleSwitch(code);
                      },
                    ),
                  ),
                ),
              ],
            ],
          ),
        ),
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.pop(context),
          child: const Text('Close', style: TextStyle(color: AppColors.darkTextSecondary)),
        ),
      ],
    );
  }
}
