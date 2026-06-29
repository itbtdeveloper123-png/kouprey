<?php
// Show errors for debugging - remove in production
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Public entry to display the privacy policy view
require __DIR__ . '/../app/Views/privacy_policy.php';
