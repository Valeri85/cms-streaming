<?php
/**
 * CMS Bootstrap File
 * 
 * Centralized initialization for all CMS pages.
 * This file handles:
 * - Session start
 * - Loading config and functions
 * - Login verification
 * 
 * Usage: require_once __DIR__ . '/includes/bootstrap.php';
 * (from cms/ directory)
 * 
 * Or for files in subdirectories:
 * require_once dirname(__DIR__) . '/includes/bootstrap.php';
 */

// ==========================================
// START SESSION
// ==========================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ==========================================
// LOAD CONFIGURATION AND FUNCTIONS
// ==========================================
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

// ==========================================
// CHECK LOGIN (with redirect)
// Supports both old 'admin_id' and new 'user_id' session keys
// ==========================================
if (!isset($_SESSION['admin_id']) && !isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}