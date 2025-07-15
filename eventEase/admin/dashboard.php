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

$stmt = $pdo->query("SELECT COUNT(*) as total_joins FROM rsvps");
$totalJoins = $stmt->fetch()['total_joins'];

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
    <style>
       /* Global Styles */
body {
    font-family: 'Segoe UI', sans-serif;
    background-color: #f4f7fc;
    margin: 0;
    padding: 0;
    color: #333;
}

.container {
    max-width: 1200px;
    margin: 2rem auto;
    padding: 0 1.5rem;
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

.hamburger {
    display: none;
    font-size: 2rem;
    background: none;
    border: none;
    color: black;
    cursor: pointer;
    margin-left: auto;
}

/* Card Styles */
.card {
    background: white;
    border-radius: 10px;
    padding: 1.5rem;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    margin-bottom: 2rem;
}

.card h2, .card h3 {
    color: #4f46e5;
}

.card p {
    font-size: 1rem;
    color: #555;
}

/* Dashboard Grid */
.dashboard-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 2rem;
}

.dashboard-card {
    background: #ffffff;
    border-radius: 10px;
    padding: 1.5rem;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    text-align: center;
}

.dashboard-card h3 {
    font-size: 1.2rem;
    margin-bottom: 1rem;
}

.dashboard-card .number {
    font-size: 2rem;
    font-weight: bold;
    color: #4f46e5;
}

.dashboard-card p {
    font-size: 1rem;
    color: #555;
}

.btn {
    padding: 0.75rem 1.5rem;
    font-size: 1rem;
    font-weight: 600;
    text-decoration: none;
    background-color: #4f46e5;
    color: white;
    border-radius: 8px;
    transition: background-color 0.3s ease;
    margin: 0.5rem 0;
    display: inline-block;
}

.btn:hover {
    background-color: #4338ca;
}

.btn-info {
    background-color: #38bdf8;
}

.btn-warning {
    background-color: #fbbf24;
}

/* Table Styles */
table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 1.5rem;
}

table th, table td {
    padding: 1rem;
    text-align: left;
    border-bottom: 1px solid #e5e7eb;
}

table th {
    background-color: #f9fafb;
    font-size: 1rem;
}

/* Badge Styling */
.badge {
    padding: 0.3rem 0.6rem;
    border-radius: 12px;
    font-size: 0.85rem;
    font-weight: 600;
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

/* Footer Styling */
.footer {
    background-color: #f9fafb;
    padding: 1rem;
    text-align: center;
    color: #555;
    border-top: 1px solid #e5e7eb;
}

/* Mobile View Styling */
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
        padding: 1rem 0;
        margin: 0;
        display: none; /* Hide nav links on mobile by default */
    }

    .nav-links.active {
        display: flex; /* Show nav links when active */
    }

    .hamburger {
        display: block; /* Show hamburger button on mobile */
    }
}

    </style>
</head>
<body>
<header class="header">
    <nav class="navbar">
        <div class="logo"><?php echo SITE_NAME; ?> - Admin</div>
        <button class="hamburger" id="hamburgerBtn" aria-label="Toggle navigation">&#9776;</button>
        <ul class="nav-links" id="navLinks">
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
            <h1>Admin Dashboard</h1>

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
                    <h3>Total JOINs</h3>
                    <div class="number"><?php echo $totalJoins; ?></div>
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
                                            <a href="events.php?action=view&id=<?php echo $event['id']; ?>" class="btn btn-secondary">View</a>
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
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var hamburger = document.getElementById('hamburgerBtn');
        var navLinks = document.getElementById('navLinks');
        if (hamburger && navLinks) {
            hamburger.addEventListener('click', function() {
                navLinks.classList.toggle('active');
            });
        }
    });


    document.addEventListener('DOMContentLoaded', function() {
    var hamburger = document.getElementById('hamburgerBtn');
    var navLinks = document.getElementById('navLinks');
    if (hamburger && navLinks) {
        hamburger.addEventListener('click', function() {
            navLinks.classList.toggle('active'); // Toggle 'active' class on nav-links to show or hide
        });
    }
});

    </script>
</body>
</html>
