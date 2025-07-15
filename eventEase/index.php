<?php
require_once 'config.php';

// Get featured events (all events)
$pdo = getDBConnection();
$stmt = $pdo->prepare("
    SELECT e.*, u.full_name as creator_name 
    FROM events e 
    JOIN users u ON e.created_by = u.id 
    WHERE e.event_date >= CURDATE() AND e.status = 'approved'
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

<style>
    body {
  font-family: 'Segoe UI', sans-serif;
  background-color: #f9fafb;
  margin: 0;
  padding: 0;
  color: #1f2937;
}

.container {
  max-width: 1150px;
  margin: 0 auto;
  padding: 2rem 1rem;
}

.header {
  background-color: #ffffff; /* Changed from #4f46e5 to white */
  color: #1f2937; /* Changed text color to dark for contrast */
  padding: 1rem 0;
}

.navbar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  padding: 0 1.5rem;
}

.nav-links {
  display: flex;
  gap: 1rem;
  list-style: none;
  margin: 0;
  padding: 0;
}

.nav-links a {
  color: #1f2937; /* Changed from white to dark for contrast */
  text-decoration: none;
  font-weight: 500;
  transition: color 0.2s ease-in-out;
}
.nav-links a:hover {
  text-decoration: underline;
  color: #4f46e5; /* Add purple on hover for accent */
}

.hamburger {
  display: none;
  font-size: 2rem;
  background: none;
  border: none;
  color: #1f2937; /* Changed from white to dark for contrast */
  cursor: pointer;
}

.dashboard-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
  gap: 1.5rem;
  margin-top: 2rem;
}

.dashboard-card {
  background: white;
  padding: 1.5rem;
  border-radius: 12px;
  box-shadow: 0 1px 4px rgba(0,0,0,0.05);
  text-align: center;
}
.dashboard-card h3 {
  font-size: 1.1rem;
  margin-bottom: 0.75rem;
}
.dashboard-card p {
  font-size: 0.9rem;
  color: #555;
}

/* .hero {
  padding: 5rem 1rem;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
  text-align: center;
} */

.hero h1 {
  font-size: 3rem;
  margin-bottom: 1rem;
}

.hero p {
  font-size: 1.2rem;
  margin-bottom: 2rem;
}

.btn {
  padding: 0.7rem 1.5rem;
  font-size: 0.95rem;
  font-weight: 600;
  border-radius: 8px;
  text-decoration: none;
  cursor: pointer;
  border: none;
  transition: all 0.2s ease-in-out;
}
.btn-primary {
  background-color: white;
  color: #4f46e5;
}
.btn-primary:hover {
  background-color: #f3f4f6;
}
.btn-secondary {
  background: rgba(255,255,255,0.2);
  color: white;
}
.btn-secondary:hover {
  background: rgba(255,255,255,0.3);
}

.event-grid {
  display: grid;
  gap: 1.5rem;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  margin-bottom: 3rem;
}

.event-card {
  background: white;
  border-radius: 10px;
  padding: 1.5rem;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.06);
  border: 1px solid #e5e7eb;
}

.event-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 0.75rem;
}

.event-title {
  font-size: 1rem;
  font-weight: bold;
  margin: 0;
}

.badge {
  padding: 0.35rem 0.75rem;
  border-radius: 999px;
  font-size: 0.75rem;
  font-weight: 600;
  background-color: #e5e7eb;
  color: #333;
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

.event-description {
  font-size: 0.9rem;
  margin-bottom: 1rem;
}

.event-meta {
  font-size: 0.85rem;
  color: #555;
  display: flex;
  flex-wrap: wrap;
  gap: 0.75rem 1.25rem;
  margin-bottom: 0.5rem;
}

.event-actions {
  margin-top: 1rem;
}
.event-actions .btn {
  display: inline-block;
  padding: 0.55rem 1.2rem;
  font-size: 0.88rem;
  font-weight: 600;
  border-radius: 6px;
  background-color: #4f46e5;
  color: white;
}

.event-actions .btn:hover {
  background-color: #4338ca;
}

.footer {
  background-color: #f3f4f6;
  text-align: center;
  padding: 2rem 0;
  font-size: 0.9rem;
  color: #6b7280;
}

@media (max-width: 768px) {
  .hamburger {
    display: block;
    color: #1f2937; /* Ensure hamburger is visible on white */
  }
  .navbar {
    flex-direction: column;
    align-items: flex-start;
  }
  .hero-minimal {
  padding: 6rem 1rem;
  background-color: #ffffff;
  text-align: center;
  border-bottom: 1px solid #e5e7eb;
}
.hero-minimal h1 {
  font-size: 2.75rem;
  font-weight: 700;
  margin-bottom: 1rem;
  color: #111827;
}
.hero-minimal p {
  font-size: 1.15rem;
  color: #4b5563;
  margin-bottom: 2rem;
}
.hero-buttons {
  display: flex;
  justify-content: center;
  gap: 1rem;
  flex-wrap: wrap;
}
.btn-outline {
  background-color: transparent;
  border: 2px solid #4f46e5;
  color: #4f46e5;
  font-weight: 600;
}
.btn-outline:hover {
  background-color: #4f46e5;
  color: white;
}
.btn-primary {
  background-color: #4f46e5;
  color: white;
  border: none;
}
.btn-primary:hover {
  background-color: #4338ca;
}
.btn,
.btn-outline,
.btn-primary {
  padding: 0.75rem 1.5rem;
  border-radius: 8px;
  font-size: 0.95rem;
  text-decoration: none;
  transition: 0.2s ease-in-out;
}

  .nav-links {
    display: none;
    flex-direction: column;
    width: 100%;
    background: #fff;
    color: #1f2937;
    padding: 1rem;
  }

  .nav-links.active {
    display: flex;
  }

  .nav-links li {
    text-align: center;
    width: 100%;
    margin: 0.5rem 0;
  }
}

</style>
    <!-- Header -->
    <header class="header">
        <nav class="navbar">
            <div class="logo"><?php echo SITE_NAME; ?></div>
            <button class="hamburger" id="hamburgerBtn" aria-label="Toggle navigation">&#9776;</button>
            <ul class="nav-links" id="navLinks">
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
        <section class="hero-minimal">
  <div class="container">
    <h1>Welcome to EventEase</h1>
    <p>A minimal school event management system for modern institutions.</p>

    <?php if (!isLoggedIn()): ?>
      <div class="hero-buttons">
        <a href="register.php" class="btn btn-primary">Get Started</a>
        <a href="login.php" class="btn btn-outline">Login</a>
      </div>
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
                                            <?php echo $event['status'] === 'pending' ? 'Apply (Pending)' : 'JOIN'; ?>
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
                                    <h3>📱 JOIN System</h3>
                <p>Simple JOIN system to track event attendance and manage capacity</p>
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
    <style>
    .hamburger {
        display: none;
        font-size: 2rem;
        background: none;
        border: none;
        color: #1f2937; /* Ensure hamburger is visible on white */
        cursor: pointer;
        margin-left: auto;
    }
    @media (max-width: 768px) {
        .navbar {
            flex-direction: column;
            align-items: flex-start;
        }
        .nav-links {
            display: none;
            flex-direction: column;
            width: 100%;
            background: #fff;
            color: #1f2937;
            padding: 1rem 0;
            margin: 0;
        }
        .nav-links.active {
            display: flex;
        }
        .hamburger {
            display: block;
        }
        .logo {
            margin-bottom: 0.5rem;
        }
        .nav-links li {
            text-align: center;
            width: 100%;
            margin: 0.5rem 0;
        }
    }
    </style>
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
    </script>
</body>
</html>
