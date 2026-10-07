import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../core/storage/secure_storage_service.dart';
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
    Navigator.pop(context); // Close dialog

    final auth = context.read<AuthProvider>();
    final dashboard = context.read<DashboardProvider>();
    final memberData = context.read<MemberDataProvider>();
    final theme = context.read<ThemeProvider>();

    // 1. Purge current state & memory
    dashboard.clear();
    memberData.clearAll();

    // 2. Switch gym tenant
    final success = await auth.switchGym(gymCode);
    if (success && auth.currentTenant != null) {
      theme.updateBranding(
        primaryHex: auth.currentTenant!.primaryColor,
        secondaryHex: auth.currentTenant!.secondaryColor,
      );
    }

    // 3. Direct to login screen for that gym
    if (mounted) {
      Navigator.of(context).pushAndRemoveUntil(
        MaterialPageRoute(builder: (_) => const LoginScreen()),
        (route) => false,
      );
    }
  }

  Future<void> _addNewGym() async {
    final code = _newCodeController.text.trim();
    if (code.isEmpty) return;
    _handleSwitch(code);
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final auth = context.watch<AuthProvider>();
    final currentGymId = auth.currentTenant?.id;

    return AlertDialog(
      title: const Row(
        children: [
          Icon(Icons.sync_alt_rounded),
          SizedBox(width: 10),
          Text('Gym Switcher'),
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
                'Switch between your registered gyms or add another gym membership:',
                style: theme.textTheme.bodyMedium,
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
                          ? theme.primaryColor.withValues(alpha: 0.12)
                          : Theme.of(context).cardTheme.color,
                      borderRadius: BorderRadius.circular(12),
                      border: Border.all(
                        color: isCurrent
                            ? theme.primaryColor
                            : (theme.dividerTheme.color ?? Colors.grey.withValues(alpha: 0.2)),
                      ),
                    ),
                    child: ListTile(
                      leading: Icon(
                        Icons.store_mall_directory_rounded,
                        color: isCurrent ? theme.primaryColor : Colors.grey,
                      ),
                      title: Text(
                        g['gym_name'] ?? 'Gym',
                        style: TextStyle(
                          fontWeight: isCurrent ? FontWeight.w700 : FontWeight.w500,
                          fontSize: 14,
                        ),
                      ),
                      subtitle: Text('Code: ${g['gym_code']}', style: const TextStyle(fontSize: 12)),
                      trailing: isCurrent
                          ? const Icon(Icons.check_circle, color: Colors.green, size: 20)
                          : const Icon(Icons.arrow_forward_ios, size: 14),
                      onTap: isCurrent ? null : () => _handleSwitch(g['gym_code']),
                    ),
                  );
                }),
                const SizedBox(height: 12),
              ],

              // Add Another Gym Accordion
              if (!_isAdding)
                TextButton.icon(
                  onPressed: () {
                    setState(() {
                      _isAdding = true;
                    });
                  },
                  icon: const Icon(Icons.add_rounded),
                  label: const Text('Add Another Gym by Code'),
                )
              else ...[
                TextField(
                  controller: _newCodeController,
                  textCapitalization: TextCapitalization.characters,
                  decoration: InputDecoration(
                    labelText: 'Enter New Gym Code',
                    hintText: 'e.g. GYM-B',
                    suffixIcon: IconButton(
                      icon: const Icon(Icons.arrow_forward),
                      onPressed: _addNewGym,
                    ),
                  ),
                  onSubmitted: (_) => _addNewGym(),
                ),
              ],
            ],
          ),
        ),
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.pop(context),
          child: const Text('Close'),
        ),
      ],
    );
  }
}
