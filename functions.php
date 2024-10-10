<?php
// functions.php
// Zawiera funkcje pomocnicze dla aplikacji

/**
 * Funkcja logująca zdarzenia do pliku clients.json
 *
 * @param string $hash        Unikalny hash klienta
 * @param string $eventname   Nazwa zdarzenia (np. 'client.php', 'download.php')
 * @return void
 */
function logEvent($hash, $eventname) {
    $foldersDir = __DIR__ . '/folders';
    $clientsFile = $foldersDir . '/clients.json';
    
    // Sprawdzenie, czy plik clients.json istnieje
    if (!file_exists($clientsFile)) {
        // Inicjalizacja pustego pliku JSON
        file_put_contents($clientsFile, json_encode([], JSON_PRETTY_PRINT));
    }
    
    // Pobranie danych klientów
    $clientsData = json_decode(file_get_contents($clientsFile), true);
    
    // Sprawdzenie, czy hash istnieje w danych klientów
    if (!isset($clientsData[$hash])) {
        // Opcjonalnie: Możesz zarejestrować błąd lub zignorować logowanie
        return;
    }
    
    // Funkcja do pobrania adresu IP użytkownika
    $ip = getUserIP();
    
    // Tworzenie nowego wydarzenia
    $newEvent = [
        'timestamp' => date('c'), // Format ISO 8601
        'ip' => $ip,
        'eventname' => $eventname
    ];
    
    // Inicjalizacja klucza 'events' jeśli nie istnieje
    if (!isset($clientsData[$hash]['events']) || !is_array($clientsData[$hash]['events'])) {
        $clientsData[$hash]['events'] = [];
    }
    
    // Dodanie nowego wydarzenia do tablicy 'events'
    $clientsData[$hash]['events'][] = $newEvent;
    
    // Zapisanie zaktualizowanych danych do clients.json
    file_put_contents($clientsFile, json_encode($clientsData, JSON_PRETTY_PRINT));
}

/**
 * Funkcja pobierająca adres IP użytkownika
 *
 * @return string Adres IP użytkownika
 */
function getUserIP() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        // IP z połączenia bezpośredniego
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        // IP z proxy
        // Może zawierać wiele adresów, zwróć pierwszy
        $ipList = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($ipList[0]);
    } else {
        return $_SERVER['REMOTE_ADDR'];
    }
}
?>
