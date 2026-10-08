import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import '../../models/admin_models.dart';
import '../../providers/admin_provider.dart';

class AdminFitnessPlansScreen extends StatefulWidget {
  const AdminFitnessPlansScreen({super.key});

  @override
  State<AdminFitnessPlansScreen> createState() => _AdminFitnessPlansScreenState();
}

class _AdminFitnessPlansScreenState extends State<AdminFitnessPlansScreen> with SingleTickerProviderStateMixin {
  late TabController _tabController;

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: 2, vsync: this);
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<AdminProvider>().fetchWorkouts();
      context.read<AdminProvider>().fetchDiet();
      context.read<AdminProvider>().fetchMembers();
    });
  }

  @override
  void dispose() {
    _tabController.dispose();
    super.dispose();
  }

  void _showAddWorkoutModal() {
    final nameCtrl = TextEditingController();
    final goalCtrl = TextEditingController(text: 'Muscle Building');
    final descCtrl = TextEditingController();
    final daysCtrl = TextEditingController(text: 'Day 1: Chest & Triceps\nDay 2: Back & Biceps\nDay 3: Legs & Core\nDay 4: Shoulders & Abs\nDay 5: HIIT & Arms');

    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (ctx) => Container(
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
                    color: Colors.white24,
                    borderRadius: BorderRadius.circular(2),
                  ),
                ),
              ),
              const SizedBox(height: 16),
              const Text('Create Workout Routine', style: TextStyle(fontSize: 20, fontWeight: FontWeight.bold, color: Colors.white)),
              const SizedBox(height: 16),
              TextField(
                controller: nameCtrl,
                style: const TextStyle(color: Colors.white),
                decoration: InputDecoration(
                  labelText: 'Routine Name *',
                  labelStyle: const TextStyle(color: Colors.white70),
                  filled: true,
                  fillColor: Colors.white.withOpacity(0.05),
                  border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
                ),
              ),
              const SizedBox(height: 12),
              TextField(
                controller: goalCtrl,
                style: const TextStyle(color: Colors.white),
                decoration: InputDecoration(
                  labelText: 'Primary Goal',
                  labelStyle: const TextStyle(color: Colors.white70),
                  filled: true,
                  fillColor: Colors.white.withOpacity(0.05),
                  border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
                ),
              ),
              const SizedBox(height: 12),
              TextField(
                controller: daysCtrl,
                maxLines: 4,
                style: const TextStyle(color: Colors.white),
                decoration: InputDecoration(
                  labelText: 'Weekly Split & Exercises *',
                  labelStyle: const TextStyle(color: Colors.white70),
                  filled: true,
                  fillColor: Colors.white.withOpacity(0.05),
                  border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
                ),
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
                    if (nameCtrl.text.trim().isEmpty) return;
                    Navigator.pop(ctx);
                    await context.read<AdminProvider>().addWorkout(
                      name: nameCtrl.text.trim(),
                      goal: goalCtrl.text.trim(),
                      daysJson: daysCtrl.text.trim(),
                      exercisesJson: '',
                      description: descCtrl.text.trim(),
                    );
                  },
                  child: const Text('Save Workout Plan', style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold, color: Colors.white)),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  void _showAddDietModal() {
    final nameCtrl = TextEditingController();
    final targetCtrl = TextEditingController(text: 'High Protein / Lean Mass');
    final calCtrl = TextEditingController(text: '2400');
    final mealsCtrl = TextEditingController(text: 'Breakfast: 4 Eggs + Oatmeal\nLunch: 200g Chicken + Brown Rice\nSnack: Whey Protein + Almonds\nDinner: Fish/Paneer + Veggies');

    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (ctx) => Container(
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
                    color: Colors.white24,
                    borderRadius: BorderRadius.circular(2),
                  ),
                ),
              ),
              const SizedBox(height: 16),
              const Text('Create Diet Plan', style: TextStyle(fontSize: 20, fontWeight: FontWeight.bold, color: Colors.white)),
              const SizedBox(height: 16),
              TextField(
                controller: nameCtrl,
                style: const TextStyle(color: Colors.white),
                decoration: InputDecoration(
                  labelText: 'Plan Name *',
                  labelStyle: const TextStyle(color: Colors.white70),
                  filled: true,
                  fillColor: Colors.white.withOpacity(0.05),
                  border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
                ),
              ),
              const SizedBox(height: 12),
              Row(
                children: [
                  Expanded(
                    child: TextField(
                      controller: targetCtrl,
                      style: const TextStyle(color: Colors.white),
                      decoration: InputDecoration(
                        labelText: 'Target Goal',
                        labelStyle: const TextStyle(color: Colors.white70),
                        filled: true,
                        fillColor: Colors.white.withOpacity(0.05),
                        border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
                      ),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: TextField(
                      controller: calCtrl,
                      keyboardType: TextInputType.number,
                      style: const TextStyle(color: Colors.white),
                      decoration: InputDecoration(
                        labelText: 'Calories (kcal)',
                        labelStyle: const TextStyle(color: Colors.white70),
                        filled: true,
                        fillColor: Colors.white.withOpacity(0.05),
                        border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
                      ),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 12),
              TextField(
                controller: mealsCtrl,
                maxLines: 4,
                style: const TextStyle(color: Colors.white),
                decoration: InputDecoration(
                  labelText: 'Meal Breakdown (Breakfast, Lunch, Dinner...) *',
                  labelStyle: const TextStyle(color: Colors.white70),
                  filled: true,
                  fillColor: Colors.white.withOpacity(0.05),
                  border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
                ),
              ),
              const SizedBox(height: 20),
              SizedBox(
                width: double.infinity,
                height: 50,
                child: ElevatedButton(
                  style: ElevatedButton.styleFrom(
                    backgroundColor: const Color(0xFF00CEC9),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                  ),
                  onPressed: () async {
                    if (nameCtrl.text.trim().isEmpty) return;
                    Navigator.pop(ctx);
                    await context.read<AdminProvider>().addDiet(
                      name: nameCtrl.text.trim(),
                      target: targetCtrl.text.trim(),
                      calories: int.tryParse(calCtrl.text.trim()) ?? 2000,
                      meals: mealsCtrl.text.trim(),
                    );
                  },
                  child: const Text('Save Diet Plan', style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold, color: Colors.black)),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  void _showAssignModal({required bool isWorkout, required int planId, required String planName}) {
    final provider = context.read<AdminProvider>();
    int? selectedMemberId = provider.members.isNotEmpty ? provider.members.first.memberId : null;
    final notesCtrl = TextEditingController();

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
                      color: Colors.white24,
                      borderRadius: BorderRadius.circular(2),
                    ),
                  ),
                ),
                const SizedBox(height: 16),
                Text(
                  'Assign "$planName"',
                  style: const TextStyle(fontSize: 18, fontWeight: FontWeight.bold, color: Colors.white),
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
                const SizedBox(height: 12),
                TextField(
                  controller: notesCtrl,
                  style: const TextStyle(color: Colors.white),
                  decoration: InputDecoration(
                    labelText: 'Trainer Instructions / Notes',
                    labelStyle: const TextStyle(color: Colors.white70),
                    filled: true,
                    fillColor: Colors.white.withOpacity(0.05),
                    border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
                  ),
                ),
                const SizedBox(height: 20),
                SizedBox(
                  width: double.infinity,
                  height: 50,
                  child: ElevatedButton(
                    style: ElevatedButton.styleFrom(
                      backgroundColor: isWorkout ? const Color(0xFF6C5CE7) : const Color(0xFF00CEC9),
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                    ),
                    onPressed: () async {
                      if (selectedMemberId == null) return;
                      Navigator.pop(ctx);
                      if (isWorkout) {
                        await context.read<AdminProvider>().assignWorkout(
                          memberId: selectedMemberId!,
                          workoutPlanId: planId,
                          notes: notesCtrl.text.trim(),
                        );
                      } else {
                        await context.read<AdminProvider>().assignDiet(
                          memberId: selectedMemberId!,
                          dietPlanId: planId,
                          notes: notesCtrl.text.trim(),
                        );
                      }
                      if (mounted) {
                        ScaffoldMessenger.of(context).showSnackBar(
                          SnackBar(content: Text('Assigned $planName to member successfully!')),
                        );
                      }
                    },
                    child: Text('Confirm Assignment', style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold, color: isWorkout ? Colors.white : Colors.black)),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final provider = context.watch<AdminProvider>();

    return Scaffold(
      backgroundColor: const Color(0xFF13131A),
      appBar: AppBar(
        backgroundColor: const Color(0xFF1E1E2C),
        elevation: 0,
        title: const Text('Workout & Diet Plans', style: TextStyle(fontWeight: FontWeight.bold, color: Colors.white)),
        bottom: TabBar(
          controller: _tabController,
          indicatorColor: const Color(0xFF6C5CE7),
          indicatorWeight: 3,
          labelColor: Colors.white,
          unselectedLabelColor: Colors.white60,
          tabs: const [
            Tab(icon: Icon(Icons.fitness_center), text: 'Workout Plans'),
            Tab(icon: Icon(Icons.restaurant_menu), text: 'Diet Plans'),
          ],
        ),
      ),
      floatingActionButton: FloatingActionButton.extended(
        backgroundColor: const Color(0xFF6C5CE7),
        icon: const Icon(Icons.add, color: Colors.white),
        label: const Text('Create Plan', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold)),
        onPressed: () {
          if (_tabController.index == 0) {
            _showAddWorkoutModal();
          } else {
            _showAddDietModal();
          }
        },
      ),
      body: TabBarView(
        controller: _tabController,
        children: [
          // 1. Workouts List
          provider.workoutPlans.isEmpty
              ? Center(
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Icon(Icons.fitness_center, size: 70, color: Colors.white.withOpacity(0.3)),
                      const SizedBox(height: 16),
                      const Text('No workout plans found', style: TextStyle(color: Colors.white70, fontSize: 16)),
                      const SizedBox(height: 8),
                      ElevatedButton(
                        style: ElevatedButton.styleFrom(backgroundColor: const Color(0xFF6C5CE7)),
                        onPressed: _showAddWorkoutModal,
                        child: const Text('Create Workout Plan', style: TextStyle(color: Colors.white)),
                      ),
                    ],
                  ),
                )
              : ListView.builder(
                  padding: const EdgeInsets.all(16),
                  itemCount: provider.workoutPlans.length,
                  itemBuilder: (context, index) {
                    final wp = provider.workoutPlans[index];

                    return Card(
                      color: const Color(0xFF1E1E2C),
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
                      margin: const EdgeInsets.only(bottom: 12),
                      child: Padding(
                        padding: const EdgeInsets.all(16),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Row(
                              mainAxisAlignment: MainAxisAlignment.spaceBetween,
                              children: [
                                Expanded(
                                  child: Text(
                                    wp.name,
                                    style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16, color: Colors.white),
                                  ),
                                ),
                                Container(
                                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                                  decoration: BoxDecoration(
                                    color: const Color(0xFF6C5CE7).withOpacity(0.2),
                                    borderRadius: BorderRadius.circular(6),
                                  ),
                                  child: Text(
                                    wp.goal,
                                    style: const TextStyle(color: Color(0xFF6C5CE7), fontSize: 11, fontWeight: FontWeight.bold),
                                  ),
                                ),
                              ],
                            ),
                            if (wp.daysJson.isNotEmpty) ...[
                              const SizedBox(height: 10),
                              Container(
                                width: double.infinity,
                                padding: const EdgeInsets.all(12),
                                decoration: BoxDecoration(
                                  color: Colors.white.withOpacity(0.04),
                                  borderRadius: BorderRadius.circular(10),
                                ),
                                child: Text(
                                  wp.daysJson,
                                  style: const TextStyle(color: Colors.white70, fontSize: 13, height: 1.4),
                                ),
                              ),
                            ],
                            const SizedBox(height: 12),
                            Row(
                              mainAxisAlignment: MainAxisAlignment.end,
                              children: [
                                OutlinedButton.icon(
                                  style: OutlinedButton.styleFrom(
                                    foregroundColor: const Color(0xFF6C5CE7),
                                    side: const BorderSide(color: Color(0xFF6C5CE7)),
                                  ),
                                  icon: const Icon(Icons.person_add, size: 16),
                                  label: const Text('Assign to Member'),
                                  onPressed: () => _showAssignModal(isWorkout: true, planId: wp.id, planName: wp.name),
                                ),
                                const SizedBox(width: 8),
                                IconButton(
                                  icon: const Icon(Icons.delete_outline, color: Colors.white30, size: 20),
                                  onPressed: () => provider.deleteWorkout(wp.id),
                                ),
                              ],
                            ),
                          ],
                        ),
                      ),
                    );
                  },
                ),

          // 2. Diet Plans List
          provider.dietPlans.isEmpty
              ? Center(
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Icon(Icons.restaurant_menu, size: 70, color: Colors.white.withOpacity(0.3)),
                      const SizedBox(height: 16),
                      const Text('No diet plans found', style: TextStyle(color: Colors.white70, fontSize: 16)),
                      const SizedBox(height: 8),
                      ElevatedButton(
                        style: ElevatedButton.styleFrom(backgroundColor: const Color(0xFF00CEC9)),
                        onPressed: _showAddDietModal,
                        child: const Text('Create Diet Plan', style: TextStyle(color: Colors.black, fontWeight: FontWeight.bold)),
                      ),
                    ],
                  ),
                )
              : ListView.builder(
                  padding: const EdgeInsets.all(16),
                  itemCount: provider.dietPlans.length,
                  itemBuilder: (context, index) {
                    final dp = provider.dietPlans[index];

                    return Card(
                      color: const Color(0xFF1E1E2C),
                      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
                      margin: const EdgeInsets.only(bottom: 12),
                      child: Padding(
                        padding: const EdgeInsets.all(16),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Row(
                              mainAxisAlignment: MainAxisAlignment.spaceBetween,
                              children: [
                                Expanded(
                                  child: Text(
                                    dp.name,
                                    style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16, color: Colors.white),
                                  ),
                                ),
                                Container(
                                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                                  decoration: BoxDecoration(
                                    color: const Color(0xFF00CEC9).withOpacity(0.2),
                                    borderRadius: BorderRadius.circular(6),
                                  ),
                                  child: Text(
                                    '${dp.calories} kcal',
                                    style: const TextStyle(color: Color(0xFF00CEC9), fontSize: 11, fontWeight: FontWeight.bold),
                                  ),
                                ),
                              ],
                            ),
                            if (dp.mealsJson.isNotEmpty) ...[
                              const SizedBox(height: 10),
                              Container(
                                width: double.infinity,
                                padding: const EdgeInsets.all(12),
                                decoration: BoxDecoration(
                                  color: Colors.white.withOpacity(0.04),
                                  borderRadius: BorderRadius.circular(10),
                                ),
                                child: Text(
                                  dp.mealsJson,
                                  style: const TextStyle(color: Colors.white70, fontSize: 13, height: 1.4),
                                ),
                              ),
                            ],
                            const SizedBox(height: 12),
                            Row(
                              mainAxisAlignment: MainAxisAlignment.end,
                              children: [
                                OutlinedButton.icon(
                                  style: OutlinedButton.styleFrom(
                                    foregroundColor: const Color(0xFF00CEC9),
                                    side: const BorderSide(color: Color(0xFF00CEC9)),
                                  ),
                                  icon: const Icon(Icons.person_add, size: 16),
                                  label: const Text('Assign to Member'),
                                  onPressed: () => _showAssignModal(isWorkout: false, planId: dp.id, planName: dp.name),
                                ),
                                const SizedBox(width: 8),
                                IconButton(
                                  icon: const Icon(Icons.delete_outline, color: Colors.white30, size: 20),
                                  onPressed: () => provider.deleteDiet(dp.id),
                                ),
                              ],
                            ),
                          ],
                        ),
                      ),
                    );
                  },
                ),
        ],
      ),
    );
  }
}
