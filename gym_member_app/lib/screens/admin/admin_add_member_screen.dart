import 'dart:io';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import 'package:intl/intl.dart';
import 'package:provider/provider.dart';
import '../../providers/admin_provider.dart';
import '../../providers/auth_provider.dart';

class AdminAddMemberScreen extends StatefulWidget {
  const AdminAddMemberScreen({super.key});

  @override
  State<AdminAddMemberScreen> createState() => _AdminAddMemberScreenState();
}

class _AdminAddMemberScreenState extends State<AdminAddMemberScreen> {
  final _formKey = GlobalKey<FormState>();

  final _fullnameController = TextEditingController();
  final _phoneController = TextEditingController();
  final _emailController = TextEditingController();
  final _addressController = TextEditingController();

  final _totalAmountController = TextEditingController(text: '1000');
  final _paidAmountController = TextEditingController(text: '1000');

  String _selectedGender = 'Male';
  String _selectedService = 'General Fitness';
  int _selectedPlanMonths = 1;
  double _monthlyRate = 1000.0;
  DateTime? _selectedDueDate;
  String _paymentMethod = 'Cash';

  // Photo
  File? _photoFile;
  Uint8List? _photoBytes;
  String? _photoFileName;
  final ImagePicker _picker = ImagePicker();

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<AdminProvider>().loadRates();
    });
  }

  @override
  void dispose() {
    _fullnameController.dispose();
    _phoneController.dispose();
    _emailController.dispose();
    _addressController.dispose();
    _totalAmountController.dispose();
    _paidAmountController.dispose();
    super.dispose();
  }

  void _recalculateTotals() {
    final total = _monthlyRate * _selectedPlanMonths;
    _totalAmountController.text = total.toStringAsFixed(0);
    // If paid was previously matching total, keep it matching, else keep user amount
    _paidAmountController.text = total.toStringAsFixed(0);
    setState(() {});
  }

  Future<void> _pickImage(ImageSource source) async {
    try {
      final picked = await _picker.pickImage(
        source: source,
        maxWidth: 800,
        maxHeight: 800,
        imageQuality: 85,
      );

      if (picked != null) {
        if (kIsWeb) {
          final bytes = await picked.readAsBytes();
          setState(() {
            _photoBytes = bytes;
            _photoFileName = picked.name;
            _photoFile = null;
          });
        } else {
          setState(() {
            _photoFile = File(picked.path);
            _photoBytes = null;
            _photoFileName = picked.name;
          });
        }
      }
    } catch (e) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Could not access camera/gallery: $e')),
      );
    }
  }

  void _showImageSourceDialog() {
    showModalBottomSheet(
      context: context,
      shape: const RoundedRectangleBorder(borderRadius: BorderRadius.vertical(top: Radius.circular(20))),
      builder: (ctx) => SafeArea(
        child: Wrap(
          children: [
            ListTile(
              leading: const Icon(Icons.camera_alt, color: Color(0xFF3B82F6)),
              title: const Text('Take Live Photo with Camera'),
              onTap: () {
                Navigator.pop(ctx);
                _pickImage(ImageSource.camera);
              },
            ),
            ListTile(
              leading: const Icon(Icons.photo_library, color: Color(0xFF10B981)),
              title: const Text('Choose Photo from Gallery'),
              onTap: () {
                Navigator.pop(ctx);
                _pickImage(ImageSource.gallery);
              },
            ),
          ],
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final admin = context.watch<AdminProvider>();
    final auth = context.watch<AuthProvider>();
    final currency = auth.currentTenant?.currency ?? '₹';

    final totalAmount = double.tryParse(_totalAmountController.text.trim()) ?? 0.0;
    final paidAmount = double.tryParse(_paidAmountController.text.trim()) ?? 0.0;
    final dueAmount = (totalAmount - paidAmount).clamp(0.0, 999999.0);

    return Scaffold(
      appBar: AppBar(
        title: const Text('Add New Member', style: TextStyle(fontWeight: FontWeight.bold)),
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16),
        child: Form(
          key: _formKey,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // --- 1. PHOTO CAPTURE AVATAR ---
              Center(
                child: Stack(
                  children: [
                    InkWell(
                      onTap: _showImageSourceDialog,
                      borderRadius: BorderRadius.circular(50),
                      child: Container(
                        width: 100,
                        height: 100,
                        decoration: BoxDecoration(
                          color: const Color(0xFF3B82F6).withOpacity(0.12),
                          shape: BoxShape.circle,
                          border: Border.all(color: const Color(0xFF3B82F6), width: 2),
                        ),
                        child: ClipOval(
                          child: _photoBytes != null
                              ? Image.memory(_photoBytes!, fit: BoxFit.cover)
                              : _photoFile != null
                                  ? Image.file(_photoFile!, fit: BoxFit.cover)
                                  : const Column(
                                      mainAxisAlignment: MainAxisAlignment.center,
                                      children: [
                                        Icon(Icons.camera_alt, size: 34, color: Color(0xFF3B82F6)),
                                        SizedBox(height: 4),
                                        Text('Add Photo', style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: Color(0xFF3B82F6))),
                                      ],
                                    ),
                        ),
                      ),
                    ),
                    Positioned(
                      bottom: 0,
                      right: 0,
                      child: InkWell(
                        onTap: _showImageSourceDialog,
                        child: Container(
                          padding: const EdgeInsets.all(6),
                          decoration: const BoxDecoration(
                            color: Color(0xFF3B82F6),
                            shape: BoxShape.circle,
                          ),
                          child: const Icon(Icons.edit, size: 16, color: Colors.white),
                        ),
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 8),
              const Center(
                child: Text(
                  'One-time Customer Photo (Distinguish same-name clients)',
                  style: TextStyle(fontSize: 12, color: Colors.grey),
                ),
              ),

              const SizedBox(height: 24),

              // --- 2. BASIC DETAILS ---
              Text('Personal Details', style: theme.textTheme.titleMedium?.copyWith(fontWeight: FontWeight.bold)),
              const SizedBox(height: 12),

              TextFormField(
                controller: _fullnameController,
                decoration: InputDecoration(
                  labelText: 'Full Name *',
                  prefixIcon: const Icon(Icons.person),
                  border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
                  filled: true,
                  fillColor: theme.cardColor,
                ),
                validator: (val) => (val == null || val.trim().isEmpty) ? 'Please enter customer full name' : null,
              ),
              const SizedBox(height: 12),

              TextFormField(
                controller: _phoneController,
                keyboardType: TextInputType.phone,
                decoration: InputDecoration(
                  labelText: 'Mobile Phone Number *',
                  prefixIcon: const Icon(Icons.phone),
                  border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
                  filled: true,
                  fillColor: theme.cardColor,
                ),
                validator: (val) => (val == null || val.trim().length < 10) ? 'Please enter 10-digit mobile number' : null,
              ),
              const SizedBox(height: 12),

              TextFormField(
                controller: _addressController,
                decoration: InputDecoration(
                  labelText: 'Address / Area (Optional)',
                  prefixIcon: const Icon(Icons.location_on),
                  border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
                  filled: true,
                  fillColor: theme.cardColor,
                ),
              ),
              const SizedBox(height: 12),

              DropdownButtonFormField<String>(
                value: _selectedGender,
                decoration: InputDecoration(
                  labelText: 'Gender',
                  prefixIcon: const Icon(Icons.wc),
                  border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
                  filled: true,
                  fillColor: theme.cardColor,
                ),
                items: const [
                  DropdownMenuItem(value: 'Male', child: Text('Male')),
                  DropdownMenuItem(value: 'Female', child: Text('Female')),
                  DropdownMenuItem(value: 'Other', child: Text('Other')),
                ],
                onChanged: (val) => setState(() => _selectedGender = val ?? 'Male'),
              ),

              const SizedBox(height: 24),

              // --- 3. MEMBERSHIP PACKAGE & DURATION ---
              Text('Membership Package', style: theme.textTheme.titleMedium?.copyWith(fontWeight: FontWeight.bold)),
              const SizedBox(height: 12),

              DropdownButtonFormField<String>(
                value: admin.rates.any((r) => r['name'] == _selectedService)
                    ? _selectedService
                    : (admin.rates.isNotEmpty ? admin.rates[0]['name'].toString() : 'General Fitness'),
                decoration: InputDecoration(
                  labelText: 'Service / Package',
                  prefixIcon: const Icon(Icons.fitness_center),
                  border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
                  filled: true,
                  fillColor: theme.cardColor,
                ),
                items: (admin.rates.isNotEmpty
                        ? admin.rates
                        : [
                            {'name': 'General Fitness', 'charge': 1000.0},
                            {'name': 'Strength & Cardio', 'charge': 1500.0},
                            {'name': 'Personal Training', 'charge': 3000.0},
                          ])
                    .map<DropdownMenuItem<String>>((r) {
                  final name = r['name'].toString();
                  final charge = (r['charge'] as num).toDouble();
                  return DropdownMenuItem(
                    value: name,
                    child: Text('$name ($currency${charge.toStringAsFixed(0)}/mo)'),
                  );
                }).toList(),
                onChanged: (val) {
                  if (val != null) {
                    _selectedService = val;
                    final match = admin.rates.firstWhere(
                      (r) => r['name'] == val,
                      orElse: () => {'charge': 1000.0},
                    );
                    _monthlyRate = (match['charge'] as num).toDouble();
                    _recalculateTotals();
                  }
                },
              ),
              const SizedBox(height: 12),

              DropdownButtonFormField<int>(
                value: _selectedPlanMonths,
                decoration: InputDecoration(
                  labelText: 'Duration (Months)',
                  prefixIcon: const Icon(Icons.calendar_month),
                  border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
                  filled: true,
                  fillColor: theme.cardColor,
                ),
                items: const [
                  DropdownMenuItem(value: 1, child: Text('1 Month')),
                  DropdownMenuItem(value: 3, child: Text('3 Months (Quarterly)')),
                  DropdownMenuItem(value: 6, child: Text('6 Months (Half-Yearly)')),
                  DropdownMenuItem(value: 12, child: Text('12 Months (1 Year)')),
                ],
                onChanged: (val) {
                  if (val != null) {
                    _selectedPlanMonths = val;
                    _recalculateTotals();
                  }
                },
              ),

              const SizedBox(height: 24),

              // --- 4. FINANCIALS & PARTIAL DUES BREAKDOWN ---
              Text('Payment & Dues (Khata)', style: theme.textTheme.titleMedium?.copyWith(fontWeight: FontWeight.bold)),
              const SizedBox(height: 12),

              Row(
                children: [
                  Expanded(
                    child: TextFormField(
                      controller: _totalAmountController,
                      keyboardType: const TextInputType.numberWithOptions(decimal: true),
                      decoration: InputDecoration(
                        labelText: 'Total Plan Fee ($currency) *',
                        border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
                        filled: true,
                        fillColor: theme.cardColor,
                      ),
                      onChanged: (_) => setState(() {}),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: TextFormField(
                      controller: _paidAmountController,
                      keyboardType: const TextInputType.numberWithOptions(decimal: true),
                      style: const TextStyle(fontWeight: FontWeight.bold, color: Color(0xFF10B981)),
                      decoration: InputDecoration(
                        labelText: 'Amount Paid ($currency) *',
                        border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
                        filled: true,
                        fillColor: theme.cardColor,
                      ),
                      onChanged: (_) => setState(() {}),
                    ),
                  ),
                ],
              ),

              const SizedBox(height: 12),

              // Due Amount Auto-Display Box
              Container(
                padding: const EdgeInsets.all(14),
                decoration: BoxDecoration(
                  color: dueAmount > 0 ? const Color(0xFFEF4444).withOpacity(0.08) : const Color(0xFF10B981).withOpacity(0.08),
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(
                    color: dueAmount > 0 ? const Color(0xFFEF4444).withOpacity(0.3) : const Color(0xFF10B981).withOpacity(0.3),
                  ),
                ),
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Text(
                      dueAmount > 0 ? '⚠️ REMAINING DUE AMOUNT:' : '✓ PAYMENT STATUS:',
                      style: TextStyle(
                        fontWeight: FontWeight.bold,
                        color: dueAmount > 0 ? const Color(0xFFEF4444) : const Color(0xFF10B981),
                        fontSize: 13,
                      ),
                    ),
                    Text(
                      dueAmount > 0 ? '$currency${dueAmount.toStringAsFixed(2)}' : 'FULL PAID (0 DUES)',
                      style: TextStyle(
                        fontWeight: FontWeight.w900,
                        color: dueAmount > 0 ? const Color(0xFFEF4444) : const Color(0xFF10B981),
                        fontSize: 16,
                      ),
                    ),
                  ],
                ),
              ),

              if (dueAmount > 0) ...[
                const SizedBox(height: 12),
                InkWell(
                  onTap: () async {
                    final picked = await showDatePicker(
                      context: context,
                      initialDate: DateTime.now().add(const Duration(days: 7)),
                      firstDate: DateTime.now(),
                      lastDate: DateTime.now().add(const Duration(days: 365)),
                    );
                    if (picked != null) {
                      setState(() => _selectedDueDate = picked);
                    }
                  },
                  child: Container(
                    padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 14),
                    decoration: BoxDecoration(
                      color: theme.cardColor,
                      borderRadius: BorderRadius.circular(12),
                      border: Border.all(color: Colors.grey.withOpacity(0.2)),
                    ),
                    child: Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        Text(
                          _selectedDueDate != null
                              ? 'Due Promise Date: ${DateFormat('yyyy-MM-dd').format(_selectedDueDate!)}'
                              : 'Select Expected Due Date (e.g. 7 days)',
                          style: TextStyle(color: _selectedDueDate != null ? const Color(0xFFEF4444) : Colors.grey[700], fontWeight: FontWeight.bold),
                        ),
                        const Icon(Icons.calendar_today, size: 18),
                      ],
                    ),
                  ),
                ),
              ],

              const SizedBox(height: 16),

              DropdownButtonFormField<String>(
                value: _paymentMethod,
                decoration: InputDecoration(
                  labelText: 'Payment Method',
                  prefixIcon: const Icon(Icons.payments),
                  border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
                  filled: true,
                  fillColor: theme.cardColor,
                ),
                items: const [
                  DropdownMenuItem(value: 'Cash', child: Text('Cash')),
                  DropdownMenuItem(value: 'UPI', child: Text('UPI / QR')),
                  DropdownMenuItem(value: 'Card', child: Text('Card / POS')),
                ],
                onChanged: (val) => setState(() => _paymentMethod = val ?? 'Cash'),
              ),

              const SizedBox(height: 28),

              // --- 5. SUBMIT BUTTON ---
              SizedBox(
                width: double.infinity,
                height: 52,
                child: ElevatedButton.icon(
                  style: ElevatedButton.styleFrom(
                    backgroundColor: const Color(0xFF3B82F6),
                    foregroundColor: Colors.white,
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
                  ),
                  icon: admin.isSubmitting
                      ? const SizedBox(
                          width: 20,
                          height: 20,
                          child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2),
                        )
                      : const Icon(Icons.person_add_alt_1),
                  label: Text(
                    admin.isSubmitting ? 'Registering Member...' : 'REGISTER MEMBER & SAVE',
                    style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
                  ),
                  onPressed: admin.isSubmitting
                      ? null
                      : () async {
                          if (!_formKey.currentState!.validate()) return;

                          final res = await admin.addMember(
                            fullname: _fullnameController.text.trim(),
                            phone: _phoneController.text.trim(),
                            email: _emailController.text.trim(),
                            address: _addressController.text.trim(),
                            gender: _selectedGender,
                            services: _selectedService,
                            planMonths: _selectedPlanMonths,
                            totalAmount: totalAmount,
                            paidAmount: paidAmount,
                            dueAmount: dueAmount,
                            dueDate: _selectedDueDate != null
                                ? DateFormat('yyyy-MM-dd').format(_selectedDueDate!)
                                : null,
                            paymentMethod: _paymentMethod,
                            photoFile: _photoFile,
                            photoBytes: _photoBytes,
                            photoFileName: _photoFileName,
                          );

                          if (res != null && mounted) {
                            ScaffoldMessenger.of(context).showSnackBar(
                              SnackBar(
                                content: Text('Member "${res['fullname']}" registered with Invoice #${res['invoice_number']}!'),
                                backgroundColor: const Color(0xFF10B981),
                              ),
                            );
                            Navigator.pop(context);
                          } else if (admin.errorMessage != null && mounted) {
                            ScaffoldMessenger.of(context).showSnackBar(
                              SnackBar(
                                content: Text(admin.errorMessage!),
                                backgroundColor: const Color(0xFFEF4444),
                              ),
                            );
                          }
                        },
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
