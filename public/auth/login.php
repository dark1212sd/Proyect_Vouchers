<?php
session_start();
require __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $usuario = $db->usuarios->findOne(['username' => $username]);

    if ($usuario && password_verify($password, $usuario->password)) {

        $_SESSION['user_id'] = (string)$usuario->_id;
        $_SESSION['role'] = $usuario->role; // Aquí capturamos: vecino, admin o superuser
        $_SESSION['username'] = $usuario->username;

        // Lógica de redirección por roles
        switch ($usuario->role) {
            case 'superuser':
                header("Location: ../super_dashboard.php");
                break;
            case 'admin':
                header("Location: ../dashboard.php");
                break;
            case 'vecino':
                header("Location: ../user_panel.php");
                break;
            default:
                header("Location: login.html?error=role");
                break;
        }
        exit();
    } else {
        echo "<script>alert('Credenciales inválidas'); window.location.href='login.html';</script>";
    }
}