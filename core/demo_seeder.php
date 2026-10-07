<?php
/**
 * Core Demo Seeder & Database Sanitation Engine
 * 
 * Provides automated tools for:
 * 1. Purging orphaned child rows with non-existent tenant_ids across all database tables.
 * 2. Seeding or resetting a pristine, showcase-ready "DEMO FITNESS & GYM" with realistic members, trainers, plans, and invoices for client demonstrations.
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/tenant.php';

class DemoSeeder {

    /**
     * List of all tables having a tenant_id column
     */
    private static $tenantTables = [
        'member_tokens',
        'device_tokens',
        'attendance',
        'member_subscriptions',
        'membership_payments',
        'member_workout_todos',
        'member_assigned_plans',
        'member_inquiries',
        'workout_plans',
        'diet_plans',
        'class_bookings',
        'classes',
        'invoices',
        'expenses',
        'todo',
        'reminder',
        'rates',
        'equipment',
        'announcements',
        'staffs',
        'trainers',
        'branches',
        'members'
    ];

    /**
     * Purge all dangling records that belong to non-existent tenants
     * 
     * @return array Summary of deleted rows per table and total count
     */
    public static function purgeOrphans() {
        $report = [];
        $totalDeleted = 0;

        // Get list of existing tenant IDs
        $existingTenants = DB::fetchAll("SELECT id FROM tenants");
        $tenantIds = array_map(function($t) { return (int)$t['id']; }, $existingTenants);

        DB::beginTransaction();
        try {
            foreach (self::$tenantTables as $tbl) {
                // Check if table exists
                $tableExists = (int)DB::fetchValue("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?", [$tbl]);
                if (!$tableExists) {
                    continue;
                }

                if (empty($tenantIds)) {
                    // No tenants exist: delete all records from tenant-specific tables
                    $result = DB::query("DELETE FROM `{$tbl}`");
                } else {
                    $inClause = implode(',', $tenantIds);
                    $result = DB::query("DELETE FROM `{$tbl}` WHERE tenant_id NOT IN ({$inClause}) OR tenant_id IS NULL");
                }

                $deleted = (int)($result['affected'] ?? 0);
                if ($deleted > 0) {
                    $report[$tbl] = $deleted;
                    $totalDeleted += $deleted;
                }
            }

            // Clean users (keep super_admin)
            if (empty($tenantIds)) {
                $userResult = DB::query("DELETE FROM users WHERE role NOT IN ('super_admin', 'superadmin')");
            } else {
                $inClause = implode(',', $tenantIds);
                $userResult = DB::query("DELETE FROM users WHERE role NOT IN ('super_admin', 'superadmin') AND (tenant_id NOT IN ({$inClause}) OR tenant_id IS NULL)");
            }
            $userDeleted = (int)($userResult['affected'] ?? 0);
            if ($userDeleted > 0) {
                $report['users'] = $userDeleted;
                $totalDeleted += $userDeleted;
            }

            // Clean password resets for non-existent users
            DB::query("DELETE FROM password_resets WHERE user_id NOT IN (SELECT id FROM users)");

            DB::commit();
        } catch (Exception $e) {
            DB::rollback();
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'total_deleted' => 0,
                'breakdown' => []
            ];
        }

        return [
            'success' => true,
            'total_deleted' => $totalDeleted,
            'breakdown' => $report
        ];
    }

    /**
     * Get count of orphaned rows in the database
     */
    public static function countOrphans() {
        $existingTenants = DB::fetchAll("SELECT id FROM tenants");
        $tenantIds = array_map(function($t) { return (int)$t['id']; }, $existingTenants);
        $totalOrphans = 0;

        foreach (self::$tenantTables as $tbl) {
            $tableExists = (int)DB::fetchValue("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?", [$tbl]);
            if (!$tableExists) {
                continue;
            }

            if (empty($tenantIds)) {
                $count = (int)DB::fetchValue("SELECT COUNT(*) FROM `{$tbl}`");
            } else {
                $inClause = implode(',', $tenantIds);
                $count = (int)DB::fetchValue("SELECT COUNT(*) FROM `{$tbl}` WHERE tenant_id NOT IN ({$inClause}) OR tenant_id IS NULL");
            }
            $totalOrphans += $count;
        }

        return $totalOrphans;
    }

    /**
     * Seed or reset a pristine DEMO GYM for live demonstrations
     * 
     * @param bool $forceReset If true, removes existing demo gym and re-creates from scratch
     * @return array Result summary with credentials
     */
    public static function seedDemoGym($forceReset = false) {
        $demoSlug = 'demo-fitness-gym';
        $demoGymCode = 'DEMO-GYM';

        // Check if demo gym already exists
        $existing = DB::fetchOne("SELECT * FROM tenants WHERE slug = ? OR gym_code = ? LIMIT 1", [$demoSlug, $demoGymCode]);

        if ($existing && !$forceReset) {
            return [
                'success' => true,
                'action' => 'already_exists',
                'message' => 'Demo gym already exists in the system.',
                'tenant' => $existing,
                'credentials' => [
                    'admin_username' => 'demogym',
                    'admin_password' => 'Password@123',
                    'gym_code' => $existing['gym_code'] ?? 'DEMO-GYM'
                ]
            ];
        }

        DB::beginTransaction();
        try {
            // If forcing reset and tenant exists, delete old data
            if ($existing) {
                $oldId = (int)$existing['id'];
                foreach (self::$tenantTables as $tbl) {
                    $tableExists = (int)DB::fetchValue("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?", [$tbl]);
                    if ($tableExists) {
                        DB::query("DELETE FROM `{$tbl}` WHERE tenant_id = ?", [$oldId]);
                    }
                }
                DB::query("DELETE FROM users WHERE tenant_id = ? AND role NOT IN ('super_admin','superadmin')", [$oldId]);
                DB::query("DELETE FROM tenants WHERE id = ?", [$oldId]);
            }

            // Get default Pro SaaS plan id
            $planId = (int)DB::fetchValue("SELECT id FROM subscription_plans WHERE billing_cycle = 'yearly' OR price > 0 ORDER BY id ASC LIMIT 1");
            if ($planId <= 0) {
                $planId = (int)DB::fetchValue("SELECT id FROM subscription_plans ORDER BY id ASC LIMIT 1");
            }
            if ($planId <= 0) {
                $planId = (int)DB::insert('subscription_plans', [
                    'name' => 'Pro Enterprise',
                    'price' => 2999.00,
                    'price_monthly' => 2999.00,
                    'price_yearly' => 29990.00,
                    'billing_cycle' => 'yearly',
                    'max_members' => 500,
                    'max_staff' => 15,
                    'max_branches' => 3,
                    'status' => 'active'
                ]);
            }

            $expiryDate = date('Y-m-d 23:59:59', strtotime('+1 year'));

            // 1. Create Tenant
            $tenantId = DB::insert('tenants', [
                'gym_code' => $demoGymCode,
                'gym_name' => 'DEMO FITNESS & GYM',
                'name' => 'DEMO FITNESS & GYM',
                'slug' => $demoSlug,
                'owner_name' => 'Demo Administrator',
                'email' => 'demo@fitisify.com',
                'phone' => '+91 9876543210',
                'address' => 'Plot 101, Fitisify Tower, Tech Zone, Mumbai, MH - 400001',
                'currency' => '₹',
                'timezone' => 'Asia/Kolkata',
                'subscription_plan_id' => $planId,
                'subscription_start' => date('Y-m-d'),
                'subscription_expiry' => $expiryDate,
                'status' => 'active',
                'primary_color' => '#ccff00',
                'secondary_color' => '#00f2fe',
                'invoice_header' => 'DEMO FITNESS & GYM - ISO CERTIFIED',
                'invoice_footer' => 'Thank you for training with Demo Fitness! Transform Your Body & Mind.'
            ]);

            if (!$tenantId) {
                throw new Exception('Failed to insert demo tenant: ' . DB::$lastError);
            }

            // 2. Create Main Branch
            $branchId = DB::insert('branches', [
                'tenant_id' => $tenantId,
                'branch_name' => 'Demo Gym - Flagship Branch',
                'address' => 'Plot 101, Fitisify Tower, Tech Zone, Mumbai - 400001',
                'phone' => '+91 9876543210',
                'email' => 'demo@fitisify.com',
                'is_main' => 1,
                'status' => 'active'
            ]);

            // 3. Create Gym Admin User
            $hashedPass = password_hash('Password@123', PASSWORD_DEFAULT);
            DB::insert('users', [
                'tenant_id' => $tenantId,
                'branch_id' => $branchId,
                'role' => 'gym_admin',
                'username' => 'demogym',
                'password' => $hashedPass,
                'email' => 'demo@fitisify.com',
                'fullname' => 'Demo Administrator',
                'phone' => '+91 9876543210',
                'status' => 'active'
            ]);

            // 4. Seed Standard Rates & Packages
            $rate1 = DB::insert('rates', [
                'tenant_id' => $tenantId,
                'name' => 'Monthly Standard Fitness',
                'charge' => 1500.00,
                'duration_months' => 1,
                'status' => 'active'
            ]);
            $rate3 = DB::insert('rates', [
                'tenant_id' => $tenantId,
                'name' => 'Quarterly Muscle Transformation',
                'charge' => 4000.00,
                'duration_months' => 3,
                'status' => 'active'
            ]);
            $rate6 = DB::insert('rates', [
                'tenant_id' => $tenantId,
                'name' => 'Half-Yearly Strength & Conditioning',
                'charge' => 7500.00,
                'duration_months' => 6,
                'status' => 'active'
            ]);
            $rate12 = DB::insert('rates', [
                'tenant_id' => $tenantId,
                'name' => 'Annual VIP All-Access Pass',
                'charge' => 12000.00,
                'duration_months' => 12,
                'status' => 'active'
            ]);

            // 5. Seed 2 Trainers/Staff
            $trainer1Id = DB::insert('staffs', [
                'tenant_id' => $tenantId,
                'branch_id' => $branchId,
                'username' => 'trainer_marcus',
                'password' => $hashedPass,
                'email' => 'marcus@fitisifydemo.com',
                'fullname' => 'Marcus Strong',
                'address' => 'Mumbai Gym Hub',
                'designation' => 'Head Strength Coach',
                'gender' => 'Male',
                'contact' => '9870011223',
                'role' => 'trainer',
                'status' => 'active'
            ]);
            $trainer2Id = DB::insert('staffs', [
                'tenant_id' => $tenantId,
                'branch_id' => $branchId,
                'username' => 'trainer_elena',
                'password' => $hashedPass,
                'email' => 'elena@fitisifydemo.com',
                'fullname' => 'Elena Rossi',
                'address' => 'Mumbai Fitness Studio',
                'designation' => 'HIIT & Mobility Coach',
                'gender' => 'Female',
                'contact' => '9870022334',
                'role' => 'trainer',
                'status' => 'active'
            ]);

            // 6. Seed Workout and Diet Plans
            $workout1 = DB::insert('workout_plans', [
                'tenant_id' => $tenantId,
                'name' => '4-Day Hypertrophy & Power Split',
                'goal' => 'Muscle Gain',
                'level' => 'Intermediate',
                'description' => 'Comprehensive upper/lower body progression with progressive overload.',
                'schedule_json' => "Day 1: Chest & Triceps (Barbell Bench 4x8, Incline DB Press 3x10, Cable Flies 3x12, Skull Crushers 3x12)\nDay 2: Back & Biceps (Deadlift 4x6, Lat Pulldown 4x10, Barbell Rows 3x8, Hammer Curls 3x12)\nDay 3: Rest & Core Mobility\nDay 4: Legs & Shoulders (Barbell Squats 4x8, Leg Press 3x12, Military Press 4x8, Lateral Raises 4x15)\nDay 5: Arms & Cardio Burn",
                'created_by' => $trainer1Id ?: 1
            ]);

            $diet1 = DB::insert('diet_plans', [
                'tenant_id' => $tenantId,
                'name' => 'High-Protein Muscle Fuel (2,600 kcal)',
                'target' => 'Clean Bulk & Recovery',
                'calories' => 2600,
                'description' => 'Optimal macronutrient balance: 180g Protein, 280g Carbs, 70g Healthy Fats.',
                'meals_json' => "08:00 AM - 4 Whole Eggs + 2 Toast + 1 Banana\n11:30 AM - Whey Protein Scoop + Oats + Almonds\n02:00 PM - 200g Grilled Chicken + 1.5 Cup Brown Rice + Veggies\n05:30 PM - Peanut Butter Toast + Black Coffee\n08:30 PM - 200g Paneer/Fish + Sweet Potato + Green Salad",
                'created_by' => $trainer1Id ?: 1
            ]);

            // 7. Seed 5 Realistic Demo Members
            $demoMembers = [
                [
                    'name' => 'Rahul Sharma',
                    'user' => 'rahul_demo',
                    'gender' => 'Male',
                    'phone' => '9820011223',
                    'email' => 'rahul@fitisifydemo.com',
                    'plan' => '12',
                    'service' => 'Annual VIP All-Access Pass',
                    'amount' => 12000.00,
                    'paid_date' => date('Y-m-d', strtotime('-2 months')),
                    'exp_date' => date('Y-m-d', strtotime('+10 months')),
                    'status' => 'Active',
                    'trainer_id' => $trainer1Id
                ],
                [
                    'name' => 'Priya Verma',
                    'user' => 'priya_demo',
                    'gender' => 'Female',
                    'phone' => '9820033445',
                    'email' => 'priya@fitisifydemo.com',
                    'plan' => '6',
                    'service' => 'Half-Yearly Strength & Conditioning',
                    'amount' => 7500.00,
                    'paid_date' => date('Y-m-d', strtotime('-1 month')),
                    'exp_date' => date('Y-m-d', strtotime('+5 months')),
                    'status' => 'Active',
                    'trainer_id' => $trainer2Id
                ],
                [
                    'name' => 'Amit Patel',
                    'user' => 'amit_demo',
                    'gender' => 'Male',
                    'phone' => '9820055667',
                    'email' => 'amit@fitisifydemo.com',
                    'plan' => '3',
                    'service' => 'Quarterly Muscle Transformation',
                    'amount' => 4000.00,
                    'paid_date' => date('Y-m-d', strtotime('-2 weeks')),
                    'exp_date' => date('Y-m-d', strtotime('+2 months 14 days')),
                    'status' => 'Active',
                    'trainer_id' => $trainer1Id
                ],
                [
                    'name' => 'Neha Singh',
                    'user' => 'neha_demo',
                    'gender' => 'Female',
                    'phone' => '9820077889',
                    'email' => 'neha@fitisifydemo.com',
                    'plan' => '1',
                    'service' => 'Monthly Standard Fitness',
                    'amount' => 1500.00,
                    'paid_date' => date('Y-m-d', strtotime('-10 days')),
                    'exp_date' => date('Y-m-d', strtotime('+20 days')),
                    'status' => 'Active',
                    'trainer_id' => $trainer2Id
                ],
                [
                    'name' => 'Vikram Malhotra',
                    'user' => 'vikram_demo',
                    'gender' => 'Male',
                    'phone' => '9820099001',
                    'email' => 'vikram@fitisifydemo.com',
                    'plan' => '1',
                    'service' => 'Monthly Standard Fitness',
                    'amount' => 1500.00,
                    'paid_date' => date('Y-m-d', strtotime('-25 days')),
                    'exp_date' => date('Y-m-d', strtotime('+5 days')), // Due for renewal soon
                    'status' => 'Active',
                    'trainer_id' => $trainer1Id
                ]
            ];

            foreach ($demoMembers as $idx => $m) {
                $memberId = DB::insert('members', [
                    'tenant_id' => $tenantId,
                    'branch_id' => $branchId,
                    'fullname' => $m['name'],
                    'username' => $m['user'],
                    'password' => $hashedPass,
                    'gender' => $m['gender'],
                    'dor' => $m['paid_date'],
                    'services' => $m['service'],
                    'amount' => $m['amount'],
                    'paid_date' => $m['paid_date'],
                    'p_year' => date('Y', strtotime($m['paid_date'])),
                    'plan' => $m['plan'],
                    'address' => 'Andheri West, Mumbai',
                    'contact' => $m['phone'],
                    'status' => $m['status'],
                    'attendance_count' => rand(8, 22),
                    'ini_weight' => 75,
                    'curr_weight' => 73,
                    'ini_bodytype' => 'Endomorph',
                    'curr_bodytype' => 'Athletic',
                    'progress_date' => date('Y-m-d'),
                    'email' => $m['email'],
                    'trainer_id' => $m['trainer_id']
                ]);

                if ($memberId) {
                    // Create app user login
                    DB::insert('users', [
                        'tenant_id' => $tenantId,
                        'branch_id' => $branchId,
                        'role' => 'member',
                        'username' => $m['user'],
                        'password' => $hashedPass,
                        'email' => $m['email'],
                        'fullname' => $m['name'],
                        'phone' => $m['phone'],
                        'status' => 'active',
                        'member_id' => $memberId
                    ]);

                    // Assign workout & diet plans
                    DB::insert('member_assigned_plans', [
                        'tenant_id' => $tenantId,
                        'member_id' => $memberId,
                        'workout_plan_id' => $workout1,
                        'diet_plan_id' => $diet1,
                        'trainer_id' => $m['trainer_id'],
                        'assigned_date' => $m['paid_date'],
                        'notes' => 'Follow weekly progressive overload and maintain proper hydration.',
                        'status' => 'active'
                    ]);

                    // Generate Invoice
                    DB::insert('invoices', [
                        'tenant_id' => $tenantId,
                        'branch_id' => $branchId,
                        'invoice_number' => 'INV-DEMO-' . str_pad($memberId, 4, '0', STR_PAD_LEFT),
                        'member_id' => $memberId,
                        'amount' => $m['amount'],
                        'paid_amount' => $m['amount'],
                        'discount' => 0.00,
                        'plan_months' => (int)$m['plan'],
                        'service_name' => $m['service'],
                        'payment_method' => 'UPI / Online',
                        'payment_date' => $m['paid_date'],
                        'status' => 'Paid',
                        'transaction_ref' => 'TXN_DEMO_' . strtoupper(substr(md5(uniqid()), 0, 8)),
                        'notes' => 'Verified Demo Membership payment.'
                    ]);

                    // Sample attendance
                    DB::insert('attendance', [
                        'tenant_id' => $tenantId,
                        'branch_id' => $branchId,
                        'user_id' => (string)$memberId,
                        'curr_date' => date('Y-m-d'),
                        'curr_time' => '07:30 AM',
                        'check_out_time' => '08:45 AM',
                        'present' => 1
                    ]);
                }
            }

            // 8. Seed Gym Announcement
            DB::insert('announcements', [
                'tenant_id' => $tenantId,
                'message' => '🔥 Welcome to Demo Fitness & Gym! Free Olympic weightlifting and functional athletic workshop this Saturday at 10:00 AM.',
                'date' => date('Y-m-d')
            ]);

            DB::commit();
        } catch (Exception $e) {
            DB::rollback();
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }

        return [
            'success' => true,
            'action' => 'created',
            'tenant_id' => $tenantId,
            'gym_name' => 'DEMO FITNESS & GYM',
            'credentials' => [
                'admin_username' => 'demogym',
                'admin_password' => 'Password@123',
                'gym_code' => $demoGymCode,
                'login_url' => base_url('/login.php')
            ]
        ];
    }
}
