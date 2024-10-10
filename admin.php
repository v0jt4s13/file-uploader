<?php
// admin.php
// Panel administratora: lista folderów i formularz do tworzenia nowych

$foldersDir = __DIR__ . '/folders';
$clientsFile = $foldersDir . '/clients.json';

// Inicjalizacja pliku clients.json, jeśli nie istnieje
if (!file_exists($clientsFile)) {
    file_put_contents($clientsFile, json_encode([], JSON_PRETTY_PRINT));
}

// Pobranie danych klientów
$clientsData = json_decode(file_get_contents($clientsFile), true);

// Tworzenie folderu
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email'], $_POST['access_key'])) {
    $email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
    $access_key = preg_replace('/[^a-zA-Z0-9]/', '', $_POST['access_key']); // Prosta walidacja

    if ($email && $access_key) {
        // Generowanie hash z dodatkowym losowym elementem dla zwiększenia bezpieczeństwa
        $domain = $_SERVER['HTTP_HOST'];
        $random_bytes = bin2hex(random_bytes(16));
        $hash = hash('sha256', $domain . $email . $access_key . $random_bytes);

        // // Po utworzeniu hash i folderu
        // $clientsFile = $foldersDir . '/clients.json';
        // $clients = [];
        // if (file_exists($clientsFile)) {
        //     $clients = json_decode(file_get_contents($clientsFile), true);
        // }
        // $clients[$hash] = $email;
        // file_put_contents($clientsFile, json_encode($clients, JSON_PRETTY_PRINT));

        $folderPath = $foldersDir . '/' . $hash;
        if (!file_exists($folderPath)) {
            mkdir($folderPath, 0755, true);

            // Tworzenie pliku .htaccess
            $htaccessContent = "Options -Indexes\n";
            $htaccessContent .= "RewriteEngine On\n";
            $htaccessContent .= "RewriteCond %{REQUEST_FILENAME} -f\n";
            $htaccessContent .= "RewriteRule ^.*$ - [F,L]\n";

            file_put_contents($folderPath . '/.htaccess', $htaccessContent);

            // Aktualizacja clients.json
            $hashed_key = password_hash($access_key, PASSWORD_DEFAULT);
            $clientsData[$hash] = [
                'email' => $email,
                'access_key' => $hashed_key
            ];
            file_put_contents($clientsFile, json_encode($clientsData, JSON_PRETTY_PRINT));

            // Wysyłanie emaila do klienta
            $client_link = "https://{$_SERVER['HTTP_HOST']}/file_manager/client.php?hash=" . urlencode($hash);
            $subject = "Twoje nowe konto na File Manager";
            $message = "Witaj,\n\nTwój folder został utworzony. Możesz uzyskać dostęp do swojego folderu za pomocą poniższego linku:\n\n$client_link\n\nTwój klucz dostępu: $access_key\n\nPozdrawiam,\nAdministracja";
            $headers = "From: admin@example.com\r\n";

            // Sprawdzenie czy email został wysłany poprawnie
            if (mail($email, $subject, $message, $headers)) {
                echo "<p>Folder dla <strong>$email</strong> został utworzony. Hashtag: <strong>$hash</strong> oraz wysłano email z instrukcjami.</p>";
            } else {
                echo "<p>Folder dla <strong>$email</strong> został utworzony, ale nie udało się wysłać emaila.</p>";
            }
            
            echo "<p>Folder dla <strong>$email</strong> został utworzony.";
            // echo "Hashtag: <strong>$hash</strong>";
            echo "</p>";
        } else {
            echo "<p>Folder dla tego klienta już istnieje.</p>";
        }
    } else {
        echo "<p>Nieprawidłowy email lub klucz dostępu.</p>";
    }
}

// Aktualizacja access_key
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_hash'], $_POST['new_access_key'])) {
    $hash = preg_replace('/[^a-f0-9]/', '', $_POST['edit_hash']);
    $new_access_key = preg_replace('/[^a-zA-Z0-9]/', '', $_POST['new_access_key']);

    if (isset($clientsData[$hash]) && $new_access_key) {
        $hashed_key = password_hash($new_access_key, PASSWORD_DEFAULT);
        $clientsData[$hash]['access_key'] = $hashed_key;
        file_put_contents($clientsFile, json_encode($clientsData, JSON_PRETTY_PRINT));

        echo "<p>Klucz dostępu dla klienta <strong>{$clientsData[$hash]['email']}</strong> został zaktualizowany.</p>";
    } else {
        echo "<p>Nieprawidłowy hash lub klucz dostępu.</p>";
    }
}

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Panel Administratora</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        h1, h2 { color: #333; }
        form { margin-bottom: 30px; }
        label { display: block; margin-top: 10px; }
        input { padding: 8px; width: 300px; }
        button { padding: 10px 20px; margin-top: 10px; }
        ul { list-style-type: none; padding: 0; }
        li { margin-bottom: 10px; }
        .folder-info { border: 1px solid #ccc; padding: 10px; border-radius: 5px; }
        .folder-info strong { display: block; }
        .edit-form { margin-top: 10px; }
    </style>
</head>
<body>
    <h1>Panel Administratora</h1>

    <?php
    // Jeśli jest tryb edycji
    if (isset($_GET['edit'])):
        $edit_hash = preg_replace('/[^a-f0-9]/', '', $_GET['edit']);
        if (isset($clientsData[$edit_hash])):
            $client_email = htmlspecialchars($clientsData[$edit_hash]['email']);
            ?>
            <h2>Edytuj Klucz Dostępu dla: <?php echo $client_email; ?></h2>
            <form method="POST" action="admin.php">
                <input type="hidden" name="edit_hash" value="<?php echo htmlspecialchars($edit_hash); ?>">
                <label for="new_access_key">Nowy Klucz Dostępu:</label>
                <input type="text" name="new_access_key" id="new_access_key" required>
                <button type="submit">Zaktualizuj Klucz</button>
            </form>
            <p><a href="admin.php">Powrót do listy folderów</a></p>
            <?php
        else:
            echo "<p>Nie znaleziono klienta o podanym hash.</p>";
        endif;
    else:
        ?>

        <h2>Utwórz Nowy Folder</h2>
        <form method="POST" action="admin.php">
            <label for="email">Email Klienta:</label>
            <input type="email" name="email" id="email" required>

            <label for="access_key">Klucz Dostępu:</label>
            <input type="text" name="access_key" id="access_key" required>

            <button type="submit">Utwórz Folder</button>
        </form>

        <h2>Lista Folderów</h2>
        <ul>
            <?php if (!empty($clientsData)): ?>
                <?php foreach ($clientsData as $hash => $client): ?>
                    <li class="folder-info">
                        <strong>Email:</strong> <?php echo htmlspecialchars($client['email']); ?><br>
                        <strong>Opcje:</strong> 
                        <a href="view_folder.php?hash=<?php echo urlencode($hash); ?>">Zarządzaj</a> | 
                        <a href="client.php?hash=<?php echo urlencode($hash); ?>">Link dla Klienta</a> | 
                        <a href="admin.php?edit=<?php echo urlencode($hash); ?>">Edytuj Klucz Dostępu</a>
                    </li>
                <?php endforeach; ?>
            <?php else: ?>
                <li>Brak dostępnych folderów.</li>
            <?php endif; ?>
        </ul>
    <?php endif; ?>
</body>
</html>