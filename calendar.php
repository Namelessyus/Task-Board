<?php
session_start();
// Check if user is logged in
if (!isset($_SESSION['userid'])) {
    header('Location: login.php');
    exit();
}
$user_id = $_SESSION['userid'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TaskBoard Calendar with Notifications</title>
    <style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background: #c4b5e8;
        min-height: 100vh;
        padding: 0; /* Changed: Remove body padding */
    }

    .container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 20px; /* Changed: Add padding to container */
    }

    /* Header Nav */
    .header {
        background: #764BA2;
        color: white;
        padding: 12px 24px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        position: sticky;
        top: 0;
        z-index: 1000;
        margin-bottom: 2rem;
        border-radius: 0 0 1rem 1rem; /* Changed: Only round bottom corners */
    }

    .logo {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 18px;
        font-weight: 600;
    }

    .logo a {
        color: white;
        text-decoration: none;
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 18px;
        font-weight: 600;
    }

    .nav {
        display: flex;
        gap: 32px;
    }

    .nav a {
        color: white;
        text-decoration: none;
        font-weight: 500;
        padding: 8px 0;
        border-bottom: 2px solid transparent;
        transition: border-color 0.3s;
    }

    .nav a:hover, .nav a.active {
        border-bottom-color: white;
    }

    /* Logout Button */
    .logout-btn {
        background: rgba(255,255,255,0.2);
        border: 1px solid rgba(255,255,255,0.3);
        color: white;
        padding: 6px 16px;
        border-radius: 6px;
        cursor: pointer;
        transition: background 0.3s;
        text-decoration: none;
        display: inline-block;
    }

    .logout-btn:hover {
        background: rgba(255,255,255,0.3);
    }

    .calendar-container {
        background: white;
        border-radius: 12px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        overflow: hidden;
    }

    .calendar-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 20px;
        background: #764ba2;
        color: white;
    }

    .calendar-navigation {
        display: flex;
        gap: 10px;
        align-items: center;
    }

    .nav-btn {
        background: #764ba2;
        border: none;
        color: white;
        width: 40px;
        height: 40px;
        border-radius: 50%;
        cursor: pointer;
        font-size: 18px;
        transition: background 0.3s;
    }

    .nav-btn:hover {
        background: rgba(255,255,255,0.2);
    }

    .current-month {
        font-size: 24px;
        font-weight: 600;
    }

    .calendar-grid {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
        gap: 1px;
        background: #e0e0e0;
    }

    .day-header {
        background: #764ba2;
        color: white;
        padding: 15px;
        text-align: center;
        font-weight: 600;
    }

    .day-cell {
        background: white;
        min-height: 120px;
        padding: 10px;
        position: relative;
        transition: background 0.3s;
    }

    .day-cell:hover {
        background: #f8f9fa;
    }

    .day-number {
        font-size: 18px;
        font-weight: bold;
        margin-bottom: 5px;
    }

    .event {
        font-size: 12px;
        padding: 4px 8px;
        margin: 3px 0;
        border-radius: 4px;
        cursor: pointer;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
        border-left: 3px solid;
    }

    .event:hover {
        opacity: 0.9;
        transform: translateX(2px);
    }

    .event.task {
        background: #e8f5e9;
        border-left-color: #2ecc71;
    }

    .event.project {
        background: #e3f2fd;
        border-left-color: #3498db;
    }

    .today {
        background: #e3ddf1ff !important;
    }

    .other-month {
        background: #f8f9fa;
        color: #95a5a6;
    }

    .legend {
        display: flex;
        gap: 20px;
        margin-top: 20px;
        padding: 15px;
        background: white;
        border-radius: 8px;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    }

    .legend-item {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .legend-color {
        width: 15px;
        height: 15px;
        border-radius: 3px;
    }

    .loading {
        text-align: center;
        padding: 40px;
        color: #7f8c8d;
    }

    .empty-day {
        color: #666768ff;
        font-size: 12px;
        text-align: center;
        margin-top: 10px;
    }

    .event.priority-low {
        opacity: 0.7;
    }

    .event.priority-high {
        font-weight: bold;
        border-left-width: 4px;
    }

    .notification-bell {
        position: relative;
        cursor: pointer;
        font-size: 24px;
        padding: 10px;
        color: white;
    }

    .notification-count {
        position: absolute;
        top: 0;
        right: 0;
        background: #e74c3c;
        color: white;
        border-radius: 50%;
        width: 20px;
        height: 20px;
        font-size: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .notification-panel {
        position: fixed;
        top: 80px;
        right: 20px;
        width: 350px;
        background: white;
        border-radius: 8px;
        box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        z-index: 1000;
        display: none;
        max-height: 500px;
        overflow-y: auto;
    }

    .notification-item {
        padding: 12px 15px;
        border-bottom: 1px solid #eee;
        cursor: pointer;
        transition: background 0.3s;
    }

    .notification-item:hover {
        background: #f8f9fa;
    }

    .notification-item.unread {
        background: #e8f4fd;
    }

    .notification-title {
        font-weight: bold;
        margin-bottom: 5px;
    }

    .notification-message {
        font-size: 14px;
        color: #555;
    }

    .notification-time {
        font-size: 12px;
        color: #888;
        margin-top: 5px;
    }

    .notification-type {
        display: inline-block;
        padding: 2px 8px;
        border-radius: 12px;
        font-size: 11px;
        margin-right: 8px;
    }

    .type-task_due { background: #ffeb3b; color: #333; }
    .type-project_due { background: #2196f3; color: white; }
    .type-overdue { background: #f44336; color: white; }

    .notification-settings {
        margin-top: 20px;
        padding: 20px;
        background: white;
        border-radius: 8px;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    }

    .settings-title {
        font-size: 18px;
        font-weight: bold;
        margin-bottom: 15px;
    }

    .setting-item {
        margin-bottom: 10px;
        display: flex;
        align-items: center;
    }

    .setting-item input[type="checkbox"] {
        margin-right: 10px;
    }

    .dismiss-btn {
        background: none;
        border: none;
        color: #888;
        cursor: pointer;
        padding: 5px;
        font-size: 18px;
        line-height: 1;
    }

    .dismiss-btn:hover {
        color: #ff4444;
    }

    @media (max-width: 768px) {
        .day-cell {
            min-height: 80px;
            padding: 5px;
        }
        
        .event {
            font-size: 10px;
            padding: 2px 4px;
        }
        
        .notification-panel {
            width: 300px;
            right: 10px;
        }
    }
</style>
</head>
<body>

<!-- Header -->
    <header class="header">
        <div class="logo">
            <a href="dashboard.php"><i class="fas fa-tasks"></i> Task Board</a>
        </div>
        <nav class="nav">
    <a href="dashboard.php" >Home</a>
    <a href="create_project.php">Create Project</a>
    <a href="join_project.php">Join Project</a>
    <a href="calendar.php" class="active">Calendar</a>
    <a href="account.php">Account</a>
</nav>
        <a href="logout.php" class="logout-btn">Logout</a>
    </header>


    <div class="container">
        <div class="calendar-container">
            <div class="calendar-header">
                <div class="calendar-navigation">
                    <button class="nav-btn" onclick="changeMonth(-1)">‹</button>
                    <h2 class="current-month" id="currentMonth"></h2>
                    <button class="nav-btn" onclick="changeMonth(1)">›</button>
                </div>
                <div style="display: flex; gap: 10px; align-items: center;">
                    <button class="nav-btn" onclick="goToToday()">Today</button>
                    <div class="notification-bell" onclick="toggleNotificationPanel()">
                        🔔
                        <span class="notification-count" id="notificationCount">0</span>
                    </div>
                </div>
            </div>

            <div class="calendar-grid" id="calendarGrid">
                <!-- Day headers will be inserted here -->
            </div>
        </div>

        <!-- Notification Panel -->
        <div class="notification-panel" id="notificationPanel">
            <div style="padding: 15px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center;">
                <strong>Upcoming & Overdue Items</strong>
                <button onclick="clearAllNotifications()" style="background: none; border: none; color: #007bff; cursor: pointer;">Dismiss All</button>
            </div>
            <div id="notificationList"></div>
        </div>

        <div class="legend">
            <div class="legend-item">
                <div class="legend-color" style="background: #e8f5e9; border-left: 3px solid #2ecc71;"></div>
                <span>Tasks</span>
            </div>
            <div class="legend-item">
                <div class="legend-color" style="background: #e3f2fd; border-left: 3px solid #3498db;"></div>
                <span>Projects</span>
            </div>
            <div class="legend-item">
                <div class="legend-color" style="background: #fff3cd; border-left: 3px solid #ffc107;"></div>
                <span>High Priority</span>
            </div>
            <div class="legend-item">
                <div class="legend-color" style="background: #f8d7da; border-left: 3px solid #dc3545;"></div>
                <span>Overdue</span>
            </div>
        </div>

        <!-- Notification Settings -->
        <div class="notification-settings">
            <div class="settings-title">Notification Settings</div>
            <div class="setting-item">
                <input type="checkbox" id="enableDesktop" checked onchange="updateNotificationSettings()">
                <label for="enableDesktop">Enable Desktop Notifications</label>
            </div>
            <div class="setting-item">
                <input type="checkbox" id="notifyTaskDue" checked onchange="updateNotificationSettings()">
                <label for="notifyTaskDue">Notify about upcoming task due dates</label>
            </div>
            <div class="setting-item">
                <input type="checkbox" id="notifyProjectDue" checked onchange="updateNotificationSettings()">
                <label for="notifyProjectDue">Notify about upcoming project due dates</label>
            </div>
            <div class="setting-item">
                <input type="checkbox" id="notifyOverdue" checked onchange="updateNotificationSettings()">
                <label for="notifyOverdue">Notify about overdue items</label>
            </div>
            <div class="setting-item">
                <label for="remindBefore">Remind before (days):</label>
                <select id="remindBefore" onchange="updateNotificationSettings()" style="margin-left: 10px;">
                    <option value="0">On due date</option>
                    <option value="1" selected>1 day before</option>
                    <option value="2">2 days before</option>
                    <option value="3">3 days before</option>
                    <option value="7">1 week before</option>
                </select>
            </div>
            <div class="setting-item">
                <label for="notificationInterval">Check every (minutes):</label>
                <select id="notificationInterval" onchange="updateNotificationSettings()" style="margin-left: 10px;">
                    <option value="5">5 minutes</option>
                    <option value="15" selected>15 minutes</option>
                    <option value="30">30 minutes</option>
                    <option value="60">1 hour</option>
                </select>
            </div>
        </div>
    </div>

    <script>
        let currentDate = new Date();
        let currentMonth = currentDate.getMonth();
        let currentYear = currentDate.getFullYear();
        let notificationInterval;
        let notificationPermission = false;
        let notifiedItems = new Set(); // Store IDs of items we've already notified about
        let userTasks = [];
        let userProjects = [];

        // Days of the week
        const daysOfWeek = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

        // Store user ID from PHP session
        const userId = <?php echo $user_id; ?>;

        document.addEventListener('DOMContentLoaded', function() {
            if (!userId) {
                alert('Please login to view your calendar');
                window.location.href = 'login.php';
                return;
            }
            
            // Request notification permission
            requestNotificationPermission();
            
            // Load notification settings
            loadNotificationSettings();
            
            renderCalendar();
            loadCalendarData();
        });

        function requestNotificationPermission() {
            if ("Notification" in window) {
                if (Notification.permission === "granted") {
                    notificationPermission = true;
                } else if (Notification.permission !== "denied") {
                    Notification.requestPermission().then(permission => {
                        notificationPermission = permission === "granted";
                        if (notificationPermission) {
                            showDesktopNotification("Notifications Enabled", "You will now receive desktop notifications for your tasks and projects.");
                        }
                    });
                }
            }
        }

        function showDesktopNotification(title, message) {
            if (!notificationPermission) return;
            
            if (Notification.permission === "granted") {
                const options = {
                    body: message,
                    icon: '/favicon.ico',
                    requireInteraction: true
                };
                
                const notification = new Notification(title, options);
                
                notification.onclick = function() {
                    window.focus();
                    notification.close();
                };
                
                // Auto close after 10 seconds
                setTimeout(() => notification.close(), 10000);
            }
        }

        function checkForDueItems() {
            if (!userTasks.length && !userProjects.length) return;
            
            const now = new Date();
            const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
            const remindBeforeDays = parseInt(document.getElementById('remindBefore').value) || 1;
            const reminderDate = new Date(today);
            reminderDate.setDate(reminderDate.getDate() + remindBeforeDays);
            
            const notifications = [];
            const notifiedToday = new Set();
            
            // Check tasks
            if (document.getElementById('notifyTaskDue')?.checked) {
                userTasks.forEach(task => {
                    if (!task.due_date) return;
                    
                    const dueDate = new Date(task.due_date);
                    const dueDateOnly = new Date(dueDate.getFullYear(), dueDate.getMonth(), dueDate.getDate());
                    const todayOnly = new Date(today.getFullYear(), today.getMonth(), today.getDate());
                    
                    // Check if due today or within reminder period
                    if (dueDateOnly <= reminderDate && dueDateOnly >= todayOnly) {
                        const daysUntilDue = Math.ceil((dueDateOnly - todayOnly) / (1000 * 60 * 60 * 24));
                        const isDueToday = daysUntilDue === 0;
                        const isDueSoon = daysUntilDue > 0 && daysUntilDue <= remindBeforeDays;
                        
                        if (isDueToday || isDueSoon) {
                            const itemKey = `task_${task.id}_due_${dueDateOnly.toDateString()}`;
                            if (!notifiedItems.has(itemKey)) {
                                notifications.push({
                                    type: 'task_due',
                                    title: `Task ${isDueToday ? 'Due Today' : 'Due Soon'}: ${task.title}`,
                                    message: `Task "${task.title}" is due ${isDueToday ? 'today' : 'in ' + daysUntilDue + ' day(s)'}${task.project_title ? ' in project: ' + task.project_title : ''}`,
                                    priority: task.priority,
                                    dueDate: task.due_date,
                                    itemKey: itemKey
                                });
                                notifiedToday.add(itemKey);
                            }
                        }
                    }
                });
            }
            
            // Check projects
            if (document.getElementById('notifyProjectDue')?.checked) {
                userProjects.forEach(project => {
                    if (!project.due_date) return;
                    
                    const dueDate = new Date(project.due_date);
                    const dueDateOnly = new Date(dueDate.getFullYear(), dueDate.getMonth(), dueDate.getDate());
                    const todayOnly = new Date(today.getFullYear(), today.getMonth(), today.getDate());
                    
                    // Check if due today or within reminder period
                    if (dueDateOnly <= reminderDate && dueDateOnly >= todayOnly) {
                        const daysUntilDue = Math.ceil((dueDateOnly - todayOnly) / (1000 * 60 * 60 * 24));
                        const isDueToday = daysUntilDue === 0;
                        const isDueSoon = daysUntilDue > 0 && daysUntilDue <= remindBeforeDays;
                        
                        if (isDueToday || isDueSoon) {
                            const itemKey = `project_${project.id}_due_${dueDateOnly.toDateString()}`;
                            if (!notifiedItems.has(itemKey)) {
                                notifications.push({
                                    type: 'project_due',
                                    title: `Project ${isDueToday ? 'Due Today' : 'Due Soon'}: ${project.title}`,
                                    message: `Project "${project.title}" is due ${isDueToday ? 'today' : 'in ' + daysUntilDue + ' day(s)'}`,
                                    priority: project.priority,
                                    dueDate: project.due_date,
                                    itemKey: itemKey
                                });
                                notifiedToday.add(itemKey);
                            }
                        }
                    }
                });
            }
            
            // Check for overdue items
            if (document.getElementById('notifyOverdue')?.checked) {
                const yesterday = new Date(today);
                yesterday.setDate(yesterday.getDate() - 1);
                
                // Check overdue tasks
                userTasks.forEach(task => {
                    if (!task.due_date || task.status === 'completed') return;
                    
                    const dueDate = new Date(task.due_date);
                    const dueDateOnly = new Date(dueDate.getFullYear(), dueDate.getMonth(), dueDate.getDate());
                    
                    if (dueDateOnly < today) {
                        const itemKey = `task_${task.id}_overdue_${today.toDateString()}`;
                        if (!notifiedItems.has(itemKey)) {
                            const daysOverdue = Math.ceil((today - dueDateOnly) / (1000 * 60 * 60 * 24));
                            notifications.push({
                                type: 'overdue',
                                title: `⚠️ Task Overdue: ${task.title}`,
                                message: `Task "${task.title}" is ${daysOverdue} day(s) overdue${task.project_title ? ' in project: ' + task.project_title : ''}`,
                                priority: task.priority,
                                dueDate: task.due_date,
                                itemKey: itemKey
                            });
                            notifiedToday.add(itemKey);
                        }
                    }
                });
                
                // Check overdue projects
                userProjects.forEach(project => {
                    if (!project.due_date) return;
                    
                    const dueDate = new Date(project.due_date);
                    const dueDateOnly = new Date(dueDate.getFullYear(), dueDate.getMonth(), dueDate.getDate());
                    
                    if (dueDateOnly < today) {
                        const itemKey = `project_${project.id}_overdue_${today.toDateString()}`;
                        if (!notifiedItems.has(itemKey)) {
                            const daysOverdue = Math.ceil((today - dueDateOnly) / (1000 * 60 * 60 * 24));
                            notifications.push({
                                type: 'overdue',
                                title: `⚠️ Project Overdue: ${project.title}`,
                                message: `Project "${project.title}" is ${daysOverdue} day(s) overdue`,
                                priority: project.priority,
                                dueDate: project.due_date,
                                itemKey: itemKey
                            });
                            notifiedToday.add(itemKey);
                        }
                    }
                });
            }
            
            // Show desktop notifications
            if (document.getElementById('enableDesktop')?.checked && notifications.length > 0) {
                notifications.forEach(notification => {
                    showDesktopNotification(notification.title, notification.message);
                });
            }
            
            // Update notification panel if open
            if (document.getElementById('notificationPanel').style.display === 'block') {
                updateNotificationPanel();
            }
            
            // Add new notifications to notified items set
            notifiedToday.forEach(itemKey => {
                notifiedItems.add(itemKey);
            });
            
            // Update notification count
            updateNotificationCount();
        }

        function startNotificationPolling() {
            // Clear existing interval
            if (notificationInterval) {
                clearInterval(notificationInterval);
            }
            
            // Start new interval
            const intervalMinutes = parseInt(document.getElementById('notificationInterval').value) || 15;
            notificationInterval = setInterval(() => {
                checkForDueItems();
            }, intervalMinutes * 60 * 1000);
            
            // Also check immediately
            checkForDueItems();
        }

        function updateNotificationPanel() {
            const now = new Date();
            const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
            const remindBeforeDays = parseInt(document.getElementById('remindBefore').value) || 1;
            const reminderDate = new Date(today);
            reminderDate.setDate(reminderDate.getDate() + remindBeforeDays);
            
            const notificationList = document.getElementById('notificationList');
            let html = '';
            let count = 0;
            
            // Store temporary IDs for this session
            const notificationItems = [];
            
            // Check tasks
            userTasks.forEach(task => {
                if (!task.due_date) return;
                
                const dueDate = new Date(task.due_date);
                const dueDateOnly = new Date(dueDate.getFullYear(), dueDate.getMonth(), dueDate.getDate());
                
                if (dueDateOnly < today && task.status !== 'completed') {
                    // Overdue task
                    const daysOverdue = Math.ceil((today - dueDateOnly) / (1000 * 60 * 60 * 24));
                    const itemKey = `task_${task.id}_overdue_${today.toDateString()}`;
                    
                    if (!notifiedItems.has(itemKey)) {
                        notificationItems.push({
                            key: itemKey,
                            type: 'overdue',
                            title: `Overdue Task: ${task.title}`,
                            message: `${daysOverdue} day(s) overdue${task.project_title ? ' in ' + task.project_title : ''}`,
                            dueDate: task.due_date
                        });
                        count++;
                    }
                } else if (dueDateOnly <= reminderDate && dueDateOnly >= today) {
                    // Upcoming task
                    const daysUntilDue = Math.ceil((dueDateOnly - today) / (1000 * 60 * 60 * 24));
                    const itemKey = `task_${task.id}_due_${dueDateOnly.toDateString()}`;
                    
                    if (!notifiedItems.has(itemKey)) {
                        notificationItems.push({
                            key: itemKey,
                            type: 'task_due',
                            title: `Task Due: ${task.title}`,
                            message: `Due in ${daysUntilDue} day(s)${task.project_title ? ' in ' + task.project_title : ''}`,
                            dueDate: task.due_date
                        });
                        count++;
                    }
                }
            });
            
            // Check projects
            userProjects.forEach(project => {
                if (!project.due_date) return;
                
                const dueDate = new Date(project.due_date);
                const dueDateOnly = new Date(dueDate.getFullYear(), dueDate.getMonth(), dueDate.getDate());
                
                if (dueDateOnly < today) {
                    // Overdue project
                    const daysOverdue = Math.ceil((today - dueDateOnly) / (1000 * 60 * 60 * 24));
                    const itemKey = `project_${project.id}_overdue_${today.toDateString()}`;
                    
                    if (!notifiedItems.has(itemKey)) {
                        notificationItems.push({
                            key: itemKey,
                            type: 'overdue',
                            title: `Overdue Project: ${project.title}`,
                            message: `${daysOverdue} day(s) overdue`,
                            dueDate: project.due_date
                        });
                        count++;
                    }
                } else if (dueDateOnly <= reminderDate && dueDateOnly >= today) {
                    // Upcoming project
                    const daysUntilDue = Math.ceil((dueDateOnly - today) / (1000 * 60 * 60 * 24));
                    const itemKey = `project_${project.id}_due_${dueDateOnly.toDateString()}`;
                    
                    if (!notifiedItems.has(itemKey)) {
                        notificationItems.push({
                            key: itemKey,
                            type: 'project_due',
                            title: `Project Due: ${project.title}`,
                            message: `Due in ${daysUntilDue} day(s)`,
                            dueDate: project.due_date
                        });
                        count++;
                    }
                }
            });
            
            // Build HTML for each notification item
            notificationItems.forEach(item => {
                html += `
                    <div class="notification-item unread" onclick="dismissNotification('${item.key}')">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                            <div style="flex-grow: 1;">
                                <div class="notification-title">
                                    <span class="notification-type type-${item.type}">${item.type === 'task_due' ? 'Task Due' : item.type === 'project_due' ? 'Project Due' : 'Overdue'}</span>
                                    ${item.title}
                                </div>
                                <div class="notification-message">${item.message}</div>
                                <div class="notification-time">Due: ${formatDate(item.dueDate)}</div>
                            </div>
                            <button class="dismiss-btn" onclick="dismissNotification('${item.key}', event)">
                                ×
                            </button>
                        </div>
                    </div>
                `;
            });
            
            if (html === '') {
                html = '<div style="padding: 20px; text-align: center; color: #888;">No upcoming or overdue items</div>';
            }
            
            notificationList.innerHTML = html;
            document.getElementById('notificationCount').textContent = count;
        }

        function dismissNotification(itemKey, event = null) {
            if (event) {
                event.stopPropagation(); // Prevent the parent div click event
            }
            
            // Add to notified items to prevent showing again
            notifiedItems.add(itemKey);
            
            // Remove the notification from the panel
            const notificationElement = document.querySelector(`[onclick*="${itemKey}"]`);
            if (notificationElement) {
                notificationElement.remove();
            }
            
            // Update count
            updateNotificationCount();
            
            // If no notifications left, show message
            const notificationList = document.getElementById('notificationList');
            if (notificationList.children.length === 0) {
                notificationList.innerHTML = '<div style="padding: 20px; text-align: center; color: #888;">No upcoming or overdue items</div>';
            }
        }

        function clearAllNotifications() {
            // Mark all visible notifications as dismissed
            const notificationItems = document.querySelectorAll('.notification-item');
            notificationItems.forEach(item => {
                if (item.onclick) {
                    // Extract the itemKey from the onclick handler
                    const onclickStr = item.onclick.toString();
                    const match = onclickStr.match(/dismissNotification\('([^']+)'/);
                    if (match && match[1]) {
                        notifiedItems.add(match[1]);
                    }
                }
            });
            
            // Clear the notification panel
            document.getElementById('notificationList').innerHTML = 
                '<div style="padding: 20px; text-align: center; color: #888;">All notifications cleared</div>';
            
            // Update count to 0
            document.getElementById('notificationCount').textContent = '0';
            
            // Hide the panel
            const panel = document.getElementById('notificationPanel');
            panel.style.display = 'none';
            
            // Show confirmation
            if (notificationPermission && document.getElementById('enableDesktop')?.checked) {
                const notification = new Notification("Notifications Cleared", {
                    body: "All notifications have been dismissed",
                    icon: '/favicon.ico'
                });
                setTimeout(() => notification.close(), 3000);
            }
        }

        function updateNotificationCount() {
            const now = new Date();
            const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
            const remindBeforeDays = parseInt(document.getElementById('remindBefore').value) || 1;
            const reminderDate = new Date(today);
            reminderDate.setDate(reminderDate.getDate() + remindBeforeDays);
            
            let count = 0;
            
            // Count tasks (excluding dismissed ones)
            userTasks.forEach(task => {
                if (!task.due_date) return;
                
                const dueDate = new Date(task.due_date);
                const dueDateOnly = new Date(dueDate.getFullYear(), dueDate.getMonth(), dueDate.getDate());
                
                if (dueDateOnly < today && task.status !== 'completed') {
                    // Overdue task
                    const itemKey = `task_${task.id}_overdue_${today.toDateString()}`;
                    if (!notifiedItems.has(itemKey)) {
                        count++;
                    }
                } else if (dueDateOnly <= reminderDate && dueDateOnly >= today) {
                    // Upcoming task
                    const itemKey = `task_${task.id}_due_${dueDateOnly.toDateString()}`;
                    if (!notifiedItems.has(itemKey)) {
                        count++;
                    }
                }
            });
            
            // Count projects (excluding dismissed ones)
            userProjects.forEach(project => {
                if (!project.due_date) return;
                
                const dueDate = new Date(project.due_date);
                const dueDateOnly = new Date(dueDate.getFullYear(), dueDate.getMonth(), dueDate.getDate());
                
                if (dueDateOnly < today) {
                    // Overdue project
                    const itemKey = `project_${project.id}_overdue_${today.toDateString()}`;
                    if (!notifiedItems.has(itemKey)) {
                        count++;
                    }
                } else if (dueDateOnly <= reminderDate && dueDateOnly >= today) {
                    // Upcoming project
                    const itemKey = `project_${project.id}_due_${dueDateOnly.toDateString()}`;
                    if (!notifiedItems.has(itemKey)) {
                        count++;
                    }
                }
            });
            
            document.getElementById('notificationCount').textContent = count;
        }

        function toggleNotificationPanel() {
            const panel = document.getElementById('notificationPanel');
            if (panel.style.display === 'block') {
                panel.style.display = 'none';
            } else {
                panel.style.display = 'block';
                updateNotificationPanel();
            }
        }

        function loadNotificationSettings() {
            // Try to load from localStorage
            const settingsKey = `notification_settings_${userId}`;
            const savedSettings = localStorage.getItem(settingsKey);
            
            if (savedSettings) {
                const settings = JSON.parse(savedSettings);
                document.getElementById('enableDesktop').checked = settings.enableDesktop || true;
                document.getElementById('notifyTaskDue').checked = settings.notifyTaskDue || true;
                document.getElementById('notifyProjectDue').checked = settings.notifyProjectDue || true;
                document.getElementById('notifyOverdue').checked = settings.notifyOverdue || true;
                document.getElementById('remindBefore').value = settings.remindBefore || 1;
                document.getElementById('notificationInterval').value = settings.notificationInterval || 15;
            }
            
            // Start notification polling with saved interval
            startNotificationPolling();
        }

        function updateNotificationSettings() {
            const settings = {
                enableDesktop: document.getElementById('enableDesktop').checked,
                notifyTaskDue: document.getElementById('notifyTaskDue').checked,
                notifyProjectDue: document.getElementById('notifyProjectDue').checked,
                notifyOverdue: document.getElementById('notifyOverdue').checked,
                remindBefore: document.getElementById('remindBefore').value,
                notificationInterval: document.getElementById('notificationInterval').value
            };
            
            // Save to localStorage
            const settingsKey = `notification_settings_${userId}`;
            localStorage.setItem(settingsKey, JSON.stringify(settings));
            
            // Restart polling with new interval
            startNotificationPolling();
            
            // Update notification display
            updateNotificationPanel();
        }

        // Format date for display
        function formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', { 
                weekday: 'short', 
                year: 'numeric', 
                month: 'short', 
                day: 'numeric' 
            });
        }

        function renderCalendar() {
            const monthNames = ["January", "February", "March", "April", "May", "June",
                "July", "August", "September", "October", "November", "December"];
            
            // Update month title
            document.getElementById('currentMonth').textContent = 
                `${monthNames[currentMonth]} ${currentYear}`;
            
            // Clear calendar grid
            const calendarGrid = document.getElementById('calendarGrid');
            calendarGrid.innerHTML = '';
            
            // Add day headers
            daysOfWeek.forEach(day => {
                const dayHeader = document.createElement('div');
                dayHeader.className = 'day-header';
                dayHeader.textContent = day;
                calendarGrid.appendChild(dayHeader);
            });
            
            // Get first day of month
            const firstDay = new Date(currentYear, currentMonth, 1);
            const startingDay = firstDay.getDay();
            
            // Get days in month
            const daysInMonth = new Date(currentYear, currentMonth + 1, 0).getDate();
            
            // Get days from previous month to fill first week
            const prevMonthDays = new Date(currentYear, currentMonth, 0).getDate();
            
            // Fill in previous month days
            for (let i = 0; i < startingDay; i++) {
                const dayCell = createDayCell(prevMonthDays - startingDay + i + 1, true);
                calendarGrid.appendChild(dayCell);
            }
            
            // Fill in current month days
            const today = new Date();
            for (let day = 1; day <= daysInMonth; day++) {
                const dayCell = createDayCell(day, false);
                
                // Highlight today
                if (day === today.getDate() && 
                    currentMonth === today.getMonth() && 
                    currentYear === today.getFullYear()) {
                    dayCell.classList.add('today');
                }
                
                calendarGrid.appendChild(dayCell);
            }
            
            // Fill remaining cells with next month days
            const totalCells = 42; // 6 weeks * 7 days
            const remainingCells = totalCells - (startingDay + daysInMonth);
            
            for (let i = 1; i <= remainingCells; i++) {
                const dayCell = createDayCell(i, true);
                calendarGrid.appendChild(dayCell);
            }
        }

        function createDayCell(dayNumber, isOtherMonth) {
            const dayCell = document.createElement('div');
            dayCell.className = 'day-cell';
            dayCell.id = `day-${dayNumber}-${isOtherMonth ? 'other' : 'current'}`;
            
            const dayNumberSpan = document.createElement('div');
            dayNumberSpan.className = 'day-number';
            dayNumberSpan.textContent = dayNumber;
            dayCell.appendChild(dayNumberSpan);
            
            const eventsContainer = document.createElement('div');
            eventsContainer.className = 'events-container';
            dayCell.appendChild(eventsContainer);
            
            if (isOtherMonth) {
                dayCell.classList.add('other-month');
            }
            
            return dayCell;
        }

        function changeMonth(direction) {
            currentMonth += direction;
            
            if (currentMonth < 0) {
                currentMonth = 11;
                currentYear--;
            } else if (currentMonth > 11) {
                currentMonth = 0;
                currentYear++;
            }
            
            renderCalendar();
            loadCalendarData();
        }

        function goToToday() {
            currentDate = new Date();
            currentMonth = currentDate.getMonth();
            currentYear = currentDate.getFullYear();
            renderCalendar();
            loadCalendarData();
        }

        async function loadCalendarData() {
            if (!userId) return;
            
            // Show loading
            document.querySelectorAll('.day-cell').forEach(cell => {
                const eventsContainer = cell.querySelector('.events-container');
                if (eventsContainer) {
                    eventsContainer.innerHTML = '<div class="loading">...</div>';
                }
            });
            
            try {
                const response = await fetch(`get_calendar_data.php?year=${currentYear}&month=${currentMonth + 1}`);
                const data = await response.json();
                
                if (data.error) {
                    alert(data.error);
                    return;
                }
                
                // Store tasks and projects for notification checking
                userTasks = data.tasks || [];
                userProjects = data.projects || [];
                
                displayCalendarData(data);
                updateNotificationCount();
                checkForDueItems();
            } catch (error) {
                console.error('Error loading calendar data:', error);
                document.querySelectorAll('.day-cell').forEach(cell => {
                    const eventsContainer = cell.querySelector('.events-container');
                    if (eventsContainer) {
                        eventsContainer.innerHTML = '<div class="empty-day">Error loading</div>';
                    }
                });
            }
        }

        function displayCalendarData(data) {
            // Clear all events first
            document.querySelectorAll('.day-cell').forEach(cell => {
                const eventsContainer = cell.querySelector('.events-container');
                if (eventsContainer) {
                    eventsContainer.innerHTML = '';
                }
            });
            
            // Display projects
            if (data.projects && Array.isArray(data.projects)) {
                data.projects.forEach(project => {
                    if (project.due_date) {
                        const dueDate = new Date(project.due_date);
                        if (dueDate.getMonth() === currentMonth && 
                            dueDate.getFullYear() === currentYear) {
                            const dayCell = document.getElementById(`day-${dueDate.getDate()}-current`);
                            if (dayCell) {
                                const eventsContainer = dayCell.querySelector('.events-container');
                                const eventElement = createEventElement(project, 'project');
                                eventsContainer.appendChild(eventElement);
                            }
                        }
                    }
                });
            }
            
            // Display tasks
            if (data.tasks && Array.isArray(data.tasks)) {
                data.tasks.forEach(task => {
                    if (task.due_date) {
                        const dueDate = new Date(task.due_date);
                        if (dueDate.getMonth() === currentMonth && 
                            dueDate.getFullYear() === currentYear) {
                            const dayCell = document.getElementById(`day-${dueDate.getDate()}-current`);
                            if (dayCell) {
                                const eventsContainer = dayCell.querySelector('.events-container');
                                const eventElement = createEventElement(task, 'task');
                                eventsContainer.appendChild(eventElement);
                            }
                        }
                    }
                });
            }
            
            // Show empty message for days with no events
            document.querySelectorAll('.day-cell:not(.other-month)').forEach(cell => {
                const eventsContainer = cell.querySelector('.events-container');
                if (eventsContainer && eventsContainer.children.length === 0) {
                    eventsContainer.innerHTML = '<div class="empty-day">No events</div>';
                }
            });
        }

        function createEventElement(item, type) {
            const eventDiv = document.createElement('div');
            eventDiv.className = `event ${type}`;
            
            // Add priority class
            if (item.priority === 'high') {
                eventDiv.classList.add('priority-high');
                eventDiv.style.background = type === 'task' ? '#fff3cd' : '#f8d7da';
            } else if (item.priority === 'low') {
                eventDiv.classList.add('priority-low');
            }
            
            // Check if overdue
            const today = new Date();
            const dueDate = new Date(item.due_date);
            const dueDateOnly = new Date(dueDate.getFullYear(), dueDate.getMonth(), dueDate.getDate());
            const todayOnly = new Date(today.getFullYear(), today.getMonth(), today.getDate());
            
            if (dueDateOnly < todayOnly && (type === 'task' ? item.status !== 'completed' : true)) {
                eventDiv.style.background = '#f8d7da';
                eventDiv.style.borderLeftColor = '#dc3545';
            }
            
            // Tooltip content
            const tooltipContent = `${type === 'task' ? 'Task' : 'Project'} Details:\n\n` +
                                 `Title: ${item.title}\n` +
                                 `Due: ${formatDate(item.due_date)}\n` +
                                 `Priority: ${item.priority}\n` +
                                 (item.status ? `Status: ${item.status}\n` : '') +
                                 (type === 'task' && item.project_title ? `Project: ${item.project_title}\n` : '') +
                                 (item.description ? `Description: ${item.description.substring(0, 100)}${item.description.length > 100 ? '...' : ''}` : '');
            
            eventDiv.title = tooltipContent;
            
            // Truncate long titles
            const title = item.title.length > 15 ? item.title.substring(0, 15) + '...' : item.title;
            eventDiv.textContent = title;
            
            // Add click event for details
            eventDiv.onclick = function() {
                alert(tooltipContent);
            };
            
            return eventDiv;
        }
    </script>
</body>
</html>