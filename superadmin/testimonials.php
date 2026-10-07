<?php
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/helpers.php';
Auth::requireAuth('super_admin');

$page = 'super_testimonials';
$pageTitle = 'Landing Page Testimonials';
$pageSubtitle = 'Manage verified client reviews and social proof displayed on the public landing page';

// Ensure testimonials table exists and is seeded
DB::query("CREATE TABLE IF NOT EXISTS `testimonials` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `author_name` VARCHAR(150) NOT NULL,
    `designation` VARCHAR(255) NOT NULL,
    `quote` TEXT NOT NULL,
    `avatar` VARCHAR(255) NULL,
    `rating` INT DEFAULT 5,
    `sort_order` INT DEFAULT 0,
    `is_active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$testCount = (int)DB::fetchValue("SELECT COUNT(*) FROM `testimonials`");
if ($testCount === 0) {
    DB::insert('testimonials', [
        'author_name' => 'Vikram Singhania',
        'designation' => 'Founder, Titan Athletics (3 Branches)',
        'quote' => 'Managing attendance, renewals and Cashfree UPI payments from one dark dashboard has completely transformed how our front desk operates. Our renewal rate jumped by 24% in the first 60 days.',
        'avatar' => 'customer/img/demo/av1.jpg',
        'rating' => 5,
        'sort_order' => 1,
        'is_active' => 1
    ]);
    DB::insert('testimonials', [
        'author_name' => 'Ananya Roy',
        'designation' => 'Managing Director, Crossfit Matrix',
        'quote' => 'The QR check-in and athlete mobile app make our gym look like an Apple product. Members constantly compliment how seamless it is to check their workout splits and fee receipts.',
        'avatar' => 'customer/img/demo/av2.jpg',
        'rating' => 5,
        'sort_order' => 2,
        'is_active' => 1
    ]);
    DB::insert('testimonials', [
        'author_name' => 'Rohan Malhotra',
        'designation' => 'Head Coach, Iron Vault Studios',
        'quote' => 'The trainer command center and automated WhatsApp expiry notifications alone have eliminated all awkward payment follow-up conversations. It pays for itself ten times over.',
        'avatar' => 'customer/img/demo/av3.jpg',
        'rating' => 5,
        'sort_order' => 3,
        'is_active' => 1
    ]);
}

// Handle Form Submissions (Create / Update / Status Toggle)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::verifyCsrf();

    if (isset($_POST['save_testimonial'])) {
        $id = (int)($_POST['id'] ?? 0);
        $author_name = trim($_POST['author_name'] ?? '');
        $designation = trim($_POST['designation'] ?? '');
        $quote = trim($_POST['quote'] ?? '');
        $rating = max(1, min(5, (int)($_POST['rating'] ?? 5)));
        $sort_order = (int)($_POST['sort_order'] ?? 0);
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        // An avatar is either an absolute http(s) URL or a local image file under one of the site's image folders
        $isValidAvatar = function ($v) {
            return preg_match('#^https?://[^\s"\'<>]+$#i', $v)
                || (preg_match('#^/?(uploads|customer/img|assets)/[A-Za-z0-9_\-./]+\.(jpe?g|png|webp|avif|gif)$#i', $v) && strpos($v, '..') === false);
        };
        $avatar = trim($_POST['existing_avatar'] ?? '');
        if ($avatar !== '' && !$isValidAvatar($avatar)) {
            $avatar = '';
        }

        // Handle Avatar File Upload
        if (!empty($_FILES['avatar_file']['name']) && $_FILES['avatar_file']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/../uploads/testimonials/';
            if (!is_dir($uploadDir)) {
                @mkdir($uploadDir, 0755, true);
            }
            $ext = strtolower(pathinfo($_FILES['avatar_file']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'avif'])) {
                $filename = 'avatar_' . time() . '_' . rand(100, 999) . '.' . $ext;
                if (move_uploaded_file($_FILES['avatar_file']['tmp_name'], $uploadDir . $filename)) {
                    $avatar = 'uploads/testimonials/' . $filename;
                }
            }
        } elseif (!empty($_POST['avatar_url'])) {
            $avatarUrl = trim($_POST['avatar_url']);
            if ($isValidAvatar($avatarUrl)) {
                $avatar = $avatarUrl;
            } else {
                set_flash('error', 'Avatar must be an https:// image URL or an uploaded image; the default avatar was used instead.');
            }
        }

        if (empty($author_name) || empty($quote)) {
            set_flash('error', 'Author name and testimonial quote are required.');
        } else {
            if ($id > 0) {
                DB::update('testimonials', [
                    'author_name' => $author_name,
                    'designation' => $designation,
                    'quote' => $quote,
                    'avatar' => $avatar ?: 'customer/img/demo/av1.jpg',
                    'rating' => $rating,
                    'sort_order' => $sort_order,
                    'is_active' => $is_active
                ], 'id = ?', [$id]);
                Auth::auditLog('UPDATE_TESTIMONIAL', "Updated testimonial by $author_name (#$id)");
                set_flash('success', "Testimonial by '$author_name' updated successfully.");
            } else {
                $newId = DB::insert('testimonials', [
                    'author_name' => $author_name,
                    'designation' => $designation,
                    'quote' => $quote,
                    'avatar' => $avatar ?: 'customer/img/demo/av1.jpg',
                    'rating' => $rating,
                    'sort_order' => $sort_order,
                    'is_active' => $is_active
                ]);
                Auth::auditLog('CREATE_TESTIMONIAL', "Created testimonial by $author_name (#$newId)");
                set_flash('success', "New testimonial by '$author_name' added successfully!");
            }
        }
        redirect(base_url('/superadmin/testimonials.php'));
    }

    // Handle Delete (POST + CSRF only)
    if (isset($_POST['delete_id'])) {
        $delId = (int)$_POST['delete_id'];
        DB::delete('testimonials', 'id = ?', [$delId]);
        Auth::auditLog('DELETE_TESTIMONIAL', "Deleted testimonial #$delId");
        set_flash('success', 'Testimonial deleted successfully.');
        redirect(base_url('/superadmin/testimonials.php'));
    }

    // Handle Toggle Active (POST + CSRF only)
    if (isset($_POST['toggle_id'])) {
        $toggleId = (int)$_POST['toggle_id'];
        $current = (int)DB::fetchValue("SELECT is_active FROM testimonials WHERE id = ?", [$toggleId]);
        $newStatus = $current ? 0 : 1;
        DB::update('testimonials', ['is_active' => $newStatus], 'id = ?', [$toggleId]);
        Auth::auditLog('TOGGLE_TESTIMONIAL', "Set testimonial #$toggleId active=$newStatus");
        set_flash('success', 'Testimonial status updated.');
        redirect(base_url('/superadmin/testimonials.php'));
    }
}

$testimonials = DB::fetchAll("SELECT * FROM testimonials ORDER BY sort_order ASC, id DESC");

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
include __DIR__ . '/../includes/topbar.php';
?>

<style>
/* Modern Modal & Testimonial Form Styles */
.modal-backdrop {
    position: fixed !important;
    top: 0 !important;
    left: 0 !important;
    right: 0 !important;
    bottom: 0 !important;
    width: 100vw !important;
    height: 100vh !important;
    background: rgba(5, 8, 13, 0.85) !important;
    backdrop-filter: blur(12px) !important;
    -webkit-backdrop-filter: blur(12px) !important;
    z-index: 99999 !important;
    display: none;
    align-items: center !important;
    justify-content: center !important;
    padding: 20px !important;
}

.modal-backdrop.show {
    display: flex !important;
}

.modern-modal-content {
    background: #0D1219 !important;
    border: 1px solid rgba(199, 255, 46, 0.25) !important;
    border-radius: 24px !important;
    box-shadow: 0 30px 70px rgba(0, 0, 0, 0.95), 0 0 40px rgba(199, 255, 46, 0.12) !important;
    width: 100% !important;
    max-width: 680px !important;
    max-height: 90vh !important;
    overflow-y: auto !important;
    position: relative !important;
    margin: auto !important;
    color: #F0F4F8;
    animation: modalPopIn 0.25s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}

@keyframes modalPopIn {
    0% { transform: scale(0.92) translateY(20px); opacity: 0; }
    100% { transform: scale(1) translateY(0); opacity: 1; }
}

.modern-modal-header {
    background: #111720;
    padding: 22px 28px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.modern-modal-body {
    padding: 28px;
}

.modern-modal-footer {
    background: #111720;
    padding: 18px 28px;
    border-top: 1px solid rgba(255, 255, 255, 0.08);
    display: flex;
    justify-content: flex-end;
    align-items: center;
    gap: 12px;
}

/* Avatar Live Uploader */
.avatar-upload-box {
    display: flex;
    align-items: center;
    gap: 20px;
    background: #151B23;
    padding: 18px;
    border-radius: 18px;
    border: 1px dashed rgba(199, 255, 46, 0.3);
    margin-bottom: 22px;
    transition: border-color 0.2s;
}

.avatar-preview-wrap {
    position: relative;
    width: 68px;
    height: 68px;
    border-radius: 50%;
    flex-shrink: 0;
    border: 2px solid var(--lime, #C7FF2E);
    box-shadow: 0 0 16px rgba(199, 255, 46, 0.3);
    overflow: hidden;
    background: #05080D;
}

.avatar-preview-wrap img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

/* Interactive Star Rating */
.star-rating-picker {
    display: flex;
    align-items: center;
    gap: 8px;
    background: #151B23;
    padding: 10px 16px;
    border-radius: 14px;
    border: 1px solid rgba(255, 255, 255, 0.08);
}

.star-rating-stars {
    display: flex;
    gap: 6px;
    font-size: 1.35rem;
    color: #4B5563;
    cursor: pointer;
}

.star-rating-stars .star-item {
    transition: transform 0.15s ease, color 0.15s ease;
}

.star-rating-stars .star-item.active {
    color: var(--gold, #F4C430);
    text-shadow: 0 0 10px rgba(244, 196, 48, 0.4);
}

.star-rating-stars .star-item:hover {
    transform: scale(1.25);
}

.star-rating-label {
    font-size: 0.85rem;
    font-weight: 700;
    color: #F4C430;
    margin-left: 8px;
}

/* Modern Input Groups */
.form-input-modern {
    background: #151B23 !important;
    border: 1px solid rgba(255, 255, 255, 0.1) !important;
    border-radius: 14px !important;
    color: #fff !important;
    padding: 12px 16px !important;
    font-size: 0.92rem !important;
    transition: all 0.2s ease !important;
}

.form-input-modern:focus {
    border-color: var(--lime, #C7FF2E) !important;
    box-shadow: 0 0 0 3px rgba(199, 255, 46, 0.15) !important;
    background: #111720 !important;
    outline: none !important;
}

.form-input-modern::placeholder {
    color: #64748B !important;
}

/* Switch Toggle */
.modern-switch {
    display: flex;
    align-items: center;
    gap: 12px;
    background: #151B23;
    padding: 12px 18px;
    border-radius: 14px;
    border: 1px solid rgba(255, 255, 255, 0.08);
    cursor: pointer;
    user-select: none;
}

.modern-switch input {
    display: none;
}

.switch-slider {
    width: 44px;
    height: 24px;
    background: #334155;
    border-radius: 999px;
    position: relative;
    transition: background 0.25s ease;
}

.switch-slider::after {
    content: '';
    position: absolute;
    width: 18px;
    height: 18px;
    background: #fff;
    border-radius: 50%;
    top: 3px;
    left: 3px;
    transition: transform 0.25s cubic-bezier(0.34, 1.56, 0.64, 1);
}

.modern-switch input:checked + .switch-slider {
    background: var(--lime, #C7FF2E);
}

.modern-switch input:checked + .switch-slider::after {
    transform: translateX(20px);
    background: #05080D;
}
</style>

<div class="card">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
        <div class="card-title">
            <i class="fas fa-quote-right" style="color: var(--lime);"></i>
            <span>Landing Page Testimonials (<?php echo count($testimonials); ?>)</span>
        </div>
        <div>
            <button type="button" class="btn btn-primary" onclick="newTestimonial()">
                <i class="fas fa-plus"></i> Add New Testimonial
            </button>
        </div>
    </div>
    
    <div class="table-responsive">
        <table class="table" style="margin-bottom: 0;">
            <thead>
                <tr>
                    <th style="width: 70px;">Avatar</th>
                    <th>Author & Gym / Title</th>
                    <th>Rating</th>
                    <th>Testimonial Quote</th>
                    <th style="width: 80px;">Order</th>
                    <th style="width: 100px;">Status</th>
                    <th style="width: 120px; text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($testimonials)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 40px 20px; color: var(--text-muted);">
                            <i class="fas fa-comment-slash" style="font-size: 2.5rem; margin-bottom: 12px; display: block; opacity: 0.4;"></i>
                            No testimonials added yet. Click "+ Add New Testimonial" to create one.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($testimonials as $t): ?>
                        <tr>
                            <td>
                                <?php 
                                $avatarSrc = $t['avatar'] ? (str_starts_with($t['avatar'], 'http') ? $t['avatar'] : base_url('/' . ltrim($t['avatar'], '/'))) : base_url('/customer/img/demo/av1.jpg'); 
                                ?>
                                <img src="<?php echo htmlspecialchars($avatarSrc); ?>" 
                                     alt="<?php echo htmlspecialchars($t['author_name']); ?>" 
                                     style="width: 44px; height: 44px; border-radius: 50%; object-fit: cover; border: 2px solid rgba(255,255,255,0.1);" 
                                     onerror="this.src='<?php echo base_url('/customer/img/demo/av1.jpg'); ?>';" />
                            </td>
                            <td>
                                <div style="font-weight: 700; color: #fff;"><?php echo htmlspecialchars($t['author_name']); ?></div>
                                <div style="font-size: 0.8rem; color: var(--text-muted);"><?php echo htmlspecialchars($t['designation']); ?></div>
                            </td>
                            <td>
                                <span style="color: var(--gold, #F4C430);">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="fa<?php echo ($i <= $t['rating']) ? 's' : 'r'; ?> fa-star"></i>
                                    <?php endfor; ?>
                                </span>
                            </td>
                            <td>
                                <div style="max-width: 420px; font-size: 0.88rem; color: var(--text-secondary); line-height: 1.45;">
                                    "<?php echo htmlspecialchars($t['quote']); ?>"
                                </div>
                            </td>
                            <td>
                                <span style="display: inline-block; padding: 2px 8px; border-radius: 4px; background: rgba(255,255,255,0.06); font-size: 0.8rem; font-weight: 700;">#<?php echo (int)$t['sort_order']; ?></span>
                            </td>
                            <td>
                                <form method="POST" action="<?php echo base_url('/superadmin/testimonials.php'); ?>" style="display: inline; margin: 0;">
                                    <?php echo Auth::csrfField(); ?>
                                    <input type="hidden" name="toggle_id" value="<?php echo (int)$t['id']; ?>" />
                                    <button type="submit" style="background: none; border: none; padding: 0; cursor: pointer;" title="Click to toggle status">
                                        <span class="status-badge status-<?php echo $t['is_active'] ? 'active' : 'inactive'; ?>">
                                            <span class="status-dot"></span>
                                            <?php echo $t['is_active'] ? 'Active' : 'Hidden'; ?>
                                        </span>
                                    </button>
                                </form>
                            </td>
                            <td style="text-align: right;">
                                <div style="display: inline-flex; gap: 6px;">
                                    <button type="button" class="btn-icon" onclick='editTestimonial(<?php echo json_encode($t, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP); ?>)' title="Edit Testimonial">
                                        <i class="fas fa-edit" style="color: var(--cyan, #00D9FF);"></i>
                                    </button>
                                    <form method="POST" action="<?php echo base_url('/superadmin/testimonials.php'); ?>" style="display: inline; margin: 0;" onsubmit="return confirm('Are you sure you want to delete this testimonial?');">
                                        <?php echo Auth::csrfField(); ?>
                                        <input type="hidden" name="delete_id" value="<?php echo (int)$t['id']; ?>" />
                                        <button type="submit" class="btn-icon" title="Delete">
                                            <i class="fas fa-trash" style="color: #ef4444;"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- High-Tech Modern Modal: Add / Edit Testimonial -->
<div class="modal-backdrop" id="testimonial-modal" style="display: none;" onclick="if(event.target === this) App.closeModal('testimonial-modal')">
    <div class="modal-content modern-modal-content" style="max-width: 680px;">
        <div class="modern-modal-header">
            <div style="display: flex; align-items: center; gap: 12px;">
                <div style="width: 40px; height: 40px; border-radius: 12px; background: rgba(199, 255, 46, 0.12); border: 1px solid rgba(199, 255, 46, 0.25); display: flex; align-items: center; justify-content: center;">
                    <i class="fas fa-quote-left" style="color: var(--lime); font-size: 1.1rem;"></i>
                </div>
                <div>
                    <div id="testimonial-modal-title" style="font-weight: 800; font-size: 1.15rem; color: #fff; letter-spacing: -0.2px;">Add New Testimonial</div>
                    <div style="font-size: 0.75rem; color: var(--text-muted);">Verified client review for public website showcase</div>
                </div>
            </div>
            <button type="button" class="btn-icon" onclick="App.closeModal('testimonial-modal')" style="background: rgba(255,255,255,0.06); border-radius: 10px; width: 36px; height: 36px;">
                <i class="fas fa-times" style="color: #94A3B8;"></i>
            </button>
        </div>

        <form method="POST" action="<?php echo base_url('/superadmin/testimonials.php'); ?>" enctype="multipart/form-data">
            <?php echo Auth::csrfField(); ?>
            <input type="hidden" name="id" id="testimonial-id" value="0" />
            <input type="hidden" name="existing_avatar" id="testimonial-existing-avatar" value="" />
            <input type="hidden" name="rating" id="testimonial-rating-input" value="5" />

            <div class="modern-modal-body">
                <!-- Avatar Upload with Live Preview -->
                <div class="avatar-upload-box">
                    <div class="avatar-preview-wrap">
                        <img id="avatar-live-preview" src="<?php echo base_url('/customer/img/demo/av1.jpg'); ?>" alt="Avatar Preview" />
                    </div>
                    <div style="flex: 1;">
                        <div style="font-weight: 700; font-size: 0.95rem; color: #fff; margin-bottom: 4px;">Author Photograph</div>
                        <div style="font-size: 0.8rem; color: var(--text-muted); margin-bottom: 10px;">Upload square avatar or enter custom image path</div>
                        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                            <label class="btn btn-secondary btn-sm" style="cursor: pointer; margin: 0; background: rgba(255,255,255,0.08); border-color: rgba(255,255,255,0.15);">
                                <i class="fas fa-cloud-arrow-up me-1 text-lime"></i> Upload Photo
                                <input type="file" name="avatar_file" id="testimonial-avatar-file" accept="image/*" style="display: none;" onchange="previewAvatarFile(this)" />
                            </label>
                            <input type="text" name="avatar_url" id="testimonial-avatar-url" class="form-input-modern" style="flex: 1; min-width: 180px; padding: 6px 12px !important; font-size: 0.82rem !important;" placeholder="Or image path / URL..." oninput="previewAvatarUrl(this.value)" />
                        </div>
                    </div>
                </div>

                <div class="form-row" style="margin-bottom: 16px;">
                    <div class="form-group" style="flex: 1.2; margin-bottom: 0;">
                        <label class="form-label" style="font-weight: 700; font-size: 0.85rem; color: #E2E8F0; margin-bottom: 6px;">Author Full Name *</label>
                        <input type="text" name="author_name" id="testimonial-author-name" class="form-input-modern" style="width: 100%;" placeholder="e.g. Vikram Singhania" required />
                    </div>
                    <div class="form-group" style="flex: 1; margin-bottom: 0;">
                        <label class="form-label" style="font-weight: 700; font-size: 0.85rem; color: #E2E8F0; margin-bottom: 6px;">Client Rating *</label>
                        <div class="star-rating-picker">
                            <div class="star-rating-stars" id="star-picker-container">
                                <span class="star-item active" onclick="setRating(1)" onmouseover="highlightStars(1)" onmouseout="resetHighlight()"><i class="fas fa-star"></i></span>
                                <span class="star-item active" onclick="setRating(2)" onmouseover="highlightStars(2)" onmouseout="resetHighlight()"><i class="fas fa-star"></i></span>
                                <span class="star-item active" onclick="setRating(3)" onmouseover="highlightStars(3)" onmouseout="resetHighlight()"><i class="fas fa-star"></i></span>
                                <span class="star-item active" onclick="setRating(4)" onmouseover="highlightStars(4)" onmouseout="resetHighlight()"><i class="fas fa-star"></i></span>
                                <span class="star-item active" onclick="setRating(5)" onmouseover="highlightStars(5)" onmouseout="resetHighlight()"><i class="fas fa-star"></i></span>
                            </div>
                            <span class="star-rating-label" id="star-rating-text">5.0 Star</span>
                        </div>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 16px;">
                    <label class="form-label" style="font-weight: 700; font-size: 0.85rem; color: #E2E8F0; margin-bottom: 6px;">Designation / Gym Title *</label>
                    <input type="text" name="designation" id="testimonial-designation" class="form-input-modern" style="width: 100%;" placeholder="e.g. Founder, Titan Athletics (3 Branches)" required />
                </div>

                <div class="form-group" style="margin-bottom: 16px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                        <label class="form-label" style="font-weight: 700; font-size: 0.85rem; color: #E2E8F0; margin-bottom: 0;">Testimonial Review Quote *</label>
                        <span id="char-count" style="font-size: 0.75rem; color: var(--text-muted);">0 / 300 chars</span>
                    </div>
                    <textarea name="quote" id="testimonial-quote" rows="4" class="form-input-modern" style="width: 100%; resize: vertical; line-height: 1.5;" placeholder="Describe how Fitisify OS transformed their front desk, member renewals, and daily gym operations..." required oninput="updateCharCount(this)"></textarea>
                </div>

                <div class="form-row" style="align-items: center; margin-bottom: 0;">
                    <div class="form-group" style="flex: 1; margin-bottom: 0;">
                        <label class="form-label" style="font-weight: 700; font-size: 0.85rem; color: #E2E8F0; margin-bottom: 6px;">Display Sort Order</label>
                        <input type="number" name="sort_order" id="testimonial-sort-order" class="form-input-modern" style="width: 100%;" value="0" />
                    </div>
                    <div class="form-group" style="flex: 1.2; margin-bottom: 0; padding-top: 24px;">
                        <label class="modern-switch" for="testimonial-is-active">
                            <input type="checkbox" name="is_active" id="testimonial-is-active" value="1" checked />
                            <div class="switch-slider"></div>
                            <span style="font-weight: 700; font-size: 0.88rem; color: #fff;">Display on Website</span>
                        </label>
                    </div>
                </div>
            </div>

            <div class="modern-modal-footer">
                <button type="button" class="btn btn-secondary" onclick="App.closeModal('testimonial-modal')" style="border-radius: 999px; padding: 10px 22px;">Cancel</button>
                <button type="submit" name="save_testimonial" class="btn btn-primary" style="border-radius: 999px; padding: 10px 26px; background: var(--lime, #C7FF2E); color: #05080D; font-weight: 800; border: none; box-shadow: 0 4px 18px rgba(199, 255, 46, 0.35);">
                    <i class="fas fa-check me-1"></i> Save Testimonial
                </button>
            </div>
        </form>
    </div>
</div>

<script>
let currentRating = 5;

function setRating(val) {
    currentRating = val;
    document.getElementById('testimonial-rating-input').value = val;
    document.getElementById('star-rating-text').innerText = val + '.0 Star';
    renderStars(val);
}

function highlightStars(val) {
    renderStars(val);
}

function resetHighlight() {
    renderStars(currentRating);
}

function renderStars(count) {
    const stars = document.querySelectorAll('#star-picker-container .star-item');
    stars.forEach((star, idx) => {
        if (idx < count) {
            star.classList.add('active');
        } else {
            star.classList.remove('active');
        }
    });
}

function updateCharCount(el) {
    document.getElementById('char-count').innerText = el.value.length + ' chars';
}

function previewAvatarFile(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('avatar-live-preview').src = e.target.result;
        };
        reader.readAsDataURL(input.files[0]);
    }
}

function previewAvatarUrl(url) {
    if (url && url.trim().length > 3) {
        document.getElementById('avatar-live-preview').src = url;
    }
}

function newTestimonial() {
    document.getElementById('testimonial-id').value = '0';
    document.getElementById('testimonial-existing-avatar').value = '';
    document.getElementById('testimonial-author-name').value = '';
    document.getElementById('testimonial-designation').value = '';
    document.getElementById('testimonial-quote').value = '';
    document.getElementById('testimonial-avatar-url').value = '';
    document.getElementById('testimonial-avatar-file').value = '';
    document.getElementById('testimonial-sort-order').value = '0';
    document.getElementById('testimonial-is-active').checked = true;
    document.getElementById('avatar-live-preview').src = '<?php echo base_url('/customer/img/demo/av1.jpg'); ?>';
    document.getElementById('testimonial-modal-title').innerText = 'Add New Testimonial';
    document.getElementById('char-count').innerText = '0 chars';
    setRating(5);
    
    App.openModal('testimonial-modal');
}

function editTestimonial(t) {
    document.getElementById('testimonial-id').value = t.id;
    document.getElementById('testimonial-existing-avatar').value = t.avatar || '';
    document.getElementById('testimonial-author-name').value = t.author_name;
    document.getElementById('testimonial-designation').value = t.designation;
    document.getElementById('testimonial-quote').value = t.quote;
    document.getElementById('testimonial-avatar-url').value = t.avatar || '';
    document.getElementById('testimonial-avatar-file').value = '';
    document.getElementById('testimonial-sort-order').value = t.sort_order;
    document.getElementById('testimonial-is-active').checked = (parseInt(t.is_active) === 1);
    document.getElementById('testimonial-modal-title').innerText = 'Edit Testimonial (' + t.author_name + ')';
    document.getElementById('char-count').innerText = (t.quote || '').length + ' chars';
    
    const avatarSrc = t.avatar ? (t.avatar.startsWith('http') ? t.avatar : '<?php echo base_url('/'); ?>/' + t.avatar.replace(/^\//, '')) : '<?php echo base_url('/customer/img/demo/av1.jpg'); ?>';
    document.getElementById('avatar-live-preview').src = avatarSrc;

    setRating(parseInt(t.rating) || 5);
    
    App.openModal('testimonial-modal');
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
