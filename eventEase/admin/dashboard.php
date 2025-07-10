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

// Get statistics
$stmt = $pdo->query("SELECT COUNT(*) as total_events FROM events");
$totalEvents = $stmt->fetch()['total_events'];

$stmt = $pdo->query("SELECT COUNT(*) as pending_events FROM events WHERE status = 'pending'");
$pendingEvents = $stmt->fetch()['pending_events'];

$stmt = $pdo->query("SELECT COUNT(*) as total_users FROM users WHERE role != 'admin'");
$totalUsers = $stmt->fetch()['total_users'];

$stmt = $pdo->query("SELECT COUNT(*) as total_rsvps FROM rsvps");
$totalRSVPs = $stmt->fetch()['total_rsvps'];

// Get recent events
$stmt = $pdo->prepare("
    SELECT e.*, u.full_name as creator_name 
    FROM events e 
    JOIN users u ON e.created_by = u.id 
    ORDER BY e.created_at DESC 
    LIMIT 5
");
$stmt->execute();
$recentEvents = $stmt->fetchAll();

// Get pending events count by status
$stmt = $pdo->query("
    SELECT status, COUNT(*) as count 
    FROM events 
    GROUP BY status
");
$eventStats = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - <?php echo SITE_NAME; ?></title>
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
            <h1 style="margin-bottom: 2rem; color: #667eea;">Admin Dashboard</h1>
            
            <!-- Welcome Message -->
            <div class="card">
                <h2>Welcome, <?php echo sanitize($_SESSION['full_name']); ?>!</h2>
                <p>Manage your school's events, users, and system settings from this dashboard.</p>
            </div>

            <!-- Statistics -->
            <div class="dashboard-grid">
                <div class="dashboard-card">
                    <h3>Total Events</h3>
                    <div class="number"><?php echo $totalEvents; ?></div>
                    <p>All events in the system</p>
                </div>
                <div class="dashboard-card">
                    <h3>Pending Events</h3>
                    <div class="number" style="color: #ffc107;"><?php echo $pendingEvents; ?></div>
                    <p>Awaiting approval</p>
                </div>
                <div class="dashboard-card">
                    <h3>Total Users</h3>
                    <div class="number"><?php echo $totalUsers; ?></div>
                    <p>Students and teachers</p>
                </div>
                <div class="dashboard-card">
                    <h3>Total RSVPs</h3>
                    <div class="number"><?php echo $totalRSVPs; ?></div>
                    <p>Event responses</p>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="card">
                <h3>Quick Actions</h3>
                <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                    <a href="events.php" class="btn btn-primary">Manage Events</a>
                    <a href="users.php" class="btn btn-secondary">Manage Users</a>
                    <a href="event_history.php" class="btn btn-info">Event History</a>
                    <a href="events.php?filter=pending" class="btn btn-warning">Review Pending Events</a>
                </div>
            </div>

            <!-- Recent Events -->
            <div class="card">
                <h3>Recent Events</h3>
                <?php if (empty($recentEvents)): ?>
                    <p>No events found.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Creator</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentEvents as $event): ?>
                                    <tr>
                                        <td><?php echo sanitize($event['title']); ?></td>
                                        <td><?php echo sanitize($event['creator_name']); ?></td>
                                        <td><?php echo date('M j, Y', strtotime($event['event_date'])); ?></td>
                                        <td>
                                            <span class="badge badge-<?php echo $event['status']; ?>">
                                                <?php echo ucfirst($event['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="events.php?action=view&id=<?php echo $event['id']; ?>" class="btn btn-secondary" style="padding: 0.25rem 0.5rem; font-size: 0.875rem;">View</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Event Status Overview -->
            <div class="card">
                <h3>Event Status Overview</h3>
                <div class="dashboard-grid">
                    <?php foreach ($eventStats as $stat): ?>
                        <div class="dashboard-card">
                            <h3><?php echo ucfirst($stat['status']); ?> Events</h3>
                            <div class="number"><?php echo $stat['count']; ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
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
