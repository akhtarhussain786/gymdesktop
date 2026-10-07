import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import '../core/theme/app_colors.dart';

class StatusBadge extends StatelessWidget {
  final String status;
  final double? fontSize;
  final EdgeInsetsGeometry? padding;

  const StatusBadge({
    super.key,
    required this.status,
    this.fontSize,
    this.padding,
  });

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final s = status.toLowerCase();
    Color bg;
    Color fg;
    Color border;

    if (s.contains('active') || s.contains('paid') || s.contains('present') || s.contains('approved')) {
      bg = isDark ? AppColors.success.withValues(alpha: 0.12) : const Color(0xFFDCFCE7);
      fg = isDark ? AppColors.success : const Color(0xFF15803D);
      border = isDark ? AppColors.success.withValues(alpha: 0.3) : const Color(0xFF86EFAC);
    } else if (s.contains('trial') || s.contains('vip') || s.contains('platinum')) {
      bg = isDark ? AppColors.cyan.withValues(alpha: 0.12) : const Color(0xFFE0F2FE);
      fg = isDark ? AppColors.cyan : const Color(0xFF0369A1);
      border = isDark ? AppColors.cyan.withValues(alpha: 0.3) : const Color(0xFF7DD3FC);
    } else if (s.contains('pending') || s.contains('due') || s.contains('warning') || s.contains('expir')) {
      bg = isDark ? AppColors.warning.withValues(alpha: 0.12) : const Color(0xFFFEF3C7);
      fg = isDark ? AppColors.warning : const Color(0xFFB45309);
      border = isDark ? AppColors.warning.withValues(alpha: 0.3) : const Color(0xFFFCD34D);
    } else if (s.contains('expired') || s.contains('inactive') || s.contains('unpaid') || s.contains('failed') || s.contains('cancel')) {
      bg = isDark ? AppColors.danger.withValues(alpha: 0.12) : const Color(0xFFFEE2E2);
      fg = isDark ? AppColors.danger : const Color(0xFFB91C1C);
      border = isDark ? AppColors.danger.withValues(alpha: 0.3) : const Color(0xFFFCA5A5);
    } else {
      bg = isDark ? AppColors.lime.withValues(alpha: 0.12) : const Color(0xFFF1F5F9);
      fg = isDark ? AppColors.lime : const Color(0xFF334155);
      border = isDark ? AppColors.lime.withValues(alpha: 0.3) : const Color(0xFFCBD5E1);
    }

    return Container(
      padding: padding ?? const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(
        color: bg,
        borderRadius: BorderRadius.circular(999),
        border: Border.all(color: border, width: 1),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Container(
            width: 5,
            height: 5,
            decoration: BoxDecoration(
              color: fg,
              shape: BoxShape.circle,
            ),
          ),
          const SizedBox(width: 6),
          Text(
            status.toUpperCase(),
            style: GoogleFonts.plusJakartaSans(
              color: fg,
              fontSize: fontSize ?? 10.5,
              fontWeight: FontWeight.w800,
              letterSpacing: 0.4,
            ),
          ),
        ],
      ),
    );
  }
}
