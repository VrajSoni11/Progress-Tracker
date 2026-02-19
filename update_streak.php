<?php
// Streak Management Functions
// Do NOT call this file directly - include it only

function updateStreak($user_id) {
    global $conn;
    
    if (!$user_id) {
        return 0; // Safety check
    }
    
    $today = date('Y-m-d');
    
    // Check if gamification table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'user_gamification'");
    if ($table_check->num_rows === 0) {
        // Table doesn't exist yet - gamification not set up
        return 0;
    }
    
    // Get user gamification stats
    $stmt = $conn->prepare("SELECT * FROM user_gamification WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stats = $stmt->get_result()->fetch_assoc();
    
    if (!$stats) {
        // Initialize gamification for new user
        $stmt = $conn->prepare("INSERT INTO user_gamification (user_id, last_login_date, current_streak) VALUES (?, ?, 1)");
        $stmt->bind_param("is", $user_id, $today);
        $stmt->execute();
        return 1;
    }
    
    $last_login = $stats['last_login_date'];
    $current_streak = $stats['current_streak'];
    
    if ($last_login === $today) {
        return $current_streak; // Already logged in today
    }
    
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    
    if ($last_login === $yesterday) {
        // Continue streak
        $current_streak++;
    } else {
        // Streak broken
        $current_streak = 1;
    }
    
    $longest_streak = max($current_streak, $stats['longest_streak']);
    
    // Update streak
    $stmt = $conn->prepare("UPDATE user_gamification SET current_streak = ?, longest_streak = ?, last_login_date = ? WHERE user_id = ?");
    $stmt->bind_param("iisi", $current_streak, $longest_streak, $today, $user_id);
    $stmt->execute();
    
    // Check for streak badges (only if badges table exists)
    $badge_check = $conn->query("SHOW TABLES LIKE 'badges'");
    if ($badge_check->num_rows > 0) {
        checkStreakBadges($user_id, $current_streak);
    }
    
    return $current_streak;
}

function checkStreakBadges($user_id, $current_streak) {
    global $conn;
    
    // Check streak badges (badge IDs from gamification.sql)
    $streak_badges = [
        3 => 6,   // 3-Day Streak
        7 => 7,   // Week Warrior
        30 => 8   // Month Master
    ];
    
    foreach ($streak_badges as $days => $badge_id) {
        if ($current_streak >= $days) {
            unlockBadge($user_id, $badge_id);
        }
    }
}

function unlockBadge($user_id, $badge_id) {
    global $conn;
    
    // Check if user_badges table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'user_badges'");
    if ($table_check->num_rows === 0) {
        return false;
    }
    
    // Check if already unlocked
    $stmt = $conn->prepare("SELECT id FROM user_badges WHERE user_id = ? AND badge_id = ?");
    $stmt->bind_param("ii", $user_id, $badge_id);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        return false; // Already unlocked
    }
    
    // Unlock badge
    $stmt = $conn->prepare("INSERT INTO user_badges (user_id, badge_id) VALUES (?, ?)");
    $stmt->bind_param("ii", $user_id, $badge_id);
    $stmt->execute();
    
    // Award XP
    $stmt = $conn->prepare("SELECT xp_reward FROM badges WHERE id = ?");
    $stmt->bind_param("i", $badge_id);
    $stmt->execute();
    $badge = $stmt->get_result()->fetch_assoc();
    
    if ($badge) {
        $xp = $badge['xp_reward'];
        $stmt = $conn->prepare("UPDATE user_gamification SET total_xp = total_xp + ? WHERE user_id = ?");
        $stmt->bind_param("ii", $xp, $user_id);
        $stmt->execute();
    }
    
    return true;
}

function getUserStreak($user_id) {
    global $conn;
    
    if (!$user_id) return 0;
    
    // Check if table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'user_gamification'");
    if ($table_check->num_rows === 0) {
        return 0;
    }
    
    $stmt = $conn->prepare("SELECT current_streak FROM user_gamification WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    return $result ? $result['current_streak'] : 0;
}
?>
