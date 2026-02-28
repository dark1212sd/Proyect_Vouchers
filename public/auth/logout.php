<?php
session_start();
session_destroy(); // Destruye la sesión
header("Location: ../login.html"); // Redirige al login
exit();
?>