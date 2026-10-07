class DietModuleData {
  final bool hasDietPlan;
  final List<DietPlanDetail> dietPlans;

  DietModuleData({
    required this.hasDietPlan,
    required this.dietPlans,
  });

  factory DietModuleData.fromJson(Map<String, dynamic> json) {
    return DietModuleData(
      hasDietPlan: json['has_diet_plan'] == true,
      dietPlans: (json['diet_plans'] as List? ?? [])
          .map((d) => DietPlanDetail.fromJson(d))
          .toList(),
    );
  }
}

class DietPlanDetail {
  final int assignmentId;
  final int planId;
  final String planName;
  final String target;
  final int calories;
  final String? description;
  final String mealsJson;
  final String nutritionistName;
  final String? trainerContact;
  final String? trainerNotes;
  final String assignedDate;

  DietPlanDetail({
    required this.assignmentId,
    required this.planId,
    required this.planName,
    required this.target,
    required this.calories,
    this.description,
    required this.mealsJson,
    required this.nutritionistName,
    this.trainerContact,
    this.trainerNotes,
    required this.assignedDate,
  });

  factory DietPlanDetail.fromJson(Map<String, dynamic> json) {
    return DietPlanDetail(
      assignmentId: json['assignment_id'] ?? 0,
      planId: json['plan_id'] ?? 0,
      planName: json['plan_name'] ?? 'Diet Routine',
      target: json['target'] ?? 'Healthy Living',
      calories: json['calories'] ?? 2000,
      description: json['description'],
      mealsJson: json['meals_json'] ?? '',
      nutritionistName: json['nutritionist_name'] ?? 'Gym Nutritionist',
      trainerContact: json['trainer_contact'],
      trainerNotes: json['trainer_notes'],
      assignedDate: json['assigned_date'] ?? '',
    );
  }
}
