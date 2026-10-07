<?php
// RETIRED legacy CodeAstro page: raw unscoped SQL with no tenant isolation. Superseded by admin/index.php.
// Kept only as a stub so old bookmarks land on the modern page; the legacy code below never runs.
require_once __DIR__ . '/../core/auth.php';
Auth::requireAuth(['gym_admin', 'staff']);
redirect(base_url('/admin/index.php'), 'info', 'This legacy page has been retired.');
exit;
?>
<?php

$servername="localhost";
$uname="root";
$pass="";
$db="gymnsb2";

$conn=mysqli_connect($servername,$uname,$pass,$db);

if(!$conn){
    die("Connection Failed");
}

$sql = "SELECT * FROM equipment";
                $query = $conn->query($sql);

                echo "$query->num_rows";
                
?>