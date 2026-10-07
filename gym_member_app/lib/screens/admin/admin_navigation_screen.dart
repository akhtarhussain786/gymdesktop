import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../providers/auth_provider.dart';
import 'admin_add_member_screen.dart';
import 'admin_dashboard_screen.dart';
import 'admin_gym_qr_screen.dart';
import 'admin_members_screen.dart';

class AdminNavigationScreen extends StatefulWidget {
  const AdminNavigationScreen({super.key});

  @override
  State<AdminNavigationScreen> createState() => _AdminNavigationScreenState();
}

class _AdminNavigationScreenState extends State<AdminNavigationScreen> {
  int _currentIndex = 0;

  void _onTabChanged(int index) {
    setState(() {
      _currentIndex = index;
    });
  }

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthProvider>();

    final screens = [
      AdminDashboardScreen(onNavigateTab: _onTabChanged),
      const AdminMembersScreen(),
      const AdminAddMemberScreen(),
      const AdminGymQrScreen(),
    ];

    return Scaffold(
      drawer: Drawer(
        child: ListView(
          padding: EdgeInsets.zero,
          children: [
            UserAccountsDrawerHeader(
              decoration: const BoxDecoration(
                color: Color(0xFF1E293B),
              ),
              currentAccountPicture: CircleAvatar(
                backgroundColor: const Color(0xFF3B82F6),
                child: Text(
                  auth.adminUser?.fullname.isNotEmpty == true ? auth.adminUser!.fullname[0].toUpperCase() : 'A',
                  style: const TextStyle(color: Colors.white, fontWeight: FontWeight.bold, fontSize: 24),
                ),
              ),
              accountName: Text(
                auth.adminUser?.fullname ?? 'Gym Owner / Admin',
                style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
              ),
              accountEmail: Text(
                '${auth.currentTenant?.gymName ?? 'Gym'} • Role: ${auth.role.toUpperCase()}',
                style: const TextStyle(color: Color(0xFF10B981)),
              ),
            ),
            ListTile(
              leading: const Icon(Icons.dashboard, color: Color(0xFF3B82F6)),
              title: const Text('Dashboard'),
              selected: _currentIndex == 0,
              onTap: () {
                Navigator.pop(context);
                _onTabChanged(0);
              },
            ),
            ListTile(
              leading: const Icon(Icons.people_alt, color: Color(0xFF8B5CF6)),
              title: const Text('Members Directory'),
              selected: _currentIndex == 1,
              onTap: () {
                Navigator.pop(context);
                _onTabChanged(1);
              },
            ),
            ListTile(
              leading: const Icon(Icons.person_add_alt_1, color: Color(0xFF10B981)),
              title: const Text('Add Member (With Photo)'),
              selected: _currentIndex == 2,
              onTap: () {
                Navigator.pop(context);
                _onTabChanged(2);
              },
            ),
            ListTile(
              leading: const Icon(Icons.qr_code_2, color: Color(0xFFF59E0B)),
              title: const Text('Gym UPI QR Code'),
              selected: _currentIndex == 3,
              onTap: () {
                Navigator.pop(context);
                _onTabChanged(3);
              },
            ),
            const Divider(),
            ListTile(
              leading: const Icon(Icons.logout, color: Colors.red),
              title: const Text('Logout', style: TextStyle(color: Colors.red, fontWeight: FontWeight.bold)),
              onTap: () {
                Navigator.pop(context);
                showDialog(
                  context: context,
                  builder: (ctx) => AlertDialog(
                    title: const Text('Confirm Logout'),
                    content: const Text('Are you sure you want to log out of Admin Console?'),
                    actions: [
                      TextButton(
                        onPressed: () => Navigator.pop(ctx),
                        child: const Text('Cancel'),
                      ),
                      ElevatedButton(
                        style: ElevatedButton.styleFrom(backgroundColor: Colors.red, foregroundColor: Colors.white),
                        onPressed: () {
                          Navigator.pop(ctx);
                          auth.logout();
                        },
                        child: const Text('Logout'),
                      ),
                    ],
                  ),
                );
              },
            ),
          ],
        ),
      ),
      body: IndexedStack(
        index: _currentIndex,
        children: screens,
      ),
      bottomNavigationBar: NavigationBar(
        selectedIndex: _currentIndex,
        onDestinationSelected: _onTabChanged,
        destinations: const [
          NavigationDestination(
            icon: Icon(Icons.dashboard_outlined),
            selectedIcon: Icon(Icons.dashboard, color: Color(0xFF3B82F6)),
            label: 'Home',
          ),
          NavigationDestination(
            icon: Icon(Icons.people_outline),
            selectedIcon: Icon(Icons.people, color: Color(0xFF3B82F6)),
            label: 'Members',
          ),
          NavigationDestination(
            icon: Icon(Icons.person_add_outlined),
            selectedIcon: Icon(Icons.person_add, color: Color(0xFF10B981)),
            label: 'Add Member',
          ),
          NavigationDestination(
            icon: Icon(Icons.qr_code_outlined),
            selectedIcon: Icon(Icons.qr_code_2, color: Color(0xFFF59E0B)),
            label: 'Gym QR',
          ),
        ],
      ),
    );
  }
}
