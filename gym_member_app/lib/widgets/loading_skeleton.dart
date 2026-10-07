import 'package:flutter/material.dart';
import '../core/theme/app_colors.dart';

class ShimmerSkeleton extends StatefulWidget {
  final double width;
  final double height;
  final double borderRadius;

  const ShimmerSkeleton({
    super.key,
    required this.width,
    required this.height,
    this.borderRadius = 14,
  });

  @override
  State<ShimmerSkeleton> createState() => _ShimmerSkeletonState();
}

class _ShimmerSkeletonState extends State<ShimmerSkeleton> with SingleTickerProviderStateMixin {
  late AnimationController _controller;
  late Animation<double> _animation;

  @override
  void initState() {
    super.initState();
    _controller = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 1400),
    )..repeat(reverse: true);
    _animation = Tween<double>(begin: 0.3, end: 0.8).animate(
      CurvedAnimation(parent: _controller, curve: Curves.easeInOut),
    );
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: _animation,
      builder: (context, child) {
        return Container(
          width: widget.width,
          height: widget.height,
          decoration: BoxDecoration(
            color: AppColors.darkCardElevated.withValues(alpha: _animation.value),
            borderRadius: BorderRadius.circular(widget.borderRadius),
            border: Border.all(
              color: AppColors.darkBorder,
              width: 1,
            ),
          ),
        );
      },
    );
  }
}

class DashboardSkeleton extends StatelessWidget {
  const DashboardSkeleton({super.key});

  @override
  Widget build(BuildContext context) {
    return SingleChildScrollView(
      physics: const NeverScrollableScrollPhysics(),
      padding: const EdgeInsets.all(20),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const ShimmerSkeleton(width: double.infinity, height: 180, borderRadius: 22),
          const SizedBox(height: 24),
          Row(
            children: const [
              Expanded(child: ShimmerSkeleton(width: double.infinity, height: 110)),
              SizedBox(width: 16),
              Expanded(child: ShimmerSkeleton(width: double.infinity, height: 110)),
            ],
          ),
          const SizedBox(height: 16),
          Row(
            children: const [
              Expanded(child: ShimmerSkeleton(width: double.infinity, height: 110)),
              SizedBox(width: 16),
              Expanded(child: ShimmerSkeleton(width: double.infinity, height: 110)),
            ],
          ),
          const SizedBox(height: 24),
          const ShimmerSkeleton(width: 160, height: 24),
          const SizedBox(height: 14),
          const ShimmerSkeleton(width: double.infinity, height: 90),
        ],
      ),
    );
  }
}
