<?php
require_once 'config.php';

if (!isLoggedIn()) {
    redirect('index.php');
}

$user_id = getCurrentUserId();
$message = '';

// Handle add category
if (isset($_POST['add_category'])) {
    $name = trim($_POST['category_name']);
    $icon = trim($_POST['category_icon']);
    $color = trim($_POST['category_color']);
    
    if (!empty($name)) {
        $stmt = $conn->prepare("INSERT INTO categories (user_id, category_name, category_icon, category_color) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isss", $user_id, $name, $icon, $color);
        if ($stmt->execute()) {
            $message = 'Category added successfully!';
        }
    }
}

// Handle delete category
if (isset($_GET['delete'])) {
    $cat_id = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM categories WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $cat_id, $user_id);
    $stmt->execute();
    $message = 'Category deleted!';
}

// Get all categories
$query = "SELECT * FROM categories WHERE user_id = ? ORDER BY category_name";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$categories = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Categories</title>
    <link rel="stylesheet" href="dashboard.css">
    <style>
        .categories-container {
            max-width: 900px;
            margin: 0 auto;
            padding: 30px 20px;
        }
        .category-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .category-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .category-icon-display {
            font-size: 2rem;
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
        }
        .add-form {
            background: white;
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .form-row {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr;
            gap: 15px;
            margin-bottom: 15px;
        }
        input[type="text"], input[type="color"] {
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1rem;
        }
        .btn-delete {
            padding: 8px 16px;
            background: #ff6b6b;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
        }
        .btn-delete:hover {
            background: #ff5252;
        }
        .btn-back {
            display: inline-block;
            padding: 10px 20px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .message {
            background: #e8f5e9;
            color: #2e7d32;
            padding: 12px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <div class="nav-brand"><h2>🎯 Manage Categories</h2></div>
            <div class="nav-user">
                <button onclick="window.location.href='dashboard.php'" class="btn-nav">← Back to Dashboard</button>
                <button onclick="window.location.href='logout.php'" class="btn-logout">Logout</button>
            </div>
        </div>
    </nav>

    <div class="categories-container">
        <?php if ($message): ?>
            <div class="message"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <div class="add-form">
            <h2>Add New Category</h2>
            <form method="POST">
                <div class="form-row">
                    <input type="text" name="category_name" placeholder="Category Name" required>
                    <input type="text" name="category_icon" placeholder="Icon (emoji)" value="📌" required>
                    <input type="color" name="category_color" value="#667eea" required>
                    <button type="submit" name="add_category" class="btn-submit">Add</button>
                </div>
            </form>
        </div>

        <h2 style="margin-bottom:20px;">Your Categories</h2>
        <?php while ($cat = $categories->fetch_assoc()): ?>
            <div class="category-card">
                <div class="category-info">
                    <div class="category-icon-display" style="background:<?php echo $cat['category_color']; ?>">
                        <?php echo $cat['category_icon']; ?>
                    </div>
                    <div>
                        <h3><?php echo htmlspecialchars($cat['category_name']); ?></h3>
                        <small style="color:#999;"><?php echo $cat['category_color']; ?></small>
                    </div>
                </div>
                <a href="?delete=<?php echo $cat['id']; ?>" class="btn-delete" onclick="return confirm('Delete this category? All tasks will also be deleted!')">
                    Delete
                </a>
            </div>
        <?php endwhile; ?>
    </div>
</body>
</html>
