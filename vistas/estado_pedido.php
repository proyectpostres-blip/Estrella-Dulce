<?php
session_start();
require_once "../conexion.php";

if (!isset($_SESSION["id_usuario"])) {
    header("Location: iniciar%20sesion.php");
    exit;
}

$idUsuario = (int) $_SESSION["id_usuario"];

$sql = "
    SELECT
        id_pedido,
        total,
        estado,
        direccion_entrega,
        fecha_pedido
    FROM pedidos
    WHERE id_usuario = :id_usuario
    ORDER BY fecha_pedido DESC
";

$stmt = $conexion->prepare($sql);
$stmt->execute([
    ":id_usuario" => $idUsuario
]);

$pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

function estadoLegible($estado) {
    $estados = [
        "PENDIENTE" => "Pendiente de confirmación",
        "EN_PREPARACION" => "En preparación",
        "EN PREPARACIÓN" => "En preparación",
        "ENVIADO" => "En camino",
        "EN_CAMINO" => "En camino",
        "ENTREGADO" => "Entregado",
        "CANCELADO" => "Cancelado"
    ];

    return $estados[strtoupper(trim((string)$estado))]
        ?? ucfirst(strtolower(str_replace("_", " ", (string)$estado)));
}

function claseEstado($estado) {
    $estado = strtoupper(trim((string)$estado));

    if ($estado === "ENTREGADO") {
        return "entregado";
    }

    if ($estado === "CANCELADO") {
        return "cancelado";
    }

    if (in_array($estado, [
        "ENVIADO", "EN_CAMINO"
    ], true)) {
        return "en-camino";
    }

    if (in_array($estado, [
        "EN_PREPARACION", "EN PREPARACIÓN"
    ], true)) {
        return "preparacion";
    }

    return "pendiente";
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis pedidos | Estrella Dulce</title>

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome para iconos -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        :root {
            --primary-pink: #e61c4d;
            --gradient-pink: linear-gradient(135deg, #db1a35, #b8152c);
            --bg-light: #f8f9fa;
            --text-dark: #2c3e50;
            --text-muted: #6c757d;
            --border-color: #e9ecef;
            --shadow-sm: 0 4px 6px rgba(0, 0, 0, 0.05);
            --shadow-md: 0 8px 15px rgba(0, 0, 0, 0.08);
        }

        body {
            background-color: var(--bg-light);
            color: var(--text-dark);
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            margin: 0;
        }

        /* Navbar */
        .ed-header {
            background-color: #ffffff;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 5%;
            box-shadow: var(--shadow-sm);
        }

        .ed-header__brand {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
        }

        .ed-header__logo {
            max-width: 50px;
            height: auto;
            object-fit: contain;
        }

        .ed-header__title {
            font-weight: bold;
            font-size: 1.2rem;
            color: var(--primary-pink);
        }

        .ed-nav {
            display: flex;
            gap: 20px;
            align-items: center;
        }

        .ed-nav a {
            text-decoration: none;
            color: var(--text-dark);
            font-weight: 600;
            transition: color 0.3s;
        }

        .ed-nav a:hover {
            color: var(--primary-pink);
        }

        /* Container Principal */
        .pedidos-container {
            max-width: 900px;
            margin: 40px auto;
            padding: 0 20px;
            flex: 1;
            width: 100%;
        }

        .titulo {
            color: var(--primary-pink);
            font-size: clamp(1.8rem, 4vw, 2.3rem);
            font-weight: 700;
            margin-bottom: 5px;
        }

        /* Tarjeta de Pedido */
        .pedido-card {
            background: #ffffff;
            border-radius: 16px;
            box-shadow: var(--shadow-md);
            padding: 25px;
            margin: 20px 0;
            border: 1px solid var(--border-color);
        }

        .pedido-cabecera {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 12px;
        }

        .estado {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .pendiente { background: #fff3cd; color: #856404; }
        .preparacion { background: #cfe2ff; color: #084298; }
        .en-camino { background: #e4d9ff; color: #5a32a3; }
        .entregado { background: #d1e7dd; color: #0f5132; }
        .cancelado { background: #f8d7da; color: #842029; }

        .pedido-total {
            color: var(--primary-pink);
            font-weight: 700;
            font-size: 1.2rem;
        }

        .btn-dulce {
            display: inline-block;
            background: var(--primary-pink);
            color: white;
            padding: 10px 18px;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: background 0.2s;
        }

        .btn-dulce:hover {
            background: #c2185b;
            color: white;
        }

        .sin-pedidos {
            background: white;
            border-radius: 16px;
            padding: 45px 20px;
            text-align: center;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-color);
        }

        /* Footer Estilo Rosa / Fucsia con Logos a los lados */
        .ed-footer {
            background: var(--gradient-pink);
            color: #ffffff;
            padding: 35px 20px;
            margin-top: 50px;
        }

      .footer-right {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 15px;
      text-align: center; 
}

    .footer-right div {
    text-align: center; 
}

        .footer-container {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }

        .footer-col {
            text-align: center;
            flex: 1;
            min-width: 250px;
        }

        /* Estilos específicos para las columnas con logos */
        .footer-left {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
            text-align: left;
        }

        .footer-right {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
            text-align: right;
        }

        .footer-logo-box {
            background: #ffffff;
            padding: 8px;
            border-radius: 10px;
            display: inline-block;
            width: 75px;
            height: 75px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            flex-shrink: 0;
        }

        .footer-logo {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }

        .footer-col h3 {
            font-size: 1.05rem;
            margin-bottom: 4px;
            font-weight: 700;
        }

        .footer-col p {
            font-size: 0.85rem;
            opacity: 0.9;
            margin-bottom: 2px;
        }

        /* Botón de WhatsApp */
        .btn-whatsapp {
            display: inline-block;
            background-color: #ffffff;
            color: var(--primary-pink);
            padding: 8px 16px;
            border-radius: 25px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.85rem;
            margin-top: 6px;
            box-shadow: var(--shadow-sm);
            transition: transform 0.2s, background-color 0.2s;
        }

        .btn-whatsapp:hover {
            transform: translateY(-2px);
            background-color: #fff0f3;
            color: var(--primary-pink);
        }

        @media (max-width: 768px) {
            .footer-container {
                flex-direction: column;
                text-align: center;
            }
            .footer-left, .footer-right {
                flex-direction: column;
                text-align: center;
            }
        }
    </style>
</head>

<body>

<!-- Header / Navegación -->
<header class="ed-header">
    <a href="../index.php" class="ed-header__brand">
        <img src="../imagen/ChatGPT Image 5 nov 2025, 10_50_14 p.m..png" alt="Logo Estrella Dulce" class="ed-header__logo">
        <span class="ed-header__title">Estrella Dulce</span>
    </a>

    <nav class="ed-nav">
        <a href="postres.php">Postres</a>
        <a href="carrito.php"><i class="fa-solid fa-cart-shopping"></i> Carrito</a>
        <a href="../index.php"><i class="fa-solid fa-house"></i> Inicio</a>
    </nav>
</header>

<!-- Main Container -->
<main class="pedidos-container">
    <h1 class="titulo">📦 Mis pedidos</h1>
    <p class="text-muted mb-4">Consulta el estado de tus compras y el historial de pedidos.</p>

    <?php if (count($pedidos) === 0): ?>

        <div class="sin-pedidos">
            <h2>Aún no tienes pedidos</h2>
            <p class="text-muted">Cuando realices una compra, aparecerá aquí.</p>
            <a href="postres.php" class="btn-dulce mt-3">Ver postres</a>
        </div>

    <?php else: ?>

        <?php foreach ($pedidos as $pedido): ?>
            <article class="pedido-card">

                <div class="pedido-cabecera">
                    <div>
                        <h2 class="h5 mb-1 fw-bold">
                            Pedido #<?= (int)$pedido["id_pedido"] ?>
                        </h2>
                        <small class="text-muted">
                            Fecha: <?= htmlspecialchars((string)$pedido["fecha_pedido"], ENT_QUOTES, "UTF-8") ?>
                        </small>
                    </div>

                    <span class="estado <?= claseEstado($pedido["estado"]) ?>">
                        <?= htmlspecialchars(estadoLegible($pedido["estado"]), ENT_QUOTES, "UTF-8") ?>
                    </span>
                </div>

                <div class="mb-3">
                    <strong>Dirección de entrega:</strong>
                    <p class="text-muted mb-0"><?= htmlspecialchars((string)$pedido["direccion_entrega"], ENT_QUOTES, "UTF-8") ?></p>
                </div>

                <div class="mb-3">
                    <span class="pedido-total">
                        Total: $<?= number_format((float)$pedido["total"], 0, ",", ".") ?>
                    </span>
                </div>

                <div class="mb-0">
                    <strong>Estado actual:</strong>
                    <span class="text-muted"><?= htmlspecialchars(estadoLegible($pedido["estado"]), ENT_QUOTES, "UTF-8") ?></span>
                </div>

            </article>
        <?php endforeach; ?>

    <?php endif; ?>
</main>

<!-- Footer con Logos a los Lados -->
<footer class="ed-footer">
    <div class="footer-container">
        
        <!-- Izquierda: Logo Pascual Bravo + Estrella Dulce S.A. -->
        <div class="footer-col footer-left">
            <div class="footer-logo-box">
            <img
        src="../imagen/Logo Pascual Bravo 2.jpg"
        alt="Logo Pascual Bravo"
        class="footer-logo"
    >

            </div>
            <div>
                <h3>Estrella Dulce S.A.</h3>
                <p>Medellín, Antioquia</p>
            </div>
        </div>
        
        <!-- Centro: Atención al cliente y WhatsApp -->
        <div class="footer-col">
            <h3>Atención al cliente</h3>
            <p>Tel: +3146199121</p>
            <a href="https://wa.me/3146199121" target="_blank" class="btn-whatsapp">
                <i class="fa-brands fa-whatsapp"></i> Escríbenos por WhatsApp
            </a>
        </div>

        <!-- Derecha: Texto de derechos + Logo Félix de Bedout Moreno -->
        <div class="footer-col footer-right">
            <div>
                <p class="mb-0">© 2026 Estrella Dulce.</p>
                <p class="mb-0">Todos los derechos reservados.</p>
            </div>
            <div class="footer-logo-box">
            <img
        src="../imagen/I.E F├®lix De Bedout Moreno Escudo.png"
        alt="Logo Félix"
        class="footer-logo"
    >

            </div>
        </div>

    </div>
</footer>

</body>
</html>