class WorkoutModuleData {
  final bool hasWorkoutPlan;
  final List<WorkoutPlanDetail> plans;
  final List<WorkoutTodoItem> todos;

  WorkoutModuleData({
    required this.hasWorkoutPlan,
    required this.plans,
    required this.todos,
  });

  factory WorkoutModuleData.fromJson(Map<String, dynamic> json) {
    return WorkoutModuleData(
      hasWorkoutPlan: json['has_workout_plan'] == true,
      plans: (json['plans'] as List? ?? [])
          .map((p) => WorkoutPlanDetail.fromJson(p))
          .toList(),
      todos: (json['todos'] as List? ?? [])
          .map((t) => WorkoutTodoItem.fromJson(t))
          .toList(),
    );
  }
}

class WorkoutPlanDetail {
  final int assignmentId;
  final int planId;
  final String planName;
  final String goal;
  final String level;
  final String? description;
  final String scheduleJson;
  final String trainerName;
  final String? trainerContact;
  final String? trainerNotes;
  final String assignedDate;

  WorkoutPlanDetail({
    required this.assignmentId,
    required this.planId,
    required this.planName,
    required this.goal,
    required this.level,
    this.description,
    required this.scheduleJson,
    required this.trainerName,
    this.trainerContact,
    this.trainerNotes,
    required this.assignedDate,
  });

  factory WorkoutPlanDetail.fromJson(Map<String, dynamic> json) {
    return WorkoutPlanDetail(
      assignmentId: json['assignment_id'] ?? 0,
      planId: json['plan_id'] ?? 0,
      planName: json['plan_name'] ?? 'Workout Plan',
      goal: json['goal'] ?? 'Fitness',
      level: json['level'] ?? 'Beginner',
      description: json['description'],
      scheduleJson: json['schedule_json'] ?? '',
      trainerName: json['trainer_name'] ?? 'Personal Trainer',
      trainerContact: json['trainer_contact'],
      trainerNotes: json['trainer_notes'],
      assignedDate: json['assigned_date'] ?? '',
    );
  }
}

class WorkoutTodoItem {
  final int id;
  final String taskDesc;
  final bool isCompleted;
  final String status;

  WorkoutTodoItem({
    required this.id,
    required this.taskDesc,
    required this.isCompleted,
    required this.status,
  });

  factory WorkoutTodoItem.fromJson(Map<String, dynamic> json) {
    return WorkoutTodoItem(
      id: json['id'] ?? 0,
      taskDesc: json['task_desc'] ?? '',
      isCompleted: json['is_completed'] == true || json['status'] == 'Completed',
      status: json['status'] ?? 'Pending',
    );
  }
}
