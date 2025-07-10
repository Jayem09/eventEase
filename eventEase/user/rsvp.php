<?php
require_once '../config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    redirect('../login.php');
}

// Redirect admin to admin dashboard
if (isAdmin()) {
    redirect('../admin/dashboard.php');
}

$pdo = getDBConnection();

// Handle RSVP actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $event_id = (int)($_POST['event_id'] ?? 0);
    
    if ($event_id > 0) {
        // Check if event exists (any status)
        $stmt = $pdo->prepare("SELECT id, max_capacity, current_capacity, status FROM events WHERE id = ?");
        $stmt->execute([$event_id]);
        $event = $stmt->fetch();
        
        if ($event) {
            // Check if user already has an RSVP
            $stmt = $pdo->prepare("SELECT id, status FROM rsvps WHERE event_id = ? AND user_id = ?");
            $stmt->execute([$event_id, $_SESSION['user_id']]);
            $existing_rsvp = $stmt->fetch();
            
            if ($action === 'attending') {
                // Check capacity
                if ($event['current_capacity'] >= $event['max_capacity']) {
                    setFlashMessage('danger', 'Sorry, this event is at full capacity.');
                } else {
                    if ($existing_rsvp) {
                        // Update existing RSVP
                        $stmt = $pdo->prepare("UPDATE rsvps SET status = 'attending' WHERE id = ?");
                        $stmt->execute([$existing_rsvp['id']]);
                    } else {
                        // Create new RSVP
                        $stmt = $pdo->prepare("INSERT INTO rsvps (event_id, user_id, status) VALUES (?, ?, 'attending')");
                        $stmt->execute([$event_id, $_SESSION['user_id']]);
                        
                        // Update event capacity
                        $stmt = $pdo->prepare("UPDATE events SET current_capacity = current_capacity + 1 WHERE id = ?");
                        $stmt->execute([$event_id]);
                    }
                    setFlashMessage('success', 'RSVP successful! You are now attending this event.');
                }
            } elseif ($action === 'not_attending') {
                if ($existing_rsvp) {
                    // Update RSVP status
                    $stmt = $pdo->prepare("UPDATE rsvps SET status = 'not_attending' WHERE id = ?");
                    $stmt->execute([$existing_rsvp['id']]);
                    
                    // If was attending, decrease capacity
                    if ($existing_rsvp['status'] === 'attending') {
                        $stmt = $pdo->prepare("UPDATE events SET current_capacity = current_capacity - 1 WHERE id = ?");
                        $stmt->execute([$event_id]);
                    }
                    setFlashMessage('success', 'RSVP updated successfully.');
                }
            }
        } else {
            setFlashMessage('danger', 'Event not found.');
        }
    }
    
    redirect('rsvp.php');
}

// Get filter parameters
$search = $_GET['search'] ?? '';
$event_id = (int)($_GET['event_id'] ?? 0);

// Build query for all events (including pending)
$where_conditions = ["e.event_date >= CURDATE()"];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(e.title LIKE ? OR e.description LIKE ? OR u.full_name LIKE ? OR e.location LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if ($event_id > 0) {
    $where_conditions[] = "e.id = ?";
    $params[] = $event_id;
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

// Get events
$query = "
    SELECT e.*, u.full_name as creator_name,
           (SELECT COUNT(*) FROM rsvps WHERE event_id = e.id AND status = 'attending') as rsvp_count,
           (SELECT status FROM rsvps WHERE event_id = e.id AND user_id = ?) as user_rsvp_status
    FROM events e 
    JOIN users u ON e.created_by = u.id 
    $where_clause
    ORDER BY e.event_date ASC
";

$stmt = $pdo->prepare($query);
$stmt->execute(array_merge([$_SESSION['user_id']], $params));
$events = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Browse Events - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <!-- Header -->
    <header class="header">
        <nav class="navbar">
            <div class="logo"><?php echo SITE_NAME; ?></div>
            <ul class="nav-links">
                <li><a href="../index.php">Home</a></li>
                <li><a href="dashboard.php">My Dashboard</a></li>
                <li><a href="rsvp.php">Browse Events</a></li>
                <li><a href="event_history.php">Event History</a></li>
                <li><a href="../logout.php">Logout</a></li>
            </ul>
        </nav>
    </header>

    <!-- Main Content -->
    <main>
        <div class="container">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
                <h1 style="color: #667eea;">Browse Events</h1>
                <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
            </div>

            <!-- Flash Messages -->
            <?php $flash = getFlashMessage(); ?>
            <?php if ($flash): ?>
                <div class="alert alert-<?php echo $flash['type']; ?>">
                    <?php echo $flash['message']; ?>
                </div>
            <?php endif; ?>

            <!-- Search -->
            <div class="card">
                <form method="GET" style="display: flex; gap: 1rem; align-items: end;">
                    <div class="form-group" style="flex: 1;">
                        <label for="search">Search Events</label>
                        <input type="text" id="search" name="search" class="form-control" 
                               value="<?php echo sanitize($search); ?>" 
                               placeholder="Search by title, description, creator, or location...">
                    </div>
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">Search</button>
                        <a href="rsvp.php" class="btn btn-secondary">Clear</a>
                    </div>
                </form>
            </div>

            <!-- Events List -->
            <div class="card">
                <h3>All Events (<?php echo count($events); ?> found)</h3>
                
                <?php if (empty($events)): ?>
                    <p>No events found matching your criteria.</p>
                <?php else: ?>
                    <div style="margin-bottom: 1rem; padding: 1rem; background-color: #e3f2fd; border-radius: 5px; border-left: 4px solid #2196f3;">
                        <strong>📋 Event Status Guide:</strong><br>
                        • <span class="badge badge-pending">Pending</span> - Event awaiting admin approval (you can still apply)<br>
                        • <span class="badge badge-approved">Approved</span> - Event confirmed and ready<br>
                        • <span class="badge badge-declined">Declined</span> - Event not approved by admin
                    </div>
                    <div class="event-grid">
                        <?php foreach ($events as $event): ?>
                            <div class="event-card">
                                <div class="event-header">
                                    <h3 class="event-title"><?php echo sanitize($event['title']); ?></h3>
                                    <span class="badge badge-<?php echo $event['status']; ?>"><?php echo ucfirst($event['status']); ?></span>
                                </div>
                                <div class="event-body">
                                    <p class="event-description"><?php echo sanitize($event['description']); ?></p>
                                    <div class="event-meta">
                                        <span>📅 <?php echo date('M j, Y', strtotime($event['event_date'])); ?></span>
                                        <span>🕒 <?php echo date('g:i A', strtotime($event['event_time'])); ?></span>
                                    </div>
                                    <div class="event-meta">
                                        <span>📍 <?php echo sanitize($event['location']); ?></span>
                                        <span>👥 <?php echo $event['rsvp_count']; ?>/<?php echo $event['max_capacity']; ?></span>
                                    </div>
                                    <div class="event-meta">
                                        <span>👤 By: <?php echo sanitize($event['creator_name']); ?></span>
                                    </div>
                                    
                                    <!-- RSVP Buttons -->
                                    <div class="event-actions">
                                        <?php if ($event['status'] === 'declined'): ?>
                                            <span class="btn btn-danger" style="cursor: default;">Event Declined</span>
                                        <?php elseif ($event['user_rsvp_status'] === 'attending'): ?>
                                            <span class="btn btn-success" style="cursor: default;">✓ Attending</span>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="not_attending">
                                                <input type="hidden" name="event_id" value="<?php echo $event['id']; ?>">
                                                <button type="submit" class="btn btn-secondary">Cancel RSVP</button>
                                            </form>
                                        <?php elseif ($event['user_rsvp_status'] === 'not_attending'): ?>
                                            <span class="btn btn-warning" style="cursor: default;">Not Attending</span>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="attending">
                                                <input type="hidden" name="event_id" value="<?php echo $event['id']; ?>">
                                                <button type="submit" class="btn btn-primary">RSVP Now</button>
                                            </form>
                                        <?php else: ?>
                                            <?php if ($event['rsvp_count'] >= $event['max_capacity']): ?>
                                                <span class="btn btn-danger" style="cursor: default;">Full Capacity</span>
                                            <?php else: ?>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="action" value="attending">
                                                    <input type="hidden" name="event_id" value="<?php echo $event['id']; ?>">
                                                    <button type="submit" class="btn btn-primary">
                                                        <?php echo $event['status'] === 'pending' ? 'Apply (Pending)' : 'RSVP'; ?>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        <?php endif; ?>
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
