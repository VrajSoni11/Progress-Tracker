<?php
require_once 'config.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$user_id = getCurrentUserId();
$action = $_REQUEST['action'] ?? '';

// Get day tasks
if ($action === 'get_day_tasks') {
    $date = $_GET['date'] ?? date('Y-m-d');
    
    // Get all categories
    $categories_query = "SELECT * FROM categories WHERE user_id = ? ORDER BY category_name";
    $stmt = $conn->prepare($categories_query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $categories_result = $stmt->get_result();
    $categories = [];
    while ($row = $categories_result->fetch_assoc()) {
        $categories[] = $row;
    }
    
    // Get tasks for the date
    $tasks_query = "SELECT t.*, c.category_name, c.category_icon, c.category_color 
                    FROM tasks t 
                    JOIN categories c ON t.category_id = c.id 
                    WHERE t.user_id = ? AND t.task_date = ? 
                    ORDER BY c.category_name, t.created_at";
    $stmt = $conn->prepare($tasks_query);
    $stmt->bind_param("is", $user_id, $date);
    $stmt->execute();
    $tasks_result = $stmt->get_result();
    $tasks = [];
    while ($row = $tasks_result->fetch_assoc()) {
        $tasks[] = $row;
    }
    
    echo json_encode([
        'success' => true,
        'categories' => $categories,
        'tasks' => $tasks
    ]);
    exit;
}

// Add task
if ($action === 'add_task') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category_id = intval($_POST['category_id'] ?? 0);
    $date = $_POST['date'] ?? date('Y-m-d');
    
    if (empty($title) || empty($category_id)) {
        echo json_encode(['success' => false, 'message' => 'Title and category are required']);
        exit;
    }
    
    // Verify category belongs to user
    $check_query = "SELECT id FROM categories WHERE id = ? AND user_id = ?";
    $stmt = $conn->prepare($check_query);
    $stmt->bind_param("ii", $category_id, $user_id);
    $stmt->execute();
    if ($stmt->get_result()->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid category']);
        exit;
    }
    
    // Insert task
    $insert_query = "INSERT INTO tasks (user_id, category_id, task_title, task_description, task_date) VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($insert_query);
    $stmt->bind_param("iisss", $user_id, $category_id, $title, $description, $date);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Task added successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to add task']);
    }
    exit;
}

// Toggle task completion
if ($action === 'toggle_task') {
    $task_id = intval($_POST['task_id'] ?? 0);
    
    // Get current status
    $query = "SELECT is_completed FROM tasks WHERE id = ? AND user_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $task_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Task not found']);
        exit;
    }
    
    $task = $result->fetch_assoc();
    $new_status = !$task['is_completed'];
    $completed_at = $new_status ? date('Y-m-d H:i:s') : null;
    
    // Update task
    $update_query = "UPDATE tasks SET is_completed = ?, completed_at = ? WHERE id = ? AND user_id = ?";
    $stmt = $conn->prepare($update_query);
    $stmt->bind_param("isii", $new_status, $completed_at, $task_id, $user_id);
    
    if ($stmt->execute()) {
        // Award XP and check badges when completing task
        if ($new_status && file_exists('update_streak.php')) {
            require_once 'update_streak.php';
            
            // Check if gamification table exists
            $table_check = $conn->query("SHOW TABLES LIKE 'user_gamification'");
            if ($table_check && $table_check->num_rows > 0) {
                // Award 10 XP per task
                $conn->query("UPDATE user_gamification SET total_xp = total_xp + 10, total_tasks_completed = total_tasks_completed + 1 WHERE user_id = $user_id");
                
                // Get total tasks completed
                $total_result = $conn->query("SELECT total_tasks_completed FROM user_gamification WHERE user_id = $user_id");
                if ($total_result && $row = $total_result->fetch_assoc()) {
                    $total_tasks = $row['total_tasks_completed'];
                    
                    // Check task count badges
                    $task_badges = [1 => 1, 10 => 2, 50 => 3, 100 => 4, 500 => 5];
                    foreach ($task_badges as $count => $badge_id) {
                        if ($total_tasks >= $count) unlockBadge($user_id, $badge_id);
                    }
                }
            }
        }
        
        echo json_encode(['success' => true, 'message' => 'Task updated']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update task']);
    }
    exit;
}

// Delete task
if ($action === 'delete_task') {
    $task_id = intval($_POST['task_id'] ?? 0);
    
    $delete_query = "DELETE FROM tasks WHERE id = ? AND user_id = ?";
    $stmt = $conn->prepare($delete_query);
    $stmt->bind_param("ii", $task_id, $user_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Task deleted']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete task']);
    }
    exit;
}

// Get monthly progress
if ($action === 'get_monthly_progress') {
    $year = intval($_GET['year'] ?? date('Y'));
    $month = intval($_GET['month'] ?? date('m'));
    
    $first_day = sprintf('%04d-%02d-01', $year, $month);
    $last_day = date('Y-m-t', strtotime($first_day));
    
    $query = "SELECT 
        task_date,
        COUNT(*) as total,
        SUM(CASE WHEN is_completed = 1 THEN 1 ELSE 0 END) as completed
        FROM tasks 
        WHERE user_id = ? AND task_date BETWEEN ? AND ?
        GROUP BY task_date";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iss", $user_id, $first_day, $last_day);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $days = [];
    while ($row = $result->fetch_assoc()) {
        $days[$row['task_date']] = [
            'total' => (int)$row['total'],
            'completed' => (int)$row['completed']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'days' => $days
    ]);
    exit;
}

// Add bulk tasks to multiple days
if ($action === 'add_bulk_tasks') {
    $task_title = trim($_POST['task_title'] ?? '');
    $task_description = trim($_POST['task_description'] ?? '');
    $category_id = intval($_POST['category_id'] ?? 0);
    $task_dates = json_decode($_POST['task_dates'] ?? '[]', true);
    
    if (empty($task_title) || $category_id === 0 || empty($task_dates)) {
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        exit;
    }
    
    // Verify category belongs to user
    $cat_check = $conn->prepare("SELECT id FROM categories WHERE id = ? AND user_id = ?");
    $cat_check->bind_param("ii", $category_id, $user_id);
    $cat_check->execute();
    if ($cat_check->get_result()->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid category']);
        exit;
    }
    
    // Insert task for each selected date
    $success_count = 0;
    $insert_stmt = $conn->prepare("INSERT INTO tasks (user_id, category_id, task_title, task_description, task_date) VALUES (?, ?, ?, ?, ?)");
    
    foreach ($task_dates as $date) {
        $insert_stmt->bind_param("iisss", $user_id, $category_id, $task_title, $task_description, $date);
        if ($insert_stmt->execute()) {
            $success_count++;
        }
    }
    
    echo json_encode([
        'success' => true,
        'count' => $success_count,
        'message' => "Task added to $success_count day(s)"
    ]);
    exit;
}

// Get dashboard stats (for updating without page reload)
if ($action === 'get_dashboard_stats') {
    $today = date('Y-m-d');
    $week_start = date('Y-m-d', strtotime('monday this week'));
    $week_end = date('Y-m-d', strtotime('sunday this week'));
    $month_start = date('Y-m-01');
    $month_end = date('Y-m-t');
    
    // Today's stats
    $today_query = "SELECT COUNT(*) as total, SUM(CASE WHEN is_completed = 1 THEN 1 ELSE 0 END) as completed FROM tasks WHERE user_id = ? AND task_date = ?";
    $stmt = $conn->prepare($today_query);
    $stmt->bind_param("is", $user_id, $today);
    $stmt->execute();
    $today_stats = $stmt->get_result()->fetch_assoc();
    $today_progress = $today_stats['total'] > 0 ? round(($today_stats['completed'] / $today_stats['total']) * 100) : 0;
    
    // Week's stats
    $week_query = "SELECT COUNT(*) as total, SUM(CASE WHEN is_completed = 1 THEN 1 ELSE 0 END) as completed FROM tasks WHERE user_id = ? AND task_date BETWEEN ? AND ?";
    $stmt = $conn->prepare($week_query);
    $stmt->bind_param("iss", $user_id, $week_start, $week_end);
    $stmt->execute();
    $week_stats = $stmt->get_result()->fetch_assoc();
    $week_progress = $week_stats['total'] > 0 ? round(($week_stats['completed'] / $week_stats['total']) * 100) : 0;
    
    // Month's stats
    $month_query = "SELECT COUNT(*) as total, SUM(CASE WHEN is_completed = 1 THEN 1 ELSE 0 END) as completed FROM tasks WHERE user_id = ? AND task_date BETWEEN ? AND ?";
    $stmt = $conn->prepare($month_query);
    $stmt->bind_param("iss", $user_id, $month_start, $month_end);
    $stmt->execute();
    $month_stats = $stmt->get_result()->fetch_assoc();
    $month_progress = $month_stats['total'] > 0 ? round(($month_stats['completed'] / $month_stats['total']) * 100) : 0;
    
    echo json_encode([
        'success' => true,
        'today_progress' => $today_progress,
        'today_completed' => $today_stats['completed'],
        'today_total' => $today_stats['total'],
        'week_progress' => $week_progress,
        'week_completed' => $week_stats['completed'],
        'week_total' => $week_stats['total'],
        'month_progress' => $month_progress,
        'month_completed' => $month_stats['completed'],
        'month_total' => $month_stats['total']
    ]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action']);
?>
