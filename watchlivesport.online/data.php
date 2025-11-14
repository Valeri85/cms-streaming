<?php
$data = file_get_contents('../data/data.json');

// Allow access from any origin
header('Access-Control-Allow-Origin: *');

// Allow the following methods for cross-origin requests
header('Access-Control-Allow-Methods: GET');

// Allow the following headers for cross-origin requests
// header('Access-Control-Allow-Headers: Authorization, Content-Type');

// Define the endpoint URL
// $url = 'www/data/data.php';
$url = '/data.php';

// Check if the endpoint was requested
if ($_SERVER['REQUEST_URI'] == $url) {

  // Return the response as JSON
  header('Content-Type: application/json');

  echo  $data;

  // Stop further execution
  exit;
}
?>
