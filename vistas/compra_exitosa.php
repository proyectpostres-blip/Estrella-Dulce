
<?php
session_start();

require_once "../conexion.php";

if (!isset($_SESSION["id_usuario"])) {
    header("Location: iniciar%20sesi%C3%B3n.php");
    exit;
}

$idUsuario = (int) $_SESSION["id_usuario"];

/*
 * Recuperar el pedido guardado después de la compra.
 */
$pedido = $_SESSION["pedido_exitoso"] ?? null;

if (
    !is_array($pedido) ||
    empty($pedido["id_pedido"])
) {
    /*
     * Si se pierde el dato temporal de la sesión,
     * consultar el último pedido del usuario.
     */
    $sql = "
        SELECT id_pedido, total, estado, fecha_pedido
        FROM pedidos
        WHERE id_usuario = :id_usuario
        ORDER BY id_pedido DESC
        LIMIT 1
    ";

    $stmt = $conexion->prepare($sql);
    $stmt->execute([
        ":id_usuario" => $idUsuario
    ]);

    $pedido = $stmt->fetch(PDO::FETCH_ASSOC);
} else {
    /*
     * Consultar el pedido y verificar que pertenezca
     * al usuario conectado.
     */
    $sql = "
        SELECT id_pedido, total, estado, fecha_pedido
        FROM pedidos
        WHERE id_pedido = :id_pedido
          AND id_usuario = :id_usuario
        LIMIT 1
    ";

    $stmt = $conexion->prepare($sql);
    $stmt->execute([
        ":id_pedido" => (int) $pedido["id_pedido"],
        ":id_usuario" => $idUsuario
    ]);

    $pedido = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (!$pedido) {
    header("Location: estado_pedido.php");
    exit;
}

$idPedido = (int) $pedido["id_pedido"];
$total = (float) $pedido["total"];
$estado = $pedido["estado"];

/*
 * Eliminar el dato temporal únicamente si corresponde
 * al pedido que se está mostrando.
 */
if (
    isset($_SESSION["pedido_exitoso"]["id_pedido"]) &&
    (int) $_SESSION["pedido_exitoso"]["id_pedido"] === $idPedido
) {
    unset($_SESSION["pedido_exitoso"]);
}

function escapar($valor): string
{
    return htmlspecialchars(
        (string) ($valor ?? ""),
        ENT_QUOTES,
        "UTF-8"
    );
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Compra exitosa - Estrella Dulce</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <link
        href="https://fonts.googleapis.com/css2?family=Fredoka:wght@400;500;600;700&family=Work+Sans:wght@400;500;600&display=swap"
        rel="stylesheet"
    >

    <style>
        :root {
            --primary: #DB1A35;
            --primary-dark: #b8152c;
        }

        body {
            margin: 0;
            background: #f8f9fa;
            font-family: 'Work Sans', sans-serif;
            color: #212529;
        }

        .top-line {
            height: 8px;
            background: var(--primary);
        }

        .confirmacion {
            max-width: 650px;
            margin: 55px auto;
            padding: 40px 25px;
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 18px rgba(0, 0, 0, .06);
            text-align: center;
        }

        .icono {
            width: 85px;
            height: 85px;
            margin: 0 auto 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #e9f7ef;
            color: #198754;
            border-radius: 50%;
            font-size: 48px;
            font-weight: bold;
        }

        h1 {
            font-family: 'Fredoka', sans-serif;
            color: var(--primary);
            font-weight: 600;
        }

        .datos-pedido {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 20px;
            margin: 25px 0;
            text-align: left;
        }

        .fila {
            display: flex;
            justify-content: space-between;
            gap: 15px;
            padding: 10px 0;
            border-bottom: 1px solid #e9ecef;
        }

        .fila:last-child {
            border-bottom: 0;
        }

        .total {
            color: var(--primary);
            font-size: 1.5rem;
            font-weight: bold;
        }

        .boton {
            display: inline-block;
            margin: 6px;
            padding: 12px 20px;
            background: var(--primary);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
        }

        .boton:hover {
            background: var(--primary-dark);
            color: white;
        }

        .boton-secundario {
            background: white;
            color: var(--primary);
            border: 1px solid var(--primary);
        }

        .boton-secundario:hover {
            background: #fff0f2;
            color: var(--primary-dark);
        }

        @media (max-width: 480px) {
            .confirmacion {
                margin: 25px auto;
                padding: 28px 16px;
            }

            .fila {
                flex-direction: column;
                gap: 4px;
            }
        }
    </style>
</head>

<body>

<div class="top-line"></div>

<main class="container">

    <section class="confirmacion">

        <div class="icono">✓</div>

        <h1>¡Compra exitosa!</h1>

        <p class="mt-3">
            Gracias por comprar en
            <strong>Estrella Dulce</strong>.
        </p>

        <p>
            Tu pedido se ha registrado correctamente.
        </p>

        <div class="datos-pedido">

            <div class="fila">
                <strong>Número de pedido</strong>
                <span>#<?= $idPedido ?></span>
            </div>

            <div class="fila">
                <strong>Fecha</strong>
                <span><?= escapar($pedido["fecha_pedido"]) ?></span>
            </div>

            <div class="fila">
                <strong>Estado</strong>
                <span><?= escapar($estado) ?></span>
            </div>

            <div class="fila">
                <strong>Total pagadero</strong>
                <span class="total">
                    $<?= number_format($total, 0, ",", ".") ?>
                </span>
            </div>

        </div>

        <p class="text-secondary">
            Puedes consultar el estado de tu pedido en cualquier momento.
        </p>

        <a href="estado_pedido.php" class="boton">
            Ver mis pedidos
        </a>

        <a href="postres.php" class="boton boton-secundario">
            Seguir comprando
        </a>

    </section>

</main>

<script>
    // Limpiar el carrito únicamente al mostrar una compra existente.
    localStorage.removeItem("carrito");

    const contador = document.getElementById("cart-count");
    if (contador) contador.textContent = "0";
</script>

</body>
</html>