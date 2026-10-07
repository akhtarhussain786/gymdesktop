import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:image_picker/image_picker.dart';
import 'package:intl/intl.dart';
import 'package:provider/provider.dart';
import 'package:url_launcher/url_launcher.dart';
import '../../core/theme/app_colors.dart';
import '../../providers/admin_provider.dart';
import '../../providers/auth_provider.dart';

class AdminAddMemberScreen extends StatefulWidget {
  const AdminAddMemberScreen({super.key});

  @override
  State<AdminAddMemberScreen> createState() => _AdminAddMemberScreenState();
}

class _AdminAddMemberScreenState extends State<AdminAddMemberScreen> {
  final _formKey = GlobalKey<FormState>();

  // Text Controllers
  final _fullnameController = TextEditingController();
  final _phoneController = TextEditingController();
  final _emailController = TextEditingController();
  final _addressController = TextEditingController();
  final _passwordController = TextEditingController(text: '123456');
  final _totalAmountController = TextEditingController(text: '1000');
  final _paidAmountController = TextEditingController(text: '1000');

  String _gender = 'Male';
  String _selectedService = 'General Fitness';
  int _planMonths = 1;
  final DateTime _dor = DateTime.now();
  DateTime? _dueDate;
  String _paymentMethod = 'Cash';

  // Photo
  XFile? _selectedPhoto;
  Uint8List? _photoBytes;
  final ImagePicker _picker = ImagePicker();

  bool _isSubmitting = false;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) async {
      await context.read<AdminProvider>().fetchRates();
      if (mounted) {
        final rates = context.read<AdminProvider>().rates;
        if (rates.isNotEmpty) {
          final matched = rates.where((r) => r.name.trim() == _selectedService).firstOrNull ?? rates.first;
          setState(() {
            _selectedService = matched.name.trim();
            final total = matched.charge * _planMonths;
            _totalAmountController.text = total.toStringAsFixed(0);
            _paidAmountController.text = total.toStringAsFixed(0);
          });
        }
      }
    });
  }

  @override
  void dispose() {
    _fullnameController.dispose();
    _phoneController.dispose();
    _emailController.dispose();
    _addressController.dispose();
    _passwordController.dispose();
    _totalAmountController.dispose();
    _paidAmountController.dispose();
    super.dispose();
  }

  double get _totalAmount => double.tryParse(_totalAmountController.text.trim()) ?? 0.0;
  double get _paidAmount => double.tryParse(_paidAmountController.text.trim()) ?? 0.0;
  double get _dueAmount => (_totalAmount - _paidAmount) > 0 ? (_totalAmount - _paidAmount) : 0.0;

  Future<void> _pickImage(ImageSource source) async {
    try {
      final photo = await _picker.pickImage(
        source: source,
        maxWidth: 800,
        maxHeight: 800,
        imageQuality: 85,
      );
      if (photo != null) {
        final bytes = await photo.readAsBytes();
        setState(() {
          _selectedPhoto = photo;
          _photoBytes = bytes;
        });
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Could not pick image: $e'), backgroundColor: AppColors.danger),
        );
      }
    }
  }

  void _showImageSourcePicker() {
    showModalBottomSheet(
      context: context,
      backgroundColor: AppColors.card(context),
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder: (ctx) {
        return SafeArea(
          child: Padding(
            padding: const EdgeInsets.symmetric(vertical: 20, horizontal: 16),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  'Member Photo',
                  style: GoogleFonts.outfit(
                    fontSize: 18,
                    fontWeight: FontWeight.w800,
                    color: AppColors.textPrimary(ctx),
                  ),
                ),
                const SizedBox(height: 16),
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceEvenly,
                  children: [
                    _buildSourceOption(
                      icon: Icons.camera_alt_rounded,
                      label: 'Take Camera Photo',
                      onTap: () {
                        Navigator.pop(ctx);
                        _pickImage(ImageSource.camera);
                      },
                    ),
                    _buildSourceOption(
                      icon: Icons.photo_library_rounded,
                      label: 'Gallery / Files',
                      onTap: () {
                        Navigator.pop(ctx);
                        _pickImage(ImageSource.gallery);
                      },
                    ),
                    if (_selectedPhoto != null)
                      _buildSourceOption(
                        icon: Icons.delete_outline_rounded,
                        label: 'Remove Photo',
                        color: AppColors.danger,
                        onTap: () {
                          Navigator.pop(ctx);
                          setState(() {
                            _selectedPhoto = null;
                            _photoBytes = null;
                          });
                        },
                      ),
                  ],
                ),
              ],
            ),
          ),
        );
      },
    );
  }

  Widget _buildSourceOption({
    required IconData icon,
    required String label,
    required VoidCallback onTap,
    Color? color,
  }) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(16),
      child: Container(
        width: 100,
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(
          color: AppColors.cardElevated(context),
          borderRadius: BorderRadius.circular(16),
          border: Border.all(color: AppColors.border(context)),
        ),
        child: Column(
          children: [
            Icon(icon, size: 28, color: color ?? AppColors.lime),
            const SizedBox(height: 8),
            Text(
              label,
              style: GoogleFonts.plusJakartaSans(
                fontSize: 11,
                fontWeight: FontWeight.w700,
                color: color ?? AppColors.textPrimary(context),
              ),
              textAlign: TextAlign.center,
            ),
          ],
        ),
      ),
    );
  }

  Future<void> _selectDueDate() async {
    final picked = await showDatePicker(
      context: context,
      initialDate: _dueDate ?? DateTime.now().add(const Duration(days: 7)),
      firstDate: DateTime.now(),
      lastDate: DateTime.now().add(const Duration(days: 365)),
      builder: (context, child) {
        return Theme(
          data: Theme.of(context).copyWith(
            colorScheme: ColorScheme.dark(
              primary: AppColors.lime,
              onPrimary: Colors.black,
              surface: AppColors.card(context),
              onSurface: AppColors.textPrimary(context),
            ),
          ),
          child: child!,
        );
      },
    );

    if (picked != null) {
      setState(() => _dueDate = picked);
    }
  }

  Future<void> _handleSubmit() async {
    if (!_formKey.currentState!.validate()) return;
    FocusScope.of(context).unfocus();

    setState(() => _isSubmitting = true);
    final admin = context.read<AdminProvider>();

    try {
      final res = await admin.addMember(
        fullname: _fullnameController.text.trim(),
        phone: _phoneController.text.trim(),
        email: _emailController.text.trim().isNotEmpty ? _emailController.text.trim() : null,
        address: _addressController.text.trim().isNotEmpty ? _addressController.text.trim() : null,
        gender: _gender,
        services: _selectedService,
        planMonths: _planMonths,
        totalAmount: _totalAmount,
        paidAmount: _paidAmount,
        dueAmount: _dueAmount,
        dueDate: _dueAmount > 0 && _dueDate != null
            ? DateFormat('yyyy-MM-dd').format(_dueDate!)
            : null,
        paymentMethod: _paymentMethod,
        password: _passwordController.text.isNotEmpty ? _passwordController.text : '123456',
        dor: DateFormat('yyyy-MM-dd').format(_dor),
        photoPath: !kIsWeb ? _selectedPhoto?.path : null,
        photoBytes: _photoBytes,
      );

      if (mounted && res != null) {
        _showSuccessDialog(res);
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(e.toString()),
            backgroundColor: AppColors.danger,
          ),
        );
      }
    } finally {
      if (mounted) setState(() => _isSubmitting = false);
    }
  }

  void _showSuccessDialog(Map<String, dynamic> data) {
    final gymName = context.read<AuthProvider>().currentTenant?.gymName ?? 'Our Gym';
    final currency = context.read<AuthProvider>().currentTenant?.currency ?? '₹';
    final name = data['fullname'] ?? _fullnameController.text;
    final username = data['username'] ?? '';
    final phone = data['phone'] ?? _phoneController.text;
    final due = (data['due_amount'] is num) ? (data['due_amount'] as num).toDouble() : _dueAmount;

    final dueText = due > 0 ? "\nPending Due: *$currency$due*" : "";
    final welcomeMessage = "Welcome to *$gymName*, *$name*! 🎉\n"
        "Your membership for *$_selectedService* is activated.\n"
        "Username: *$username*\n"
        "App Login: Use your username & password to track workouts & attendance.$dueText";

    showDialog(
      context: context,
      barrierDismissible: false,
      builder: (ctx) {
        return AlertDialog(
          backgroundColor: AppColors.card(ctx),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(24),
            side: BorderSide(color: AppColors.limeBorder),
          ),
          contentPadding: const EdgeInsets.all(24),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(
                width: 60,
                height: 60,
                decoration: BoxDecoration(
                  color: AppColors.lime.withValues(alpha: 0.15),
                  shape: BoxShape.circle,
                  border: Border.all(color: AppColors.lime, width: 2),
                ),
                child: const Icon(Icons.person_add_alt_1_rounded, color: AppColors.lime, size: 32),
              ),
              const SizedBox(height: 16),
              Text(
                'Member Registered!',
                style: GoogleFonts.outfit(
                  fontSize: 20,
                  fontWeight: FontWeight.w900,
                  color: AppColors.textPrimary(ctx),
                ),
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: 6),
              Text(
                'New member account created and membership activated.',
                style: GoogleFonts.plusJakartaSans(
                  fontSize: 12.5,
                  color: AppColors.textMuted(ctx),
                ),
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: 16),

              Container(
                padding: const EdgeInsets.all(14),
                decoration: BoxDecoration(
                  color: AppColors.cardElevated(ctx),
                  borderRadius: BorderRadius.circular(14),
                  border: Border.all(color: AppColors.border(ctx)),
                ),
                child: Column(
                  children: [
                    _infoRow(ctx, 'Full Name', name),
                    _infoRow(ctx, 'Username', username),
                    _infoRow(ctx, 'Service', _selectedService),
                    _infoRow(ctx, 'Initial Due', due > 0 ? '$currency$due' : 'Zero (Fully Paid)'),
                  ],
                ),
              ),
              const SizedBox(height: 20),

              // WhatsApp Welcome Message
              ElevatedButton.icon(
                onPressed: () async {
                  final cleanPhone = phone.replaceAll(RegExp(r'[^0-9]'), '');
                  final url = 'https://wa.me/$cleanPhone?text=${Uri.encodeComponent(welcomeMessage)}';
                  final uri = Uri.parse(url);
                  if (await canLaunchUrl(uri)) {
                    await launchUrl(uri, mode: LaunchMode.externalApplication);
                  }
                },
                style: ElevatedButton.styleFrom(
                  backgroundColor: const Color(0xFF25D366),
                  foregroundColor: Colors.white,
                  padding: const EdgeInsets.symmetric(vertical: 14),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                  minimumSize: const Size.fromHeight(46),
                ),
                icon: const Icon(Icons.send_rounded, size: 18),
                label: Text(
                  'Send Welcome on WhatsApp',
                  style: GoogleFonts.plusJakartaSans(
                    fontWeight: FontWeight.w800,
                    fontSize: 13,
                  ),
                ),
              ),
              const SizedBox(height: 10),

              TextButton(
                onPressed: () {
                  Navigator.of(ctx).pop(); // Close dialog
                  Navigator.of(context).pop(); // Back to members list
                },
                child: Text(
                  'Go to Members List',
                  style: GoogleFonts.plusJakartaSans(
                    color: AppColors.lime,
                    fontWeight: FontWeight.w800,
                    fontSize: 13.5,
                  ),
                ),
              ),
            ],
          ),
        );
      },
    );
  }

  Widget _infoRow(BuildContext ctx, String label, String value) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(
            label,
            style: GoogleFonts.plusJakartaSans(
              fontSize: 12,
              fontWeight: FontWeight.w600,
              color: AppColors.textMuted(ctx),
            ),
          ),
          Text(
            value,
            style: GoogleFonts.plusJakartaSans(
              fontSize: 12.5,
              fontWeight: FontWeight.w800,
              color: AppColors.textPrimary(ctx),
            ),
          ),
        ],
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final admin = context.watch<AdminProvider>();
    final currency = context.watch<AuthProvider>().currentTenant?.currency ?? '₹';

    return Scaffold(
      backgroundColor: AppColors.bg(context),
      appBar: AppBar(
        title: Text(
          'Register New Member',
          style: GoogleFonts.outfit(fontWeight: FontWeight.w900, fontSize: 18),
        ),
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(20),
        child: Center(
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 500),
            child: Form(
              key: _formKey,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  // 1. Photo Card
                  Center(
                    child: Stack(
                      alignment: Alignment.bottomRight,
                      children: [
                        InkWell(
                          onTap: _showImageSourcePicker,
                          borderRadius: BorderRadius.circular(50),
                          child: Container(
                            width: 100,
                            height: 100,
                            decoration: BoxDecoration(
                              shape: BoxShape.circle,
                              color: AppColors.cardElevated(context),
                              border: Border.all(color: AppColors.limeBorder, width: 2),
                              boxShadow: [
                                BoxShadow(
                                  color: AppColors.lime.withValues(alpha: 0.2),
                                  blurRadius: 15,
                                ),
                              ],
                            ),
                            child: ClipOval(
                              child: _photoBytes != null
                                  ? Image.memory(_photoBytes!, fit: BoxFit.cover)
                                  : Column(
                                      mainAxisAlignment: MainAxisAlignment.center,
                                      children: [
                                        const Icon(Icons.add_a_photo_rounded, color: AppColors.lime, size: 28),
                                        const SizedBox(height: 4),
                                        Text(
                                          'Add Photo',
                                          style: GoogleFonts.plusJakartaSans(
                                            fontSize: 10,
                                            fontWeight: FontWeight.w700,
                                            color: AppColors.lime,
                                          ),
                                        ),
                                      ],
                                    ),
                            ),
                          ),
                        ),
                        Container(
                          padding: const EdgeInsets.all(6),
                          decoration: const BoxDecoration(
                            color: AppColors.lime,
                            shape: BoxShape.circle,
                          ),
                          child: const Icon(Icons.camera_alt_rounded, size: 14, color: Colors.black),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 24),

                  // 2. Personal Information Section
                  _sectionHeader('PERSONAL INFORMATION'),
                  const SizedBox(height: 12),

                  TextFormField(
                    controller: _fullnameController,
                    style: GoogleFonts.plusJakartaSans(fontSize: 14),
                    decoration: const InputDecoration(
                      labelText: 'Full Name *',
                      hintText: 'e.g., Rahul Sharma',
                      prefixIcon: Icon(Icons.person_outline_rounded),
                    ),
                    validator: (v) => (v == null || v.trim().isEmpty) ? 'Full Name is required' : null,
                  ),
                  const SizedBox(height: 14),

                  TextFormField(
                    controller: _phoneController,
                    keyboardType: TextInputType.phone,
                    style: GoogleFonts.plusJakartaSans(fontSize: 14),
                    decoration: const InputDecoration(
                      labelText: 'Mobile Phone Number *',
                      hintText: 'e.g., 9876543210',
                      prefixIcon: Icon(Icons.phone_outlined),
                    ),
                    validator: (v) => (v == null || v.trim().isEmpty) ? 'Phone number is required' : null,
                  ),
                  const SizedBox(height: 14),

                  Row(
                    children: [
                      Expanded(
                        child: TextFormField(
                          controller: _emailController,
                          keyboardType: TextInputType.emailAddress,
                          style: GoogleFonts.plusJakartaSans(fontSize: 13),
                          decoration: const InputDecoration(
                            labelText: 'Email (Optional)',
                            prefixIcon: Icon(Icons.email_outlined),
                          ),
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: DropdownButtonFormField<String>(
                          initialValue: const ['Male', 'Female', 'Other'].contains(_gender) ? _gender : 'Male',
                          dropdownColor: AppColors.card(context),
                          decoration: const InputDecoration(
                            labelText: 'Gender',
                            prefixIcon: Icon(Icons.wc_rounded),
                          ),
                          items: const [
                            DropdownMenuItem(value: 'Male', child: Text('Male')),
                            DropdownMenuItem(value: 'Female', child: Text('Female')),
                            DropdownMenuItem(value: 'Other', child: Text('Other')),
                          ],
                          onChanged: (v) {
                            if (v != null) setState(() => _gender = v);
                          },
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 14),

                  TextFormField(
                    controller: _addressController,
                    style: GoogleFonts.plusJakartaSans(fontSize: 13),
                    decoration: const InputDecoration(
                      labelText: 'Residential Address / City',
                      hintText: 'e.g., Sector 15, Near City Mall',
                      prefixIcon: Icon(Icons.location_on_outlined),
                    ),
                  ),
                  const SizedBox(height: 24),

                  // 3. Package & Plan Section
                  _sectionHeader('MEMBERSHIP PACKAGE & CHARGES'),
                  const SizedBox(height: 12),

                  // Service Selector (From rates or default)
                  Builder(
                    builder: (context) {
                      final rawServices = (admin.rates.isNotEmpty
                              ? admin.rates.map((r) => r.name.trim()).toList()
                              : ['General Fitness', 'Strength & Cardio', 'Personal Training', 'CrossFit']);

                      final availableServices = <String>[];
                      for (final s in rawServices) {
                        if (s.isNotEmpty && !availableServices.contains(s)) {
                          availableServices.add(s);
                        }
                      }
                      if (availableServices.isEmpty) {
                        availableServices.add('General Fitness');
                      }

                      final currentSelected = availableServices.contains(_selectedService)
                          ? _selectedService
                          : availableServices.first;

                      return DropdownButtonFormField<String>(
                        key: ValueKey(currentSelected),
                        initialValue: currentSelected,
                        dropdownColor: AppColors.card(context),
                        decoration: const InputDecoration(
                          labelText: 'Select Workout / Gym Package',
                          prefixIcon: Icon(Icons.fitness_center_rounded),
                        ),
                        items: availableServices
                            .map((name) => DropdownMenuItem(value: name, child: Text(name)))
                            .toList(),
                        onChanged: (v) {
                          if (v != null) {
                            setState(() {
                              _selectedService = v;
                              final match = admin.rates.where((r) => r.name.trim() == v).firstOrNull;
                              if (match != null) {
                                final total = match.charge * _planMonths;
                                _totalAmountController.text = total.toStringAsFixed(0);
                                _paidAmountController.text = total.toStringAsFixed(0);
                              }
                            });
                          }
                        },
                      );
                    },
                  ),
                  const SizedBox(height: 14),

                  // Plan Duration Selector Chips
                  Text(
                    'PLAN DURATION',
                    style: GoogleFonts.plusJakartaSans(
                      fontSize: 11,
                      fontWeight: FontWeight.w800,
                      color: AppColors.textMuted(context),
                      letterSpacing: 0.5,
                    ),
                  ),
                  const SizedBox(height: 8),
                  Row(
                    children: [
                      _durationChip(1, '1 Mo'),
                      _durationChip(3, '3 Mos'),
                      _durationChip(6, '6 Mos'),
                      _durationChip(12, '1 Year'),
                    ],
                  ),
                  const SizedBox(height: 20),

                  // Financials Card
                  Container(
                    padding: const EdgeInsets.all(18),
                    decoration: BoxDecoration(
                      color: AppColors.card(context),
                      borderRadius: BorderRadius.circular(18),
                      border: Border.all(color: AppColors.border(context)),
                    ),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        Row(
                          children: [
                            Expanded(
                              child: TextFormField(
                                controller: _totalAmountController,
                                keyboardType: TextInputType.number,
                                style: GoogleFonts.outfit(
                                  fontSize: 18,
                                  fontWeight: FontWeight.w800,
                                  color: AppColors.textPrimary(context),
                                ),
                                decoration: InputDecoration(
                                  labelText: 'Total Plan Fee',
                                  prefixText: '$currency ',
                                ),
                                onChanged: (v) => setState(() {}),
                              ),
                            ),
                            const SizedBox(width: 12),
                            Expanded(
                              child: TextFormField(
                                controller: _paidAmountController,
                                keyboardType: TextInputType.number,
                                style: GoogleFonts.outfit(
                                  fontSize: 18,
                                  fontWeight: FontWeight.w800,
                                  color: AppColors.lime,
                                ),
                                decoration: InputDecoration(
                                  labelText: 'Amount Paid Now',
                                  prefixText: '$currency ',
                                ),
                                onChanged: (v) => setState(() {}),
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 14),

                        // Computed Due Banner
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
                          decoration: BoxDecoration(
                            color: _dueAmount > 0
                                ? AppColors.warning.withValues(alpha: 0.12)
                                : AppColors.success.withValues(alpha: 0.12),
                            borderRadius: BorderRadius.circular(12),
                            border: Border.all(
                              color: _dueAmount > 0 ? AppColors.warning : AppColors.success,
                            ),
                          ),
                          child: Row(
                            mainAxisAlignment: MainAxisAlignment.spaceBetween,
                            children: [
                              Text(
                                _dueAmount > 0 ? 'Pending Due Balance:' : 'Payment Status:',
                                style: GoogleFonts.plusJakartaSans(
                                  fontSize: 12.5,
                                  fontWeight: FontWeight.w700,
                                  color: _dueAmount > 0 ? AppColors.warning : AppColors.success,
                                ),
                              ),
                              Text(
                                _dueAmount > 0 ? '$currency${_dueAmount.toStringAsFixed(2)}' : '✓ Full Paid (Zero Due)',
                                style: GoogleFonts.outfit(
                                  fontSize: 14,
                                  fontWeight: FontWeight.w900,
                                  color: _dueAmount > 0 ? AppColors.warning : AppColors.success,
                                ),
                              ),
                            ],
                          ),
                        ),

                        if (_dueAmount > 0) ...[
                          const SizedBox(height: 14),
                          InkWell(
                            onTap: _selectDueDate,
                            borderRadius: BorderRadius.circular(12),
                            child: Container(
                              padding: const EdgeInsets.all(12),
                              decoration: BoxDecoration(
                                color: AppColors.cardElevated(context),
                                borderRadius: BorderRadius.circular(12),
                                border: Border.all(color: AppColors.border(context)),
                              ),
                              child: Row(
                                children: [
                                  const Icon(Icons.event_note_rounded, size: 18, color: AppColors.cyan),
                                  const SizedBox(width: 10),
                                  Expanded(
                                    child: Column(
                                      crossAxisAlignment: CrossAxisAlignment.start,
                                      children: [
                                        Text(
                                          'Promise Due Date',
                                          style: GoogleFonts.plusJakartaSans(
                                            fontSize: 11,
                                            color: AppColors.textMuted(context),
                                            fontWeight: FontWeight.w600,
                                          ),
                                        ),
                                        Text(
                                          _dueDate != null
                                              ? DateFormat('dd MMM yyyy').format(_dueDate!)
                                              : 'Select date for remaining due',
                                          style: GoogleFonts.plusJakartaSans(
                                            fontSize: 13,
                                            fontWeight: FontWeight.w700,
                                            color: AppColors.textPrimary(context),
                                          ),
                                        ),
                                      ],
                                    ),
                                  ),
                                  const Icon(Icons.calendar_month_rounded, size: 16, color: AppColors.lime),
                                ],
                              ),
                            ),
                          ),
                        ],
                        const SizedBox(height: 14),

                        // Payment Method
                        DropdownButtonFormField<String>(
                          initialValue: const ['Cash', 'UPI', 'Card', 'Online'].contains(_paymentMethod) ? _paymentMethod : 'Cash',
                          dropdownColor: AppColors.card(context),
                          decoration: const InputDecoration(
                            labelText: 'Initial Payment Method',
                            prefixIcon: Icon(Icons.account_balance_wallet_rounded),
                          ),
                          items: const [
                            DropdownMenuItem(value: 'Cash', child: Text('Cash at Desk')),
                            DropdownMenuItem(value: 'UPI', child: Text('UPI / QR Code')),
                            DropdownMenuItem(value: 'Card', child: Text('Credit / Debit Card')),
                            DropdownMenuItem(value: 'Online', child: Text('Online Transfer')),
                          ],
                          onChanged: (v) {
                            if (v != null) setState(() => _paymentMethod = v);
                          },
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 28),

                  // Register Button
                  ElevatedButton(
                    onPressed: _isSubmitting ? null : _handleSubmit,
                    style: ElevatedButton.styleFrom(
                      backgroundColor: AppColors.lime,
                      foregroundColor: Colors.black,
                      padding: const EdgeInsets.symmetric(vertical: 16),
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
                      shadowColor: AppColors.lime.withValues(alpha: 0.3),
                      elevation: 8,
                    ),
                    child: _isSubmitting
                        ? const SizedBox(
                            height: 22,
                            width: 22,
                            child: CircularProgressIndicator(strokeWidth: 2.5, color: Colors.black),
                          )
                        : Row(
                            mainAxisAlignment: MainAxisAlignment.center,
                            children: [
                              const Icon(Icons.person_add_alt_1_rounded, size: 20),
                              const SizedBox(width: 8),
                              Text(
                                'Complete Member Registration',
                                style: GoogleFonts.plusJakartaSans(
                                  fontWeight: FontWeight.w900,
                                  fontSize: 15,
                                  letterSpacing: 0.3,
                                ),
                              ),
                            ],
                          ),
                  ),
                  const SizedBox(height: 30),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }

  Widget _sectionHeader(String title) {
    return Text(
      title,
      style: GoogleFonts.plusJakartaSans(
        fontSize: 11.5,
        fontWeight: FontWeight.w800,
        color: AppColors.textMuted(context),
        letterSpacing: 0.8,
      ),
    );
  }

  Widget _durationChip(int months, String label) {
    final isSelected = _planMonths == months;
    return Expanded(
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 3),
        child: ChoiceChip(
          label: Text(label),
          selected: isSelected,
          onSelected: (s) {
            if (s) {
              setState(() {
                _planMonths = months;
                final match = context.read<AdminProvider>().rates.where((r) => r.name == _selectedService).firstOrNull;
                final unitPrice = match?.charge ?? 1000.0;
                final total = unitPrice * months;
                _totalAmountController.text = total.toStringAsFixed(0);
                _paidAmountController.text = total.toStringAsFixed(0);
              });
            }
          },
          selectedColor: AppColors.lime,
          labelStyle: GoogleFonts.plusJakartaSans(
            fontWeight: FontWeight.w800,
            fontSize: 12,
            color: isSelected ? Colors.black : AppColors.textPrimary(context),
          ),
        ),
      ),
    );
  }
}
