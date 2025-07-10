<?php
require_once 'config.php';

// Get featured events (all events)
$pdo = getDBConnection();
$stmt = $pdo->prepare("
    SELECT e.*, u.full_name as creator_name 
    FROM events e 
    JOIN users u ON e.created_by = u.id 
    WHERE e.event_date >= CURDATE()
    ORDER BY e.event_date ASC 
    LIMIT 6
");
$stmt->execute();
$featuredEvents = $stmt->fetchAll();

// Get event statistics
$stmt = $pdo->query("SELECT COUNT(*) as total_events FROM events WHERE status = 'approved'");
$totalEvents = $stmt->fetch()['total_events'];

$stmt = $pdo->query("SELECT COUNT(*) as total_users FROM users WHERE role != 'admin'");
$totalUsers = $stmt->fetch()['total_users'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?> - School Event Management</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <!-- Header -->
    <header class="header">
        <nav class="navbar">
            <div class="logo"><?php echo SITE_NAME; ?></div>
            <ul class="nav-links">
                <li><a href="index.php">Home</a></li>
                <li><a href="#events">Events</a></li>
                <?php if (isLoggedIn()): ?>
                    <?php if (isAdmin()): ?>
                        <li><a href="admin/dashboard.php">Admin Dashboard</a></li>
                    <?php else: ?>
                        <li><a href="user/dashboard.php">My Dashboard</a></li>
                    <?php endif; ?>
                    <li><a href="logout.php">Logout</a></li>
                <?php else: ?>
                    <li><a href="login.php">Login</a></li>
                    <li><a href="register.php">Register</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    </header>

    <!-- Main Content -->
    <main>
        <!-- Hero Section -->
        <section class="hero" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 4rem 0; text-align: center;">
            <div class="container">
                <h1 style="font-size: 3rem; margin-bottom: 1rem;">Welcome to EventEase</h1>
                <p style="font-size: 1.2rem; margin-bottom: 2rem;">The ultimate school event management platform for students and teachers</p>
                <?php if (!isLoggedIn()): ?>
                    <a href="register.php" class="btn btn-primary" style="margin-right: 1rem;">Get Started</a>
                    <a href="login.php" class="btn" style="background: rgba(255,255,255,0.2); color: white;">Login</a>
                <?php endif; ?>
            </div>
        </section>

        <!-- Statistics Section -->
        <section class="container" style="margin-top: -2rem; position: relative; z-index: 10;">
            <div class="dashboard-grid">
                <div class="dashboard-card">
                    <h3>Total Events</h3>
                    <div class="number"><?php echo $totalEvents; ?></div>
                </div>
                <div class="dashboard-card">
                    <h3>Active Users</h3>
                    <div class="number"><?php echo $totalUsers; ?></div>
                </div>
                <div class="dashboard-card">
                    <h3>Easy Management</h3>
                    <div class="number">✓</div>
                </div>
            </div>
        </section>

        <!-- Featured Events Section -->
        <section id="events" class="container">
            <h2 style="text-align: center; margin-bottom: 2rem; color: #667eea;">Featured Events</h2>
            
            <?php if (empty($featuredEvents)): ?>
                <div class="card" style="text-align: center;">
                    <h3>No events available</h3>
                    <p>Check back later for upcoming events!</p>
                </div>
            <?php else: ?>
                <div class="event-grid">
                    <?php foreach ($featuredEvents as $event): ?>
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
                                    <span>👥 <?php echo $event['current_capacity']; ?>/<?php echo $event['max_capacity']; ?></span>
                                </div>
                                <div class="event-meta">
                                    <span>👤 By: <?php echo sanitize($event['creator_name']); ?></span>
                                </div>
                                <?php if (isLoggedIn() && !isAdmin()): ?>
                                    <div class="event-actions">
                                        <a href="user/rsvp.php?event_id=<?php echo $event['id']; ?>" class="btn btn-primary">
                                            <?php echo $event['status'] === 'pending' ? 'Apply (Pending)' : 'RSVP'; ?>
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <!-- Features Section -->
        <section class="container" style="margin-top: 4rem;">
            <h2 style="text-align: center; margin-bottom: 3rem; color: #667eea;">Why Choose EventEase?</h2>
            <div class="dashboard-grid">
                <div class="dashboard-card">
                    <h3>🎯 Easy Event Creation</h3>
                    <p>Students and teachers can easily create events with our intuitive interface</p>
                </div>
                <div class="dashboard-card">
                    <h3>✅ Admin Approval</h3>
                    <p>All events go through admin approval to ensure quality and safety</p>
                </div>
                <div class="dashboard-card">
                    <h3>📱 RSVP System</h3>
                    <p>Simple RSVP system to track event attendance and manage capacity</p>
                </div>
                <div class="dashboard-card">
                    <h3>📊 Analytics</h3>
                    <p>Comprehensive analytics and reporting for event management</p>
                </div>
            </div>
        </section>
    </main>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <p>&copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. All rights reserved.</p>
            <p>School Event Management System</p>
        </div>
    </footer>

    <script src="assets/js/main.js"></script>
</body>
</html>
