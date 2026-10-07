<?php
// RETIRED legacy staff panel page (no tenant isolation / GET actions without CSRF). Superseded by admin/members.php.
// Kept only as a stub; the legacy code below never runs.
require_once __DIR__ . '/../../core/auth.php';
Auth::requireAuth(['gym_admin', 'staff', 'trainer']);
redirect(base_url((($_SESSION['role'] ?? '') === 'trainer') ? '/trainer/index.php' : '/admin/members.php'), 'info', 'This legacy page has been retired.');
exit;
?>
<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('location:../index.php');
}

include "dbcon.php";

if (isset($_GET['id']) && isset($_GET['action'])) {
    $id = $_GET['id'];
    $action = $_GET['action'];

    if ($action == 'approve') {
        $sql = "UPDATE members SET status = 'approved' WHERE id = $id";
    } elseif ($action == 'reject') {
        $sql = "UPDATE members SET status = 'rejected' WHERE id = $id";
    }

    if (mysqli_query($conn, $sql)) {
        header('location: staff.php'); // Redirect back to the staff page
    } else {
        echo "Error: " . mysqli_error($conn);
    }
}
?>