<?php
// view_folder.php
// Wyświetlanie zawartości wybranego folderu, dodawanie i usuwanie plików

$foldersDir = __DIR__ . '/folders';
$clientsFile = $foldersDir . '/clients.json';

// Pobranie danych klientów
$clientsData = json_decode(file_get_contents($clientsFile), true);
// $stored_key = $clientsData[$hash]['access_key'];

// Ujednolicenie komunikatu błędów
function show_error() {
    echo "<p>Wystąpił problem z dostępem do folderu.</p>";
}

if (!isset($_GET['hash'])) {
    die(show_error());
}

$hash = preg_replace('/[^a-f0-9]/', '', $_GET['hash']); // Walidacja hash
$folderPath = $foldersDir . '/' . $hash;
$client_email = $clientsData[$hash]['email'];

if (!file_exists($folderPath)) {
    die(show_error());
}

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
        $uploadFile = $folderPath . '/' . $fileName;

        // Unikalna nazwa pliku, aby uniknąć nadpisywania
        if (file_exists($uploadFile)) {
            $fileName = pathinfo($fileName, PATHINFO_FILENAME) . '_' . time() . '.' . $fileExtension;
            $uploadFile = $folderPath . '/' . $fileName;
        }

        if (move_uploaded_file($fileTmpPath, $uploadFile)) {
            // echo "<p>Plik został przesłany.</p>";

            // Wysyłanie emaila do klienta o przesłaniu pliku
            $client_link = "https://{$_SERVER['HTTP_HOST']}/file_manager/folders/" . urlencode($hash) . "";
            $subject = "Na Twoim koncie pojawił się nowy plik";
            $message = "Witaj,\n\nW Twoim folderze pojawił się nowy plik. Zapisz plik na dysku za pomocą poniższego linku:\n\n$client_link\n\nPozdrawiam,\nAdministracja";
            $headers = "From: admin@example.com\r\n";

            // Sprawdzenie czy email został wysłany poprawnie
            if (mail($client_email, $subject, $message, $headers)) {
                echo "<p>Nowy plik dla <strong>$client_email</strong> został utworzony. Wysłano email z instrukcjami.</p>";
            } else {
                echo "<p>Nowy plik dla <strong>$client_email</strong> został utworzony, ale nie udało się wysłać emaila.</p>";
            }

        } else {
            echo "<p>Błąd podczas przesyłania pliku.</p>";
        }
    } else {
        echo "<p>Dozwolone są tylko pliki graficzne (JPEG, PNG, GIF) oraz PDF.</p>";
    }
}

// Usuwanie pliku
if (isset($_GET['delete'])) {
    $fileToDelete = basename($_GET['delete']);
    $filePath = $folderPath . '/' . $fileToDelete;
    if (file_exists($filePath)) {
        unlink($filePath);
        echo "<p>Plik został usunięty.</p>";
    }
}

// Lista plików
// $files = array_diff(scandir($folderPath), array('.', '..', '.htaccess'));
$files = array_diff(scandir($folderPath), array('.', '..', '..htaccess', '..htaccess.swp', '.htaccess'));
// Sortowanie plików wg daty uploadu (od najnowszych)
usort($files, function($a, $b) use ($folderPath) {
    $timeA = filemtime($folderPath . '/' . $a);
    $timeB = filemtime($folderPath . '/' . $b);
    return $timeB - $timeA; // Od najnowszych do najstarszych
});
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Zarządzaj Folderem</title>
</head>
<body>
    <h1>Zarządzaj Folderem: <?php echo htmlspecialchars($client_email); ?></h1>

    <h2>Prześlij Nowy Plik</h2>
    <form method="POST" action="view_folder.php?hash=<?php echo urlencode($hash); ?>" enctype="multipart/form-data">
        <input type="file" name="file" required>
        <button type="submit">Prześlij</button>
    </form>

    <h2>Pliki w Folderze</h2>
    <ul>
        <?php foreach ($files as $file): ?>
            <li>
                <?php echo htmlspecialchars($file); ?>
                - <a href="folders/<?php echo urlencode($hash . '/' . $file); ?>" target="_blank">Pobierz</a>
                - <a href="view_folder.php?hash=<?php echo urlencode($hash); ?>&delete=<?php echo urlencode($file); ?>" onclick="return confirm('Na pewno usunąć ten plik?');">Usuń</a>
            </li>
        <?php endforeach; ?>
    </ul>

    <p><a href="admin.php">Powrót do Panelu Administratora</a></p>
</body>
</html>
