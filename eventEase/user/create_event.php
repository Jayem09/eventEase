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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $event_date = $_POST['event_date'] ?? '';
    $event_time = $_POST['event_time'] ?? '';
    $location = trim($_POST['location'] ?? '');
    $max_capacity = (int)($_POST['max_capacity'] ?? 0);
    
    // Validation
    $errors = [];
    
    if (empty($title)) {
        $errors[] = 'Event title is required.';
    }
    
    if (empty($description)) {
        $errors[] = 'Event description is required.';
    }
    
    if (empty($event_date)) {
        $errors[] = 'Event date is required.';
    } elseif (strtotime($event_date) < strtotime(date('Y-m-d'))) {
        $errors[] = 'Event date cannot be in the past.';
    }
    
    if (empty($event_time)) {
        $errors[] = 'Event time is required.';
    }
    
    if (empty($location)) {
        $errors[] = 'Event location is required.';
    }
    
    if ($max_capacity <= 0) {
        $errors[] = 'Maximum capacity must be greater than 0.';
    }
    
    if (empty($errors)) {
        $pdo = getDBConnection();
        
        $stmt = $pdo->prepare("
            INSERT INTO events (title, description, event_date, event_time, location, max_capacity, created_by) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        if ($stmt->execute([$title, $description, $event_date, $event_time, $location, $max_capacity, $_SESSION['user_id']])) {
            setFlashMessage('success', 'Event created successfully! It is now pending admin approval.');
            redirect('dashboard.php');
        } else {
            $errors[] = 'Failed to create event. Please try again.';
        }
    }
    
    if (!empty($errors)) {
        setFlashMessage('danger', implode(' ', $errors));
        redirect('dashboard.php');
    }
} else {
    // If not POST request, redirect to dashboard
    redirect('dashboard.php');
}
?> 