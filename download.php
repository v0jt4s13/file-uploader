<?php
// download.php
session_start();

// Dołączenie pliku functions.php
require_once 'functions.php';

$foldersDir = __DIR__ . '/folders';
$clientsFile = $foldersDir . '/clients.json';

// Pobranie danych klientów
$clientsData = json_decode(file_get_contents($clientsFile), true);

// Sprawdzenie, czy hash i file są podane
if (!isset($_GET['hash']) || !isset($_GET['file'])) {
    http_response_code(400);
    echo "Nieprawidłowe żądanie.";
    exit;
}

$hash = preg_replace('/[^a-f0-9]/', '', $_GET['hash']);
$file = basename($_GET['file']); // Zapobieganie Directory Traversal

$folderPath = $foldersDir . '/' . $hash;
$filePath = $folderPath . '/' . $file;

// Sprawdzenie, czy folder i plik istnieją
if (!file_exists($folderPath) || !file_exists($filePath) || !isset($clientsData[$hash])) {
    http_response_code(403);
    echo "Dostęp zabroniony.";
    exit;
}

// Sprawdzenie, czy użytkownik jest zalogowany i ma dostęp do tego folderu
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== $hash) {
    http_response_code(403);
    echo "Dostęp zabroniony.";
    exit;
}

// Logowanie zdarzenia pobrania pliku
logEvent($hash, 'download.php');

// Ustawienie nagłówków do pobierania pliku
$mimeType = mime_content_type($filePath);
header('Content-Description: File Transfer');
header('Content-Type: ' . $mimeType);
header('Content-Disposition: attachment; filename="' . basename($filePath) . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($filePath));

// Odczyt i wyświetlenie pliku
readfile($filePath);
exit;
?>
