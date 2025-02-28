<?php
session_start();

// Logout logic
if (isset($_POST['logout'])) {
    session_destroy();
    header("Location: index.php"); // Redirect to login page
    exit();
}

require_once 'db_connect.php';

// --- Collapsible Sections State Management ---
// Initialize session variables for collapsible sections if they don't exist
if (!isset($_SESSION['addTaskCollapsed'])) {
    $_SESSION['addTaskCollapsed'] = true; // Initially collapsed
}
if (!isset($_SESSION['addUserCollapsed'])) {
    $_SESSION['addUserCollapsed'] = true; // Initially collapsed
}
if (!isset($_SESSION['summaryCollapsed'])) {
    $_SESSION['summaryCollapsed'] = true; // Initially collapsed
}
error_reporting(E_ALL);
ini_set('display_errors', 1);
?>