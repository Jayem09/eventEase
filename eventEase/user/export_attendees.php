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

// Get event ID and format from URL
$event_id = (int)($_GET['event_id'] ?? 0);
$format = $_GET['format'] ?? 'csv';

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

if ($format === 'csv') {
    // Export as CSV
    $filename = 'attendees_' . $event['title'] . '_' . date('Y-m-d') . '.csv';
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Create CSV content
    $output = fopen('php://output', 'w');
    
    // Add headers
    fputcsv($output, [
        'Event Title',
        'Event Date',
        'Event Time',
        'Location',
        'Attendee Name',
        'Email',
        'Role',
        'Department',
        'Status',
        'Response Date'
    ]);
    
    // Add data rows
    foreach ($attendees as $attendee) {
        fputcsv($output, [
            $event['title'],
            date('M j, Y', strtotime($event['event_date'])),
            date('g:i A', strtotime($event['event_time'])),
            $event['location'],
            $attendee['full_name'],
            $attendee['email'],
            ucfirst($attendee['role']),
            $attendee['department'] ?: '-',
            ucfirst($attendee['status']),
            date('M j, Y g:i A', strtotime($attendee['created_at']))
        ]);
    }
    
    fclose($output);
    exit;
} else {
    // For PDF or other formats, redirect back with message
    setFlashMessage('info', 'PDF export feature coming soon. Please use CSV export for now.');
    redirect('view_attendees.php?event_id=' . $event_id);
}
?> 