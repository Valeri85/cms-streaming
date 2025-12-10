<?php
/**
 * CMS Logout
 * 
 * Destroys session and redirects to login page
 * 
 * Location: /var/www/u1852176/data/www/watchlivesport.online/logout.php
 */

session_start();
session_destroy();

header('Location: login.php');
exit;