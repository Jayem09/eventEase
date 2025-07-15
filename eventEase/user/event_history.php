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

// Get filter parameters
$filter = $_GET['filter'] ?? '';
$search = $_GET['search'] ?? '';
$year = $_GET['year'] ?? date('Y');

// Build query for past events created by user
$where_conditions = ["e.event_date < CURDATE()", "e.created_by = ?"];
$params = [$_SESSION['user_id']];

if ($filter === 'approved') {
    $where_conditions[] = "e.status = 'approved'";
} elseif ($filter === 'declined') {
    $where_conditions[] = "e.status = 'declined'";
} elseif ($filter === 'pending') {
    $where_conditions[] = "e.status = 'pending'";
}

if (!empty($search)) {
    $where_conditions[] = "(e.title LIKE ? OR e.description LIKE ? OR e.location LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if (!empty($year)) {
    $where_conditions[] = "YEAR(e.event_date) = ?";
    $params[] = $year;
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

// Get past events created by user
$query = "
    SELECT e.*, 
           (SELECT COUNT(*) FROM rsvps WHERE event_id = e.id AND status = 'attending') as rsvp_count,
           (SELECT COUNT(*) FROM rsvps WHERE event_id = e.id) as total_responses
    FROM events e 
    $where_clause
    ORDER BY e.event_date DESC
";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$myPastEvents = $stmt->fetchAll();

// Get past events user attended
$stmt = $pdo->prepare("
    SELECT e.*, r.status as rsvp_status, u.full_name as creator_name,
           (SELECT COUNT(*) FROM rsvps WHERE event_id = e.id AND status = 'attending') as rsvp_count
    FROM events e 
    JOIN rsvps r ON e.id = r.event_id
    JOIN users u ON e.created_by = u.id
    WHERE r.user_id = ? AND e.event_date < CURDATE()
    ORDER BY e.event_date DESC
");
$stmt->execute([$_SESSION['user_id']]);
$attendedPastEvents = $stmt->fetchAll();

// Get statistics
$stmt = $pdo->prepare("SELECT COUNT(*) as total_created FROM events WHERE event_date < CURDATE() AND created_by = ?");
$stmt->execute([$_SESSION['user_id']]);
$totalCreated = $stmt->fetch()['total_created'];

$stmt = $pdo->prepare("SELECT COUNT(*) as total_attended FROM rsvps r JOIN events e ON r.event_id = e.id WHERE r.user_id = ? AND e.event_date < CURDATE() AND r.status = 'attending'");
$stmt->execute([$_SESSION['user_id']]);
$totalAttended = $stmt->fetch()['total_attended'];

$stmt = $pdo->prepare("SELECT COUNT(*) as total_approved FROM events WHERE event_date < CURDATE() AND created_by = ? AND status = 'approved'");
$stmt->execute([$_SESSION['user_id']]);
$totalApproved = $stmt->fetch()['total_approved'];

$stmt = $pdo->prepare("SELECT COUNT(*) as total_declined FROM events WHERE event_date < CURDATE() AND created_by = ? AND status = 'declined'");
$stmt->execute([$_SESSION['user_id']]);
$totalDeclined = $stmt->fetch()['total_declined'];

// Get years for filter
$stmt = $pdo->prepare("SELECT DISTINCT YEAR(event_date) as year FROM events WHERE event_date < CURDATE() AND created_by = ? ORDER BY year DESC");
$stmt->execute([$_SESSION['user_id']]);
$years = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Event History - <?php echo SITE_NAME; ?></title>
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



    <style>
        body {
  font-family: 'Segoe UI', sans-serif;
  background: #f9fafb;
  margin: 0;
  padding: 0;
  color: #1f2937;
}

.container {
  max-width: 1150px;
  margin: 2rem auto;
  padding: 0 1.5rem;
}

.card {
  background: #ffffff;
  padding: 1.5rem;
  border-radius: 12px;
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
  margin-bottom: 2rem;
}

.event-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  gap: 1.5rem;
}

.event-card {
  background: white;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  padding: 1.2rem;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
  transition: transform 0.2s ease-in-out;
}
.event-card:hover {
  transform: translateY(-2px);
}

.event-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 0.75rem;
}
.event-title {
  font-size: 1.1rem;
  font-weight: 600;
  margin: 0;
}

.event-body {
  font-size: 0.95rem;
  line-height: 1.5;
}

.event-meta {
  margin-top: 0.5rem;
  font-size: 0.85rem;
  color: #555;
  display: flex;
  flex-wrap: wrap;
  gap: 0.75rem 1.5rem;
}

.badge {
  padding: 0.3rem 0.7rem;
  border-radius: 999px;
  font-size: 0.75rem;
  font-weight: 600;
  text-transform: capitalize;
}
.badge-approved { background: #c6f6d5; color: #065f46; }
.badge-declined { background: #fecaca; color: #991b1b; }
.badge-pending { background: #fefcbf; color: #92400e; }
.badge-attending { background: #bee3f8; color: #1e40af; }
.badge-not_attending { background: #e0e7ff; color: #4338ca; }

.btn {
  display: inline-block;
  padding: 0.65rem 1.3rem;
  font-size: 0.88rem;
  font-weight: 600;
  border: none;
  border-radius: 8px;
  cursor: pointer;
  text-decoration: none;
  transition: all 0.2s ease-in-out;
}
.btn-primary { background: #4f46e5; color: white; }
.btn-secondary { background: #e2e8f0; color: #2d3748; }
.btn-danger { background: #ef4444; color: white; }

.btn:hover {
  opacity: 0.9;
}

.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
  gap: 1rem;
  margin-bottom: 2rem;
}

.stat-card {
  background: white;
  border: 1px solid #e5e7eb;
  border-radius: 10px;
  padding: 1.2rem;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
  text-align: center;
}
.stat-number {
  font-size: 2rem;
  font-weight: bold;
  margin-bottom: 0.25rem;
}
.stat-label {
  font-size: 0.95rem;
  font-weight: 600;
  color: #374151;
}
.stat-sublabel {
  font-size: 0.85rem;
  color: #6b7280;
}

.form-control {
  width: 100%;
  padding: 0.6rem 1rem;
  font-size: 0.95rem;
  border: 1px solid #d1d5db;
  border-radius: 8px;
  background-color: #f9fafb;
  transition: border 0.2s ease-in-out;
}
.form-control:focus {
  border-color: #6366f1;
  outline: none;
  background-color: white;
}

.form-group {
  display: flex;
  flex-direction: column;
}

.form-grid {
  display: flex;
  flex-wrap: wrap;
  gap: 1rem;
}

.footer {
  text-align: center;
  font-size: 0.85rem;
  padding: 1.5rem 0;
  color: #6b7280;
}

    </style>

    <!-- Main Content -->
    <main>
        <div class="container">
            <div class="page-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
                <h1 class="page-title">My Event History</h1>
                <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
            </div>

            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $totalCreated; ?></div>
                    <div class="stat-label">Events I Created</div>
                    <div class="stat-sublabel">Past events created</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number" style="color: var(--success-color); "><?php echo $totalAttended; ?></div>
                    <div class="stat-label">Events I Attended</div>
                    <div class="stat-sublabel">Past events attended</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number" style="color: var(--success-color); "><?php echo $totalApproved; ?></div>
                    <div class="stat-label">Approved Events</div>
                    <div class="stat-sublabel">Successfully held</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number" style="color: var(--danger-color); "><?php echo $totalDeclined; ?></div>
                    <div class="stat-label">Declined Events</div>
                    <div class="stat-sublabel">Not approved</div>
                </div>
            </div>

            <!-- Filters and Search -->
            <div class="card">
                <form method="GET" class="form-grid form-grid-3" style="gap: 1rem; align-items: end; flex-wrap: wrap;">
                    <div class="form-group">
                        <label for="search" class="form-label">Search My Events</label>
                        <input type="text" id="search" name="search" class="form-control" 
                               value="<?php echo sanitize($search); ?>" 
                               placeholder="Search by title, description, or location...">
                    </div>
                    <div class="form-group">
                        <label for="filter" class="form-label">Filter by Status</label>
                        <select id="filter" name="filter" class="form-control">
                            <option value="">All Events</option>
                            <option value="approved" <?php echo $filter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                            <option value="declined" <?php echo $filter === 'declined' ? 'selected' : ''; ?>>Declined</option>
                            <option value="pending" <?php echo $filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="year" class="form-label">Year</label>
                        <select id="year" name="year" class="form-control">
                            <?php foreach ($years as $y): ?>
                                <option value="<?php echo $y['year']; ?>" <?php echo $year == $y['year'] ? 'selected' : ''; ?>>
                                    <?php echo $y['year']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" style="display: flex; gap: 0.5rem; align-items: end;">
                        <button type="submit" class="btn btn-primary">Filter</button>
                        <a href="event_history.php" class="btn btn-secondary">Clear</a>
                    </div>
                </form>
            </div>

            <!-- My Past Events -->
            <div class="card">
                <h3>Events I Created (<?php echo count($myPastEvents); ?> found)</h3>
                
                <?php if (empty($myPastEvents)): ?>
                    <p>You haven't created any past events yet.</p>
                <?php else: ?>
                    <div class="event-grid">
                        <?php foreach ($myPastEvents as $event): ?>
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
                                        <span>📊 Total Responses: <?php echo $event['total_responses']; ?></span>
                                        <span>📅 Created: <?php echo date('M j, Y', strtotime($event['created_at'])); ?></span>
                                    </div>
                                    
                                    <!-- Action Buttons -->
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

            <!-- Events I Attended -->
            <div class="card">
                <h3>Events I Attended (<?php echo count($attendedPastEvents); ?> found)</h3>
                
                <?php if (empty($attendedPastEvents)): ?>
                    <p>You haven't attended any past events yet.</p>
                <?php else: ?>
                    <div class="event-grid">
                        <?php foreach ($attendedPastEvents as $event): ?>
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
                                        <span>📊 My Status: <span class="badge badge-<?php echo $event['rsvp_status']; ?>"><?php echo ucfirst($event['rsvp_status']); ?></span></span>
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