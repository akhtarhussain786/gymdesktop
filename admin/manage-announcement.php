<?php
require_once __DIR__ . '/../core/auth.php';
Auth::requireAuth(['gym_admin', 'staff']);

$page = 'announcements';
$pageTitle = 'Gym Announcements';
$pageSubtitle = 'Publish notices, holiday schedules, and gym updates for members';
$tenantId = Tenant::getTenantId();

// Handle New Announcement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_announcement'])) {
    Auth::verifyCsrf();

    $message = trim($_POST['message'] ?? '');
    $date = $_POST['date'] ?? date('Y-m-d');

    if (!empty($message)) {
        $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : date('Y-m-d');
        $saved = DB::insert('announcements', [
            'tenant_id' => $tenantId,
            'title' => mb_substr($message, 0, 80),
            'message' => $message,
            'date' => $date
        ]);
        if (!$saved) {
            redirect('manage-announcement.php', 'error', 'Could not publish the announcement. Please try again.');
        }
        Auth::auditLog('ADD_ANNOUNCEMENT', "Posted announcement: $message");
        redirect('manage-announcement.php', 'success', 'Announcement published successfully!');
    }
}

// Handle Delete Announcement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete'])) {
    Auth::verifyCsrf();
    $deleteId = (int)$_POST['delete'];
    DB::delete('announcements', 'id = ? AND tenant_id = ?', [$deleteId, $tenantId]);
    redirect('manage-announcement.php', 'success', 'Announcement deleted.');
}

$announcements = DB::fetchAll("SELECT * FROM announcements WHERE tenant_id = ? ORDER BY date DESC, id DESC", [$tenantId]);

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
include __DIR__ . '/../includes/topbar.php';
?>

<div style="display: flex; justify-content: flex-end; margin-bottom: 20px;">
    <button type="button" class="btn btn-primary btn-sm" onclick="App.openModal('add-announcement-modal')">
        <i class="fas fa-bullhorn"></i> Post New Announcement
    </button>
</div>

<div class="card">
    <div class="card-header">
        <div class="card-title">
            <i class="fas fa-bullhorn"></i>
            <span>All Published Announcements (<?php echo count($announcements); ?>)</span>
        </div>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Announcement Notice</th>
                        <th>Published Date</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($announcements)): ?>
                        <tr>
                            <td colspan="4" style="text-align: center; padding: 40px; color: var(--text-muted);">
                                No announcements published yet.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($announcements as $idx => $a): ?>
                            <tr>
                                <td><?php echo $idx + 1; ?></td>
                                <td>
                                    <div style="font-weight: 600; color: var(--text-main); line-height: 1.5;"><?php echo e($a['message']); ?></div>
                                </td>
                                <td>
                                    <span class="status-badge badge-info"><?php echo format_date($a['date']); ?></span>
                                </td>
                                <td>
                                    <form method="POST" action="manage-announcement.php" style="display:inline;"><?php echo Auth::csrfField(); ?><button type="submit" name="delete" value="<?php echo (int)$a['id']; ?>" class="btn btn-secondary btn-sm" title="Delete Announcement" onclick="return confirm('Delete this announcement?')">
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

<!-- Post Announcement Modal -->
<div class="modal-backdrop" id="add-announcement-modal">
    <div class="modal-content">
        <div class="modal-header">
            <div class="card-title">
                <i class="fas fa-bullhorn"></i>
                <span>Post Gym Announcement</span>
            </div>
            <button type="button" class="btn-icon" onclick="App.closeModal('add-announcement-modal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form method="POST" action="">
            <?php echo Auth::csrfField(); ?>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Announcement Date *</label>
                    <input type="date" name="date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required />
                </div>
                <div class="form-group">
                    <label class="form-label">Notice Message *</label>
                    <textarea name="message" class="form-control" rows="4" placeholder="Enter announcement text to display on member and staff dashboards..." required></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="App.closeModal('add-announcement-modal')">Cancel</button>
                <button type="submit" name="save_announcement" value="1" class="btn btn-primary">Publish Notice</button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
