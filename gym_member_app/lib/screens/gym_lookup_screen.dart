import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../core/theme/app_colors.dart';
import '../core/theme/theme_provider.dart';
import '../providers/auth_provider.dart';
import '../widgets/branded_button.dart';
import 'login_screen.dart';

class GymLookupScreen extends StatefulWidget {
  const GymLookupScreen({super.key});

  @override
  State<GymLookupScreen> createState() => _GymLookupScreenState();
}

class _GymLookupScreenState extends State<GymLookupScreen> {
  final _formKey = GlobalKey<FormState>();
  final _codeController = TextEditingController();
  bool _hasSearched = false;

  @override
  void dispose() {
    _codeController.dispose();
    super.dispose();
  }

  Future<void> _handleLookup() async {
    if (!_formKey.currentState!.validate()) return;
    FocusScope.of(context).unfocus();

    final auth = context.read<AuthProvider>();
    final themeProvider = context.read<ThemeProvider>();

    final success = await auth.lookupGym(_codeController.text.trim());
    if (success && mounted && auth.currentTenant != null) {
      // Update dynamic branding colors
      themeProvider.updateBranding(
        primaryHex: auth.currentTenant!.primaryColor,
        secondaryHex: auth.currentTenant!.secondaryColor,
      );
      setState(() {
        _hasSearched = true;
      });
    }
  }

  void _proceedToLogin() {
    Navigator.of(context).pushReplacement(
      MaterialPageRoute(builder: (_) => const LoginScreen()),
    );
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final auth = context.watch<AuthProvider>();
    final isDark = theme.brightness == Brightness.dark;

    return Scaffold(
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 32),
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 440),
              child: Form(
                key: _formKey,
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    // App Branding Icon
                    Center(
                      child: Container(
                        padding: const EdgeInsets.all(20),
                        decoration: BoxDecoration(
                          color: theme.primaryColor.withValues(alpha: 0.15),
                          shape: BoxShape.circle,
                        ),
                        child: Icon(
                          Icons.fitness_center_rounded,
                          size: 54,
                          color: theme.primaryColor,
                        ),
                      ),
                    ),
                    const SizedBox(height: 24),

                    // Title
                    Text(
                      'Find Your Gym',
                      style: theme.textTheme.titleLarge?.copyWith(
                        fontSize: 26,
                        fontWeight: FontWeight.w800,
                      ),
                      textAlign: TextAlign.center,
                    ),
                    const SizedBox(height: 8),
                    Text(
                      'Enter your unique Gym Code provided by your fitness club to load your personal member portal.',
                      style: theme.textTheme.bodyMedium?.copyWith(height: 1.4),
                      textAlign: TextAlign.center,
                    ),
                    const SizedBox(height: 32),

                    // Gym Code Input
                    TextFormField(
                      controller: _codeController,
                      textCapitalization: TextCapitalization.characters,
                      decoration: InputDecoration(
                        labelText: 'Gym Code',
                        hintText: 'e.g. GYM-A, GYM-B, GYM-FIT01',
                        prefixIcon: const Icon(Icons.qr_code_rounded),
                        suffixIcon: IconButton(
                          icon: const Icon(Icons.qr_code_scanner_rounded),
                          tooltip: 'Quick Fill Test Code',
                          onPressed: () {
                            _codeController.text = 'GYM-A';
                          },
                        ),
                      ),
                      validator: (value) {
                        if (value == null || value.trim().isEmpty) {
                          return 'Please enter your Gym Code';
                        }
                        return null;
                      },
                      onFieldSubmitted: (_) => _handleLookup(),
                    ),
                    const SizedBox(height: 12),

                    // Quick test code chips
                    Wrap(
                      spacing: 8,
                      alignment: WrapAlignment.center,
                      children: [
                        ActionChip(
                          avatar: const Icon(Icons.tag, size: 14),
                          label: const Text('Gym A (GYM-A)'),
                          onPressed: () {
                            _codeController.text = 'GYM-A';
                            _handleLookup();
                          },
                        ),
                        ActionChip(
                          avatar: const Icon(Icons.tag, size: 14),
                          label: const Text('Gym B (GYM-B)'),
                          onPressed: () {
                            _codeController.text = 'GYM-B';
                            _handleLookup();
                          },
                        ),
                      ],
                    ),
                    const SizedBox(height: 20),

                    // Search Button
                    BrandedButton(
                      text: 'Verify Gym Code',
                      icon: Icons.search_rounded,
                      isLoading: auth.isLoading,
                      onPressed: _handleLookup,
                    ),

                    // Error Message
                    if (auth.errorMessage != null) ...[
                      const SizedBox(height: 16),
                      Container(
                        padding: const EdgeInsets.all(12),
                        decoration: BoxDecoration(
                          color: AppColors.danger.withValues(alpha: 0.12),
                          borderRadius: BorderRadius.circular(10),
                          border: Border.all(color: AppColors.danger.withValues(alpha: 0.4)),
                        ),
                        child: Row(
                          children: [
                            const Icon(Icons.error_outline_rounded, color: AppColors.danger, size: 20),
                            const SizedBox(width: 10),
                            Expanded(
                              child: Text(
                                auth.errorMessage!,
                                style: const TextStyle(color: AppColors.danger, fontSize: 13, fontWeight: FontWeight.w500),
                              ),
                            ),
                          ],
                        ),
                      ),
                    ],

                    // Gym Verified Preview Card
                    if (auth.currentTenant != null && _hasSearched) ...[
                      const SizedBox(height: 28),
                      Container(
                        padding: const EdgeInsets.all(20),
                        decoration: BoxDecoration(
                          color: isDark ? AppColors.darkCard : AppColors.lightCard,
                          borderRadius: BorderRadius.circular(16),
                          border: Border.all(
                            color: theme.primaryColor.withValues(alpha: 0.4),
                            width: 1.5,
                          ),
                          boxShadow: [
                            BoxShadow(
                              color: theme.primaryColor.withValues(alpha: 0.1),
                              blurRadius: 16,
                              offset: const Offset(0, 4),
                            ),
                          ],
                        ),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Row(
                              children: [
                                Container(
                                  width: 48,
                                  height: 48,
                                  decoration: BoxDecoration(
                                    color: theme.primaryColor.withValues(alpha: 0.2),
                                    borderRadius: BorderRadius.circular(12),
                                  ),
                                  child: Center(
                                    child: Icon(Icons.store_mall_directory_rounded, color: theme.primaryColor, size: 28),
                                  ),
                                ),
                                const SizedBox(width: 14),
                                Expanded(
                                  child: Column(
                                    crossAxisAlignment: CrossAxisAlignment.start,
                                    children: [
                                      Text(
                                        auth.currentTenant!.gymName,
                                        style: theme.textTheme.titleMedium?.copyWith(
                                          fontWeight: FontWeight.w700,
                                          fontSize: 17,
                                        ),
                                      ),
                                      const SizedBox(height: 2),
                                      Text(
                                        'Code: ${auth.currentTenant!.gymCode}',
                                        style: TextStyle(
                                          color: theme.primaryColor,
                                          fontWeight: FontWeight.w600,
                                          fontSize: 12.5,
                                        ),
                                      ),
                                    ],
                                  ),
                                ),
                              ],
                            ),
                            const SizedBox(height: 14),
                            const Divider(),
                            const SizedBox(height: 12),
                            Row(
                              children: [
                                const Icon(Icons.location_on_outlined, size: 16, color: Colors.grey),
                                const SizedBox(width: 6),
                                Expanded(
                                  child: Text(
                                    auth.currentTenant!.address,
                                    style: theme.textTheme.bodyMedium?.copyWith(fontSize: 13),
                                    maxLines: 2,
                                    overflow: TextOverflow.ellipsis,
                                  ),
                                ),
                              ],
                            ),
                            if (auth.currentTenant!.phone.isNotEmpty) ...[
                              const SizedBox(height: 8),
                              Row(
                                children: [
                                  const Icon(Icons.phone_outlined, size: 16, color: Colors.grey),
                                  const SizedBox(width: 6),
                                  Text(
                                    auth.currentTenant!.phone,
                                    style: theme.textTheme.bodyMedium?.copyWith(fontSize: 13),
                                  ),
                                ],
                              ),
                            ],
                            const SizedBox(height: 20),
                            BrandedButton(
                              text: 'Continue with this Gym',
                              icon: Icons.arrow_forward_rounded,
                              onPressed: _proceedToLogin,
                            ),
                          ],
                        ),
                      ),
                    ],
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}
