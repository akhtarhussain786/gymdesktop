import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../models/admin_models.dart';
import '../../providers/admin_provider.dart';

class AdminAttendanceScreen extends StatefulWidget {
  const AdminAttendanceScreen({super.key});

  @override
  State<AdminAttendanceScreen> createState() => _AdminAttendanceScreenState();
}

class _AdminAttendanceScreenState extends State<AdminAttendanceScreen> {
  DateTime _selectedDate = DateTime.now();

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      _loadAttendance();
      context.read<AdminProvider>().fetchMembers();
    });
  }

  void _loadAttendance() {
    final dateStr = _selectedDate.toString().split(' ')[0];
    context.read<AdminProvider>().fetchAttendanceLogs(date: dateStr);
  }

  void _pickDate() async {
    final picked = await showDatePicker(
      context: context,
      initialDate: _selectedDate,
      firstDate: DateTime(2020),
      lastDate: DateTime.now().add(const Duration(days: 1)),
      builder: (context, child) {
        return Theme(
          data: Theme.of(context).copyWith(
            colorScheme: const ColorScheme.dark(
              primary: Color(0xFF6C5CE7),
              surface: Color(0xFF1E1E2C),
            ),
          ),
          child: child!,
        );
      },
    );
    if (picked != null && picked != _selectedDate) {
      setState(() => _selectedDate = picked);
      _loadAttendance();
    }
  }

  void _showMarkAttendanceModal() {
    final provider = context.read<AdminProvider>();
    int? selectedMemberId = provider.members.isNotEmpty ? provider.members.first.memberId : null;
    String action = 'checkin';

    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (ctx) => StatefulBuilder(
        builder: (ctx, setModalState) => Container(
          padding: EdgeInsets.only(
            top: 24,
            left: 20,
            right: 20,
            bottom: MediaQuery.of(ctx).viewInsets.bottom + 24,
          ),
          decoration: const BoxDecoration(
            color: Color(0xFF1E1E2C),
            borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Center(
                child: Container(
                  width: 40,
                  height: 4,
                  decoration: BoxDecoration(
                    color: Colors.white24,
                    borderRadius: BorderRadius.circular(2),
                  ),
                ),
              ),
              const SizedBox(height: 16),
              const Text(
                'Manual Attendance Entry',
                style: TextStyle(fontSize: 20, fontWeight: FontWeight.bold, color: Colors.white),
              ),
              const SizedBox(height: 16),
              DropdownButtonFormField<int>(
                value: selectedMemberId,
                dropdownColor: const Color(0xFF2A2A3E),
                style: const TextStyle(color: Colors.white),
                decoration: InputDecoration(
                  labelText: 'Select Member',
                  labelStyle: const TextStyle(color: Colors.white70),
                  filled: true,
                  fillColor: Colors.white.withOpacity(0.05),
                  border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
                ),
                items: provider.members.map((m) {
                  return DropdownMenuItem<int>(
                    value: m.memberId,
                    child: Text('${m.fullname} (${m.phone})', overflow: TextOverflow.ellipsis),
                  );
                }).toList(),
                onChanged: (val) {
                  if (val != null) setModalState(() => selectedMemberId = val);
                },
              ),
              const SizedBox(height: 16),
              Row(
                children: [
                  Expanded(
                    child: RadioListTile<String>(
                      title: const Text('Check In', style: TextStyle(color: Colors.white)),
                      value: 'checkin',
                      groupValue: action,
                      activeColor: const Color(0xFF00CEC9),
                      onChanged: (val) => setModalState(() => action = val!),
                    ),
                  ),
                  Expanded(
                    child: RadioListTile<String>(
                      title: const Text('Check Out', style: TextStyle(color: Colors.white)),
                      value: 'checkout',
                      groupValue: action,
                      activeColor: const Color(0xFFFF7675),
                      onChanged: (val) => setModalState(() => action = val!),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 20),
              SizedBox(
                width: double.infinity,
                height: 50,
                child: ElevatedButton(
                  style: ElevatedButton.styleFrom(
                    backgroundColor: const Color(0xFF6C5CE7),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                  ),
                  onPressed: () async {
                    if (selectedMemberId == null) return;
                    Navigator.pop(ctx);
                    try {
                      await context.read<AdminProvider>().markAttendance(
                        memberId: selectedMemberId!,
                        action: action,
                      );
                      if (mounted) {
                        ScaffoldMessenger.of(context).showSnackBar(
                          SnackBar(content: Text('Attendance marked (${action.toUpperCase()}) successfully!')),
                        );
                      }
                    } catch (e) {
                      if (mounted) {
                        ScaffoldMessenger.of(context).showSnackBar(
                          SnackBar(content: Text('Error: $e')),
                        );
                      }
                    }
                  },
                  child: const Text('Submit Attendance', style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold, color: Colors.white)),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final provider = context.watch<AdminProvider>();
    final isToday = _selectedDate.toString().split(' ')[0] == DateTime.now().toString().split(' ')[0];

    return Scaffold(
      backgroundColor: const Color(0xFF13131A),
      appBar: AppBar(
        backgroundColor: const Color(0xFF1E1E2C),
        elevation: 0,
        title: const Text('Live Attendance', style: TextStyle(fontWeight: FontWeight.bold, color: Colors.white)),
        actions: [
          IconButton(
            icon: const Icon(Icons.calendar_month, color: Colors.white70),
            onPressed: _pickDate,
          ),
          IconButton(
            icon: const Icon(Icons.refresh, color: Colors.white70),
            onPressed: _loadAttendance,
          ),
        ],
      ),
      floatingActionButton: FloatingActionButton.extended(
        backgroundColor: const Color(0xFF00CEC9),
        icon: const Icon(Icons.touch_app, color: Colors.black),
        label: const Text('Mark Entry', style: TextStyle(color: Colors.black, fontWeight: FontWeight.bold)),
        onPressed: _showMarkAttendanceModal,
      ),
      body: Column(
        children: [
          // Date & Quick Stat Banner
          Container(
            margin: const EdgeInsets.all(16),
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
            decoration: BoxDecoration(
              color: const Color(0xFF1E1E2C),
              borderRadius: BorderRadius.circular(16),
              border: Border.all(color: Colors.white.withOpacity(0.06)),
            ),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Row(
                  children: [
                    const Icon(Icons.today, color: Color(0xFF6C5CE7), size: 20),
                    const SizedBox(width: 8),
                    Text(
                      isToday ? 'Today, ${_selectedDate.toString().split(' ')[0]}' : _selectedDate.toString().split(' ')[0],
                      style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 14),
                    ),
                  ],
                ),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                  decoration: BoxDecoration(
                    color: const Color(0xFF00CEC9).withOpacity(0.15),
                    borderRadius: BorderRadius.circular(8),
                  ),
                  child: Text(
                    '${provider.attendanceLogs.length} Checked In',
                    style: const TextStyle(color: Color(0xFF00CEC9), fontWeight: FontWeight.bold, fontSize: 12),
                  ),
                ),
              ],
            ),
          ),

          // Attendance Log List
          Expanded(
            child: provider.isSectionLoading
                ? const Center(child: CircularProgressIndicator(color: Color(0xFF6C5CE7)))
                : provider.attendanceLogs.isEmpty
                    ? Center(
                        child: Column(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            Icon(Icons.how_to_reg_outlined, size: 70, color: Colors.white.withOpacity(0.3)),
                            const SizedBox(height: 16),
                            const Text('No check-ins for this date', style: TextStyle(color: Colors.white70, fontSize: 16)),
                            const SizedBox(height: 8),
                            ElevatedButton(
                              style: ElevatedButton.styleFrom(backgroundColor: const Color(0xFF00CEC9)),
                              onPressed: _showMarkAttendanceModal,
                              child: const Text('Mark Attendance', style: TextStyle(color: Colors.black, fontWeight: FontWeight.bold)),
                            ),
                          ],
                        ),
                      )
                    : ListView.builder(
                        padding: const EdgeInsets.symmetric(horizontal: 16),
                        itemCount: provider.attendanceLogs.length,
                        itemBuilder: (context, index) {
                          final log = provider.attendanceLogs[index];

                          return Card(
                            color: const Color(0xFF1E1E2C),
                            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
                            margin: const EdgeInsets.only(bottom: 10),
                            child: Padding(
                              padding: const EdgeInsets.all(14),
                              child: Row(
                                children: [
                                  CircleAvatar(
                                    radius: 22,
                                    backgroundColor: const Color(0xFF00CEC9).withOpacity(0.15),
                                    child: const Icon(Icons.person, color: Color(0xFF00CEC9)),
                                  ),
                                  const SizedBox(width: 14),
                                  Expanded(
                                    child: Column(
                                      crossAxisAlignment: CrossAxisAlignment.start,
                                      children: [
                                        Text(
                                          log.fullname,
                                          style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 15, color: Colors.white),
                                        ),
                                        const SizedBox(height: 4),
                                        Row(
                                          children: [
                                            const Icon(Icons.login, size: 14, color: Color(0xFF00CEC9)),
                                            const SizedBox(width: 4),
                                            Text(
                                              'In: ${log.currTime}',
                                              style: const TextStyle(color: Colors.white70, fontSize: 12),
                                            ),
                                            if (log.checkOutTime != null && log.checkOutTime!.isNotEmpty) ...[
                                              const SizedBox(width: 12),
                                              const Icon(Icons.logout, size: 14, color: Color(0xFFFF7675)),
                                              const SizedBox(width: 4),
                                              Text(
                                                'Out: ${log.checkOutTime}',
                                                style: const TextStyle(color: Colors.white70, fontSize: 12),
                                              ),
                                            ],
                                          ],
                                        ),
                                      ],
                                    ),
                                  ),
                                  Container(
                                    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                                    decoration: BoxDecoration(
                                      color: (log.checkOutTime != null && log.checkOutTime!.isNotEmpty)
                                          ? Colors.white10
                                          : const Color(0xFF00CEC9).withOpacity(0.2),
                                      borderRadius: BorderRadius.circular(6),
                                    ),
                                    child: Text(
                                      (log.checkOutTime != null && log.checkOutTime!.isNotEmpty) ? 'Completed' : 'Present',
                                      style: TextStyle(
                                        color: (log.checkOutTime != null && log.checkOutTime!.isNotEmpty)
                                            ? Colors.white60
                                            : const Color(0xFF00CEC9),
                                        fontSize: 11,
                                        fontWeight: FontWeight.bold,
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
