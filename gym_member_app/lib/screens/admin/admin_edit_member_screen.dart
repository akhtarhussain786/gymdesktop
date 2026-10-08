import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:provider/provider.dart';
import '../../core/theme/app_colors.dart';
import '../../models/admin_models.dart';
import '../../providers/admin_provider.dart';

class AdminEditMemberScreen extends StatefulWidget {
  final AdminMemberItem member;

  const AdminEditMemberScreen({super.key, required this.member});

  @override
  State<AdminEditMemberScreen> createState() => _AdminEditMemberScreenState();
}

class _AdminEditMemberScreenState extends State<AdminEditMemberScreen> {
  final _formKey = GlobalKey<FormState>();
  late TextEditingController _nameCtrl;
  late TextEditingController _phoneCtrl;
  late TextEditingController _emailCtrl;
  late TextEditingController _addressCtrl;
  late TextEditingController _weightCtrl;
  late String _services;
  late int _planMonths;
  late String _status;
  late String _gender;
  late DateTime _expiryDate;
  late DateTime _dor;

  @override
  void initState() {
    super.initState();
    _nameCtrl = TextEditingController(text: widget.member.fullname);
    _phoneCtrl = TextEditingController(text: widget.member.phone);
    _emailCtrl = TextEditingController(text: widget.member.email);
    _addressCtrl = TextEditingController(text: widget.member.address);
    _weightCtrl = TextEditingController(text: '70');
    _services = widget.member.services.isNotEmpty ? widget.member.services : 'General Fitness';
    _planMonths = widget.member.planMonths > 0 ? widget.member.planMonths : 1;
    _status = widget.member.membershipStatus;
    _gender = widget.member.gender;

    // Parse Expiry and DOR
    try {
      _expiryDate = DateTime.parse(widget.member.expiryDate);
    } catch (_) {
      _expiryDate = DateTime.now().add(Duration(days: 30 * _planMonths));
    }

    try {
      _dor = DateTime.parse(widget.member.startDate);
    } catch (_) {
      _dor = DateTime.now();
    }

    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<AdminProvider>().fetchRates();
    });
  }

  @override
  void dispose() {
    _nameCtrl.dispose();
    _phoneCtrl.dispose();
    _emailCtrl.dispose();
    _addressCtrl.dispose();
    _weightCtrl.dispose();
    super.dispose();
  }

  int get _daysRemaining {
    final now = DateTime.now();
    final today = DateTime(now.year, now.month, now.day);
    final exp = DateTime(_expiryDate.year, _expiryDate.month, _expiryDate.day);
    return exp.difference(today).inDays;
  }

  Future<void> _selectExpiryDate() async {
    final picked = await showDatePicker(
      context: context,
      initialDate: _expiryDate.isBefore(DateTime.now()) ? DateTime.now() : _expiryDate,
      firstDate: DateTime(2020),
      lastDate: DateTime.now().add(const Duration(days: 3650)),
      builder: (context, child) {
        return Theme(
          data: Theme.of(context).copyWith(
            colorScheme: const ColorScheme.dark(
              primary: AppColors.lime,
              onPrimary: Colors.black,
              surface: Color(0xFF1E1E2C),
            ),
          ),
          child: child!,
        );
      },
    );

    if (picked != null) {
      setState(() {
        _expiryDate = picked;
        if (_expiryDate.isAfter(DateTime.now())) {
          _status = 'Active';
        }
      });
    }
  }

  void _extendMonths(int months) {
    setState(() {
      final base = _expiryDate.isBefore(DateTime.now()) ? DateTime.now() : _expiryDate;
      _expiryDate = DateTime(base.year, base.month + months, base.day);
      _status = 'Active';
    });
  }

  void _saveChanges() async {
    if (!_formKey.currentState!.validate()) return;

    try {
      await context.read<AdminProvider>().updateMember(
        memberId: widget.member.memberId,
        fullname: _nameCtrl.text.trim(),
        phone: _phoneCtrl.text.trim(),
        email: _emailCtrl.text.trim(),
        address: _addressCtrl.text.trim(),
        gender: _gender,
        services: _services,
        planMonths: _planMonths,
        status: _status,
        expiryDate: DateFormat('yyyy-MM-dd').format(_expiryDate),
        dor: DateFormat('yyyy-MM-dd').format(_dor),
        currWeight: double.tryParse(_weightCtrl.text.trim()),
      );

      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Member profile & expiry date updated successfully!')),
        );
        Navigator.pop(context);
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Update failed: $e')),
        );
      }
    }
  }

  void _deleteMember() {
    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        backgroundColor: const Color(0xFF1E1E2C),
        title: const Text('Delete Member Record?', style: TextStyle(color: Colors.white)),
        content: Text(
          'Are you sure you want to delete ${widget.member.fullname}? All active passes and history will be permanently removed.',
          style: const TextStyle(color: Colors.white70),
        ),
        actions: [
          TextButton(
            child: const Text('Cancel', style: TextStyle(color: Colors.white60)),
            onPressed: () => Navigator.pop(ctx),
          ),
          ElevatedButton(
            style: ElevatedButton.styleFrom(backgroundColor: Colors.redAccent),
            child: const Text('Delete Member', style: TextStyle(color: Colors.white)),
            onPressed: () async {
              Navigator.pop(ctx);
              await context.read<AdminProvider>().deleteMember(widget.member.memberId);
              if (mounted) {
                Navigator.pop(context);
                ScaffoldMessenger.of(context).showSnackBar(
                  const SnackBar(content: Text('Member deleted successfully')),
                );
              }
            },
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final provider = context.watch<AdminProvider>();
    final isExpired = _daysRemaining < 0;
    final isExpiringSoon = _daysRemaining >= 0 && _daysRemaining <= 7;

    return Scaffold(
      backgroundColor: const Color(0xFF13131A),
      appBar: AppBar(
        backgroundColor: const Color(0xFF1E1E2C),
        elevation: 0,
        title: const Text('Edit Member Profile', style: TextStyle(fontWeight: FontWeight.bold, color: Colors.white)),
        actions: [
          IconButton(
            icon: const Icon(Icons.delete_outline, color: Colors.redAccent),
            onPressed: _deleteMember,
          ),
        ],
      ),
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.fromLTRB(16, 16, 16, 36),
          child: Form(
          key: _formKey,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // 1. Membership Expiry Card (HIGHLIGHTED)
              Container(
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  color: const Color(0xFF1E1E2C),
                  borderRadius: BorderRadius.circular(18),
                  border: Border.all(
                    color: isExpired
                        ? AppColors.danger.withOpacity(0.5)
                        : (isExpiringSoon ? AppColors.warning.withOpacity(0.5) : AppColors.lime.withOpacity(0.4)),
                  ),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        Row(
                          children: [
                            Icon(
                              Icons.alarm,
                              color: isExpired ? AppColors.danger : (isExpiringSoon ? AppColors.warning : AppColors.lime),
                              size: 20,
                            ),
                            const SizedBox(width: 8),
                            const Text(
                              'MEMBERSHIP EXPIRY DATE',
                              style: TextStyle(color: Colors.white70, fontSize: 11, fontWeight: FontWeight.bold, letterSpacing: 0.5),
                            ),
                          ],
                        ),
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                          decoration: BoxDecoration(
                            color: (isExpired ? AppColors.danger : (isExpiringSoon ? AppColors.warning : AppColors.success)).withOpacity(0.15),
                            borderRadius: BorderRadius.circular(6),
                          ),
                          child: Text(
                            isExpired
                                ? 'EXPIRED'
                                : (isExpiringSoon ? 'EXPIRES IN $_daysRemaining DAYS' : 'ACTIVE ($_daysRemaining DAYS LEFT)'),
                            style: TextStyle(
                              color: isExpired ? AppColors.danger : (isExpiringSoon ? AppColors.warning : AppColors.success),
                              fontSize: 10.5,
                              fontWeight: FontWeight.bold,
                            ),
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 12),

                    // Current Expiry Date Display + Tap to Pick
                    InkWell(
                      onTap: _selectExpiryDate,
                      borderRadius: BorderRadius.circular(12),
                      child: Container(
                        padding: const EdgeInsets.all(14),
                        decoration: BoxDecoration(
                          color: const Color(0xFF13131A),
                          borderRadius: BorderRadius.circular(12),
                          border: Border.all(color: Colors.white12),
                        ),
                        child: Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  const Text('Expiry Date', style: TextStyle(color: Colors.white60, fontSize: 11)),
                                  const SizedBox(height: 2),
                                  Text(
                                    DateFormat('dd MMM yyyy (EEE)').format(_expiryDate),
                                    style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 14),
                                    overflow: TextOverflow.ellipsis,
                                  ),
                                ],
                              ),
                            ),
                            const SizedBox(width: 8),
                            Container(
                              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                              decoration: BoxDecoration(
                                color: AppColors.lime.withOpacity(0.15),
                                borderRadius: BorderRadius.circular(8),
                              ),
                              child: const Row(
                                mainAxisSize: MainAxisSize.min,
                                children: [
                                  Icon(Icons.calendar_month_rounded, size: 14, color: AppColors.lime),
                                  SizedBox(width: 4),
                                  Text('Change Date', style: TextStyle(color: AppColors.lime, fontWeight: FontWeight.bold, fontSize: 11)),
                                ],
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
                    const SizedBox(height: 12),

                    // Quick Extend Chips
                    const Text('Quick Extend Plan:', style: TextStyle(color: Colors.white60, fontSize: 11)),
                    const SizedBox(height: 6),
                    Row(
                      children: [
                        _extendChip('+1 Month', () => _extendMonths(1)),
                        const SizedBox(width: 8),
                        _extendChip('+3 Months', () => _extendMonths(3)),
                        const SizedBox(width: 8),
                        _extendChip('+6 Months', () => _extendMonths(6)),
                        const SizedBox(width: 8),
                        _extendChip('+1 Year', () => _extendMonths(12)),
                      ],
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 20),

              // 2. Member Information Section
              const Text('Member Information', style: TextStyle(color: Colors.white, fontSize: 15, fontWeight: FontWeight.bold)),
              const SizedBox(height: 12),

              TextFormField(
                controller: _nameCtrl,
                style: const TextStyle(color: Colors.white),
                decoration: InputDecoration(
                  labelText: 'Full Name *',
                  labelStyle: const TextStyle(color: Colors.white70),
                  filled: true,
                  fillColor: const Color(0xFF1E1E2C),
                  border: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: BorderSide.none),
                  prefixIcon: const Icon(Icons.person_outline, color: AppColors.lime),
                ),
                validator: (val) => (val == null || val.trim().isEmpty) ? 'Required' : null,
              ),
              const SizedBox(height: 12),

              TextFormField(
                controller: _phoneCtrl,
                keyboardType: TextInputType.phone,
                style: const TextStyle(color: Colors.white),
                decoration: InputDecoration(
                  labelText: 'Phone Number *',
                  labelStyle: const TextStyle(color: Colors.white70),
                  filled: true,
                  fillColor: const Color(0xFF1E1E2C),
                  border: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: BorderSide.none),
                  prefixIcon: const Icon(Icons.phone_outlined, color: Color(0xFF00CEC9)),
                ),
                validator: (val) => (val == null || val.trim().isEmpty) ? 'Required' : null,
              ),
              const SizedBox(height: 12),

              Row(
                children: [
                  Expanded(
                    child: DropdownButtonFormField<String>(
                      initialValue: ['Male', 'Female', 'Other'].contains(_gender) ? _gender : 'Male',
                      dropdownColor: const Color(0xFF2A2A3E),
                      style: const TextStyle(color: Colors.white),
                      decoration: InputDecoration(
                        labelText: 'Gender',
                        labelStyle: const TextStyle(color: Colors.white70),
                        filled: true,
                        fillColor: const Color(0xFF1E1E2C),
                        border: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: BorderSide.none),
                      ),
                      items: const [
                        DropdownMenuItem(value: 'Male', child: Text('Male')),
                        DropdownMenuItem(value: 'Female', child: Text('Female')),
                        DropdownMenuItem(value: 'Other', child: Text('Other')),
                      ],
                      onChanged: (val) {
                        if (val != null) setState(() => _gender = val);
                      },
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: DropdownButtonFormField<String>(
                      initialValue: ['Active', 'Expired', 'Pending'].contains(_status) ? _status : 'Active',
                      dropdownColor: const Color(0xFF2A2A3E),
                      style: const TextStyle(color: Colors.white),
                      decoration: InputDecoration(
                        labelText: 'Status',
                        labelStyle: const TextStyle(color: Colors.white70),
                        filled: true,
                        fillColor: const Color(0xFF1E1E2C),
                        border: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: BorderSide.none),
                      ),
                      items: const [
                        DropdownMenuItem(value: 'Active', child: Text('Active')),
                        DropdownMenuItem(value: 'Expired', child: Text('Expired')),
                        DropdownMenuItem(value: 'Pending', child: Text('Pending')),
                      ],
                      onChanged: (val) {
                        if (val != null) setState(() => _status = val);
                      },
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 12),

              TextFormField(
                controller: _emailCtrl,
                style: const TextStyle(color: Colors.white),
                decoration: InputDecoration(
                  labelText: 'Email Address (Optional)',
                  labelStyle: const TextStyle(color: Colors.white70),
                  filled: true,
                  fillColor: const Color(0xFF1E1E2C),
                  border: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: BorderSide.none),
                  prefixIcon: const Icon(Icons.email_outlined, color: Colors.white54),
                ),
              ),
              const SizedBox(height: 12),

              TextFormField(
                controller: _addressCtrl,
                maxLines: 2,
                style: const TextStyle(color: Colors.white),
                decoration: InputDecoration(
                  labelText: 'Address',
                  labelStyle: const TextStyle(color: Colors.white70),
                  filled: true,
                  fillColor: const Color(0xFF1E1E2C),
                  border: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: BorderSide.none),
                  prefixIcon: const Icon(Icons.location_on_outlined, color: Colors.white54),
                ),
              ),
              const SizedBox(height: 24),

              // Save Changes Button
              SizedBox(
                width: double.infinity,
                height: 52,
                child: ElevatedButton(
                  style: ElevatedButton.styleFrom(
                    backgroundColor: AppColors.lime,
                    foregroundColor: Colors.black,
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                  ),
                  onPressed: provider.isActionLoading ? null : _saveChanges,
                  child: provider.isActionLoading
                      ? const CircularProgressIndicator(color: Colors.black)
                      : const Text('Save & Update Member', style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold)),
                ),
              ),
              const SizedBox(height: 30),
            ],
          ),
        ),
      ),
    ),
  );
}

  Widget _extendChip(String label, VoidCallback onTap) {
    return Expanded(
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(8),
        child: Container(
          padding: const EdgeInsets.symmetric(vertical: 8),
          decoration: BoxDecoration(
            color: const Color(0xFF13131A),
            borderRadius: BorderRadius.circular(8),
            border: Border.all(color: Colors.white.withOpacity(0.08)),
          ),
          child: Center(
            child: Text(
              label,
              style: const TextStyle(color: Color(0xFF00CEC9), fontWeight: FontWeight.bold, fontSize: 11),
            ),
          ),
        ),
      ),
    );
  }
}
