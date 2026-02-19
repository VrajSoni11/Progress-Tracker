<?php
require_once 'config.php';

if (!isLoggedIn()) {
    redirect('index.php');
}

$user_id = getCurrentUserId();

// Get category-wise progress
$month_start = date('Y-m-01');
$month_end = date('Y-m-t');

$category_stats_query = "SELECT 
    c.category_name, c.category_icon, c.category_color,
    COUNT(t.id) as total_tasks,
    SUM(CASE WHEN t.is_completed = 1 THEN 1 ELSE 0 END) as completed_tasks
    FROM categories c
    LEFT JOIN tasks t ON c.id = t.category_id AND t.task_date BETWEEN ? AND ?
    WHERE c.user_id = ?
    GROUP BY c.id
    ORDER BY completed_tasks DESC";

$stmt = $conn->prepare($category_stats_query);
$stmt->bind_param("ssi", $month_start, $month_end, $user_id);
$stmt->execute();
$categories = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get daily progress for last 30 days
$daily_stats = [];
for ($i = 29; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $display_date = date('M d', strtotime("-$i days"));
    
    $daily_query = "SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN is_completed = 1 THEN 1 ELSE 0 END) as completed
        FROM tasks WHERE user_id = ? AND task_date = ?";
    $stmt = $conn->prepare($daily_query);
    $stmt->bind_param("is", $user_id, $date);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    $progress = $result['total'] > 0 ? round(($result['completed'] / $result['total']) * 100) : 0;
    $daily_stats[] = ['date' => $display_date, 'progress' => $progress];
}

// Get hourly productivity
$hourly_query = "SELECT HOUR(completed_at) as hour, COUNT(*) as count
    FROM tasks WHERE user_id = ? AND is_completed = 1 AND completed_at IS NOT NULL
    GROUP BY HOUR(completed_at)";
$stmt = $conn->prepare($hourly_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$hourly_result = $stmt->get_result();
$hourly_data = array_fill(0, 24, 0);
while ($row = $hourly_result->fetch_assoc()) {
    if ($row['hour'] !== null) $hourly_data[$row['hour']] = $row['count'];
}

// Get weekday productivity
$weekday_query = "SELECT DAYNAME(task_date) as day_name,
    COUNT(*) as total,
    SUM(CASE WHEN is_completed = 1 THEN 1 ELSE 0 END) as completed
    FROM tasks WHERE user_id = ? AND task_date >= DATE_SUB(CURDATE(), INTERVAL 60 DAY)
    GROUP BY DAYOFWEEK(task_date), DAYNAME(task_date)
    ORDER BY DAYOFWEEK(task_date)";
$stmt = $conn->prepare($weekday_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$weekday_result = $stmt->get_result();
$weekday_data = [];
while ($row = $weekday_result->fetch_assoc()) {
    $rate = $row['total'] > 0 ? round(($row['completed'] / $row['total']) * 100) : 0;
    $weekday_data[] = ['day' => $row['day_name'], 'rate' => $rate];
}

// Overall stats
$overall_query = "SELECT 
    COUNT(*) as total_tasks,
    SUM(CASE WHEN is_completed = 1 THEN 1 ELSE 0 END) as completed_tasks,
    COUNT(DISTINCT task_date) as active_days
    FROM tasks WHERE user_id = ?";
$stmt = $conn->prepare($overall_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$overall = $stmt->get_result()->fetch_assoc();
$overall_completion = $overall['total_tasks'] > 0 ? round(($overall['completed_tasks'] / $overall['total_tasks']) * 100) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics Dashboard</title>
    <link rel="stylesheet" href="dashboard.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        .analytics-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(350px,1fr));gap:var(--spacing-lg);margin-bottom:var(--spacing-2xl)}
        .analytics-card{background:rgba(255,255,255,.9);border:2px solid var(--border-light);border-radius:var(--radius-xl);padding:var(--spacing-xl);backdrop-filter:blur(10px);box-shadow:var(--shadow-md)}
        .analytics-card h3{color:var(--text-primary);margin-bottom:var(--spacing-lg);font-size:1.125rem;display:flex;align-items:center;gap:.5rem}
        .chart-container{position:relative;height:300px}
        .overview-stats{display:grid;grid-template-columns:repeat(3,1fr);gap:var(--spacing-md);margin-bottom:var(--spacing-2xl)}
        .overview-stat{background:var(--ice-gradient);border:2px solid var(--border-light);border-radius:var(--radius-lg);padding:var(--spacing-lg);text-align:center}
        .overview-stat-value{font-size:2.5rem;font-weight:800;background:var(--primary-gradient);-webkit-background-clip:text;-webkit-text-fill-color:transparent;margin-bottom:.5rem}
        .overview-stat-label{color:var(--text-secondary);font-weight:600;font-size:.875rem}
        @media(max-width:768px){.overview-stats,.analytics-grid{grid-template-columns:1fr}}
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <div class="nav-brand"><h2>📊 Advanced Analytics</h2></div>
            <div class="nav-user">
                <button onclick="downloadWeeklyReport()" class="btn-nav" style="background:linear-gradient(135deg,#4ade80,#22c55e);color:white;font-weight:700">
                    📥 Download Weekly Report
                </button>
                <button onclick="location.href='dashboard.php'" class="btn-nav">← Dashboard</button>
                <button onclick="location.href='logout.php'" class="btn-logout">Logout</button>
            </div>
        </div>
    </nav>
    <div class="container">
        <div class="overview-stats">
            <div class="overview-stat">
                <div class="overview-stat-value"><?php echo $overall['total_tasks']; ?></div>
                <div class="overview-stat-label">Total Tasks</div>
            </div>
            <div class="overview-stat">
                <div class="overview-stat-value"><?php echo $overall_completion; ?>%</div>
                <div class="overview-stat-label">Completion Rate</div>
            </div>
            <div class="overview-stat">
                <div class="overview-stat-value"><?php echo $overall['active_days']; ?></div>
                <div class="overview-stat-label">Active Days</div>
            </div>
        </div>
        <div class="analytics-grid">
            <div class="analytics-card" style="grid-column:span 2">
                <h3>📈 30-Day Trend</h3>
                <div class="chart-container"><canvas id="trend"></canvas></div>
            </div>
            <div class="analytics-card">
                <h3>🎨 Category Distribution</h3>
                <div class="chart-container"><canvas id="pie"></canvas></div>
            </div>
            <div class="analytics-card" style="grid-column:span 2">
                <h3>⏰ Hourly Productivity</h3>
                <div class="chart-container"><canvas id="hourly"></canvas></div>
            </div>
            <div class="analytics-card">
                <h3>📅 Weekday Performance</h3>
                <div class="chart-container"><canvas id="weekday"></canvas></div>
            </div>
        </div>
    </div>
    <script>
        Chart.defaults.font.family="'Inter',sans-serif";
        Chart.defaults.color='#4f7c82';
        new Chart(document.getElementById('trend'),{type:'line',data:{labels:<?=json_encode(array_column($daily_stats,'date'))?>,datasets:[{label:'Progress',data:<?=json_encode(array_column($daily_stats,'progress'))?>,borderColor:'#4ade80',backgroundColor:'rgba(74,222,128,0.1)',tension:.4,fill:true,borderWidth:3}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}},scales:{y:{beginAtZero:true,max:100,ticks:{callback:v=>v+'%'}}}}});
        new Chart(document.getElementById('pie'),{type:'doughnut',data:{labels:<?=json_encode(array_map(fn($c)=>$c['category_icon'].' '.$c['category_name'],array_filter($categories,fn($c)=>$c['completed_tasks']>0)))?>,datasets:[{data:<?=json_encode(array_column(array_filter($categories,fn($c)=>$c['completed_tasks']>0),'completed_tasks'))?>,backgroundColor:<?=json_encode(array_column(array_filter($categories,fn($c)=>$c['completed_tasks']>0),'category_color'))?>,borderWidth:3,borderColor:'#fff'}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'bottom'}}}});
        new Chart(document.getElementById('hourly'),{type:'bar',data:{labels:Array.from({length:24},(_,i)=>i+':00'),datasets:[{label:'Tasks',data:<?=json_encode($hourly_data)?>,backgroundColor:'rgba(79,124,130,0.6)',borderColor:'#4f7c82',borderWidth:2,borderRadius:6}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}},scales:{y:{beginAtZero:true}}}});
        new Chart(document.getElementById('weekday'),{type:'radar',data:{labels:<?=json_encode(array_column($weekday_data,'day'))?>,datasets:[{label:'Rate',data:<?=json_encode(array_column($weekday_data,'rate'))?>,backgroundColor:'rgba(74,222,128,0.2)',borderColor:'#4ade80',borderWidth:3}]},options:{responsive:true,maintainAspectRatio:false,scales:{r:{beginAtZero:true,max:100}},plugins:{legend:{display:false}}}});
        
        // Download Weekly Report Function
        function downloadWeeklyReport() {
            const today = new Date();
            const dayOfWeek = today.getDay();
            const monday = new Date(today);
            monday.setDate(today.getDate() - dayOfWeek + (dayOfWeek === 0 ? -6 : 1));
            const weekStr = monday.toISOString().split('T')[0];
            window.open('generate_report.php?week=' + weekStr, '_blank');
        }
    </script>
</body>
</html>
