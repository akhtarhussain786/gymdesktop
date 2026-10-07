import 'package:flutter/material.dart';

class LoadingSkeleton extends StatelessWidget {
  final double height;
  final double? width;
  final double borderRadius;

  const LoadingSkeleton({
    super.key,
    required this.height,
    this.width,
    this.borderRadius = 12,
  });

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final baseColor = isDark ? const Color(0xFF1E293B) : const Color(0xFFE2E8F0);

    return Container(
      height: height,
      width: width ?? double.infinity,
      decoration: BoxDecoration(
        color: baseColor,
        borderRadius: BorderRadius.circular(borderRadius),
      ),
    );
  }
}

class DashboardSkeleton extends StatelessWidget {
  const DashboardSkeleton({super.key});

  @override
  Widget build(BuildContext context) {
    return SingleChildScrollView(
      padding: const EdgeInsets.all(20),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const LoadingSkeleton(height: 140, borderRadius: 20),
          const SizedBox(height: 20),
          Row(
            children: const [
              Expanded(child: LoadingSkeleton(height: 110, borderRadius: 16)),
              SizedBox(width: 14),
              Expanded(child: LoadingSkeleton(height: 110, borderRadius: 16)),
            ],
          ),
          const SizedBox(height: 20),
          const LoadingSkeleton(height: 180, borderRadius: 16),
          const SizedBox(height: 20),
          const LoadingSkeleton(height: 160, borderRadius: 16),
        ],
      ),
    );
  }
}
