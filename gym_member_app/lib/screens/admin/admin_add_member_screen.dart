import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:image_picker/image_picker.dart';
import 'package:intl/intl.dart';
import 'package:provider/provider.dart';
import 'package:qr_flutter/qr_flutter.dart';
import 'package:url_launcher/url_launcher.dart';
import '../../core/theme/app_colors.dart';
import '../../core/services/pdf_service.dart';
import '../../models/admin_transaction_models.dart';
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
  final _usernameController = TextEditingController();
  final _phoneController = TextEditingController();
  final _emailController = TextEditingController();
  final _addressController = TextEditingController();
  final _passwordController = TextEditingController(text: '123456');
  final _totalAmountController = TextEditingController(text: '1000');
  final _paidAmountController = TextEditingController(text: '1000');
  final _upiRefController = TextEditingController();

  String _gender = 'Male';
  String _selectedService = 'General Fitness';
  int _planMonths = 1;
  DateTime _dor = DateTime.now();
  DateTime? _dueDate;
  String _paymentMethod = 'Cash';
  bool _obscurePassword = true;

  // Photo
  XFile? _selectedPhoto;
  Uint8List? _photoBytes;
  final ImagePicker _picker = ImagePicker();

  bool _isSubmitting = false;

  @override
  void initState() {
    super.initState();
    _fullnameController.addListener(_autoSuggestUsername);
    WidgetsBinding.instance.addPostFrameCallback((_) async {
      await context.read<AdminProvider>().fetchRates();
      // Preload gym QR for UPI payment
      context.read<AdminProvider>().fetchGymQr();
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

  void _autoSuggestUsername() {
    if (_usernameController.text.isEmpty || _usernameController.text.startsWith('mem_')) {
      final name = _fullnameController.text.trim().toLowerCase().replaceAll(RegExp(r'[^a-z0-9]'), '_');
      if (name.isNotEmpty) {
        _usernameController.text = name;
      }
    }
  }

  @override
  void dispose() {
    _fullnameController.removeListener(_autoSuggestUsername);
    _fullnameController.dispose();
    _usernameController.dispose();
    _phoneController.dispose();
    _emailController.dispose();
    _addressController.dispose();
    _passwordController.dispose();
    _totalAmountController.dispose();
    _paidAmountController.dispose();
    _upiRefController.dispose();
    super.dispose();
  }

  double get _totalAmount => double.tryParse(_totalAmountController.text.trim()) ?? 0.0;
  double get _paidAmount => double.tryParse(_paidAmountController.text.trim()) ?? 0.0;
  double get _dueAmount => (_totalAmount - _paidAmount) > 0 ? (_totalAmount - _paidAmount) : 0.0;

  DateTime get _computedExpiryDate {
    return DateTime(_dor.year, _dor.month + _planMonths, _dor.day);
  }

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
      backgroundColor: const Color(0xFF1E1E2C),
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
                    color: Colors.white,
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
          color: const Color(0xFF2A2A3E),
          borderRadius: BorderRadius.circular(16),
          border: Border.all(color: Colors.white.withOpacity(0.08)),
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
                color: color ?? Colors.white,
              ),
              textAlign: TextAlign.center,
            ),
          ],
        ),
      ),
    );
  }

  Future<void> _selectJoiningDate() async {
    final picked = await showDatePicker(
      context: context,
      initialDate: _dor,
      firstDate: DateTime(2020),
      lastDate: DateTime.now().add(const Duration(days: 30)),
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
      setState(() => _dor = picked);
    }
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
      setState(() => _dueDate = picked);
    }
  }

  Future<void> _handleSubmit() async {
    if (!_formKey.currentState!.validate()) return;
    FocusScope.of(context).unfocus();

    setState(() => _isSubmitting = true);
    final admin = context.read<AdminProvider>();

    try {
      final upiRef = _upiRefController.text.trim();
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
        transactionRef: upiRef.isNotEmpty ? upiRef : null,
        notes: upiRef.isNotEmpty ? 'UPI Ref / UTR: $upiRef' : null,
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
    final rawMemberId = data['member_id'] ?? data['id'] ?? '0';
    final memberIdInt = int.tryParse('$rawMemberId') ?? 0;
    final memberId = rawMemberId.toString();
    final name = data['fullname'] ?? _fullnameController.text;
    final username = data['username'] ?? _usernameController.text;
    final password = data['password'] ?? _passwordController.text;
    final phone = data['phone'] ?? _phoneController.text;
    final paid = (data['paid_amount'] is num) ? (data['paid_amount'] as num).toDouble() : _paidAmount;
    final due = (data['due_amount'] is num) ? (data['due_amount'] as num).toDouble() : _dueAmount;
    final total = (data['total_amount'] is num) ? (data['total_amount'] as num).toDouble() : _totalAmount;
    final startDateStr = data['start_date'] ?? DateFormat('dd MMM yyyy').format(_dor);
    final expiryDateStr = data['expiry_date'] ?? DateFormat('dd MMM yyyy').format(_computedExpiryDate);

    final credentialsText = "🏋️ *Gym Membership Confirmation - $gymName*\n\n"
        "👤 Member ID: #$memberId\n"
        "📛 Name: $name\n"
        "📱 Phone: $phone\n"
        "🔑 *Login Username:* $username\n"
        "🔒 *Login Password:* $password\n\n"
        "📦 Plan: $_selectedService ($_planMonths Month)\n"
        "📅 Joining Date: $startDateStr\n"
        "⏳ Expiry Date: $expiryDateStr\n"
        "💳 Amount Paid: $currency${paid.toStringAsFixed(0)}\n"
        "${due > 0 ? "⚠️ Pending Due: $currency${due.toStringAsFixed(0)}\n" : "✓ Dues: Clear (₹0)\n"}\n"
        "📲 Download Gym Member App & login with your username/password to track workouts & attendance!";

    bool isGeneratingPdf = false;

    showDialog(
      context: context,
      barrierDismissible: false,
      builder: (ctx) {
        return StatefulBuilder(
          builder: (dialogCtx, setDialogState) {
            Future<MemberRegistrationDocumentData?> fetchDoc() async {
              if (memberIdInt <= 0) return null;
              setDialogState(() => isGeneratingPdf = true);
              final provider = Provider.of<AdminProvider>(context, listen: false);
              final docData = await provider.fetchMemberRegistrationData(memberIdInt);
              setDialogState(() => isGeneratingPdf = false);
              return docData;
            }

            return AlertDialog(
              backgroundColor: const Color(0xFF1E1E2C),
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(24),
                side: const BorderSide(color: AppColors.lime),
              ),
              contentPadding: const EdgeInsets.all(20),
              content: SingleChildScrollView(
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Container(
                      width: 56,
                      height: 56,
                      decoration: BoxDecoration(
                        color: AppColors.lime.withOpacity(0.15),
                        shape: BoxShape.circle,
                        border: Border.all(color: AppColors.lime, width: 2),
                      ),
                      child: const Icon(Icons.check_circle_rounded, color: AppColors.lime, size: 30),
                    ),
                    const SizedBox(height: 12),
                    Text(
                      'Member Registered Successfully!',
                      style: GoogleFonts.outfit(
                        fontSize: 18,
                        fontWeight: FontWeight.w900,
                        color: Colors.white,
                      ),
                      textAlign: TextAlign.center,
                    ),
                    const SizedBox(height: 4),
                    Text(
                      'Official record created with full payment audit details.',
                      style: GoogleFonts.plusJakartaSans(
                        fontSize: 12,
                        color: Colors.white60,
                      ),
                      textAlign: TextAlign.center,
                    ),
                    const SizedBox(height: 16),

                    // Credentials & Details Card
                    Container(
                      padding: const EdgeInsets.all(14),
                      decoration: BoxDecoration(
                        color: const Color(0xFF13131A),
                        borderRadius: BorderRadius.circular(16),
                        border: Border.all(color: Colors.white.withOpacity(0.08)),
                      ),
                      child: Column(
                        children: [
                          _infoRow('Member ID', '#$memberId', isHighlight: true),
                          _infoRow('Member Name', name),
                          _infoRow('Mobile Number', phone),
                          _infoRow('Login Username', username, isHighlight: true),
                          _infoRow('Login Password', password, isHighlight: true),
                          const Divider(height: 14, color: Colors.white12),
                          _infoRow('Selected Plan', '$_selectedService ($_planMonths Mo)'),
                          _infoRow('Joining Date', startDateStr),
                          _infoRow('Expiry Date', expiryDateStr),
                          _infoRow('Plan / Reg Fee', '$currency${total.toStringAsFixed(0)}'),
                          _infoRow('Amount Paid', '$currency${paid.toStringAsFixed(0)}', color: const Color(0xFF00CEC9)),
                          _infoRow(
                            'Pending Due',
                            due > 0 ? '$currency${due.toStringAsFixed(0)}' : '₹0 (Clear)',
                            color: due > 0 ? AppColors.warning : AppColors.success,
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 16),

                    if (isGeneratingPdf)
                      const Padding(
                        padding: EdgeInsets.symmetric(vertical: 8),
                        child: Row(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2, color: AppColors.lime)),
                            SizedBox(width: 8),
                            Text('Preparing official document...', style: TextStyle(color: Colors.white70, fontSize: 12)),
                          ],
                        ),
                      )
                    else ...[
                      // 1. Primary Share Registration PDF Button
                      SizedBox(
                        width: double.infinity,
                        child: ElevatedButton.icon(
                          onPressed: () async {
                            final doc = await fetchDoc();
                            if (doc != null && mounted) {
                              await PdfService.shareRegistrationPdf(context, doc);
                            }
                          },
                          style: ElevatedButton.styleFrom(
                            backgroundColor: AppColors.lime,
                            foregroundColor: Colors.black,
                            padding: const EdgeInsets.symmetric(vertical: 12),
                            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                          ),
                          icon: const Icon(Icons.share_rounded, size: 18),
                          label: const Text('Share Registration PDF', style: TextStyle(fontSize: 13, fontWeight: FontWeight.bold)),
                        ),
                      ),
                      const SizedBox(height: 8),

                      // 2 & 3. View PDF & Download PDF Buttons
                      Row(
                        children: [
                          Expanded(
                            child: OutlinedButton.icon(
                              onPressed: () async {
                                final doc = await fetchDoc();
                                if (doc != null && mounted) {
                                  PdfService.previewRegistrationPdf(context, doc);
                                }
                              },
                              style: OutlinedButton.styleFrom(
                                foregroundColor: Colors.white,
                                side: BorderSide(color: Colors.white.withOpacity(0.25)),
                                padding: const EdgeInsets.symmetric(vertical: 11),
                                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                              ),
                              icon: const Icon(Icons.remove_red_eye_outlined, size: 16),
                              label: const Text('View PDF', style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold)),
                            ),
                          ),
                          const SizedBox(width: 8),
                          Expanded(
                            child: OutlinedButton.icon(
                              onPressed: () async {
                                final doc = await fetchDoc();
                                if (doc != null && mounted) {
                                  await PdfService.downloadRegistrationPdf(context, doc);
                                }
                              },
                              style: OutlinedButton.styleFrom(
                                foregroundColor: Colors.white,
                                side: BorderSide(color: Colors.white.withOpacity(0.25)),
                                padding: const EdgeInsets.symmetric(vertical: 11),
                                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                              ),
                              icon: const Icon(Icons.download_rounded, size: 16),
                              label: const Text('Download PDF', style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold)),
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 8),
                    ],

                    // Credentials Copy & WhatsApp Buttons
                    Row(
                      children: [
                        Expanded(
                          child: OutlinedButton.icon(
                            onPressed: () {
                              Clipboard.setData(ClipboardData(text: credentialsText));
                              ScaffoldMessenger.of(context).showSnackBar(
                                const SnackBar(content: Text('Login credentials copied to clipboard!')),
                              );
                            },
                            style: OutlinedButton.styleFrom(
                              foregroundColor: Colors.white70,
                              side: BorderSide(color: Colors.white.withOpacity(0.15)),
                              padding: const EdgeInsets.symmetric(vertical: 10),
                              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                            ),
                            icon: const Icon(Icons.copy_rounded, size: 14),
                            label: const Text('Copy Text', style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold)),
                          ),
                        ),
                        const SizedBox(width: 8),
                        Expanded(
                          child: ElevatedButton.icon(
                            onPressed: () async {
                              final cleanPhone = phone.replaceAll(RegExp(r'[^0-9]'), '');
                              final url = 'https://wa.me/$cleanPhone?text=${Uri.encodeComponent(credentialsText)}';
                              final uri = Uri.parse(url);
                              if (await canLaunchUrl(uri)) {
                                await launchUrl(uri, mode: LaunchMode.externalApplication);
                              }
                            },
                            style: ElevatedButton.styleFrom(
                              backgroundColor: const Color(0xFF25D366),
                              foregroundColor: Colors.white,
                              padding: const EdgeInsets.symmetric(vertical: 10),
                              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                            ),
                            icon: const Icon(Icons.send_rounded, size: 14),
                            label: const Text('WhatsApp Text', style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold)),
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 12),

                    SizedBox(
                      width: double.infinity,
                      child: TextButton(
                        onPressed: () {
                          Navigator.of(ctx).pop();
                          Navigator.of(context).pop();
                        },
                        style: TextButton.styleFrom(
                          foregroundColor: Colors.white60,
                          padding: const EdgeInsets.symmetric(vertical: 8),
                        ),
                        child: const Text('Done & Back to Members', style: TextStyle(fontWeight: FontWeight.w600, fontSize: 13)),
                      ),
                    ),
                  ],
                ),
              ),
            );
          },
        );
      },
    );
  }

  Widget _infoRow(String label, String value, {bool isHighlight = false, Color? color}) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 3),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(
            label,
            style: GoogleFonts.plusJakartaSans(
              fontSize: 12,
              fontWeight: FontWeight.w600,
              color: Colors.white60,
            ),
          ),
          Text(
            value,
            style: GoogleFonts.plusJakartaSans(
              fontSize: 12.5,
              fontWeight: FontWeight.w800,
              color: color ?? (isHighlight ? AppColors.lime : Colors.white),
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
      backgroundColor: const Color(0xFF13131A),
      appBar: AppBar(
        backgroundColor: const Color(0xFF1E1E2C),
        elevation: 0,
        title: Text(
          'Register New Member',
          style: GoogleFonts.outfit(fontWeight: FontWeight.w900, fontSize: 18, color: Colors.white),
        ),
      ),
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.fromLTRB(16, 16, 16, 36),
        child: Center(
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 550),
            child: Form(
              key: _formKey,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  // 1. Photo Avatar
                  Center(
                    child: Stack(
                      alignment: Alignment.bottomRight,
                      children: [
                        InkWell(
                          onTap: _showImageSourcePicker,
                          borderRadius: BorderRadius.circular(50),
                          child: Container(
                            width: 90,
                            height: 90,
                            decoration: BoxDecoration(
                              shape: BoxShape.circle,
                              color: const Color(0xFF1E1E2C),
                              border: Border.all(color: AppColors.lime, width: 2),
                            ),
                            child: ClipOval(
                              child: _photoBytes != null
                                  ? Image.memory(_photoBytes!, fit: BoxFit.cover)
                                  : Column(
                                      mainAxisAlignment: MainAxisAlignment.center,
                                      children: [
                                        const Icon(Icons.add_a_photo_rounded, color: AppColors.lime, size: 26),
                                        const SizedBox(height: 2),
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
                  const SizedBox(height: 20),

                  // 2. Personal Information Section
                  _sectionHeader('1. PERSONAL INFORMATION'),
                  const SizedBox(height: 10),

                  TextFormField(
                    controller: _fullnameController,
                    style: const TextStyle(color: Colors.white, fontSize: 14),
                    decoration: InputDecoration(
                      labelText: 'Full Name *',
                      labelStyle: const TextStyle(color: Colors.white70),
                      filled: true,
                      fillColor: const Color(0xFF1E1E2C),
                      border: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: BorderSide.none),
                      prefixIcon: const Icon(Icons.person_outline_rounded, color: AppColors.lime),
                    ),
                    validator: (v) => (v == null || v.trim().isEmpty) ? 'Full Name is required' : null,
                  ),
                  const SizedBox(height: 12),

                  TextFormField(
                    controller: _phoneController,
                    keyboardType: TextInputType.phone,
                    style: const TextStyle(color: Colors.white, fontSize: 14),
                    decoration: InputDecoration(
                      labelText: 'Mobile Phone Number *',
                      labelStyle: const TextStyle(color: Colors.white70),
                      filled: true,
                      fillColor: const Color(0xFF1E1E2C),
                      border: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: BorderSide.none),
                      prefixIcon: const Icon(Icons.phone_outlined, color: Color(0xFF00CEC9)),
                    ),
                    validator: (v) => (v == null || v.trim().isEmpty) ? 'Phone number is required' : null,
                  ),
                  const SizedBox(height: 12),

                  Row(
                    children: [
                      Expanded(
                        child: TextFormField(
                          controller: _emailController,
                          keyboardType: TextInputType.emailAddress,
                          style: const TextStyle(color: Colors.white, fontSize: 13),
                          decoration: InputDecoration(
                            labelText: 'Email (Optional)',
                            labelStyle: const TextStyle(color: Colors.white70),
                            filled: true,
                            fillColor: const Color(0xFF1E1E2C),
                            border: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: BorderSide.none),
                            prefixIcon: const Icon(Icons.email_outlined, color: Colors.white54),
                          ),
                        ),
                      ),
                      const SizedBox(width: 10),
                      Expanded(
                        child: DropdownButtonFormField<String>(
                          initialValue: const ['Male', 'Female', 'Other'].contains(_gender) ? _gender : 'Male',
                          dropdownColor: const Color(0xFF2A2A3E),
                          style: const TextStyle(color: Colors.white),
                          decoration: InputDecoration(
                            labelText: 'Gender',
                            labelStyle: const TextStyle(color: Colors.white70),
                            filled: true,
                            fillColor: const Color(0xFF1E1E2C),
                            border: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: BorderSide.none),
                            prefixIcon: const Icon(Icons.wc_rounded, color: Colors.white54),
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
                  const SizedBox(height: 12),

                  TextFormField(
                    controller: _addressController,
                    style: const TextStyle(color: Colors.white, fontSize: 13),
                    decoration: InputDecoration(
                      labelText: 'Residential Address / City',
                      labelStyle: const TextStyle(color: Colors.white70),
                      filled: true,
                      fillColor: const Color(0xFF1E1E2C),
                      border: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: BorderSide.none),
                      prefixIcon: const Icon(Icons.location_on_outlined, color: Colors.white54),
                    ),
                  ),
                  const SizedBox(height: 20),

                  // 3. Member App Login Credentials
                  _sectionHeader('2. MEMBER APP LOGIN CREDENTIALS'),
                  const SizedBox(height: 10),

                  Row(
                    children: [
                      Expanded(
                        child: TextFormField(
                          controller: _usernameController,
                          style: const TextStyle(color: Colors.white, fontSize: 13),
                          decoration: InputDecoration(
                            labelText: 'App Username *',
                            labelStyle: const TextStyle(color: Colors.white70),
                            filled: true,
                            fillColor: const Color(0xFF1E1E2C),
                            border: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: BorderSide.none),
                            prefixIcon: const Icon(Icons.alternate_email, color: AppColors.lime),
                          ),
                        ),
                      ),
                      const SizedBox(width: 10),
                      Expanded(
                        child: TextFormField(
                          controller: _passwordController,
                          obscureText: _obscurePassword,
                          style: const TextStyle(color: Colors.white, fontSize: 13),
                          decoration: InputDecoration(
                            labelText: 'App Password *',
                            labelStyle: const TextStyle(color: Colors.white70),
                            filled: true,
                            fillColor: const Color(0xFF1E1E2C),
                            border: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: BorderSide.none),
                            prefixIcon: const Icon(Icons.lock_outline, color: Color(0xFFFDCB6E)),
                            suffixIcon: IconButton(
                              icon: Icon(_obscurePassword ? Icons.visibility_off : Icons.visibility, color: Colors.white54, size: 18),
                              onPressed: () => setState(() => _obscurePassword = !_obscurePassword),
                            ),
                          ),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 20),

                  // 4. Membership Package & Dates
                  _sectionHeader('3. PACKAGE & JOINING DATES'),
                  const SizedBox(height: 10),

                  // Joining Date Selector
                  InkWell(
                    onTap: _selectJoiningDate,
                    borderRadius: BorderRadius.circular(12),
                    child: Container(
                      padding: const EdgeInsets.all(14),
                      decoration: BoxDecoration(
                        color: const Color(0xFF1E1E2C),
                        borderRadius: BorderRadius.circular(12),
                      ),
                      child: Row(
                        children: [
                          const Icon(Icons.calendar_today_rounded, color: Color(0xFF00CEC9), size: 20),
                          const SizedBox(width: 12),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                const Text('Joining Date (DOR)', style: TextStyle(color: Colors.white60, fontSize: 11)),
                                Text(
                                  DateFormat('dd MMMM yyyy').format(_dor),
                                  style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 14),
                                ),
                              ],
                            ),
                          ),
                          Container(
                            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                            decoration: BoxDecoration(
                              color: Colors.white.withOpacity(0.08),
                              borderRadius: BorderRadius.circular(6),
                            ),
                            child: const Text('Change Date', style: TextStyle(color: AppColors.lime, fontSize: 11, fontWeight: FontWeight.bold)),
                          ),
                        ],
                      ),
                    ),
                  ),
                  const SizedBox(height: 12),

                  // Service Selector
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
                      if (availableServices.isEmpty) availableServices.add('General Fitness');

                      final currentSelected = availableServices.contains(_selectedService) ? _selectedService : availableServices.first;

                      return DropdownButtonFormField<String>(
                        key: ValueKey(currentSelected),
                        initialValue: currentSelected,
                        dropdownColor: const Color(0xFF2A2A3E),
                        style: const TextStyle(color: Colors.white),
                        decoration: InputDecoration(
                          labelText: 'Select Workout / Gym Package',
                          labelStyle: const TextStyle(color: Colors.white70),
                          filled: true,
                          fillColor: const Color(0xFF1E1E2C),
                          border: OutlineInputBorder(borderRadius: BorderRadius.circular(12), borderSide: BorderSide.none),
                          prefixIcon: const Icon(Icons.fitness_center_rounded, color: AppColors.lime),
                        ),
                        items: availableServices.map((name) => DropdownMenuItem(value: name, child: Text(name))).toList(),
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
                  const SizedBox(height: 12),

                  // Duration selector
                  Text(
                    'PLAN DURATION',
                    style: GoogleFonts.plusJakartaSans(
                      fontSize: 11,
                      fontWeight: FontWeight.w800,
                      color: Colors.white60,
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
                  const SizedBox(height: 12),

                  // Live Expiry Banner
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
                    decoration: BoxDecoration(
                      color: const Color(0xFF6C5CE7).withOpacity(0.12),
                      borderRadius: BorderRadius.circular(12),
                      border: Border.all(color: const Color(0xFF6C5CE7).withOpacity(0.3)),
                    ),
                    child: Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        Row(
                          children: [
                            const Icon(Icons.alarm, color: Color(0xFF6C5CE7), size: 18),
                            const SizedBox(width: 8),
                            Text('Membership Expiry:', style: GoogleFonts.plusJakartaSans(color: Colors.white70, fontSize: 12)),
                          ],
                        ),
                        Text(
                          DateFormat('dd MMM yyyy').format(_computedExpiryDate),
                          style: const TextStyle(color: Color(0xFF6C5CE7), fontWeight: FontWeight.bold, fontSize: 13),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 20),

                  // 5. Financials & Payment Section
                  _sectionHeader('4. PAYMENT & DUES BREAKDOWN'),
                  const SizedBox(height: 10),

                  Container(
                    padding: const EdgeInsets.all(16),
                    decoration: BoxDecoration(
                      color: const Color(0xFF1E1E2C),
                      borderRadius: BorderRadius.circular(18),
                    ),
                    child: Column(
                      children: [
                        Row(
                          children: [
                            Expanded(
                              child: TextFormField(
                                controller: _totalAmountController,
                                keyboardType: TextInputType.number,
                                style: const TextStyle(fontSize: 17, fontWeight: FontWeight.bold, color: Colors.white),
                                decoration: InputDecoration(
                                  labelText: 'Total Fee',
                                  labelStyle: const TextStyle(color: Colors.white70),
                                  prefixText: '$currency ',
                                  prefixStyle: const TextStyle(color: Colors.white70),
                                ),
                                onChanged: (v) => setState(() {}),
                              ),
                            ),
                            const SizedBox(width: 12),
                            Expanded(
                              child: TextFormField(
                                controller: _paidAmountController,
                                keyboardType: TextInputType.number,
                                style: const TextStyle(fontSize: 17, fontWeight: FontWeight.bold, color: Color(0xFF00CEC9)),
                                decoration: InputDecoration(
                                  labelText: 'Amount Paid Now',
                                  labelStyle: const TextStyle(color: Colors.white70),
                                  prefixText: '$currency ',
                                  prefixStyle: const TextStyle(color: Color(0xFF00CEC9)),
                                ),
                                onChanged: (v) => setState(() {}),
                              ),
                            ),
                          ],
                        ),
                        const SizedBox(height: 14),

                        // Due status banner
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
                          decoration: BoxDecoration(
                            color: _dueAmount > 0 ? AppColors.warning.withOpacity(0.12) : AppColors.success.withOpacity(0.12),
                            borderRadius: BorderRadius.circular(12),
                            border: Border.all(color: _dueAmount > 0 ? AppColors.warning : AppColors.success),
                          ),
                          child: Row(
                            mainAxisAlignment: MainAxisAlignment.spaceBetween,
                            children: [
                              Text(
                                _dueAmount > 0 ? 'Pending Due Balance:' : 'Payment Status:',
                                style: TextStyle(
                                  fontSize: 12.5,
                                  fontWeight: FontWeight.bold,
                                  color: _dueAmount > 0 ? AppColors.warning : AppColors.success,
                                ),
                              ),
                              Text(
                                _dueAmount > 0 ? '$currency${_dueAmount.toStringAsFixed(2)}' : '✓ Full Paid (Zero Due)',
                                style: TextStyle(
                                  fontSize: 14,
                                  fontWeight: FontWeight.bold,
                                  color: _dueAmount > 0 ? AppColors.warning : AppColors.success,
                                ),
                              ),
                            ],
                          ),
                        ),

                        if (_dueAmount > 0) ...[
                          const SizedBox(height: 12),
                          InkWell(
                            onTap: _selectDueDate,
                            borderRadius: BorderRadius.circular(12),
                            child: Container(
                              padding: const EdgeInsets.all(12),
                              decoration: BoxDecoration(
                                color: Colors.white.withOpacity(0.04),
                                borderRadius: BorderRadius.circular(12),
                                border: Border.all(color: Colors.white12),
                              ),
                              child: Row(
                                children: [
                                  const Icon(Icons.event_note_rounded, size: 18, color: Color(0xFF00CEC9)),
                                  const SizedBox(width: 10),
                                  Expanded(
                                    child: Column(
                                      crossAxisAlignment: CrossAxisAlignment.start,
                                      children: [
                                        const Text('Promise Due Date', style: TextStyle(fontSize: 11, color: Colors.white60)),
                                        Text(
                                          _dueDate != null ? DateFormat('dd MMM yyyy').format(_dueDate!) : 'Tap to select due date',
                                          style: const TextStyle(fontSize: 13, fontWeight: FontWeight.bold, color: Colors.white),
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
                          dropdownColor: const Color(0xFF2A2A3E),
                          style: const TextStyle(color: Colors.white),
                          decoration: InputDecoration(
                            labelText: 'Initial Payment Method',
                            labelStyle: const TextStyle(color: Colors.white70),
                            prefixIcon: const Icon(Icons.account_balance_wallet_rounded, color: AppColors.lime),
                          ),
                          items: const [
                            DropdownMenuItem(value: 'Cash', child: Text('Cash at Desk')),
                            DropdownMenuItem(value: 'UPI', child: Text('UPI / Dynamic QR Code')),
                            DropdownMenuItem(value: 'Card', child: Text('Credit / Debit Card')),
                            DropdownMenuItem(value: 'Online', child: Text('Online Transfer')),
                          ],
                          onChanged: (v) {
                            if (v != null) {
                              setState(() => _paymentMethod = v);
                              if (v == 'UPI') {
                                context.read<AdminProvider>().fetchGymQr(
                                  amount: _paidAmount > 0 ? _paidAmount : _totalAmount,
                                  note: 'Member Registration - ${_fullnameController.text.trim()}',
                                );
                              }
                            }
                          },
                        ),

                        // Dynamic UPI QR Section
                        if (_paymentMethod == 'UPI') ...[
                          const SizedBox(height: 16),
                          _buildUpiQrCard(context, currency),
                        ],
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
    ),
  );
}

  Widget _buildUpiQrCard(BuildContext context, String currency) {
    final admin = context.watch<AdminProvider>();
    final auth = context.watch<AuthProvider>();
    final qrData = admin.gymQr;
    final gymName = qrData?.gymName ?? auth.currentTenant?.gymName ?? 'Our Gym';
    final upiId = qrData?.upiId ?? auth.currentTenant?.upiId ?? 'gymdesk@upi';
    final amountToPay = _paidAmount > 0 ? _paidAmount : _totalAmount;
    final memberName = _fullnameController.text.trim().isNotEmpty ? _fullnameController.text.trim() : 'New Member';
    final note = 'Member Reg - $memberName';

    final upiPayload = (qrData?.upiPayload != null && qrData!.upiPayload.isNotEmpty)
        ? qrData.upiPayload
        : 'upi://pay?pa=$upiId&pn=${Uri.encodeComponent(gymName)}&am=${amountToPay.toStringAsFixed(2)}&cu=INR&tn=${Uri.encodeComponent(note)}';

    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: const Color(0xFF13131A),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: AppColors.lime.withOpacity(0.4), width: 1.5),
        boxShadow: [
          BoxShadow(
            color: AppColors.lime.withOpacity(0.08),
            blurRadius: 16,
            offset: const Offset(0, 4),
          ),
        ],
      ),
      child: Column(
        children: [
          // Header Badge
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                decoration: BoxDecoration(
                  color: AppColors.lime.withOpacity(0.15),
                  borderRadius: BorderRadius.circular(8),
                  border: Border.all(color: AppColors.lime.withOpacity(0.3)),
                ),
                child: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    const Icon(Icons.qr_code_2_rounded, size: 15, color: AppColors.lime),
                    const SizedBox(width: 5),
                    Text(
                      'SCAN TO PAY VIA UPI',
                      style: GoogleFonts.plusJakartaSans(
                        fontSize: 10.5,
                        fontWeight: FontWeight.w800,
                        color: AppColors.lime,
                        letterSpacing: 0.5,
                      ),
                    ),
                  ],
                ),
              ),
              Text(
                '$currency${amountToPay.toStringAsFixed(0)}',
                style: GoogleFonts.outfit(
                  fontSize: 17,
                  fontWeight: FontWeight.w900,
                  color: AppColors.lime,
                ),
              ),
            ],
          ),
          const SizedBox(height: 14),

          // High-contrast QR Code Frame
          Container(
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(16),
              boxShadow: [
                BoxShadow(
                  color: Colors.black.withOpacity(0.3),
                  blurRadius: 10,
                ),
              ],
            ),
            child: QrImageView(
              data: upiPayload,
              version: QrVersions.auto,
              size: 170,
              backgroundColor: Colors.white,
              eyeStyle: const QrEyeStyle(
                eyeShape: QrEyeShape.square,
                color: Color(0xFF0F172A),
              ),
              dataModuleStyle: const QrDataModuleStyle(
                dataModuleShape: QrDataModuleShape.square,
                color: Color(0xFF0F172A),
              ),
            ),
          ),
          const SizedBox(height: 12),

          // UPI ID & Copy button
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
            decoration: BoxDecoration(
              color: Colors.white.withOpacity(0.04),
              borderRadius: BorderRadius.circular(10),
              border: Border.all(color: Colors.white10),
            ),
            child: Row(
              children: [
                const Icon(Icons.account_balance_rounded, size: 14, color: Colors.white60),
                const SizedBox(width: 8),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text(
                        'Gym UPI ID',
                        style: TextStyle(fontSize: 10, color: Colors.white38),
                      ),
                      Text(
                        upiId,
                        style: const TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: Colors.white),
                        overflow: TextOverflow.ellipsis,
                      ),
                    ],
                  ),
                ),
                InkWell(
                  onTap: () {
                    Clipboard.setData(ClipboardData(text: upiId));
                    ScaffoldMessenger.of(context).showSnackBar(
                      SnackBar(
                        content: Text('UPI ID copied: $upiId'),
                        backgroundColor: AppColors.success,
                        duration: const Duration(seconds: 2),
                      ),
                    );
                  },
                  borderRadius: BorderRadius.circular(6),
                  child: Container(
                    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                    decoration: BoxDecoration(
                      color: AppColors.lime.withOpacity(0.2),
                      borderRadius: BorderRadius.circular(6),
                    ),
                    child: const Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Icon(Icons.copy_rounded, size: 12, color: AppColors.lime),
                        SizedBox(width: 4),
                        Text('Copy', style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: AppColors.lime)),
                      ],
                    ),
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 10),

          // Direct launch button for UPI Apps
          SizedBox(
            width: double.infinity,
            child: OutlinedButton.icon(
              onPressed: () async {
                final uri = Uri.parse(upiPayload);
                if (await canLaunchUrl(uri)) {
                  await launchUrl(uri, mode: LaunchMode.externalApplication);
                } else {
                  if (context.mounted) {
                    ScaffoldMessenger.of(context).showSnackBar(
                      const SnackBar(
                        content: Text('No supported UPI app found. Please scan the QR code using Google Pay, PhonePe, or Paytm.'),
                      ),
                    );
                  }
                }
              },
              style: OutlinedButton.styleFrom(
                foregroundColor: Colors.white,
                side: BorderSide(color: AppColors.lime.withOpacity(0.4)),
                padding: const EdgeInsets.symmetric(vertical: 10),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
              ),
              icon: const Icon(Icons.open_in_new_rounded, size: 14, color: AppColors.lime),
              label: Text(
                'Open UPI App (GPay / PhonePe / Paytm)',
                style: GoogleFonts.plusJakartaSans(
                  fontSize: 11.5,
                  fontWeight: FontWeight.w700,
                  color: Colors.white,
                ),
              ),
            ),
          ),
          const SizedBox(height: 10),

          // UTR / Transaction Ref input
          TextFormField(
            controller: _upiRefController,
            style: const TextStyle(fontSize: 12.5, color: Colors.white),
            decoration: InputDecoration(
              labelText: 'Transaction UTR / Ref ID (Optional)',
              labelStyle: const TextStyle(fontSize: 11, color: Colors.white60),
              hintText: 'e.g. 423985729103',
              hintStyle: const TextStyle(fontSize: 11, color: Colors.white24),
              isDense: true,
              filled: true,
              fillColor: Colors.white.withOpacity(0.04),
              border: OutlineInputBorder(borderRadius: BorderRadius.circular(10), borderSide: BorderSide.none),
              prefixIcon: const Icon(Icons.tag_rounded, size: 16, color: AppColors.lime),
            ),
          ),
          const SizedBox(height: 8),

          // Helpful guide
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Icon(Icons.info_outline_rounded, size: 13, color: Colors.white38),
              const SizedBox(width: 6),
              Expanded(
                child: Text(
                  'Ask member to scan and complete payment of $currency${amountToPay.toStringAsFixed(0)}, then click "Complete Member Registration" below.',
                  style: GoogleFonts.plusJakartaSans(
                    fontSize: 10.5,
                    color: Colors.white54,
                  ),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _sectionHeader(String title) {
    return Text(
      title,
      style: GoogleFonts.plusJakartaSans(
        fontSize: 11.5,
        fontWeight: FontWeight.w800,
        color: Colors.white60,
        letterSpacing: 0.8,
      ),
    );
  }

  Widget _durationChip(int months, String label) {
    final isSelected = _planMonths == months;
    return Expanded(
      child: Padding(
        padding: const EdgeInsets.symmetric(horizontal: 2),
        child: ChoiceChip(
          label: Text(label),
          selected: isSelected,
          materialTapTargetSize: MaterialTapTargetSize.shrinkWrap,
          visualDensity: VisualDensity.compact,
          padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 2),
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
          backgroundColor: const Color(0xFF1E1E2C),
          labelStyle: GoogleFonts.plusJakartaSans(
            fontWeight: FontWeight.w800,
            fontSize: 11.5,
            color: isSelected ? Colors.black : Colors.white70,
          ),
        ),
      ),
    );
  }
}
