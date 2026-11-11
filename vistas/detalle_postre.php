<?php
session_start();
require_once "../conexion.php";

/* =========================================
   VALIDAR ID DEL POSTRE
   ========================================= */
if (!isset($_GET["id"]) || !is_numeric($_GET["id"])) {
    header("Location: postres.php");
    exit;
}

$idPostre = (int) $_GET["id"];

/* =========================================
   CONSULTAR POSTRE
   ========================================= */
try {
    $sql = "SELECT
                p.id_postre,
                p.nombre,
                p.descripcion,
                p.precio,
                p.stock,
                p.imagen,
                p.ingredientes,
                p.estado,
                c.nombre AS categoria
            FROM postres p
            INNER JOIN categorias c
                ON p.id_categoria = c.id_categoria
            WHERE p.id_postre = :id
            LIMIT 1";

    $stmt = $conexion->prepare($sql);
    $stmt->execute([":id" => $idPostre]);
    $postre = $stmt->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log($e->getMessage());
    die("No fue posible consultar el postre.");
}

if (!$postre) {
    header("Location: postres.php");
    exit;
}

/* =========================================
   FUNCIÓN PARA ESCAPAR TEXTO
   ========================================= */
function escapar($valor): string {
    return htmlspecialchars(
        (string) $valor,
        ENT_QUOTES,
        "UTF-8"
    );
}

/* =========================================
   DATOS DEL POSTRE
   ========================================= */
$nombre = $postre["nombre"];
$descripcion = $postre["descripcion"] ?? "";
$precio = (float) $postre["precio"];
$stock = (int) $postre["stock"];
$imagen = $postre["imagen"] ?? "";
$ingredientes = $postre["ingredientes"] ?? "";
$categoria = $postre["categoria"] ?? "";

$usuarioLogueado = isset($_SESSION["id_usuario"]);
$nombreUsuario = $_SESSION["nombre"] ?? "";
$apellidoUsuario = $_SESSION["apellido"] ?? "";
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= escapar($nombre) ?> - Estrella Dulce</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <link
        href="https://fonts.googleapis.com/css2?family=Fredoka:wght@400;500;600;700&family=Work+Sans:wght@400;500;600&display=swap"
        rel="stylesheet"
    >

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            background: #f8f9fa;
            font-family: 'Work Sans', sans-serif;
            color: #212529;
        }

        .ed-header {
            background: #DB1A35;
            padding: .8rem 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 15px;
        }

        .ed-header__brand {
            display: flex;
            align-items: center;
            gap: .8rem;
            text-decoration: none;
        }

        .ed-header__logo {
            width: 50px;
            height: 50px;
            object-fit: contain;
            border-radius: 50%;
            background: white;
            padding: 4px;
        }

        .ed-header__title {
            font-family: 'Fredoka', sans-serif;
            color: white;
            font-size: 1.6rem;
            margin: 0;
        }

        .ed-nav {
            display: flex;
            align-items: center;
            gap: 1.2rem;
            flex-wrap: wrap;
        }

        .ed-nav a {
            color: white;
            text-decoration: none;
        }

        .ed-nav a:hover {
            text-decoration: underline;
        }

        .detalle {
            width: 100%;
            max-width: 1100px;
            margin: 45px auto;
            padding: 30px;
            background: white;
            border-radius: 18px;
            box-shadow: 0 5px 25px rgba(0, 0, 0, .08);
        }

        .detalle-imagen {
            width: 100%;
            max-height: 450px;
            object-fit: cover;
            border-radius: 15px;
        }

        .imagen-vacia {
            height: 350px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f8f9fa;
            border-radius: 15px;
            font-size: 5rem;
        }

        .detalle h1 {
            font-family: 'Fredoka', sans-serif;
            font-weight: 600;
        }

        .precio {
            color: #DB1A35;
            font-size: 1.8rem;
            font-weight: 700;
            font-family: 'Fredoka', sans-serif;
            margin: 15px 0;
        }

        .selector {
            margin: 20px 0;
        }

        .selector label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .selector select {
            width: 100%;
            padding: 12px;
            border: 1px solid #ced4da;
            border-radius: 8px;
            background: #f8f9fa;
            font: inherit;
        }

        .selector select:focus {
            outline: none;
            border-color: #DB1A35;
            box-shadow: 0 0 0 3px rgba(219, 26, 53, .12);
        }

        .precio-ayuda {
            color: #6c757d;
            font-size: .85rem;
        }

        .btn-principal {
            background: #DB1A35;
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
        }

        .btn-principal:hover {
            background: #b8152c;
            color: white;
        }

        .btn-principal:disabled {
            background: #6c757d;
            cursor: not-allowed;
        }

        .stock {
            font-weight: 600;
            margin: 20px 0;
        }

        /* =========================================
           ESTILOS DEL FOOTER (LOGOS CON ESQUINAS REDONDEADAS)
           ========================================= */
        .ed-footer {
            margin-top: auto;
            background: #DB1A35;
            color: white;
            padding: 30px 20px;
            text-align: center;
        }

        .ed-footer .page-wrap {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
        }

        @media (min-width: 768px) {
            .ed-footer .page-wrap {
                flex-direction: row;
            }
        }

        /* Logos en contenedores rectangulares con esquinas ligeramente redondeadas */
        .footer-logo-left,
        .footer-logo-right {
            width: 75px;
            height: 75px;
            object-fit: contain;
            background: white;
            border-radius: 12px; /* Esquinas redondeadas estilo tarjeta/cuadro */
            padding: 6px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.15);
        }

        .footer-link {
            color: white;
            text-decoration: underline;
        }

        .footer-link:hover {
            color: #f8f9fa;
        }

        .btn-ed--white {
            background: white;
            color: #DB1A35;
            padding: 8px 16px;
            border-radius: 6px;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            transition: background 0.2s;
        }

        .btn-ed--white:hover {
            background: #f1f1f1;
            color: #b8152c;
        }

        @media (max-width: 768px) {
            .ed-header {
                flex-direction: column;
                padding: 15px;
            }

            .ed-nav {
                justify-content: center;
            }

            .detalle {
                margin: 20px auto;
                padding: 20px;
            }
        }
    </style>
</head>

<body>

<header class="ed-header">
    <a href="../index.php" class="ed-header__brand">
        <img
            src="../imagen/ChatGPT Image 5 nov 2025, 10_50_14 p.m..png"
            class="ed-header__logo"
            alt="Logo Estrella Dulce"
        >
        <span class="ed-header__title">Estrella Dulce</span>
    </a>

    <nav class="ed-nav" aria-label="Navegación principal">
        <a href="postres.php">Postres</a>
        <a href="nosotros.html">Nosotros</a>
        <a href="contacto.php">Contacto</a>
        <a href="carrito.php">🛒 Carrito</a>

        <?php if ($usuarioLogueado): ?>
            <span style="color:white">
                👤 <?= escapar(trim($nombreUsuario . " " . $apellidoUsuario)) ?>
            </span>
            <a href="../cerrar_sesion.php">Cerrar sesión</a>
        <?php else: ?>
            <a href="iniciar%20sesi%C3%B3n.php">Iniciar sesión</a>
        <?php endif; ?>
    </nav>
</header>

<main class="detalle">
    <div class="row align-items-center">

        <div class="col-md-6 mb-4 mb-md-0">
            <?php if ($imagen !== ""): ?>
                <img
                    src="../imagen/<?= escapar($imagen) ?>"
                    class="detalle-imagen"
                    alt="<?= escapar($nombre) ?>"
                >
            <?php else: ?>
                <div class="imagen-vacia" role="img" aria-label="Sin imagen">
                    🍰
                </div>
            <?php endif; ?>
        </div>

        <div class="col-md-6">

            <p class="text-muted">
                <?= escapar($categoria) ?>
            </p>

            <h1><?= escapar($nombre) ?></h1>

            <p><?= nl2br(escapar($descripcion)) ?></p>

            <!-- SELECTOR DE TAMAÑO -->
            <div class="selector">
                <label for="tamano">Selecciona el tamaño:</label>
                <select id="tamano">
                    <option value="junior">Junior - $7.000</option>
                    <option value="mediano">Mediano - $9.000</option>
                    <option value="grande">Grande - $11.000</option>
                </select>
            </div>

            <!-- PRECIO SEGÚN EL TAMAÑO -->
            <p class="precio" id="precio" aria-live="polite">
                $7.000
            </p>

            <p class="precio-ayuda">
                Selecciona el tamaño para consultar el precio.
            </p>

            <p>
                <strong>Ingredientes:</strong><br>
                <?= nl2br(escapar($ingredientes)) ?>
            </p>

            <p class="stock">
                <?php if ($stock > 0): ?>
                    🟢 Disponible: <?= $stock ?> unidades
                <?php else: ?>
                    🔴 Agotado
                <?php endif; ?>
            </p>

            <?php if ($stock > 0): ?>
                <button
                    type="button"
                    id="btnAgregar"
                    class="btn-principal"
                >
                    🛒 Agregar al carrito
                </button>
            <?php else: ?>
                <button
                    type="button"
                    class="btn btn-secondary"
                    disabled
                >
                    Agotado
                </button>
            <?php endif; ?>

            <a href="postres.php" class="btn btn-outline-secondary ms-2">
                ← Volver
            </a>

        </div>
    </div>
</main>

<footer class="ed-footer">
    <div class="page-wrap">
        <!-- LOGO PASCUAL -->
        <img
            src="../imagen/Logo Pascual Bravo 2.jpg"
            alt="Logo Pascual Bravo"
            class="footer-logo-left"
        >

        <!-- INFORMACIÓN -->
        <div class="text-center ed-footer-info my-3 my-md-0">
            <p>
                <strong style="color: white;">
                    Estrella Dulce S.A.
                </strong>
            </p>
            <p>
                Medellín, Antioquia
            </p>
            <p>
                <a href="#" class="footer-link">Términos y condiciones</a>
                |
                <a href="#" class="footer-link">Política de privacidad</a>
            </p>
            <p>
                &copy; 2026 Estrella Dulce. Todos los derechos reservados.
            </p>
        </div>

        <!-- ATENCIÓN AL CLIENTE -->
        <ul class="list-unstyled text-center text-md-start mb-0">
            <li class="mb-2">
                <span class="customer-service-title" style="font-weight: bold;">
                    Atención al cliente
                </span>
                <br>
                <p class="mb-0" style="color: rgba(255,255,255,0.85); font-size: 0.9rem;">
                    Tel: +3146199121 | Email: proyectpostres@gmail.com
                </p>
            </li>
            <li style="margin-top: 0.75rem;">
                <a
                    href="https://api.whatsapp.com/send?phone=573146199121&amp;text=Hola,%20vengo%20de%20la%20p%C3%A1gina%20web"
                    class="btn-ed--white"
                    target="_blank"
                    rel="noopener"
                >
                    💬 Escríbenos por WhatsApp
                </a>
            </li>
        </ul>

        <!-- LOGO FÉLIX -->
        <img
            src="../imagen/I.E F├®lix De Bedout Moreno Escudo.png"
            alt="Logo Félix"
            class="footer-logo-right"
        >
    </div>
</footer>

<script>
"use strict";

/* =========================================
   DATOS DEL POSTRE
   ========================================= */
const idPostre = <?= json_encode($idPostre) ?>;

const nombrePostre = <?= json_encode(
    $nombre,
    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
) ?>;

const precioBase = <?= json_encode($precio) ?>;
const stockDisponible = <?= json_encode($stock) ?>;

const imagenPostre = <?= json_encode(
    $imagen,
    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
) ?>;

/* =========================================
   PRECIOS FIJOS POR TAMAÑO
   ========================================= */
const preciosTamano = {
    junior: 7000,
    mediano: 9000,
    grande: 11000
};

const nombresTamano = {
    junior: "Junior",
    mediano: "Mediano",
    grande: "Grande"
};

const selector = document.getElementById("tamano");
const elementoPrecio = document.getElementById("precio");

/* FORMATEAR PRECIOS EN PESOS COLOMBIANOS */
function formatearPrecio(valor) {
    return Number(valor).toLocaleString("es-CO", {
        maximumFractionDigits: 0
    });
}

/* OBTENER EL PRECIO DEL TAMAÑO SELECCIONADO */
function obtenerPrecioTamano(tamano) {
    return preciosTamano[tamano] ?? Number(precioBase);
}

/* ACTUALIZAR EL PRECIO EN PANTALLA */
function actualizarPrecio() {
    const tamano = selector.value;
    const precioFinal = obtenerPrecioTamano(tamano);

    elementoPrecio.textContent =
        "$" + formatearPrecio(precioFinal);
}

selector.addEventListener("change", actualizarPrecio);

/* =========================================
   CARGAR CARRITO DESDE LOCALSTORAGE
   ========================================= */
function cargarCarrito() {
    try {
        const datos = JSON.parse(
            localStorage.getItem("carrito") || "[]"
        );

        return Array.isArray(datos) ? datos : [];
    } catch (error) {
        return [];
    }
}

/* =========================================
   AGREGAR POSTRE AL CARRITO
   ========================================= */
function agregarCarrito() {
    const tamano = selector.value;
    const precioFinal = obtenerPrecioTamano(tamano);

    if (stockDisponible <= 0) {
        alert("Este postre está agotado.");
        return;
    }

    let carrito = cargarCarrito();

    /* BUSCAR EL MISMO POSTRE Y TAMAÑO */
    const productoExistente = carrito.find(producto =>
        Number(producto.id_postre) === Number(idPostre) &&
        (producto.tamano || "junior") === tamano
    );

    /* VALIDAR EL STOCK TOTAL DEL POSTRE */
    const cantidadTotalPostre = carrito.reduce(
        (total, producto) => {
            if (Number(producto.id_postre) === Number(idPostre)) {
                return total +
                    Math.max(0, Number(producto.cantidad) || 0);
            }

            return total;
        },
        0
    );

    if (cantidadTotalPostre >= stockDisponible) {
        alert("No hay más unidades disponibles de este postre.");
        return;
    }

    /* ACTUALIZAR O CREAR EL PRODUCTO */
    if (productoExistente) {
        productoExistente.cantidad =
            (Number(productoExistente.cantidad) || 0) + 1;

        productoExistente.precio = precioFinal;
        productoExistente.precio_base = Number(precioBase);
        productoExistente.nombre_tamano = nombresTamano[tamano];

    } else {
        carrito.push({
            id_postre: Number(idPostre),
            nombre: nombrePostre,
            precio: precioFinal,
            precio_base: Number(precioBase),
            tamano: tamano,
            nombre_tamano: nombresTamano[tamano],
            imagen: imagenPostre,
            cantidad: 1
        });
    }

    /* GUARDAR CARRITO */
    try {
        localStorage.setItem("carrito", JSON.stringify(carrito));

        window.dispatchEvent(new Event("carritoActualizado"));

        alert(
            nombrePostre + " (" + nombresTamano[tamano] +
            ") agregado al carrito por $" +
            formatearPrecio(precioFinal)
        );

    } catch (error) {
        alert("No se pudo guardar el producto en el carrito.");
    }
}

/* EVENTO DEL BOTÓN */
const btnAgregar = document.getElementById("btnAgregar");

if (btnAgregar) {
    btnAgregar.addEventListener("click", agregarCarrito);
}

/* MOSTRAR PRECIO INICIAL */
actualizarPrecio();
</script>

</body>
</html>