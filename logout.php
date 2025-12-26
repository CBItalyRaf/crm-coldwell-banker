<?php
/**
 * CRM - Logout
 * Distrugge sessione CRM e redirect a Dashboard
 */

session_start();

// Distruggi sessione CRM
$_SESSION = array();

if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time()-42000, '/');
}

session_destroy();

// Redirect a Dashboard
header('Location: https://coldwellbankeritaly.tech/repository/dashboard/');
exit;
?>
