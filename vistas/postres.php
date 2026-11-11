
<?php
session_start();
require_once "../conexion.php";

$usuarioLogueado = isset($_SESSION["id_usuario"]);
$nombreUsuario = $_SESSION["nombre"] ?? "";
$apellidoUsuario = $_SESSION["apellido"] ?? "";

function escapar($valor): string {
    return htmlspecialchars((string)$valor, ENT_QUOTES, "UTF-8");
}

try {
    $sql = "SELECT
                p.id_postre,
                p.nombre,
                p.descripcion,
                p.precio,
                p.stock,
                p.imagen,
                p.estado,
                c.nombre AS categoria
            FROM postres p
            INNER JOIN categorias c
                ON p.id_categoria = c.id_categoria
            WHERE p.estado = 'DISPONIBLE'
            ORDER BY p.id_postre ASC";

    $stmt = $conexion->prepare($sql);
    $stmt->execute();
    $postres = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log($e->getMessage());
    $postres = [];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Nuestros Postres - Estrella Dulce</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<link href="https://fonts.googleapis.com/css2?family=Fredoka:wght@400;500;600;700&family=Work+Sans:wght@400;500;600&display=swap" rel="stylesheet">

<style>
:root {
    --primary: #DB1A35;
    --primary-dark: #b8152c;
}

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
}

.ed-header {
    background: var(--primary);
    padding: 15px 25px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 15px;
}

.ed-header__brand {
    display: flex;
    align-items: center;
    gap: 12px;
    text-decoration: none;
}

.ed-header__logo {
    width: 50px;
    height: 50px;
    object-fit: contain;
    background: white;
    border-radius: 50%;
    padding: 4px;
}

.ed-header__title {
    color: white;
    font: 600 1.6rem 'Fredoka', sans-serif;
    margin: 0;
}

.ed-nav {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
}

.ed-nav a {
    color: white;
    text-decoration: none;
}

.ed-nav a:hover {
    text-decoration: underline;
}

.postres-title {
    font-family: 'Fredoka', sans-serif;
    font-weight: 600;
}

.postres-card {
    height: 100%;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    background: white;
    border: 1px solid #e9ecef;
    border-radius: 16px;
    box-shadow: 0 3px 10px #0000000a;
    transition: transform .2s ease, box-shadow .2s ease;
}

.postres-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 22px #00000012;
}

.postres-card .card-img-top {
    width: 100%;
    height: 210px;
    object-fit: cover;
    border-bottom: 4px solid var(--primary);
}

.imagen-vacia {
    height: 210px;
    display: grid;
    place-items: center;
    background: #f8f9fa;
    font-size: 4rem;
    border-bottom: 4px solid var(--primary);
}

.card-body {
    padding: 20px;
    display: flex;
    flex-direction: column;
    flex: 1;
}


.card-title {
    font-family: 'Fredoka', sans-serif;
    font-weight: 600;
    min-height: 2.4em;
}

.card-text {
    color: #6c757d;
    min-height: 3em;
    line-height: 1.5;
}


.precio {
    color: var(--primary);
    font: 700 1.6rem 'Fredoka', sans-serif;
    margin: 12px 0 18px;
}

.acciones-postre {
    display: flex;
    gap: 8px;
    margin-top: auto;
}

.acciones-postre .btn {
    flex: 1;
    font-weight: 600;
    padding: 10px 8px;
    border-radius: 8px;
}

.btn-postre {
    background: var(--primary);
    border-color: var(--primary);
    color: white;
}

.btn-postre:hover {
    background: var(--primary-dark);
    border-color: var(--primary-dark);
    color: white;
}

.btn-postre:disabled {
    background: #6c757d;
    border-color: #6c757d;
    color: white;
}

.btn-ver {
    border: 1px solid var(--primary);
    color: var(--primary);
    background: white;
}

.btn-ver:hover {
    background: #fff0f2;
    border-color: var(--primary);
    color: var(--primary-dark);
}

.boton-carrito {
    position: fixed;
    bottom: 22px;
    right: 22px;
    width: 60px;
    height: 60px;
    border: 0;
    border-radius: 50%;
    background: var(--primary);
    color: white;
    font-size: 25px;
    box-shadow: 0 5px 15px #00000030;
    cursor: pointer;
    z-index: 999;
}

.contador-carrito {
    position: absolute;
    top: -5px;
    right: -5px;
    min-width: 25px;
    height: 25px;
    padding: 3px;
    background: white;
    color: var(--primary);
    border: 1px solid var(--primary);
    border-radius: 50%;
    font-size: 13px;
    font-weight: bold;
    display: flex;
    align-items: center;
    justify-content: center;
}

.ed-footer {
    margin-top: auto;
    padding: 18px 20px;
    background: var(--primary);
    color: white;
    width: 100%;
}

.ed-footer .page-wrap {
    max-width: 1200px;
    margin: auto;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 25px;
}

.footer-logo {
    width: 60px;
    height: 60px;
    object-fit: contain;
    background: white;
    border-radius: 8px;
    padding: 4px;
}

.footer-link {
    color: white;
}

.btn-whatsapp {
    display: inline-block;
    padding: 12px 18px;
    background: #25D366;
    color: white;
    border-radius: 30px;
    text-decoration: none;
    font-weight: 600;
}

.btn-whatsapp:hover {
    background: #128C7E;
    color: white;
}

.sin-productos {
    padding: 40px 20px;
    text-align: center;
    background: white;
    border-radius: 12px;
}

@media(max-width:768px) {
    .ed-header,
    .ed-footer .page-wrap {
        flex-direction: column;
        text-align: center;
    }

    .ed-nav {
        justify-content: center;
    }
}

@media(max-width:480px) {
    
/* ================================
   FOOTER ESTRELLA DULCE
================================ */

.ed-footer {
    margin-top: auto;
    padding: 30px 20px;
    background: var(--primary);
    color: white;
    width: 100%;
}

.ed-footer .page-wrap {
    width: 100%;
    max-width: 1200px;
    margin: 0 auto;

    display: grid !important;
    grid-template-columns: 80px minmax(0, 1fr) minmax(0, 1fr) 80px;

    align-items: center;
    justify-content: space-between;
    gap: 20px;
}

/* LOGOS */

.footer-logo {
    width: 80px;
    height: 80px;
    object-fit: contain;
    background: white;
    border-radius: 12px;
    padding: 8px;
}

/* INFORMACIÓN DE LA EMPRESA */

.ed-footer-info {
    text-align: center;
    margin: 0 !important;
}

.ed-footer p {
    margin-bottom: 7px;
    font-size: 14px;
    line-height: 1.4;
}

.ed-footer-info strong {
    color: white;
    font-weight: 700;
}

.footer-link,
.footer-link:visited,
.footer-link:hover,
.footer-link:active {
    color: white !important;
    text-decoration: none !important;
}

/* ATENCIÓN AL CLIENTE */

.customer-service-title {
    color: white;
    font-size: 15px;
    font-weight: 700;
}

.ed-footer ul {
    margin: 0 !important;
    padding: 0;
}

/* BOTÓN WHATSAPP */


.btn-whatsapp,
.btn-whatsapp:visited,
.btn-whatsapp:active {
    display: inline-block;
    padding: 12px 24px;
    background: white !important;
    color: var(--primary) !important;
    border: none;
    border-radius: 9px;
    text-decoration: none !important;
    font-size: 14px;
    font-weight: 500;
    transition: .2s ease;
}

.btn-whatsapp:hover {
    background: #f5f5f5 !important;
    color: var(--primary-dark) !important;
    text-decoration: none !important;
}
}

.btn-ed.btn-ed--white:hover {
    background-color: #d9fbe5 !important;
    color: #168044 !important;
    border-color: #d9fbe5 !important;
    text-decoration: none !important;
}

/* RESPONSIVE */

@media (max-width: 768px) {

    .ed-footer .page-wrap {
        grid-template-columns: 1fr;
        justify-items: center;
        text-align: center;
        gap: 18px;
    }

    .ed-footer-info {
        text-align: center;
    }

    .ed-footer ul {
        text-align: center !important;
    }

}

@media (max-width: 480px) {

    .footer-logo {
        width: 70px;
        height: 70px;
    }

    .ed-footer {
        padding: 25px 15px;
    }

    .ed-footer p {
        font-size: 13px;
    }

    .btn-whatsapp {
        padding: 11px 18px;
    }

}

}
</style>
</head>

<body>

<header class="ed-header">

    <a href="../index.php" class="ed-header__brand">
        <img
            src="../imagen/ChatGPT Image 5 nov 2025, 10_50_14 p.m..png"
            alt="Logo Estrella Dulce"
            class="ed-header__logo"
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

            <a href="iniciar%20sesi%C3%B3n.php">👤 Iniciar sesión</a>

        <?php endif; ?>

        <a href="../index.php">🏠 Inicio</a>
    </nav>

</header>

<main class="container my-5">

    <h1 class="postres-title text-center mb-5">
        🍰 Explora nuestros deliciosos postres 🍨
    </h1>

    <div class="row">

        <?php if (count($postres) > 0): ?>

            <?php foreach ($postres as $postre): ?>

                <?php
                $id = (int)$postre["id_postre"];
                $nombre = (string)$postre["nombre"];
                $descripcion = (string)($postre["descripcion"] ?? "");
                $precio = (float)$postre["precio"];
                $stock = (int)$postre["stock"];
                $imagen = (string)($postre["imagen"] ?? "");
                ?>

                <div class="col-12 col-md-6 col-lg-4 mb-4">

                    <article class="postres-card">

                        <?php if ($imagen !== ""): ?>

                            <img
                                src="../imagen/<?= escapar($imagen) ?>"
                                class="card-img-top"
                                alt="<?= escapar($nombre) ?>"
                                loading="lazy"
                            >

                        <?php else: ?>

                            <div
                                class="imagen-vacia"
                                role="img"
                                aria-label="Imagen no disponible"
                            >
                                🍰
                            </div>

                        <?php endif; ?>

                        <div class="card-body">

                            <h2 class="card-title">
                                <?= escapar($nombre) ?>
                            </h2>

                            <p class="card-text">
                                <?= escapar($descripcion) ?>
                            </p>

                            <p class="precio">
                                $<?= number_format($precio, 0, ',', '.') ?>
                            </p>

                            <div class="acciones-postre">

                                <a
                                    href="detalle_postre.php?id=<?= $id ?>"
                                    class="btn btn-ver"
                                >
                                    Ver detalle
                                </a>

                                <button
                                    type="button"
                                    class="btn btn-postre btn-agregar"
                                    data-id="<?= $id ?>"
                                    data-nombre="<?= escapar($nombre) ?>"
                                    data-precio="<?= escapar((string)$precio) ?>"
                                    data-stock="<?= $stock ?>"
                                    data-imagen="<?= escapar($imagen) ?>"
                                    <?= $stock <= 0 ? 'disabled' : '' ?>
                                >
                                    <?= $stock <= 0 ? 'Agotado' : 'Agregar 🛒' ?>
                                </button>

                            </div>

                        </div>

                    </article>

                </div>

            <?php endforeach; ?>

        <?php else: ?>

            <div class="col-12">
                <div class="sin-productos">
                    <h2>🍰 No hay postres disponibles</h2>
                    <p class="mb-0">
                        En este momento no tenemos productos disponibles.
                        Vuelve a visitarnos pronto.
                    </p>
                </div>
            </div>

        <?php endif; ?>

    </div>

</main>

<button
    class="boton-carrito"
    id="botonCarrito"
    type="button"
    aria-label="Abrir carrito"
>
    🛒
    <span id="contador" class="contador-carrito">0</span>
</button>



<footer class="ed-footer">



<footer class="ed-footer">

    <div class="page-wrap">

        <!-- LOGO PASCUAL BRAVO -->
        <img
            src="../imagen/Logo Pascual Bravo 2.jpg"
            alt="Logo Pascual Bravo"
            class="footer-logo"
        >

        <!-- INFORMACIÓN DE LA EMPRESA -->
        <div class="ed-footer-info">

            <p>
                <strong>Estrella Dulce S.A.</strong>
            </p>

            <p>
                Medellín, Antioquia
            </p>

            <p>
                <a href="#" class="footer-link">
                    Términos y condiciones
                </a>

                |

                <a href="#" class="footer-link">
                    Política de privacidad
                </a>
            </p>

            <p>
                &copy; 2026 Estrella Dulce.
                Todos los derechos reservados.
            </p>

        </div>

        <!-- ATENCIÓN AL CLIENTE -->
        <ul class="list-unstyled">

            <li class="mb-2">

                <span class="customer-service-title">
                    Atención al cliente
                </span>

                <br>

                <p class="mb-0">
                    Tel: +3146199121 |
                    Email: proyectpostres@gmail.com
                </p>

            </li>

            <li style="margin-top: 12px;">

                <a
                    href="https://api.whatsapp.com/send?phone=573146199121&amp;text=Hola,%20vengo%20de%20la%20p%C3%A1gina%20web"
                    class="btn-whatsapp"
                    target="_blank"
                    rel="noopener"
                >
                    💬 Escríbenos por WhatsApp
                </a>

            </li>

        </ul>

        <!-- LOGO FÉLIX DE BEDOUT -->
        <img
            src="../imagen/I.E F├®lix De Bedout Moreno Escudo.png"
            alt="Logo Félix"
            class="footer-logo"
        >

    </div>

</footer>


</footer>

<script>
"use strict";

/* CARGAR CARRITO */
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

let carrito = cargarCarrito();

/* FORMATO DE PRECIO */
function formatearPrecio(precio) {
    return Number(precio).toLocaleString("es-CO", {
        maximumFractionDigits: 0
    });
}

/* GUARDAR CARRITO */
function guardarCarrito() {
    try {
        localStorage.setItem("carrito", JSON.stringify(carrito));

        actualizarContador();

        window.dispatchEvent(new Event("carritoActualizado"));

        return true;

    } catch (error) {
        alert("No se pudo guardar el carrito en este navegador.");
        return false;
    }
}

/* AGREGAR PRODUCTO */
function agregarCarrito(id, nombre, precio, stock, imagen) {
    id = Number(id);
    precio = Number(precio);
    stock = Number(stock);

    if (!Number.isFinite(precio) || precio < 0) {
        alert("El precio del producto no es válido.");
        return;
    }

    if (stock <= 0) {
        alert("Este postre está agotado.");
        return;
    }

    carrito = cargarCarrito();

    const productoExistente = carrito.find(producto =>
        Number(producto.id_postre) === id &&
        !producto.tamano
    );

    const cantidadActual = productoExistente
        ? Math.max(0, Number(productoExistente.cantidad) || 0)
        : 0;

    const cantidadTotalPostre = carrito.reduce((total, producto) => {
        if (Number(producto.id_postre) === id) {
            return total + Math.max(
                0,
                Number(producto.cantidad) || 0
            );
        }

        return total;
    }, 0);

    if (cantidadTotalPostre >= stock) {
        alert("No hay más unidades disponibles de " + nombre + ".");
        return;
    }

    if (productoExistente) {
        productoExistente.cantidad = cantidadActual + 1;
        productoExistente.precio = precio;
        productoExistente.imagen = imagen || "";
    } else {
        carrito.push({
            id_postre: id,
            nombre: nombre,
            precio: precio,
            imagen: imagen || "",
            cantidad: 1
        });
    }

    if (guardarCarrito()) {
        alert(
            nombre + " agregado al carrito por $" +
            formatearPrecio(precio)
        );
    }
}

/* CONECTAR BOTONES */
document.querySelectorAll(".btn-agregar").forEach(boton => {
    boton.addEventListener("click", function() {
        agregarCarrito(
            this.dataset.id,
            this.dataset.nombre,
            this.dataset.precio,
            this.dataset.stock,
            this.dataset.imagen
        );
    });
});

/* ACTUALIZAR CONTADOR */
function actualizarContador() {
    const contador = document.getElementById("contador");

    if (!contador) {
        return;
    }

    const cantidadTotal = carrito.reduce((total, producto) => {
        return total + Math.max(
            0,
            Number(producto.cantidad) || 0
        );
    }, 0);

    contador.textContent = cantidadTotal;
}

/* ABRIR CARRITO */
document.getElementById("botonCarrito").addEventListener("click", function() {
    window.location.href = "carrito.php";
});

/* SINCRONIZAR ENTRE PESTAÑAS */
window.addEventListener("storage", function(evento) {
    if (evento.key === "carrito") {
        carrito = cargarCarrito();
        actualizarContador();
    }
});

/* INICIALIZAR */
actualizarContador();
</script>

</body>
</html>
