<?php
require_once '../config.php';

if (!isLoggedIn()) redirect('../login.php');
if (isAdmin()) redirect('../admin/dashboard.php');

$pdo = getDBConnection();

// Handle JOIN
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $event_id = (int)($_POST['event_id'] ?? 0);
    $action = $_POST['action'];
    if ($event_id > 0) {
        $stmt = $pdo->prepare("SELECT id, max_capacity, current_capacity, status FROM events WHERE id = ?");
        $stmt->execute([$event_id]);
        $event = $stmt->fetch();

        if ($event) {
            $stmt = $pdo->prepare("SELECT id, status FROM rsvps WHERE event_id = ? AND user_id = ?");
            $stmt->execute([$event_id, $_SESSION['user_id']]);
            $existing_rsvp = $stmt->fetch();

            if ($action === 'attending') {
                if ($event['current_capacity'] >= $event['max_capacity']) {
                    setFlashMessage('danger', 'Sorry, this event is at full capacity.');
                } else {
                    if ($existing_rsvp) {
                        $stmt = $pdo->prepare("UPDATE rsvps SET status = 'attending' WHERE id = ?");
                        $stmt->execute([$existing_rsvp['id']]);
                    } else {
                        $stmt = $pdo->prepare("INSERT INTO rsvps (event_id, user_id, status) VALUES (?, ?, 'attending')");
                        $stmt->execute([$event_id, $_SESSION['user_id']]);
                        $stmt = $pdo->prepare("UPDATE events SET current_capacity = current_capacity + 1 WHERE id = ?");
                        $stmt->execute([$event_id]);
                    }
                    setFlashMessage('success', 'JOIN successful! You are now attending this event.');
                }
            } elseif ($action === 'not_attending') {
                if ($existing_rsvp) {
                    $stmt = $pdo->prepare("UPDATE rsvps SET status = 'not_attending' WHERE id = ?");
                    $stmt->execute([$existing_rsvp['id']]);
                    if ($existing_rsvp['status'] === 'attending') {
                        $stmt = $pdo->prepare("UPDATE events SET current_capacity = current_capacity - 1 WHERE id = ?");
                        $stmt->execute([$event_id]);
                    }
                    setFlashMessage('success', 'JOIN updated successfully.');
                }
            }
        } else {
            setFlashMessage('danger', 'Event not found.');
        }
    }
    redirect('rsvp.php');
}

$search = $_GET['search'] ?? '';
$event_id = (int)($_GET['event_id'] ?? 0);

$where_conditions = ["e.event_date >= CURDATE()"];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(e.title LIKE ? OR e.description LIKE ? OR u.full_name LIKE ? OR e.location LIKE ?)";
    $search_param = "%$search%";
    array_push($params, ...array_fill(0, 4, $search_param));
}
if ($event_id > 0) {
    $where_conditions[] = "e.id = ?";
    $params[] = $event_id;
}
$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

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
    <style>
        .header {
            background: white;
            color: #1f2937;
            border-bottom: 1px solid #e5e7eb;
            padding: 0.75rem 0;
        }
        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.5rem 1.25rem;
            max-width: 1150px;
            margin: 0 auto;
        }
        .navbar .logo {
            font-size: 1.15rem;
            font-weight: bold;
            color: #1f2937;
        }
        .nav-links {
            display: flex;
            gap: 1rem;
            list-style: none;
            margin: 0;
            padding: 0;
        }
        .nav-links a {
            text-decoration: none;
            color: #374151;
            font-weight: 500;
            transition: color 0.2s;
        }
        .nav-links a:hover {
            color: #4f46e5;
        }
        @media (max-width: 768px) {
            .navbar {
                flex-direction: column;
                align-items: flex-start;
                padding: 1rem;
            }
            .nav-links {
                flex-direction: column;
                width: 100%;
                background-color: white;
                padding: 0.75rem 0;
            }
        }
    </style>
</head>
<body>

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

    <main>
        <div class="container">
            <div class="page-header">
                <h1>Browse Events</h1>
                <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
            </div>

            <?php $flash = getFlashMessage(); ?>
            <?php if ($flash): ?>
                <div class="alert alert-<?php echo $flash['type']; ?>">
                    <?php echo $flash['message']; ?>
                </div>
            <?php endif; ?>

            <div class="card">
                <form method="GET" class="form-grid">
                    <div class="form-group">
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

            <div class="card">
                <h3>All Events (<?php echo count($events); ?> found)</h3>

                <details style="margin-bottom:1rem;">
                    <summary>📋 Event Status Guide</summary>
                    <div>
                        • <span class="badge badge-pending">Pending</span> – Waiting approval<br>
                        • <span class="badge badge-approved">Approved</span> – Confirmed<br>
                        • <span class="badge badge-declined">Declined</span> – Rejected
                    </div>
                </details>

                <?php if (empty($events)): ?>
                    <p>No events found.</p>
                <?php else: ?>
                    <div class="event-grid">
                        <?php foreach ($events as $event): ?>
                            <div class="event-card">
                                <div class="event-header">
                                    <h3 class="event-title"><?php echo sanitize($event['title']); ?></h3>
                                    <span class="badge badge-<?php echo $event['status']; ?>"><?php echo ucfirst($event['status']); ?></span>
                                </div>
                                <div class="event-body">
                                    <p><?php echo sanitize($event['description']); ?></p>
                                    <div class="event-meta">
                                        📅 <?php echo date('M j, Y', strtotime($event['event_date'])); ?> |
                                        🕒 <?php echo date('g:i A', strtotime($event['event_time'])); ?> |
                                        📍 <?php echo sanitize($event['location']); ?> |
                                        👥 <?php echo $event['rsvp_count']; ?>/<?php echo $event['max_capacity']; ?> |
                                        👤 <?php echo sanitize($event['creator_name']); ?>
                                    </div>

                                    <div class="event-actions">
                                        <?php if ($event['status'] === 'declined'): ?>
                                            <span class="btn btn-danger" disabled>Event Declined</span>
                                        <?php elseif ($event['rsvp_count'] >= $event['max_capacity']): ?>
                                            <span class="btn btn-danger" disabled>Full Capacity</span>
                                        <?php elseif ($event['user_rsvp_status'] === 'attending'): ?>
                                            <span class="btn btn-success" disabled>✓ Attending</span>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="action" value="not_attending">
                                                <input type="hidden" name="event_id" value="<?php echo $event['id']; ?>">
                                                <button type="submit" class="btn btn-secondary">Cancel JOIN</button>
                                            </form>
                                        <?php else: ?>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="action" value="attending">
                                                <input type="hidden" name="event_id" value="<?php echo $event['id']; ?>">
                                                <button type="submit" class="btn btn-primary">
                                                    <?php echo $event['status'] === 'pending' ? 'Apply (Pending)' : 'JOIN'; ?>
                                                </button>
                                            </form>
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

    <footer class="footer" style="padding:1rem; text-align:center; font-size:0.85rem; color:#6b7280;">
        &copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. All rights reserved.
    </footer>

</body>
</html>
