
<?php
session_start();
session_destroy(); // ¡Corte! Matamos la memoria de la sesión
header("Location: Index.php"); // Te devolvemos a la taquilla
exit;
?>