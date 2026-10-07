import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:provider/provider.dart';
import '../core/config/api_config.dart';
import '../core/network/api_service.dart';
import '../core/theme/app_colors.dart';
import '../providers/auth_provider.dart';
import '../widgets/branded_button.dart';

class ForgotPasswordDialog extends StatefulWidget {
  final String? gymCode;

  const ForgotPasswordDialog({super.key, this.gymCode});

  @override
  State<ForgotPasswordDialog> createState() => _ForgotPasswordDialogState();
}

class _ForgotPasswordDialogState extends State<ForgotPasswordDialog> {
  final _emailController = TextEditingController();
  bool _isLoading = false;
  String? _message;
  bool _isSuccess = false;

  @override
  void dispose() {
    _emailController.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    final email = _emailController.text.trim();
    if (email.isEmpty) return;

    setState(() {
      _isLoading = true;
      _message = null;
    });

    final effectiveGymCode = widget.gymCode ?? context.read<AuthProvider>().currentTenant?.gymCode ?? '';

    try {
      await ApiService.post(ApiConfig.forgotPassword, body: {
        'gym_code': effectiveGymCode,
        'email': email,
      });

      setState(() {
        _isLoading = false;
        _isSuccess = true;
        _message = 'Password reset request registered. Please check with your gym reception.';
      });
    } on ApiException catch (e) {
      setState(() {
        _isLoading = false;
        _isSuccess = false;
        _message = e.message;
      });
    } catch (_) {
      setState(() {
        _isLoading = false;
        _isSuccess = false;
        _message = 'Failed to submit reset request. Please contact gym reception.';
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final effectiveGymCode = widget.gymCode ?? context.read<AuthProvider>().currentTenant?.gymCode ?? '';

    return AlertDialog(
      backgroundColor: AppColors.darkCard,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(22),
        side: const BorderSide(color: AppColors.darkBorder),
      ),
      title: Text(
        'RESET PASSWORD',
        style: GoogleFonts.outfit(
          color: AppColors.darkTextPrimary,
          fontWeight: FontWeight.w800,
          fontSize: 18,
          letterSpacing: 0.4,
        ),
      ),
      content: SingleChildScrollView(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'Enter your registered email address or member username for Gym Code ($effectiveGymCode):',
              style: GoogleFonts.plusJakartaSans(
                color: AppColors.darkTextSecondary,
                fontSize: 13,
              ),
            ),
            const SizedBox(height: 16),
            if (!_isSuccess) ...[
              TextField(
                controller: _emailController,
                style: GoogleFonts.plusJakartaSans(color: AppColors.darkTextPrimary),
                decoration: const InputDecoration(
                  labelText: 'Email or Username',
                  prefixIcon: Icon(Icons.email_outlined, color: AppColors.darkTextMuted),
                ),
                keyboardType: TextInputType.emailAddress,
              ),
            ],
            if (_message != null) ...[
              const SizedBox(height: 14),
              Container(
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(
                  color: _isSuccess
                      ? AppColors.success.withValues(alpha: 0.15)
                      : AppColors.danger.withValues(alpha: 0.15),
                  borderRadius: BorderRadius.circular(10),
                  border: Border.all(
                    color: _isSuccess ? AppColors.success : AppColors.danger,
                  ),
                ),
                child: Text(
                  _message!,
                  style: GoogleFonts.plusJakartaSans(
                    color: _isSuccess ? AppColors.success : AppColors.danger,
                    fontWeight: FontWeight.w600,
                    fontSize: 12.5,
                  ),
                ),
              ),
            ],
          ],
        ),
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.pop(context),
          child: const Text('Close', style: TextStyle(color: AppColors.darkTextSecondary)),
        ),
        if (!_isSuccess)
          BrandedButton(
            label: 'Send Request',
            width: 140,
            height: 40,
            isLoading: _isLoading,
            onPressed: _submit,
          ),
      ],
    );
  }
}
