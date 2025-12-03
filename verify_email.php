<?php
session_start();
include('connect.php');

if (isset($_GET['code'])) {
    $code = mysqli_real_escape_string($conn, $_GET['code']);
    
    $sql = "SELECT * FROM users WHERE verification_code = '$code' AND is_verified = 0";
    $result = mysqli_query($conn, $sql);
    
    if (mysqli_num_rows($result) > 0) {
        $update = "UPDATE users SET is_verified = 1, verification_code = NULL WHERE verification_code = '$code'";
        if (mysqli_query($conn, $update)) {
            echo "Email verified successfully! You can now <a href='login.php'>login</a>.";
        }
    } else {
        echo "Invalid or expired verification code.";
    }
}
?>