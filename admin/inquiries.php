<?php
require_once __DIR__ . '/../core/auth.php';
Auth::requireAuth(['gym_admin', 'staff']);

$page = 'inquiries';
$pageTitle = 'Member Requests';
$pageSubtitle = 'Support tickets and password-reset requests sent from the member app';
$tenantId = Tenant::getTenantId();

$allowedStatuses = ['open', 'in_progress', 'resolved', 'closed'];

// Handle reply / status change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_reply'])) {
    Auth::verifyCsrf();

    $inquiryId = (int)($_POST['inquiry_id'] ?? 0);
    $reply = trim($_POST['reply'] ?? '');
    $status = in_array($_POST['status'] ?? '', $allowedStatuses, true) ? $_POST['status'] : 'in_progress';

    $inquiry = DB::fetchOne("SELECT id FROM member_inquiries WHERE id = ? AND tenant_id = ?", [$inquiryId, $tenantId]);
    if (!$inquiry) {
        redirect('inquiries.php', 'error', 'Request not found.');
    }

    $data = ['status' => $status];
    if ($reply !== '') {
        $data['reply'] = mb_substr($reply, 0, 5000);
    }
    DB::update('member_inquiries', $data, 'id = ? AND tenant_id = ?', [$inquiryId, $tenantId]);
    Auth::auditLog('REPLY_INQUIRY', "Updated member request #{$inquiryId} (status: {$status})");
    redirect('inquiries.php', 'success', 'Request updated. The member will see your reply in the app.');
}

$filter = in_array($_GET['status'] ?? '', $allowedStatuses, true) ? $_GET['status'] : '';
$sql = "SELECT i.*, m.fullname, m.username, m.contact
          FROM member_inquiries i
          LEFT JOIN members m ON m.user_id = i.member_id AND m.tenant_id = i.tenant_id
         WHERE i.tenant_id = ?";
$params = [$tenantId];
if ($filter !== '') {
    $sql .= " AND i.status = ?";
    $params[] = $filter;
}
$sql .= " ORDER BY FIELD(i.status, 'open', 'pending', 'in_progress', 'resolved', 'closed'), i.created_at DESC, i.id DESC LIMIT 300";
$inquiries = DB::fetchAll($sql, $params);
$openCount = (int)DB::fetchValue("SELECT COUNT(*) FROM member_inquiries WHERE tenant_id = ? AND status IN ('open','pending','in_progress')", [$tenantId]);

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
include __DIR__ . '/../includes/topbar.php';
?>

<div style="display: flex; justify-content: flex-end; gap: 8px; margin-bottom: 20px; flex-wrap: wrap;">
    <a href="inquiries.php" class="btn btn-sm <?php echo $filter === '' ? 'btn-primary' : 'btn-secondary'; ?>">All</a>
    <?php foreach ($allowedStatuses as $st): ?>
        <a href="inquiries.php?status=<?php echo e($st); ?>" class="btn btn-sm <?php echo $filter === $st ? 'btn-primary' : 'btn-secondary'; ?>"><?php echo e(ucwords(str_replace('_', ' ', $st))); ?></a>
    <?php endforeach; ?>
</div>

<div class="card">
    <div class="card-header">
        <div class="card-title">
            <i class="fas fa-life-ring"></i>
            <span>Member Requests (<?php echo $openCount; ?> awaiting action)</span>
        </div>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Received</th>
                        <th>Member</th>
                        <th>Request</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($inquiries)): ?>
                        <tr>
                            <td colspan="5" style="text-align: center; padding: 40px; color: var(--text-muted);">
                                No member requests yet.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($inquiries as $q): ?>
                            <?php $isReset = stripos($q['category'] ?? '', 'password') !== false; ?>
                            <tr>
                                <td><span class="status-badge badge-info"><?php echo format_date($q['created_at'], 'M d, Y H:i'); ?></span></td>
                                <td>
                                    <div style="font-weight: 600;"><?php echo e($q['fullname'] ?? ('Member #' . $q['member_id'])); ?></div>
                                    <div style="font-size: 0.8rem; color: var(--text-muted);"><?php echo e($q['username'] ?? ''); ?><?php echo !empty($q['contact']) ? ' · ' . e($q['contact']) : ''; ?></div>
                                </td>
                                <td style="max-width: 420px;">
                                    <div style="font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted);"><?php echo e($q['category'] ?? 'General'); ?></div>
                                    <div style="font-weight: 600;"><?php echo e($q['subject']); ?></div>
                                    <div style="white-space: pre-wrap; line-height: 1.5;"><?php echo e($q['message']); ?></div>
                                    <?php if (!empty($q['reply'])): ?>
                                        <div style="margin-top: 8px; padding: 8px 10px; border-left: 3px solid var(--primary); background: var(--bg-main); white-space: pre-wrap;"><strong>Reply:</strong> <?php echo e($q['reply']); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo status_badge(str_replace('_', ' ', $q['status'] ?? 'open')); ?></td>
                                <td style="white-space: nowrap;">
                                    <?php if ($isReset && !empty($q['member_id'])): ?>
                                        <a href="edit-memberform.php?id=<?php echo (int)$q['member_id']; ?>" class="btn btn-secondary btn-sm" title="Set a new password for this member">
                                            <i class="fas fa-key"></i> Reset Password
                                        </a>
                                    <?php endif; ?>
                                    <button type="button" class="btn btn-primary btn-sm" onclick="openReply(<?php echo (int)$q['id']; ?>, <?php echo e(json_encode($q['status'] ?? 'open')); ?>)">
                                        <i class="fas fa-reply"></i> Reply
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Reply Modal -->
<div class="modal-backdrop" id="reply-modal">
    <div class="modal-content">
        <div class="modal-header">
            <div class="card-title">
                <i class="fas fa-reply"></i>
                <span>Reply to Member Request</span>
            </div>
            <button type="button" class="btn-icon" onclick="App.closeModal('reply-modal')">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <form method="POST" action="inquiries.php">
            <?php echo Auth::csrfField(); ?>
            <input type="hidden" name="inquiry_id" id="reply-inquiry-id" value="" />
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Status *</label>
                    <select name="status" id="reply-status" class="form-select">
                        <?php foreach ($allowedStatuses as $st): ?>
                            <option value="<?php echo e($st); ?>"><?php echo e(ucwords(str_replace('_', ' ', $st))); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Reply (shown to the member in the app)</label>
                    <textarea name="reply" class="form-control" rows="4" placeholder="Type your reply..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="App.closeModal('reply-modal')">Cancel</button>
                <button type="submit" name="save_reply" value="1" class="btn btn-primary">Save</button>
            </div>
        </form>
    </div>
</div>

<script>
function openReply(id, status) {
    document.getElementById('reply-inquiry-id').value = id;
    document.getElementById('reply-status').value = status === 'pending' ? 'open' : status;
    App.openModal('reply-modal');
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
