<?php

// =====================================================
// CONEXIÓN A LA BASE DE DATOS - ESTRELLA DULCE
// =====================================================

$servidor = "localhost";
$usuario = "root";
$password = "";
$base_datos = "estrella_dulce";

try {

    $conexion = new PDO(
        "mysql:host=$servidor;dbname=$base_datos;charset=utf8mb4",
        $usuario,
        $password
    );

    // CONFIGURAR MYSQL CON HORARIO DE COLOMBIA (UTC-5)
    date_default_timezone_set('America/Bogota');
    $conexion->exec("SET time_zone = '-05:00'");

    // Mostrar errores de PDO como excepciones
    $conexion->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Retornar los datos como arreglos asociativos
    $conexion->setAttribute(
        PDO::ATTR_DEFAULT_FETCH_MODE,
        PDO::FETCH_ASSOC
    );

} catch (PDOException $e) {

    die("Error de conexión con la base de datos: " . $e->getMessage());

}
?>

