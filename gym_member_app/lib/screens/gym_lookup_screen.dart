import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../core/theme/app_colors.dart';
import '../core/theme/theme_provider.dart';
import '../core/storage/secure_storage_service.dart';
import '../providers/auth_provider.dart';
import '../widgets/branded_button.dart';
import '../widgets/fitisify_logo_header.dart';
import 'login_screen.dart';

class GymLookupScreen extends StatefulWidget {
  const GymLookupScreen({super.key});

  @override
  State<GymLookupScreen> createState() => _GymLookupScreenState();
}

class _GymLookupScreenState extends State<GymLookupScreen> {
  final _manualCodeController = TextEditingController();
  final _manualFormKey = GlobalKey<FormState>();

  bool _isInitialLoading = true;
  bool _showManualCodeEntry = false;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      _loadGymData();
    });
  }

  @override
  void dispose() {
    _manualCodeController.dispose();
    super.dispose();
  }

  Future<void> _loadGymData() async {
    setState(() => _isInitialLoading = true);
    final auth = context.read<AuthProvider>();

    // Fetch available gyms directory
    await auth.fetchAvailableGyms();

    // Check if a gym was previously selected and saved
    final savedCode = await SecureStorageService.getCurrentGymCode();
    if (savedCode != null && savedCode.isNotEmpty && auth.currentTenant == null) {
      await auth.lookupGym(savedCode);
    }

    if (mounted) {
      setState(() => _isInitialLoading = false);
    }
  }

  Future<void> _selectGymByCode(String code) async {
    FocusScope.of(context).unfocus();
    final auth = context.read<AuthProvider>();
    final themeProvider = context.read<ThemeProvider>();

    final success = await auth.lookupGym(code);
    if (success && mounted && auth.currentTenant != null) {
      themeProvider.updateBranding(
        primaryHex: auth.currentTenant!.primaryColor,
        secondaryHex: auth.currentTenant!.secondaryColor,
      );
    }
  }

  void _proceedToLogin() {
    if (context.read<AuthProvider>().currentTenant == null) return;
    Navigator.of(context).pushReplacement(
      MaterialPageRoute(builder: (_) => const LoginScreen()),
    );
  }

  void _openSearchableGymModal(List<Map<String, dynamic>> gyms) {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (ctx) => _SearchableGymSelectorModal(
        gyms: gyms,
        selectedGymCode: context.read<AuthProvider>().currentTenant?.gymCode,
        onSelect: (selectedGym) {
          Navigator.pop(ctx);
          final code = (selectedGym['gym_code'] ?? selectedGym['slug'] ?? '').toString();
          if (code.isNotEmpty) {
            _selectGymByCode(code);
          }
        },
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final auth = context.watch<AuthProvider>();
    final isDark = theme.brightness == Brightness.dark;
    final availableGyms = auth.availableGyms;
    final selectedTenant = auth.currentTenant;

    return Scaffold(
      backgroundColor: isDark ? AppColors.darkBg : AppColors.lightBg,
      appBar: AppBar(
        backgroundColor: Colors.transparent,
        elevation: 0,
        surfaceTintColor: Colors.transparent,
        actions: [
          IconButton(
            icon: Icon(
              isDark ? Icons.light_mode_rounded : Icons.dark_mode_rounded,
              color: theme.textTheme.bodyMedium?.color,
            ),
            tooltip: 'Toggle Theme',
            onPressed: () => context.read<ThemeProvider>().toggleDarkMode(),
          ),
          const SizedBox(width: 8),
        ],
      ),
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 16),
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 460),
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  // App Branding Header
                  const Center(
                    child: FitisifyLogoHeader(
                      iconSize: 46,
                      fontSize: 24,
                    ),
                  ),
                  const SizedBox(height: 20),

                  // Title
                  Text(
                    'Welcome to FITISIFY OS',
                    style: theme.textTheme.titleLarge?.copyWith(
                      fontSize: 24,
                      fontWeight: FontWeight.w800,
                      letterSpacing: -0.5,
                    ),
                    textAlign: TextAlign.center,
                  ),
                  const SizedBox(height: 6),
                  Text(
                    'Select your gym to continue',
                    style: theme.textTheme.bodyMedium?.copyWith(
                      fontSize: 13.5,
                      color: theme.textTheme.bodyMedium?.color?.withValues(alpha: 0.75),
                      height: 1.4,
                    ),
                    textAlign: TextAlign.center,
                  ),
                  const SizedBox(height: 28),

                  // Loading State
                  if (_isInitialLoading || (auth.isLoading && selectedTenant == null && availableGyms.isEmpty)) ...[
                    Container(
                      padding: const EdgeInsets.all(32),
                      decoration: BoxDecoration(
                        color: isDark ? AppColors.darkCard : AppColors.lightCard,
                        borderRadius: BorderRadius.circular(20),
                        border: Border.all(
                          color: theme.dividerTheme.color ?? Colors.grey.withValues(alpha: 0.15),
                        ),
                      ),
                      child: Column(
                        children: [
                          SizedBox(
                            width: 36,
                            height: 36,
                            child: CircularProgressIndicator(
                              strokeWidth: 3,
                              valueColor: AlwaysStoppedAnimation<Color>(theme.primaryColor),
                            ),
                          ),
                          const SizedBox(height: 16),
                          Text(
                            'Loading gyms...',
                            style: theme.textTheme.bodyMedium?.copyWith(
                              fontWeight: FontWeight.w600,
                              fontSize: 14,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ]
                  // Error State
                  else if (auth.errorMessage != null && availableGyms.isEmpty) ...[
                    Container(
                      padding: const EdgeInsets.all(24),
                      decoration: BoxDecoration(
                        color: AppColors.danger.withValues(alpha: 0.08),
                        borderRadius: BorderRadius.circular(20),
                        border: Border.all(color: AppColors.danger.withValues(alpha: 0.3)),
                      ),
                      child: Column(
                        children: [
                          const Icon(Icons.wifi_off_rounded, color: AppColors.danger, size: 44),
                          const SizedBox(height: 12),
                          const Text(
                            'Unable to load gyms',
                            style: TextStyle(
                              color: AppColors.danger,
                              fontWeight: FontWeight.w800,
                              fontSize: 17,
                            ),
                          ),
                          const SizedBox(height: 6),
                          Text(
                            auth.errorMessage ?? 'Please check your internet connection and try again.',
                            style: TextStyle(
                              color: theme.textTheme.bodyMedium?.color?.withValues(alpha: 0.8),
                              fontSize: 13,
                              height: 1.35,
                            ),
                            textAlign: TextAlign.center,
                          ),
                          const SizedBox(height: 18),
                          ElevatedButton.icon(
                            onPressed: _loadGymData,
                            icon: const Icon(Icons.refresh_rounded, size: 18),
                            label: const Text('Retry', style: TextStyle(fontWeight: FontWeight.w700)),
                            style: ElevatedButton.styleFrom(
                              backgroundColor: AppColors.danger,
                              foregroundColor: Colors.white,
                              padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 12),
                              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ]
                  // Empty Gym List State
                  else if (availableGyms.isEmpty && !_showManualCodeEntry) ...[
                    Container(
                      padding: const EdgeInsets.all(24),
                      decoration: BoxDecoration(
                        color: isDark ? AppColors.darkCard : AppColors.lightCard,
                        borderRadius: BorderRadius.circular(20),
                        border: Border.all(
                          color: theme.dividerTheme.color ?? Colors.grey.withValues(alpha: 0.2),
                        ),
                      ),
                      child: Column(
                        children: [
                          Icon(Icons.search_off_rounded, size: 44, color: Colors.grey.withValues(alpha: 0.6)),
                          const SizedBox(height: 12),
                          Text(
                            'No gyms available',
                            style: theme.textTheme.titleMedium?.copyWith(
                              fontWeight: FontWeight.w800,
                              fontSize: 17,
                            ),
                          ),
                          const SizedBox(height: 6),
                          Text(
                            'Please contact your gym administrator.',
                            style: theme.textTheme.bodyMedium?.copyWith(
                              fontSize: 13,
                              color: theme.textTheme.bodyMedium?.color?.withValues(alpha: 0.7),
                            ),
                            textAlign: TextAlign.center,
                          ),
                          const SizedBox(height: 18),
                          OutlinedButton.icon(
                            onPressed: _loadGymData,
                            icon: const Icon(Icons.refresh_rounded, size: 18),
                            label: const Text('Retry'),
                            style: OutlinedButton.styleFrom(
                              padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 12),
                              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ]
                  // Main Content: Dropdown & Gym Details Card
                  else ...[
                    if (!_showManualCodeEntry) ...[
                      // Dropdown Field Label
                      Align(
                        alignment: Alignment.centerLeft,
                        child: Text(
                          'Select Your Gym',
                          style: theme.textTheme.titleSmall?.copyWith(
                            fontWeight: FontWeight.w700,
                            fontSize: 13.5,
                            color: theme.textTheme.bodyMedium?.color?.withValues(alpha: 0.85),
                          ),
                        ),
                      ),
                      const SizedBox(height: 8),

                      // Select Your Gym Dropdown Button
                      GestureDetector(
                        onTap: auth.isLoading ? null : () => _openSearchableGymModal(availableGyms),
                        child: AnimatedContainer(
                          duration: const Duration(milliseconds: 200),
                          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
                          decoration: BoxDecoration(
                            color: isDark ? AppColors.darkCard : AppColors.lightCard,
                            borderRadius: BorderRadius.circular(16),
                            border: Border.all(
                              color: selectedTenant != null
                                  ? theme.primaryColor
                                  : (theme.dividerTheme.color ?? Colors.grey.withValues(alpha: 0.3)),
                              width: selectedTenant != null ? 1.8 : 1.2,
                            ),
                            boxShadow: [
                              BoxShadow(
                                color: selectedTenant != null
                                    ? theme.primaryColor.withValues(alpha: 0.1)
                                    : Colors.black.withValues(alpha: 0.03),
                                blurRadius: 10,
                                offset: const Offset(0, 3),
                              ),
                            ],
                          ),
                          child: Row(
                            children: [
                              // Left Icon
                              Container(
                                padding: const EdgeInsets.all(9),
                                decoration: BoxDecoration(
                                  color: theme.primaryColor.withValues(alpha: 0.12),
                                  borderRadius: BorderRadius.circular(10),
                                ),
                                child: Icon(
                                  Icons.fitness_center_rounded,
                                  color: theme.primaryColor,
                                  size: 20,
                                ),
                              ),
                              const SizedBox(width: 14),

                              // Text content
                              Expanded(
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Text(
                                      selectedTenant != null ? selectedTenant.gymName : 'Choose your gym',
                                      style: theme.textTheme.titleMedium?.copyWith(
                                        fontWeight: selectedTenant != null ? FontWeight.w700 : FontWeight.w500,
                                        fontSize: 15,
                                        color: selectedTenant != null
                                            ? theme.textTheme.titleMedium?.color
                                            : theme.textTheme.bodyMedium?.color?.withValues(alpha: 0.5),
                                      ),
                                    ),
                                    if (selectedTenant != null) ...[
                                      const SizedBox(height: 2),
                                      Text(
                                        selectedTenant.address.isNotEmpty
                                            ? selectedTenant.address
                                            : 'Code: ${selectedTenant.gymCode}',
                                        style: theme.textTheme.bodyMedium?.copyWith(
                                          fontSize: 12,
                                          color: theme.textTheme.bodyMedium?.color?.withValues(alpha: 0.65),
                                        ),
                                        maxLines: 1,
                                        overflow: TextOverflow.ellipsis,
                                      ),
                                    ],
                                  ],
                                ),
                              ),
                              const SizedBox(width: 8),

                              // Dropdown Arrow or Spinner
                              auth.isLoading
                                  ? SizedBox(
                                      width: 20,
                                      height: 20,
                                      child: CircularProgressIndicator(
                                        strokeWidth: 2,
                                        valueColor: AlwaysStoppedAnimation<Color>(theme.primaryColor),
                                      ),
                                    )
                                  : Icon(
                                      Icons.keyboard_arrow_down_rounded,
                                      color: theme.primaryColor,
                                      size: 26,
                                    ),
                            ],
                          ),
                        ),
                      ),
                    ],

                    // Manual Code Entry Form (Fallback Option)
                    if (_showManualCodeEntry) ...[
                      Form(
                        key: _manualFormKey,
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            TextFormField(
                              controller: _manualCodeController,
                              textCapitalization: TextCapitalization.characters,
                              autocorrect: false,
                              decoration: InputDecoration(
                                labelText: 'Gym Code',
                                hintText: 'Enter gym code (e.g. GYM-KHAN)',
                                prefixIcon: const Icon(Icons.vpn_key_rounded),
                                border: OutlineInputBorder(borderRadius: BorderRadius.circular(14)),
                                filled: true,
                                fillColor: isDark ? AppColors.darkCard : AppColors.lightCard,
                              ),
                              validator: (value) {
                                if (value == null || value.trim().isEmpty) {
                                  return 'Please enter your Gym Code';
                                }
                                return null;
                              },
                              onFieldSubmitted: (val) {
                                if (_manualFormKey.currentState!.validate()) {
                                  _selectGymByCode(val.trim());
                                }
                              },
                            ),
                            const SizedBox(height: 12),
                            BrandedButton(
                              label: 'Verify Gym Code',
                              icon: Icons.search_rounded,
                              isLoading: auth.isLoading,
                              onPressed: () {
                                if (_manualFormKey.currentState!.validate()) {
                                  _selectGymByCode(_manualCodeController.text.trim());
                                }
                              },
                            ),
                          ],
                        ),
                      ),
                    ],

                    // Inline Error Alert (if lookup fails after tap)
                    if (auth.errorMessage != null && availableGyms.isNotEmpty) ...[
                      const SizedBox(height: 16),
                      Container(
                        padding: const EdgeInsets.all(12),
                        decoration: BoxDecoration(
                          color: AppColors.danger.withValues(alpha: 0.1),
                          borderRadius: BorderRadius.circular(12),
                          border: Border.all(color: AppColors.danger.withValues(alpha: 0.3)),
                        ),
                        child: Row(
                          children: [
                            const Icon(Icons.error_outline_rounded, color: AppColors.danger, size: 20),
                            const SizedBox(width: 10),
                            Expanded(
                              child: Text(
                                auth.errorMessage!,
                                style: const TextStyle(
                                  color: AppColors.danger,
                                  fontWeight: FontWeight.w600,
                                  fontSize: 12.5,
                                ),
                              ),
                            ),
                          ],
                        ),
                      ),
                    ],

                    // Gym Information Card (Displays when a gym is selected)
                    if (selectedTenant != null) ...[
                      const SizedBox(height: 20),
                      AnimatedContainer(
                        duration: const Duration(milliseconds: 300),
                        padding: const EdgeInsets.all(20),
                        decoration: BoxDecoration(
                          color: isDark ? AppColors.darkCard : AppColors.lightCard,
                          borderRadius: BorderRadius.circular(20),
                          border: Border.all(
                            color: theme.primaryColor.withValues(alpha: 0.35),
                            width: 1.5,
                          ),
                          boxShadow: [
                            BoxShadow(
                              color: theme.primaryColor.withValues(alpha: 0.1),
                              blurRadius: 16,
                              offset: const Offset(0, 6),
                            ),
                          ],
                        ),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Row(
                              children: [
                                // Gym Logo / Avatar
                                Container(
                                  width: 56,
                                  height: 56,
                                  decoration: BoxDecoration(
                                    color: theme.primaryColor.withValues(alpha: 0.12),
                                    borderRadius: BorderRadius.circular(14),
                                    border: Border.all(color: theme.primaryColor.withValues(alpha: 0.2)),
                                  ),
                                  child: selectedTenant.logo != null && selectedTenant.logo!.isNotEmpty
                                      ? ClipRRect(
                                          borderRadius: BorderRadius.circular(14),
                                          child: Image.network(
                                            selectedTenant.logo!,
                                            fit: BoxFit.contain,
                                            errorBuilder: (ctx, err, stack) => Icon(
                                              Icons.fitness_center_rounded,
                                              color: theme.primaryColor,
                                              size: 28,
                                            ),
                                          ),
                                        )
                                      : Icon(
                                          Icons.fitness_center_rounded,
                                          color: theme.primaryColor,
                                          size: 28,
                                        ),
                                ),
                                const SizedBox(width: 14),

                                // Gym Details
                                Expanded(
                                  child: Column(
                                    crossAxisAlignment: CrossAxisAlignment.start,
                                    children: [
                                      Text(
                                        selectedTenant.gymName,
                                        style: theme.textTheme.titleMedium?.copyWith(
                                          fontWeight: FontWeight.w800,
                                          fontSize: 16.5,
                                        ),
                                        maxLines: 2,
                                        overflow: TextOverflow.ellipsis,
                                      ),
                                      const SizedBox(height: 4),
                                      Container(
                                        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                                        decoration: BoxDecoration(
                                          color: AppColors.success.withValues(alpha: 0.14),
                                          borderRadius: BorderRadius.circular(6),
                                        ),
                                        child: Text(
                                          'Verified Club • ${selectedTenant.gymCode}',
                                          style: const TextStyle(
                                            color: AppColors.success,
                                            fontWeight: FontWeight.w800,
                                            fontSize: 11.5,
                                          ),
                                        ),
                                      ),
                                    ],
                                  ),
                                ),
                                const SizedBox(width: 8),
                                const Icon(
                                  Icons.check_circle_rounded,
                                  color: AppColors.success,
                                  size: 26,
                                ),
                              ],
                            ),

                            // Address / Location (if returned by API)
                            if (selectedTenant.address.isNotEmpty) ...[
                              const SizedBox(height: 14),
                              Divider(color: theme.dividerTheme.color ?? Colors.grey.withValues(alpha: 0.15)),
                              const SizedBox(height: 8),
                              Row(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Icon(
                                    Icons.location_on_outlined,
                                    size: 16,
                                    color: theme.textTheme.bodyMedium?.color?.withValues(alpha: 0.6),
                                  ),
                                  const SizedBox(width: 6),
                                  Expanded(
                                    child: Text(
                                      selectedTenant.address,
                                      style: theme.textTheme.bodyMedium?.copyWith(
                                        fontSize: 12.5,
                                        color: theme.textTheme.bodyMedium?.color?.withValues(alpha: 0.8),
                                      ),
                                      maxLines: 2,
                                      overflow: TextOverflow.ellipsis,
                                    ),
                                  ),
                                ],
                              ),
                            ],
                          ],
                        ),
                      ),
                    ],

                    const SizedBox(height: 24),

                    // Main Action: Continue Button
                    BrandedButton(
                      label: 'Continue',
                      icon: Icons.arrow_forward_rounded,
                      onPressed: selectedTenant != null ? _proceedToLogin : null,
                    ),

                    const SizedBox(height: 16),

                    // Manual Code Toggle Link
                    Center(
                      child: TextButton.icon(
                        onPressed: () {
                          setState(() {
                            _showManualCodeEntry = !_showManualCodeEntry;
                          });
                        },
                        icon: Icon(
                          _showManualCodeEntry ? Icons.list_alt_rounded : Icons.vpn_key_rounded,
                          size: 16,
                        ),
                        label: Text(
                          _showManualCodeEntry ? 'Choose from Gym List' : 'Enter Gym Pass Code manually',
                          style: TextStyle(
                            fontSize: 12.5,
                            fontWeight: FontWeight.w600,
                            color: theme.primaryColor,
                          ),
                        ),
                      ),
                    ),
                  ],
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}

// Searchable Gym Selector Modal Bottom Sheet
class _SearchableGymSelectorModal extends StatefulWidget {
  final List<Map<String, dynamic>> gyms;
  final String? selectedGymCode;
  final ValueChanged<Map<String, dynamic>> onSelect;

  const _SearchableGymSelectorModal({
    required this.gyms,
    this.selectedGymCode,
    required this.onSelect,
  });

  @override
  State<_SearchableGymSelectorModal> createState() => _SearchableGymSelectorModalState();
}

class _SearchableGymSelectorModalState extends State<_SearchableGymSelectorModal> {
  final _searchController = TextEditingController();
  List<Map<String, dynamic>> _filteredGyms = [];

  @override
  void initState() {
    super.initState();
    _filteredGyms = widget.gyms;
  }

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  void _filterGyms(String query) {
    final q = query.toLowerCase().trim();
    setState(() {
      if (q.isEmpty) {
        _filteredGyms = widget.gyms;
      } else {
        _filteredGyms = widget.gyms.where((g) {
          final name = (g['gym_name'] ?? '').toString().toLowerCase();
          final code = (g['gym_code'] ?? g['slug'] ?? '').toString().toLowerCase();
          final city = (g['city'] ?? '').toString().toLowerCase();
          final address = (g['address'] ?? '').toString().toLowerCase();
          return name.contains(q) || code.contains(q) || city.contains(q) || address.contains(q);
        }).toList();
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final isDark = theme.brightness == Brightness.dark;
    final bottomInset = MediaQuery.of(context).viewInsets.bottom;

    return Container(
      constraints: BoxConstraints(
        maxHeight: MediaQuery.of(context).size.height * 0.82,
      ),
      padding: EdgeInsets.only(bottom: bottomInset),
      decoration: BoxDecoration(
        color: theme.scaffoldBackgroundColor,
        borderRadius: const BorderRadius.vertical(top: Radius.circular(24)),
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          // Drag handle indicator
          Center(
            child: Container(
              margin: const EdgeInsets.only(top: 12, bottom: 8),
              width: 38,
              height: 4,
              decoration: BoxDecoration(
                color: Colors.grey.withValues(alpha: 0.3),
                borderRadius: BorderRadius.circular(2),
              ),
            ),
          ),

          // Modal Title Bar
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 8),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text(
                  'Select Your Gym',
                  style: theme.textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.w800,
                    fontSize: 18,
                  ),
                ),
                IconButton(
                  icon: const Icon(Icons.close_rounded),
                  onPressed: () => Navigator.pop(context),
                  visualDensity: VisualDensity.compact,
                ),
              ],
            ),
          ),

          // Search Field
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 4),
            child: TextField(
              controller: _searchController,
              autofocus: false,
              decoration: InputDecoration(
                hintText: 'Search gym name, code, or city...',
                prefixIcon: const Icon(Icons.search_rounded),
                suffixIcon: _searchController.text.isNotEmpty
                    ? IconButton(
                        icon: const Icon(Icons.clear_rounded),
                        onPressed: () {
                          _searchController.clear();
                          _filterGyms('');
                        },
                      )
                    : null,
                filled: true,
                fillColor: isDark ? AppColors.darkCard : AppColors.lightCard,
                border: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(14),
                  borderSide: BorderSide.none,
                ),
                contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
              ),
              onChanged: _filterGyms,
            ),
          ),
          const SizedBox(height: 12),

          // Gyms List
          Expanded(
            child: _filteredGyms.isEmpty
                ? Center(
                    child: Padding(
                      padding: const EdgeInsets.all(24),
                      child: Column(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          Icon(Icons.search_off_rounded, size: 48, color: Colors.grey.withValues(alpha: 0.5)),
                          const SizedBox(height: 12),
                          Text(
                            'No gyms found matching "${_searchController.text}"',
                            style: theme.textTheme.bodyMedium?.copyWith(color: Colors.grey),
                            textAlign: TextAlign.center,
                          ),
                        ],
                      ),
                    ),
                  )
                : ListView.separated(
                    padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 8),
                    itemCount: _filteredGyms.length,
                    separatorBuilder: (ctx, idx) => const SizedBox(height: 10),
                    itemBuilder: (context, index) {
                      final gym = _filteredGyms[index];
                      final name = (gym['gym_name'] ?? 'Gym').toString();
                      final code = (gym['gym_code'] ?? gym['slug'] ?? 'GYM').toString();
                      final address = (gym['address'] ?? gym['city'] ?? '').toString();
                      final logo = gym['logo'];
                      final isSelected = widget.selectedGymCode != null &&
                          widget.selectedGymCode!.toLowerCase() == code.toLowerCase();

                      return InkWell(
                        onTap: () => widget.onSelect(gym),
                        borderRadius: BorderRadius.circular(16),
                        child: Container(
                          padding: const EdgeInsets.all(14),
                          decoration: BoxDecoration(
                            color: isSelected
                                ? theme.primaryColor.withValues(alpha: 0.08)
                                : (isDark ? AppColors.darkCard : AppColors.lightCard),
                            borderRadius: BorderRadius.circular(16),
                            border: Border.all(
                              color: isSelected
                                  ? theme.primaryColor
                                  : (theme.dividerTheme.color ?? Colors.grey.withValues(alpha: 0.2)),
                              width: isSelected ? 1.6 : 1.0,
                            ),
                          ),
                          child: Row(
                            children: [
                              // Gym Avatar / Logo
                              Container(
                                width: 44,
                                height: 44,
                                decoration: BoxDecoration(
                                  color: theme.primaryColor.withValues(alpha: 0.12),
                                  borderRadius: BorderRadius.circular(12),
                                ),
                                child: logo != null && logo.toString().isNotEmpty
                                    ? ClipRRect(
                                        borderRadius: BorderRadius.circular(12),
                                        child: Image.network(
                                          logo.toString(),
                                          fit: BoxFit.contain,
                                          errorBuilder: (ctx, err, stack) => Icon(
                                            Icons.fitness_center_rounded,
                                            color: theme.primaryColor,
                                            size: 22,
                                          ),
                                        ),
                                      )
                                    : Icon(
                                        Icons.fitness_center_rounded,
                                        color: theme.primaryColor,
                                        size: 22,
                                      ),
                              ),
                              const SizedBox(width: 14),

                              // Name & Address
                              Expanded(
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Text(
                                      name,
                                      style: TextStyle(
                                        fontWeight: FontWeight.w700,
                                        fontSize: 14.5,
                                        color: isSelected ? theme.primaryColor : null,
                                      ),
                                    ),
                                    const SizedBox(height: 2),
                                    Text(
                                      address.isNotEmpty ? address : 'Code: $code',
                                      style: theme.textTheme.bodyMedium?.copyWith(
                                        fontSize: 12,
                                        color: theme.textTheme.bodyMedium?.color?.withValues(alpha: 0.65),
                                      ),
                                      maxLines: 1,
                                      overflow: TextOverflow.ellipsis,
                                    ),
                                  ],
                                ),
                              ),
                              const SizedBox(width: 8),

                              // Code Badge
                              Container(
                                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                                decoration: BoxDecoration(
                                  color: theme.primaryColor.withValues(alpha: 0.12),
                                  borderRadius: BorderRadius.circular(8),
                                ),
                                child: Text(
                                  code,
                                  style: TextStyle(
                                    fontWeight: FontWeight.w800,
                                    fontSize: 11.5,
                                    color: theme.primaryColor,
                                  ),
                                ),
                              ),
                            ],
                          ),
                        ),
                      );
                    },
                  ),
          ),
        ],
      ),
    );
  }
}
