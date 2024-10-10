<?php
// logout.php
session_start();
session_unset();
session_destroy();
header("Location: client.php?hash=" . urlencode($_GET['hash'] ?? ''));
exit;
?>
