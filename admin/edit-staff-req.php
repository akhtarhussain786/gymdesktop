<?php
// RETIRED legacy CodeAstro page: raw unscoped SQL with no tenant isolation. Superseded by admin/staffs.php.
// Kept only as a stub so old bookmarks land on the modern page; the legacy code below never runs.
require_once __DIR__ . '/../core/auth.php';
Auth::requireAuth(['gym_admin']);
redirect(base_url('/admin/staffs.php'), 'info', 'This legacy page has been retired.');
exit;
?>
<?php
session_start();
//the isset function to check username is already loged in and stored on the session
if(!isset($_SESSION['user_id'])){
header('location:../index.php');	
}
?>

<?php 
        
            if(isset($_POST['fullname'])){
            $fullname = $_POST["fullname"];    
            $username = $_POST["username"];
            $gender = $_POST["gender"];
            $contact = $_POST["contact"];
            $address = $_POST["address"];
            $designation = $_POST["designation"];
            $id = $_POST["id"];
            
            include 'dbcon.php';
            //code after connection is successfull
            //update query
            $qry = "update staffs set fullname='$fullname', username='$username', gender='$gender', contact='$contact',  address='$address', designation='$designation' where user_id='$id'";
            $result = mysqli_query($con,$qry); //query executes

            if(!$result){
                echo"ERROR!!";
            }else {

                header('Location:staffs.php');

            }

            }else{
                echo"<h3>YOU ARE NOT AUTHORIZED TO REDIRECT THIS PAGE. GO BACK to <a href='index.php'> DASHBOARD </a></h3>";
            }
?>