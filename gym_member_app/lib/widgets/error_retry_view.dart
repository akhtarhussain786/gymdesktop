import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:provider/provider.dart';
import '../core/theme/app_colors.dart';
import '../providers/auth_provider.dart';
import '../screens/gym_lookup_screen.dart';
import '../screens/login_screen.dart';

class ErrorRetryView extends StatelessWidget {
  final String message;
  final VoidCallback onRetry;
  final String? title;

  const ErrorRetryView({
    super.key,
    required this.message,
    required this.onRetry,
    this.title,
  });

  @override
  Widget build(BuildContext context) {
    final isAuthError = message.toLowerCase().contains('authorization') ||
        message.toLowerCase().contains('log in') ||
        message.toLowerCase().contains('login') ||
        message.toLowerCase().contains('session expired') ||
        message.toLowerCase().contains('401');

    return Center(
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 32, vertical: 40),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Container(
              width: 72,
              height: 72,
              decoration: BoxDecoration(
                color: (isAuthError ? AppColors.lime : AppColors.danger).withValues(alpha: 0.12),
                borderRadius: BorderRadius.circular(20),
                border: Border.all(
                  color: (isAuthError ? AppColors.limeBorder : AppColors.danger).withValues(alpha: 0.3),
                  width: 1,
                ),
              ),
              child: Icon(
                isAuthError ? Icons.lock_reset_rounded : Icons.error_outline_rounded,
                color: isAuthError ? AppColors.lime : AppColors.danger,
                size: 36,
              ),
            ),
            const SizedBox(height: 20),
            Text(
              title ?? (isAuthError ? 'Session Expired' : 'Connection Issue'),
              style: GoogleFonts.outfit(
                color: AppColors.darkTextPrimary,
                fontSize: 19,
                fontWeight: FontWeight.w800,
              ),
              textAlign: TextAlign.center,
            ),
            const SizedBox(height: 8),
            Text(
              isAuthError
                  ? 'Your login session has expired or is invalid. Please log in again to continue.'
                  : message,
              style: GoogleFonts.plusJakartaSans(
                color: AppColors.darkTextSecondary,
                fontSize: 13.5,
                height: 1.5,
              ),
              textAlign: TextAlign.center,
            ),
            const SizedBox(height: 24),
            ElevatedButton.icon(
              style: ElevatedButton.styleFrom(
                backgroundColor: isAuthError ? AppColors.lime : null,
                foregroundColor: isAuthError ? const Color(0xFF090D14) : null,
                padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 12),
              ),
              onPressed: isAuthError
                  ? () async {
                      final auth = context.read<AuthProvider>();
                      await auth.logout(silent: true);
                      if (context.mounted) {
                        Navigator.of(context).pushAndRemoveUntil(
                          MaterialPageRoute(
                            builder: (_) => auth.currentTenant != null
                                ? const LoginScreen()
                                : const GymLookupScreen(),
                          ),
                          (route) => false,
                        );
                      }
                    }
                  : onRetry,
              icon: Icon(isAuthError ? Icons.login_rounded : Icons.refresh_rounded, size: 18),
              label: Text(
                isAuthError ? 'Log In Again' : 'Try Again',
                style: GoogleFonts.plusJakartaSans(
                  fontWeight: FontWeight.w800,
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
