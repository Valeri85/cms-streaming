<?php
/**
 * CMS Header Component
 * 
 * Contains HTML doctype, head section, and opens body/layout.
 * 
 * Usage:
 *   $pageTitle = 'Dashboard';           // Required: Page title
 *   $currentPage = 'dashboard';         // Optional: For sidebar active state
 *   $extraCss = ['css/custom.css'];     // Optional: Additional CSS files
 *   $bodyClass = '';                    // Optional: Body class
 *   $bodyData = ['key' => 'value'];     // Optional: data-* attributes
 *   include __DIR__ . '/includes/header.php';
 */

// Defaults
$pageTitle = $pageTitle ?? 'CMS';
$currentPage = $currentPage ?? basename($_SERVER['SCRIPT_NAME'], '.php');
$extraCss = $extraCss ?? [];
$bodyClass = $bodyClass ?? '';
$bodyData = $bodyData ?? [];

// Build body data attributes string
$bodyDataStr = '';
foreach ($bodyData as $key => $value) {
    $bodyDataStr .= ' data-' . htmlspecialchars($key) . '="' . htmlspecialchars($value) . '"';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - CMS</title>
    <link rel="stylesheet" href="cms-style.css">
    <?php foreach ($extraCss as $cssFile): ?>
        <link rel="stylesheet" href="<?php echo htmlspecialchars($cssFile); ?>">
    <?php endforeach; ?>
</head>
<body<?php echo $bodyClass ? ' class="' . htmlspecialchars($bodyClass) . '"' : ''; ?><?php echo $bodyDataStr; ?>>
    <div class="cms-layout">
        <?php include __DIR__ . '/sidebar.php'; ?>
        
        <main class="cms-main">