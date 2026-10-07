<?php
require_once __DIR__ . '/../core/auth.php';
Auth::requireAuth(['gym_admin', 'staff', 'trainer']);

$page = 'classes';
$pageTitle = 'Classes & Group Schedules';
$pageSubtitle = 'Manage fitness classes, workout studios, instructor assignments, and class schedules';
$tenantId = Tenant::getTenantId();

// Handle New Class
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_class'])) {
    Auth::verifyCsrf();
    Auth::requireAuth(['gym_admin', 'staff']); // trainers can view their schedule, not edit it

    $title = trim($_POST['title'] ?? '');
    $trainer_id = !empty($_POST['trainer_id']) ? (int)$_POST['trainer_id'] : null;
    $day_of_week = $_POST['day_of_week'] ?? 'Monday';
    $start_time = $_POST['start_time'] ?? '06:00:00';
    $end_time = $_POST['end_time'] ?? '07:00:00';
    $capacity = (int)($_POST['capacity'] ?? 20);
    $room = trim($_POST['room'] ?? 'Main Studio');

    $validDays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday', 'Daily'];
    if (!in_array($day_of_week, $validDays, true)) $day_of_week = 'Monday';
    // Instructor must be a staff member of THIS gym
    if ($trainer_id !== null && !DB::fetchValue("SELECT user_id FROM staffs WHERE user_id = ? AND tenant_id = ?", [$trainer_id, $tenantId])) {
        $trainer_id = null;
    }
    if ($title === '' || $capacity < 1 || strtotime($end_time) <= strtotime($start_time)) {
        redirect('classes.php', 'error', 'Class title, a capacity of at least 1 and an end time after the start time are required.');
    }

    DB::insert('classes', [
        'tenant_id' => $tenantId,
        'branch_id' => 1,
        'title' => $title,
        'trainer_id' => $trainer_id,
        'day_of_week' => $day_of_week,
        'start_time' => $start_time,
        'end_time' => $end_time,
        'capacity' => $capacity,
        'room' => $room,
        'status' => 'active'
    ]);

    Auth::auditLog('ADD_CLASS', "Scheduled fitness class '$title' on $day_of_week");
    redirect('classes.php', 'success', "Fitness class '$title' added to schedule!");
}

// Handle Delete Class
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete'])) {
    Auth::verifyCsrf();
    Auth::requireAuth(['gym_admin', 'staff']);
    $deleteId = (int)$_POST['delete'];
    if (DB::delete('classes', 'id = ? AND tenant_id = ?', [$deleteId, $tenantId])) {
        DB::delete('class_bookings', 'class_id = ? AND tenant_id = ?', [$deleteId, $tenantId]);
    }
    redirect('classes.php', 'success', 'Class removed from schedule.');
}

$classes = DB::fetchAll("SELECT c.*, s.fullname as instructor_name 
                         FROM classes c 
                         LEFT JOIN staffs s ON c.trainer_id = s.user_id 
                         WHERE c.tenant_id = ? 
                         ORDER BY FIELD(c.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday', 'Daily'), c.start_time ASC", [$tenantId]);

$trainers = DB::fetchAll("SELECT * FROM staffs WHERE tenant_id = ? AND designation = 'Trainer'", [$tenantId]);

// Populate sample schedules if empty
if (empty($classes)) {
    DB::insert('classes', [
        'tenant_id' => $tenantId,
        'title' => 'Morning HIIT & Cardio Blast',
        'trainer_id' => !empty($trainers[0]['user_id']) ? $trainers[0]['user_id'] : null,
        'day_of_week' => 'Monday',
        'start_time' => '06:30:00',
        'end_time' => '07:30:00',
        'capacity' => 25,
        'room' => 'Studio A',
        'status' => 'active'
    ]);
    DB::insert('classes', [
        'tenant_id' => $tenantId,
        'title' => 'Power Yoga & Flexibility',
        'trainer_id' => !empty($trainers[0]['user_id']) ? $trainers[0]['user_id'] : null,
        'day_of_week' => 'Wednesday',
        'start_time' => '07:00:00',
        'end_time' => '08:00:00',
        'capacity' => 20,
        'room' => 'Zen Studio',
        'status' => 'active'
    ]);
    $classes = DB::fetchAll("SELECT c.*, s.fullname as instructor_name FROM classes c LEFT JOIN staffs s ON c.trainer_id = s.user_id WHERE c.tenant_id = ?", [$tenantId]);
}

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
include __DIR__ . '/../includes/topbar.php';
?>

<div style="display: flex; justify-content: flex-end; margin-bottom: 20px;">
    <button type="button" class="btn btn-primary btn-sm" onclick="App.openModal('add-class-modal')">
        <i class="fas fa-plus"></i> Schedule New Class
    </button>
</div>

<div class="card">
    <div class="card-header">
        <div class="card-title">
            <i class="fas fa-calendar-alt"></i>
            <span>Weekly Class Timetable (<?php echo count($classes); ?> Sessions)</span>
        </div>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Class Title</th>
                        <th>Day of Week</th>
                        <th>Time Slot</th>
                        <th>Instructor</th>
                        <th>Studio / Room</th>
                        <th>Max Capacity</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($classes)): ?>
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 40px; color: var(--text-muted);">
                                No classes scheduled yet.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($classes as $c): ?>
                            <tr>
                                <td><strong style="color: var(--text-main);"><?php echo e($c['title']); ?></strong></td>
                                <td><span class="status-badge badge-info"><?php echo e($c['day_of_week']); ?></span></td>
                                <td>
                                    <i class="fas fa-clock" style="color: var(--text-light); font-size: 0.8rem;"></i>
                                    <?php echo date('h:i A', strtotime($c['start_time'])); ?> – <?php echo date('h:i A', strtotime($c['end_time'])); ?>
                                </td>
                                <td><?php echo e($c['instructor_name'] ?: 'Unassigned'); ?></td>
                                <td><?php echo e($c['room']); ?></td>
                                <td><strong><?php echo $c['capacity']; ?></strong> spots</td>
                                <td><?php echo status_badge($c['status']); ?></td>
                                <td>
                                    <form method="POST" action="classes.php" style="display:inline;"><?php echo Auth::csrfField(); ?><button type="submit" name="delete" value="<?php echo (int)$c['id']; ?>" class="btn btn-secondary btn-sm" title="Remove Class" onclick="return confirm('Remove this class from schedule?')">
                                        <i class="fas fa-trash" style="color: var(--danger);"></i>
                                    </button></form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Class Modal -->
<div class="modal-backdrop" id="add-class-modal">
    <div class="modal-content">
        <div class="modal-header">
            <div class="card-title">
                <i class="fas fa-calendar-plus"></i>
                <span>Schedule New Fitness Class</span>
            </div>
            <button type="button" class="btn-icon" onclick="App.closeModal('add-class-modal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form method="POST" action="">
            <?php echo Auth::csrfField(); ?>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Class Name *</label>
                    <input type="text" name="title" class="form-control" placeholder="e.g. Zumba Dance Fitness, Spinning" required />
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Day of Week *</label>
                        <select name="day_of_week" class="form-select">
                            <option value="Monday">Monday</option>
                            <option value="Tuesday">Tuesday</option>
                            <option value="Wednesday">Wednesday</option>
                            <option value="Thursday">Thursday</option>
                            <option value="Friday">Friday</option>
                            <option value="Saturday">Saturday</option>
                            <option value="Sunday">Sunday</option>
                            <option value="Daily">Daily (Mon-Sat)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Instructor / Trainer</label>
                        <select name="trainer_id" class="form-select">
                            <option value="">None / Open</option>
                            <?php foreach ($trainers as $tr): ?>
                                <option value="<?php echo $tr['user_id']; ?>"><?php echo e($tr['fullname']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Start Time *</label>
                        <input type="time" name="start_time" class="form-control" value="07:00" required />
                    </div>
                    <div class="form-group">
                        <label class="form-label">End Time *</label>
                        <input type="time" name="end_time" class="form-control" value="08:00" required />
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Studio / Room</label>
                        <input type="text" name="room" class="form-control" value="Main Studio" required />
                    </div>
                    <div class="form-group">
                        <label class="form-label">Capacity (Max Members)</label>
                        <input type="number" name="capacity" class="form-control" value="25" required />
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="App.closeModal('add-class-modal')">Cancel</button>
                <button type="submit" name="save_class" value="1" class="btn btn-primary">Add to Timetable</button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
