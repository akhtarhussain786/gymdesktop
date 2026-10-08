<?php
require_once __DIR__ . '/../core/auth.php';
Auth::requireAuth(['gym_admin', 'staff']);

$page = 'members';
$pageTitle = 'Edit Member Profile';
$pageSubtitle = 'Update member information, membership plan, and physical progress metrics';
$tenantId = Tenant::getTenantId();
$memberId = (int)($_GET['id'] ?? 0);

$member = DB::fetchOne("SELECT * FROM members WHERE user_id = ? AND tenant_id = ?", [$memberId, $tenantId]);
if (!$member) {
    redirect('members.php', 'error', 'Member record not found.');
}

$rates = Tenant::getRates($tenantId);
$hasMemberService = false;
$memberService = trim($member['services'] ?? '');
if (!empty($memberService)) {
    foreach ($rates as $r) {
        if (strcasecmp($r['name'], $memberService) === 0) {
            $hasMemberService = true;
            break;
        }
    }
    if (!$hasMemberService) {
        array_unshift($rates, [
            'id' => 0,
            'tenant_id' => $tenantId,
            'name' => $memberService,
            'charge' => 500.00
        ]);
    }
}
$trainers = DB::fetchAll("SELECT * FROM staffs WHERE tenant_id = ? AND designation = 'Trainer'", [$tenantId]);

// Form Processing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_member'])) {
    Auth::verifyCsrf();

    $fullname = trim($_POST['fullname'] ?? '');
    $gender = $_POST['gender'] ?? 'Male';
    $services = $_POST['services'] ?? 'Fitness';
    $plan = (int)($_POST['plan'] ?? 1);
    $address = trim($_POST['address'] ?? '');
    $contact = trim($_POST['contact'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $status = $_POST['status'] ?? 'Active';
    $trainer_id = !empty($_POST['trainer_id']) ? (int)$_POST['trainer_id'] : null;
    if (!in_array($status, ['Active', 'Expired', 'Pending'], true)) $status = $member['status'];
    if (!in_array($gender, ['Male', 'Female', 'Other'], true)) $gender = $member['gender'];
    $plan = max(1, $plan);
    // Trainer must be a staff member of THIS gym
    if ($trainer_id !== null && !DB::fetchValue("SELECT user_id FROM staffs WHERE user_id = ? AND tenant_id = ?", [$trainer_id, $tenantId])) {
        $trainer_id = null;
    }

    // Body Progress Metrics
    $ini_weight = (float)($_POST['ini_weight'] ?? 70);
    $curr_weight = (float)($_POST['curr_weight'] ?? 70);
    $ini_bodytype = trim($_POST['ini_bodytype'] ?? 'Athletic');
    $curr_bodytype = trim($_POST['curr_bodytype'] ?? 'Athletic');

    // Optional Avatar / Photo upload
    $avatarFileName = null;
    if (!empty($_FILES['avatar']['name']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif']) && @getimagesize($_FILES['avatar']['tmp_name']) !== false) {
            $uploadDir = __DIR__ . '/../uploads/avatars';
            if (!is_dir($uploadDir)) {
                @mkdir($uploadDir, 0777, true);
            }
            $avatarFileName = 'avatar_' . $tenantId . '_' . $memberId . '_' . time() . '.' . $ext;
            if (@move_uploaded_file($_FILES['avatar']['tmp_name'], $uploadDir . '/' . $avatarFileName)) {
                $updateMemberData['avatar'] = $avatarFileName;
                $updateMemberData['photo'] = $avatarFileName;
            }
        }
    }

    $updateMemberData = [
        'fullname' => $fullname,
        'gender' => $gender,
        'services' => $services,
        'plan' => $plan,
        'address' => $address,
        'contact' => $contact,
        'email' => $email,
        'status' => $status,
        'trainer_id' => $trainer_id,
        'ini_weight' => $ini_weight,
        'curr_weight' => $curr_weight,
        'initial_weight' => $ini_weight,
        'current_weight' => $curr_weight,
        'ini_bodytype' => $ini_bodytype,
        'curr_bodytype' => $curr_bodytype,
        'ini_body_type' => $ini_bodytype,
        'curr_body_type' => $curr_bodytype,
        'body_type' => $curr_bodytype,
        'progress_date' => date('Y-m-d')
    ];

    if ($avatarFileName) {
        $updateMemberData['avatar'] = $avatarFileName;
        $updateMemberData['photo'] = $avatarFileName;
    }

    DB::update('members', $updateMemberData, 'user_id = ? AND tenant_id = ?', [$memberId, $tenantId]);

    // Sync changes to users table
    // Login stays enabled for Expired/Pending members so they can still see dues and renew.
    // Don't fabricate '<username>@gym.com' emails: login matches users by email across all gyms.
    $userUpdate = [
        'fullname' => $fullname,
        'email' => $email !== '' ? $email : null,
        'phone' => $contact
    ];
    if ($avatarFileName) {
        $userUpdate['avatar'] = $avatarFileName;
    }

    // Optional password reset
    if (!empty($_POST['new_password'])) {
        $rawPass = $_POST['new_password'];
        $bcryptHash = password_hash($rawPass, PASSWORD_DEFAULT);
        // bcrypt for the legacy members row too (was unsalted md5)
        DB::update('members', ['password' => $bcryptHash], 'user_id = ? AND tenant_id = ?', [$memberId, $tenantId]);
        $userUpdate['password'] = $bcryptHash;
    }

    DB::update('users', $userUpdate, 'tenant_id = ? AND member_id = ?', [$tenantId, $memberId]);

    Auth::auditLog('UPDATE_MEMBER', "Updated profile of member $fullname (#$memberId)");
    redirect('members.php', 'success', "Member profile for $fullname updated successfully!");
}

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
include __DIR__ . '/../includes/topbar.php';
    $currentAvatarUrl = api_member_avatar_url($member['avatar'] ?? null, $member['photo'] ?? null);
?>

<div class="card" style="max-width: 860px; margin: 0 auto;">
    <div class="card-header">
        <div class="card-title">
            <i class="fas fa-user-edit"></i>
            <span>Edit Member: <?php echo e($member['fullname']); ?></span>
        </div>
        <div style="display: flex; gap: 8px;">
            <a href="view-member-report.php?id=<?php echo $memberId; ?>" class="btn btn-secondary btn-sm">
                <i class="fas fa-id-card"></i> ID & Progress
            </a>
            <a href="members.php" class="btn btn-secondary btn-sm">
                <i class="fas fa-arrow-left"></i> Back
            </a>
        </div>
    </div>
    <div class="card-body">
        <form method="POST" action="" enctype="multipart/form-data">
            <?php echo Auth::csrfField(); ?>

            <!-- Profile Photo Box -->
            <div style="background: var(--bg-app); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 16px; margin-bottom: 20px; display: flex; align-items: center; gap: 20px;">
                <div style="width: 72px; height: 72px; border-radius: 50%; background: var(--bg-card); border: 2px solid var(--primary); display: flex; align-items: center; justify-content: center; overflow: hidden; flex-shrink: 0;" id="avatar-preview-box">
                    <?php if ($currentAvatarUrl): ?>
                        <img id="avatar-preview-img" src="<?php echo htmlspecialchars($currentAvatarUrl); ?>" style="width: 100%; height: 100%; object-fit: cover;" alt="Member Avatar" />
                        <i class="fas fa-camera" id="avatar-preview-icon" style="font-size: 1.5rem; color: var(--text-muted); display: none;"></i>
                    <?php else: ?>
                        <i class="fas fa-camera" id="avatar-preview-icon" style="font-size: 1.5rem; color: var(--text-muted);"></i>
                        <img id="avatar-preview-img" src="" style="width: 100%; height: 100%; object-fit: cover; display: none;" alt="Preview" />
                    <?php endif; ?>
                </div>
                <div style="flex: 1;">
                    <label class="form-label" style="margin-bottom: 4px;">Member Profile Photo (App & ID Pass)</label>
                    <input type="file" name="avatar" id="avatar-file-input" class="form-control" accept="image/*" onchange="previewMemberPhoto(this)" style="font-size: 0.85rem;" />
                    <small style="color: var(--text-muted);">Upload a new photo to update the profile across the Member App & Digital Pass.</small>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Full Name *</label>
                    <input type="text" name="fullname" class="form-control" value="<?php echo e($member['fullname']); ?>" required />
                </div>
                <div class="form-group">
                    <label class="form-label">Username (Read-only)</label>
                    <input type="text" class="form-control" value="<?php echo e($member['username']); ?>" readonly style="background: var(--bg-app);" />
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Contact Number *</label>
                    <input type="text" name="contact" class="form-control" value="<?php echo e($member['contact']); ?>" required />
                </div>
                <div class="form-group">
                    <label class="form-label">Email Address</label>
                    <input type="email" name="email" class="form-control" value="<?php echo e($member['email'] ?? ''); ?>" />
                </div>
                <div class="form-group">
                    <label class="form-label">Gender</label>
                    <select name="gender" class="form-select">
                        <option value="Male" <?php echo $member['gender'] === 'Male' ? 'selected' : ''; ?>>Male</option>
                        <option value="Female" <?php echo $member['gender'] === 'Female' ? 'selected' : ''; ?>>Female</option>
                        <option value="Other" <?php echo $member['gender'] === 'Other' ? 'selected' : ''; ?>>Other</option>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Service Package</label>
                    <select name="services" class="form-select">
                        <?php foreach ($rates as $r): ?>
                            <option value="<?php echo e($r['name']); ?>" <?php echo $member['services'] === $r['name'] ? 'selected' : ''; ?>>
                                <?php echo e($r['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Plan Duration</label>
                    <select name="plan" class="form-select">
                        <option value="1" <?php echo $member['plan'] == 1 ? 'selected' : ''; ?>>1 Month</option>
                        <option value="3" <?php echo $member['plan'] == 3 ? 'selected' : ''; ?>>3 Months</option>
                        <option value="6" <?php echo $member['plan'] == 6 ? 'selected' : ''; ?>>6 Months</option>
                        <option value="12" <?php echo $member['plan'] == 12 ? 'selected' : ''; ?>>12 Months</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Membership Status</label>
                    <select name="status" class="form-select">
                        <option value="Active" <?php echo $member['status'] === 'Active' ? 'selected' : ''; ?>>Active</option>
                        <option value="Expired" <?php echo $member['status'] === 'Expired' ? 'selected' : ''; ?>>Expired</option>
                        <option value="Pending" <?php echo $member['status'] === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                    </select>
                </div>
            </div>

            <div class="card-header" style="padding-left: 0; padding-right: 0; margin-top: 10px; margin-bottom: 16px;">
                <div class="card-title" style="font-size: 1rem;">
                    <i class="fas fa-weight"></i>
                    <span>Physical Transformation & Body Metrics</span>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Initial Weight (kg)</label>
                    <input type="number" step="0.1" name="ini_weight" class="form-control" value="<?php echo e($member['ini_weight'] ?? $member['initial_weight'] ?? '70'); ?>" />
                </div>
                <div class="form-group">
                    <label class="form-label">Current Weight (kg)</label>
                    <input type="number" step="0.1" name="curr_weight" class="form-control" value="<?php echo e($member['curr_weight'] ?? $member['current_weight'] ?? '70'); ?>" />
                </div>
                <div class="form-group">
                    <label class="form-label">Initial Body Type</label>
                    <input type="text" name="ini_bodytype" class="form-control" value="<?php echo e($member['ini_bodytype'] ?? $member['ini_body_type'] ?? 'Athletic'); ?>" placeholder="e.g. Slim, Chubby" />
                </div>
                <div class="form-group">
                    <label class="form-label">Current Body Type</label>
                    <input type="text" name="curr_bodytype" class="form-control" value="<?php echo e($member['curr_bodytype'] ?? $member['curr_body_type'] ?? $member['body_type'] ?? 'Athletic'); ?>" placeholder="e.g. Athletic, Muscular" />
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Assign Personal Trainer</label>
                    <select name="trainer_id" class="form-select">
                        <option value="">None / General Gym Trainer</option>
                        <?php foreach ($trainers as $tr): ?>
                            <option value="<?php echo $tr['user_id']; ?>" <?php echo ($member['trainer_id'] ?? 0) == $tr['user_id'] ? 'selected' : ''; ?>>
                                <?php echo e($tr['fullname']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Reset Password (Leave blank to keep current)</label>
                    <input type="password" name="new_password" class="form-control" placeholder="Enter new password" />
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Address</label>
                <input type="text" name="address" class="form-control" value="<?php echo e($member['address']); ?>" />
            </div>

            <div style="margin-top: 24px; display: flex; justify-content: flex-end; gap: 12px;">
                <a href="members.php" class="btn btn-secondary">Cancel</a>
                <button type="submit" name="update_member" value="1" class="btn btn-primary btn-lg">
                    <i class="fas fa-save"></i> Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function previewMemberPhoto(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const previewImg = document.getElementById('avatar-preview-img');
            const previewIcon = document.getElementById('avatar-preview-icon');
            if (previewImg) {
                previewImg.src = e.target.result;
                previewImg.style.display = 'block';
            }
            if (previewIcon) {
                previewIcon.style.display = 'none';
            }
        };
        reader.readAsDataURL(input.files[0]);
    }
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>