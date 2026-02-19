<?php
require_once 'config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('index.php');
}

$user_id = getCurrentUserId();
$username = getCurrentUsername();
$full_name = $_SESSION['full_name'];

// Update streak
if (file_exists('update_streak.php')) {
    require_once 'update_streak.php';
    $current_streak = updateStreak($user_id);
} else {
    $current_streak = 0;
}

// Get current week start (Monday)
$current_week_start = isset($_GET['week']) ? $_GET['week'] : date('Y-m-d', strtotime('monday this week'));
$week_start = new DateTime($current_week_start);

// Calculate week dates
$week_dates = [];
for ($i = 0; $i < 7; $i++) {
    $date = clone $week_start;
    $date->modify("+$i days");
    $week_dates[] = $date;
}

// Get all categories for current user
$categories_query = "SELECT * FROM categories WHERE user_id = ? ORDER BY category_name";
$stmt = $conn->prepare($categories_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$categories_result = $stmt->get_result();
$categories = [];
while ($row = $categories_result->fetch_assoc()) {
    $categories[] = $row;
}

// Get all tasks for the week
$week_end = clone $week_start;
$week_end->modify('+6 days');
$week_start_str = $week_start->format('Y-m-d');
$week_end_str = $week_end->format('Y-m-d');

$tasks_query = "SELECT t.*, c.category_name, c.category_icon, c.category_color 
                FROM tasks t 
                JOIN categories c ON t.category_id = c.id 
                WHERE t.user_id = ? AND t.task_date BETWEEN ? AND ? 
                ORDER BY t.task_date, c.category_name";
$stmt = $conn->prepare($tasks_query);
$stmt->bind_param("iss", $user_id, $week_start_str, $week_end_str);
$stmt->execute();
$tasks_result = $stmt->get_result();
$tasks = [];
while ($row = $tasks_result->fetch_assoc()) {
    $tasks[$row['task_date']][] = $row;
}

// Calculate progress stats
$today = date('Y-m-d');

// Today's progress
$today_query = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN is_completed = 1 THEN 1 ELSE 0 END) as completed
    FROM tasks 
    WHERE user_id = ? AND task_date = ?";
$stmt = $conn->prepare($today_query);
$stmt->bind_param("is", $user_id, $today);
$stmt->execute();
$today_stats = $stmt->get_result()->fetch_assoc();
$today_progress = $today_stats['total'] > 0 ? round(($today_stats['completed'] / $today_stats['total']) * 100) : 0;

// Week's progress
$week_query = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN is_completed = 1 THEN 1 ELSE 0 END) as completed
    FROM tasks 
    WHERE user_id = ? AND task_date BETWEEN ? AND ?";
$stmt = $conn->prepare($week_query);
$stmt->bind_param("iss", $user_id, $week_start_str, $week_end_str);
$stmt->execute();
$week_stats = $stmt->get_result()->fetch_assoc();
$week_progress = $week_stats['total'] > 0 ? round(($week_stats['completed'] / $week_stats['total']) * 100) : 0;

// Month's progress
$month_start = date('Y-m-01');
$month_end = date('Y-m-t');
$month_query = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN is_completed = 1 THEN 1 ELSE 0 END) as completed
    FROM tasks 
    WHERE user_id = ? AND task_date BETWEEN ? AND ?";
$stmt = $conn->prepare($month_query);
$stmt->bind_param("iss", $user_id, $month_start, $month_end);
$stmt->execute();
$month_stats = $stmt->get_result()->fetch_assoc();
$month_progress = $month_stats['total'] > 0 ? round(($month_stats['completed'] / $month_stats['total']) * 100) : 0;

// Find today's index in week
$today_index = 0;
foreach ($week_dates as $index => $date) {
    if ($date->format('Y-m-d') === $today) {
        $today_index = $index;
        break;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Progress Tracker</title>
    <link rel="stylesheet" href="dashboard.css">
</head>
<body>
    <!-- Top Navigation -->
    <nav class="navbar">
        <div class="nav-container">
            <div class="nav-brand" style="display: flex; align-items: center; gap: 0;">
            <img src="logo.png" alt="ProgoV" style="height: 80px; width: auto; margin-right: 12px; filter: drop-shadow(0 2px 4px rgba(0,0,0,0.1)); transition: transform 0.3s ease;">
            <h2 style="background: var(--primary-gradient); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; font-weight: 800; letter-spacing: -0.5px; margin: 0; font-size: 1.5rem;">ProgoV</h2>
        </div>
            <div class="nav-user">
                <?php if ($current_streak > 0): ?>
                    <span style="background:linear-gradient(135deg,#ff6b6b,#ff8a8a);color:white;padding:0.5rem 1rem;border-radius:var(--radius-md);font-weight:700;display:flex;align-items:center;gap:0.375rem">
                        🔥 <?php echo $current_streak; ?> Day Streak
                    </span>
                <?php endif; ?>
                <span>Welcome, <strong><?php echo htmlspecialchars($full_name); ?></strong></span>
                <button onclick="window.location.href='ai_insights.php'" class="btn-nav" style="background:linear-gradient(135deg,#667eea,#764ba2);color:white;font-weight:700">
                    🤖 AI Insights
                </button>
                <button onclick="window.location.href='gamification.php'" class="btn-nav">🏆 Achievements</button>
                <button onclick="window.location.href='categories.php'" class="btn-nav">📂 Categories</button>
                <button onclick="window.location.href='analytics.php'" class="btn-nav">📊 Analytics</button>
                <button onclick="window.location.href='logout.php'" class="btn-logout">Logout</button>
            </div>
        </div>
    </nav>

    <div class="container">
        <!-- Progress Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <svg class="circular-progress" viewBox="0 0 80 80">
                        <circle class="progress-ring-bg" cx="40" cy="40" r="35"></circle>
                        <circle class="progress-ring-fill" cx="40" cy="40" r="35" 
                                style="stroke-dashoffset: calc(220 - (220 * <?php echo $today_progress; ?>) / 100);"></circle>
                    </svg>
                    <div class="stat-icon-inner">📅</div>
                </div>
                <div class="stat-content">
                    <p class="stat-label">Today's Progress</p>
                    <h3 class="stat-value"><?php echo $today_progress; ?>%</h3>
                    <small><?php echo $today_stats['completed']; ?>/<?php echo $today_stats['total']; ?> tasks</small>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <svg class="circular-progress" viewBox="0 0 80 80">
                        <circle class="progress-ring-bg" cx="40" cy="40" r="35"></circle>
                        <circle class="progress-ring-fill" cx="40" cy="40" r="35" 
                                style="stroke-dashoffset: calc(220 - (220 * <?php echo $week_progress; ?>) / 100);"></circle>
                    </svg>
                    <div class="stat-icon-inner">📊</div>
                </div>
                <div class="stat-content">
                    <p class="stat-label">This Week</p>
                    <h3 class="stat-value"><?php echo $week_progress; ?>%</h3>
                    <small><?php echo $week_stats['completed']; ?>/<?php echo $week_stats['total']; ?> tasks</small>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <svg class="circular-progress" viewBox="0 0 80 80">
                        <circle class="progress-ring-bg" cx="40" cy="40" r="35"></circle>
                        <circle class="progress-ring-fill" cx="40" cy="40" r="35" 
                                style="stroke-dashoffset: calc(220 - (220 * <?php echo $month_progress; ?>) / 100);"></circle>
                    </svg>
                    <div class="stat-icon-inner">🏆</div>
                </div>
                <div class="stat-content">
                    <p class="stat-label">This Month</p>
                    <h3 class="stat-value"><?php echo $month_progress; ?>%</h3>
                    <small><?php echo $month_stats['completed']; ?>/<?php echo $month_stats['total']; ?> tasks</small>
                </div>
            </div>
        </div>

        <!-- Monthly Dot Calendar -->
        <div class="monthly-calendar-container">
            <div class="monthly-calendar-header">
                <h3 class="monthly-calendar-title">
                    📅 <?php echo date('F Y'); ?> Progress Tracker
                </h3>
                <div class="monthly-calendar-stats">
                    <span class="completed" id="monthCompletedDays">0</span> / 
                    <span id="monthTotalDays">0</span> days active
                </div>
            </div>
            
            <div class="monthly-calendar-wrapper">
                <div class="calendar-weekdays">
                    <div class="calendar-day-label">Sun</div>
                    <div class="calendar-day-label">Mon</div>
                    <div class="calendar-day-label">Tue</div>
                    <div class="calendar-day-label">Wed</div>
                    <div class="calendar-day-label">Thu</div>
                    <div class="calendar-day-label">Fri</div>
                    <div class="calendar-day-label">Sat</div>
                </div>
                <div class="monthly-calendar-grid" id="monthlyCalendarDays"></div>
            </div>
            
            <div class="calendar-legend">
                <div class="legend-item">
                    <div class="legend-dot completed"></div>
                    <span>All tasks completed</span>
                </div>
                <div class="legend-item">
                    <div class="legend-dot partial"></div>
                    <span>Partially completed</span>
                </div>
                <div class="legend-item">
                    <div class="legend-dot pending"></div>
                    <span>No tasks / Pending</span>
                </div>
            </div>
        </div>

        <!-- Week Navigation -->
        <div class="week-nav">
            <?php
            $prev_week = clone $week_start;
            $prev_week->modify('-7 days');
            $next_week = clone $week_start;
            $next_week->modify('+7 days');
            ?>
            <button onclick="window.location.href='?week=<?php echo $prev_week->format('Y-m-d'); ?>'" class="btn-week-nav">
                ← Previous Week
            </button>
            <h2 class="week-title">
                Week: <?php echo $week_start->format('M d'); ?> - <?php echo $week_end->format('M d, Y'); ?>
            </h2>
            <button onclick="openBulkTaskModal()" class="btn-week-nav" style="background: linear-gradient(135deg, #4ade80 0%, #22c55e 100%);">
                ➕ Add Task to Multiple Days
            </button>
            <button onclick="window.location.href='?week=<?php echo $next_week->format('Y-m-d'); ?>'" class="btn-week-nav">
                Next Week →
            </button>
        </div>

        <!-- 3D Rotating Carousel -->
        <div class="calendar-container-wrapper">
            <div class="carousel-nav prev">
                <button class="carousel-nav-btn" onclick="previousDay()">←</button>
            </div>
            
            <div class="calendar-carousel">
                <div class="calendar-grid">
                    <?php foreach ($week_dates as $index => $date): ?>
                        <?php
                        $date_str = $date->format('Y-m-d');
                        $is_today = ($date_str === $today);
                        $day_tasks = $tasks[$date_str] ?? [];
                        $completed_count = count(array_filter($day_tasks, function($t) { return $t['is_completed']; }));
                        $total_count = count($day_tasks);
                        $day_progress = $total_count > 0 ? round(($completed_count / $total_count) * 100) : 0;
                        ?>
                        <div class="day-card" data-card-index="<?php echo $index; ?>" 
                             onclick="openDayModal('<?php echo $date_str; ?>', '<?php echo $date->format('l, M d, Y'); ?>')">
                            <div class="day-header">
                                <div class="day-name"><?php echo $date->format('l'); ?></div>
                                <div class="day-date"><?php echo $date->format('M d'); ?></div>
                            </div>
                            
                            <?php if ($is_today): ?>
                                <div class="today-badge">TODAY</div>
                            <?php endif; ?>
                            
                            <div class="day-progress">
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: <?php echo $day_progress; ?>%"></div>
                                </div>
                                <small><?php echo $completed_count; ?>/<?php echo $total_count; ?> tasks</small>
                            </div>
                            
                            <div class="day-tasks-preview">
                                <?php if (empty($day_tasks)): ?>
                                    <p class="no-tasks">No tasks planned</p>
                                <?php else: ?>
                                    <?php foreach (array_slice($day_tasks, 0, 3) as $task): ?>
                                        <div class="task-preview <?php echo $task['is_completed'] ? 'completed' : ''; ?>">
                                            <span class="task-icon"><?php echo $task['category_icon']; ?></span>
                                            <span class="task-title"><?php echo htmlspecialchars($task['task_title']); ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                    <?php if (count($day_tasks) > 3): ?>
                                        <small class="more-tasks">+<?php echo count($day_tasks) - 3; ?> more</small>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div class="carousel-nav next">
                <button class="carousel-nav-btn" onclick="nextDay()">→</button>
            </div>
            
            <div class="carousel-dots" id="carouselDots"></div>
        </div>
    </div>

    <!-- Day Detail Modal -->
    <div id="dayModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">Day Details</h2>
                <span class="modal-close" onclick="closeDayModal()">&times;</span>
            </div>
            <div class="modal-body">
                <div id="modalLoading" class="loading">Loading...</div>
                <div id="modalTasksContent" style="display:none;"></div>
            </div>
        </div>
    </div>

    <!-- Bulk Task Add Modal -->
    <div id="bulkTaskModal" class="modal">
        <div class="modal-content" style="max-width: 700px;">
            <div class="modal-header" style="background: linear-gradient(135deg, #4ade80 0%, #22c55e 100%);">
                <h2>➕ Add Task to Multiple Days</h2>
                <span class="modal-close" onclick="closeBulkTaskModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form id="bulkTaskForm" onsubmit="submitBulkTask(event)">
                    <!-- Task Details -->
                    <div style="background: var(--bg-ice); padding: var(--spacing-lg); border-radius: var(--radius-lg); margin-bottom: var(--spacing-lg);">
                        <h3 style="color: var(--text-primary); margin-bottom: var(--spacing-md); font-size: 1rem;">📝 Task Details</h3>
                        
                        <div style="margin-bottom: var(--spacing-md);">
                            <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: var(--text-primary);">Task Title *</label>
                            <input type="text" id="bulkTaskTitle" required 
                                   placeholder="e.g., Morning Exercise"
                                   style="width: 100%; padding: 12px; border: 2px solid var(--border-light); border-radius: var(--radius-md); font-size: 1rem;">
                        </div>
                        
                        <div style="margin-bottom: var(--spacing-md);">
                            <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: var(--text-primary);">Description (optional)</label>
                            <textarea id="bulkTaskDescription" rows="3" 
                                      placeholder="Add any notes or details..."
                                      style="width: 100%; padding: 12px; border: 2px solid var(--border-light); border-radius: var(--radius-md); font-size: 0.9375rem; resize: vertical;"></textarea>
                        </div>
                        
                        <div>
                            <label style="display: block; margin-bottom: 0.5rem; font-weight: 600; color: var(--text-primary);">Category *</label>
                            <select id="bulkTaskCategory" required 
                                    style="width: 100%; padding: 12px; border: 2px solid var(--border-light); border-radius: var(--radius-md); font-size: 1rem;">
                                <option value="">Select a category</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>">
                                        <?php echo $cat['category_icon'] . ' ' . $cat['category_name']; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Day Selection -->
                    <div style="background: var(--bg-tertiary); padding: var(--spacing-lg); border-radius: var(--radius-lg); margin-bottom: var(--spacing-lg);">
                        <h3 style="color: var(--text-primary); margin-bottom: var(--spacing-md); font-size: 1rem;">📅 Select Days</h3>
                        <p style="color: var(--text-secondary); font-size: 0.875rem; margin-bottom: var(--spacing-md);">
                            Choose which days you want to add this task to:
                        </p>
                        
                        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 0.75rem;">
                            <?php foreach ($week_dates as $index => $date): ?>
                                <?php
                                $date_str = $date->format('Y-m-d');
                                $is_today = ($date_str === $today);
                                ?>
                                <label class="bulk-day-checkbox" style="display: flex; align-items: center; gap: 0.75rem; padding: 12px; background: white; border: 2px solid var(--border-light); border-radius: var(--radius-md); cursor: pointer; transition: all 0.2s;">
                                    <input type="checkbox" name="bulk_days[]" value="<?php echo $date_str; ?>" 
                                           style="width: 20px; height: 20px; cursor: pointer;">
                                    <div style="flex: 1;">
                                        <div style="font-weight: 700; color: var(--text-primary); font-size: 0.875rem;">
                                            <?php echo $date->format('l'); ?>
                                            <?php if ($is_today): ?>
                                                <span style="background: var(--glacier); color: white; padding: 0.125rem 0.5rem; border-radius: 12px; font-size: 0.625rem; margin-left: 0.25rem;">TODAY</span>
                                            <?php endif; ?>
                                        </div>
                                        <div style="color: var(--text-secondary); font-size: 0.75rem;">
                                            <?php echo $date->format('M d, Y'); ?>
                                        </div>
                                    </div>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        
                        <div style="display: flex; gap: 0.5rem; margin-top: var(--spacing-md);">
                            <button type="button" onclick="selectAllDays()" 
                                    style="padding: 8px 16px; background: var(--bg-ice); border: 1px solid var(--border-medium); border-radius: var(--radius-md); cursor: pointer; font-size: 0.875rem; font-weight: 600; color: var(--glacier);">
                                ✓ Select All
                            </button>
                            <button type="button" onclick="selectWeekdays()" 
                                    style="padding: 8px 16px; background: var(--bg-ice); border: 1px solid var(--border-medium); border-radius: var(--radius-md); cursor: pointer; font-size: 0.875rem; font-weight: 600; color: var(--glacier);">
                                📅 Weekdays Only
                            </button>
                            <button type="button" onclick="selectWeekend()" 
                                    style="padding: 8px 16px; background: var(--bg-ice); border: 1px solid var(--border-medium); border-radius: var(--radius-md); cursor: pointer; font-size: 0.875rem; font-weight: 600; color: var(--glacier);">
                                🎉 Weekend Only
                            </button>
                            <button type="button" onclick="clearAllDays()" 
                                    style="padding: 8px 16px; background: white; border: 1px solid var(--border-medium); border-radius: var(--radius-md); cursor: pointer; font-size: 0.875rem; font-weight: 600; color: var(--text-tertiary);">
                                ✗ Clear All
                            </button>
                        </div>
                    </div>
                    
                    <!-- Submit Button -->
                    <button type="submit" 
                            style="width: 100%; padding: 16px; background: linear-gradient(135deg, #4ade80 0%, #22c55e 100%); color: white; border: none; border-radius: var(--radius-md); font-size: 1.125rem; font-weight: 700; cursor: pointer; box-shadow: 0 4px 12px rgba(34, 197, 94, 0.3);">
                        ➕ Add Task to Selected Days
                    </button>
                </form>
                
                <div id="bulkTaskResult" style="margin-top: var(--spacing-md); display: none;"></div>
            </div>
        </div>
    </div>

    <!-- Dashboard JS -->
    <script src="dashboard.js"></script>
    
    <!-- Monthly Calendar Script -->
    <script>
        function populateMonthlyCalendar() {
            const container = document.getElementById('monthlyCalendarDays');
            const currentDate = new Date();
            const currentYear = currentDate.getFullYear();
            const currentMonth = currentDate.getMonth();
            const today = currentDate.getDate();
            
            const firstDay = new Date(currentYear, currentMonth, 1).getDay();
            const daysInMonth = new Date(currentYear, currentMonth + 1, 0).getDate();
            
            fetch(`api_tasks.php?action=get_monthly_progress&year=${currentYear}&month=${currentMonth + 1}`)
                .then(response => response.json())
                .then(data => {
                    let html = '';
                    let completedDays = 0;
                    let activeDays = 0;
                    
                    for (let i = 0; i < firstDay; i++) {
                        html += '<div class="calendar-day-dot empty"></div>';
                    }
                    
                    for (let day = 1; day <= daysInMonth; day++) {
                        const dateStr = `${currentYear}-${String(currentMonth + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
                        const dayData = data.days[dateStr] || { total: 0, completed: 0 };
                        
                        let classes = 'calendar-day-dot';
                        let tooltip = '';
                        
                        if (day === today) classes += ' today';
                        
                        if (day > today) {
                            classes += ' future';
                            tooltip = 'Future';
                        } else if (dayData.total === 0) {
                            classes += ' pending';
                            tooltip = 'No tasks';
                        } else if (dayData.completed === dayData.total) {
                            classes += ' completed';
                            tooltip = `${dayData.completed}/${dayData.total} completed!`;
                            completedDays++;
                            activeDays++;
                        } else if (dayData.completed > 0) {
                            classes += ' partial';
                            tooltip = `${dayData.completed}/${dayData.total} completed`;
                            activeDays++;
                        } else {
                            tooltip = `0/${dayData.total} completed`;
                            activeDays++;
                        }
                        
                        html += `<div class="${classes}" data-tooltip="${tooltip}">${day}</div>`;
                    }
                    
                    container.innerHTML = html;
                    document.getElementById('monthCompletedDays').textContent = completedDays;
                    document.getElementById('monthTotalDays').textContent = activeDays;
                })
                .catch(error => console.error('Error loading monthly calendar:', error));
        }
        
        document.addEventListener('DOMContentLoaded', populateMonthlyCalendar);
    </script>
    
    <!-- 3D Carousel Script -->
    <script>
        let currentDayIndex = <?php echo $today_index; ?>;
        const totalDays = 7;

        function initializeCarousel() {
            const cards = document.querySelectorAll('.day-card');
            cards.forEach((card, index) => {
                card.addEventListener('click', function(e) {
                    const position = parseInt(card.getAttribute('data-position'));
                    if (position !== 0) {
                        e.stopPropagation();
                        rotateToIndex(index);
                    }
                });
            });
        }

        function updateCarouselPositions() {
            const cards = document.querySelectorAll('.day-card');
            cards.forEach((card, cardIndex) => {
                let position = cardIndex - currentDayIndex;
                if (position > 3) position -= totalDays;
                if (position < -3) position += totalDays;
                card.setAttribute('data-position', position);
                card.style.zIndex = 10 - Math.abs(position);
            });
        }

        function nextDay() {
            currentDayIndex = (currentDayIndex + 1) % totalDays;
            updateCarouselPositions();
            updateDots();
        }

        function previousDay() {
            currentDayIndex = (currentDayIndex - 1 + totalDays) % totalDays;
            updateCarouselPositions();
            updateDots();
        }

        function rotateToIndex(index) {
            currentDayIndex = index;
            updateCarouselPositions();
            updateDots();
        }

        function createDots() {
            const dotsContainer = document.getElementById('carouselDots');
            if (!dotsContainer) return;
            dotsContainer.innerHTML = '';
            const cards = document.querySelectorAll('.day-card');
            cards.forEach((card, index) => {
                const dot = document.createElement('div');
                dot.className = 'carousel-dot';
                dot.addEventListener('click', () => rotateToIndex(index));
                dotsContainer.appendChild(dot);
            });
            updateDots();
        }

        function updateDots() {
            const dots = document.querySelectorAll('.carousel-dot');
            dots.forEach((dot, index) => {
                dot.classList.toggle('active', index === currentDayIndex);
            });
        }

        function handleKeyboard(e) {
            if (e.key === 'ArrowRight') { e.preventDefault(); nextDay(); }
            else if (e.key === 'ArrowLeft') { e.preventDefault(); previousDay(); }
        }

        let touchStartX = 0;
        const carousel = document.querySelector('.calendar-carousel');
        if (carousel) {
            carousel.addEventListener('touchstart', (e) => {
                touchStartX = e.changedTouches[0].screenX;
            }, { passive: true });
            
            carousel.addEventListener('touchend', (e) => {
                const diff = touchStartX - e.changedTouches[0].screenX;
                if (Math.abs(diff) > 50) {
                    diff > 0 ? nextDay() : previousDay();
                }
            }, { passive: true });
        }

        document.addEventListener('DOMContentLoaded', function() {
            initializeCarousel();
            updateCarouselPositions();
            createDots();
            document.addEventListener('keydown', handleKeyboard);
        });
    </script>
    
    <!-- Bulk Task Modal Functions -->
    <script>
        function openBulkTaskModal() {
            document.getElementById('bulkTaskModal').style.display = 'block';
            document.getElementById('bulkTaskForm').reset();
            document.getElementById('bulkTaskResult').style.display = 'none';
        }
        
        function closeBulkTaskModal() {
            document.getElementById('bulkTaskModal').style.display = 'none';
        }
        
        function selectAllDays() {
            document.querySelectorAll('input[name="bulk_days[]"]').forEach(cb => cb.checked = true);
        }
        
        function selectWeekdays() {
            const checkboxes = document.querySelectorAll('input[name="bulk_days[]"]');
            checkboxes.forEach((cb, index) => {
                // Monday to Friday (indices 1-5 in week starting Monday)
                cb.checked = (index >= 0 && index <= 4);
            });
        }
        
        function selectWeekend() {
            const checkboxes = document.querySelectorAll('input[name="bulk_days[]"]');
            checkboxes.forEach((cb, index) => {
                // Saturday and Sunday (indices 5-6 in week starting Monday)
                cb.checked = (index === 5 || index === 6);
            });
        }
        
        function clearAllDays() {
            document.querySelectorAll('input[name="bulk_days[]"]').forEach(cb => cb.checked = false);
        }
        
        function submitBulkTask(event) {
            event.preventDefault();
            
            const title = document.getElementById('bulkTaskTitle').value;
            const description = document.getElementById('bulkTaskDescription').value;
            const category = document.getElementById('bulkTaskCategory').value;
            const selectedDays = Array.from(document.querySelectorAll('input[name="bulk_days[]"]:checked'))
                                      .map(cb => cb.value);
            
            if (selectedDays.length === 0) {
                showBulkResult('Please select at least one day!', 'error');
                return;
            }
            
            // Show loading
            const submitBtn = event.target.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '⏳ Adding tasks...';
            submitBtn.disabled = true;
            
            // Send request
            fetch('api_tasks.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'add_bulk_tasks',
                    task_title: title,
                    task_description: description,
                    category_id: category,
                    task_dates: JSON.stringify(selectedDays)
                })
            })
            .then(response => response.json())
            .then(data => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
                
                if (data.success) {
                    showBulkResult(`✅ Success! Task added to ${data.count} day(s)!`, 'success');
                    setTimeout(() => {
                        closeBulkTaskModal();
                        location.reload(); // Refresh to show new tasks
                    }, 1500);
                } else {
                    showBulkResult('❌ Error: ' + data.message, 'error');
                }
            })
            .catch(error => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
                showBulkResult('❌ Error adding tasks. Please try again.', 'error');
                console.error('Error:', error);
            });
        }
        
        function showBulkResult(message, type) {
            const resultDiv = document.getElementById('bulkTaskResult');
            resultDiv.style.display = 'block';
            resultDiv.innerHTML = message;
            resultDiv.style.padding = '12px 16px';
            resultDiv.style.borderRadius = 'var(--radius-md)';
            resultDiv.style.fontWeight = '600';
            resultDiv.style.textAlign = 'center';
            
            if (type === 'success') {
                resultDiv.style.background = '#f0fdf4';
                resultDiv.style.color = '#166534';
                resultDiv.style.border = '2px solid #bbf7d0';
            } else {
                resultDiv.style.background = '#fef2f2';
                resultDiv.style.color = '#991b1b';
                resultDiv.style.border = '2px solid #fecaca';
            }
        }
        
        // Close modals when clicking outside
        window.onclick = function(event) {
            const bulkModal = document.getElementById('bulkTaskModal');
            if (event.target === bulkModal) {
                closeBulkTaskModal();
            }
        }
        
        // Add hover effect to day checkboxes
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.bulk-day-checkbox').forEach(label => {
                const checkbox = label.querySelector('input[type="checkbox"]');
                
                label.addEventListener('mouseenter', () => {
                    label.style.borderColor = 'var(--glacier)';
                    label.style.background = 'var(--bg-ice)';
                });
                
                label.addEventListener('mouseleave', () => {
                    if (!checkbox.checked) {
                        label.style.borderColor = 'var(--border-light)';
                        label.style.background = 'white';
                    }
                });
                
                checkbox.addEventListener('change', () => {
                    if (checkbox.checked) {
                        label.style.borderColor = 'var(--glacier)';
                        label.style.background = 'rgba(184, 227, 233, 0.2)';
                        label.style.boxShadow = '0 4px 12px rgba(79, 124, 130, 0.15)';
                    } else {
                        label.style.borderColor = 'var(--border-light)';
                        label.style.background = 'white';
                        label.style.boxShadow = 'none';
                    }
                });
            });
        });
    </script>
</body>
</html>
