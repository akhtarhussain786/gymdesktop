import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../providers/admin_provider.dart';

class AdminReportsScreen extends StatefulWidget {
  const AdminReportsScreen({super.key});

  @override
  State<AdminReportsScreen> createState() => _AdminReportsScreenState();
}

class _AdminReportsScreenState extends State<AdminReportsScreen> {
  final DateTime _startDate = DateTime(DateTime.now().year, 1, 1);
  final DateTime _endDate = DateTime.now();

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      _loadReports();
    });
  }

  void _loadReports() {
    context.read<AdminProvider>().fetchReports(
      startDate: _startDate.toString().split(' ')[0],
      endDate: _endDate.toString().split(' ')[0],
    );
  }

  @override
  Widget build(BuildContext context) {
    final provider = context.watch<AdminProvider>();
    final data = provider.reportsData;

    return Scaffold(
      backgroundColor: const Color(0xFF13131A),
      appBar: AppBar(
        backgroundColor: const Color(0xFF1E1E2C),
        elevation: 0,
        title: const Text('Reports & Analytics', style: TextStyle(fontWeight: FontWeight.bold, color: Colors.white)),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh, color: Colors.white70),
            onPressed: _loadReports,
          ),
        ],
      ),
      body: provider.isSectionLoading
          ? const Center(child: CircularProgressIndicator(color: Color(0xFF6C5CE7)))
          : (data == null)
              ? const Center(child: Text('No data available', style: TextStyle(color: Colors.white70)))
              : SingleChildScrollView(
                  padding: const EdgeInsets.all(16),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      // Net Profit Card
                      Container(
                        padding: const EdgeInsets.all(20),
                        decoration: BoxDecoration(
                          gradient: LinearGradient(
                            colors: data.netProfit >= 0
                                ? [const Color(0xFF00B894), const Color(0xFF00CEC9)]
                                : [const Color(0xFFE17055), const Color(0xFFD63031)],
                            begin: Alignment.topLeft,
                            end: Alignment.bottomRight,
                          ),
                          borderRadius: BorderRadius.circular(20),
                          boxShadow: [
                            BoxShadow(
                              color: (data.netProfit >= 0 ? const Color(0xFF00CEC9) : const Color(0xFFD63031)).withOpacity(0.3),
                              blurRadius: 16,
                              offset: const Offset(0, 8),
                            ),
                          ],
                        ),
                        child: Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                const Text('Net Profit / (Loss)', style: TextStyle(color: Colors.white70, fontSize: 13, fontWeight: FontWeight.w500)),
                                const SizedBox(height: 6),
                                Text(
                                  '₹${data.netProfit.toStringAsFixed(0)}',
                                  style: const TextStyle(color: Colors.white, fontSize: 28, fontWeight: FontWeight.bold),
                                ),
                              ],
                            ),
                            Icon(
                              data.netProfit >= 0 ? Icons.trending_up : Icons.trending_down,
                              color: Colors.white,
                              size: 40,
                            ),
                          ],
                        ),
                      ),
                      const SizedBox(height: 16),

                      // Revenue & Expense Grid
                      Row(
                        children: [
                          Expanded(
                            child: _buildMetricCard(
                              title: 'Total Revenue',
                              value: '₹${data.revenue.toStringAsFixed(0)}',
                              icon: Icons.monetization_on,
                              color: const Color(0xFF00CEC9),
                            ),
                          ),
                          const SizedBox(width: 12),
                          Expanded(
                            child: _buildMetricCard(
                              title: 'Total Expenses',
                              value: '₹${data.expenses.toStringAsFixed(0)}',
                              icon: Icons.payments,
                              color: const Color(0xFFFF7675),
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 12),
                      Row(
                        children: [
                          Expanded(
                            child: _buildMetricCard(
                              title: 'Total Check-ins',
                              value: '${data.attendanceCount}',
                              icon: Icons.how_to_reg,
                              color: const Color(0xFF6C5CE7),
                            ),
                          ),
                          const SizedBox(width: 12),
                          Expanded(
                            child: _buildMetricCard(
                              title: 'New Members',
                              value: '${data.newMembersCount}',
                              icon: Icons.person_add,
                              color: const Color(0xFFFDCB6E),
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 24),

                      // 6-Month Monthly Trend
                      const Text(
                        '6-Month Financial Trend',
                        style: TextStyle(color: Colors.white, fontSize: 17, fontWeight: FontWeight.bold),
                      ),
                      const SizedBox(height: 12),
                      ...data.monthlyTrend.map((m) {
                        final maxVal = data.monthlyTrend.fold<double>(1.0, (prev, elem) => elem.revenue > prev ? elem.revenue : prev);
                        final revRatio = maxVal > 0 ? (m.revenue / maxVal).clamp(0.05, 1.0) : 0.05;

                        return Container(
                          margin: const EdgeInsets.only(bottom: 12),
                          padding: const EdgeInsets.all(14),
                          decoration: BoxDecoration(
                            color: const Color(0xFF1E1E2C),
                            borderRadius: BorderRadius.circular(14),
                          ),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Row(
                                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                children: [
                                  Text(m.month, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 14)),
                                  Text('Revenue: ₹${m.revenue.toStringAsFixed(0)}', style: const TextStyle(color: Color(0xFF00CEC9), fontWeight: FontWeight.bold, fontSize: 13)),
                                ],
                              ),
                              const SizedBox(height: 8),
                              LinearProgressIndicator(
                                value: revRatio,
                                backgroundColor: Colors.white10,
                                valueColor: const AlwaysStoppedAnimation<Color>(Color(0xFF00CEC9)),
                                minHeight: 8,
                                borderRadius: BorderRadius.circular(4),
                              ),
                              const SizedBox(height: 6),
                              Row(
                                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                children: [
                                  Text('Expenses: ₹${m.expenses.toStringAsFixed(0)}', style: const TextStyle(color: Color(0xFFFF7675), fontSize: 12)),
                                  Text('Net: ₹${m.net.toStringAsFixed(0)}', style: TextStyle(color: m.net >= 0 ? const Color(0xFF00CEC9) : const Color(0xFFFF7675), fontSize: 12, fontWeight: FontWeight.bold)),
                                ],
                              ),
                            ],
                          ),
                        );
                      }),
                    ],
                  ),
                ),
    );
  }

  Widget _buildMetricCard({required String title, required String value, required IconData icon, required Color color}) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: const Color(0xFF1E1E2C),
        borderRadius: BorderRadius.circular(16),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(title, style: const TextStyle(color: Colors.white60, fontSize: 12, fontWeight: FontWeight.w500)),
              Icon(icon, color: color, size: 20),
            ],
          ),
          const SizedBox(height: 10),
          Text(value, style: TextStyle(color: color, fontSize: 20, fontWeight: FontWeight.bold)),
        ],
      ),
    );
  }
}
