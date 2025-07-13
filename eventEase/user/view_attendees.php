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

// Get event ID from URL
$event_id = (int)($_GET['event_id'] ?? 0);

if ($event_id <= 0) {
    setFlashMessage('danger', 'Invalid event ID.');
    redirect('dashboard.php');
}

// Check if the event exists and belongs to the current user
$stmt = $pdo->prepare("
    SELECT e.*, u.full_name as creator_name 
    FROM events e 
    JOIN users u ON e.created_by = u.id 
    WHERE e.id = ? AND e.created_by = ?
");
$stmt->execute([$event_id, $_SESSION['user_id']]);
$event = $stmt->fetch();

if (!$event) {
    setFlashMessage('danger', 'Event not found or you do not have permission to view it.');
    redirect('dashboard.php');
}

// Get attendees for this event
$stmt = $pdo->prepare("
    SELECT r.*, u.full_name, u.email, u.role, u.department
    FROM rsvps r
    JOIN users u ON r.user_id = u.id
    WHERE r.event_id = ?
    ORDER BY r.created_at ASC
");
$stmt->execute([$event_id]);
$attendees = $stmt->fetchAll();

// Get statistics
$total_attendees = count($attendees);
$attending_count = 0;
$not_attending_count = 0;

foreach ($attendees as $attendee) {
    if ($attendee['status'] === 'attending') {
        $attending_count++;
    } elseif ($attendee['status'] === 'not_attending') {
        $not_attending_count++;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Attendees - <?php echo SITE_NAME; ?></title>
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
                <h1 style="color: #667eea;">Event Attendees</h1>
                <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
            </div>

            <!-- Flash Messages -->
            <?php $flash = getFlashMessage(); ?>
            <?php if ($flash): ?>
                <div class="alert alert-<?php echo $flash['type']; ?>">
                    <?php echo $flash['message']; ?>
                </div>
            <?php endif; ?>

            <!-- Event Details -->
            <div class="card">
                <h3><?php echo sanitize($event['title']); ?></h3>
                <div class="event-meta">
                    <span>📅 <?php echo date('M j, Y', strtotime($event['event_date'])); ?></span>
                    <span>🕒 <?php echo date('g:i A', strtotime($event['event_time'])); ?></span>
                    <span>📍 <?php echo sanitize($event['location']); ?></span>
                </div>
                <div class="event-meta">
                    <span>👥 Capacity: <?php echo $event['current_capacity']; ?>/<?php echo $event['max_capacity']; ?></span>
                    <span>📊 Status: <span class="badge badge-<?php echo $event['status']; ?>"><?php echo ucfirst($event['status']); ?></span></span>
                </div>
                <p><?php echo sanitize($event['description']); ?></p>
            </div>

            <!-- Attendance Statistics -->
            <div class="dashboard-grid">
                <div class="dashboard-card">
                    <h3>Total Responses</h3>
                    <div class="number"><?php echo $total_attendees; ?></div>
                    <p>People who responded</p>
                </div>
                <div class="dashboard-card">
                    <h3>Attending</h3>
                    <div class="number" style="color: #28a745;"><?php echo $attending_count; ?></div>
                    <p>Confirmed attendees</p>
                </div>
                <div class="dashboard-card">
                    <h3>Not Attending</h3>
                    <div class="number" style="color: #dc3545;"><?php echo $not_attending_count; ?></div>
                    <p>Declined invitations</p>
                </div>
                <div class="dashboard-card">
                    <h3>Available Spots</h3>
                    <div class="number" style="color: #007bff;"><?php echo max(0, $event['max_capacity'] - $attending_count); ?></div>
                    <p>Remaining capacity</p>
                </div>
            </div>

            <!-- Attendees List -->
            <div class="card">
                <h3>Attendees List (<?php echo $total_attendees; ?> responses)</h3>
                
                <?php if (empty($attendees)): ?>
                    <p>No one has responded to this event yet.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Department</th>
                                    <th>Status</th>
                                    <th>Response Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($attendees as $attendee): ?>
                                    <tr>
                                        <td><?php echo sanitize($attendee['full_name']); ?></td>
                                        <td><?php echo sanitize($attendee['email']); ?></td>
                                        <td>
                                            <span class="badge badge-<?php echo $attendee['role']; ?>">
                                                <?php echo ucfirst($attendee['role']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo sanitize($attendee['department'] ?: '-'); ?></td>
                                        <td>
                                            <?php if ($attendee['status'] === 'attending'): ?>
                                                <span class="badge badge-success">Attending</span>
                                            <?php elseif ($attendee['status'] === 'not_attending'): ?>
                                                <span class="badge badge-danger">Not Attending</span>
                                            <?php else: ?>
                                                <span class="badge badge-warning">Maybe</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo date('M j, Y g:i A', strtotime($attendee['created_at'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Export Options -->
                    <div style="margin-top: 2rem; text-align: center;">
                        <h4>Export Attendees</h4>
                        <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
                            <a href="export_attendees.php?event_id=<?php echo $event_id; ?>&format=csv" class="btn btn-primary">
                                📊 Export to CSV
                            </a>
                            <a href="export_attendees.php?event_id=<?php echo $event_id; ?>&format=pdf" class="btn btn-secondary">
                                📄 Export to PDF
                            </a>
                        </div>
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