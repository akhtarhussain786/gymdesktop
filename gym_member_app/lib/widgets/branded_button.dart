import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import '../core/theme/app_colors.dart';

class BrandedButton extends StatelessWidget {
  final String label;
  final VoidCallback? onPressed;
  final IconData? icon;
  final bool isLoading;
  final bool isOutlined;
  final Color? customColor;
  final double? width;
  final double height;

  const BrandedButton({
    super.key,
    String? label,
    String? text,
    this.onPressed,
    this.icon,
    this.isLoading = false,
    this.isOutlined = false,
    this.customColor,
    this.width,
    this.height = 50,
  }) : label = label ?? text ?? '';

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final primary = customColor ?? theme.primaryColor;

    if (isOutlined) {
      return SizedBox(
        width: width,
        height: height,
        child: OutlinedButton(
          onPressed: isLoading ? null : onPressed,
          style: OutlinedButton.styleFrom(
            side: const BorderSide(color: AppColors.darkBorder, width: 1.2),
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(999),
            ),
            backgroundColor: AppColors.darkCard.withValues(alpha: 0.5),
            foregroundColor: AppColors.darkTextPrimary,
          ),
          child: _buildChild(AppColors.darkTextPrimary),
        ),
      );
    }

    return SizedBox(
      width: width,
      height: height,
      child: Container(
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(999),
          boxShadow: [
            BoxShadow(
              color: primary.withValues(alpha: 0.35),
              blurRadius: 16,
              offset: const Offset(0, 4),
            ),
          ],
        ),
        child: ElevatedButton(
          onPressed: isLoading ? null : onPressed,
          style: ElevatedButton.styleFrom(
            backgroundColor: primary,
            foregroundColor: const Color(0xFF05080D),
            elevation: 0,
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(999),
            ),
          ),
          child: _buildChild(const Color(0xFF05080D)),
        ),
      ),
    );
  }

  Widget _buildChild(Color textColor) {
    if (isLoading) {
      return SizedBox(
        width: 20,
        height: 20,
        child: CircularProgressIndicator(
          strokeWidth: 2.2,
          valueColor: AlwaysStoppedAnimation<Color>(textColor),
        ),
      );
    }

    if (icon != null) {
      return Row(
        mainAxisSize: MainAxisSize.min,
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Icon(icon, size: 18, color: textColor),
          const SizedBox(width: 8),
          Text(
            label,
            style: GoogleFonts.plusJakartaSans(
              color: textColor,
              fontWeight: FontWeight.w800,
              fontSize: 14.5,
              letterSpacing: 0.2,
            ),
          ),
        ],
      );
    }

    return Text(
      label,
      style: GoogleFonts.plusJakartaSans(
        color: textColor,
        fontWeight: FontWeight.w800,
        fontSize: 14.5,
        letterSpacing: 0.2,
      ),
    );
  }
}
