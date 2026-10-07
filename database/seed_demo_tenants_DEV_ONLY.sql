-- DEVELOPMENT / DEMO DATA ONLY - never run this on production.
-- Creates demo gyms GYM-A / GYM-B whose accounts all use the publicly known password 'password'.
-- (Moved out of migration_flutter_member_app.sql so running migrations never seeds these accounts.)

-- 6. Seed Test Tenants for Multi-Tenant Isolation Verification:
-- Gym A (Fitisify Fitness Gym - Code: GYM-A)
-- Gym B (Apex Elite Gym - Code: GYM-B)
INSERT INTO `tenants` (`id`, `gym_code`, `gym_name`, `slug`, `owner_name`, `email`, `phone`, `address`, `subscription_plan_id`, `subscription_start`, `subscription_expiry`, `status`, `currency`, `timezone`, `primary_color`, `secondary_color`, `invoice_header`, `invoice_footer`) VALUES
(101, 'GYM-A', 'Fitisify Fitness Gym A', 'fitisify-gym-a', 'Alexander Pierce', 'contact@gyma.com', '+1 (555) 019-2831', '742 Evergreen Terrace, Sector 4', 3, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 1 YEAR), 'active', '$', 'America/New_York', '#2563eb', '#10b981', 'FITISIFY FITNESS GYM A', 'Thank you for training with us at Gym A!'),
(102, 'GYM-B', 'Apex Elite Gym B', 'apex-elite-gym-b', 'Sophia Martinez', 'support@gym-b.com', '+1 (555) 084-9122', '1200 Grand Avenue, Suite 100', 3, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 1 YEAR), 'active', '₹', 'Asia/Kolkata', '#7c3aed', '#f59e0b', 'APEX ELITE GYM B', 'Transforming lives every day at Gym B!')
ON DUPLICATE KEY UPDATE 
`gym_code` = VALUES(`gym_code`),
`gym_name` = VALUES(`gym_name`),
`primary_color` = VALUES(`primary_color`),
`secondary_color` = VALUES(`secondary_color`),
`currency` = VALUES(`currency`),
`status` = 'active';

-- Ensure Branches for Gym A and Gym B
INSERT INTO `branches` (`id`, `tenant_id`, `branch_name`, `address`, `phone`, `email`, `is_main`, `status`) VALUES
(101, 101, 'Gym A Main Branch', '742 Evergreen Terrace, Sector 4', '+1 (555) 019-2831', 'branch@gyma.com', 1, 'active'),
(102, 102, 'Gym B Elite Studio', '1200 Grand Avenue, Suite 100', '+1 (555) 084-9122', 'branch@gym-b.com', 1, 'active')
ON DUPLICATE KEY UPDATE `branch_name` = VALUES(`branch_name`);

-- Trainers for Gym A and Gym B
INSERT INTO `staffs` (`user_id`, `tenant_id`, `branch_id`, `username`, `password`, `email`, `fullname`, `address`, `designation`, `gender`, `contact`, `role`, `status`) VALUES
(101, 101, 101, 'trainer_a', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'trainer_a@gyma.com', 'Marcus Strong', 'Gym A Quarters', 'Trainer', 'Male', 2147483647, 'trainer', 'active'),
(102, 102, 102, 'trainer_b', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'trainer_b@gym-b.com', 'Elena Rossi', 'Gym B Quarters', 'Trainer', 'Female', 2147483647, 'trainer', 'active')
ON DUPLICATE KEY UPDATE `fullname` = VALUES(`fullname`);

-- Workout & Diet Plans for Gym A and Gym B
INSERT INTO `workout_plans` (`id`, `tenant_id`, `name`, `goal`, `level`, `description`, `schedule_json`, `created_by`) VALUES
(101, 101, 'Gym A 4-Day Hypertrophy Split', 'Muscle Gain', 'Intermediate', 'Focus on upper/lower body strength and mass.', 'Day 1: Chest & Triceps (Bench Press 4x8, Incline Dumbbell 3x10, Dips 3x12, Rope Pushdowns 4x12)\nDay 2: Back & Biceps (Deadlifts 4x6, Pull-ups 3x10, Barbell Rows 4x8, Hammer Curls 3x12)\nDay 3: Rest / Active Recovery\nDay 4: Legs & Core (Barbell Squats 4x8, Leg Press 3x12, Romanian Deadlift 3x10, Planks 3x60s)\nDay 5: Shoulders & Arms (Overhead Press 4x8, Lateral Raises 4x15, Skull Crushers 3x10, Preacher Curls 3x10)', 101),
(102, 102, 'Gym B High-Intensity Lean Cut', 'Fat Loss & Conditioning', 'Advanced', 'Metabolic conditioning and functional strength.', 'Day 1: Full Body HIIT & Kettlebells (KB Swings 5x20, Box Jumps 4x12, Burpees 4x15, Row Sprint 500m)\nDay 2: Push Power & Core (Push-ups 4x20, DB Shoulder Press 4x10, Hanging Leg Raises 4x15)\nDay 3: Pull & Posterior Chain (Lat Pulldown 4x12, Cable Rows 4x12, Face Pulls 4x15)\nDay 4: Rest & Mobility\nDay 5: Lower Body Burn (Walking Lunges 4x20, Goblet Squats 4x15, Box Step-ups 4x12, Sled Push 4x20m)', 102)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `schedule_json` = VALUES(`schedule_json`);

INSERT INTO `diet_plans` (`id`, `tenant_id`, `name`, `target`, `calories`, `description`, `meals_json`, `created_by`) VALUES
(101, 101, 'Gym A High-Protein Bulk', 'Clean Bulk', 2800, 'Macro breakdown: 40% Carbs, 35% Protein, 25% Fats', '08:00 AM - Breakfast: 4 Whole Eggs + 2 Egg Whites, 1 Cup Oatmeal with Berries & Almond Butter\n11:30 AM - Mid-Day: Whey Protein Shake + 1 Banana + Handful of Walnuts\n02:00 PM - Lunch: 200g Grilled Chicken Breast + 1.5 Cup Jasmine Rice + Steamed Broccoli\n05:30 PM - Pre-Workout: Rice Cakes + Peanut Butter + Black Coffee\n08:30 PM - Dinner: 200g Salmon / Lean Beef + Sweet Potato Mash + Mixed Green Salad', 101),
(102, 102, 'Gym B Lean Shred & Keto-Style', 'Fat Loss & Definition', 1950, 'Macro breakdown: 15% Carbs, 45% Protein, 40% Healthy Fats', '08:30 AM - Breakfast: Avocado Toast with 3 Poached Eggs & Spinach\n12:00 PM - Snack: Greek Yogurt with Chia Seeds & Protein Powder\n02:30 PM - Lunch: Grilled Salmon Bowl with Quinoa, Cucumber & Olive Oil Dressing\n06:00 PM - Pre-Workout: Green Tea + Apple with Almond Butter\n08:30 PM - Dinner: Grilled Chicken Salad with Mixed Greens, Cherry Tomatoes & Pumpkin Seeds', 102)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `meals_json` = VALUES(`meals_json`);

-- Seed Duplicate Member ID in Gym A (david_gyma) and Gym B (david_gymb)
-- Password for both: 'password' (bcrypt hash $2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi)
INSERT INTO `members` (`user_id`, `tenant_id`, `branch_id`, `fullname`, `username`, `password`, `gender`, `dor`, `services`, `amount`, `paid_date`, `p_year`, `plan`, `address`, `contact`, `status`, `attendance_count`, `ini_weight`, `curr_weight`, `ini_bodytype`, `curr_bodytype`, `progress_date`, `email`, `trainer_id`) VALUES
(101, 101, 101, 'David Miller (Gym A Member)', 'david_gyma', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Male', CURDATE(), 'Gold All-Access Gym A', 120, CURDATE(), YEAR(CURDATE()), '6', '742 Evergreen Terrace, Apt 4B', '5551234567', 'Active', 18, 75, 78, 'Lean', 'Athletic', CURDATE(), 'david@gyma.com', 101),
(201, 102, 102, 'David Miller (Gym B Member)', 'david_gymb', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Male', CURDATE(), 'Platinum VIP Gym B', 250, CURDATE(), YEAR(CURDATE()), '12', '1200 Grand Ave, Suite 3B', '5559876543', 'Active', 25, 82, 77, 'Overweight', 'Toned', CURDATE(), 'david@gym-b.com', 102)
ON DUPLICATE KEY UPDATE `fullname` = VALUES(`fullname`), `services` = VALUES(`services`), `status` = 'Active';

-- Add unified users for David Miller in Gym A and Gym B
INSERT INTO `users` (`id`, `tenant_id`, `branch_id`, `role`, `username`, `password`, `email`, `fullname`, `phone`, `status`, `member_id`) VALUES
(101, 101, 101, 'member', 'david_gyma', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'david@gyma.com', 'David Miller (Gym A Member)', '5551234567', 'active', 101),
(201, 102, 102, 'member', 'david_gymb', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'david@gym-b.com', 'David Miller (Gym B Member)', '5559876543', 'active', 201)
ON DUPLICATE KEY UPDATE `fullname` = VALUES(`fullname`), `status` = 'active';

-- Assign Plans to members
INSERT INTO `member_assigned_plans` (`id`, `tenant_id`, `member_id`, `workout_plan_id`, `diet_plan_id`, `trainer_id`, `assigned_date`, `notes`, `status`) VALUES
(101, 101, 101, 101, 101, 101, CURDATE(), 'Maintain progressive overload on compound lifts. Gym A Plan.', 'active'),
(102, 102, 201, 102, 102, 102, CURDATE(), 'Track heart rate during HIIT sessions. Gym B Plan.', 'active')
ON DUPLICATE KEY UPDATE `status` = 'active';

-- Invoices for Gym A and Gym B
INSERT INTO `invoices` (`id`, `tenant_id`, `branch_id`, `invoice_number`, `member_id`, `amount`, `paid_amount`, `discount`, `plan_months`, `service_name`, `payment_method`, `payment_date`, `status`, `transaction_ref`, `notes`) VALUES
(101, 101, 101, 'INV-GYMA-1001', 101, 120.00, 120.00, 0.00, 6, 'Gold All-Access Gym A', 'Credit Card', CURDATE(), 'Paid', 'TXN_GYMA_88291', 'Gym A 6-month membership payment verified.'),
(102, 102, 102, 'INV-GYMB-2001', 201, 250.00, 250.00, 0.00, 12, 'Platinum VIP Gym B', 'UPI / Online', CURDATE(), 'Paid', 'TXN_GYMB_99381', 'Gym B 1-year annual subscription receipt.')
ON DUPLICATE KEY UPDATE `status` = 'Paid';

-- Attendance records for Gym A and Gym B
INSERT INTO `attendance` (`id`, `tenant_id`, `branch_id`, `user_id`, `curr_date`, `curr_time`, `check_out_time`, `present`) VALUES
(101, 101, 101, '101', CURDATE(), '07:30 AM', '08:45 AM', 1),
(102, 102, 102, '201', CURDATE(), '06:15 PM', '07:45 PM', 1)
ON DUPLICATE KEY UPDATE `present` = 1;

-- Announcements for Gym A and Gym B
INSERT INTO `announcements` (`id`, `tenant_id`, `message`, `date`) VALUES
(101, 101, 'Welcome to Gym A! Join our Saturday Powerlifting workshop at 10:00 AM.', CURDATE()),
(102, 102, 'Gym B Announcement: New Olympic lifting platforms and sauna hours updated for members.', CURDATE())
ON DUPLICATE KEY UPDATE `message` = VALUES(`message`);

COMMIT;
