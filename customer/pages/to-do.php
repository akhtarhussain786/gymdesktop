<?php
require_once __DIR__ . '/../../core/auth.php';
Auth::requireAuth('member');

$page = 'member_todo';
$pageTitle = 'My Workout Checklist & Tasks';
$pageSubtitle = 'Keep track of your training routines, sets, and fitness goals';
$memberId = $_SESSION['user_id'];
$tenantId = Tenant::getTenantId();

// Handle Add Task
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_task'])) {
    Auth::verifyCsrf();
    $task_desc = trim($_POST['task_desc'] ?? '');
    if (!empty($task_desc)) {
        DB::insert('todo', [
            'tenant_id' => $tenantId,
            'user_id' => $memberId,
            'task_desc' => $task_desc,
            'task_status' => 'Pending'
        ]);
        redirect('to-do.php', 'success', 'Task added to your checklist.');
    }
}

// Handle Toggle Task Status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle'])) {
    Auth::verifyCsrf();
    $id = (int)$_POST['toggle'];
    $task = DB::fetchOne("SELECT * FROM todo WHERE id = ? AND user_id = ? AND tenant_id = ?", [$id, $memberId, $tenantId]);
    if ($task) {
        $newStatus = ($task['task_status'] === 'Completed') ? 'Pending' : 'Completed';
        DB::update('todo', ['task_status' => $newStatus], 'id = ? AND user_id = ? AND tenant_id = ?', [$id, $memberId, $tenantId]);
        redirect('to-do.php', 'info', 'Task status updated.');
    }
}

// Handle Delete Task
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete'])) {
    Auth::verifyCsrf();
    $id = (int)$_POST['delete'];
    DB::delete('todo', 'id = ? AND user_id = ? AND tenant_id = ?', [$id, $memberId, $tenantId]);
    redirect('to-do.php', 'success', 'Task removed.');
}

$todos = DB::fetchAll("SELECT * FROM todo WHERE user_id = ? AND tenant_id = ? ORDER BY id DESC", [$memberId, $tenantId]);

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
include __DIR__ . '/../../includes/topbar.php';
?>

<div class="card" style="max-width: 700px; margin: 0 auto 24px;">
    <div class="card-header">
        <div class="card-title">
            <i class="fas fa-plus-circle"></i>
            <span>Add Fitness Task / Target</span>
        </div>
    </div>
    <div class="card-body">
        <form method="POST" action="" style="display: flex; gap: 12px;">
            <?php echo Auth::csrfField(); ?>
            <input type="text" name="task_desc" class="form-control" placeholder="e.g. Complete 50 pushups, drink 3L water..." required />
            <button type="submit" name="add_task" value="1" class="btn btn-primary" style="white-space: nowrap;">
                <i class="fas fa-plus"></i> Add Task
            </button>
        </form>
    </div>
</div>

<div class="card" style="max-width: 700px; margin: 0 auto;">
    <div class="card-header">
        <div class="card-title">
            <i class="fas fa-tasks"></i>
            <span>My Workout Checklist (<?php echo count($todos); ?>)</span>
        </div>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width: 40px;">Status</th>
                        <th>Task Description</th>
                        <th style="text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($todos)): ?>
                        <tr>
                            <td colspan="3" style="text-align: center; padding: 30px; color: var(--text-muted);">
                                No tasks added yet. Add your workout targets above!
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($todos as $t): ?>
                            <?php $isDone = ($t['task_status'] === 'Completed'); ?>
                            <tr>
                                <td>
                                    <form method="POST" action="to-do.php" style="display:inline;"><?php echo Auth::csrfField(); ?><button type="submit" name="toggle" value="<?php echo (int)$t['id']; ?>" class="btn-icon" style="width: 28px; height: 28px; border-radius: var(--radius-full); <?php echo $isDone ? 'background: var(--secondary); color: white; border: none;' : ''; ?>">
                                        <i class="fas <?php echo $isDone ? 'fa-check' : 'fa-circle'; ?>" style="font-size: 0.75rem;"></i>
                                    </button></form>
                                </td>
                                <td>
                                    <span style="<?php echo $isDone ? 'text-decoration: line-through; opacity: 0.6;' : 'font-weight: 600;'; ?>">
                                        <?php echo e($t['task_desc']); ?>
                                    </span>
                                </td>
                                <td style="text-align: right;">
                                    <form method="POST" action="to-do.php" style="display:inline;" onsubmit="return confirm('Delete this task?')"><?php echo Auth::csrfField(); ?><button type="submit" name="delete" value="<?php echo (int)$t['id']; ?>" class="btn-icon" style="color: var(--danger); width: 28px; height: 28px;" title="Delete">
                                        <i class="fas fa-trash"></i>
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

<?php include __DIR__ . '/../../includes/footer.php'; ?>
