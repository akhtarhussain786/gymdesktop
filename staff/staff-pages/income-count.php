<?php
// RETIRED legacy staff panel page (no tenant isolation / GET actions without CSRF). Superseded by admin/index.php.
// Kept only as a stub; the legacy code below never runs.
require_once __DIR__ . '/../../core/auth.php';
Auth::requireAuth(['gym_admin', 'staff', 'trainer']);
redirect(base_url((($_SESSION['role'] ?? '') === 'trainer') ? '/trainer/index.php' : '/admin/index.php'), 'info', 'This legacy page has been retired.');
exit;
?>
<?php

$servername="localhost";
$uname="root";
$pass="";
$db="gymnsb";

$conn=mysqli_connect($servername,$uname,$pass,$db);

if(!$conn){
    die("Connection Failed");
}

$sql = "SELECT SUM( amount) FROM members";
        $amountsum = mysqli_query($conn, $sql) or die(mysqli_error($sql));
        $row_amountsum = mysqli_fetch_assoc($amountsum);
        $totalRows_amountsum = mysqli_num_rows($amountsum);
        echo $row_amountsum['SUM( amount)'];
?>