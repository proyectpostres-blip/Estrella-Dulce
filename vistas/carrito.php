<?php
session_start();

require_once "../conexion.php";

/* =====================================================
   1. VERIFICAR SESIÓN
   ===================================================== */

if (!isset($_SESSION["id_usuario"])) {
    // Guardar el destino para regresar al carrito después del login.
    $_SESSION["redirigir_despues_login"] = "carrito.php";

    header("Location: iniciar_sesion.php");
    exit;
}

$idUsuario = (int) $_SESSION["id_usuario"];
$nombreUsuario = $_SESSION["nombre"] ?? "";
$apellidoUsuario = $_SESSION["apellido"] ?? "";
$mensajeError = "";

/* =====================================================
   2. FUNCIONES AUXILIARES
   ===================================================== */

function escapar($valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, "UTF-8");
}

/* =====================================================
   3. PROCESAR Y FINALIZAR PEDIDO
   ===================================================== */

if (
    $_SERVER["REQUEST_METHOD"] === "POST"
    && isset($_POST["finalizar_pedido"])
) {
    $carritoJson = $_POST["carrito_json"] ?? "";
    $direccion = trim($_POST["direccion_entrega"] ?? "");
    $telefono = trim($_POST["telefono_contacto"] ?? "");
    $observaciones = trim($_POST["observaciones"] ?? "");
    $metodoPago = trim($_POST["metodo_pago"] ?? "");

    $metodosPermitidos = [
        "Efectivo",
        "Transferencia bancaria",
        "Pago contra entrega"
    ];

    if ($direccion === "") {
        $mensajeError = "Debes ingresar la dirección de entrega.";
    } elseif ($telefono === "") {
        $mensajeError = "Debes ingresar un teléfono de contacto.";
    } elseif (!in_array($metodoPago, $metodosPermitidos, true)) {
        $mensajeError = "Selecciona un método de pago válido.";
    } elseif ($carritoJson === "" || strlen($carritoJson) > 100000) {
        $mensajeError = "El carrito está vacío o es demasiado grande.";
    } else {
        $carrito = json_decode($carritoJson, true);

        if (
            !is_array($carrito)
            || empty($carrito)
            || json_last_error() !== JSON_ERROR_NONE
        ) {
            $mensajeError = "El carrito no contiene productos válidos.";
        } else {
            try {
                /* Consolidar productos repetidos y validar cantidades. */
                $cantidades = [];

                foreach ($carrito as $item) {
                    if (!is_array($item)) {
                        throw new Exception("Producto inválido en el carrito.");
                    }

                    $idPostre = filter_var(
                        $item["id_postre"] ?? null,
                        FILTER_VALIDATE_INT
                    );

                    $cantidad = filter_var(
                        $item["cantidad"] ?? null,
                        FILTER_VALIDATE_INT
                    );

                    if (
                        !$idPostre || $idPostre < 1
                        || !$cantidad || $cantidad < 1
                        || $cantidad > 100
                    ) {
                        throw new Exception(
                            "Hay un producto o una cantidad inválida."
                        );
                    }

                    $cantidades[$idPostre] =
                        ($cantidades[$idPostre] ?? 0) + $cantidad;

                    if ($cantidades[$idPostre] > 100) {
                        throw new Exception(
                            "La cantidad máxima por producto es 100."
                        );
                    }
                }

                $conexion->beginTransaction();

                $total = 0;
                $productosPedido = [];

                /* Consultar precios y bloquear los productos. */
                $sqlProducto = "
                    SELECT id_postre, nombre, precio, stock, estado
                    FROM postres
                    WHERE id_postre = :id_postre
                    FOR UPDATE
                ";

                $stmtProducto = $conexion->prepare($sqlProducto);

                foreach ($cantidades as $idPostre => $cantidad) {
                    $stmtProducto->execute([
                        ":id_postre" => $idPostre
                    ]);

                    $productoBD = $stmtProducto->fetch(PDO::FETCH_ASSOC);

                    if (!$productoBD) {
                        throw new Exception(
                            "Uno de los productos ya no existe."
                        );
                    }

                    if ($productoBD["estado"] !== "DISPONIBLE") {
                        throw new Exception(
                            "El producto "
                            . $productoBD["nombre"]
                            . " ya no está disponible."
                        );
                    }

                    if ($cantidad > (int) $productoBD["stock"]) {
                        throw new Exception(
                            "No hay suficiente stock de "
                            . $productoBD["nombre"]
                            . ". Disponible: "
                            . (int) $productoBD["stock"]
                            . "."
                        );
                    }

                    $precio = (float) $productoBD["precio"];

                    if (!is_finite($precio) || $precio < 0) {
                        throw new Exception(
                            "El precio del producto no es válido."
                        );
                    }

                    $subtotal = round($precio * $cantidad, 2);
                    $total = round($total + $subtotal, 2);

                    $productosPedido[] = [
                        "id_postre" => (int) $productoBD["id_postre"],
                        "cantidad" => $cantidad,
                        "precio" => $precio,
                        "subtotal" => $subtotal
                    ];
                }

                /* Preparar las observaciones del pedido. */
                $observacionesFinales =
                    "Método de pago: " . $metodoPago;

                if ($observaciones !== "") {
                    $observacionesFinales .= " | " . $observaciones;
                }

                /* Registrar el pedido. */
                $sqlPedido = "
                    INSERT INTO pedidos (
                        id_usuario,
                        total,
                        estado,
                        direccion_entrega,
                        telefono_contacto,
                        observaciones,
                        fecha_pedido
                    )
                    VALUES (
                        :id_usuario,
                        :total,
                        :estado,
                        :direccion,
                        :telefono,
                        :observaciones,
                        NOW()
                    )
                ";

                $stmtPedido = $conexion->prepare($sqlPedido);

                $stmtPedido->execute([
                    ":id_usuario" => $idUsuario,
                    ":total" => $total,
                    ":estado" => "PENDIENTE",
                    ":direccion" => $direccion,
                    ":telefono" => $telefono,
                    ":observaciones" => $observacionesFinales
                ]);

                $idPedido = (int) $conexion->lastInsertId();

                if ($idPedido <= 0) {
                    throw new Exception(
                        "No fue posible registrar el pedido."
                    );
                }

                /* Registrar detalles y descontar inventario. */
                $sqlDetalle = "
                    INSERT INTO detalle_pedidos (
                        id_pedido,
                        id_postre,
                        cantidad,
                        precio_unitario,
                        subtotal
                    )
                    VALUES (
                        :id_pedido,
                        :id_postre,
                        :cantidad,
                        :precio,
                        :subtotal
                    )
                ";

                $stmtDetalle = $conexion->prepare($sqlDetalle);

                $sqlStock = "
                    UPDATE postres
                    SET stock = stock - :cantidad
                    WHERE id_postre = :id_postre
                      AND stock >= :cantidad_stock
                      AND estado = 'DISPONIBLE'
                ";

                $stmtStock = $conexion->prepare($sqlStock);

                foreach ($productosPedido as $producto) {
                    $stmtDetalle->execute([
                        ":id_pedido" => $idPedido,
                        ":id_postre" => $producto["id_postre"],
                        ":cantidad" => $producto["cantidad"],
                        ":precio" => $producto["precio"],
                        ":subtotal" => $producto["subtotal"]
                    ]);

                    $stmtStock->execute([
                        ":cantidad" => $producto["cantidad"],
                        ":id_postre" => $producto["id_postre"],
                        ":cantidad_stock" => $producto["cantidad"]
                    ]);

                    if ($stmtStock->rowCount() !== 1) {
                        throw new Exception(
                            "No se pudo actualizar el inventario. "
                            . "Verifica la disponibilidad de los productos."
                        );
                    }
                }

                /* Confirmar la transacción. */
                $conexion->commit();

                $_SESSION["pedido_exitoso"] = [
                    "id_pedido" => $idPedido,
                    "total" => $total
                ];

                header("Location: compra_exitosa.php");
                exit;

            } catch (PDOException $e) {
                if ($conexion->inTransaction()) {
                    $conexion->rollBack();
                }

                error_log($e->getMessage());

                $mensajeError =
                    "No fue posible procesar el pedido. Intenta nuevamente.";

            } catch (Exception $e) {
                if ($conexion->inTransaction()) {
                    $conexion->rollBack();
                }

                $mensajeError = $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Mi carrito | Estrella Dulce</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link
        href="https://fonts.googleapis.com/css2?family=Fredoka:wght@400;500;600;700&family=Work+Sans:wght@400;500;600;700&display=swap"
        rel="stylesheet"
    >

    <style>
        :root {
            --rojo: #db1a35;
            --rojo-oscuro: #b8152c;
            --verde-whatsapp: #25d366;
            --verde-whatsapp-oscuro: #128c4a;
            --verde: #198754;
            --blanco: #fff;
            --fondo: #f8f9fa;
            --gris-100: #f1f3f5;
            --gris-200: #e9ecef;
            --gris-300: #dee2e6;
            --gris-400: #ced4da;
            --gris-600: #6c757d;
            --gris-700: #495057;
            --gris-900: #212529;
            --radio: 14px;
            --sombra: 0 8px 30px rgba(0, 0, 0, .07);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            background: var(--fondo);
            color: var(--gris-900);
            font-family: "Work Sans", sans-serif;
        }

        a {
            color: inherit;
        }

        button,
        input,
        textarea,
        select {
            font: inherit;
        }

        button,
        a {
            -webkit-tap-highlight-color: transparent;
        }

        /* ENCABEZADO */

        .ed-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            flex-wrap: wrap;
            padding: 12px 5%;
            background: var(--rojo);
            box-shadow: 0 3px 12px rgba(219, 26, 53, .2);
        }

        .ed-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
        }

        .ed-logo {
            width: 50px;
            height: 50px;
            padding: 3px;
            border: 2px solid var(--blanco);
            border-radius: 50%;
            background: var(--blanco);
            object-fit: contain;
        }

        .ed-brand h1 {
            color: var(--blanco);
            font: 600 1.6rem "Fredoka", sans-serif;
        }

        .ed-nav {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 22px;
            flex-wrap: wrap;
        }

        .ed-nav a {
            color: var(--blanco);
            font-size: .95rem;
            font-weight: 600;
            text-decoration: none;
            transition: opacity .2s;
        }

        .ed-nav a:hover {
            opacity: .8;
        }

        /* BOTONES GENERALES */

        .btn-ed {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            min-height: 44px;
            padding: 11px 18px;
            border: 1px solid transparent;
            border-radius: 9px;
            cursor: pointer;
            font-size: .95rem;
            font-weight: 600;
            text-align: center;
            text-decoration: none;
            transition:
                background .2s,
                border-color .2s,
                color .2s,
                transform .2s,
                box-shadow .2s;
        }

        .btn-ed:focus-visible,
        .whatsapp-btn:focus-visible {
            outline: 3px solid #ffbf47;
            outline-offset: 3px;
        }

        .btn-primary {
            background: var(--rojo);
            color: var(--blanco);
        }

        .btn-primary:hover {
            background: var(--rojo-oscuro);
        }

        .btn-outline {
            border-color: var(--gris-300);
            background: var(--blanco);
            color: var(--gris-900);
        }

        .btn-outline:hover {
            background: var(--gris-100);
        }

        .btn-danger {
            background: #dc3545;
            color: var(--blanco);
        }

        .btn-danger:hover {
            background: #b02a37;
        }

        .btn-success {
            background: var(--verde);
            color: var(--blanco);
        }

        .btn-success:hover {
            background: #146c43;
        }

        /* CONTENEDOR DEL CARRITO */

        main {
            flex: 1;
            width: 100%;
        }

        .contenedor-carrito {
            width: min(900px, calc(100% - 32px));
            margin: 32px auto;
            padding: 30px;
            border-top: 5px solid var(--rojo);
            border-radius: 16px;
            background: var(--blanco);
            box-shadow: var(--sombra);
        }

        .titulo-carrito {
            margin-bottom: 25px;
            font: 600 2rem "Fredoka", sans-serif;
            text-align: center;
        }

        .alerta-error {
            margin-bottom: 20px;
            padding: 14px 16px;
            border: 1px solid #f1aeb5;
            border-radius: 10px;
            background: #f8d7da;
            color: #842029;
            line-height: 1.5;
        }

        /* CARRITO VACÍO */

        .carrito-vacio {
            padding: 30px 10px;
            color: var(--gris-600);
            text-align: center;
        }

        .carrito-vacio .emoji {
            display: block;
            margin-bottom: 12px;
            font-size: 3.5rem;
        }

        .carrito-vacio h2 {
            margin-bottom: 10px;
            color: var(--gris-900);
            font: 600 1.5rem "Fredoka", sans-serif;
        }

        .carrito-vacio p {
            margin-bottom: 18px;
            line-height: 1.6;
        }

        /* PRODUCTOS */

        .producto-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            margin-bottom: 12px;
            padding: 18px;
            border: 1px solid var(--gris-200);
            border-radius: 12px;
            background: var(--fondo);
            transition: border-color .2s, box-shadow .2s;
        }

        .producto-item:hover {
            border-color: #ef9aa8;
            box-shadow: 0 4px 12px rgba(219, 26, 53, .06);
        }

        .producto-info {
            display: flex;
            flex: 1;
            flex-direction: column;
            gap: 7px;
            min-width: 0;
        }

        .producto-nombre {
            overflow-wrap: anywhere;
            font: 600 1.1rem "Fredoka", sans-serif;
        }

        .producto-precio {
            color: var(--rojo);
            font-size: 1rem;
            font-weight: 700;
        }

        .producto-acciones {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .btn-cantidad {
            display: inline-flex;
            width: 34px;
            height: 34px;
            align-items: center;
            justify-content: center;
            border: none;
            border-radius: 50%;
            background: var(--gris-200);
            color: var(--gris-900);
            cursor: pointer;
            font-size: 1.2rem;
            font-weight: 700;
        }

        .btn-cantidad:hover {
            background: var(--rojo);
            color: var(--blanco);
        }

        .cantidad {
            min-width: 22px;
            font-weight: 700;
            text-align: center;
        }

        .btn-eliminar {
            padding: 8px;
            border: none;
            border-radius: 7px;
            background: transparent;
            color: #dc3545;
            cursor: pointer;
            font-size: 1.15rem;
        }

        .btn-eliminar:hover {
            background: #dc3545;
            color: var(--blanco);
        }

        /* TOTAL Y ACCIONES */

        .total-carrito {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            margin-top: 24px;
            padding: 20px;
            border: 2px solid var(--rojo);
            border-radius: 12px;
            background: #fff8f9;
        }

        .total-label {
            font: 600 1.2rem "Fredoka", sans-serif;
        }

        .total-monto {
            color: var(--rojo);
            font: 700 1.8rem "Fredoka", sans-serif;
        }

        .botones-carrito {
            display: flex;
            justify-content: center;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 20px;
        }

        /* FORMULARIO DE ENVÍO */

        .formulario-envio {
            display: none;
            margin-top: 30px;
            padding-top: 25px;
            border-top: 2px solid var(--gris-200);
        }

        .formulario-envio.active {
            display: block;
            animation: aparecer .25s ease;
        }

        @keyframes aparecer {
            from {
                opacity: 0;
                transform: translateY(8px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .formulario-envio h2 {
            margin-bottom: 8px;
            font: 600 1.6rem "Fredoka", sans-serif;
            text-align: center;
        }

        .form-subtexto {
            margin-bottom: 24px;
            color: var(--gris-600);
            line-height: 1.5;
            text-align: center;
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
        }

        .campo {
            min-width: 0;
            margin-bottom: 16px;
        }

        .campo-full {
            grid-column: 1 / -1;
        }

        .campo label {
            display: block;
            margin-bottom: 7px;
            color: var(--gris-700);
            font-size: .92rem;
            font-weight: 600;
        }

        .campo input,
        .campo textarea,
        .campo select {
            width: 100%;
            min-height: 46px;
            padding: 12px;
            border: 1px solid var(--gris-300);
            border-radius: 9px;
            background: var(--blanco);
            color: var(--gris-900);
            outline: none;
        }

        .campo textarea {
            min-height: 90px;
            resize: vertical;
        }

        .campo input:focus,
        .campo textarea:focus,
        .campo select:focus {
            border-color: var(--rojo);
            box-shadow: 0 0 0 3px rgba(219, 26, 53, .12);
        }

        /* MÉTODOS DE PAGO */

        .metodos-pago {
            margin: 12px 0 24px;
        }

        .label-pago {
            display: block;
            margin-bottom: 12px;
            font-weight: 600;
        }

        .opciones-pago {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px;
        }

        .opcion-pago {
            position: relative;
        }

        .opcion-pago input {
            position: absolute;
            width: 1px;
            height: 1px;
            opacity: 0;
        }

        .opcion-pago label {
            display: flex;
            min-height: 100px;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px;
            border: 2px solid var(--gris-200);
            border-radius: 12px;
            background: var(--fondo);
            cursor: pointer;
            font-size: .88rem;
            font-weight: 600;
            text-align: center;
            transition: .2s;
        }

        .opcion-pago .icono {
            font-size: 1.8rem;
        }

        .opcion-pago input:checked + label {
            border-color: var(--rojo);
            background: #fff4f6;
            box-shadow: 0 0 0 2px rgba(219, 26, 53, .08);
        }

        .opcion-pago input:focus-visible + label {
            outline: 3px solid #ffbf47;
            outline-offset: 3px;
        }

        .opcion-pago label:hover {
            border-color: #ef9aa8;
        }

        .botones-formulario {
            display: flex;
            gap: 12px;
            margin-top: 15px;
        }

        .botones-formulario button {
            flex: 1;
            min-height: 50px;
        }

        .btn-pagar {
            font: 600 1rem "Fredoka", sans-serif;
        }

        .btn-pagar:disabled {
            cursor: wait;
            opacity: .65;
        }

        /* FOOTER */

        .ed-footer {
            margin-top: auto;
            padding: 30px 5%;
            background: var(--rojo);
            color: var(--blanco);
        }

        .footer-contenido {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 25px;
            max-width: 1200px;
            margin: 0 auto;
        }

        .footer-logo {
            width: 58px;
            height: 58px;
            flex-shrink: 0;
            padding: 4px;
            border: 2px solid var(--blanco);
            border-radius: 10px;
            background: var(--blanco);
            object-fit: contain;
        }

        .footer-info {
            flex: 1;
            text-align: center;
        }

        .footer-info p {
            margin: 6px 0;
            color: rgba(255, 255, 255, .9);
            font-size: .9rem;
            line-height: 1.5;
        }

        .footer-info strong {
            color: var(--blanco);
            font: 600 1.15rem "Fredoka", sans-serif;
        }

        .footer-info a {
            color: var(--blanco);
            text-decoration: underline;
            text-underline-offset: 3px;
        }

        .footer-contacto {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 12px;
            max-width: 340px;
        }

        .footer-contacto h2 {
            font: 600 1.1rem "Fredoka", sans-serif;
        }

        .footer-contacto p {
            font-size: .9rem;
            line-height: 1.6;
            text-align: center;
        }

        /* BOTÓN WHATSAPP MEJORADO */

        .whatsapp-btn {
            display: inline-flex;
            position: relative;
            align-items: center;
            justify-content: center;
            gap: 11px;
            width: 100%;
            min-height: 54px;
            padding: 13px 20px;
            overflow: hidden;
            border: 2px solid rgba(255, 255, 255, .8);
            border-radius: 50px;
            background: var(--verde-whatsapp);
            color: #073b20;
            box-shadow: 0 5px 15px rgba(0, 0, 0, .16);
            font-size: .98rem;
            font-weight: 700;
            text-align: center;
            text-decoration: none;
            transition:
                background .25s ease,
                color .25s ease,
                transform .25s ease,
                box-shadow .25s ease;
        }

        .whatsapp-btn:hover {
            transform: translateY(-3px);
            background: var(--verde-whatsapp-oscuro);
            color: var(--blanco);
            box-shadow: 0 9px 22px rgba(0, 0, 0, .23);
        }

        .whatsapp-btn:active {
            transform: scale(.98);
        }

        .whatsapp-icon {
            display: flex;
            width: 30px;
            height: 30px;
            flex-shrink: 0;
            align-items: center;
            justify-content: center;
        }

        .whatsapp-icon svg {
            width: 29px;
            height: 29px;
            fill: currentColor;
        }

        .whatsapp-texto {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 2px;
            line-height: 1.25;
        }

        .whatsapp-texto strong {
            font-size: .96rem;
        }

        .whatsapp-texto small {
            font-size: .74rem;
            font-weight: 500;
            opacity: .9;
        }

        /* RESPONSIVE */

        @media (max-width: 850px) {
            .footer-contenido {
                flex-wrap: wrap;
                justify-content: center;
            }

            .footer-info {
                flex-basis: 60%;
            }

            .footer-contacto {
                flex-basis: 100%;
                max-width: 400px;
            }

            .whatsapp-btn {
                max-width: 340px;
            }
        }

        @media (max-width: 600px) {
            .ed-header {
                flex-direction: column;
                gap: 14px;
                padding: 14px;
            }

            .ed-brand h1 {
                font-size: 1.45rem;
            }

            .ed-nav {
                gap: 14px;
            }

            .ed-nav a {
                font-size: .88rem;
            }

            .contenedor-carrito {
                width: calc(100% - 20px);
                margin: 16px auto;
                padding: 18px 14px;
            }

            .titulo-carrito {
                font-size: 1.65rem;
            }

            .producto-item {
                align-items: stretch;
                flex-direction: column;
                gap: 14px;
                padding: 15px;
            }

            .producto-acciones {
                justify-content: flex-start;
            }

            .total-carrito {
                align-items: center;
                flex-direction: column;
                text-align: center;
            }

            .botones-carrito {
                align-items: stretch;
                flex-direction: column;
            }

            .botones-carrito .btn-ed {
                width: 100%;
            }

            .form-row {
                grid-template-columns: 1fr;
                gap: 0;
            }

            .campo-full {
                grid-column: auto;
            }

            .opciones-pago {
                grid-template-columns: 1fr;
            }

            .opcion-pago label {
                min-height: 72px;
                flex-direction: row;
                justify-content: flex-start;
                text-align: left;
            }

            .opcion-pago .icono {
                width: 35px;
                text-align: center;
            }

            .botones-formulario {
                flex-direction: column-reverse;
            }

            .footer-contenido {
                flex-direction: column;
                gap: 22px;
            }

            .footer-info {
                flex-basis: auto;
            }

            .footer-contacto {
                width: 100%;
            }

            .whatsapp-btn {
                width: 100%;
                max-width: 100%;
                padding: 12px 15px;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            *,
            *::before,
            *::after {
                scroll-behavior: auto !important;
                animation-duration: .01ms !important;
                transition-duration: .01ms !important;
            }
        }
    </style>
</head>

<body>

    <!-- ENCABEZADO -->
    <header class="ed-header">
        <a href="../index.html" class="ed-brand">
            <img
                src="../imagen/ChatGPT Image 5 nov 2025, 10_50_14 p.m..png"
                alt="Logo Estrella Dulce"
                class="ed-logo"
            >
            <h1>Estrella Dulce</h1>
        </a>

        <nav class="ed-nav" aria-label="Navegación principal">
            <a href="postres.php">Postres</a>
            <a href="nosotros.html">Nosotros</a>
            <a href="contacto.php">Contacto</a>
            <a href="carrito.php" aria-label="Mi carrito">🛒 Carrito</a>
            <a href="../index.php">🏠 Inicio</a>
        </nav>
    </header>

    <!-- CONTENIDO PRINCIPAL -->
    <main>
        <section class="contenedor-carrito">
            <h1 class="titulo-carrito">🛒 Tu carrito de postres</h1>

            <?php if ($mensajeError !== ""): ?>
                <div class="alerta-error" role="alert">
                    <?= escapar($mensajeError) ?>
                </div>
            <?php endif; ?>

            <div id="carritoContainer" aria-live="polite"></div>

            <!-- FORMULARIO REAL PARA ENVIAR EL PEDIDO A PHP -->
            <form
                id="formularioEnvio"
                class="formulario-envio"
                method="POST"
                action=""
            >
                <h2>📦 Datos de entrega</h2>

                <p class="form-subtexto">
                    Hola, <?= escapar(trim($nombreUsuario . " " . $apellidoUsuario)) ?>.
                    Completa los datos para confirmar tu pedido.
                </p>

                <input
                    type="hidden"
                    name="finalizar_pedido"
                    value="1"
                >

                <input
                    type="hidden"
                    name="carrito_json"
                    id="carritoJson"
                    value=""
                >

                <div class="form-row">
                    <div class="campo campo-full">
                        <label for="direccion">Dirección de entrega *</label>
                        <input
                            type="text"
                            id="direccion"
                            name="direccion_entrega"
                            maxlength="250"
                            placeholder="Ej. Calle 123 #45-67, Medellín"
                            autocomplete="street-address"
                            required
                        >
                    </div>

                    <div class="campo campo-full">
                        <label for="telefono">Teléfono de contacto *</label>
                        <input
                            type="tel"
                            id="telefono"
                            name="telefono_contacto"
                            maxlength="30"
                            placeholder="Ej. 3001234567"
                            autocomplete="tel"
                            required
                        >
                    </div>

                    <div class="campo campo-full">
                        <label for="observaciones">
                            Observaciones adicionales (opcional)
                        </label>
                        <textarea
                            id="observaciones"
                            name="observaciones"
                            maxlength="1000"
                            placeholder="Indicaciones para la entrega, referencias de la dirección, etc."
                        ></textarea>
                    </div>
                </div>

                <div class="metodos-pago">
                    <span class="label-pago">
                        💳 Selecciona tu método de pago *
                    </span>

                    <div class="opciones-pago">
                        <div class="opcion-pago">
                            <input
                                type="radio"
                                name="metodo_pago"
                                id="pagoEfectivo"
                                value="Efectivo"
                                required
                                checked
                            >
                            <label for="pagoEfectivo">
                                <span class="icono">💵</span>
                                <span>Efectivo</span>
                            </label>
                        </div>

                        <div class="opcion-pago">
                            <input
                                type="radio"
                                name="metodo_pago"
                                id="pagoTransferencia"
                                value="Transferencia bancaria"
                                required
                            >
                            <label for="pagoTransferencia">
                                <span class="icono">🏦</span>
                                <span>Transferencia bancaria</span>
                            </label>
                        </div>

                        <div class="opcion-pago">
                            <input
                                type="radio"
                                name="metodo_pago"
                                id="pagoContraEntrega"
                                value="Pago contra entrega"
                                required
                            >
                            <label for="pagoContraEntrega">
                                <span class="icono">🛵</span>
                                <span>Pago contra entrega</span>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="total-carrito">
                    <span class="total-label">Total a pagar</span>
                    <span class="total-monto" id="totalFormulario">
                        $0
                    </span>
                </div>

                <div class="botones-formulario">
                    <button
                        type="button"
                        class="btn-ed btn-outline"
                        id="btnCancelar"
                    >
                        ← Volver al carrito
                    </button>

                    <button
                        type="submit"
                        class="btn-ed btn-success btn-pagar"
                        id="btnPagar"
                    >
                        ✓ Confirmar pedido
                    </button>
                </div>
            </form>
        </section>
    </main>

    <!-- PIE DE PÁGINA -->
    <footer class="ed-footer">
        <div class="footer-contenido">

            <img
                src="../imagen/I.U Pascual Bravo Escudo.png"
                alt="Escudo Pascual Bravo"
                class="footer-logo"
                loading="lazy"
            >

            <div class="footer-info">
                <p><strong>Estrella Dulce S.A.</strong></p>
                <p>Medellín, Antioquia, Colombia</p>

                <p>
                    <a href="contacto.html">Contáctanos</a>
                </p>

                <p>
                    &copy; 2026 Estrella Dulce.
                    Todos los derechos reservados.
                </p>
            </div>

            <div class="footer-contacto">
                <h2>💬 Atención al cliente</h2>

                <p>
                    ¿Tienes dudas sobre nuestros postres o tu pedido?
                    ¡Estamos para ayudarte!
                </p>

                <!-- BOTÓN WHATSAPP -->
                <a
                    href="https://api.whatsapp.com/send?phone=573146199121&amp;text=Hola%2C%20vengo%20de%20la%20p%C3%A1gina%20web%20de%20Estrella%20Dulce.%20Quiero%20obtener%20m%C3%A1s%20informaci%C3%B3n."
                    class="whatsapp-btn"
                    target="_blank"
                    rel="noopener noreferrer"
                    aria-label="Escríbenos por WhatsApp"
                >
                    <span class="whatsapp-icon" aria-hidden="true">
                        <svg
                            viewBox="0 0 32 32"
                            xmlns="http://www.w3.org/2000/svg"
                        >
                            <path d="M16 .8A15.1 15.1 0 0 0 3.04 23.65L.9 31.2l7.72-2.02A15.1 15.1 0 1 0 16 .8Zm0 27.45a12.3 12.3 0 0 1-6.28-1.72l-.45-.27-4.58 1.2 1.22-4.46-.29-.46A12.35 12.35 0 1 1 16 28.25Zm6.77-9.25c-.37-.19-2.18-1.08-2.52-1.2-.34-.13-.58-.19-.82.19-.24.37-.95 1.2-1.16 1.45-.22.24-.43.27-.8.09-.37-.19-1.55-.57-2.95-1.82-1.09-.97-1.83-2.18-2.04-2.55-.22-.38-.02-.58.16-.76.17-.16.37-.43.56-.64.19-.21.25-.37.37-.62.13-.24.07-.46-.03-.65-.09-.18-.82-1.98-1.12-2.72-.29-.71-.6-.61-.82-.62h-.7c-.24 0-.64.09-.98.46-.34.37-1.29 1.26-1.29 3.06s1.32 3.55 1.5 3.79c.19.25 2.6 3.97 6.3 5.57.88.38 1.57.61 2.1.78.89.28 1.7.24 2.34.14.71-.1 2.18-.89 2.49-1.75.3-.87.3-1.61.21-1.76-.1-.15-.34-.24-.71-.43Z"/>
                        </svg>
                    </span>

                    <span class="whatsapp-texto">
                        <strong>Escríbenos por WhatsApp</strong>
                        <small>Estamos aquí para ayudarte</small>
                    </span>
                </a>
            </div>

            <img
            src="../imagen/I.E F├®lix De Bedout Moreno Escudo.png"
                alt="Logo Félix"
                class="footer-logo"
            alt="Logo Félix de Bedout Moreno"
            class="footer-logo"
            loading="lazy"
        >


        </div>
    </footer>

    <script>
        "use strict";

        /* =====================================================
           1. REFERENCIAS Y CONFIGURACIÓN
           ===================================================== */

        const CLAVE_CARRITO = "carrito";

        const formatoCOP = new Intl.NumberFormat("es-CO", {
            style: "currency",
            currency: "COP",
            maximumFractionDigits: 0
        });

        const contenedor = document.getElementById("carritoContainer");
        const formulario = document.getElementById("formularioEnvio");
        const campoCarrito = document.getElementById("carritoJson");
        const totalFormulario = document.getElementById("totalFormulario");
        const btnPagar = document.getElementById("btnPagar");
        const btnCancelar = document.getElementById("btnCancelar");

        let carrito = cargarDatosCarrito();

        /* =====================================================
           2. CARGAR Y VALIDAR CARRITO LOCAL
           ===================================================== */

        function cargarDatosCarrito() {
            try {
                const datos = JSON.parse(
                    localStorage.getItem(CLAVE_CARRITO) || "[]"
                );

                if (!Array.isArray(datos)) {
                    return [];
                }

                return datos.filter(item => {
                    return item
                        && Number.isInteger(Number(item.id_postre))
                        && Number(item.id_postre) > 0
                        && Number.isFinite(Number(item.precio))
                        && Number(item.precio) >= 0
                        && Number.isInteger(Number(item.cantidad ?? 1))
                        && Number(item.cantidad ?? 1) >= 1
                        && Number(item.cantidad ?? 1) <= 100;
                }).map(item => ({
                    ...item,
                    id_postre: Number(item.id_postre),
                    precio: Number(item.precio),
                    cantidad: Number(item.cantidad ?? 1),
                    nombre: String(item.nombre ?? "Postre")
                }));
            } catch (error) {
                console.error("No se pudo leer el carrito:", error);
                return [];
            }
        }

        function guardarCarrito() {
            localStorage.setItem(
                CLAVE_CARRITO,
                JSON.stringify(carrito)
            );
        }

        function escaparHTML(valor) {
            return String(valor).replace(/[&<>"']/g, caracter => {
                const entidades = {
                    "&": "&amp;",
                    "<": "&lt;",
                    ">": "&gt;",
                    '"': "&quot;",
                    "'": "&#039;"
                };

                return entidades[caracter];
            });
        }

        function calcularTotal() {
            return carrito.reduce((total, item) => {
                return total + item.precio * item.cantidad;
            }, 0);
        }

        /* =====================================================
           3. MOSTRAR PRODUCTOS
           ===================================================== */

        function renderizarCarrito() {
            if (carrito.length === 0) {
                contenedor.innerHTML = `
                    <div class="carrito-vacio">
                        <span class="emoji">🍰</span>
                        <h2>Tu carrito está vacío</h2>
                        <p>
                            ¡Tenemos postres deliciosos esperándote!
                            Explora el catálogo y agrega tus favoritos.
                        </p>
                        <div class="botones-carrito">
                            <a href="postres.html" class="btn-ed btn-primary">
                                Ver nuestros postres
                            </a>
                            <a href="estado_pedido.php" class="btn-ed btn-success">
                                📋 Ver estado de productos
                            </a>
                        </div>
                    </div>
                `;

                formulario.classList.remove("active");
                return;
            }

            let html = "";

            carrito.forEach((item, index) => {
                const subtotal = item.precio * item.cantidad;

                html += `
                    <article class="producto-item">
                        <div class="producto-info">
                            <span class="producto-nombre">
                                ${escaparHTML(item.nombre)}
                            </span>

                            <span class="producto-precio">
                                ${formatoCOP.format(item.precio)} por unidad
                            </span>

                            <span>
                                Subtotal:
                                <strong>${formatoCOP.format(subtotal)}</strong>
                            </span>
                        </div>

                        <div class="producto-acciones">
                            <button
                                type="button"
                                class="btn-cantidad"
                                data-accion="restar"
                                data-index="${index}"
                                aria-label="Disminuir cantidad"
                            >−</button>

                            <span class="cantidad">
                                ${item.cantidad}
                            </span>

                            <button
                                type="button"
                                class="btn-cantidad"
                                data-accion="sumar"
                                data-index="${index}"
                                aria-label="Aumentar cantidad"
                            >+</button>

                            <button
                                type="button"
                                class="btn-eliminar"
                                data-accion="eliminar"
                                data-index="${index}"
                                aria-label="Eliminar ${escaparHTML(item.nombre)}"
                                title="Eliminar producto"
                            >✕</button>
                        </div>
                    </article>
                `;
            });

            html += `
                <div class="total-carrito">
                    <span class="total-label">Total del pedido</span>
                    <span class="total-monto">
                        ${formatoCOP.format(calcularTotal())}
                    </span>
                </div>

                <div class="botones-carrito">
                    <a href="postres.html" class="btn-ed btn-outline">
                        ← Seguir comprando
                    </a>

                    <a href="estado_pedido.php" class="btn-ed btn-success">
                        📋 Ver estado de productos
                    </a>

                    <button
                        type="button"
                        class="btn-ed btn-primary"
                        id="btnMostrarFormulario"
                    >
                        📦 Realizar pedido
                    </button>

                    <button
                        type="button"
                        class="btn-ed btn-danger"
                        id="btnVaciar"
                    >
                        🗑️ Vaciar carrito
                    </button>
                </div>
            `;

            contenedor.innerHTML = html;

            document.getElementById("btnMostrarFormulario")
                .addEventListener("click", mostrarFormularioEnvio);

            document.getElementById("btnVaciar")
                .addEventListener("click", vaciarCarrito);

            actualizarTotalFormulario();
        }

        /* =====================================================
           4. CAMBIAR CANTIDADES Y ELIMINAR
           ===================================================== */

        contenedor.addEventListener("click", event => {
            const boton = event.target.closest("button[data-accion]");

            if (!boton) {
                return;
            }

            const index = Number(boton.dataset.index);
            const accion = boton.dataset.accion;

            if (!Number.isInteger(index) || !carrito[index]) {
                return;
            }

            if (accion === "sumar") {
                if (carrito[index].cantidad >= 100) {
                    alert("La cantidad máxima por producto es 100.");
                    return;
                }

                carrito[index].cantidad++;
            } else if (accion === "restar") {
                carrito[index].cantidad--;

                if (carrito[index].cantidad < 1) {
                    carrito.splice(index, 1);
                }
            } else if (accion === "eliminar") {
                carrito.splice(index, 1);
            } else {
                return;
            }

            guardarCarrito();
            renderizarCarrito();

            window.dispatchEvent(new Event("storage"));
        });

        function vaciarCarrito() {
            if (carrito.length === 0) {
                return;
            }

            if (!confirm("¿Seguro que deseas vaciar todo el carrito?")) {
                return;
            }

            carrito = [];
            guardarCarrito();
            renderizarCarrito();

            window.dispatchEvent(new Event("storage"));
        }

        /* =====================================================
           5. FORMULARIO DE ENVÍO
           ===================================================== */

        function mostrarFormularioEnvio() {
            if (carrito.length === 0) {
                alert("Agrega productos antes de realizar tu pedido.");
                return;
            }

            formulario.classList.add("active");
            actualizarTotalFormulario();

            formulario.scrollIntoView({
                behavior: "smooth",
                block: "start"
            });
        }

        function ocultarFormularioEnvio() {
            formulario.classList.remove("active");

            contenedor.scrollIntoView({
                behavior: "smooth",
                block: "start"
            });
        }

        btnCancelar.addEventListener("click", ocultarFormularioEnvio);

        function actualizarTotalFormulario() {
            totalFormulario.textContent =
                formatoCOP.format(calcularTotal());
        }

        /* =====================================================
           6. ENVIAR PEDIDO AL SERVIDOR PHP
           ===================================================== */

        formulario.addEventListener("submit", event => {
            if (carrito.length === 0) {
                event.preventDefault();
                alert("Tu carrito está vacío.");
                renderizarCarrito();
                return;
            }

            const direccion = document
                .getElementById("direccion")
                .value.trim();

            const telefono = document
                .getElementById("telefono")
                .value.trim();

            if (!direccion || !telefono) {
                event.preventDefault();
                alert("Completa la dirección y el teléfono de contacto.");
                return;
            }

            /*
             * Enviar únicamente identificadores y cantidades.
             * Los precios reales se consultan nuevamente en PHP
             * directamente desde la base de datos.
             */
            const carritoServidor = carrito.map(item => ({
                id_postre: item.id_postre,
                cantidad: item.cantidad
            }));

            campoCarrito.value = JSON.stringify(carritoServidor);

            btnPagar.disabled = true;
            btnPagar.textContent = "Procesando pedido...";

            /*
             * No se cancela el envío: el formulario hace POST
             * al PHP, que registra el pedido y redirige al éxito.
             */
        });

        /* =====================================================
           7. SINCRONIZACIÓN ENTRE PESTAÑAS
           ===================================================== */

        window.addEventListener("storage", event => {
            if (event.key === CLAVE_CARRITO) {
                carrito = cargarDatosCarrito();
                renderizarCarrito();
            }
        });

        /* =====================================================
           8. INICIALIZAR
           ===================================================== */

        renderizarCarrito();
    </script>

</body>
</html>