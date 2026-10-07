import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:provider/provider.dart';
import '../core/theme/app_colors.dart';
import '../providers/auth_provider.dart';
import '../providers/dashboard_provider.dart';
import '../widgets/branded_button.dart';
import '../providers/admin_provider.dart';
import 'admin/admin_navigation_screen.dart';
import 'forgot_password_dialog.dart';
import 'gym_lookup_screen.dart';
import 'main_navigation_screen.dart';

class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key});

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final _formKey = GlobalKey<FormState>();
  final _loginIdController = TextEditingController();
  final _passwordController = TextEditingController();
  bool _obscurePassword = true;

  @override
  void dispose() {
    _loginIdController.dispose();
    _passwordController.dispose();
    super.dispose();
  }

  Future<void> _handleLogin() async {
    if (!_formKey.currentState!.validate()) return;
    FocusScope.of(context).unfocus();

    final auth = context.read<AuthProvider>();

    final success = await auth.login(
      loginId: _loginIdController.text.trim(),
      password: _passwordController.text,
    );

    if (success && mounted) {
      if (auth.isAdmin) {
        final adminProvider = context.read<AdminProvider>();
        adminProvider.fetchDashboard(refresh: true);
        Navigator.of(context).pushReplacement(
          MaterialPageRoute(builder: (_) => const AdminNavigationScreen()),
        );
      } else {
        final dashboardProvider = context.read<DashboardProvider>();
        dashboardProvider.fetchDashboard(refresh: true);
        Navigator.of(context).pushReplacement(
          MaterialPageRoute(builder: (_) => const MainNavigationScreen()),
        );
      }
    }
  }

  void _handleChangeGym() {
    context.read<AuthProvider>().changeGym();
    Navigator.of(context).pushReplacement(
      MaterialPageRoute(builder: (_) => const GymLookupScreen()),
    );
  }

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthProvider>();
    final tenant = auth.currentTenant;

    if (tenant == null) {
      return const GymLookupScreen();
    }

    return Scaffold(
      extendBodyBehindAppBar: true,
      backgroundColor: AppColors.bg(context),
      appBar: AppBar(
        backgroundColor: Colors.transparent,
        elevation: 0,
        surfaceTintColor: Colors.transparent,
        automaticallyImplyLeading: false,
        actions: [
          TextButton.icon(
            onPressed: _handleChangeGym,
            icon: Icon(Icons.swap_horiz_rounded, size: 18, color: AppColors.primaryText(context)),
            label: Text(
              'Change Gym',
              style: GoogleFonts.plusJakartaSans(
                color: AppColors.primaryText(context),
                fontWeight: FontWeight.w700,
                fontSize: 13,
              ),
            ),
          ),
          const SizedBox(width: 8),
        ],
      ),
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 16),
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 420),
              child: Container(
                padding: const EdgeInsets.all(28),
                decoration: BoxDecoration(
                  color: AppColors.card(context),
                  borderRadius: BorderRadius.circular(24),
                  border: Border.all(color: AppColors.border(context), width: 1.2),
                  boxShadow: [
                    BoxShadow(
                      color: Colors.black.withValues(alpha: Theme.of(context).brightness == Brightness.dark ? 0.7 : 0.08),
                      blurRadius: 30,
                      offset: const Offset(0, 10),
                    ),
                  ],
                ),
                child: Form(
                  key: _formKey,
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      // Gym Logo Icon
                      Center(
                        child: Container(
                          width: 68,
                          height: 68,
                          decoration: BoxDecoration(
                            color: const Color(0xFF131A24),
                            borderRadius: BorderRadius.circular(20),
                            border: Border.all(color: AppColors.limeBorder, width: 1.5),
                            boxShadow: [
                              BoxShadow(
                                color: AppColors.lime.withValues(alpha: 0.15),
                                blurRadius: 15,
                              ),
                            ],
                          ),
                          child: ClipRRect(
                            borderRadius: BorderRadius.circular(14),
                            child: Image.asset(
                              'assets/images/app_logo.png',
                              fit: BoxFit.contain,
                              errorBuilder: (ctx, err, stack) => const Icon(
                                Icons.bolt_rounded,
                                size: 34,
                                color: AppColors.lime,
                              ),
                            ),
                          ),
                        ),
                      ),
                      const SizedBox(height: 18),

                      Text(
                        tenant.gymName.toUpperCase(),
                        style: GoogleFonts.outfit(
                          fontSize: 20,
                          fontWeight: FontWeight.w900,
                          color: AppColors.textPrimary(context),
                          letterSpacing: -0.3,
                        ),
                        textAlign: TextAlign.center,
                      ),
                      const SizedBox(height: 6),

                      // Gym Code Badge
                      Center(
                        child: Container(
                          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 3),
                          decoration: BoxDecoration(
                            color: Theme.of(context).brightness == Brightness.dark ? AppColors.lime.withValues(alpha: 0.1) : const Color(0xFFDCFCE7),
                            borderRadius: BorderRadius.circular(6),
                            border: Border.all(color: Theme.of(context).brightness == Brightness.dark ? AppColors.limeBorder : const Color(0xFF86EFAC)),
                          ),
                          child: Text(
                            'PORTAL: ${tenant.gymCode}',
                            style: GoogleFonts.plusJakartaSans(
                              color: AppColors.primaryText(context),
                              fontWeight: FontWeight.w800,
                              fontSize: 10.5,
                              letterSpacing: 0.8,
                            ),
                          ),
                        ),
                      ),
                      const SizedBox(height: 8),
                      Text(
                        'FITISIFY OS • Your Complete Fitness Experience',
                        style: GoogleFonts.plusJakartaSans(
                          fontSize: 11.5,
                          fontWeight: FontWeight.w600,
                          color: AppColors.textMuted(context),
                        ),
                        textAlign: TextAlign.center,
                      ),
                      const SizedBox(height: 24),

                      if (auth.errorMessage != null) ...[
                        Container(
                          padding: const EdgeInsets.all(12),
                          decoration: BoxDecoration(
                            color: AppColors.danger.withValues(alpha: 0.12),
                            borderRadius: BorderRadius.circular(12),
                            border: Border.all(color: AppColors.danger.withValues(alpha: 0.3)),
                          ),
                          child: Row(
                            children: [
                              const Icon(Icons.error_outline_rounded, color: AppColors.danger, size: 18),
                              const SizedBox(width: 10),
                              Expanded(
                                child: Text(
                                  auth.errorMessage!,
                                  style: GoogleFonts.plusJakartaSans(
                                    color: AppColors.danger,
                                    fontSize: 12.5,
                                    fontWeight: FontWeight.w600,
                                  ),
                                ),
                              ),
                            ],
                          ),
                        ),
                        const SizedBox(height: 18),
                      ],

                      // Login ID
                      TextFormField(
                        controller: _loginIdController,
                        style: GoogleFonts.plusJakartaSans(color: AppColors.darkTextPrimary, fontSize: 14),
                        decoration: const InputDecoration(
                          labelText: 'Username or Email',
                          hintText: 'Enter member username',
                          prefixIcon: Icon(Icons.person_outline_rounded, color: AppColors.darkTextMuted),
                        ),
                        validator: (value) {
                          if (value == null || value.trim().isEmpty) {
                            return 'Please enter your username';
                          }
                          return null;
                        },
                      ),
                      const SizedBox(height: 16),

                      // Password
                      TextFormField(
                        controller: _passwordController,
                        obscureText: _obscurePassword,
                        style: GoogleFonts.plusJakartaSans(color: AppColors.darkTextPrimary, fontSize: 14),
                        decoration: InputDecoration(
                          labelText: 'Password',
                          hintText: '••••••••',
                          prefixIcon: const Icon(Icons.lock_outline_rounded, color: AppColors.darkTextMuted),
                          suffixIcon: IconButton(
                            icon: Icon(
                              _obscurePassword ? Icons.visibility_off_outlined : Icons.visibility_outlined,
                              color: AppColors.darkTextMuted,
                            ),
                            onPressed: () {
                              setState(() {
                                _obscurePassword = !_obscurePassword;
                              });
                            },
                          ),
                        ),
                        validator: (value) {
                          if (value == null || value.isEmpty) {
                            return 'Please enter your password';
                          }
                          return null;
                        },
                      ),
                      const SizedBox(height: 12),

                      Align(
                        alignment: Alignment.centerRight,
                        child: TextButton(
                          onPressed: () {
                            showDialog(
                              context: context,
                              builder: (_) => const ForgotPasswordDialog(),
                            );
                          },
                          child: Text(
                            'Forgot Password?',
                            style: GoogleFonts.plusJakartaSans(
                              color: AppColors.lime,
                              fontSize: 12.5,
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                        ),
                      ),
                      const SizedBox(height: 18),

                      // Submit Button
                      BrandedButton(
                        label: 'Sign In to Portal',
                        icon: Icons.login_rounded,
                        isLoading: auth.isLoading,
                        onPressed: _handleLogin,
                      ),
                      const SizedBox(height: 24),

                      // Powered by Nexora Lab
                      Center(
                        child: Row(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            Text(
                              'Powered by ',
                              style: GoogleFonts.plusJakartaSans(
                                color: AppColors.darkTextMuted,
                                fontSize: 11,
                              ),
                            ),
                            Text(
                              'NEXORA LAB TECHNOLOGIES',
                              style: GoogleFonts.outfit(
                                color: AppColors.lime,
                                fontSize: 11,
                                fontWeight: FontWeight.w800,
                                letterSpacing: 0.4,
                              ),
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}
