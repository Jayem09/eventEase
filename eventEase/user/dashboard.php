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

// Debug: Check current user role
// echo "Current user role: " . $_SESSION['role'] . "<br>";
// echo "User ID: " . $_SESSION['user_id'] . "<br>";

$pdo = getDBConnection();

// Get user's events
$stmt = $pdo->prepare("
    SELECT e.*, 
           (SELECT COUNT(*) FROM rsvps WHERE event_id = e.id AND status = 'attending') as rsvp_count
    FROM events e 
    WHERE e.created_by = ? 
    ORDER BY e.created_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$myEvents = $stmt->fetchAll();

// Get events user has RSVP'd to
$stmt = $pdo->prepare("
    SELECT e.*, r.status as rsvp_status, u.full_name as creator_name
    FROM events e 
    JOIN rsvps r ON e.id = r.event_id
    JOIN users u ON e.created_by = u.id
    WHERE r.user_id = ? AND e.status = 'approved'
    ORDER BY e.event_date ASC
");
$stmt->execute([$_SESSION['user_id']]);
$rsvpEvents = $stmt->fetchAll();

// Get statistics
$stmt = $pdo->prepare("SELECT COUNT(*) as total_created FROM events WHERE created_by = ?");
$stmt->execute([$_SESSION['user_id']]);
$totalCreated = $stmt->fetch()['total_created'];

$stmt = $pdo->prepare("SELECT COUNT(*) as total_rsvps FROM rsvps WHERE user_id = ? AND status = 'attending'");
$stmt->execute([$_SESSION['user_id']]);
$totalRSVPs = $stmt->fetch()['total_rsvps'];

$stmt = $pdo->prepare("SELECT COUNT(*) as pending_events FROM events WHERE created_by = ? AND status = 'pending'");
$stmt->execute([$_SESSION['user_id']]);
$pendingEvents = $stmt->fetch()['pending_events'];

// Get upcoming events (all statuses) for RSVP
$stmt = $pdo->prepare("
    SELECT e.*, u.full_name as creator_name,
           (SELECT COUNT(*) FROM rsvps WHERE event_id = e.id AND status = 'attending') as rsvp_count,
           (SELECT COUNT(*) FROM rsvps WHERE event_id = e.id AND user_id = ?) as has_rsvp
    FROM events e 
    JOIN users u ON e.created_by = u.id
    WHERE e.event_date >= CURDATE() AND e.created_by != ?
    ORDER BY e.event_date ASC
    LIMIT 6
");
$stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
$upcomingEvents = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Dashboard - <?php echo SITE_NAME; ?></title>
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
            <h1 style="margin-bottom: 2rem; color: #667eea;">My Dashboard</h1>
            
            <!-- Welcome Message -->
            <div class="card">
                <h2>Welcome, <?php echo sanitize($_SESSION['full_name']); ?>!</h2>
                <p>You are logged in as a <strong><?php echo ucfirst($_SESSION['role']); ?></strong>. 
                   Create events, RSVP to others, and manage your activities.</p>
            </div>

            <!-- Statistics -->
            <div class="dashboard-grid">
                <div class="dashboard-card">
                    <h3>Events Created</h3>
                    <div class="number"><?php echo $totalCreated; ?></div>
                    <p>Your events</p>
                </div>
                <div class="dashboard-card">
                    <h3>Events Attending</h3>
                    <div class="number"><?php echo $totalRSVPs; ?></div>
                    <p>RSVP'd events</p>
                </div>
                <div class="dashboard-card">
                    <h3>Pending Events</h3>
                    <div class="number" style="color: #ffc107;"><?php echo $pendingEvents; ?></div>
                    <p>Awaiting approval</p>
                </div>
                <div class="dashboard-card">
                    <h3>Quick Actions</h3>
                    <div style="margin-top: 1rem;">
                        <a href="#create-event" class="btn btn-primary" style="margin-bottom: 0.5rem; display: block;">Create New Event</a>
                        <a href="rsvp.php" class="btn btn-secondary" style="margin-bottom: 0.5rem; display: block;">Browse Events</a>
                        <a href="event_history.php" class="btn btn-info" style="display: block;">Event History</a>
                    </div>
                </div>
            </div>

            <!-- Create Event Form -->
            <div class="card" id="create-event">
                <h3>Create New Event</h3>
                <form method="POST" action="create_event.php" data-validate>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label for="title">Event Title *</label>
                            <input type="text" id="title" name="title" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="location">Location *</label>
                            <input type="text" id="location" name="location" class="form-control" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="description">Description *</label>
                        <textarea id="description" name="description" class="form-control" rows="4" required></textarea>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label for="event_date">Event Date *</label>
                            <input type="date" id="event_date" name="event_date" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="event_time">Event Time *</label>
                            <input type="time" id="event_time" name="event_time" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="max_capacity">Max Capacity *</label>
                            <input type="number" id="max_capacity" name="max_capacity" class="form-control" min="1" required>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Create Event</button>
                </form>
            </div>

            <!-- My Events -->
            <div class="card">
                <h3>My Events</h3>
                <?php if (empty($myEvents)): ?>
                    <p>You haven't created any events yet. Create your first event above!</p>
                <?php else: ?>
                    <div class="event-grid">
                        <?php foreach ($myEvents as $event): ?>
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
                                        <span>📊 RSVPs: <?php echo $event['rsvp_count']; ?></span>
                                        <span>📅 Created: <?php echo date('M j, Y', strtotime($event['created_at'])); ?></span>
                                    </div>
                                    <div class="event-actions">
                                        <a href="view_attendees.php?event_id=<?php echo $event['id']; ?>" class="btn btn-primary">
                                            👥 View Attendees (<?php echo $event['rsvp_count']; ?>)
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Events I'm Attending -->
            <div class="card">
                <h3>Events I'm Attending</h3>
                <?php if (empty($rsvpEvents)): ?>
                    <p>You haven't RSVP'd to any events yet. <a href="rsvp.php">Browse available events</a>!</p>
                <?php else: ?>
                    <div class="event-grid">
                        <?php foreach ($rsvpEvents as $event): ?>
                            <div class="event-card">
                                <div class="event-header">
                                    <h3 class="event-title"><?php echo sanitize($event['title']); ?></h3>
                                    <span class="badge badge-attending"><?php echo ucfirst($event['rsvp_status']); ?></span>
                                </div>
                                <div class="event-body">
                                    <p class="event-description"><?php echo sanitize($event['description']); ?></p>
                                    <div class="event-meta">
                                        <span>📅 <?php echo date('M j, Y', strtotime($event['event_date'])); ?></span>
                                        <span>🕒 <?php echo date('g:i A', strtotime($event['event_time'])); ?></span>
                                    </div>
                                    <div class="event-meta">
                                        <span>📍 <?php echo sanitize($event['location']); ?></span>
                                        <span>👤 By: <?php echo sanitize($event['creator_name']); ?></span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Upcoming Events -->
            <div class="card">
                <h3>Upcoming Events</h3>
                <?php if (empty($upcomingEvents)): ?>
                    <p>No upcoming events available.</p>
                <?php else: ?>
                    <div style="margin-bottom: 1rem; padding: 1rem; background-color: #e3f2fd; border-radius: 5px; border-left: 4px solid #2196f3;">
                        <strong>📋 Event Status Guide:</strong><br>
                        • <span class="badge badge-pending">Pending</span> - Event awaiting admin approval (you can still apply)<br>
                        • <span class="badge badge-approved">Approved</span> - Event confirmed and ready<br>
                        • <span class="badge badge-declined">Declined</span> - Event not approved by admin
                    </div>
                    <div class="event-grid">
                        <?php foreach ($upcomingEvents as $event): ?>
                                                    <div class="event-card">
                            <div class="event-header">
                                <h3 class="event-title"><?php echo sanitize($event['title']); ?></h3>
                                <span class="badge badge-<?php echo $event['status']; ?>"><?php echo ucfirst($event['status']); ?></span>
                            </div>
                                <div class="event-body">
                                    <p class="event-description"><?php echo sanitize(substr($event['description'], 0, 100)) . '...'; ?></p>
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
                                    
                                    <div class="event-actions">
                                        <?php if ($event['status'] === 'declined'): ?>
                                            <span class="btn btn-danger" style="cursor: default;">Event Declined</span>
                                        <?php elseif ($event['has_rsvp'] > 0): ?>
                                            <span class="btn btn-success" style="cursor: default;">Already Applied</span>
                                        <?php else: ?>
                                            <a href="rsvp.php?event_id=<?php echo $event['id']; ?>" class="btn btn-primary">
                                                <?php echo $event['status'] === 'pending' ? 'Apply (Pending)' : 'RSVP'; ?>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div style="text-align: center; margin-top: 2rem;">
                        <a href="rsvp.php" class="btn btn-secondary">View All Events</a>
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
