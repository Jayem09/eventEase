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

// Handle AJAX actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $response = ['success' => false, 'message' => ''];
    
    $action = $_POST['action'];
    $event_id = (int)($_POST['event_id'] ?? 0);
    
    if ($event_id > 0) {
        switch ($action) {
            case 'approve':
                $stmt = $pdo->prepare("UPDATE events SET status = 'approved', approved_by = ? WHERE id = ?");
                if ($stmt->execute([$_SESSION['user_id'], $event_id])) {
                    $response = ['success' => true, 'message' => 'Event approved successfully!'];
                } else {
                    $response = ['success' => false, 'message' => 'Failed to approve event.'];
                }
                break;
                
            case 'decline':
                $stmt = $pdo->prepare("UPDATE events SET status = 'declined', approved_by = ? WHERE id = ?");
                if ($stmt->execute([$_SESSION['user_id'], $event_id])) {
                    $response = ['success' => true, 'message' => 'Event declined successfully!'];
                } else {
                    $response = ['success' => false, 'message' => 'Failed to decline event.'];
                }
                break;
                
            case 'delete':
                $stmt = $pdo->prepare("DELETE FROM events WHERE id = ?");
                if ($stmt->execute([$event_id])) {
                    $response = ['success' => true, 'message' => 'Event deleted successfully!'];
                } else {
                    $response = ['success' => false, 'message' => 'Failed to delete event.'];
                }
                break;
        }
    }
    
    // Always return JSON response for AJAX requests
    if (isset($_POST['ajax']) || isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    } else {
        // For non-AJAX requests, set flash message and redirect
        if ($response['success']) {
            setFlashMessage('success', $response['message']);
        } else {
            setFlashMessage('danger', $response['message']);
        }
        redirect('events.php');
    }
}

// Get filter parameters
$filter = $_GET['filter'] ?? '';
$search = $_GET['search'] ?? '';

// Build query
$where_conditions = [];
$params = [];

if ($filter === 'pending') {
    $where_conditions[] = "e.status = 'pending'";
} elseif ($filter === 'approved') {
    $where_conditions[] = "e.status = 'approved'";
} elseif ($filter === 'declined') {
    $where_conditions[] = "e.status = 'declined'";
}

if (!empty($search)) {
    $where_conditions[] = "(e.title LIKE ? OR e.description LIKE ? OR u.full_name LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get events
$query = "
    SELECT e.*, u.full_name as creator_name, u.email as creator_email,
           (SELECT COUNT(*) FROM rsvps WHERE event_id = e.id AND status = 'attending') as rsvp_count
    FROM events e 
    JOIN users u ON e.created_by = u.id 
    $where_clause
    ORDER BY e.created_at DESC
";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$events = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Events - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
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
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
                <h1 style="color: #667eea;">Manage Events</h1>
                <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
            </div>

            <!-- Filters and Search -->
            <div class="card">
                <form method="GET" style="display: flex; gap: 1rem; align-items: end; flex-wrap: wrap;">
                    <div class="form-group" style="flex: 1; min-width: 200px;">
                        <label for="search">Search Events</label>
                        <input type="text" id="search" name="search" class="form-control" 
                               value="<?php echo sanitize($search); ?>" 
                               placeholder="Search by title, description, or creator...">
                    </div>
                    <div class="form-group">
                        <label for="filter">Filter by Status</label>
                        <select id="filter" name="filter" class="form-control">
                            <option value="">All Events</option>
                            <option value="pending" <?php echo $filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="approved" <?php echo $filter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                            <option value="declined" <?php echo $filter === 'declined' ? 'selected' : ''; ?>>Declined</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">Filter</button>
                        <a href="events.php" class="btn btn-secondary">Clear</a>
                    </div>
                </form>
            </div>

            <!-- Events List -->
            <div class="card">
                <h3>Events (<?php echo count($events); ?> found)</h3>
                
                <?php if (empty($events)): ?>
                    <p>No events found matching your criteria.</p>
                <?php else: ?>
                    <div class="event-grid">
                        <?php foreach ($events as $event): ?>
                            <div class="event-card">
                                <div class="event-header">
                                    <h3 class="event-title"><?php echo sanitize($event['title']); ?></h3>
                                    <span class="badge badge-<?php echo $event['status']; ?>">
                                        <?php echo ucfirst($event['status']); ?>
                                    </span>
                                </div>
                                <div class="event-body">
                                    <p class="event-description"><?php echo sanitize($event['description']); ?></p>
                                    <div class="event-meta">
                                        <span>📅 <?php echo date('M j, Y', strtotime($event['event_date'])); ?></span>
                                        <span>🕒 <?php echo date('g:i A', strtotime($event['event_time'])); ?></span>
                                    </div>
                                    <div class="event-meta">
                                        <span>📍 <?php echo sanitize($event['location']); ?></span>
                                        <span>👥 <?php echo $event['current_capacity']; ?>/<?php echo $event['max_capacity']; ?></span>
                                    </div>
                                    <div class="event-meta">
                                        <span>👤 By: <?php echo sanitize($event['creator_name']); ?></span>
                                        <span>📧 <?php echo sanitize($event['creator_email']); ?></span>
                                    </div>
                                    <div class="event-meta">
                                        <span>📊 JOINs: <?php echo $event['rsvp_count']; ?></span>
                                        <span>📅 Created: <?php echo date('M j, Y', strtotime($event['created_at'])); ?></span>
                                    </div>
                                    
                                    <!-- Action Buttons -->
                                    <div class="event-actions">
                                        <?php if ($event['status'] === 'pending'): ?>
                                            <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to approve this event?')">
                                                <input type="hidden" name="action" value="approve">
                                                <input type="hidden" name="event_id" value="<?php echo $event['id']; ?>">
                                                <button type="submit" class="btn btn-success">Approve</button>
                                            </form>
                                            <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to decline this event?')">
                                                <input type="hidden" name="action" value="decline">
                                                <input type="hidden" name="event_id" value="<?php echo $event['id']; ?>">
                                                <button type="submit" class="btn btn-danger">Decline</button>
                                            </form>
                                        <?php endif; ?>
                                        
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this event? This action cannot be undone.')">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="event_id" value="<?php echo $event['id']; ?>">
                                            <button type="submit" class="btn btn-danger">Delete</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
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
