-- Repair and Recreate Tables for gymnsb2
-- Run against the application database selected by your client (e.g. mysql -u USER -p DB_NAME < this_file.sql)

DROP TABLE IF EXISTS `announcements`;
CREATE TABLE `announcements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL DEFAULT 1,
  `message` text NOT NULL,
  `date` date NOT NULL,
  PRIMARY KEY (`id`),
  KEY `tenant_id` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `announcements` (`id`, `tenant_id`, `message`, `date`) VALUES
(1, 1, 'Welcome to Fitisify Gym! Morning cross-fit batch starts at 6:00 AM.', CURDATE()),
(2, 1, 'Sunday Special HIIT session with Head Trainer.', CURDATE());

DROP TABLE IF EXISTS `attendance`;
CREATE TABLE `attendance` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL DEFAULT 1,
  `user_id` int(11) NOT NULL,
  `curr_date` date NOT NULL,
  `curr_time` varchar(20) NOT NULL,
  `check_out_time` varchar(20) DEFAULT NULL,
  `present` tinyint(4) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `tenant_id` (`tenant_id`),
  KEY `user_date` (`user_id`, `curr_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `equipment`;
CREATE TABLE `equipment` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL DEFAULT 1,
  `name` varchar(100) NOT NULL,
  `amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `vendor` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `address` varchar(150) DEFAULT NULL,
  `contact` varchar(30) DEFAULT NULL,
  `date` date NOT NULL,
  PRIMARY KEY (`id`),
  KEY `tenant_id` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `equipment` (`id`, `tenant_id`, `name`, `amount`, `quantity`, `vendor`, `description`, `address`, `contact`, `date`) VALUES
(1, 1, 'Commercial Treadmill X9', 45000.00, 4, 'DnS Fitness Corp', 'Heavy duty automatic incline treadmill', 'Main Road, Mumbai', '9876543210', CURDATE()),
(2, 1, 'Multi Bench Press Station', 25000.00, 2, 'SS Industries', 'Incline, Flat, Decline 6-in-1 bench', 'Industrial Area, Pune', '9876543211', CURDATE()),
(3, 1, 'Adjustable Dumbbell Set (5-40kg)', 18000.00, 8, 'Uptown Equipment', 'Steel & rubber coated commercial dumbbells', 'Park Street, Delhi', '9876543212', CURDATE());

DROP TABLE IF EXISTS `rates`;
CREATE TABLE `rates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL DEFAULT 1,
  `name` varchar(100) NOT NULL,
  `charge` decimal(10,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id`),
  KEY `tenant_id` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `rates` (`id`, `tenant_id`, `name`, `charge`) VALUES
(1, 1, 'Fitness & Strength', 1500.00),
(2, 1, 'Cardio & Aerobics', 1200.00),
(3, 1, 'Sauna & Spa Recovery', 2000.00),
(4, 1, 'VIP All-Access Pass', 3000.00);

DROP TABLE IF EXISTS `staffs`;
CREATE TABLE `staffs` (
  `user_id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL DEFAULT 1,
  `branch_id` int(11) NOT NULL DEFAULT 1,
  `username` varchar(60) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) NOT NULL,
  `fullname` varchar(100) NOT NULL,
  `address` varchar(150) DEFAULT NULL,
  `designation` varchar(50) NOT NULL DEFAULT 'Trainer',
  `gender` varchar(20) DEFAULT 'Male',
  `contact` varchar(30) DEFAULT NULL,
  `role` enum('gym_admin','staff','trainer') NOT NULL DEFAULT 'trainer',
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  PRIMARY KEY (`user_id`),
  KEY `tenant_id` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `staffs` (`user_id`, `tenant_id`, `username`, `password`, `email`, `fullname`, `designation`, `gender`, `contact`, `role`) VALUES
(1, 1, 'trainer_rahul', md5('123456'), 'rahul@fitisify.com', 'Rahul Verma', 'Trainer', 'Male', '9876543220', 'trainer'),
(2, 1, 'trainer_priya', md5('123456'), 'priya@fitisify.com', 'Priya Sharma', 'Trainer', 'Female', '9876543221', 'trainer'),
(3, 1, 'staff_vikram', md5('123456'), 'vikram@fitisify.com', 'Vikram Singh', 'Manager', 'Male', '9876543222', 'staff');

DROP TABLE IF EXISTS `members`;
CREATE TABLE `members` (
  `user_id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL DEFAULT 1,
  `branch_id` int(11) NOT NULL DEFAULT 1,
  `fullname` varchar(100) NOT NULL,
  `username` varchar(60) NOT NULL,
  `password` varchar(255) NOT NULL,
  `gender` varchar(20) NOT NULL DEFAULT 'Male',
  `dor` date NOT NULL,
  `services` varchar(100) NOT NULL DEFAULT 'Fitness & Strength',
  `amount` decimal(10,2) NOT NULL DEFAULT 1500.00,
  `paid_date` date NOT NULL,
  `p_year` int(11) NOT NULL DEFAULT 2026,
  `plan` int(11) NOT NULL DEFAULT 1,
  `address` varchar(150) DEFAULT NULL,
  `contact` varchar(30) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `trainer_id` int(11) DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'Active',
  `attendance_count` int(11) NOT NULL DEFAULT 0,
  `ini_weight` decimal(6,2) NOT NULL DEFAULT 0.00,
  `curr_weight` decimal(6,2) NOT NULL DEFAULT 0.00,
  `ini_bodytype` varchar(50) DEFAULT 'Normal',
  `curr_bodytype` varchar(50) DEFAULT 'Athletic',
  `progress_date` date DEFAULT NULL,
  `reminder` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`user_id`),
  KEY `tenant_id` (`tenant_id`),
  KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `members` (`user_id`, `tenant_id`, `fullname`, `username`, `password`, `gender`, `dor`, `services`, `amount`, `paid_date`, `p_year`, `plan`, `address`, `contact`, `email`, `status`, `attendance_count`, `ini_weight`, `curr_weight`, `ini_bodytype`, `curr_bodytype`, `progress_date`) VALUES
(1, 1, 'Harry Denn', 'harry', md5('123456'), 'Male', '2026-01-15', 'Fitness & Strength', 4500.00, '2026-08-01', 2026, 3, '64 Mulberry Lane', '9876543201', 'harry@member.com', 'Active', 12, 75.00, 72.00, 'Normal', 'Athletic', CURDATE()),
(2, 1, 'Charles Anderson', 'charles', md5('123456'), 'Male', '2026-02-10', 'Cardio & Aerobics', 3600.00, '2026-08-05', 2026, 3, '99 Heron Way', '9876543202', 'charles@member.com', 'Active', 15, 88.00, 83.50, 'Fat', 'Normal', CURDATE()),
(3, 1, 'Pooja Mehta', 'pooja', md5('123456'), 'Female', '2026-03-01', 'VIP All-Access Pass', 9000.00, '2026-07-20', 2026, 3, '23 Park Avenue', '9876543203', 'pooja@member.com', 'Active', 8, 58.00, 56.00, 'Normal', 'Toned', CURDATE()),
(4, 1, 'Karen McGray', 'karen', md5('123456'), 'Female', '2026-01-05', 'Sauna & Spa Recovery', 2000.00, '2026-07-01', 2026, 1, '23 Rubaiyat Road', '9876543204', 'karen@member.com', 'Expired', 22, 60.00, 59.00, 'Normal', 'Fit', CURDATE());

-- Insert Member login users into `users` table for customer portal access
INSERT IGNORE INTO `users` (`tenant_id`, `branch_id`, `role`, `username`, `password`, `email`, `fullname`, `phone`, `status`, `member_id`) VALUES
(1, 1, 'member', 'harry', md5('123456'), 'harry@member.com', 'Harry Denn', '9876543201', 'active', 1),
(1, 1, 'member', 'charles', md5('123456'), 'charles@member.com', 'Charles Anderson', '9876543202', 'active', 2),
(1, 1, 'member', 'pooja', md5('123456'), 'pooja@member.com', 'Pooja Mehta', '9876543203', 'active', 3),
(1, 1, 'member', 'karen', md5('123456'), 'karen@member.com', 'Karen McGray', '9876543204', 'active', 4);

DROP TABLE IF EXISTS `todo`;
CREATE TABLE `todo` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL DEFAULT 1,
  `user_id` int(11) NOT NULL,
  `task_desc` text NOT NULL,
  `task_status` enum('Pending','Completed') NOT NULL DEFAULT 'Pending',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `tenant_id` (`tenant_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `todo` (`id`, `tenant_id`, `user_id`, `task_desc`, `task_status`) VALUES
(1, 1, 1, 'Complete 20 minutes treadmill warm-up', 'Completed'),
(2, 1, 1, 'Bench press 4 sets x 10 reps (60kg)', 'Pending'),
(3, 1, 1, 'Post-workout protein shake & 3L water intake', 'Pending');

DROP TABLE IF EXISTS `reminder`;
CREATE TABLE `reminder` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL DEFAULT 1,
  `name` varchar(100) NOT NULL,
  `message` text NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'unread',
  `date` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `user_id` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `tenant_id` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
