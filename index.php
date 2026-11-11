<?php
session_start();
require_once "conexion.php";

// SESIÓN DEL USUARIO
$usuarioLogueado = isset($_SESSION["id_usuario"]);
$nombreUsuario = $usuarioLogueado ? ($_SESSION["nombre"] ?? "") : "";
$apellidoUsuario = $usuarioLogueado ? ($_SESSION["apellido"] ?? "") : "";

// POSTRES DESTACADOS
try {
    $stmtDestacados = $conexion->prepare("SELECT p.id_postre, p.nombre, p.descripcion, p.precio, p.stock, p.imagen, p.estado, p.destacado, c.nombre AS categoria
        FROM postres p
        INNER JOIN categorias c ON p.id_categoria = c.id_categoria
        WHERE p.estado = 'DISPONIBLE' AND p.destacado = 1
        ORDER BY p.id_postre ASC LIMIT 3");
    $stmtDestacados->execute();
    $postresDestacados = $stmtDestacados->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log($e->getMessage());
    $postresDestacados = [];
}

function precioColombia($precio) {
    return "$" . number_format((float)$precio, 0, ",", ".");
}

function escaparIndex($valor) {
    return htmlspecialchars((string)$valor, ENT_QUOTES, "UTF-8");
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Estrella Dulce - Postres Artesanales</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
:root {
    --primary: #DB1A35;
    --primary-dark: #b8152c;
    --white: #fff;
    --gray-100: #f8f9fa;
    --gray-200: #e9ecef;
    --gray-600: #6c757d;
    --gray-900: #212529;
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    background: var(--gray-100);
    font-family: Inter, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    color: var(--gray-900);
}

.ed-header {
    background: var(--primary);
    padding: .8rem 2rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    box-shadow: 0 2px 8px rgba(219,26,53,.3);
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
    background: #fff;
    padding: 4px;
    border: 2px solid #fff;
}

.ed-header__title {
    font-size: 1.6rem;
    font-weight: 700;
    color: #fff;
    letter-spacing: -.5px;
    margin: 0;
}

.ed-nav {
    display: flex;
    align-items: center;
    gap: 1.5rem;
    flex-wrap: wrap;
}

.ed-nav > a {
    color: #fff;
    text-decoration: none;
    font-weight: 500;
    font-size: .95rem;
    transition: all .2s;
    padding: .3rem 0;
    border-bottom: 2px solid transparent;
}

.ed-nav > a:hover {
    border-bottom-color: #fff;
    opacity: .85;
}

.ed-cart {
    position: relative;
    display: flex;
    align-items: center;
    gap: 4px;
    font-size: 1.2rem;
    color: #fff !important;
}

.ed-cart-count {
    background: #fff;
    color: var(--primary);
    font-weight: 700;
    font-size: .7rem;
    padding: .1rem .45rem;
    border-radius: 20px;
    min-width: 20px;
    text-align: center;
    line-height: 1.5;
}

.btn-ed {
    display: inline-block;
    padding: .55rem 1.8rem;
    font-weight: 500;
    border-radius: 8px;
    text-decoration: none;
    transition: all .2s;
    border: 1px solid transparent;
    cursor: pointer;
    text-align: center;
    font-size: .95rem;
}

.btn-ed--primary {
    background: var(--primary);
    color: #fff;
    border-color: var(--primary);
}

.btn-ed--primary:hover {
    background: var(--primary-dark);
    border-color: var(--primary-dark);
    color: #fff;
}

.btn-ed--outline-primary {
    background: transparent;
    color: var(--primary);
    border-color: var(--primary);
}

.btn-ed--outline-primary:hover {
    background: var(--primary);
    color: #fff;
}

.btn-ed--white {
    background: #fff;
    color: var(--primary);
    border-color: #fff;
}

.btn-ed--white:hover {
    background: rgba(255,255,255,.85);
    color: var(--primary-dark);
}

.carousel-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 3rem 2rem 0;
}

.carousel-item img {
    height: 400px;
    object-fit: cover;
    border-radius: 12px;
}

.carousel-control-prev-icon,
.carousel-control-next-icon {
    background-color: rgba(219,26,53,.7);
    border-radius: 50%;
    padding: 1.5rem;
    background-size: 50% 50%;
}

.card-postre {
    background: #fff;
    border-radius: 12px;
    overflow: hidden;
    transition: transform .2s, box-shadow .2s;
    height: 100%;
    border: 1px solid var(--gray-200) !important;
    box-shadow: 0 1px 3px rgba(0,0,0,.04);
}

.card-postre:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 25px rgba(219,26,53,.12);
    border-color: var(--primary) !important;
}

.card-postre .card-img-top {
    height: 200px;
    object-fit: cover;
    border-bottom: 2px solid var(--primary);
}

.card-postre .card-title {
    color: var(--gray-900);
    font-weight: 600;
    font-size: 1.2rem;
}

.card-postre .card-text {
    color: var(--gray-600);
    font-size: .95rem;
    line-height: 1.5;
    min-height: 72px;
}

.card-postre .card-body {
    padding: 1.25rem;
    display: flex;
    flex-direction: column;
}

.badge-destacado {
    position: absolute;
    top: 12px;
    left: 12px;
    background: var(--primary);
    color: #fff;
    padding: .35rem .7rem;
    border-radius: 20px;
    font-size: .78rem;
    font-weight: 600;
    z-index: 2;
}

.imagen-postre {
    position: relative;
}

.usuario-menu {
    display: flex;
    align-items: center;
    gap: .8rem;
}

.usuario-nombre {
    color: #fff;
    font-weight: 600;
    font-size: .95rem;
    white-space: nowrap;
}

.btn-cerrar-sesion {
    color: #fff !important;
    text-decoration: none !important;
    border: 1px solid rgba(255,255,255,.8);
    padding: .35rem .8rem !important;
    border-radius: 6px;
    transition: all .2s;
    white-space: nowrap;
}

.btn-cerrar-sesion:hover {
    background: #fff;
    color: var(--primary) !important;
}

.ed-footer {
    background: var(--primary);
    color: #fff;
    padding: 2.5rem 1.5rem;
    margin-top: 3rem;
}

.ed-footer .page-wrap {
    max-width: 1200px;
    margin: 0 auto;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 2rem;
}

.footer-logo {
    width: 80px;
    height: 80px;
    object-fit: contain;
    background: #fff;
    border-radius: 12px;
    padding: 6px;
    border: 2px solid #fff;
}

.ed-footer-info p {
    margin: .2rem 0;
    font-size: .9rem;
    color: rgba(255,255,255,.85);
}

.ed-footer-info a.footer-link {
    color: rgba(255,255,255,.85);
    text-decoration: none;
    transition: color .2s;
}

.ed-footer-info a.footer-link:hover {
    color: #fff;
    text-decoration: underline;
}

.customer-service-title {
    color: #fff;
    font-size: 1rem;
    font-weight: 600;
}

.btn-administracion {
    color: #fff;
    text-decoration: none;
    font-weight: 500;
    font-size: .95rem;
    transition: all .2s;
    padding: .3rem 0;
    border-bottom: 2px solid transparent;
}

.btn-administracion:hover {
    border-bottom-color: #fff;
    opacity: .85;
}
@media(max-width:768px) {
    .ed-header {
        flex-direction: column;
        align-items: center;
        padding: .8rem 1rem;
        gap: .6rem;
    }

    .ed-nav {
        justify-content: center;
        gap: 1rem;
    }

    .ed-header__title {
        font-size: 1.3rem;
    }

    .carousel-item img {
        height: 220px;
    }

    .ed-footer .page-wrap {
        flex-direction: column;
        text-align: center;
    }

    .footer-logo {
        width: 70px;
        height: 70px;
    }

    .usuario-menu {
        justify-content: center;
        flex-wrap: wrap;
    }
}

@media(max-width:480px) {
    .ed-nav > a {
        font-size: .85rem;
    }

    .btn-ed {
        padding: .4rem 1.2rem;
        font-size: .85rem;
    }
}
</style>
</head>

<body>

<header class="ed-header">

    <a href="index.php" class="ed-header__brand">
        <img src="./imagen/ChatGPT Image 5 nov 2025, 10_50_14 p.m..png"
             alt="Logo Estrella Dulce"
             class="ed-header__logo">

        <h1 class="ed-header__title">Estrella Dulce</h1>
    </a>

    <nav class="ed-nav">

        <a href="vistas/postres.php">Postres</a>
        <a href="vistas/nosotros.html">Nosotros</a>
        <a href="vistas/contacto.php">Contacto</a>

        <a href="vistas/carrito.php" class="ed-cart" aria-label="Carrito">
            🛒 <span id="cart-count" class="ed-cart-count">0</span>
        </a>

        <?php if ($usuarioLogueado): ?>

<div class="usuario-menu">

    <?php if (
        isset($_SESSION['rol']) &&
        strtoupper(trim($_SESSION['rol'])) === 'ADMINISTRADOR'
    ): ?>
        <a href="admin.php" class="btn-administracion ed-nav">
            ⚙️ Administrador
        </a>
    <?php else: ?>
        <span class="usuario-nombre">
            👤 <?= escaparIndex(trim($nombreUsuario . " " . $apellidoUsuario)) ?>
        </span>
    <?php endif; ?>

    <a href="cerrar_sesion.php" class="btn-cerrar-sesion">
        Cerrar sesión
    </a>

</div>

<?php else: ?>

<a href="vistas/iniciar_sesion.php" aria-label="Iniciar sesión">
    👤 Iniciar sesión
</a>

<?php endif; ?>


    </nav>
</header>

<main>

    <div class="page-wrap carousel-container">

        <div id="postresCarousel" class="carousel slide mb-5" data-bs-ride="carousel">

            <div class="carousel-inner">

                <div class="carousel-item active">
                    <img src="./imagen/postre_cafe.jpg"
                         class="d-block w-100"
                         alt="Postre de café">
                </div>

                <div class="carousel-item">
                    <img src="./imagen/postre_milo.jpg"
                         class="d-block w-100"
                         alt="Postre de Milo">
                </div>

                <div class="carousel-item">
                    <img src="./imagen/Postre de Fresa Chocolate.png"
                         class="d-block w-100"
                         alt="Postre de fresa y chocolate">
                </div>

            </div>

            <button class="carousel-control-prev"
                    type="button"
                    data-bs-target="#postresCarousel"
                    data-bs-slide="prev">

                <span class="carousel-control-prev-icon"></span>
                <span class="visually-hidden">Anterior</span>

            </button>

            <button class="carousel-control-next"
                    type="button"
                    data-bs-target="#postresCarousel"
                    data-bs-slide="next">

                <span class="carousel-control-next-icon"></span>
                <span class="visually-hidden">Siguiente</span>

            </button>

        </div>

    </div>

    <section class="container my-5">

        <h2 class="text-center mb-4"
            style="color:var(--gray-900);font-weight:700">

            Nuestros Postres Destacados

        </h2>

        <div class="row text-center align-items-stretch">

            <?php if (!empty($postresDestacados)): ?>

                <?php foreach ($postresDestacados as $postre): ?>

                    <?php
                    $idPostre = (int)$postre["id_postre"];
                    $nombrePostre = (string)$postre["nombre"];

                    $imagenPostre = !empty($postre["imagen"])
                        ? "imagen/" . $postre["imagen"]
                        : "imagen/postre_cafe.jpg";

                    $descripcionPostre = trim(
                        (string)($postre["descripcion"] ?? "")
                    );

                    if ($descripcionPostre === "") {
                        $descripcionPostre = "Delicioso postre artesanal de Estrella Dulce.";
                    }
                    ?>

                    <div class="col-12 col-md-6 col-lg-4 mb-4">

                        <div class="card card-postre">

                            <div class="imagen-postre">

                                <span class="badge-destacado">
                                    ⭐ Destacado
                                </span>

                                <a href="vistas/postres.php?id=<?= $idPostre ?>">

                                    <img
                                        src="<?= escaparIndex($imagenPostre) ?>"
                                        class="card-img-top"
                                        alt="<?= escaparIndex($nombrePostre) ?>"
                                        onerror="this.onerror=null;this.src='imagen/postre_cafe.jpg';"
                                    >

                                </a>

                            </div>

                            <div class="card-body">

                                <h5 class="card-title">
                                    <?= escaparIndex($nombrePostre) ?>
                                </h5>

                                <p class="card-text">
                                    <?= escaparIndex($descripcionPostre) ?>
                                </p>

                                <p class="fw-bold"
                                   style="color:var(--primary);font-size:1.1rem">

                                    <?= precioColombia($postre["precio"]) ?>

                                </p>

                                <div class="d-flex gap-2 mt-auto">

                                    <a href="vistas/postres.php?id=<?= $idPostre ?>"
                                       class="btn-ed btn-ed--outline-primary flex-fill">

                                        Ver Más

                                    </a>

                                    <button
                                        type="button"
                                        class="btn-ed btn-ed--primary flex-fill btn-agregar-index"
                                        data-id="<?= $idPostre ?>"
                                        data-nombre="<?= escaparIndex($nombrePostre) ?>"
                                        data-precio="<?= escaparIndex((string)$postre["precio"]) ?>"
                                        data-imagen="<?= escaparIndex($imagenPostre) ?>"
                                        aria-label="Agregar <?= escaparIndex($nombrePostre) ?> al carrito">

                                        🛒 Agregar

                                    </button>

                                </div>

                            </div>

                        </div>

                    </div>

                <?php endforeach; ?>

            <?php else: ?>

                <div class="col-12">

                    <div class="alert alert-light text-center"
                         style="border:1px solid var(--gray-200);border-radius:12px;padding:2rem">

                        <h5 style="color:var(--gray-900);font-weight:600">
                            Próximamente nuestros destacados 🍰
                        </h5>

                        <p class="mb-0" style="color:var(--gray-600)">
                            El administrador todavía no ha seleccionado postres destacados.
                        </p>

                    </div>

                </div>

            <?php endif; ?>

        </div>

    </section>

</main>

<footer class="ed-footer">

    <div class="page-wrap d-flex flex-column flex-md-row align-items-center justify-content-between">

        <img src="./imagen/Logo Pascual Bravo 2.jpg"
             alt="Logo Pascual Bravo"
             class="footer-logo">

        <div class="text-center ed-footer-info my-3 my-md-0">

            <p>
                <strong style="color:#fff">Estrella Dulce S.A.</strong>
            </p>

            <p>Medellín, Antioquia</p>

            <p>
                <a href="#" class="footer-link">Términos y condiciones</a>
                |
                <a href="#" class="footer-link">Política de privacidad</a>
            </p>

            <p>
                &copy; 2026 Estrella Dulce. Todos los derechos reservados.
            </p>

        </div>

        <ul class="list-unstyled text-center text-md-start mb-0">

            <li class="mb-2">

                <span class="customer-service-title">
                    Atención al cliente
                </span>

                <br>

                <p class="mb-0"
                   style="color:rgba(255,255,255,.85);font-size:.9rem">

                    Tel: +3146199121 |
                    Email: proyectpostres@gmail.com

                </p>

            </li>

            <li style="margin-top:.75rem">

                <a href="https://api.whatsapp.com/send?phone=573146199121&amp;text=Hola,%20vengo%20de%20la%20p%C3%A1gina%20web"
                   class="btn-ed btn-ed--white"
                   target="_blank"
                   rel="noopener">

                    💬 Escríbenos por WhatsApp

                </a>

            </li>

        </ul>

        <img src="./imagen/I.E F├®lix De Bedout Moreno Escudo.png"
             alt="Logo Félix"
             class="footer-logo">

    </div>

</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
"use strict";

// Se conserva el mismo formato que utiliza vistas/carrito.php.
const CLAVE_CARRITO = "carrito";

const USUARIO_LOGUEADO = <?= $usuarioLogueado ? 'true' : 'false' ?>;


// LEER CARRITO
function leerCarrito() {

    try {

        const datos = JSON.parse(
            localStorage.getItem(CLAVE_CARRITO) || "[]"
        );

        return Array.isArray(datos) ? datos : [];

    } catch (error) {

        console.error("No se pudo leer el carrito:", error);

        return [];

    }

}


// ACTUALIZAR CONTADOR
function actualizarContadorCarrito() {

    const carrito = leerCarrito();

    const cantidadTotal = carrito.reduce((total, item) => {

        const cantidad = Number.parseInt(item.cantidad, 10) || 0;

        return total + Math.max(0, cantidad);

    }, 0);

    const contador = document.getElementById("cart-count");

    if (contador) {
        contador.textContent = cantidadTotal;
    }

}


// GUARDAR CARRITO
function guardarCarrito(carrito) {

    try {

        localStorage.setItem(
            CLAVE_CARRITO,
            JSON.stringify(carrito)
        );

        actualizarContadorCarrito();

        return true;

    } catch (error) {

        console.error("No se pudo guardar el carrito:", error);

        alert("No se pudo guardar el producto. Revisa que el navegador permita el almacenamiento.");

        return false;

    }

}


// AGREGAR PRODUCTO AL CARRITO
function agregarCarrito(idPostre, nombre, precio, imagen) {

    const id = Number(idPostre);
    const valor = Number(precio);

    if (
        !Number.isInteger(id) ||
        id <= 0 ||
        !Number.isFinite(valor) ||
        valor < 0
    ) {

        alert("No se pudo agregar el producto porque sus datos no son válidos.");

        return;

    }

    const carrito = leerCarrito();

    const existente = carrito.find(
        item => Number(item.id_postre) === id
    );

    if (existente) {

        existente.cantidad =
            Math.max(0, Number.parseInt(existente.cantidad, 10) || 0) + 1;

        existente.nombre = String(nombre);
        existente.precio = valor;
        existente.imagen = String(imagen || existente.imagen || "");

    } else {

        carrito.push({

            id_postre: id,
            nombre: String(nombre),
            precio: valor,
            imagen: String(imagen || ""),
            cantidad: 1

        });

    }

    // Guardar primero para evitar perder el producto.
    if (!guardarCarrito(carrito)) {
        return;
    }

    if (!USUARIO_LOGUEADO) {

        alert("Producto agregado al carrito. Inicia sesión para continuar.");

        window.location.href = "vistas/carrito.php";

        return;

    }

    alert(String(nombre) + " se agregó al carrito.");

}


// DETECTAR CLIC EN AGREGAR
document.addEventListener("click", function(evento) {

    const boton = evento.target.closest(".btn-agregar-index");

    if (!boton) {
        return;
    }

    evento.preventDefault();

    agregarCarrito(
        boton.dataset.id,
        boton.dataset.nombre,
        boton.dataset.precio,
        boton.dataset.imagen
    );

});


// ACTUALIZAR CONTADOR AL CARGAR
document.addEventListener(
    "DOMContentLoaded",
    actualizarContadorCarrito
);


// ACTUALIZAR SI CAMBIA EL CARRITO EN OTRA PESTAÑA
window.addEventListener("storage", function(evento) {

    if (evento.key === CLAVE_CARRITO) {
        actualizarContadorCarrito();
    }

});

</script>

</body>
</html>