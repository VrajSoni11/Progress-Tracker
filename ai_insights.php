<?php
require_once 'config.php';

if (!isLoggedIn()) {
    redirect('index.php');
}

$user_id = getCurrentUserId();
$full_name = $_SESSION['full_name'];

// Generate AI insights
$insights = generateAIInsights($user_id);

function generateAIInsights($user_id) {
    global $conn;
    $insights = [
        'productivity' => [],
        'patterns' => [],
        'recommendations' => [],
        'warnings' => [],
        'achievements' => []
    ];
    
    // 1. TIME UTILIZATION ANALYSIS
    $hourly_query = "SELECT HOUR(completed_at) as hour, COUNT(*) as count
        FROM tasks 
        WHERE user_id = ? AND is_completed = 1 AND completed_at IS NOT NULL
        GROUP BY HOUR(completed_at)
        ORDER BY count DESC
        LIMIT 1";
    $stmt = $conn->prepare($hourly_query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $peak_hour = $stmt->get_result()->fetch_assoc();
    
    if ($peak_hour && $peak_hour['count'] > 5) {
        $hour = $peak_hour['hour'];
        $period = $hour < 12 ? 'morning' : ($hour < 17 ? 'afternoon' : 'evening');
        $insights['productivity'][] = [
            'icon' => '⏰',
            'title' => 'Peak Productivity Time',
            'message' => "You're most productive in the $period (around {$hour}:00). Schedule important tasks during this time!",
            'type' => 'success'
        ];
    }
    
    // 2. CATEGORY PERFORMANCE PATTERNS
    $category_query = "SELECT c.category_name, c.category_icon,
        COUNT(t.id) as total,
        SUM(CASE WHEN t.is_completed = 1 THEN 1 ELSE 0 END) as completed,
        ROUND(SUM(CASE WHEN t.is_completed = 1 THEN 1 ELSE 0 END) / COUNT(t.id) * 100) as rate
        FROM categories c
        LEFT JOIN tasks t ON c.id = t.category_id AND t.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        WHERE c.user_id = ?
        GROUP BY c.id
        HAVING total > 5
        ORDER BY rate DESC";
    $stmt = $conn->prepare($category_query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $categories = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    if (count($categories) > 0) {
        $best = $categories[0];
        $worst = $categories[count($categories) - 1];
        
        if ($best['rate'] >= 80) {
            $insights['patterns'][] = [
                'icon' => '🎯',
                'title' => 'Strong Performance',
                'message' => "You excel at {$best['category_icon']} {$best['category_name']} tasks with {$best['rate']}% completion rate!",
                'type' => 'success'
            ];
        }
        
        if ($worst['rate'] < 50 && count($categories) > 1) {
            $insights['warnings'][] = [
                'icon' => '⚠️',
                'title' => 'Needs Attention',
                'message' => "{$worst['category_icon']} {$worst['category_name']} tasks have only {$worst['rate']}% completion. Break them into smaller tasks!",
                'type' => 'warning'
            ];
        }
    }
    
    // 3. WEEKDAY PRODUCTIVITY PATTERNS
    $weekday_query = "SELECT DAYNAME(task_date) as day_name, DAYOFWEEK(task_date) as day_num,
        COUNT(*) as total,
        SUM(CASE WHEN is_completed = 1 THEN 1 ELSE 0 END) as completed,
        ROUND(SUM(CASE WHEN is_completed = 1 THEN 1 ELSE 0 END) / COUNT(*) * 100) as rate
        FROM tasks
        WHERE user_id = ? AND task_date >= DATE_SUB(CURDATE(), INTERVAL 60 DAY)
        GROUP BY day_num, day_name
        ORDER BY rate DESC";
    $stmt = $conn->prepare($weekday_query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $weekdays = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    if (count($weekdays) >= 5) {
        $best_day = $weekdays[0];
        $worst_day = $weekdays[count($weekdays) - 1];
        
        if ($best_day['rate'] >= 80) {
            $insights['patterns'][] = [
                'icon' => '📅',
                'title' => 'Best Day Pattern',
                'message' => "{$best_day['day_name']}s are your most productive days ({$best_day['rate']}% completion). Schedule challenging tasks then!",
                'type' => 'info'
            ];
        }
        
        if ($worst_day['rate'] < 60) {
            $insights['recommendations'][] = [
                'icon' => '💡',
                'title' => 'Improve Your ' . $worst_day['day_name'],
                'message' => "Your completion rate drops to {$worst_day['rate']}% on {$worst_day['day_name']}s. Try lighter tasks or better planning!",
                'type' => 'info'
            ];
        }
    }
    
    // 4. STREAK ANALYSIS
    $streak_query = "SELECT current_streak, longest_streak FROM user_gamification WHERE user_id = ?";
    $stmt = $conn->prepare($streak_query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $streak = $stmt->get_result()->fetch_assoc();
    
    if ($streak) {
        if ($streak['current_streak'] >= 7) {
            $insights['achievements'][] = [
                'icon' => '🔥',
                'title' => 'Impressive Streak!',
                'message' => "You've maintained a {$streak['current_streak']}-day streak! Keep the momentum going!",
                'type' => 'success'
            ];
            
            if ($streak['current_streak'] >= 20) {
                $days_to_30 = 30 - $streak['current_streak'];
                $insights['achievements'][] = [
                    'icon' => '🎯',
                    'title' => 'Streak Milestone Approaching',
                    'message' => "Just $days_to_30 more days to reach a 30-day streak! You're crushing it!",
                    'type' => 'success'
                ];
            }
        }
        
        if ($streak['current_streak'] < 3 && $streak['longest_streak'] >= 7) {
            $insights['warnings'][] = [
                'icon' => '📉',
                'title' => 'Streak Broken',
                'message' => "Your longest streak was {$streak['longest_streak']} days. Let's rebuild that consistency!",
                'type' => 'warning'
            ];
        }
    }
    
    // 5. TASK COMPLETION TIME ANALYSIS
    $completion_query = "SELECT 
        AVG(TIMESTAMPDIFF(HOUR, created_at, completed_at)) as avg_hours
        FROM tasks
        WHERE user_id = ? AND is_completed = 1 AND completed_at IS NOT NULL
        AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
    $stmt = $conn->prepare($completion_query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $completion = $stmt->get_result()->fetch_assoc();
    
    if ($completion && $completion['avg_hours'] !== null) {
        $hours = round($completion['avg_hours']);
        if ($hours < 2) {
            $insights['patterns'][] = [
                'icon' => '⚡',
                'title' => 'Quick Executor',
                'message' => "You complete tasks within $hours hours on average. Great at getting things done quickly!",
                'type' => 'success'
            ];
        } elseif ($hours > 24) {
            $days = round($hours / 24);
            $insights['recommendations'][] = [
                'icon' => '🎯',
                'title' => 'Break Tasks Down',
                'message' => "Tasks take ~$days days to complete. Try breaking them into smaller, daily sub-tasks!",
                'type' => 'info'
            ];
        }
    }
    
    // 6. TASK VOLUME ANALYSIS
    $volume_query = "SELECT 
        COUNT(*) as total_tasks,
        COUNT(*) / COUNT(DISTINCT task_date) as avg_per_day
        FROM tasks
        WHERE user_id = ? AND task_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
    $stmt = $conn->prepare($volume_query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $volume = $stmt->get_result()->fetch_assoc();
    
    if ($volume) {
        $avg = round($volume['avg_per_day'], 1);
        if ($avg > 10) {
            $insights['warnings'][] = [
                'icon' => '📊',
                'title' => 'High Task Volume',
                'message' => "You're planning $avg tasks per day. Consider prioritizing to avoid overwhelm!",
                'type' => 'warning'
            ];
        } elseif ($avg < 3 && $volume['total_tasks'] > 0) {
            $insights['recommendations'][] = [
                'icon' => '📈',
                'title' => 'Room to Grow',
                'message' => "You average $avg tasks per day. There's capacity to add more goals!",
                'type' => 'info'
            ];
        }
    }
    
    // 7. RECENT ACTIVITY CHECK
    $recent_query = "SELECT MAX(task_date) as last_active FROM tasks WHERE user_id = ?";
    $stmt = $conn->prepare($recent_query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $recent = $stmt->get_result()->fetch_assoc();
    
    if ($recent && $recent['last_active']) {
        $days_inactive = (strtotime('today') - strtotime($recent['last_active'])) / 86400;
        if ($days_inactive >= 3) {
            $insights['warnings'][] = [
                'icon' => '⏰',
                'title' => 'Get Back On Track',
                'message' => "It's been " . round($days_inactive) . " days since your last task. Time to restart your routine!",
                'type' => 'warning'
            ];
        }
    }
    
    // 8. CATEGORY BALANCE
    $balance_query = "SELECT COUNT(DISTINCT category_id) as active_categories FROM tasks WHERE user_id = ? AND task_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
    $stmt = $conn->prepare($balance_query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $balance = $stmt->get_result()->fetch_assoc();
    
    $total_cats_query = "SELECT COUNT(*) as total FROM categories WHERE user_id = ?";
    $stmt = $conn->prepare($total_cats_query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $total_cats = $stmt->get_result()->fetch_assoc();
    
    if ($balance && $total_cats && $total_cats['total'] > 2) {
        $active = $balance['active_categories'];
        $total = $total_cats['total'];
        
        if ($active < $total / 2) {
            $unused = $total - $active;
            $insights['recommendations'][] = [
                'icon' => '🎨',
                'title' => 'Balance Your Life',
                'message' => "You're only using $active of $total categories. Add tasks to your other $unused life areas for better balance!",
                'type' => 'info'
            ];
        }
    }
    
    // 9. CONSISTENCY SCORE
    $consistency_query = "SELECT COUNT(DISTINCT task_date) as active_days FROM tasks WHERE user_id = ? AND task_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
    $stmt = $conn->prepare($consistency_query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $consistency = $stmt->get_result()->fetch_assoc();
    
    if ($consistency) {
        $active_days = $consistency['active_days'];
        $consistency_rate = round(($active_days / 30) * 100);
        
        if ($consistency_rate >= 80) {
            $insights['achievements'][] = [
                'icon' => '🌟',
                'title' => 'Highly Consistent',
                'message' => "You've been active $active_days out of 30 days ($consistency_rate%). Excellent consistency!",
                'type' => 'success'
            ];
        } elseif ($consistency_rate < 50) {
            $insights['recommendations'][] = [
                'icon' => '📅',
                'title' => 'Build Consistency',
                'message' => "You've been active $active_days days this month. Aim for daily engagement to build lasting habits!",
                'type' => 'info'
            ];
        }
    }
    
    // 10. SMART RECOMMENDATIONS
    $today_query = "SELECT COUNT(*) as today_tasks FROM tasks WHERE user_id = ? AND task_date = CURDATE()";
    $stmt = $conn->prepare($today_query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $today = $stmt->get_result()->fetch_assoc();
    
    if ($today && $today['today_tasks'] == 0) {
        $insights['recommendations'][] = [
            'icon' => '🎯',
            'title' => 'Plan Your Day',
            'message' => "You haven't added any tasks for today. Start by planning 3-5 important tasks!",
            'type' => 'info'
        ];
    }
    
    return $insights;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Insights - Progress Tracker</title>
    <link rel="stylesheet" href="dashboard.css">
    <style>
        .insights-container{max-width:1200px;margin:0 auto;padding:2rem}
        .insights-header{text-align:center;margin-bottom:3rem}
        .insights-header h1{font-size:2.5rem;margin:0 0 0.5rem;background:var(--primary-gradient);-webkit-background-clip:text;-webkit-text-fill-color:transparent}
        .insights-header p{color:var(--text-secondary);font-size:1.125rem}
        .insight-section{margin-bottom:2rem}
        .insight-section-title{font-size:1.5rem;font-weight:700;color:var(--text-primary);margin-bottom:1rem;display:flex;align-items:center;gap:0.5rem}
        .insight-card{background:rgba(255,255,255,0.95);border:2px solid var(--border-light);border-radius:var(--radius-xl);padding:1.5rem;margin-bottom:1rem;transition:all var(--transition-base);border-left:6px solid}
        .insight-card:hover{transform:translateY(-2px);box-shadow:var(--shadow-xl)}
        .insight-card.success{border-left-color:#22c55e;background:linear-gradient(to right,rgba(34,197,94,0.05),transparent)}
        .insight-card.warning{border-left-color:#f59e0b;background:linear-gradient(to right,rgba(245,158,11,0.05),transparent)}
        .insight-card.info{border-left-color:#3b82f6;background:linear-gradient(to right,rgba(59,130,246,0.05),transparent)}
        .insight-header-row{display:flex;align-items:center;gap:1rem;margin-bottom:0.75rem}
        .insight-icon{font-size:2rem;line-height:1}
        .insight-title{font-size:1.25rem;font-weight:700;color:var(--text-primary);margin:0}
        .insight-message{color:var(--text-secondary);line-height:1.6;font-size:1rem}
        .no-insights{text-align:center;padding:4rem 2rem;background:var(--bg-ice);border-radius:var(--radius-xl);border:2px dashed var(--border-medium)}
        .no-insights-icon{font-size:4rem;margin-bottom:1rem;opacity:0.5}
        .no-insights-text{color:var(--text-secondary);font-size:1.125rem;margin-bottom:1rem}
        .no-insights-subtext{color:var(--text-tertiary);font-size:0.9375rem}
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <div class="nav-brand"><h2>🤖 AI Insights</h2></div>
            <div class="nav-user">
                <span>Welcome, <strong><?php echo htmlspecialchars($full_name); ?></strong></span>
                <button onclick="location.href='dashboard.php'" class="btn-nav">← Dashboard</button>
                <button onclick="location.href='logout.php'" class="btn-logout">Logout</button>
            </div>
        </div>
    </nav>

    <div class="insights-container">
        <div class="insights-header">
            <h1>AI-Powered Insights</h1>
            <p>Personalized analysis of your productivity patterns and habits</p>
        </div>

        <?php
        $has_insights = false;
        foreach ($insights as $section => $items) {
            if (!empty($items)) {
                $has_insights = true;
                break;
            }
        }
        
        if (!$has_insights):
        ?>
            <div class="no-insights">
                <div class="no-insights-icon">🤖</div>
                <div class="no-insights-text">Not enough data for AI insights yet</div>
                <div class="no-insights-subtext">Keep using the app for a few days to unlock personalized insights!</div>
            </div>
        <?php else: ?>
            
            <?php if (!empty($insights['achievements'])): ?>
            <div class="insight-section">
                <h2 class="insight-section-title">🏆 Achievements & Wins</h2>
                <?php foreach ($insights['achievements'] as $insight): ?>
                    <div class="insight-card <?php echo $insight['type']; ?>">
                        <div class="insight-header-row">
                            <div class="insight-icon"><?php echo $insight['icon']; ?></div>
                            <h3 class="insight-title"><?php echo $insight['title']; ?></h3>
                        </div>
                        <p class="insight-message"><?php echo $insight['message']; ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php if (!empty($insights['productivity'])): ?>
            <div class="insight-section">
                <h2 class="insight-section-title">⚡ Productivity Analysis</h2>
                <?php foreach ($insights['productivity'] as $insight): ?>
                    <div class="insight-card <?php echo $insight['type']; ?>">
                        <div class="insight-header-row">
                            <div class="insight-icon"><?php echo $insight['icon']; ?></div>
                            <h3 class="insight-title"><?php echo $insight['title']; ?></h3>
                        </div>
                        <p class="insight-message"><?php echo $insight['message']; ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php if (!empty($insights['patterns'])): ?>
            <div class="insight-section">
                <h2 class="insight-section-title">🔍 Pattern Detection</h2>
                <?php foreach ($insights['patterns'] as $insight): ?>
                    <div class="insight-card <?php echo $insight['type']; ?>">
                        <div class="insight-header-row">
                            <div class="insight-icon"><?php echo $insight['icon']; ?></div>
                            <h3 class="insight-title"><?php echo $insight['title']; ?></h3>
                        </div>
                        <p class="insight-message"><?php echo $insight['message']; ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php if (!empty($insights['recommendations'])): ?>
            <div class="insight-section">
                <h2 class="insight-section-title">💡 Smart Recommendations</h2>
                <?php foreach ($insights['recommendations'] as $insight): ?>
                    <div class="insight-card <?php echo $insight['type']; ?>">
                        <div class="insight-header-row">
                            <div class="insight-icon"><?php echo $insight['icon']; ?></div>
                            <h3 class="insight-title"><?php echo $insight['title']; ?></h3>
                        </div>
                        <p class="insight-message"><?php echo $insight['message']; ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php if (!empty($insights['warnings'])): ?>
            <div class="insight-section">
                <h2 class="insight-section-title">⚠️ Areas for Improvement</h2>
                <?php foreach ($insights['warnings'] as $insight): ?>
                    <div class="insight-card <?php echo $insight['type']; ?>">
                        <div class="insight-header-row">
                            <div class="insight-icon"><?php echo $insight['icon']; ?></div>
                            <h3 class="insight-title"><?php echo $insight['title']; ?></h3>
                        </div>
                        <p class="insight-message"><?php echo $insight['message']; ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

        <?php endif; ?>
    </div>
</body>
</html>
