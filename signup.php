<?php
session_start();
include('connect.php');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $fullname = mysqli_real_escape_string($conn, $_POST['fullname']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Check if username contains numbers
    if (preg_match('/[0-9]/', $fullname)) {
        die("Username should not contain numbers. <a href='signup.html'>Go back</a>");
    }

    // Check if passwords match
    if ($password !== $confirm_password) {
        die("Passwords do not match. <a href='signup.html'>Go back</a>");
    }

    // Hash the password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // Check if email already exists
    $check_email = "SELECT * FROM users WHERE email='$email'";
    $result = mysqli_query($conn, $check_email);

    if (mysqli_num_rows($result) > 0) {
        die("Email already registered. <a href='signup.html'>Try again</a> <br>
        Try <a href='login.html'>Login </a>");
    }

    // Insert user into database (no role field)
    $insert_user = "INSERT INTO users (username, email, password) VALUES ('$fullname', '$email', '$hashed_password')";

    if (mysqli_query($conn, $insert_user)) {
        // Set session and go directly to dashboard
        $_SESSION['userid'] = mysqli_insert_id($conn);
        $_SESSION['username'] = $fullname;
        
        header("Location: dashboard.php");
        exit();
    } else {
        die("Error: " . mysqli_error($conn));
    }
}
?>