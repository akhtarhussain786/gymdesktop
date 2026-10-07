import 'package:flutter/material.dart';
import '../core/theme/app_colors.dart';

class StatusBadge extends StatelessWidget {
  final String status;
  final Color? customColor;
  final bool small;

  const StatusBadge({
    super.key,
    required this.status,
    this.customColor,
    this.small = false,
  });

  @override
  Widget build(BuildContext context) {
    final lower = status.toLowerCase();
    Color color;

    if (customColor != null) {
      color = customColor!;
    } else if (lower.contains('active') || lower.contains('paid') || lower.contains('present') || lower.contains('completed') || lower.contains('resolved')) {
      color = AppColors.success;
    } else if (lower.contains('expir') || lower.contains('due') || lower.contains('progress') || lower.contains('partial') || lower.contains('pending')) {
      color = AppColors.warning;
    } else if (lower.contains('suspend') || lower.contains('unpaid') || lower.contains('cancel') || lower.contains('inactive')) {
      color = AppColors.danger;
    } else {
      color = AppColors.info;
    }

    return Container(
      padding: EdgeInsets.symmetric(
        horizontal: small ? 8 : 12,
        vertical: small ? 3 : 5,
      ),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.15),
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: color.withValues(alpha: 0.4), width: 1),
      ),
      child: Text(
        status.toUpperCase(),
        style: TextStyle(
          color: color,
          fontSize: small ? 10.5 : 12,
          fontWeight: FontWeight.w700,
          letterSpacing: 0.5,
        ),
      ),
    );
  }
}
