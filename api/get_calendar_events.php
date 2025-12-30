<?php
// api/get_calendar_events.php
// Endpoint GET per recuperare eventi calendario pubblico
// Da usare con FullCalendar in index.php

require_once '../check_auth.php';
require_once '../config/database.php';
require_once '../helpers/calendar_events.php';

header('Content-Type: application/json');

$pdo = getDB();

// Parametri opzionali per filtrare
$start = $_GET['start'] ?? null; // YYYY-MM-DD
$end = $_GET['end'] ?? null;     // YYYY-MM-DD

// Recupera eventi
$events = getCalendarEvents($pdo, $start, $end);

// Formatta per FullCalendar
$formatted = formatEventsForCalendar($events);

echo json_encode($formatted);
