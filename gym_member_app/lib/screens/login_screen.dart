import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../core/theme/app_colors.dart';
import '../providers/auth_provider.dart';
import '../providers/dashboard_provider.dart';
import '../widgets/branded_button.dart';
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
  bool _rememberGym = true;

  @override
  void initState() {
    super.initState();
    // Prefill helper for testing
    final auth = context.read<AuthProvider>();
    if (auth.currentTenant?.gymCode == 'GYM-A') {
      _loginIdController.text = 'david_gyma';
      _passwordController.text = 'password';
    } else if (auth.currentTenant?.gymCode == 'GYM-B') {
      _loginIdController.text = 'david_gymb';
      _passwordController.text = 'password';
    }
  }

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
    final dashboardProvider = context.read<DashboardProvider>();

    final success = await auth.login(
      loginId: _loginIdController.text.trim(),
      password: _passwordController.text,
    );

    if (success && mounted) {
      // Preload dashboard
      dashboardProvider.fetchDashboard(refresh: true);
      Navigator.of(context).pushReplacement(
        MaterialPageRoute(builder: (_) => const MainNavigationScreen()),
      );
    }
  }

  void _handleChangeGym() {
    context.read<AuthProvider>().changeGym();
    Navigator.of(context).pushReplacement(
      MaterialPageRoute(builder: (_) => const GymLookupScreen()),
    );
  }

  void _showContactGymDialog() {
    final tenant = context.read<AuthProvider>().currentTenant;
    if (tenant == null) return;

    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        title: Text(tenant.gymName),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text('Contact information for member support:', style: Theme.of(ctx).textTheme.bodyMedium),
            const SizedBox(height: 14),
            if (tenant.phone.isNotEmpty) ...[
              Row(
                children: [
                  const Icon(Icons.phone, size: 18, color: AppColors.success),
                  const SizedBox(width: 8),
                  SelectableText(tenant.phone),
                ],
              ),
              const SizedBox(height: 8),
            ],
            if (tenant.email.isNotEmpty) ...[
              Row(
                children: [
                  const Icon(Icons.email, size: 18, color: AppColors.info),
                  const SizedBox(width: 8),
                  SelectableText(tenant.email),
                ],
              ),
              const SizedBox(height: 8),
            ],
            if (tenant.address.isNotEmpty) ...[
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Icon(Icons.location_on, size: 18, color: Colors.grey),
                  const SizedBox(width: 8),
                  Expanded(child: SelectableText(tenant.address)),
                ],
              ),
            ],
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx),
            child: const Text('Close'),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final auth = context.watch<AuthProvider>();
    final tenant = auth.currentTenant;

    if (tenant == null) {
      return const GymLookupScreen();
    }

    return Scaffold(
      appBar: AppBar(
        automaticallyImplyLeading: false,
        actions: [
          TextButton.icon(
            onPressed: _handleChangeGym,
            icon: const Icon(Icons.swap_horiz_rounded, size: 18),
            label: const Text('Change Gym'),
          ),
          const SizedBox(width: 8),
        ],
      ),
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 16),
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 440),
              child: Form(
                key: _formKey,
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    // Dynamic Branded Gym Header
                    Center(
                      child: Container(
                        width: 72,
                        height: 72,
                        decoration: BoxDecoration(
                          color: theme.primaryColor.withValues(alpha: 0.15),
                          borderRadius: BorderRadius.circular(18),
                        ),
                        child: Center(
                          child: Icon(
                            Icons.fitness_center_rounded,
                            size: 38,
                            color: theme.primaryColor,
                          ),
                        ),
                      ),
                    ),
                    const SizedBox(height: 16),

                    Text(
                      tenant.gymName,
                      style: theme.textTheme.titleLarge?.copyWith(
                        fontSize: 22,
                        fontWeight: FontWeight.w800,
                      ),
                      textAlign: TextAlign.center,
                    ),
                    const SizedBox(height: 4),

                    // Gym Code Badge
                    Center(
                      child: Container(
                        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                        decoration: BoxDecoration(
                          color: theme.primaryColor.withValues(alpha: 0.1),
                          borderRadius: BorderRadius.circular(8),
                          border: Border.all(color: theme.primaryColor.withValues(alpha: 0.3)),
                        ),
                        child: Text(
                          'GYM CODE: ${tenant.gymCode}',
                          style: TextStyle(
                            color: theme.primaryColor,
                            fontWeight: FontWeight.w700,
                            fontSize: 11.5,
                            letterSpacing: 0.8,
                          ),
                        ),
                      ),
                    ),
                    const SizedBox(height: 28),

                    // Login Id Input
                    TextFormField(
                      controller: _loginIdController,
                      decoration: const InputDecoration(
                        labelText: 'Username, Member ID, or Email',
                        hintText: 'e.g. david_gyma or member@email.com',
                        prefixIcon: Icon(Icons.person_outline_rounded),
                      ),
                      validator: (value) {
                        if (value == null || value.trim().isEmpty) {
                          return 'Please enter your login username or ID';
                        }
                        return null;
                      },
                    ),
                    const SizedBox(height: 16),

                    // Password Input
                    TextFormField(
                      controller: _passwordController,
                      obscureText: _obscurePassword,
                      decoration: InputDecoration(
                        labelText: 'Password',
                        hintText: '••••••••',
                        prefixIcon: const Icon(Icons.lock_outline_rounded),
                        suffixIcon: IconButton(
                          icon: Icon(
                            _obscurePassword ? Icons.visibility_off_outlined : Icons.visibility_outlined,
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
                      onFieldSubmitted: (_) => _handleLogin(),
                    ),
                    const SizedBox(height: 12),

                    // Remember & Forgot Password row
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        Row(
                          children: [
                            SizedBox(
                              width: 24,
                              height: 24,
                              child: Checkbox(
                                value: _rememberGym,
                                onChanged: (v) {
                                  setState(() {
                                    _rememberGym = v ?? true;
                                  });
                                },
                              ),
                            ),
                            const SizedBox(width: 8),
                            Text(
                              'Remember Gym',
                              style: theme.textTheme.bodyMedium?.copyWith(fontSize: 13),
                            ),
                          ],
                        ),
                        TextButton(
                          onPressed: () {
                            showDialog(
                              context: context,
                              builder: (_) => ForgotPasswordDialog(gymCode: tenant.gymCode),
                            );
                          },
                          child: Text(
                            'Forgot Password?',
                            style: TextStyle(
                              color: theme.primaryColor,
                              fontWeight: FontWeight.w600,
                              fontSize: 13,
                            ),
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 20),

                    // Login Button
                    BrandedButton(
                      text: 'Sign In to Member Portal',
                      icon: Icons.login_rounded,
                      isLoading: auth.isLoading,
                      onPressed: _handleLogin,
                    ),

                    // Error Box
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

                    const SizedBox(height: 28),

                    // Contact Gym Footer
                    Center(
                      child: TextButton.icon(
                        onPressed: _showContactGymDialog,
                        icon: const Icon(Icons.headset_mic_outlined, size: 16),
                        label: const Text('Need help? Contact Gym Support'),
                        style: TextButton.styleFrom(
                          foregroundColor: theme.textTheme.bodyMedium?.color,
                        ),
                      ),
                    ),
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
