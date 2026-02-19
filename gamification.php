<?php
require_once 'config.php';
require_once 'update_streak.php';

if (!isLoggedIn()) redirect('index.php');

$user_id = getCurrentUserId();
$full_name = $_SESSION['full_name'];

// Get or create gamification stats
$stats_query = "SELECT * FROM user_gamification WHERE user_id = ?";
$stmt = $conn->prepare($stats_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();

if (!$stats) {
    $conn->query("INSERT INTO user_gamification (user_id) VALUES ($user_id)");
    $stats = ['current_streak' => 0, 'longest_streak' => 0, 'total_xp' => 0, 'level' => 1, 'total_tasks_completed' => 0, 'perfect_days' => 0];
}

// Get unlocked badges
$badges_query = "SELECT b.*, ub.unlocked_at 
    FROM user_badges ub 
    JOIN badges b ON ub.badge_id = b.id 
    WHERE ub.user_id = ? 
    ORDER BY ub.unlocked_at DESC";
$stmt = $conn->prepare($badges_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$unlocked_badges = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$unlocked_badge_ids = array_column($unlocked_badges, 'badge_id');

// Get all badges
$all_badges = $conn->query("SELECT * FROM badges ORDER BY badge_tier, xp_reward")->fetch_all(MYSQLI_ASSOC);

// Calculate level from XP
$xp = $stats['total_xp'];
$level = floor(sqrt($xp / 100)) + 1;
$xp_for_current_level = pow($level - 1, 2) * 100;
$xp_for_next_level = pow($level, 2) * 100;
$xp_progress = $xp - $xp_for_current_level;
$xp_needed = $xp_for_next_level - $xp_for_current_level;
$level_progress = $xp_needed > 0 ? ($xp_progress / $xp_needed) * 100 : 0;

// Badge tier colors
$tier_colors = [
    'bronze' => ['bg' => '#cd7f32', 'border' => '#8b5a2b'],
    'silver' => ['bg' => '#c0c0c0', 'border' => '#808080'],
    'gold' => ['bg' => '#ffd700', 'border' => '#daa520'],
    'platinum' => ['bg' => '#e5e4e2', 'border' => '#b4b4b4']
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Achievements</title>
    <link rel="stylesheet" href="dashboard.css">
    <style>
        .gamification-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:var(--spacing-lg);margin-bottom:var(--spacing-2xl)}
        .gamification-card{background:rgba(255,255,255,.95);border:2px solid var(--border-light);border-radius:var(--radius-xl);padding:var(--spacing-xl);backdrop-filter:blur(10px);box-shadow:var(--shadow-md);transition:all var(--transition-base)}
        .gamification-card:hover{transform:translateY(-4px);box-shadow:var(--shadow-xl)}
        .gamification-card h3{color:var(--text-primary);margin-bottom:var(--spacing-lg);font-size:1.25rem;display:flex;align-items:center;gap:.5rem}
        .streak-display{text-align:center;padding:var(--spacing-xl);background:linear-gradient(135deg,rgba(255,107,107,.1),rgba(255,138,138,.1));border-radius:var(--radius-lg);margin-bottom:var(--spacing-md)}
        .streak-number{font-size:4rem;font-weight:800;background:linear-gradient(135deg,#ff6b6b,#ff8a8a);-webkit-background-clip:text;-webkit-text-fill-color:transparent;line-height:1;margin-bottom:.5rem}
        .streak-label{color:var(--text-secondary);font-weight:600;text-transform:uppercase;letter-spacing:.1em;font-size:.875rem}
        .level-display{text-align:center;padding:var(--spacing-xl);background:var(--ice-gradient);border-radius:var(--radius-lg);margin-bottom:var(--spacing-md)}
        .level-number{font-size:3rem;font-weight:800;background:var(--primary-gradient);-webkit-background-clip:text;-webkit-text-fill-color:transparent;margin-bottom:.5rem}
        .xp-bar{height:24px;background:var(--bg-tertiary);border-radius:var(--radius-full);overflow:hidden;margin-bottom:.5rem;box-shadow:inset 0 2px 4px rgba(11,46,51,.1)}
        .xp-fill{height:100%;background:linear-gradient(90deg,#4ade80,#22c55e);border-radius:var(--radius-full);transition:width .5s;position:relative;display:flex;align-items:center;justify-content:center;color:white;font-weight:700;font-size:.75rem}
        .badge-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:var(--spacing-md)}
        .badge-item{text-align:center;padding:var(--spacing-md);border-radius:var(--radius-lg);transition:all var(--transition-base);cursor:pointer;position:relative}
        .badge-item.unlocked{background:rgba(74,222,128,.1);border:2px solid #22c55e}
        .badge-item.locked{background:var(--bg-tertiary);border:2px solid var(--border-light);opacity:.5}
        .badge-item:hover{transform:scale(1.05)}
        .badge-icon{font-size:3rem;margin-bottom:.5rem;display:block}
        .badge-name{font-weight:700;color:var(--text-primary);font-size:.875rem;margin-bottom:.25rem}
        .badge-xp{font-size:.75rem;color:var(--text-secondary);font-weight:600}
        .badge-tier{position:absolute;top:8px;right:8px;padding:.25rem .5rem;border-radius:var(--radius-sm);font-size:.625rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em}
        .stats-row{display:grid;grid-template-columns:repeat(3,1fr);gap:var(--spacing-md);margin-bottom:var(--spacing-md)}
        .stat-box{text-align:center;padding:var(--spacing-md);background:var(--bg-ice);border-radius:var(--radius-md);border:2px solid var(--border-light)}
        .stat-box-value{font-size:2rem;font-weight:800;background:var(--primary-gradient);-webkit-background-clip:text;-webkit-text-fill-color:transparent;margin-bottom:.25rem}
        .stat-box-label{font-size:.75rem;color:var(--text-secondary);font-weight:600;text-transform:uppercase;letter-spacing:.05em}
        .recent-badges{display:flex;flex-direction:column;gap:var(--spacing-sm)}
        .recent-badge-item{display:flex;align-items:center;gap:var(--spacing-md);padding:var(--spacing-md);background:var(--bg-ice);border-radius:var(--radius-md);border:1px solid var(--border-light)}
        .recent-badge-icon{font-size:2rem}
        .recent-badge-info{flex:1}
        .recent-badge-name{font-weight:700;color:var(--text-primary);margin-bottom:.25rem}
        .recent-badge-date{font-size:.75rem;color:var(--text-tertiary)}
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <div class="nav-brand"><h2>🏆 ACHIEVEMENTS</h2></div>
            <div class="nav-user">
                <span>🔥 <strong><?php echo $stats['current_streak']; ?> day streak</strong></span>
                <button onclick="location.href='dashboard.php'" class="btn-nav">← Dashboard</button>
                <button onclick="location.href='logout.php'" class="btn-logout">Logout</button>
            </div>
        </div>
    </nav>

    <div class="container">
        <!-- Top Stats Overview -->
        <div class="gamification-grid">
            <!-- Streak Card -->
            <div class="gamification-card">
                <h3>🔥 Current Streak</h3>
                <div class="streak-display">
                    <div class="streak-number"><?php echo $stats['current_streak']; ?></div>
                    <div class="streak-label">Days in a Row</div>
                </div>
                <div class="stats-row">
                    <div class="stat-box">
                        <div class="stat-box-value"><?php echo $stats['longest_streak']; ?></div>
                        <div class="stat-box-label">Longest</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-box-value"><?php echo $stats['total_tasks_completed']; ?></div>
                        <div class="stat-box-label">Tasks</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-box-value"><?php echo $stats['perfect_days']; ?></div>
                        <div class="stat-box-label">Perfect Days</div>
                    </div>
                </div>
            </div>

            <!-- Level Card -->
            <div class="gamification-card">
                <h3>⭐ Level & Experience</h3>
                <div class="level-display">
                    <div class="level-number">Level <?php echo $level; ?></div>
                    <div class="streak-label"><?php echo number_format($xp); ?> Total XP</div>
                </div>
                <div class="xp-bar">
                    <div class="xp-fill" style="width: <?php echo $level_progress; ?>%">
                        <?php echo round($level_progress); ?>%
                    </div>
                </div>
                <p style="text-align:center;color:var(--text-secondary);font-size:.875rem;margin-top:.5rem">
                    <?php echo number_format($xp_progress); ?> / <?php echo number_format($xp_needed); ?> XP to Level <?php echo $level + 1; ?>
                </p>
            </div>

            <!-- Recent Badges -->
            <div class="gamification-card">
                <h3>🏆 Recent Achievements</h3>
                <?php if (empty($unlocked_badges)): ?>
                    <p style="text-align:center;color:var(--text-tertiary);padding:var(--spacing-xl);font-style:italic">
                        Complete tasks to unlock badges!
                    </p>
                <?php else: ?>
                    <div class="recent-badges">
                        <?php foreach (array_slice($unlocked_badges, 0, 4) as $badge): ?>
                            <div class="recent-badge-item">
                                <div class="recent-badge-icon"><?php echo $badge['badge_icon']; ?></div>
                                <div class="recent-badge-info">
                                    <div class="recent-badge-name"><?php echo $badge['badge_name']; ?></div>
                                    <div class="recent-badge-date">
                                        Unlocked <?php echo date('M d, Y', strtotime($badge['unlocked_at'])); ?>
                                    </div>
                                </div>
                                <div style="color:#22c55e;font-weight:700;font-size:.875rem">
                                    +<?php echo $badge['xp_reward']; ?> XP
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- All Badges -->
        <div class="gamification-card" style="grid-column:1/-1">
            <h3>🏅 All Badges (<?php echo count($unlocked_badges); ?>/<?php echo count($all_badges); ?>)</h3>
            
            <!-- Bronze Badges -->
            <h4 style="color:var(--text-primary);margin:var(--spacing-lg) 0 var(--spacing-md);display:flex;align-items:center;gap:.5rem">
                🥉 Bronze Tier
            </h4>
            <div class="badge-grid">
                <?php foreach ($all_badges as $badge): ?>
                    <?php if ($badge['badge_tier'] !== 'bronze') continue; ?>
                    <?php $is_unlocked = in_array($badge['id'], $unlocked_badge_ids); ?>
                    <div class="badge-item <?php echo $is_unlocked ? 'unlocked' : 'locked'; ?>" 
                         title="<?php echo htmlspecialchars($badge['badge_description']); ?>">
                        <div class="badge-tier" style="background:<?php echo $tier_colors['bronze']['bg']; ?>;color:#fff">
                            Bronze
                        </div>
                        <div class="badge-icon"><?php echo $badge['badge_icon']; ?></div>
                        <div class="badge-name"><?php echo $badge['badge_name']; ?></div>
                        <div class="badge-xp">+<?php echo $badge['xp_reward']; ?> XP</div>
                        <?php if (!$is_unlocked): ?>
                            <div style="font-size:.75rem;color:var(--text-tertiary);margin-top:.5rem">
                                🔒 Locked
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Silver Badges -->
            <h4 style="color:var(--text-primary);margin:var(--spacing-lg) 0 var(--spacing-md);display:flex;align-items:center;gap:.5rem">
                🥈 Silver Tier
            </h4>
            <div class="badge-grid">
                <?php foreach ($all_badges as $badge): ?>
                    <?php if ($badge['badge_tier'] !== 'silver') continue; ?>
                    <?php $is_unlocked = in_array($badge['id'], $unlocked_badge_ids); ?>
                    <div class="badge-item <?php echo $is_unlocked ? 'unlocked' : 'locked'; ?>"
                         title="<?php echo htmlspecialchars($badge['badge_description']); ?>">
                        <div class="badge-tier" style="background:<?php echo $tier_colors['silver']['bg']; ?>;color:#333">
                            Silver
                        </div>
                        <div class="badge-icon"><?php echo $badge['badge_icon']; ?></div>
                        <div class="badge-name"><?php echo $badge['badge_name']; ?></div>
                        <div class="badge-xp">+<?php echo $badge['xp_reward']; ?> XP</div>
                        <?php if (!$is_unlocked): ?>
                            <div style="font-size:.75rem;color:var(--text-tertiary);margin-top:.5rem">
                                🔒 Locked
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Gold Badges -->
            <h4 style="color:var(--text-primary);margin:var(--spacing-lg) 0 var(--spacing-md);display:flex;align-items:center;gap:.5rem">
                🥇 Gold Tier
            </h4>
            <div class="badge-grid">
                <?php foreach ($all_badges as $badge): ?>
                    <?php if ($badge['badge_tier'] !== 'gold') continue; ?>
                    <?php $is_unlocked = in_array($badge['id'], $unlocked_badge_ids); ?>
                    <div class="badge-item <?php echo $is_unlocked ? 'unlocked' : 'locked'; ?>"
                         title="<?php echo htmlspecialchars($badge['badge_description']); ?>">
                        <div class="badge-tier" style="background:<?php echo $tier_colors['gold']['bg']; ?>;color:#333">
                            Gold
                        </div>
                        <div class="badge-icon"><?php echo $badge['badge_icon']; ?></div>
                        <div class="badge-name"><?php echo $badge['badge_name']; ?></div>
                        <div class="badge-xp">+<?php echo $badge['xp_reward']; ?> XP</div>
                        <?php if (!$is_unlocked): ?>
                            <div style="font-size:.75rem;color:var(--text-tertiary);margin-top:.5rem">
                                🔒 Locked
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Platinum Badges -->
            <h4 style="color:var(--text-primary);margin:var(--spacing-lg) 0 var(--spacing-md);display:flex;align-items:center;gap:.5rem">
                💎 Platinum Tier
            </h4>
            <div class="badge-grid">
                <?php foreach ($all_badges as $badge): ?>
                    <?php if ($badge['badge_tier'] !== 'platinum') continue; ?>
                    <?php $is_unlocked = in_array($badge['id'], $unlocked_badge_ids); ?>
                    <div class="badge-item <?php echo $is_unlocked ? 'unlocked' : 'locked'; ?>"
                         title="<?php echo htmlspecialchars($badge['badge_description']); ?>">
                        <div class="badge-tier" style="background:<?php echo $tier_colors['platinum']['bg']; ?>;color:#333">
                            Platinum
                        </div>
                        <div class="badge-icon"><?php echo $badge['badge_icon']; ?></div>
                        <div class="badge-name"><?php echo $badge['badge_name']; ?></div>
                        <div class="badge-xp">+<?php echo $badge['xp_reward']; ?> XP</div>
                        <?php if (!$is_unlocked): ?>
                            <div style="font-size:.75rem;color:var(--text-tertiary);margin-top:.5rem">
                                🔒 Locked
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</body>
</html>
