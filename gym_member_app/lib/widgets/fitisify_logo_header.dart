import 'package:flutter/material.dart';
import '../core/theme/app_colors.dart';

class FitisifyLogoHeader extends StatelessWidget {
  final double iconSize;
  final double fontSize;
  final bool showOsBadge;

  const FitisifyLogoHeader({
    super.key,
    this.iconSize = 44,
    this.fontSize = 24,
    this.showOsBadge = true,
  });

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: CrossAxisAlignment.center,
      children: [
        // Squircle Icon Badge with Lime Thunderbolt
        Container(
          width: iconSize,
          height: iconSize,
          decoration: BoxDecoration(
            color: const Color(0xFF131A24),
            borderRadius: BorderRadius.circular(iconSize * 0.3),
            border: Border.all(
              color: AppColors.lime.withValues(alpha: 0.6),
              width: 1.5,
            ),
            boxShadow: [
              BoxShadow(
                color: AppColors.lime.withValues(alpha: 0.2),
                blurRadius: 12,
                offset: const Offset(0, 4),
              ),
            ],
          ),
          child: Center(
            child: Icon(
              Icons.bolt_rounded,
              size: iconSize * 0.6,
              color: AppColors.lime,
            ),
          ),
        ),
        const SizedBox(width: 12),

        // FITISIFY Text
        Text(
          'FITISIFY',
          style: TextStyle(
            fontSize: fontSize,
            fontWeight: FontWeight.w900,
            letterSpacing: 1.5,
            color: Theme.of(context).brightness == Brightness.dark ? Colors.white : const Color(0xFF0F172A),
            height: 1.0,
          ),
        ),

        // OS Pill Badge
        if (showOsBadge) ...[
          const SizedBox(width: 8),
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
            decoration: BoxDecoration(
              color: AppColors.lime,
              borderRadius: BorderRadius.circular(8),
              boxShadow: [
                BoxShadow(
                  color: AppColors.lime.withValues(alpha: 0.3),
                  blurRadius: 8,
                  offset: const Offset(0, 2),
                ),
              ],
            ),
            child: const Text(
              'OS',
              style: TextStyle(
                fontSize: 12,
                fontWeight: FontWeight.w900,
                color: Color(0xFF090D14),
                letterSpacing: 0.8,
              ),
            ),
          ),
        ],
      ],
    );
  }
}
