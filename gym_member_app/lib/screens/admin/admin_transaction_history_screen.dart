import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:intl/intl.dart';
import 'package:provider/provider.dart';
import '../../core/theme/app_colors.dart';
import '../../core/services/pdf_service.dart';
import '../../models/admin_transaction_models.dart';
import '../../providers/admin_provider.dart';
import '../../providers/auth_provider.dart';

class AdminTransactionHistoryScreen extends StatefulWidget {
  const AdminTransactionHistoryScreen({super.key});

  @override
  State<AdminTransactionHistoryScreen> createState() => _AdminTransactionHistoryScreenState();
}

class _AdminTransactionHistoryScreenState extends State<AdminTransactionHistoryScreen> {
  final TextEditingController _searchController = TextEditingController();
  final ScrollController _scrollController = ScrollController();

  String _selectedDateFilter = 'all';
  String? _customStartDate;
  String? _customEndDate;
  String _selectedPaymentMode = 'all';
  String _selectedPaymentStatus = 'all';
  String _selectedStaffId = 'all';

  bool _showFilters = false;

  final currencyFormatter = NumberFormat.currency(locale: 'en_IN', symbol: '₹', decimalDigits: 2);

  @override
  void initState() {
    super.initState();
    _scrollController.addListener(_onScroll);
    WidgetsBinding.instance.addPostFrameCallback((_) {
      _fetchTransactions(resetPage: true);
    });
  }

  @override
  void dispose() {
    _searchController.dispose();
    _scrollController.dispose();
    super.dispose();
  }

  void _onScroll() {
    if (_scrollController.position.pixels >= _scrollController.position.maxScrollExtent - 200) {
      final provider = Provider.of<AdminProvider>(context, listen: false);
      if (provider.txnHasMore && !provider.isTransactionsPaginationLoading && !provider.isTransactionsLoading) {
        _fetchTransactions(resetPage: false);
      }
    }
  }

  void _fetchTransactions({bool resetPage = true}) {
    final provider = Provider.of<AdminProvider>(context, listen: false);
    final nextPage = resetPage ? 1 : provider.txnCurrentPage + 1;

    provider.fetchTransactions(
      search: _searchController.text.trim(),
      dateFilter: _selectedDateFilter,
      startDate: _customStartDate,
      endDate: _customEndDate,
      paymentMode: _selectedPaymentMode,
      paymentStatus: _selectedPaymentStatus,
      staffId: _selectedStaffId,
      page: nextPage,
      isRefresh: resetPage,
    );
  }

  void _resetFilters() {
    setState(() {
      _selectedDateFilter = 'all';
      _customStartDate = null;
      _customEndDate = null;
      _selectedPaymentMode = 'all';
      _selectedPaymentStatus = 'all';
      _selectedStaffId = 'all';
      _searchController.clear();
    });
    _fetchTransactions(resetPage: true);
  }

  Future<void> _pickCustomDateRange() async {
    final picked = await showDateRangePicker(
      context: context,
      firstDate: DateTime(2020),
      lastDate: DateTime.now().add(const Duration(days: 365)),
      builder: (context, child) {
        return Theme(
          data: ThemeData.dark().copyWith(
            colorScheme: const ColorScheme.dark(
              primary: AppColors.lime,
              onPrimary: Colors.black,
              surface: Color(0xFF1E1E2C),
              onSurface: Colors.white,
            ),
          ),
          child: child!,
        );
      },
    );

    if (picked != null) {
      setState(() {
        _selectedDateFilter = 'custom';
        _customStartDate = DateFormat('yyyy-MM-dd').format(picked.start);
        _customEndDate = DateFormat('yyyy-MM-dd').format(picked.end);
      });
      _fetchTransactions(resetPage: true);
    }
  }

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthProvider>();
    final currency = auth.currentTenant?.currency ?? '₹';

    return Scaffold(
      backgroundColor: AppColors.bg(context),
      appBar: AppBar(
        title: Text(
          'Transaction History',
          style: GoogleFonts.outfit(fontWeight: FontWeight.w800, fontSize: 18),
        ),
        actions: [
          IconButton(
            icon: Icon(
              _showFilters ? Icons.filter_alt_off : Icons.filter_alt_outlined,
              color: _showFilters || _hasActiveFilters ? AppColors.lime : AppColors.textMuted(context),
            ),
            tooltip: 'Toggle Filters',
            onPressed: () {
              setState(() {
                _showFilters = !_showFilters;
              });
            },
          ),
          IconButton(
            icon: const Icon(Icons.refresh_rounded),
            tooltip: 'Refresh',
            onPressed: () => _fetchTransactions(resetPage: true),
          ),
        ],
      ),
      body: Consumer<AdminProvider>(
        builder: (context, provider, _) {
          return RefreshIndicator(
            onRefresh: () async => _fetchTransactions(resetPage: true),
            color: AppColors.lime,
            child: CustomScrollView(
              controller: _scrollController,
              slivers: [
                // Top Search Bar
                SliverToBoxAdapter(
                  child: _buildSearchBar(context),
                ),

                // Expandable Filter Section
                if (_showFilters || _hasActiveFilters)
                  SliverToBoxAdapter(
                    child: _buildFilterSection(context, provider),
                  ),

                // Top KPI Summary Cards
                SliverToBoxAdapter(
                  child: _buildKpiSection(context, provider.transactionSummary, provider.isTransactionsLoading, currency),
                ),

                // Header with Total Count
                SliverToBoxAdapter(
                  child: Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 16.0, vertical: 8.0),
                    child: Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        Text(
                          'Transactions (${provider.txnTotalCount})',
                          style: GoogleFonts.outfit(
                            color: AppColors.textPrimary(context),
                            fontSize: 16,
                            fontWeight: FontWeight.bold,
                          ),
                        ),
                        if (_hasActiveFilters)
                          GestureDetector(
                            onTap: _resetFilters,
                            child: Text(
                              'Reset Filters',
                              style: GoogleFonts.plusJakartaSans(
                                color: AppColors.lime,
                                fontSize: 12.5,
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                          ),
                      ],
                    ),
                  ),
                ),

                // Transactions List / Shimmer / Empty State
                if (provider.isTransactionsLoading && provider.transactions.isEmpty)
                  SliverToBoxAdapter(
                    child: _buildLoadingShimmer(context),
                  )
                else if (provider.errorMessage != null && provider.transactions.isEmpty)
                  SliverFillRemaining(
                    hasScrollBody: false,
                    child: _buildErrorState(context, provider.errorMessage!),
                  )
                else if (provider.transactions.isEmpty)
                  SliverFillRemaining(
                    hasScrollBody: false,
                    child: _buildEmptyState(context),
                  )
                else
                  SliverList(
                    delegate: SliverChildBuilderDelegate(
                      (context, index) {
                        final txn = provider.transactions[index];
                        return _buildTransactionCard(context, txn, currency);
                      },
                      childCount: provider.transactions.length,
                    ),
                  ),

                // Pagination Loading Indicator
                if (provider.isTransactionsPaginationLoading)
                  const SliverToBoxAdapter(
                    child: Padding(
                      padding: EdgeInsets.all(16.0),
                      child: Center(
                        child: SizedBox(
                          height: 24,
                          width: 24,
                          child: CircularProgressIndicator(strokeWidth: 2, color: AppColors.lime),
                        ),
                      ),
                    ),
                  ),

                const SliverToBoxAdapter(
                  child: SizedBox(height: 36),
                ),
              ],
            ),
          );
        },
      ),
    );
  }

  bool get _hasActiveFilters =>
      _selectedDateFilter != 'all' ||
      _selectedPaymentMode != 'all' ||
      _selectedPaymentStatus != 'all' ||
      _selectedStaffId != 'all' ||
      _searchController.text.trim().isNotEmpty;

  Widget _buildSearchBar(BuildContext context) {
    return Container(
      margin: const EdgeInsets.fromLTRB(16, 12, 16, 8),
      decoration: BoxDecoration(
        color: AppColors.card(context),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: AppColors.border(context)),
      ),
      child: TextField(
        controller: _searchController,
        style: GoogleFonts.plusJakartaSans(color: AppColors.textPrimary(context), fontSize: 13.5),
        textInputAction: TextInputAction.search,
        onSubmitted: (_) => _fetchTransactions(resetPage: true),
        decoration: InputDecoration(
          hintText: 'Search by Member, Phone, ID or Txn #',
          hintStyle: GoogleFonts.plusJakartaSans(color: AppColors.textMuted(context), fontSize: 13),
          prefixIcon: Icon(Icons.search_rounded, color: AppColors.textMuted(context)),
          suffixIcon: _searchController.text.isNotEmpty
              ? IconButton(
                  icon: Icon(Icons.clear, color: AppColors.textMuted(context), size: 18),
                  onPressed: () {
                    _searchController.clear();
                    _fetchTransactions(resetPage: true);
                  },
                )
              : null,
          border: InputBorder.none,
          contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
        ),
      ),
    );
  }

  Widget _buildFilterSection(BuildContext context, AdminProvider provider) {
    return Container(
      margin: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: AppColors.card(context),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: AppColors.border(context)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(
                'Date Range',
                style: GoogleFonts.plusJakartaSans(
                  color: AppColors.textSecondary(context),
                  fontSize: 12,
                  fontWeight: FontWeight.w700,
                ),
              ),
              if (_selectedDateFilter == 'custom' && _customStartDate != null)
                Text(
                  '$_customStartDate to $_customEndDate',
                  style: GoogleFonts.plusJakartaSans(color: AppColors.lime, fontSize: 11, fontWeight: FontWeight.w700),
                ),
            ],
          ),
          const SizedBox(height: 8),
          SingleChildScrollView(
            scrollDirection: Axis.horizontal,
            child: Row(
              children: [
                _buildFilterChip('All Time', 'all', _selectedDateFilter, (val) {
                  setState(() => _selectedDateFilter = val);
                  _fetchTransactions(resetPage: true);
                }),
                _buildFilterChip('Today', 'today', _selectedDateFilter, (val) {
                  setState(() => _selectedDateFilter = val);
                  _fetchTransactions(resetPage: true);
                }),
                _buildFilterChip('Yesterday', 'yesterday', _selectedDateFilter, (val) {
                  setState(() => _selectedDateFilter = val);
                  _fetchTransactions(resetPage: true);
                }),
                _buildFilterChip('Last 7 Days', '7_days', _selectedDateFilter, (val) {
                  setState(() => _selectedDateFilter = val);
                  _fetchTransactions(resetPage: true);
                }),
                _buildFilterChip('This Month', 'this_month', _selectedDateFilter, (val) {
                  setState(() => _selectedDateFilter = val);
                  _fetchTransactions(resetPage: true);
                }),
                ActionChip(
                  avatar: const Icon(Icons.calendar_month_outlined, size: 16, color: AppColors.lime),
                  label: Text(
                    _selectedDateFilter == 'custom' ? 'Custom Date' : 'Pick Range...',
                    style: GoogleFonts.plusJakartaSans(
                      color: _selectedDateFilter == 'custom' ? AppColors.lime : AppColors.textSecondary(context),
                      fontSize: 12,
                      fontWeight: _selectedDateFilter == 'custom' ? FontWeight.bold : FontWeight.normal,
                    ),
                  ),
                  backgroundColor: _selectedDateFilter == 'custom' ? AppColors.lime.withValues(alpha: 0.15) : AppColors.cardElevated(context),
                  side: BorderSide(
                    color: _selectedDateFilter == 'custom' ? AppColors.lime : AppColors.border(context),
                  ),
                  onPressed: _pickCustomDateRange,
                ),
              ],
            ),
          ),
          const SizedBox(height: 12),

          Row(
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Payment Mode',
                      style: GoogleFonts.plusJakartaSans(
                        color: AppColors.textSecondary(context),
                        fontSize: 11.5,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                    const SizedBox(height: 6),
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 10),
                      decoration: BoxDecoration(
                        color: AppColors.cardElevated(context),
                        borderRadius: BorderRadius.circular(10),
                        border: Border.all(color: AppColors.border(context)),
                      ),
                      child: DropdownButtonHideUnderline(
                        child: DropdownButton<String>(
                          value: _selectedPaymentMode,
                          isExpanded: true,
                          dropdownColor: AppColors.card(context),
                          icon: Icon(Icons.arrow_drop_down, color: AppColors.textMuted(context)),
                          style: GoogleFonts.plusJakartaSans(color: AppColors.textPrimary(context), fontSize: 12),
                          items: const [
                            DropdownMenuItem(value: 'all', child: Text('All Modes')),
                            DropdownMenuItem(value: 'Cash', child: Text('Cash')),
                            DropdownMenuItem(value: 'UPI', child: Text('UPI / QR')),
                            DropdownMenuItem(value: 'Card', child: Text('Card')),
                            DropdownMenuItem(value: 'Bank Transfer', child: Text('Bank Transfer')),
                            DropdownMenuItem(value: 'Online', child: Text('Online Gateway')),
                          ],
                          onChanged: (val) {
                            if (val != null) {
                              setState(() => _selectedPaymentMode = val);
                              _fetchTransactions(resetPage: true);
                            }
                          },
                        ),
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Status',
                      style: GoogleFonts.plusJakartaSans(
                        color: AppColors.textSecondary(context),
                        fontSize: 11.5,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                    const SizedBox(height: 6),
                    Container(
                      padding: const EdgeInsets.symmetric(horizontal: 10),
                      decoration: BoxDecoration(
                        color: AppColors.cardElevated(context),
                        borderRadius: BorderRadius.circular(10),
                        border: Border.all(color: AppColors.border(context)),
                      ),
                      child: DropdownButtonHideUnderline(
                        child: DropdownButton<String>(
                          value: _selectedPaymentStatus,
                          isExpanded: true,
                          dropdownColor: AppColors.card(context),
                          icon: Icon(Icons.arrow_drop_down, color: AppColors.textMuted(context)),
                          style: GoogleFonts.plusJakartaSans(color: AppColors.textPrimary(context), fontSize: 12),
                          items: const [
                            DropdownMenuItem(value: 'all', child: Text('All Statuses')),
                            DropdownMenuItem(value: 'paid', child: Text('Paid')),
                            DropdownMenuItem(value: 'partial', child: Text('Partial')),
                            DropdownMenuItem(value: 'pending', child: Text('Pending')),
                            DropdownMenuItem(value: 'refunded', child: Text('Refunded')),
                            DropdownMenuItem(value: 'failed', child: Text('Failed')),
                          ],
                          onChanged: (val) {
                            if (val != null) {
                              setState(() => _selectedPaymentStatus = val);
                              _fetchTransactions(resetPage: true);
                            }
                          },
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),

          if (provider.staffFilterOptions.isNotEmpty) ...[
            const SizedBox(height: 10),
            Text(
              'Collected By Staff',
              style: GoogleFonts.plusJakartaSans(color: AppColors.textSecondary(context), fontSize: 11.5, fontWeight: FontWeight.w700),
            ),
            const SizedBox(height: 6),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 10),
              decoration: BoxDecoration(
                color: AppColors.cardElevated(context),
                borderRadius: BorderRadius.circular(10),
                border: Border.all(color: AppColors.border(context)),
              ),
              child: DropdownButtonHideUnderline(
                child: DropdownButton<String>(
                  value: _selectedStaffId,
                  isExpanded: true,
                  dropdownColor: AppColors.card(context),
                  icon: Icon(Icons.arrow_drop_down, color: AppColors.textMuted(context)),
                  style: GoogleFonts.plusJakartaSans(color: AppColors.textPrimary(context), fontSize: 12),
                  items: [
                    const DropdownMenuItem(value: 'all', child: Text('All Staff / Admin')),
                    ...provider.staffFilterOptions.map(
                      (staff) => DropdownMenuItem(
                        value: staff.id.toString(),
                        child: Text('${staff.name} (${staff.role})'),
                      ),
                    ),
                  ],
                  onChanged: (val) {
                    if (val != null) {
                      setState(() => _selectedStaffId = val);
                      _fetchTransactions(resetPage: true);
                    }
                  },
                ),
              ),
            ),
          ],
        ],
      ),
    );
  }

  Widget _buildFilterChip(String label, String value, String currentValue, Function(String) onSelected) {
    final isSelected = value == currentValue;
    return Padding(
      padding: const EdgeInsets.only(right: 6),
      child: ChoiceChip(
        label: Text(label),
        selected: isSelected,
        onSelected: (selected) {
          if (selected) onSelected(value);
        },
        labelStyle: GoogleFonts.plusJakartaSans(
          color: isSelected ? AppColors.lime : AppColors.textSecondary(context),
          fontSize: 12,
          fontWeight: isSelected ? FontWeight.bold : FontWeight.normal,
        ),
        backgroundColor: AppColors.cardElevated(context),
        selectedColor: AppColors.lime.withValues(alpha: 0.18),
        side: BorderSide(
          color: isSelected ? AppColors.lime : AppColors.border(context),
        ),
      ),
    );
  }

  Widget _buildKpiSection(BuildContext context, AdminTransactionSummary? summary, bool isLoading, String currency) {
    final total = summary?.totalCollection ?? 0.0;
    final today = summary?.todayCollection ?? 0.0;
    final month = summary?.thisMonthCollection ?? 0.0;
    final cash = summary?.cashCollection ?? 0.0;
    final upi = summary?.onlineUpiCollection ?? 0.0;
    final dues = summary?.pendingDues ?? 0.0;

    return Container(
      margin: const EdgeInsets.symmetric(horizontal: 16, vertical: 6),
      child: Column(
        children: [
          Row(
            children: [
              Expanded(
                child: _buildKpiCard(
                  context,
                  title: 'Total Collection',
                  amount: total,
                  currency: currency,
                  icon: Icons.account_balance_wallet_rounded,
                  color: const Color(0xFF10B981),
                  isLoading: isLoading,
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: _buildKpiCard(
                  context,
                  title: "Today's Collection",
                  amount: today,
                  currency: currency,
                  icon: Icons.today_rounded,
                  color: const Color(0xFF3B82F6),
                  isLoading: isLoading,
                ),
              ),
            ],
          ),
          const SizedBox(height: 10),
          Row(
            children: [
              Expanded(
                child: _buildKpiCard(
                  context,
                  title: 'This Month',
                  amount: month,
                  currency: currency,
                  icon: Icons.calendar_today_rounded,
                  color: const Color(0xFF8B5CF6),
                  isLoading: isLoading,
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: _buildKpiCard(
                  context,
                  title: 'Cash Collection',
                  amount: cash,
                  currency: currency,
                  icon: Icons.payments_rounded,
                  color: const Color(0xFFF59E0B),
                  isLoading: isLoading,
                ),
              ),
            ],
          ),
          const SizedBox(height: 10),
          Row(
            children: [
              Expanded(
                child: _buildKpiCard(
                  context,
                  title: 'UPI / Online',
                  amount: upi,
                  currency: currency,
                  icon: Icons.qr_code_scanner_rounded,
                  color: const Color(0xFF00CEC9),
                  isLoading: isLoading,
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: _buildKpiCard(
                  context,
                  title: 'Pending Dues',
                  amount: dues,
                  currency: currency,
                  icon: Icons.pending_actions_rounded,
                  color: const Color(0xFFFF5A36),
                  isLoading: isLoading,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildKpiCard(
    BuildContext context, {
    required String title,
    required double amount,
    required String currency,
    required IconData icon,
    required Color color,
    required bool isLoading,
  }) {
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: AppColors.card(context),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: color.withValues(alpha: 0.25)),
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [
            color.withValues(alpha: 0.08),
            AppColors.card(context),
          ],
        ),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Flexible(
                child: Text(
                  title,
                  style: GoogleFonts.plusJakartaSans(
                    color: AppColors.textSecondary(context),
                    fontSize: 11,
                    fontWeight: FontWeight.w700,
                  ),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                ),
              ),
              Container(
                padding: const EdgeInsets.all(5),
                decoration: BoxDecoration(
                  color: color.withValues(alpha: 0.15),
                  shape: BoxShape.circle,
                ),
                child: Icon(icon, color: color, size: 13),
              ),
            ],
          ),
          const SizedBox(height: 6),
          isLoading
              ? Container(
                  width: 60,
                  height: 18,
                  decoration: BoxDecoration(
                    color: AppColors.border(context),
                    borderRadius: BorderRadius.circular(4),
                  ),
                )
              : Text(
                  '$currency${amount.toStringAsFixed(2)}',
                  style: GoogleFonts.outfit(
                    color: color,
                    fontSize: 16,
                    fontWeight: FontWeight.w900,
                    letterSpacing: -0.2,
                  ),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                ),
        ],
      ),
    );
  }

  Widget _buildTransactionCard(BuildContext context, AdminTransactionItem txn, String currency) {
    Color statusColor;
    switch (txn.status.toLowerCase()) {
      case 'paid':
        statusColor = const Color(0xFF10B981);
        break;
      case 'partial':
        statusColor = const Color(0xFFF59E0B);
        break;
      case 'refunded':
        statusColor = const Color(0xFF8B5CF6);
        break;
      case 'failed':
        statusColor = const Color(0xFFFF5A36);
        break;
      default:
        statusColor = const Color(0xFF6B7280);
    }

    return Container(
      margin: const EdgeInsets.symmetric(horizontal: 16, vertical: 6),
      decoration: BoxDecoration(
        color: AppColors.card(context),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: AppColors.border(context)),
      ),
      child: Material(
        color: Colors.transparent,
        child: InkWell(
          borderRadius: BorderRadius.circular(14),
          onTap: () => _showTransactionDetailsBottomSheet(context, txn, currency),
          child: Padding(
            padding: const EdgeInsets.all(14),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                // Top Row: Member Name, Avatar, Amount
                Row(
                  crossAxisAlignment: CrossAxisAlignment.center,
                  children: [
                    CircleAvatar(
                      radius: 20,
                      backgroundColor: AppColors.lime.withValues(alpha: 0.15),
                      backgroundImage: txn.memberAvatar != null && txn.memberAvatar!.isNotEmpty
                          ? NetworkImage(txn.memberAvatar!)
                          : null,
                      child: (txn.memberAvatar == null || txn.memberAvatar!.isEmpty)
                          ? Text(
                              txn.memberName.isNotEmpty ? txn.memberName[0].toUpperCase() : 'M',
                              style: const TextStyle(color: AppColors.lime, fontWeight: FontWeight.bold),
                            )
                          : null,
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            txn.memberName,
                            style: GoogleFonts.outfit(
                              color: AppColors.textPrimary(context),
                              fontSize: 15,
                              fontWeight: FontWeight.bold,
                            ),
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                          ),
                          const SizedBox(height: 2),
                          Text(
                            'ID: #${txn.memberId} • ${txn.memberPhone}',
                            style: GoogleFonts.plusJakartaSans(
                              color: AppColors.textMuted(context),
                              fontSize: 11.5,
                            ),
                          ),
                        ],
                      ),
                    ),
                    Column(
                      crossAxisAlignment: CrossAxisAlignment.end,
                      children: [
                        Text(
                          '$currency${txn.paidAmount.toStringAsFixed(2)}',
                          style: GoogleFonts.outfit(
                            color: const Color(0xFF10B981),
                            fontSize: 16,
                            fontWeight: FontWeight.bold,
                          ),
                        ),
                        if (txn.dueAmount > 0)
                          Text(
                            'Due: $currency${txn.dueAmount.toStringAsFixed(2)}',
                            style: GoogleFonts.plusJakartaSans(
                              color: const Color(0xFFFF5A36),
                              fontSize: 11,
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                      ],
                    ),
                  ],
                ),
                const SizedBox(height: 10),
                Divider(color: AppColors.border(context), height: 1),
                const SizedBox(height: 10),

                // Bottom Row: Receipt #, Plan, Mode, Status, Date
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Flexible(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Row(
                            children: [
                              Container(
                                padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                                decoration: BoxDecoration(
                                  color: AppColors.cardElevated(context),
                                  borderRadius: BorderRadius.circular(4),
                                  border: Border.all(color: AppColors.border(context)),
                                ),
                                child: Text(
                                  txn.invoiceNumber,
                                  style: GoogleFonts.plusJakartaSans(
                                    color: AppColors.textSecondary(context),
                                    fontSize: 10,
                                    fontWeight: FontWeight.bold,
                                  ),
                                ),
                              ),
                              const SizedBox(width: 6),
                              Container(
                                padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                                decoration: BoxDecoration(
                                  color: AppColors.lime.withValues(alpha: 0.12),
                                  borderRadius: BorderRadius.circular(4),
                                ),
                                child: Text(
                                  txn.paymentMethod,
                                  style: GoogleFonts.plusJakartaSans(
                                    color: AppColors.lime,
                                    fontSize: 10,
                                    fontWeight: FontWeight.w700,
                                  ),
                                ),
                              ),
                            ],
                          ),
                          const SizedBox(height: 4),
                          Text(
                            'Plan: ${txn.serviceName}',
                            style: GoogleFonts.plusJakartaSans(
                              color: AppColors.textSecondary(context),
                              fontSize: 11,
                            ),
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                          ),
                        ],
                      ),
                    ),
                    Column(
                      crossAxisAlignment: CrossAxisAlignment.end,
                      children: [
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                          decoration: BoxDecoration(
                            color: statusColor.withValues(alpha: 0.15),
                            borderRadius: BorderRadius.circular(6),
                            border: Border.all(color: statusColor.withValues(alpha: 0.3)),
                          ),
                          child: Text(
                            txn.status.toUpperCase(),
                            style: GoogleFonts.plusJakartaSans(
                              color: statusColor,
                              fontSize: 10,
                              fontWeight: FontWeight.bold,
                            ),
                          ),
                        ),
                        const SizedBox(height: 4),
                        Text(
                          txn.paymentDate,
                          style: GoogleFonts.plusJakartaSans(
                            color: AppColors.textMuted(context),
                            fontSize: 10,
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  void _showTransactionDetailsBottomSheet(BuildContext context, AdminTransactionItem txn, String currency) {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (bottomSheetCtx) => _buildTransactionDetailModal(bottomSheetCtx, txn, currency),
    );
  }

  Widget _buildTransactionDetailModal(BuildContext context, AdminTransactionItem txn, String currency) {
    Color statusColor;
    switch (txn.status.toLowerCase()) {
      case 'paid':
        statusColor = const Color(0xFF10B981);
        break;
      case 'partial':
        statusColor = const Color(0xFFF59E0B);
        break;
      case 'refunded':
        statusColor = const Color(0xFF8B5CF6);
        break;
      case 'failed':
        statusColor = const Color(0xFFFF5A36);
        break;
      default:
        statusColor = const Color(0xFF6B7280);
    }

    return SafeArea(
      child: Container(
        decoration: BoxDecoration(
          color: AppColors.card(context),
          borderRadius: const BorderRadius.vertical(top: Radius.circular(24)),
        ),
        padding: EdgeInsets.only(
          left: 20,
          right: 20,
          top: 12,
          bottom: MediaQuery.of(context).viewInsets.bottom + 20,
        ),
        child: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Center(
                child: Container(
                  width: 40,
                  height: 4,
              decoration: BoxDecoration(
                color: AppColors.border(context),
                borderRadius: BorderRadius.circular(2),
              ),
            ),
          ),
          const SizedBox(height: 16),

          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Transaction Details',
                    style: GoogleFonts.outfit(
                      color: AppColors.textPrimary(context),
                      fontSize: 18,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                  Text(
                    'Receipt: ${txn.invoiceNumber}',
                    style: GoogleFonts.plusJakartaSans(
                      color: AppColors.textMuted(context),
                      fontSize: 12,
                    ),
                  ),
                ],
              ),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                decoration: BoxDecoration(
                  color: statusColor.withValues(alpha: 0.15),
                  borderRadius: BorderRadius.circular(8),
                  border: Border.all(color: statusColor.withValues(alpha: 0.4)),
                ),
                child: Text(
                  txn.status.toUpperCase(),
                  style: GoogleFonts.plusJakartaSans(
                    color: statusColor,
                    fontSize: 12,
                    fontWeight: FontWeight.bold,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 16),
          Divider(color: AppColors.border(context)),
          const SizedBox(height: 10),

          // Member Info Box
          Container(
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(
              color: AppColors.cardElevated(context),
              borderRadius: BorderRadius.circular(12),
              border: Border.all(color: AppColors.border(context)),
            ),
            child: Row(
              children: [
                CircleAvatar(
                  radius: 22,
                  backgroundColor: AppColors.lime.withValues(alpha: 0.15),
                  backgroundImage: txn.memberAvatar != null && txn.memberAvatar!.isNotEmpty
                      ? NetworkImage(txn.memberAvatar!)
                      : null,
                  child: (txn.memberAvatar == null || txn.memberAvatar!.isEmpty)
                      ? Text(
                          txn.memberName.isNotEmpty ? txn.memberName[0].toUpperCase() : 'M',
                          style: const TextStyle(color: AppColors.lime, fontWeight: FontWeight.bold),
                        )
                      : null,
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        txn.memberName,
                        style: GoogleFonts.outfit(
                          color: AppColors.textPrimary(context),
                          fontSize: 15,
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                      const SizedBox(height: 2),
                      Text(
                        'Member ID: #${txn.memberId} | Mobile: ${txn.memberPhone}',
                        style: GoogleFonts.plusJakartaSans(color: AppColors.textSecondary(context), fontSize: 12),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 14),

          _buildDetailRow(context, 'Membership Plan', txn.serviceName),
          _buildDetailRow(context, 'Payment Date', txn.paymentDate),
          _buildDetailRow(context, 'Payment Method', txn.paymentMethod),
          if (txn.transactionRef.isNotEmpty)
            _buildDetailRow(context, 'Transaction Ref', txn.transactionRef),
          _buildDetailRow(context, 'Collected By', txn.collectedBy),
          if (txn.notes.isNotEmpty)
            _buildDetailRow(context, 'Remarks / Notes', txn.notes),

          const SizedBox(height: 10),
          Divider(color: AppColors.border(context)),
          const SizedBox(height: 10),

          // Financial Breakdown Table
          Container(
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(
              color: AppColors.cardElevated(context),
              borderRadius: BorderRadius.circular(12),
              border: Border.all(color: AppColors.border(context)),
            ),
            child: Column(
              children: [
                _buildFinancialRow(context, 'Total Invoiced Fee', '$currency${txn.amount.toStringAsFixed(2)}'),
                if (txn.discount > 0)
                  _buildFinancialRow(context, 'Discount Applied', '- $currency${txn.discount.toStringAsFixed(2)}', isNegative: true),
                _buildFinancialRow(context, 'Amount Paid', '$currency${txn.paidAmount.toStringAsFixed(2)}', isBold: true, isHighlight: true),
                if (txn.dueAmount > 0)
                  _buildFinancialRow(context, 'Remaining Balance Due', '$currency${txn.dueAmount.toStringAsFixed(2)}', isDue: true),
              ],
            ),
          ),
          const SizedBox(height: 20),

          // 3 Action Buttons: View, Download, Share Receipt
          Row(
            children: [
              Expanded(
                child: ElevatedButton.icon(
                  icon: const Icon(Icons.remove_red_eye_outlined, size: 16),
                  label: const Text('View PDF', style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold)),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: AppColors.cardElevated(context),
                    foregroundColor: AppColors.textPrimary(context),
                    side: BorderSide(color: AppColors.border(context)),
                    padding: const EdgeInsets.symmetric(vertical: 12),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                  ),
                  onPressed: () {
                    Navigator.pop(context);
                    PdfService.previewReceiptPdf(context, txn);
                  },
                ),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: ElevatedButton.icon(
                  icon: const Icon(Icons.download_rounded, size: 16),
                  label: const Text('Download', style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold)),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: AppColors.cardElevated(context),
                    foregroundColor: AppColors.textPrimary(context),
                    side: BorderSide(color: AppColors.border(context)),
                    padding: const EdgeInsets.symmetric(vertical: 12),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                  ),
                  onPressed: () async {
                    Navigator.pop(context);
                    await PdfService.downloadReceiptPdf(context, txn);
                  },
                ),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: ElevatedButton.icon(
                  icon: const Icon(Icons.share_rounded, size: 16),
                  label: const Text('Share PDF', style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold)),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: AppColors.lime,
                    foregroundColor: Colors.black,
                    padding: const EdgeInsets.symmetric(vertical: 12),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                  ),
                  onPressed: () async {
                    Navigator.pop(context);
                    await PdfService.shareReceiptPdf(context, txn);
                  },
                ),
              ),
            ],
          ),
          const SizedBox(height: 10),
        ],
      ),
    ),
  ),
);
}

  Widget _buildDetailRow(BuildContext context, String label, String value) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(
            label,
            style: GoogleFonts.plusJakartaSans(color: AppColors.textSecondary(context), fontSize: 13),
          ),
          const SizedBox(width: 16),
          Flexible(
            child: Text(
              value,
              textAlign: TextAlign.end,
              style: GoogleFonts.plusJakartaSans(
                color: AppColors.textPrimary(context),
                fontSize: 13,
                fontWeight: FontWeight.w600,
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildFinancialRow(
    BuildContext context,
    String label,
    String value, {
    bool isBold = false,
    bool isHighlight = false,
    bool isNegative = false,
    bool isDue = false,
  }) {
    Color valColor = AppColors.textPrimary(context);
    if (isHighlight) valColor = const Color(0xFF10B981);
    if (isNegative) valColor = const Color(0xFFF59E0B);
    if (isDue) valColor = const Color(0xFFFF5A36);

    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Text(
            label,
            style: GoogleFonts.plusJakartaSans(
              color: isDue ? const Color(0xFFFF5A36) : AppColors.textSecondary(context),
              fontSize: 13,
              fontWeight: isBold ? FontWeight.bold : FontWeight.normal,
            ),
          ),
          Text(
            value,
            style: GoogleFonts.outfit(
              color: valColor,
              fontSize: 14,
              fontWeight: isBold ? FontWeight.w900 : FontWeight.w600,
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildLoadingShimmer(BuildContext context) {
    return Column(
      children: List.generate(
        5,
        (index) => Container(
          margin: const EdgeInsets.symmetric(horizontal: 16, vertical: 6),
          height: 100,
          decoration: BoxDecoration(
            color: AppColors.card(context),
            borderRadius: BorderRadius.circular(14),
            border: Border.all(color: AppColors.border(context)),
          ),
        ),
      ),
    );
  }

  Widget _buildEmptyState(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(32.0),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Container(
              padding: const EdgeInsets.all(20),
              decoration: BoxDecoration(
                color: AppColors.lime.withValues(alpha: 0.1),
                shape: BoxShape.circle,
              ),
              child: const Icon(
                Icons.receipt_long_outlined,
                size: 54,
                color: AppColors.lime,
              ),
            ),
            const SizedBox(height: 16),
            Text(
              'No Transactions Found',
              style: GoogleFonts.outfit(
                color: AppColors.textPrimary(context),
                fontSize: 18,
                fontWeight: FontWeight.bold,
              ),
            ),
            const SizedBox(height: 8),
            Text(
              'No payments match your selected search or filters. Try resetting the filters.',
              textAlign: TextAlign.center,
              style: GoogleFonts.plusJakartaSans(
                color: AppColors.textSecondary(context),
                fontSize: 13,
              ),
            ),
            if (_hasActiveFilters) ...[
              const SizedBox(height: 16),
              ElevatedButton.icon(
                onPressed: _resetFilters,
                icon: const Icon(Icons.refresh_rounded, size: 16),
                label: const Text('Reset Filters'),
                style: ElevatedButton.styleFrom(
                  backgroundColor: AppColors.lime,
                  foregroundColor: Colors.black,
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }

  Widget _buildErrorState(BuildContext context, String error) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(32.0),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            const Icon(Icons.error_outline_rounded, size: 48, color: Color(0xFFFF5A36)),
            const SizedBox(height: 12),
            Text(
              'Failed to Load Transactions',
              style: GoogleFonts.outfit(color: AppColors.textPrimary(context), fontSize: 16, fontWeight: FontWeight.bold),
            ),
            const SizedBox(height: 6),
            Text(
              error,
              textAlign: TextAlign.center,
              style: GoogleFonts.plusJakartaSans(color: AppColors.textSecondary(context), fontSize: 12),
            ),
            const SizedBox(height: 16),
            ElevatedButton(
              onPressed: () => _fetchTransactions(resetPage: true),
              style: ElevatedButton.styleFrom(
                backgroundColor: AppColors.lime,
                foregroundColor: Colors.black,
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
              ),
              child: const Text('Retry'),
            ),
          ],
        ),
      ),
    );
  }
}
