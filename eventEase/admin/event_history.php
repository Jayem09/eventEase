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

// Get filter parameters
$filter = $_GET['filter'] ?? '';
$search = $_GET['search'] ?? '';
$year = $_GET['year'] ?? date('Y');

// Build query for past events
$where_conditions = ["e.event_date < CURDATE()"];
$params = [];

if ($filter === 'approved') {
    $where_conditions[] = "e.status = 'approved'";
} elseif ($filter === 'declined') {
    $where_conditions[] = "e.status = 'declined'";
} elseif ($filter === 'pending') {
    $where_conditions[] = "e.status = 'pending'";
}

if (!empty($search)) {
    $where_conditions[] = "(e.title LIKE ? OR e.description LIKE ? OR u.full_name LIKE ? OR e.location LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if (!empty($year)) {
    $where_conditions[] = "YEAR(e.event_date) = ?";
    $params[] = $year;
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

// Get past events with statistics
$query = "
    SELECT e.*, u.full_name as creator_name, u.email as creator_email,
           (SELECT COUNT(*) FROM rsvps WHERE event_id = e.id AND status = 'attending') as rsvp_count,
           (SELECT COUNT(*) FROM rsvps WHERE event_id = e.id) as total_responses
    FROM events e 
    JOIN users u ON e.created_by = u.id 
    $where_clause
    ORDER BY e.event_date DESC
";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$pastEvents = $stmt->fetchAll();

// Get statistics
$stmt = $pdo->query("SELECT COUNT(*) as total_past_events FROM events WHERE event_date < CURDATE()");
$totalPastEvents = $stmt->fetch()['total_past_events'];

$stmt = $pdo->query("SELECT COUNT(*) as total_approved FROM events WHERE event_date < CURDATE() AND status = 'approved'");
$totalApproved = $stmt->fetch()['total_approved'];

$stmt = $pdo->query("SELECT COUNT(*) as total_declined FROM events WHERE event_date < CURDATE() AND status = 'declined'");
$totalDeclined = $stmt->fetch()['total_declined'];

$stmt = $pdo->query("SELECT COUNT(*) as total_pending FROM events WHERE event_date < CURDATE() AND status = 'pending'");
$totalPending = $stmt->fetch()['total_pending'];

// Get years for filter
$stmt = $pdo->query("SELECT DISTINCT YEAR(event_date) as year FROM events WHERE event_date < CURDATE() ORDER BY year DESC");
$years = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event History - <?php echo SITE_NAME; ?></title>
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
                <h1 style="color: #667eea;">Event History</h1>
                <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
            </div>

            <!-- Statistics -->
            <div class="dashboard-grid">
                <div class="dashboard-card">
                    <h3>Total Past Events</h3>
                    <div class="number"><?php echo $totalPastEvents; ?></div>
                    <p>All completed events</p>
                </div>
                <div class="dashboard-card">
                    <h3>Approved Events</h3>
                    <div class="number" style="color: #28a745;"><?php echo $totalApproved; ?></div>
                    <p>Successfully held</p>
                </div>
                <div class="dashboard-card">
                    <h3>Declined Events</h3>
                    <div class="number" style="color: #dc3545;"><?php echo $totalDeclined; ?></div>
                    <p>Not approved</p>
                </div>
                <div class="dashboard-card">
                    <h3>Pending Events</h3>
                    <div class="number" style="color: #ffc107;"><?php echo $totalPending; ?></div>
                    <p>Still awaiting approval</p>
                </div>
            </div>

            <!-- Filters and Search -->
            <div class="card">
                <form method="GET" style="display: flex; gap: 1rem; align-items: end; flex-wrap: wrap;">
                    <div class="form-group" style="flex: 1; min-width: 200px;">
                        <label for="search">Search Events</label>
                        <input type="text" id="search" name="search" class="form-control" 
                               value="<?php echo sanitize($search); ?>" 
                               placeholder="Search by title, description, creator, or location...">
                    </div>
                    <div class="form-group">
                        <label for="filter">Filter by Status</label>
                        <select id="filter" name="filter" class="form-control">
                            <option value="">All Events</option>
                            <option value="approved" <?php echo $filter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                            <option value="declined" <?php echo $filter === 'declined' ? 'selected' : ''; ?>>Declined</option>
                            <option value="pending" <?php echo $filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="year">Year</label>
                        <select id="year" name="year" class="form-control">
                            <?php foreach ($years as $y): ?>
                                <option value="<?php echo $y['year']; ?>" <?php echo $year == $y['year'] ? 'selected' : ''; ?>>
                                    <?php echo $y['year']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">Filter</button>
                        <a href="event_history.php" class="btn btn-secondary">Clear</a>
                    </div>
                </form>
            </div>

            <!-- Past Events List -->
            <div class="card">
                <h3>Past Events (<?php echo count($pastEvents); ?> found)</h3>
                
                <?php if (empty($pastEvents)): ?>
                    <p>No past events found matching your criteria.</p>
                <?php else: ?>
                    <div class="event-grid">
                        <?php foreach ($pastEvents as $event): ?>
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
                                        <span>👥 <?php echo $event['rsvp_count']; ?>/<?php echo $event['max_capacity']; ?></span>
                                    </div>
                                    <div class="event-meta">
                                        <span>👤 By: <?php echo sanitize($event['creator_name']); ?></span>
                                        <span>📧 <?php echo sanitize($event['creator_email']); ?></span>
                                    </div>
                                    <div class="event-meta">
                                        <span>📊 Total Responses: <?php echo $event['total_responses']; ?></span>
                                        <span>📅 Created: <?php echo date('M j, Y', strtotime($event['created_at'])); ?></span>
                                    </div>
                                    
                                    <!-- Action Buttons -->
                                    <div class="event-actions">
                                        <a href="view_event_details.php?event_id=<?php echo $event['id']; ?>" class="btn btn-secondary">
                                            View Details
                                        </a>
                                        <?php if ($event['status'] === 'pending'): ?>
                                            <button class="btn btn-success" 
                                                    data-action="approve" 
                                                    data-event-id="<?php echo $event['id']; ?>"
                                                    data-confirm="Are you sure you want to approve this past event?">
                                                Approve
                                            </button>
                                            <button class="btn btn-danger" 
                                                    data-action="decline" 
                                                    data-event-id="<?php echo $event['id']; ?>"
                                                    data-confirm="Are you sure you want to decline this past event?">
                                                Decline
                                            </button>
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