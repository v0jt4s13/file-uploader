<?php
// client.php
// Interfejs klienta do przeglądania i przesyłania plików z wymogiem klucza dostępu

// Inicjalizacja sesji
session_start();

// Dołączenie pliku functions.php
require_once 'functions.php';

$foldersDir = __DIR__ . '/folders';
$clientsFile = $foldersDir . '/clients.json';

// Pobranie danych klientów
$clientsData = json_decode(file_get_contents($clientsFile), true);

// Ujednolicenie komunikatu błędów
function show_error() {
    echo "<p>Wystąpił problem z dostępem do folderu.</p>";
}

// Sprawdzenie, czy hash został podany
if (!isset($_GET['hash'])) {
    die(show_error());
}

$hash = preg_replace('/[^a-f0-9]/', '', $_GET['hash']); // Walidacja hash
$folderPath = $foldersDir . '/' . $hash;

// Sprawdzenie, czy folder istnieje i czy hash jest w clients.json
if (!file_exists($folderPath) || !isset($clientsData[$hash])) {
    die(show_error());
}

// Jeśli użytkownik nie jest zalogowany, wymaga podania klucza dostępu
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== $hash) {
    // Obsługa formularza logowania
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['access_key'])) {
        $input_key = preg_replace('/[^a-zA-Z0-9]/', '', $_POST['access_key']);
        $stored_key = $clientsData[$hash]['access_key'];

        if (password_verify($input_key, $clientsData[$hash]['access_key']) == 1) {
            // Uwierzytelnienie użytkownika
            $_SESSION['authenticated'] = $hash;
            // Logowanie odwiedzin w client.php
            logEvent($hash, 'client.php');

            header("Location: client.php?hash=" . urlencode($hash));
            exit;
        } else {
            $error_message = "Nieprawidłowy klucz dostępu.";
        }
    }

    // Wyświetlenie formularza logowania
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Logowanie Klienta</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            h1 { color: #333; }
            form { max-width: 400px; margin: auto; }
            label { display: block; margin-top: 10px; }
            input { padding: 8px; width: 100%; }
            button { padding: 10px 20px; margin-top: 10px; }
            .error { color: red; }
        </style>
    </head>
    <body>
        <h1>Logowanie Klienta</h1>
        <?php if (isset($error_message)): ?>
            <p class="error"><?php echo htmlspecialchars($error_message); ?></p>
        <?php endif; ?>
        <form method="POST" action="client.php?hash=<?php echo urlencode($hash); ?>">
            <label for="access_key">Klucz Dostępu:</label>
            <input type="text" name="access_key" id="access_key" required>
            <button type="submit">Zaloguj się</button>
        </form>
    </body>
    </html>
    <?php
    exit;
}

// Teraz użytkownik jest uwierzytelniony, możemy wyświetlić zawartość folderu

// Przesyłanie plików
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf'];

    $fileTmpPath = $_FILES['file']['tmp_name'];
    $fileName = basename($_FILES['file']['name']);
    $fileSize = $_FILES['file']['size'];
    $fileType = mime_content_type($fileTmpPath);
    $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

    // Sprawdzenie typu MIME i rozszerzenia
    if (in_array($fileType, $allowedMimeTypes) && in_array($fileExtension, $allowedExtensions)) {
        // Opcjonalnie: ograniczenie rozmiaru pliku (np. 5MB)
        $maxFileSize = 5 * 1024 * 1024; // 5 MB
        if ($fileSize > $maxFileSize) {
            echo "<p>Plik jest za duży. Maksymalny rozmiar to 5 MB.</p>";
        } else {
            $uploadFile = $folderPath . '/' . $fileName;

            // Unikalna nazwa pliku, aby uniknąć nadpisywania
            if (file_exists($uploadFile)) {
                $fileName = pathinfo($fileName, PATHINFO_FILENAME) . '_' . time() . '.' . $fileExtension;
                $uploadFile = $folderPath . '/' . $fileName;
            }

            if (move_uploaded_file($fileTmpPath, $uploadFile)) {
                // Logowanie zdarzenia przesłania pliku
                // logEvent($hash, 'client_file_upload');
                echo "<p>Plik został przesłany.</p>";
            } else {
                echo "<p>Błąd podczas przesyłania pliku.</p>";
            }
        }
    } else {
        echo "<p>Dozwolone są tylko pliki graficzne (JPEG, PNG, GIF) oraz PDF.</p>";
    }
}

// Usuwanie pliku (tylko dla administratora, jeśli wymagane)
// if (isset($_GET['delete'])) {
//     $fileToDelete = basename($_GET['delete']);
//     $filePath = $folderPath . '/' . $fileToDelete;
//     if (file_exists($filePath)) {
//         unlink($filePath);
//         echo "<p>Plik został usunięty.</p>";
//     }
// }


// Lista plików
$files = array_diff(scandir($folderPath), array('.', '..', '..htaccess', '..htaccess.swp', '.htaccess'));
// echo $files;

// Pobranie plików z czasem modyfikacji
$files_with_time = [];
foreach ($files as $file) {
    // echo $file;
    $filePath = $folderPath . '/' . $file;
    if (is_file($filePath)) {
        $files_with_time[$file] = filemtime($filePath);
    }
}

// Sortowanie plików wg daty uploadu (najnowsze na początku)
arsort($files_with_time);

// Teraz $files_with_time jest posortowana wg czasu, od najnowszych do najstarszych
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Panel Klienta</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        h1, h2 { color: #333; }
        form { margin-bottom: 30px; }
        label { display: block; margin-top: 10px; }
        input { padding: 8px; width: 300px; }
        button { padding: 10px 20px; margin-top: 10px; }
        ul { list-style-type: none; padding: 0; }
        li { margin-bottom: 10px; }
        .file-info { border: 1px solid #ccc; padding: 10px; border-radius: 5px; }
    </style>
</head>
<body>
    <h1>Panel Klienta</h1>

    <!-- <h2>Prześlij Nowy Plik</h2>
    <form method="POST" action="client.php?hash=<?php echo urlencode($hash); ?>" enctype="multipart/form-data">
        <input type="file" name="file" required>
        <button type="submit">Prześlij</button>
    </form>
    //-->
    <h2>Dostępne Pliki</h2>
    <ul>
        <?php if (!empty($files_with_time)): ?>
            <?php foreach ($files_with_time as $file => $mtime): ?>
                <li class="file-info">
                    <b><?php echo date("Y-m-d H:i:s", $mtime); 
                        $file_url = 'folders/' . $hash . '/' . $file;
                        // urlencode($hash . '/' . $file)
                    ?></b>
                    - <a href="download.php?hash=<?php echo urlencode($hash); ?>&file=<?php echo urlencode($file); ?>">Pobierz <?php echo htmlspecialchars($file); ?></a>
                    <?php // <a href="<?php echo $file_url; ? >" target="_blank">Pobierz <?php echo htmlspecialchars($file); ? ></a> ?>
                </li>
            <?php endforeach; ?>
        <?php else: ?>
            <li>Brak dostępnych plików.</li>
        <?php endif; ?>
    </ul>

    <p><a href="logout.php">Wyloguj się</a></p>
    <p><a href="logout.php?hash=<?php echo urlencode($hash); ?>">Wyloguj się 2</a></p>
</body>
</html>
