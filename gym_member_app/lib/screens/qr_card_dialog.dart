import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:qr_flutter/qr_flutter.dart';
import '../core/theme/app_colors.dart';
import '../models/dashboard_data.dart';
import '../models/gym_tenant.dart';
import '../models/member_user.dart';

class QrCardDialog extends StatelessWidget {
  final DashboardQrPass qrPass;
  final GymTenant gym;
  final MemberUser member;

  const QrCardDialog({
    super.key,
    required this.qrPass,
    required this.gym,
    required this.member,
  });

  @override
  Widget build(BuildContext context) {
    return Dialog(
      backgroundColor: AppColors.darkCard,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(24),
        side: const BorderSide(color: AppColors.limeBorder, width: 1.5),
      ),
      child: ConstrainedBox(
        constraints: const BoxConstraints(maxWidth: 360),
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              // Header
              Text(
                gym.gymName.toUpperCase(),
                style: GoogleFonts.outfit(
                  color: AppColors.darkTextPrimary,
                  fontWeight: FontWeight.w900,
                  fontSize: 18,
                  letterSpacing: 0.5,
                ),
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: 4),
              Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Container(
                    width: 6,
                    height: 6,
                    decoration: const BoxDecoration(
                      color: AppColors.lime,
                      shape: BoxShape.circle,
                    ),
                  ),
                  const SizedBox(width: 6),
                  Text(
                    'DIGITAL PASS • READY TO SCAN',
                    style: GoogleFonts.plusJakartaSans(
                      color: AppColors.lime,
                      fontSize: 11,
                      fontWeight: FontWeight.w800,
                      letterSpacing: 0.5,
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 20),

              // High-Contrast White QR Card Frame
              Container(
                padding: const EdgeInsets.all(14),
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(18),
                  boxShadow: [
                    BoxShadow(
                      color: AppColors.lime.withValues(alpha: 0.15),
                      blurRadius: 20,
                      offset: const Offset(0, 4),
                    ),
                  ],
                ),
                child: QrImageView(
                  data: qrPass.payload,
                  version: QrVersions.auto,
                  size: 180.0,
                  eyeStyle: const QrEyeStyle(
                    eyeShape: QrEyeShape.square,
                    color: Color(0xFF05080D),
                  ),
                  dataModuleStyle: const QrDataModuleStyle(
                    dataModuleShape: QrDataModuleShape.square,
                    color: Color(0xFF05080D),
                  ),
                ),
              ),
              const SizedBox(height: 20),

              // Member Details
              Text(
                member.fullname,
                style: GoogleFonts.outfit(
                  color: AppColors.darkTextPrimary,
                  fontWeight: FontWeight.w800,
                  fontSize: 17,
                ),
              ),
              const SizedBox(height: 4),
              Text(
                'CODE: ${qrPass.code}',
                style: GoogleFonts.plusJakartaSans(
                  color: AppColors.lime,
                  fontWeight: FontWeight.w800,
                  fontSize: 13,
                  letterSpacing: 0.6,
                ),
              ),
              const SizedBox(height: 4),
              Text(
                'Valid Until: ${qrPass.validUntil}',
                style: GoogleFonts.plusJakartaSans(
                  color: AppColors.darkTextSecondary,
                  fontSize: 12,
                ),
              ),
              const SizedBox(height: 22),

              // Close Button
              SizedBox(
                width: double.infinity,
                height: 44,
                child: ElevatedButton(
                  style: ElevatedButton.styleFrom(
                    backgroundColor: AppColors.darkCardElevated,
                    foregroundColor: AppColors.darkTextPrimary,
                    side: const BorderSide(color: AppColors.darkBorder),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(999)),
                  ),
                  onPressed: () => Navigator.pop(context),
                  child: Text(
                    'Close Pass',
                    style: GoogleFonts.plusJakartaSans(fontWeight: FontWeight.w700),
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
