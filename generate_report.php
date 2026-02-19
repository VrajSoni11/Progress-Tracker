<?php
require_once 'config.php';

if (!isLoggedIn()) {
    redirect('index.php');
}

$user_id = getCurrentUserId();
$full_name = $_SESSION['full_name'];

// Get week parameter
$week_start_str = $_GET['week'] ?? date('Y-m-d', strtotime('monday this week'));
$week_start = new DateTime($week_start_str);
$week_end = clone $week_start;
$week_end->modify('+6 days');

// Collect data
$week_data = collectWeeklyData($user_id, $week_start->format('Y-m-d'), $week_end->format('Y-m-d'));
$html = generateReportCardHTML($week_data, $full_name, $week_start, $week_end);

// Download mode - opens print dialog
if (isset($_GET['download'])) {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Weekly Report - <?php echo $week_start->format('M d'); ?> to <?php echo $week_end->format('M d, Y'); ?></title>
        <style>
            @media print {
                body { margin: 0; padding: 20px; }
                .no-print { display: none !important; }
            }
            body { font-family: Arial, sans-serif; }
        </style>
        <script>
            window.onload = function() {
                setTimeout(function() {
                    window.print();
                }, 500);
            }
        </script>
    </head>
    <body>
        <?php echo $html; ?>
        <div class="no-print" style="position:fixed;top:20px;right:20px;background:white;padding:1.5rem;border-radius:12px;box-shadow:0 10px 30px rgba(0,0,0,0.2);border:2px solid #4f7c82">
            <p style="margin:0 0 1rem;font-weight:700;color:#0b2e33;font-size:1.125rem">📄 Save as PDF</p>
            <p style="margin:0 0 1rem;color:#4f7c82;font-size:0.875rem">Use the print dialog to save this report as PDF</p>
            <button onclick="window.print()" style="width:100%;padding:0.75rem 1.5rem;background:#4f7c82;color:white;border:none;border-radius:8px;cursor:pointer;font-weight:600;font-size:1rem;margin-bottom:0.5rem">
                🖨️ Print / Save as PDF
            </button>
            <button onclick="window.close()" style="width:100%;padding:0.75rem 1.5rem;background:#93b1b5;color:white;border:none;border-radius:8px;cursor:pointer;font-weight:600">
                ✕ Close
            </button>
        </div>
    </body>
    </html>
    <?php
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Weekly Report Preview</title>
    <link rel="stylesheet" href="dashboard.css">
    <style>
        .report-preview{max-width:900px;margin:2rem auto;background:white;box-shadow:0 10px 40px rgba(0,0,0,0.1);border-radius:12px;overflow:hidden}
        .report-actions{text-align:center;padding:2rem;background:var(--bg-ice);border-bottom:2px solid var(--border-light)}
        .btn-download{padding:1rem 2rem;background:var(--primary-gradient);color:white;border:none;border-radius:var(--radius-md);font-size:1.125rem;font-weight:700;cursor:pointer;box-shadow:var(--shadow-lg);margin:0 0.5rem}
        .btn-download:hover{transform:translateY(-2px);box-shadow:var(--shadow-xl)}
        .btn-back{padding:1rem 2rem;background:var(--bg-secondary);border:2px solid var(--border-medium);border-radius:var(--radius-md);font-size:1rem;font-weight:600;cursor:pointer;color:var(--text-primary)}
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <div class="nav-brand"><h2>📊 Weekly Report</h2></div>
            <div class="nav-user">
                <button onclick="location.href='analytics.php'" class="btn-nav">← Back to Analytics</button>
            </div>
        </div>
    </nav>
    
    <div class="report-actions">
        <h2 style="color:var(--text-primary);margin-bottom:1rem">📄 Weekly Report Card Preview</h2>
        <p style="margin:0 0 1.5rem;color:var(--text-secondary);font-size:1.125rem">
            Week of <?php echo $week_start->format('M d'); ?> - <?php echo $week_end->format('M d, Y'); ?>
        </p>
        <button onclick="window.location.href='?week=<?php echo $week_start_str; ?>&download=1'" class="btn-download">
            📥 Download as PDF
        </button>
        <button onclick="window.location.href='analytics.php'" class="btn-back">
            Cancel
        </button>
        <p style="margin-top:1.5rem;padding:1rem;background:rgba(74,222,128,0.1);border-radius:8px;font-size:0.875rem;color:var(--text-secondary);max-width:600px;margin-left:auto;margin-right:auto">
            💡 <strong>How to save:</strong> Click "Download as PDF" → Print dialog opens → Choose "Save as PDF" as destination → Click Save!
        </p>
    </div>
    
    <div class="report-preview">
        <?php echo $html; ?>
    </div>
</body>
</html>

<?php
function collectWeeklyData($user_id, $start_date, $end_date) {
    global $conn;
    $data = [];
    
    $stats_query = "SELECT COUNT(*) as total_tasks, SUM(CASE WHEN is_completed = 1 THEN 1 ELSE 0 END) as completed_tasks, COUNT(DISTINCT task_date) as active_days FROM tasks WHERE user_id = ? AND task_date BETWEEN ? AND ?";
    $stmt = $conn->prepare($stats_query);
    $stmt->bind_param("iss", $user_id, $start_date, $end_date);
    $stmt->execute();
    $data['overall'] = $stmt->get_result()->fetch_assoc();
    $data['overall']['completion_rate'] = $data['overall']['total_tasks'] > 0 ? round(($data['overall']['completed_tasks'] / $data['overall']['total_tasks']) * 100) : 0;
    
    $category_query = "SELECT c.category_name, c.category_icon, c.category_color, COUNT(t.id) as total, SUM(CASE WHEN t.is_completed = 1 THEN 1 ELSE 0 END) as completed FROM categories c LEFT JOIN tasks t ON c.id = t.category_id AND t.task_date BETWEEN ? AND ? WHERE c.user_id = ? GROUP BY c.id HAVING total > 0 ORDER BY completed DESC";
    $stmt = $conn->prepare($category_query);
    $stmt->bind_param("ssi", $start_date, $end_date, $user_id);
    $stmt->execute();
    $data['categories'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    $daily_query = "SELECT task_date, COUNT(*) as total, SUM(CASE WHEN is_completed = 1 THEN 1 ELSE 0 END) as completed FROM tasks WHERE user_id = ? AND task_date BETWEEN ? AND ? GROUP BY task_date ORDER BY task_date";
    $stmt = $conn->prepare($daily_query);
    $stmt->bind_param("iss", $user_id, $start_date, $end_date);
    $stmt->execute();
    $data['daily'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    return $data;
}

function generateReportCardHTML($data, $name, $week_start, $week_end) {
    $completion_rate = $data['overall']['completion_rate'];
    $grade = getGrade($completion_rate);
    $grade_color = getGradeColor($grade);
    
    ob_start();
    ?>
    <div style="padding:40px;font-family:'Times New Roman',serif;color:#000;max-width:800px;margin:0 auto;background:#fff">
        <!-- Header -->
        <div style="text-align:center;margin-bottom:40px;border-bottom:3px double #000;padding-bottom:30px">
            <h1 style="font-size:28px;margin:0 0 10px;color:#000;font-weight:bold;letter-spacing:1px">PROGRESS TRACKER</h1>
            <h2 style="font-size:20px;margin:0 0 5px;color:#000;font-weight:normal">Weekly Performance Report</h2>
            <p style="font-size:14px;color:#333;margin:10px 0 0">
                Week of <?php echo $week_start->format('F d'); ?> - <?php echo $week_end->format('F d, Y'); ?>
            </p>
        </div>
        
        <!-- Student Info -->
        <table style="width:100%;margin-bottom:30px;border:2px solid #000">
            <tr>
                <td style="padding:15px;border-right:1px solid #000;width:25%;background:#f5f5f5;font-weight:bold">Student Name:</td>
                <td style="padding:15px;width:75%"><?php echo htmlspecialchars($name); ?></td>
            </tr>
            <tr>
                <td style="padding:15px;border-right:1px solid #000;border-top:1px solid #000;background:#f5f5f5;font-weight:bold">Report Period:</td>
                <td style="padding:15px;border-top:1px solid #000"><?php echo $week_start->format('M d, Y'); ?> - <?php echo $week_end->format('M d, Y'); ?></td>
            </tr>
            <tr>
                <td style="padding:15px;border-right:1px solid #000;border-top:1px solid #000;background:#f5f5f5;font-weight:bold">Date Issued:</td>
                <td style="padding:15px;border-top:1px solid #000"><?php echo date('F d, Y'); ?></td>
            </tr>
        </table>
        
        <!-- Overall Performance -->
        <div style="text-align:center;margin-bottom:30px;border:3px solid #000;padding:25px;background:#f9f9f9">
            <p style="font-size:14px;margin:0 0 10px;font-weight:bold;text-transform:uppercase;letter-spacing:1px">Overall Performance Grade</p>
            <div style="font-size:72px;font-weight:bold;color:<?php echo $grade_color; ?>;line-height:1;margin:15px 0"><?php echo $grade; ?></div>
            <p style="font-size:16px;margin:10px 0 0;color:#333"><?php echo $completion_rate; ?>% Task Completion Rate</p>
        </div>
        
        <!-- Performance Metrics -->
        <table style="width:100%;margin-bottom:30px;border-collapse:collapse;border:2px solid #000">
            <thead>
                <tr style="background:#000;color:#fff">
                    <th style="padding:12px;text-align:left;font-size:14px;font-weight:bold">PERFORMANCE METRIC</th>
                    <th style="padding:12px;text-align:center;font-size:14px;font-weight:bold">VALUE</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td style="padding:12px;border:1px solid #000;background:#f5f5f5">Tasks Completed</td>
                    <td style="padding:12px;border:1px solid #000;text-align:center;font-weight:bold"><?php echo $data['overall']['completed_tasks']; ?></td>
                </tr>
                <tr>
                    <td style="padding:12px;border:1px solid #000;background:#f5f5f5">Total Tasks Assigned</td>
                    <td style="padding:12px;border:1px solid #000;text-align:center;font-weight:bold"><?php echo $data['overall']['total_tasks']; ?></td>
                </tr>
                <tr>
                    <td style="padding:12px;border:1px solid #000;background:#f5f5f5">Active Days</td>
                    <td style="padding:12px;border:1px solid #000;text-align:center;font-weight:bold"><?php echo $data['overall']['active_days']; ?> / 7</td>
                </tr>
                <tr>
                    <td style="padding:12px;border:1px solid #000;background:#f5f5f5">Completion Rate</td>
                    <td style="padding:12px;border:1px solid #000;text-align:center;font-weight:bold"><?php echo $completion_rate; ?>%</td>
                </tr>
            </tbody>
        </table>
        
        <!-- Category Performance -->
        <h3 style="font-size:16px;margin:30px 0 15px;font-weight:bold;text-transform:uppercase;border-bottom:2px solid #000;padding-bottom:8px">Subject Performance</h3>
        <table style="width:100%;margin-bottom:30px;border-collapse:collapse;border:2px solid #000">
            <thead>
                <tr style="background:#000;color:#fff">
                    <th style="padding:12px;text-align:left;font-size:14px;font-weight:bold">SUBJECT</th>
                    <th style="padding:12px;text-align:center;font-size:14px;font-weight:bold;width:120px">COMPLETED</th>
                    <th style="padding:12px;text-align:center;font-size:14px;font-weight:bold;width:120px">TOTAL</th>
                    <th style="padding:12px;text-align:center;font-size:14px;font-weight:bold;width:100px">RATE</th>
                    <th style="padding:12px;text-align:center;font-size:14px;font-weight:bold;width:80px">GRADE</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data['categories'] as $cat): ?>
                    <?php 
                    $cat_rate = $cat['total'] > 0 ? round(($cat['completed'] / $cat['total']) * 100) : 0;
                    $cat_grade = getGrade($cat_rate);
                    ?>
                    <tr>
                        <td style="padding:12px;border:1px solid #000;background:#f5f5f5"><?php echo htmlspecialchars($cat['category_name']); ?></td>
                        <td style="padding:12px;border:1px solid #000;text-align:center"><?php echo $cat['completed']; ?></td>
                        <td style="padding:12px;border:1px solid #000;text-align:center"><?php echo $cat['total']; ?></td>
                        <td style="padding:12px;border:1px solid #000;text-align:center"><?php echo $cat_rate; ?>%</td>
                        <td style="padding:12px;border:1px solid #000;text-align:center;font-weight:bold;color:<?php echo getGradeColor($cat_grade); ?>"><?php echo $cat_grade; ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <!-- Daily Attendance -->
        <h3 style="font-size:16px;margin:30px 0 15px;font-weight:bold;text-transform:uppercase;border-bottom:2px solid #000;padding-bottom:8px">Daily Attendance Record</h3>
        <table style="width:100%;margin-bottom:30px;border-collapse:collapse;border:2px solid #000">
            <thead>
                <tr style="background:#000;color:#fff">
                    <th style="padding:12px;text-align:center;font-size:14px;font-weight:bold">DAY</th>
                    <?php 
                    for ($i = 0; $i < 7; $i++) {
                        $date = clone $week_start;
                        $date->modify("+$i days");
                        echo '<th style="padding:12px;text-align:center;font-size:14px;font-weight:bold">' . $date->format('D') . '<br>' . $date->format('M d') . '</th>';
                    }
                    ?>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td style="padding:12px;border:1px solid #000;background:#f5f5f5;font-weight:bold;text-align:center">Status</td>
                    <?php 
                    $daily_map = [];
                    foreach ($data['daily'] as $day) {
                        $daily_map[$day['task_date']] = $day;
                    }
                    
                    for ($i = 0; $i < 7; $i++) {
                        $date = clone $week_start;
                        $date->modify("+$i days");
                        $date_str = $date->format('Y-m-d');
                        $day_data = $daily_map[$date_str] ?? null;
                        
                        if (!$day_data) {
                            $status = '-';
                            $bg = '#fff';
                        } else {
                            $day_rate = round(($day_data['completed'] / $day_data['total']) * 100);
                            if ($day_rate == 100) {
                                $status = 'COMPLETE';
                                $bg = '#d4edda';
                            } elseif ($day_rate > 0) {
                                $status = 'PARTIAL';
                                $bg = '#fff3cd';
                            } else {
                                $status = 'INCOMPLETE';
                                $bg = '#f8d7da';
                            }
                        }
                        
                        echo '<td style="padding:12px;border:1px solid #000;text-align:center;background:' . $bg . ';font-weight:bold;font-size:11px">' . $status . '</td>';
                    }
                    ?>
                </tr>
                <tr>
                    <td style="padding:12px;border:1px solid #000;background:#f5f5f5;font-weight:bold;text-align:center">Rate</td>
                    <?php 
                    for ($i = 0; $i < 7; $i++) {
                        $date = clone $week_start;
                        $date->modify("+$i days");
                        $date_str = $date->format('Y-m-d');
                        $day_data = $daily_map[$date_str] ?? null;
                        $day_rate = $day_data ? round(($day_data['completed'] / $day_data['total']) * 100) : 0;
                        echo '<td style="padding:12px;border:1px solid #000;text-align:center">' . $day_rate . '%</td>';
                    }
                    ?>
                </tr>
            </tbody>
        </table>
        
        <!-- Grading Scale -->
        <div style="margin-top:30px;padding:20px;border:1px solid #000;background:#f9f9f9">
            <h4 style="font-size:14px;margin:0 0 15px;font-weight:bold;text-transform:uppercase">Grading Scale</h4>
            <table style="width:100%;border-collapse:collapse">
                <tr>
                    <td style="padding:8px;border:1px solid #ccc;background:#fff;width:20%">A+ (90-100%)</td>
                    <td style="padding:8px;border:1px solid #ccc;background:#fff;width:20%">A (85-89%)</td>
                    <td style="padding:8px;border:1px solid #ccc;background:#fff;width:20%">B+ (75-84%)</td>
                    <td style="padding:8px;border:1px solid #ccc;background:#fff;width:20%">B (70-74%)</td>
                    <td style="padding:8px;border:1px solid #ccc;background:#fff;width:20%">C (55-69%)</td>
                </tr>
            </table>
        </div>
        
        <!-- Footer -->
        <div style="margin-top:40px;padding-top:20px;border-top:2px solid #000;text-align:center">
            <p style="font-size:12px;color:#666;margin:0">This report was automatically generated by Progress Tracker on <?php echo date('F d, Y'); ?></p>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

function getGrade($p) {
    if ($p >= 90) return 'A+';
    if ($p >= 85) return 'A';
    if ($p >= 80) return 'A-';
    if ($p >= 75) return 'B+';
    if ($p >= 70) return 'B';
    if ($p >= 65) return 'B-';
    if ($p >= 60) return 'C+';
    if ($p >= 55) return 'C';
    if ($p >= 50) return 'C-';
    return 'F';
}

function getGradeColor($g) {
    if (in_array($g, ['A+', 'A', 'A-'])) return '#22c55e';
    if (in_array($g, ['B+', 'B', 'B-'])) return '#3b82f6';
    if (in_array($g, ['C+', 'C', 'C-'])) return '#f59e0b';
    return '#ef4444';
}
?>