<?php
session_start();
include('connect.php'); // your database connection file

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $password = $_POST['password'];

    // Check if user exists
    $sql = "SELECT * FROM users WHERE email='$email'";
    $result = mysqli_query($conn, $sql);

    if (mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);

        // Verify password
        if (password_verify($password, $row['password'])) {
            $_SESSION['username'] = $row['username'];
            $_SESSION['userid'] = $row['id'];
            $_SESSION['role'] = $row['role'];

            // If role is not set, redirect to choose_role
            if (is_null($row['role'])) {
                header("Location: choose_role.php");
                exit();
            } else {
                header("Location: dashboard.php");
                exit();
            }
        } else {
            die("Incorrect password. <a href='login.html'>Try again</a>");
        }
    } else {
        die("User not found. <a href='signup.html'>Sign up</a>");
    }
}
?>
