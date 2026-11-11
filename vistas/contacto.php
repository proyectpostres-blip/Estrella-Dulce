<?php
session_start();

require_once "../conexion.php";

$usuarioLogueado = isset($_SESSION["id_usuario"]);

$nombreUsuario = "";
$apellidoUsuario = "";
$correoUsuario = "";

if ($usuarioLogueado) {
    $nombreUsuario = $_SESSION["nombre"] ?? "";
    $apellidoUsuario = $_SESSION["apellido"] ?? "";
    $correoUsuario = $_SESSION["correo"] ?? "";
}

$mensajeExito = "";
$mensajeError = "";

/* =====================================================
   GUARDAR MENSAJE DE CONTACTO
   ===================================================== */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $nombre = trim($_POST["nombre"] ?? "");
    $correo = trim($_POST["correo"] ?? "");
    $mensaje = trim($_POST["mensaje"] ?? "");

    if ($nombre === "" || $correo === "" || $mensaje === "") {

        $mensajeError = "Por favor completa todos los campos.";

    } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {

        $mensajeError = "Por favor ingresa un correo electrónico válido.";

    } else {

        try {

            $idUsuario = $usuarioLogueado
                ? $_SESSION["id_usuario"]
                : null;

            $sql = "INSERT INTO mensajes_contacto
                    (id_usuario, nombre, correo, mensaje)
                    VALUES (?, ?, ?, ?)";

            $stmt = $conexion->prepare($sql);

            $stmt->execute([
                $idUsuario,
                $nombre,
                $correo,
                $mensaje
            ]);

            $mensajeExito = "💌 ¡Gracias por escribirnos, "
                . htmlspecialchars($nombre, ENT_QUOTES, 'UTF-8')
                . "! Hemos recibido tu mensaje y te responderemos lo antes posible.";

        } catch (PDOException $e) {

            $mensajeError = "No fue posible guardar el mensaje. Inténtalo nuevamente.";

            // Para pruebas puedes activar esta línea:
            // $mensajeError .= " " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Atención al Cliente - Estrella Dulce</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">

    <link href="https://fonts.googleapis.com/css2?family=Fredoka:wght@400;500;600;700&family=Work+Sans:wght@400;500;600&display=swap" rel="stylesheet">

    <style>

        :root {
            --primary: #DB1A35;
            --primary-dark: #b8152c;
            --white: #ffffff;
            --gray-100: #f8f9fa;
            --gray-200: #e9ecef;
            --gray-300: #dee2e6;
            --gray-400: #ced4da;
            --gray-600: #6c757d;
            --gray-700: #495057;
            --gray-900: #212529;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background-color: var(--gray-100);
            font-family: 'Work Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            color: var(--gray-900);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* HEADER - ROJO */

        .ed-header {
            background: var(--primary);
            padding: 0.8rem 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            box-shadow: 0 2px 8px rgba(219, 26, 53, 0.3);
        }

        .ed-header__brand {
            display: flex;
            align-items: center;
            gap: 0.8rem;
            text-decoration: none;
        }

        .ed-header__logo {
            width: 50px;
            height: 50px;
            object-fit: contain;
            border-radius: 50%;
            background: var(--white);
            padding: 4px;
            border: 2px solid var(--white);
        }

        .ed-header__title {
            font-family: 'Fredoka', sans-serif;
            font-size: 1.6rem;
            font-weight: 600;
            color: var(--white);
            letter-spacing: -0.5px;
            margin: 0;
        }

        .ed-nav {
            display: flex;
            align-items: center;
            gap: 1.5rem;
            flex-wrap: wrap;
        }

        .ed-nav a {
            color: var(--white);
            text-decoration: none;
            font-weight: 500;
            font-size: 0.95rem;
            transition: all 0.2s ease;
            padding: 0.3rem 0;
            border-bottom: 2px solid transparent;
        }

        .ed-nav a:hover {
            border-bottom-color: var(--white);
            opacity: 0.85;
        }

        .ed-cart {
            position: relative;
            display: flex;
            align-items: center;
            gap: 4px;
            font-size: 1.2rem;
            color: var(--white);
        }

        /* USUARIO */

        .usuario-menu {
            display: flex;
            align-items: center;
            gap: 0.8rem;
        }

        .usuario-nombre {
            color: var(--white);
            font-weight: 600;
            font-size: 0.95rem;
            white-space: nowrap;
        }

        .btn-cerrar-sesion {
            color: var(--white) !important;
            text-decoration: none !important;
            border: 1px solid rgba(255, 255, 255, 0.8);
            padding: 0.35rem 0.8rem !important;
            border-radius: 6px;
            transition: all 0.2s ease;
            white-space: nowrap;
            font-size: 0.9rem !important;
        }

        .btn-cerrar-sesion:hover {
            background: var(--white);
            color: var(--primary) !important;
            border-bottom-color: rgba(255, 255, 255, 0.8) !important;
            opacity: 1 !important;
        }

        /* BOTONES */

        .btn-ed {
            display: inline-block;
            padding: 0.55rem 1.8rem;
            font-weight: 500;
            border-radius: 8px;
            text-decoration: none;
            transition: all 0.2s ease;
            border: 1px solid transparent;
            cursor: pointer;
            text-align: center;
            font-size: 0.95rem;
            font-family: 'Work Sans', sans-serif;
        }

        .btn-ed--white {
            background: var(--white);
            color: var(--primary);
            border-color: var(--white);
        }

        .btn-ed--white:hover {
            background: rgba(255, 255, 255, 0.85);
            color: var(--primary-dark);
        }

        /* FOOTER */

        .ed-footer {
            background: var(--primary);
            color: var(--white);
            padding: 2.5rem 1.5rem;
            margin-top: auto;
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
            background: var(--white);
            border-radius: 12px;
            padding: 6px;
            border: 2px solid var(--white);
        }

        .ed-footer-info p {
            margin: 0.2rem 0;
            font-size: 0.9rem;
            color: rgba(255, 255, 255, 0.85);
            font-family: 'Work Sans', sans-serif;
        }

        .ed-footer-info a.footer-link {
            color: rgba(255, 255, 255, 0.85);
            text-decoration: none;
            transition: color 0.2s;
        }

        .ed-footer-info a.footer-link:hover {
            color: var(--white);
            text-decoration: underline;
        }

        .customer-service-title {
            color: var(--white);
            font-size: 1rem;
            font-weight: 600;
            font-family: 'Fredoka', sans-serif;
        }

        /* CONTACTO */

        .contacto-main {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 3rem 1.5rem;
        }

        .contacto-contenedor {
            max-width: 600px;
            width: 100%;
            background: var(--white);
            padding: 2.5rem 2.5rem 3rem;
            border-radius: 16px;
            box-shadow: 0 2px 20px rgba(0, 0, 0, 0.06);
            border-top: 4px solid var(--primary);
        }

        .contacto-title {
            font-family: 'Fredoka', sans-serif;
            font-weight: 600;
            font-size: 2rem;
            color: var(--gray-900);
            text-align: center;
            margin-bottom: 0.5rem;
        }

        .contacto-descripcion {
            text-align: center;
            color: var(--gray-600);
            font-size: 1.05rem;
            line-height: 1.6;
            margin-bottom: 2rem;
        }

        .contacto-campo {
            margin-bottom: 1.25rem;
        }

        .contacto-campo label {
            display: block;
            font-weight: 600;
            color: var(--gray-700);
            margin-bottom: 0.4rem;
            font-size: 0.9rem;
        }

        .contacto-campo input,
        .contacto-campo textarea {
            width: 100%;
            padding: 0.7rem 1rem;
            border: 1px solid var(--gray-300);
            border-radius: 8px;
            font-size: 1rem;
            font-family: 'Work Sans', sans-serif;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
            background: var(--gray-100);
        }

        .contacto-campo input:focus,
        .contacto-campo textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(219, 26, 53, 0.12);
            background: var(--white);
        }

        .contacto-campo input::placeholder,
        .contacto-campo textarea::placeholder {
            color: var(--gray-400);
        }

        .contacto-btn {
            width: 100%;
            padding: 0.75rem;
            background: var(--primary);
            color: var(--white);
            border: none;
            border-radius: 8px;
            font-family: 'Fredoka', sans-serif;
            font-weight: 600;
            font-size: 1.05rem;
            cursor: pointer;
            transition: all 0.2s ease;
            margin-top: 0.5rem;
        }

        .contacto-btn:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(219, 26, 53, 0.3);
        }

        .contacto-btn:active {
            transform: scale(0.98);
        }

        .contacto-info-extra {
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--gray-200);
            text-align: center;
            font-size: 0.9rem;
            color: var(--gray-600);
        }

        .contacto-info-extra strong {
            color: var(--gray-900);
        }

        .contacto-info-extra .whatsapp-link {
            display: inline-block;
            margin-top: 0.8rem;
            color: var(--primary);
            font-weight: 600;
            text-decoration: none;
        }

        .contacto-info-extra .whatsapp-link:hover {
            text-decoration: underline;
        }

        /* MENSAJES */

        .mensaje-exito {
            background: #e8f7ed;
            color: #1f7a3f;
            border: 1px solid #b9e6c7;
            padding: 0.9rem 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            text-align: center;
            line-height: 1.5;
        }

        .mensaje-error {
            background: #fdeaea;
            color: #a61b1b;
            border: 1px solid #f3b5b5;
            padding: 0.9rem 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            text-align: center;
        }

        @media (max-width: 768px) {

            .ed-header {
                flex-direction: column;
                align-items: center;
                padding: 0.8rem 1rem;
                gap: 0.6rem;
            }

            .ed-nav {
                justify-content: center;
                gap: 1rem;
            }

            .ed-header__title {
                font-size: 1.3rem;
            }

            .usuario-menu {
                flex-wrap: wrap;
                justify-content: center;
            }

            .ed-footer .page-wrap {
                flex-direction: column;
                text-align: center;
            }

            .footer-logo {
                width: 70px;
                height: 70px;
            }

            .contacto-contenedor {
                padding: 1.5rem;
            }

            .contacto-title {
                font-size: 1.6rem;
            }
        }

        @media (max-width: 480px) {

            .contacto-contenedor {
                padding: 1.25rem;
            }

            .contacto-title {
                font-size: 1.4rem;
            }

            .usuario-menu {
                flex-direction: column;
                gap: 0.4rem;
            }
        }

    </style>

</head>

<body>

    <!-- HEADER -->

    <header class="ed-header">

        <a href="../index.php" class="ed-header__brand">

            <img
                src="../imagen/ChatGPT Image 5 nov 2025, 10_50_14 p.m..png"
                alt="Logo Estrella Dulce"
                class="ed-header__logo"
            >

            <h1 class="ed-header__title">
                Estrella Dulce
            </h1>

        </a>


        <nav class="ed-nav">

            <a href="postres.php">
                Postres
            </a>

            <a href="nosotros.html">
                Nosotros
            </a>

            <a href="contacto.php">
                Contacto
            </a>

            <a href="carrito.php" class="ed-cart" aria-label="Carrito">
                🛒
            </a>


            <?php if ($usuarioLogueado): ?>

                <div class="usuario-menu">

                    <span class="usuario-nombre">

                        👤

                        <?php
                        echo htmlspecialchars(
                            trim($nombreUsuario . " " . $apellidoUsuario),
                            ENT_QUOTES,
                            'UTF-8'
                        );
                        ?>

                    </span>


                    <a
                        href="../cerrar_sesion.php"
                        class="btn-cerrar-sesion"
                    >
                        Cerrar sesión
                    </a>

                </div>

            <?php else: ?>

                <a
                    href="iniciar%20sesion.php"
                    aria-label="Iniciar sesión"
                >
                    👤
                </a>

            <?php endif; ?>


            <a href="../index.php">
                🏠 Inicio
            </a>

        </nav>

    </header>


    <!-- CONTENIDO PRINCIPAL -->

    <main class="contacto-main">

        <section class="contacto-contenedor">

            <h1 class="contacto-title">
                ¡Hola! 💬
            </h1>


            <p class="contacto-descripcion">

                ¿Tienes alguna pregunta, sugerencia o problema con tu pedido?

                <br>

                Estamos felices de ayudarte 🍰✨

            </p>


            <?php if ($mensajeExito !== ""): ?>

                <div class="mensaje-exito">
                    <?php echo $mensajeExito; ?>
                </div>

            <?php endif; ?>


            <?php if ($mensajeError !== ""): ?>

                <div class="mensaje-error">
                    <?php echo htmlspecialchars(
                        $mensajeError,
                        ENT_QUOTES,
                        'UTF-8'
                    ); ?>
                </div>

            <?php endif; ?>


            <form
                class="contacto-form"
                method="POST"
                action=""
            >

                <div class="contacto-campo">

                    <label for="nombre">
                        Tu nombre:
                    </label>

                    <input
                        type="text"
                        id="nombre"
                        name="nombre"
                        placeholder="Ej. María López"
                        value="<?php echo htmlspecialchars(
                            $usuarioLogueado
                                ? trim($nombreUsuario . " " . $apellidoUsuario)
                                : ($_POST["nombre"] ?? ""),
                            ENT_QUOTES,
                            'UTF-8'
                        ); ?>"
                        required
                    >

                </div>


                <div class="contacto-campo">

                    <label for="correo">
                        Tu correo:
                    </label>

                    <input
                        type="email"
                        id="correo"
                        name="correo"
                        placeholder="Ej. maria@gmail.com"
                        value="<?php echo htmlspecialchars(
                            $usuarioLogueado
                                ? $correoUsuario
                                : ($_POST["correo"] ?? ""),
                            ENT_QUOTES,
                            'UTF-8'
                        ); ?>"
                        required
                    >

                </div>


                <div class="contacto-campo">

                    <label for="mensaje">
                        Mensaje:
                    </label>

                    <textarea
                        id="mensaje"
                        name="mensaje"
                        rows="5"
                        placeholder="Cuéntanos cómo podemos ayudarte..."
                        required
                    ><?php echo htmlspecialchars(
                        $_POST["mensaje"] ?? "",
                        ENT_QUOTES,
                        'UTF-8'
                    ); ?></textarea>

                </div>


                <button
                    type="submit"
                    class="contacto-btn"
                >
                    Enviar mensaje
                </button>

            </form>


            <div class="contacto-info-extra">

                <p>
                    <strong>📞 Atención telefónica:</strong>
                    +3146199121
                </p>

                <p>
                    <strong>📧 Email:</strong>
                    proyectpostres@gmail.com
                </p>

                <a
                    href="https://api.whatsapp.com/send?phone=573146199121&amp;text=Hola,%20vengo%20de%20la%20p%C3%A1gina%20web"
                    class="whatsapp-link"
                    target="_blank"
                    rel="noopener"
                >
                    💬 Escríbenos por WhatsApp
                </a>

            </div>

        </section>

    </main>


    <!-- FOOTER -->

    <footer class="ed-footer">

        <div class="page-wrap d-flex flex-column flex-md-row align-items-center justify-content-between">

            <img
                src="../imagen/I.U Pascual Bravo Escudo.png"
                alt="Logo Pascual"
                class="footer-logo"
            >


            <div class="text-center ed-footer-info my-3 my-md-0">

                <p>

                    <strong
                        style="color: var(--white); font-family: 'Fredoka', sans-serif;"
                    >
                        Estrella Dulce S.A.
                    </strong>

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
                    &copy; 2026 Estrella Dulce. Todos los derechos reservados.
                </p>

            </div>


            <ul class="list-unstyled text-center text-md-start mb-0">

                <li class="mb-2">

                    <span class="customer-service-title">
                        Atención al cliente
                    </span>

                    <br>

                    <p
                        class="mb-0"
                        style="color: rgba(255,255,255,0.85); font-size: 0.9rem;"
                    >
                        Tel: +3146199121 |
                        Email: proyectpostres@gmail.com
                    </p>

                </li>


                <li style="margin-top: 0.75rem;">

                    <a
                        href="https://api.whatsapp.com/send?phone=573146199121&amp;text=Hola,%20vengo%20de%20la%20p%C3%A1gina%20web"
                        class="btn-ed btn-ed--white"
                        target="_blank"
                        rel="noopener"
                    >
                        💬 Escríbenos por WhatsApp
                    </a>

                </li>

            </ul>


            <img
                src="../imagen/I.E F├®lix De Bedout Moreno Escudo.png"
                alt="Logo Félix"
                class="footer-logo"
            >

        </div>

    </footer>

</body>

</html>