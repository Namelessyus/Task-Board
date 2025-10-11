<?php
session_start();

if (!isset($_SESSION['username'])) {
    header("Location: login.html");
    exit();
}

$username = $_SESSION['username'];
$role = $_SESSION['role'];
?>

<!DOCTYPE html>
<html>
<head>
    <title>Dashboard</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="dashboard-container">
        <h1>Welcome, <?php echo $username; ?>!</h1>
        <p>Your role: <?php echo ucfirst($role); ?></p>
        <a href="logout.php">Logout</a>

        <?php if($role == "supervisor") { ?>
            <h2>Supervisor Dashboard</h2>
            <ul>
                <li>Create and manage projects</li>
                <li>Add tasks and assign to participants</li>
                <li>Monitor team progress</li>
            </ul>
        <?php } else { ?>
            <h2>Participant Dashboard</h2>
            <ul>
                <li>View assigned tasks</li>
                <li>Select tasks from available pool</li>
                <li>Update task status</li>
            </ul>
        <?php } ?>
    </div>
</body>
</html>
