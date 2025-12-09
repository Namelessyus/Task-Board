<?php
session_start();
include('connect.php');

if (!isset($_SESSION['userid'])) {
    header("Location: login.php");
    exit();
}

$username = $_SESSION['username'];
$userid = $_SESSION['userid'];

// Get user's projects (created and joined) - excluding soft-deleted
$my_projects = [];
$joined_projects = [];

// Projects created by user (where user is supervisor) - excluding soft-deleted
$created_sql = "SELECT p.*, COUNT(pm.user_id) as member_count 
                FROM projects p 
                LEFT JOIN project_members pm ON p.id = pm.project_id 
                WHERE p.supervisor_id = '$userid' AND p.is_deleted = 0
                GROUP BY p.id 
                ORDER BY p.created_at DESC";
$created_result = mysqli_query($conn, $created_sql);
if ($created_result) {
    while ($row = mysqli_fetch_assoc($created_result)) {
        $my_projects[] = $row;
    }
}

// Projects joined by user (where user is participant) - excluding soft-deleted
$joined_sql = "SELECT p.*, pm.role as member_role 
               FROM projects p 
               JOIN project_members pm ON p.id = pm.project_id 
               WHERE pm.user_id = '$userid' AND p.supervisor_id != '$userid' AND p.is_deleted = 0
               ORDER BY p.created_at DESC";
$joined_result = mysqli_query($conn, $joined_sql);
if ($joined_result) {
    while ($row = mysqli_fetch_assoc($joined_result)) {
        $joined_projects[] = $row;
    }
}

// Combine all projects for the main grid
$all_projects = array_merge($my_projects, $joined_projects);

// Check for success message from project deletion
if (isset($_SESSION['success'])) {
    $success_message = $_SESSION['success'];
    unset($_SESSION['success']);
}
?>
<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" href="dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <title>Task Board - Dashboard</title>
    <style>
        /* Google Classroom Style */
        .classroom-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 25px;
            padding: 20px 0;
        }

        .class-card {
            background: white;
            border-radius: 12px;
            padding: 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            border: 1px solid #e2e8f0;
            overflow: hidden;
        }

        .class-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }

        .class-header {
            height: 100px;
            background: #764BA2;
            position: relative;
            padding: 20px;
            color: white;
        }

        .class-title {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 5px;
            color: white;
        }

        .class-description {
            font-size: 14px;
            opacity: 0.9;
            color: white;
        }

        .class-body {
            padding: 20px;
        }

        .class-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e2e8f0;
        }

        .class-role {
            background: #f59e0b;
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .class-role.participant {
            background: #764BA2;
        }

        .class-code {
            background: #48bb78;
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            font-family: 'Courier New', monospace;
            letter-spacing: 1px;
        }

        .class-stats {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 20px;
        }

        .stat-item {
            text-align: center;
            padding: 10px;
            background: #f7fafc;
            border-radius: 8px;
        }

        .stat-number {
            font-size: 24px;
            font-weight: 700;
            color: #667eea;
            display: block;
        }

        .stat-label {
            font-size: 12px;
            color: #718096;
            margin-top: 5px;
        }

        .class-actions {
            display: flex;
            gap: 10px;
        }

        .class-btn {
            flex: 1;
            padding: 10px;
            border: none;
            border-radius: 6px;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            text-align: center;
            font-size: 14px;
            transition: all 0.3s;
        }

        .btn-primary {
            background: #764BA2;
            color: white;
        }

        .btn-primary:hover {
            background: #5d3a7f;
            text-decoration: none;
            color: white;
        }

        .btn-secondary {
            background: #e2e8f0;
            color: #4a5568;
        }

        .btn-secondary:hover {
            background: #cbd5e0;
            text-decoration: none;
            color: #4a5568;
        }

        .welcome-section {
            background: white;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .welcome-title {
            font-size: 28px;
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 10px;
        }

        .welcome-subtitle {
            font-size: 16px;
            color: #718096;
            margin-bottom: 20px;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #718096;
        }

        .empty-state h3 {
            font-size: 24px;
            margin-bottom: 10px;
            color: #4a5568;
        }

        .empty-state p {
            font-size: 16px;
            margin-bottom: 25px;
        }

        .create-first-btn {
            background: #764BA2;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 16px;
            transition: all 0.3s;
        }

        .create-first-btn:hover {
            background: #5d3a7f;
            text-decoration: none;
            color: white;
            transform: translateY(-2px);
        }
        
        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 15px;
            border: 1px solid #c3e6cb;
            text-align: center;
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
    <a href="dashboard.php" class="active">Home</a>
    <a href="create_project.php">Create Project</a>
    <a href="join_project.php">Join Project</a>
    <a href="account.php">Account</a>
</nav>
        <a href="logout.php" class="logout-btn">Logout</a>
    </header>

    <!-- Dashboard Content -->
   <div class="dashboard-container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-section">
                <div class="dropdown-header" onclick="toggleDropdown('created-projects')">
                    <h3>Projects I Created</h3>
                    <span class="dropdown-arrow" id="created-arrow">▼</span>
                </div>
                <ul class="project-list dropdown-content" id="created-projects">
                    <?php foreach($my_projects as $project): ?>
                        <li>
                            <a href="project_detail.php?id=<?php echo $project['id']; ?>">
                                <span class="project-title-text">
                                    <?php 
                                    $title = htmlspecialchars($project['title']);
                                    if (strlen($title) > 18) {
                                        echo substr($title, 0, 18) . '...';
                                    } else {
                                        echo $title;
                                    }
                                    ?>
                                </span>
                                <span class="supervisor-badge">Supervisor</span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                    <?php if(empty($my_projects)): ?>
                        <li class="no-projects">No projects created</li>
                    <?php endif; ?>
                </ul>
            </div>
            
            <div class="sidebar-section">
                <div class="dropdown-header" onclick="toggleDropdown('joined-projects')">
                    <h3>Projects I Joined</h3>
                    <span class="dropdown-arrow" id="joined-arrow">▼</span>
                </div>
                <ul class="project-list dropdown-content" id="joined-projects">
                    <?php foreach($joined_projects as $project): ?>
                        <li>
                            <a href="project_detail.php?id=<?php echo $project['id']; ?>">
                                <span class="project-title-text">
                                    <?php 
                                    $title = htmlspecialchars($project['title']);
                                    if (strlen($title) > 18) {
                                        echo substr($title, 0, 18) . '...';
                                    } else {
                                        echo $title;
                                    }
                                    ?>
                                </span>
                                <span class="participant-badge">Participant</span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                    <?php if(empty($joined_projects)): ?>
                        <li class="no-projects">No projects joined</li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Welcome Section -->
            <?php if(isset($success_message)): ?>
                <div class="success-message">
                    <?php echo $success_message; ?>
                </div>
            <?php endif; ?>
 
            <?php if(!empty($all_projects)): ?>
                <div class="classroom-grid">
                    <?php foreach($all_projects as $project): 
                        $is_supervisor = ($project['supervisor_id'] == $userid);
                        
                        // Get task counts for this project (excluding soft-deleted)
                        $tasks_sql = "SELECT status, COUNT(*) as count FROM tasks WHERE project_id = '{$project['id']}' AND is_deleted = 0 GROUP BY status";
                        $tasks_result = mysqli_query($conn, $tasks_sql);
                        $task_counts = ['pending' => 0, 'in_progress' => 0, 'completed' => 0];
                        
                        if ($tasks_result) {
                            while ($row = mysqli_fetch_assoc($tasks_result)) {
                                $task_counts[$row['status']] = $row['count'];
                            }
                        }
                        
                        $total_tasks = array_sum($task_counts);
                        $completed_percentage = $total_tasks > 0 ? round(($task_counts['completed'] / $total_tasks) * 100) : 0;
                    ?>
                        <div class="class-card">
                            <div class="class-header">
                                <h3 class="class-title"><?php echo htmlspecialchars($project['title']); ?></h3>
                                <p class="class-description"><?php echo htmlspecialchars(substr($project['description'], 0, 60)) . '...'; ?></p>
                            </div>
                            
                            <div class="class-body">
                                <div class="class-meta">
                                    <span class="class-role <?php echo $is_supervisor ? '' : 'participant'; ?>">
                                        <?php echo $is_supervisor ? 'Supervisor' : 'Participant'; ?>
                                    </span>
                                    <?php if($is_supervisor): ?>
                                        <span class="class-code"><?php echo $project['join_code']; ?></span>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="class-stats">
                                    <div class="stat-item">
                                        <span class="stat-number"><?php echo $total_tasks; ?></span>
                                        <span class="stat-label">Total Tasks</span>
                                    </div>
                                    <div class="stat-item">
                                        <span class="stat-number"><?php echo $completed_percentage; ?>%</span>
                                        <span class="stat-label">Completed</span>
                                    </div>
                                </div>
                                
                                <div class="class-actions">
                                    <a href="project_detail.php?id=<?php echo $project['id']; ?>" class="class-btn btn-primary">
                                        <i class="fas fa-eye"></i> View Project
                                    </a>
                                    <?php if($is_supervisor): ?>
                                        <a href="project_detail.php?id=<?php echo $project['id']; ?>&manage=true" class="class-btn btn-secondary">
                                            <i class="fas fa-cog"></i> Manage
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <!-- Add New Project Card -->
                    <div class="class-card" style="border: 2px dashed #cbd5e0; background: #f7fafc;">
                        <div class="class-body" style="text-align: center; padding: 40px 20px;">
                            <i class="fas fa-plus" style="font-size: 48px; color: #667eea; margin-bottom: 15px;"></i>
                            <h3 style="color: #4a5568; margin-bottom: 10px;">Create New Project</h3>
                            <p style="color: #718096; margin-bottom: 20px; font-size: 14px;">Start a new project and invite team members</p>
                            <a href="create_project.php" class="create-first-btn">
                                <i class="fas fa-plus"></i> Create Project
                            </a>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <!-- Empty State -->
                <div class="empty-state">
                    <i class="fas fa-tasks" style="font-size: 64px; color: #cbd5e0; margin-bottom: 20px;"></i>
                    <h3>No Projects Yet</h3>
                    <p>Create your first project or join an existing one to get started!</p>
                    <div style="display: flex; gap: 15px; justify-content: center;">
                        <a href="create_project.php" class="create-first-btn">
                            <i class="fas fa-plus"></i> Create Project
                        </a>
                        <a href="join_project.php" class="create-first-btn" style="background: #48bb78;">
                            <i class="fas fa-users"></i> Join Project
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    
    <script>
function toggleDropdown(dropdownId) {
    const dropdown = document.getElementById(dropdownId);
    const arrow = document.getElementById(dropdownId.replace('projects', 'arrow'));
    
    dropdown.classList.toggle('active');
    arrow.style.transform = dropdown.classList.contains('active') ? 'rotate(180deg)' : 'rotate(0deg)';
}

// Initialize dropdowns as open by default
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('created-projects').classList.add('active');
    document.getElementById('joined-projects').classList.add('active');
    
    // Add draggable functionality
    makeProjectsDraggable();
    
    // Setup join code copying
    setupJoinCodeCopying();
});

// Simple draggable functionality
function makeProjectsDraggable() {
    const grid = document.querySelector('.classroom-grid');
    const cards = document.querySelectorAll('.class-card');
    
    cards.forEach(card => {
        // Only make project cards draggable (not the "Create New Project" card)
        const isProjectCard = card.querySelector('.class-header') && 
                               card.querySelector('.class-title') && 
                               card.querySelector('.class-body');
        
        if (isProjectCard) {
            // Make card draggable
            card.draggable = true;
            card.style.cursor = 'move';
            card.style.userSelect = 'none';
            
            // Drag events
            card.addEventListener('dragstart', handleDragStart);
            card.addEventListener('dragover', handleDragOver);
            card.addEventListener('dragenter', handleDragEnter);
            card.addEventListener('dragleave', handleDragLeave);
            card.addEventListener('drop', handleDrop);
            card.addEventListener('dragend', handleDragEnd);
        } else {
            // This is the "Create New Project" card - make it non-draggable
            card.draggable = false;
            card.style.cursor = 'default';
        }
    });
}

// Setup click-to-copy for join codes
function setupJoinCodeCopying() {
    const joinCodes = document.querySelectorAll('.class-code');
    
    joinCodes.forEach(codeElement => {
        // Store original text
        const originalText = codeElement.textContent;
        
        // Add cursor pointer for visual indication
        codeElement.style.cursor = 'pointer';
        codeElement.title = 'Click to copy join code';
        
        // Add hover effect
        codeElement.addEventListener('mouseenter', function() {
            this.style.transform = 'scale(1.05)';
            this.style.boxShadow = '0 2px 8px rgba(0,0,0,0.2)';
        });
        
        codeElement.addEventListener('mouseleave', function() {
            this.style.transform = 'scale(1)';
            this.style.boxShadow = 'none';
        });
        
        // Add click event listener
        codeElement.addEventListener('click', function(e) {
            e.stopPropagation(); // Prevent card from being dragged
            e.preventDefault(); // Prevent any default behavior
            
            // Store original styles
            const originalBackground = this.style.background;
            const originalColor = this.style.color;
            
            // Copy text to clipboard
            navigator.clipboard.writeText(originalText).then(() => {
                // Show feedback
                this.textContent = 'Copied!';
                this.style.background = '#48bb78';
                this.style.color = 'white';
                
                // Reset after 1.5 seconds
                setTimeout(() => {
                    this.textContent = originalText;
                    this.style.background = originalBackground;
                    this.style.color = originalColor;
                }, 1500);
                
                // Show notification
                showNotification('Join code copied to clipboard!');
            }).catch(err => {
                console.error('Failed to copy: ', err);
                showNotification('Failed to copy code');
            });
        });
        
        // Prevent drag events on the join code element
        codeElement.addEventListener('dragstart', function(e) {
            e.stopPropagation(); // Prevent card drag from starting
            e.preventDefault(); // Prevent default drag behavior
        });
        
        // Allow text selection on join code
        codeElement.style.userSelect = 'text';
        codeElement.style.webkitUserSelect = 'text';
        codeElement.style.mozUserSelect = 'text';
        codeElement.style.msUserSelect = 'text';
    });
}

let draggedCard = null;

function handleDragStart(e) {
    // Don't start drag if clicking on join code
    if (e.target.classList.contains('class-code') || 
        e.target.closest('.class-code')) {
        e.preventDefault();
        return;
    }
    
    draggedCard = this;
    this.style.opacity = '0.4';
    e.dataTransfer.effectAllowed = 'move';
    e.dataTransfer.setData('text/html', this.innerHTML);
}

function handleDragOver(e) {
    if (e.preventDefault) {
        e.preventDefault(); // Necessary to allow dropping
    }
    e.dataTransfer.dropEffect = 'move';
    return false;
}

function handleDragEnter(e) {
    if (this !== draggedCard) {
        this.classList.add('over');
    }
}

function handleDragLeave(e) {
    this.classList.remove('over');
}

function handleDrop(e) {
    if (e.stopPropagation) {
        e.stopPropagation(); // stops the browser from redirecting
    }
    
    // Don't do anything if dropping the same card we're dragging
    if (draggedCard !== this) {
        // Get the grid
        const grid = document.querySelector('.classroom-grid');
        
        // Get all draggable cards only
        const cards = Array.from(document.querySelectorAll('.class-card[draggable="true"]'));
        
        // Get the index of dragged card and target card
        const draggedIndex = cards.indexOf(draggedCard);
        const targetIndex = cards.indexOf(this);
        
        if (draggedIndex < targetIndex) {
            // Insert after the target
            grid.insertBefore(draggedCard, this.nextSibling);
        } else {
            // Insert before the target
            grid.insertBefore(draggedCard, this);
        }
    }
    
    return false;
}

function handleDragEnd(e) {
    // Reset the opacity
    this.style.opacity = '1';
    
    // Remove over class from all cards
    document.querySelectorAll('.class-card').forEach(card => {
        card.classList.remove('over');
    });
    
    // Save the new order
    saveProjectOrder();
}

// Save project order to localStorage
function saveProjectOrder() {
    // Get only draggable cards (project cards)
    const cards = Array.from(document.querySelectorAll('.class-card[draggable="true"]'));
    const projectIds = cards.map(card => {
        // Try to get project ID from data attribute or extract from link
        const link = card.querySelector('a.btn-primary');
        if (link) {
            const url = new URL(link.href, window.location.origin);
            return url.searchParams.get('id');
        }
        return null;
    }).filter(id => id !== null);
    
    if (projectIds.length > 0) {
        localStorage.setItem('projectOrder', JSON.stringify(projectIds));
        showNotification('Project order saved!');
    }
}

// Show notification
function showNotification(message) {
    // Create notification element
    let notification = document.getElementById('drag-notification');
    if (!notification) {
        notification = document.createElement('div');
        notification.id = 'drag-notification';
        notification.style.cssText = `
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: #38a169;
            color: white;
            padding: 12px 24px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            z-index: 1000;
            display: none;
            align-items: center;
            gap: 10px;
        `;
        notification.innerHTML = `<i class="fas fa-check-circle"></i><span>${message}</span>`;
        document.body.appendChild(notification);
    }
    
    notification.querySelector('span').textContent = message;
    notification.style.display = 'flex';
    
    setTimeout(() => {
        notification.style.display = 'none';
    }, 2000);
}

// Add CSS for drag and drop and join code styling
const style = document.createElement('style');
style.textContent = `
    .class-card.over {
        border: 2px dashed #667eea;
        background: #f0f4ff;
    }
    
    .class-card.dragging {
        opacity: 0.5;
        transform: rotate(3deg);
    }
    
    /* Join code hover effects */
    .class-code {
        transition: all 0.2s ease;
        cursor: pointer !important;
    }
    
    /* Improve long text handling */
    .class-title {
        overflow: hidden;
        text-overflow: ellipsis;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        line-height: 1.3;
        max-height: 2.6em;
    }
    
    .class-description {
        overflow: hidden;
        text-overflow: ellipsis;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        line-height: 1.4;
        max-height: 2.8em;
    }
    
    /* Style for the "Create New Project" card */
    .class-card:not([draggable="true"]) {
        cursor: default !important;
    }
`;
document.head.appendChild(style);

// Load saved order on page load
window.addEventListener('load', function() {
    const savedOrder = localStorage.getItem('projectOrder');
    if (savedOrder) {
        try {
            const order = JSON.parse(savedOrder);
            const grid = document.querySelector('.classroom-grid');
            
            // Get only draggable cards (project cards)
            const projectCards = Array.from(document.querySelectorAll('.class-card[draggable="true"]'));
            
            // Sort based on saved order
            projectCards.sort((a, b) => {
                const linkA = a.querySelector('a.btn-primary');
                const linkB = b.querySelector('a.btn-primary');
                const idA = new URL(linkA.href, window.location.origin).searchParams.get('id');
                const idB = new URL(linkB.href, window.location.origin).searchParams.get('id');
                
                const indexA = order.indexOf(idA);
                const indexB = order.indexOf(idB);
                
                return indexA - indexB;
            });
            
            // Get the "Create New Project" card (non-draggable)
            const createCard = document.querySelector('.class-card:not([draggable="true"])');
            
            // Clear the grid first
            while (grid.firstChild) {
                grid.removeChild(grid.firstChild);
            }
            
            // Add sorted project cards
            projectCards.forEach(card => {
                grid.appendChild(card);
            });
            
            // Add create card at the end
            if (createCard) {
                grid.appendChild(createCard);
            }
        } catch (e) {
            console.error('Error loading saved order:', e);
        }
    }
});
</script>
</body>
</html>