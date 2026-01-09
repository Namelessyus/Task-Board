<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';
require_once 'connect.php';

// Function to get HTML email template for reminders
function getReminderTemplate($task, $reminder_type) {
    $reminder_text = "";
    switch($reminder_type) {
        case '2_days':
            $reminder_text = "Your task is due in 2 days";
            break;
        case '1_day':
            $reminder_text = "Your task is due tomorrow";
            break;
        case '12_hours':
            $reminder_text = "Your task is due in 12 hours";
            break;
        case '1_hour':
            $reminder_text = "Your task is due in 1 hour";
            break;
        default:
            $reminder_text = "Reminder: Your task is approaching its due date";
    }
    
    return "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
            <h2 style='color: #333;'>Task Reminder: " . htmlspecialchars($task['title']) . "</h2>
            <p>Hello " . htmlspecialchars($task['user_name']) . ",</p>
            <p><strong>$reminder_text</strong></p>
            
            <div style='background: #f5f5f5; padding: 15px; border-radius: 5px; margin: 15px 0;'>
                <h3 style='margin-top: 0;'>" . htmlspecialchars($task['title']) . "</h3>
                <p><strong>Priority:</strong> " . htmlspecialchars($task['priority']) . "</p>
                <p><strong>Description:</strong> " . htmlspecialchars($task['description']) . "</p>
                <p><strong>Due Date:</strong> " . htmlspecialchars($task['due_date']) . "</p>
            </div>
            
            <p>Please log in to your TaskBoard account to view more details or update the task.</p>
            <br>
            <p>Best regards,<br><strong>TaskBoard Team</strong></p>
        </div>
    ";
}

// Function to get plain text email
function getPlainTextReminder($task, $reminder_type) {
    $reminder_text = "";
    switch($reminder_type) {
        case '2_days':
            $reminder_text = "Your task is due in 2 days";
            break;
        case '1_day':
            $reminder_text = "Your task is due tomorrow";
            break;
        case '12_hours':
            $reminder_text = "Your task is due in 12 hours";
            break;
        case '1_hour':
            $reminder_text = "Your task is due in 1 hour";
            break;
        default:
            $reminder_text = "Reminder: Your task is approaching its due date";
    }
    
    return "Task Reminder: {$task['title']}\n\n" .
           "Hello {$task['user_name']},\n\n" .
           "$reminder_text\n\n" .
           "Title: {$task['title']}\n" .
           "Priority: {$task['priority']}\n" .
           "Description: {$task['description']}\n" .
           "Due Date: {$task['due_date']}\n\n" .
           "Please log in to your TaskBoard account to view more details or update the task.\n\n" .
           "Best regards,\nTaskBoard Team";
}

// Function to calculate reminder times based on priority
function getReminderTimes($due_date, $priority) {
    $reminders = array();
    $due_timestamp = strtotime($due_date);
    
    if (!$due_timestamp) {
        return $reminders;
    }
    
    switch(strtolower($priority)) {
        case 'high':
            // 2 days before
            $reminders['2_days'] = date('Y-m-d H:i:s', $due_timestamp - (2 * 24 * 60 * 60));
            // 1 day before
            $reminders['1_day'] = date('Y-m-d H:i:s', $due_timestamp - (1 * 24 * 60 * 60));
            // 12 hours before
            $reminders['12_hours'] = date('Y-m-d H:i:s', $due_timestamp - (12 * 60 * 60));
            // 1 hour before
            $reminders['1_hour'] = date('Y-m-d H:i:s', $due_timestamp - (1 * 60 * 60));
            break;
            
        case 'medium':
            // 1 day before
            $reminders['1_day'] = date('Y-m-d H:i:s', $due_timestamp - (1 * 24 * 60 * 60));
            // 12 hours before
            $reminders['12_hours'] = date('Y-m-d H:i:s', $due_timestamp - (12 * 60 * 60));
            break;
            
        case 'low':
            // 1 day before
            $reminders['1_day'] = date('Y-m-d H:i:s', $due_timestamp - (1 * 24 * 60 * 60));
            // 12 hours before
            $reminders['12_hours'] = date('Y-m-d H:i:s', $due_timestamp - (12 * 60 * 60));
            break;
    }
    
    return $reminders;
}

// Function to check if a reminder should be sent
function shouldSendReminder($task, $current_time) {
    $due_date = $task['due_date'];
    $priority = strtolower($task['priority']);
    $reminder_times = getReminderTimes($due_date, $priority);
    
    foreach ($reminder_times as $reminder_type => $reminder_time) {
        // Check if current time is within 5 minutes of reminder time
        $reminder_timestamp = strtotime($reminder_time);
        $current_timestamp = strtotime($current_time);
        
        if ($reminder_timestamp && $current_timestamp) {
            $time_diff = abs($current_timestamp - $reminder_timestamp);
            
            // If we're within 5 minutes of the reminder time and haven't sent this reminder yet
            if ($time_diff <= 300) { // 300 seconds = 5 minutes
                // Check if this specific reminder has already been sent
                $check_query = "SELECT COUNT(*) as count FROM task_reminders 
                                WHERE task_id = ? AND reminder_type = ?";
                $stmt = mysqli_prepare($GLOBALS['conn'], $check_query);
                mysqli_stmt_bind_param($stmt, "is", $task['task_id'], $reminder_type);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                $row = mysqli_fetch_assoc($result);
                mysqli_stmt_close($stmt);
                
                if ($row['count'] == 0) {
                    return array(
                        'send' => true,
                        'reminder_type' => $reminder_type,
                        'reminder_time' => $reminder_time
                    );
                }
            }
        }
    }
    
    return array('send' => false);
}

// Main execution
if (!$conn) {
    die("Database connection failed");
}

// Get current date and time
$current_time = date('Y-m-d H:i:s');
echo "Current time: $current_time<br><br>";

// Query to get all tasks that are not completed and have due dates in the future
$query = "SELECT t.id as task_id, t.title, t.description, t.due_date, t.priority,
                 u.email, u.username as user_name 
          FROM tasks t 
          JOIN users u ON t.assigned_to = u.id 
          WHERE t.due_date > NOW() 
          AND t.status != 'completed' 
          ORDER BY t.due_date ASC";
          
$result = mysqli_query($conn, $query);

if (!$result) {
    die("Error executing query: " . mysqli_error($conn));
}

$task_count = mysqli_num_rows($result);

if ($task_count > 0) {
    echo "Found $task_count active tasks.<br><br>";
    
    // Check if reminders table exists, create if not
    $check_table_query = "CREATE TABLE IF NOT EXISTS task_reminders (
        id INT AUTO_INCREMENT PRIMARY KEY,
        task_id INT NOT NULL,
        reminder_type VARCHAR(20) NOT NULL,
        sent_at DATETIME NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
        UNIQUE KEY unique_reminder (task_id, reminder_type)
    )";
    
    mysqli_query($conn, $check_table_query);
    
    // Initialize PHPMailer
    $mail = new PHPMailer(true);
    
    // SMTP configuration
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'schoolusecfa@gmail.com';
    $mail->Password = '......'; // Your app password
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = 587;
    
    // Sender
    $mail->setFrom('schoolusecfa@gmail.com', 'TaskBoard');
    $mail->isHTML(true);
    
    $sent_count = 0;
    $failed_count = 0;
    
    // Process each task
    while ($task = mysqli_fetch_assoc($result)) {
        $task_id = $task['task_id'];
        $user_email = $task['email'];
        $user_name = $task['user_name'];
        $task_title = $task['title'];
        $priority = $task['priority'];
        
        // Check if we should send a reminder
        $reminder_check = shouldSendReminder($task, $current_time);
        
        if ($reminder_check['send']) {
            $reminder_type = $reminder_check['reminder_type'];
            
            // Prepare email
            $mail->clearAddresses();
            $mail->addAddress($user_email, $user_name);
            
            $subject_prefix = "";
            switch($priority) {
                case 'high':
                    $subject_prefix = "[HIGH PRIORITY] ";
                    break;
                case 'medium':
                    $subject_prefix = "[Medium Priority] ";
                    break;
                case 'low':
                    $subject_prefix = "[Low Priority] ";
                    break;
            }
            
            $mail->Subject = $subject_prefix . "Task Reminder: " . htmlspecialchars($task_title);
            $mail->Body = getReminderTemplate($task, $reminder_type);
            $mail->AltBody = getPlainTextReminder($task, $reminder_type);
            
            // Try to send email
            try {
                if ($mail->send()) {
                    // Log the reminder in database
                    $log_query = "INSERT INTO task_reminders (task_id, reminder_type, sent_at) 
                                  VALUES (?, ?, NOW()) 
                                  ON DUPLICATE KEY UPDATE sent_at = NOW()";
                    $stmt = mysqli_prepare($conn, $log_query);
                    
                    if ($stmt) {
                        mysqli_stmt_bind_param($stmt, "is", $task_id, $reminder_type);
                        mysqli_stmt_execute($stmt);
                        
                        $sent_count++;
                        
                        // Display which reminder was sent
                        $reminder_display = "";
                        switch($reminder_type) {
                            case '2_days':
                                $reminder_display = "2-day reminder";
                                break;
                            case '1_day':
                                $reminder_display = "1-day reminder";
                                break;
                            case '12_hours':
                                $reminder_display = "12-hour reminder";
                                break;
                            case '1_hour':
                                $reminder_display = "1-hour reminder";
                                break;
                        }
                        
                        echo "✓ $reminder_display sent to: $user_email (Task: $task_title, Priority: $priority)<br>";
                        
                        mysqli_stmt_close($stmt);
                    } else {
                        $failed_count++;
                        echo "✗ Failed to log reminder for: $user_email<br>";
                    }
                } else {
                    $failed_count++;
                    echo "✗ Email send failed for: $user_email<br>";
                }
            } catch (Exception $e) {
                $failed_count++;
                echo "✗ Exception for $user_email: " . $e->getMessage() . "<br>";
            }
        }
    }
    
    // Summary
    echo "<br><strong>Summary:</strong><br>";
    echo "Total tasks checked: $task_count<br>";
    echo "Reminders sent: $sent_count<br>";
    echo "Reminders failed: $failed_count<br>";
    
    // Show upcoming reminders in the next 24 hours
    echo "<br><strong>Upcoming reminders in next 24 hours:</strong><br>";
    $next_24_hours = date('Y-m-d H:i:s', strtotime('+24 hours'));
    
    $upcoming_query = "SELECT t.id, t.title, t.due_date, t.priority, u.name, u.email 
                       FROM tasks t 
                       JOIN users u ON t.assigned_to = u.id 
                       WHERE t.due_date > NOW() 
                       AND t.due_date <= ? 
                       AND t.status != 'completed'
                       ORDER BY t.priority DESC, t.due_date ASC";
    
    $stmt = mysqli_prepare($conn, $upcoming_query);
    mysqli_stmt_bind_param($stmt, "s", $next_24_hours);
    mysqli_stmt_execute($stmt);
    $upcoming_result = mysqli_stmt_get_result($stmt);
    
    $upcoming_count = 0;
    while ($upcoming_task = mysqli_fetch_assoc($upcoming_result)) {
        $upcoming_count++;
        $time_left = strtotime($upcoming_task['due_date']) - time();
        $hours_left = round($time_left / 3600, 1);
        
        echo "• {$upcoming_task['title']} - Due: {$upcoming_task['due_date']} ";
        echo "({$hours_left} hours left) - Priority: {$upcoming_task['priority']}<br>";
    }
    
    if ($upcoming_count == 0) {
        echo "No tasks due in the next 24 hours.<br>";
    }
    
    mysqli_stmt_close($stmt);
    
} else {
    echo "No active tasks found.<br>";
}

// Free result and close connection
mysqli_free_result($result);
mysqli_close($conn);
?>