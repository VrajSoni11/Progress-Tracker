<?php
require_once 'config.php';

// If already logged in, redirect to dashboard
if (isLoggedIn()) {
    redirect('dashboard.php');
}

$error = '';
$success = '';

// Handle Login
if (isset($_POST['login'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    $stmt = $conn->prepare("SELECT id, username, password, full_name FROM users WHERE username = ? OR email = ?");
    $stmt->bind_param("ss", $username, $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            redirect('dashboard.php');
        } else {
            $error = 'Invalid password!';
        }
    } else {
        $error = 'User not found!';
    }
}

// Handle Registration
if (isset($_POST['register'])) {
    $full_name = trim($_POST['full_name']);
    $username = trim($_POST['reg_username']);
    $email = trim($_POST['email']);
    $password = $_POST['reg_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validation
    if (empty($full_name) || empty($username) || empty($email) || empty($password)) {
        $error = 'All fields are required!';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match!';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters!';
    } else {
        // Check if username or email exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->bind_param("ss", $username, $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $error = 'Username or email already exists!';
        } else {
            // Insert new user
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("INSERT INTO users (username, email, password, full_name) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("ssss", $username, $email, $hashed_password, $full_name);
            
            if ($stmt->execute()) {
                $user_id = $conn->insert_id;
                
                // Create default categories for new user
                $default_categories = [
                    ['Education', '📚', '#667eea'],
                    ['Gym', '💪', '#ff6b6b'],
                    ['Personal', '🎨', '#a8edea'],
                    ['Work', '💼', '#4ecdc4'],
                    ['Health', '🏥', '#95e1d3']
                ];
                
                $stmt = $conn->prepare("INSERT INTO categories (user_id, category_name, category_icon, category_color) VALUES (?, ?, ?, ?)");
                foreach ($default_categories as $cat) {
                    $stmt->bind_param("isss", $user_id, $cat[0], $cat[1], $cat[2]);
                    $stmt->execute();
                }
                
                $success = 'Registration successful! Please login.';
            } else {
                $error = 'Registration failed. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Progress Tracker - Login</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, #b8e3e9 0%, #93b1b5 50%, #4f7c82 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
            position: relative;
            overflow: hidden;
        }
        
        /* Animated background elements */
        body::before,
        body::after {
            content: '';
            position: absolute;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(60px);
        }
        
        body::before {
            width: 600px;
            height: 600px;
            top: -300px;
            right: -200px;
            animation: float 20s infinite ease-in-out;
        }
        
        body::after {
            width: 400px;
            height: 400px;
            bottom: -150px;
            left: -100px;
            animation: float 15s infinite ease-in-out reverse;
        }
        
        @keyframes float {
            0%, 100% { transform: translate(0, 0) rotate(0deg); }
            33% { transform: translate(30px, -30px) rotate(120deg); }
            66% { transform: translate(-20px, 20px) rotate(240deg); }
        }
        
        .auth-container {
            background: white;
            border-radius: 24px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            overflow: hidden;
            max-width: 1000px;
            width: 100%;
            display: grid;
            grid-template-columns: 1fr 1fr;
            position: relative;
            z-index: 1;
            animation: slideUp 0.6s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .auth-left {
            padding: 60px;
            background: linear-gradient(135deg, #4f7c82 0%, #0b2e33 100%);
            color: white;
            display: flex;
            flex-direction: column;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }
        
        .auth-left::before {
            content: '';
            position: absolute;
            inset: 0;
            background: url('data:image/svg+xml,<svg width="100" height="100" xmlns="http://www.w3.org/2000/svg"><circle cx="50" cy="50" r="1" fill="white" opacity="0.3"/></svg>');
            opacity: 0.1;
        }
        
        .auth-left h1 {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 20px;
            letter-spacing: -0.025em;
            position: relative;
        }
        
        .auth-left p {
            font-size: 1.125rem;
            opacity: 0.95;
            line-height: 1.7;
            font-weight: 400;
            position: relative;
        }
        
        .auth-right {
            padding: 60px;
            background: white;
        }
        
        .form-tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 40px;
            background: #f8fafc;
            padding: 6px;
            border-radius: 12px;
        }
        
        .tab-btn {
            flex: 1;
            padding: 12px;
            background: transparent;
            border: none;
            border-radius: 8px;
            font-size: 0.9375rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            color: #64748b;
        }
        
        .tab-btn.active {
            background: white;
            color: #4f7c82;
            box-shadow: 0 1px 3px 0 rgba(11, 46, 51, 0.1);
        }
        
        .tab-btn:hover:not(.active) {
            color: #334155;
        }
        
        .form-content {
            display: none;
            animation: fadeIn 0.3s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .form-content.active {
            display: block;
        }
        
        .form-group {
            margin-bottom: 24px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #0f172a;
            font-size: 0.875rem;
            letter-spacing: 0.025em;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px 16px;
            border: 1.5px solid #e2e8f0;
            border-radius: 10px;
            font-size: 0.9375rem;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            font-family: 'Inter', sans-serif;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #4f7c82;
            box-shadow: 0 0 0 4px rgba(79, 124, 130, 0.1);
        }
        
        .form-group input::placeholder {
            color: #cbd5e1;
        }
        
        .btn-submit {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #4f7c82 0%, #0b2e33 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 4px 6px -1px rgba(79, 124, 130, 0.3);
        }
        
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(79, 124, 130, 0.4);
        }
        
        .btn-submit:active {
            transform: translateY(0);
        }
        
        .alert {
            padding: 14px 18px;
            border-radius: 10px;
            margin-bottom: 24px;
            font-weight: 500;
            font-size: 0.875rem;
            animation: slideDown 0.3s ease;
        }
        
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .alert-error {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        
        .alert-success {
            background: #f0fdf4;
            color: #166534;
            border: 1px solid #bbf7d0;
        }
        
        @media (max-width: 968px) {
            .auth-container {
                grid-template-columns: 1fr;
            }
            
            .auth-left {
                padding: 40px 30px;
            }
            
            .auth-left h1 {
                font-size: 2rem;
            }
            
            .auth-right {
                padding: 40px 30px;
            }
        }
        
        @media (max-width: 480px) {
            body {
                padding: 10px;
            }
            
            .auth-left {
                padding: 30px 20px;
            }
            
            .auth-left h1 {
                font-size: 1.75rem;
            }
            
            .auth-right {
                padding: 30px 20px;
            }
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="auth-left">
            <h1>🎯 Progress Tracker</h1>
            <p>Plan your week, track your goals, and measure your success. Stay organized and achieve more every single day with our intelligent progress tracking system.</p>
        </div>
        
        <div class="auth-right">
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <div class="form-tabs">
                <button class="tab-btn active" onclick="showTab('login')">Sign In</button>
                <button class="tab-btn" onclick="showTab('register')">Create Account</button>
            </div>
            
            <!-- Login Form -->
            <div id="login-form" class="form-content active">
                <form method="POST">
                    <div class="form-group">
                        <label>Username or Email</label>
                        <input type="text" name="username" placeholder="Enter your username or email" required>
                    </div>
                    <div class="form-group">
                        <label>Password</label>
                        <input type="password" name="password" placeholder="Enter your password" required>
                    </div>
                    <button type="submit" name="login" class="btn-submit">Sign In</button>
                </form>
            </div>
            
            <!-- Register Form -->
            <div id="register-form" class="form-content">
                <form method="POST">
                    <div class="form-group">
                        <label>Full Name</label>
                        <input type="text" name="full_name" placeholder="John Doe" required>
                    </div>
                    <div class="form-group">
                        <label>Username</label>
                        <input type="text" name="reg_username" placeholder="Choose a username" required>
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" placeholder="you@example.com" required>
                    </div>
                    <div class="form-group">
                        <label>Password</label>
                        <input type="password" name="reg_password" placeholder="At least 6 characters" required minlength="6">
                    </div>
                    <div class="form-group">
                        <label>Confirm Password</label>
                        <input type="password" name="confirm_password" placeholder="Re-enter password" required minlength="6">
                    </div>
                    <button type="submit" name="register" class="btn-submit">Create Account</button>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        function showTab(tab) {
            // Update buttons
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
            event.target.classList.add('active');
            
            // Update forms
            document.querySelectorAll('.form-content').forEach(form => form.classList.remove('active'));
            document.getElementById(tab + '-form').classList.add('active');
        }
    </script>
</body>
</html>
