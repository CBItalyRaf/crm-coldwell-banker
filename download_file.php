<?php
require_once 'check_auth.php';
require_once 'config/database.php';

$file_id = $_GET['id'] ?? '';

if (!$file_id) {
    die("File non specificato");
}

$pdo = getDB();

// Carica file info
$stmt = $pdo->prepare("SELECT * FROM agency_contract_files WHERE id = :id");
$stmt->execute(['id' => $file_id]);
$file = $stmt->fetch();

if (!$file) {
    die("File non trovato");
}

// Verifica che il file esista
$filepath = __DIR__ . '/' . $file['file_path'];

if (!file_exists($filepath)) {
    die("File non trovato sul filesystem");
}

// Determina MIME type
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $filepath);
finfo_close($finfo);

// Invia headers per download
header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename="' . basename($file['file_name']) . '"');
header('Content-Length: ' . filesize($filepath));
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');

// Output file
readfile($filepath);
exit;
