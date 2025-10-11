<?php
include('connect.php'); // your database connection file

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $fullname = mysqli_real_escape_string($conn, $_POST['fullname']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

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
        die("Email already registered. <a href='signup.html'>Try again</a>");
    }

    // Insert user into database with NULL role
    $insert_user = "INSERT INTO users (username, email, password, role) VALUES ('$fullname', '$email', '$hashed_password', NULL)";

    if (mysqli_query($conn, $insert_user)) {
        // Redirect to login after successful signup
        header("Location: login.html");
        exit();
    } else {
        die("Error: " . mysqli_error($conn));
    }
}
?>
