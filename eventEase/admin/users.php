<?php
require_once '../config.php';

// Check if user is logged in and is admin
if (!isLoggedIn()) {
    redirect('../login.php');
}

if (!isAdmin()) {
    setFlashMessage('danger', 'Access denied. Admin privileges required.');
    redirect('../user/dashboard.php');
}

$pdo = getDBConnection();

// Handle user actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $user_id = (int)($_POST['user_id'] ?? 0);
    
    if ($user_id > 0 && $user_id !== $_SESSION['user_id']) { // Prevent admin from deleting themselves
        switch ($action) {
            case 'delete':
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role != 'admin'");
                if ($stmt->execute([$user_id])) {
                    setFlashMessage('success', 'User deleted successfully!');
                } else {
                    setFlashMessage('danger', 'Failed to delete user.');
                }
                break;
                
            case 'change_role':
                $new_role = $_POST['new_role'] ?? '';
                if (in_array($new_role, ['student', 'teacher'])) {
                    $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ? AND role != 'admin'");
                    if ($stmt->execute([$new_role, $user_id])) {
                        setFlashMessage('success', 'User role updated successfully!');
                    } else {
                        setFlashMessage('danger', 'Failed to update user role.');
                    }
                }
                break;
        }
    }
    
    redirect('users.php');
}

// Get filter parameters
$filter = $_GET['filter'] ?? '';
$search = $_GET['search'] ?? '';

// Build query
$where_conditions = [];
$params = [];

if ($filter === 'students') {
    $where_conditions[] = "role = 'student'";
} elseif ($filter === 'teachers') {
    $where_conditions[] = "role = 'teacher'";
} elseif ($filter === 'admins') {
    $where_conditions[] = "role = 'admin'";
}

if (!empty($search)) {
    $where_conditions[] = "(username LIKE ? OR email LIKE ? OR full_name LIKE ? OR department LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get users with event counts
$query = "
    SELECT u.*, 
           (SELECT COUNT(*) FROM events WHERE created_by = u.id) as events_created,
           (SELECT COUNT(*) FROM rsvps WHERE user_id = u.id AND status = 'attending') as events_attended
    FROM users u 
    $where_clause
    ORDER BY u.created_at DESC
";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$users = $stmt->fetchAll();

// Get user statistics
$stmt = $pdo->query("SELECT role, COUNT(*) as count FROM users GROUP BY role");
$userStats = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        :root {
            --primary-bg: #4f46e5;
            --secondary-bg: #f4f7fc;
            --accent-color: #38bdf8;
            --text-color: #333;
            --card-radius: 12px;
            --transition: all 0.3s ease-in-out;
        }

        body {
            font-family: 'Segoe UI', sans-serif;
            background-color: var(--secondary-bg);
            margin: 0;
            padding: 0;
            color: var(--text-color);
        }

        .header {
            background-color: white;
            padding: 1rem 0;
            color: #fff;
        }

        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 2rem;
        }

        .logo {
            font-size: 1.6rem;
            color: black;
            font-weight: bold;
        }

        .nav-links {
            display: flex;
            gap: 1.5rem;
            list-style: none;
            margin: 0;
            padding: 0;
        }

        .nav-links a {
            color: black;
            text-decoration: none;
            font-weight: 500;
            padding: 0.5rem;
            transition: color 0.3s;
        }

        .nav-links a:hover {
            color: #f3f4f6;
        }

        .container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 1rem;
        }

        .card {
            background: #fff;
            padding: 1.5rem;
            border-radius: var(--card-radius);
            box-shadow: 0 2px 6px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }

        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
        }

        .dashboard-card {
            background: #fff;
            padding: 1.5rem;
            border-radius: var(--card-radius);
            box-shadow: 0 2px 6px rgba(0,0,0,0.1);
            text-align: center;
        }

        .dashboard-card h3 {
            font-size: 1.2rem;
            margin-bottom: 0.75rem;
        }

        .dashboard-card .number {
            font-size: 2rem;
            font-weight: bold;
            margin: 0.5rem 0;
            color: var(--primary-bg);
        }

        .btn {
            padding: 0.75rem 1.5rem;
            background-color: var(--primary-bg);
            color: #fff;
            font-size: 1rem;
            text-decoration: none;
            border-radius: var(--card-radius);
            display: inline-block;
            transition: background-color 0.3s;
        }

        .btn:hover {
            background-color: #4338ca;
        }

        .form-control {
            padding: 0.75rem;
            border-radius: 8px;
            width: 100%;
            border: 1px solid #ddd;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .event-card {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: var(--card-radius);
            padding: 1.5rem;
            box-shadow: 0 2px 6px rgba(0,0,0,0.1);
        }

        .event-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 1rem;
        }

        .event-title {
            font-size: 1.2rem;
            font-weight: bold;
        }

        .badge {
            padding: 0.4rem 0.75rem;
            border-radius: 20px;
            font-size: 0.85rem;
            text-transform: capitalize;
        }

        .badge-approved {
            background-color: #c6f6d5;
            color: #065f46;
        }

        .badge-pending {
            background-color: #fefcbf;
            color: #92400e;
        }

        .badge-declined {
            background-color: #fecaca;
            color: #991b1b;
        }

        .event-meta {
            font-size: 0.85rem;
            margin-top: 0.5rem;
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .event-actions {
            margin-top: 1rem;
        }

        .footer {
            background-color: #f9fafb;
            text-align: center;
            padding: 1rem 0;
            font-size: 0.9rem;
            color: #777;
        }

        @media (max-width: 768px) {
            .navbar {
                flex-direction: column;
                align-items: flex-start;
                padding: 1rem;
            }

            .nav-links {
                flex-direction: column;
                margin-top: 1rem;
            }

            .container {
                padding: 1rem;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <nav class="navbar">
            <div class="logo"><?php echo SITE_NAME; ?> - Admin</div>
            <ul class="nav-links">
                <li><a href="../index.php">Home</a></li>
                <li><a href="dashboard.php">Dashboard</a></li>
                <li><a href="events.php">Manage Events</a></li>
                <li><a href="event_history.php">Event History</a></li>
                <li><a href="users.php">Manage Users</a></li>
                <li><a href="../logout.php">Logout</a></li>
            </ul>
        </nav>
    </header>

    <!-- Main Content -->
    <main>
        <div class="container">
            <h1 style="margin-bottom: 2rem; color: #667eea;">Manage Users</h1>

            <!-- Filters and Search -->
            <div class="card">
                <form method="GET" style="display: flex; gap: 1rem; align-items: end; flex-wrap: wrap;">
                    <div class="form-group" style="flex: 1; min-width: 200px;">
                        <label for="search">Search Users</label>
                        <input type="text" id="search" name="search" class="form-control" 
                               value="<?php echo sanitize($search); ?>" 
                               placeholder="Search by username, email, name, or department...">
                    </div>
                    <div class="form-group">
                        <label for="filter">Filter by Role</label>
                        <select id="filter" name="filter" class="form-control">
                            <option value="">All Users</option>
                            <option value="students" <?php echo $filter === 'students' ? 'selected' : ''; ?>>Students</option>
                            <option value="teachers" <?php echo $filter === 'teachers' ? 'selected' : ''; ?>>Teachers</option>
                            <option value="admins" <?php echo $filter === 'admins' ? 'selected' : ''; ?>>Admins</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">Filter</button>
                        <a href="users.php" class="btn btn-secondary">Clear</a>
                    </div>
                </form>
            </div>

            <!-- Users List -->
            <div class="card">
                <h3>Users (<?php echo count($users); ?> found)</h3>
                
                <?php if (empty($users)): ?>
                    <p>No users found matching your criteria.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Username</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Department</th>
                                    <th>Events Created</th>
                                    <th>Events Attended</th>
                                    <th>Joined</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td><?php echo sanitize($user['full_name']); ?></td>
                                        <td><?php echo sanitize($user['username']); ?></td>
                                        <td><?php echo sanitize($user['email']); ?></td>
                                        <td>
                                            <span class="badge badge-<?php echo $user['role']; ?>">
                                                <?php echo ucfirst($user['role']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo sanitize($user['department'] ?: '-'); ?></td>
                                        <td><?php echo $user['events_created']; ?></td>
                                        <td><?php echo $user['events_attended']; ?></td>
                                        <td><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                                        <td>
                                            <?php if ($user['id'] !== $_SESSION['user_id'] && $user['role'] !== 'admin'): ?>
                                                <div style="display: flex; gap: 0.5rem;">
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="action" value="change_role">
                                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                        <select name="new_role" class="form-control" style="padding: 0.25rem; font-size: 0.875rem;" onchange="this.form.submit()">
                                                            <option value="student" <?php echo $user['role'] === 'student' ? 'selected' : ''; ?>>Student</option>
                                                            <option value="teacher" <?php echo $user['role'] === 'teacher' ? 'selected' : ''; ?>>Teacher</option>
                                                        </select>
                                                    </form>
                                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this user? This action cannot be undone.')">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                        <button type="submit" class="btn btn-danger" style="padding: 0.25rem 0.5rem; font-size: 0.875rem;">Delete</button>
                                                    </form>
                                                </div>
                                            <?php else: ?>
                                                <span style="color: #666; font-size: 0.875rem;">-</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. All rights reserved.</p>
        </div>
    </footer>

    <script src="../assets/js/main.js"></script>
</body>
</html>
