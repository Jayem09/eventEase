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

        // Get events user has JOIN'd to
$stmt = $pdo->prepare("
    SELECT e.*, r.status as rsvp_status, u.full_name as creator_name
    FROM events e 
    JOIN rsvps r ON e.id = r.event_id
    JOIN users u ON e.created_by = u.id
    WHERE r.user_id = ? AND e.status = 'approved'
    ORDER BY e.event_date ASC
");
$stmt->execute([$_SESSION['user_id']]);
        $joinEvents = $stmt->fetchAll();

// Get statistics
$stmt = $pdo->prepare("SELECT COUNT(*) as total_created FROM events WHERE created_by = ?");
$stmt->execute([$_SESSION['user_id']]);
$totalCreated = $stmt->fetch()['total_created'];

        $stmt = $pdo->prepare("SELECT COUNT(*) as total_joins FROM rsvps WHERE user_id = ? AND status = 'attending'");
        $stmt->execute([$_SESSION['user_id']]);
        $totalJoins = $stmt->fetch()['total_joins'];

$stmt = $pdo->prepare("SELECT COUNT(*) as pending_events FROM events WHERE created_by = ? AND status = 'pending'");
$stmt->execute([$_SESSION['user_id']]);
$pendingEvents = $stmt->fetch()['pending_events'];

        // Get upcoming events (all statuses) for JOIN
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
            <button class="hamburger" id="hamburgerBtn" aria-label="Toggle navigation">&#9776;</button>
            <ul class="nav-links" id="navLinks">
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
                   Create events, JOIN others, and manage your activities.</p>
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
                                    <div class="number"><?php echo $totalJoins; ?></div>
                <p>JOIN'd events</p>
                </div>
                <div class="dashboard-card">
                    <h3>Pending Events</h3>
                    <div class="number" style="color: #ffc107;"><?php echo $pendingEvents; ?></div>
                    <p>Awaiting approval</p>
                </div>
                <div class="dashboard-card">
                    <h3>Quick Actions</h3>
                    <div style="margin-top: 1rem;">
                        <button id="openCreateEventModal" class="btn btn-primary">Create New Event</button>
                        <a href="rsvp.php" class="btn btn-secondary">Browse Events</a>
                        <a href="event_history.php" class="btn btn-info">Event History</a>
                    </div>
                </div>
            </div>

            <!-- Create Event Modal -->
            <div id="createEventModal" class="modal">
  <div class="modal-content">
    <span class="close" id="closeCreateEventModal">&times;</span>
    <h3>Create New Event</h3>

    <form method="POST" action="create_event.php" data-validate>
      <div class="grid-2">
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

      <div class="grid-3">
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
                                        <span>📊 JOINs: <?php echo $event['rsvp_count']; ?></span>
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
            <div class="card" id="joined-events-section">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                    <h3>Events I'm Attending</h3>
                    <button onclick="printJoinedEvents()" class="btn btn-secondary print-btn" style="margin-bottom:0.5rem;">Print</button>
                </div>
                <?php if (empty($joinEvents)): ?>
                    <p>You haven't JOIN'd to any events yet. <a href="rsvp.php">Browse available events</a>!</p>
                <?php else: ?>
                    <div class="event-grid">
                        <?php foreach ($joinEvents as $event): ?>
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
                                                <?php echo $event['status'] === 'pending' ? 'Apply (Pending)' : 'JOIN'; ?>
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
    <style>
    :root {
        --primary:rgb(255, 255, 255);
        --secondary: #4c51bf;
        --light-bg: #f9f9f9;
        --ds-bg:rgb(39, 38, 38);
        --text-color: #333;
        --border-radius: 12px;
        --transition: all 0.2s ease-in-out;
    }

    body {
        font-family: 'Segoe UI', sans-serif;
        background-color: var(--light-bg);
        margin: 0;
        padding: 0;
        color: var(--text-color);
    }

    .container {
        max-width: 1200px;
        margin: 2rem auto;
        padding: 0 1.5rem;
    }

    .header {
        background: var(--primary);
        padding: 1rem 0;
        color: #fff;
    }

    .navbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
    }

    .logo {
        font-size: 1.5rem;
        font-weight: bold;
    }

    .nav-links {
        display: flex;
        gap: 1rem;
        list-style: none;
        margin: 0;
        padding: 0;
    }

    .nav-links a {
        color: var(--ds-bg);
        text-decoration: none;
        font-weight: 500;
        transition: var(--transition);
    }

    .nav-links a:hover {
        text-decoration: underline;
    }

    .hamburger {
        display: none;
        font-size: 1.8rem;
        background: none;
        border: none;
        color: #fff;
    }

    .card {
        background: #fff;
        padding: 1.5rem;
        border-radius: var(--border-radius);
        box-shadow: 0 2px 4px rgba(0,0,0,0.06);
        margin-bottom: 2rem;
    }

    .dashboard-grid {
        display: grid;
        gap: 1rem;
        grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
        margin-bottom: 2rem;
    }

    .dashboard-card {
        background: #fff;
        padding: 1.5rem;
        border-radius: var(--border-radius);
        box-shadow: 0 2px 6px rgba(0,0,0,0.05);
        text-align: center;
    }

    .dashboard-card h3 {
        margin-bottom: 0.5rem;
        font-size: 1.1rem;
    }

    .number {
        font-size: 2rem;
        font-weight: bold;
        margin: 0.5rem 0;
    }
    .dashboard-card .btn {
    display: block;
    width: 100%;
    margin-bottom: 0.75rem;
    padding: 0.75rem 1.5rem;
    font-size: 0.95rem;
    font-weight: 600;
    border: none;
    border-radius: 10px;
    text-align: center;
    text-decoration: none;
    transition: all 0.2s ease-in-out;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.06);
}

.dashboard-card .btn:last-child {
    margin-bottom: 0;
}

.btn-primary {
    background-color: #4f46e5;
    color: white;
}

.btn-primary:hover {
    background-color: #4338ca;
}

.btn-secondary {
    background-color: #f3f4f6;
    color: #1f2937;
}

.btn-secondary:hover {
    background-color: #e5e7eb;
}

.btn-info {
    background: linear-gradient(to right, #38bdf8, #0ea5e9);
    color: white;
}

.btn-info:hover {
    filter: brightness(1.05);
}


    .event-grid {
        display: grid;
        gap: 1rem;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    }

    .event-card {
        background: #fff;
        border: 1px solid #e2e8f0;
        border-radius: var(--border-radius);
        padding: 1rem;
        box-shadow: 0 1px 3px rgba(0,0,0,0.04);
    }

    .event-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 0.5rem;
    }

    .event-title {
        font-size: 1rem;
        font-weight: 600;
        margin: 0;
    }

    .badge {
        padding: 0.3rem 0.6rem;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: capitalize;
        background: #e2e8f0;
        color: #333;
    }

    .badge-approved {
        background-color: #c6f6d5;
        color: #22543d;
    }
    .badge-pending {
        background-color: #fefcbf;
        color: #744210;
    }
    .badge-declined {
        background-color: #fed7d7;
        color: #742a2a;
    }
    .badge-attending {
        background-color: #bee3f8;
        color: #2c5282;
    }

    .event-body {
        font-size: 0.9rem;
        color: #444;
    }

    .event-meta {
        margin-top: 0.5rem;
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem 1.5rem;
        font-size: 0.85rem;
        color: #666;
    }

    .event-actions {
        margin-top: 1rem;
    }

    .modal {
        display: none;
        position: fixed;
        z-index: 999;
        left: 0; top: 0; width: 100%; height: 100%;
        background-color: rgba(0,0,0,0.4);
        overflow-y: auto;
    }

    .modal-content {
        background-color: #fff;
        margin: 5% auto;
        padding: 2rem;
        border-radius: var(--border-radius);
        width: 95%;
        max-width: 600px;
        position: relative;
    }

    .close {
        color: #aaa;
        position: absolute;
        right: 1rem;
        top: 1rem;
        font-size: 2rem;
        cursor: pointer;
    }

    .close:hover {
        color: #000;
    }

    footer.footer {
        padding: 1rem 0;
        text-align: center;
        font-size: 0.9rem;
        color: #777;
    }

    @media (max-width: 768px) {
        .hamburger {
            display: block;
        }
        .nav-links {
            flex-direction: column;
            width: 100%;
            background: #fff;
            color: #333;
            display: none;
            margin-top: 1rem;
        }
        .nav-links.active {
            display: flex;
        }
        .nav-links li {
            text-align: center;
            width: 100%;
            padding: 0.5rem 0;
        }
    }

    @media print {
        body * { visibility: hidden !important; }
        #joined-events-section, #joined-events-section * { visibility: visible !important; }
        #joined-events-section { position: absolute; left: 0; top: 0; width: 100vw; background: #fff; }
        .print-btn { display: none !important; }
    }

    </style>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
      var openBtn = document.getElementById('openCreateEventModal');
      var modal = document.getElementById('createEventModal');
      var closeBtn = document.getElementById('closeCreateEventModal');
      if (openBtn && modal && closeBtn) {
        openBtn.onclick = function() { modal.style.display = 'block'; };
        closeBtn.onclick = function() { modal.style.display = 'none'; };
        window.onclick = function(event) {
          if (event.target == modal) { modal.style.display = 'none'; }
        };
      }
      var hamburger = document.getElementById('hamburgerBtn');
      var navLinks = document.getElementById('navLinks');
      if (hamburger && navLinks) {
        hamburger.addEventListener('click', function() {
            navLinks.classList.toggle('active');
        });
      }
    });
    function printJoinedEvents() {
        var printContents = document.getElementById('joined-events-section').innerHTML;
        var originalContents = document.body.innerHTML;
        document.body.innerHTML = printContents;
        window.print();
        document.body.innerHTML = originalContents;
        window.location.reload(); // reload to restore event handlers
    }
    </script>
</body>
</html>
