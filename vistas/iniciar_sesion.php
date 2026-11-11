<?php
session_start();

require_once "../conexion.php";

$errorLogin = "";
$successLogin = "";
$errorRegistro = "";
$successRegistro = "";

/* =========================================================
   MOSTRAR MENSAJE DESPUÉS DEL REGISTRO
   ========================================================= */

if (isset($_GET["registro"]) && $_GET["registro"] === "exitoso") {
    $successLogin = "✅ ¡Cuenta creada correctamente! Ahora inicia sesión con tus datos.";
}


/* =========================================================
   PROCESAR FORMULARIOS PHP
   ========================================================= */

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $accion = $_POST["accion"] ?? "";


    /* =====================================================
       LOGIN
       ===================================================== */

    if ($accion === "login") {

        $email = trim($_POST["email"] ?? "");
        $password = $_POST["password"] ?? "";

        if ($email === "" || $password === "") {

            $errorLogin = "⚠️ Por favor completa ambos campos.";

        } else {

            try {

                $sql = "SELECT 
                            u.id_usuario,
                            u.id_rol,
                            u.nombre,
                            u.apellido,
                            u.correo,
                            u.password,
                            u.estado,
                            r.nombre AS rol
                        FROM usuarios u
                        INNER JOIN roles r 
                            ON u.id_rol = r.id_rol
                        WHERE u.correo = ?
                        LIMIT 1";

                $stmt = $conexion->prepare($sql);
                $stmt->execute([$email]);

                $usuario = $stmt->fetch(PDO::FETCH_ASSOC);


                /* =================================================
                   USUARIO NO EXISTE
                   ================================================= */

                if (!$usuario) {

                    $errorLogin = "❌ Usuario o contraseña incorrectos. Verifica tus datos.";


                /* =================================================
                   CUENTA INACTIVA
                   ================================================= */

                } elseif (strtoupper(trim($usuario["estado"])) !== "ACTIVO") {

                    $errorLogin = "❌ Esta cuenta se encuentra inactiva.";


                /* =================================================
                   VERIFICAR CONTRASEÑA SIN HASH
                   ================================================= */

                } elseif ($password !== $usuario["password"]) {

                    $errorLogin = "❌ Usuario o contraseña incorrectos. Verifica tus datos.";


                /* =================================================
                   LOGIN CORRECTO
                   ================================================= */

                } else {

                    /*
                     * Regenerar sesión por seguridad.
                     */
                    session_regenerate_id(true);


                    /*
                     * Guardar información del usuario.
                     */
                    $_SESSION["id_usuario"] = $usuario["id_usuario"];
                    $_SESSION["id_rol"] = $usuario["id_rol"];
                    $_SESSION["nombre"] = $usuario["nombre"];
                    $_SESSION["apellido"] = $usuario["apellido"];
                    $_SESSION["correo"] = $usuario["correo"];
                    $_SESSION["rol"] = strtoupper(trim($usuario["rol"]));


                    /* =================================================
                       ADMINISTRADOR
                       ================================================= */

                    if ($_SESSION["rol"] === "ADMINISTRADOR") {

                        header("Location: ../admin.php");
                        exit;
                    }


                    /* =================================================
                       EMPLEADO
                       ================================================= */

                    if ($_SESSION["rol"] === "EMPLEADO") {

                        header("Location: ../admin.php");
                        exit;
                    }


                    /* =================================================
                       CLIENTE
                       ================================================= */

                    /*
                     * Si el cliente llegó al login porque quería abrir
                     * el carrito, regresarlo allí después de autenticarse.
                     */
                    $destino = $_SESSION["redirigir_despues_login"] ?? "../index.php";
                    unset($_SESSION["redirigir_despues_login"]);

                    // Evitar redirecciones externas o rutas inseguras.
                    if (
                        !is_string($destino) ||
                        $destino === "" ||
                        strpos($destino, "//") === 0 ||
                        preg_match('/^[a-z][a-z0-9+.-]*:/i', $destino)
                    ) {
                        $destino = "../index.php";
                    }

                    header("Location: " . $destino);
                    exit;
                }

            } catch (PDOException $e) {

                $errorLogin = "❌ Ocurrió un error al consultar la base de datos.";

            }
        }
    }


    /* =========================================================
       REGISTRO
       ========================================================= */

    if ($accion === "registro") {

        $nombre = trim($_POST["nombre"] ?? "");
        $email = trim($_POST["email"] ?? "");
        $password = $_POST["password"] ?? "";
        $password2 = $_POST["password2"] ?? "";


        /* =====================================================
           VALIDACIONES
           ===================================================== */

        if (
            $nombre === "" ||
            $email === "" ||
            $password === "" ||
            $password2 === ""
        ) {

            $errorRegistro = "⚠️ Completa todos los campos.";

        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

            $errorRegistro = "⚠️ Ingresa un correo electrónico válido.";

        } elseif (strlen($password) < 6) {

            $errorRegistro = "⚠️ La contraseña debe tener al menos 6 caracteres.";

        } elseif ($password !== $password2) {

            $errorRegistro = "⚠️ Las contraseñas no coinciden.";

        } else {

            try {

                /* =================================================
                   VERIFICAR CORREO
                   ================================================= */

                $sql = "SELECT id_usuario
                        FROM usuarios
                        WHERE correo = ?
                        LIMIT 1";

                $stmt = $conexion->prepare($sql);
                $stmt->execute([$email]);

                $usuarioExiste = $stmt->fetch(PDO::FETCH_ASSOC);


                if ($usuarioExiste) {

                    $errorRegistro = "⚠️ Este correo ya está registrado. Inicia sesión.";

                } else {

                    /*
                     * IMPORTANTE:
                     *
                     * La contraseña se guarda directamente,
                     * SIN password_hash().
                     */

                    $sql = "INSERT INTO usuarios
                            (
                                id_rol,
                                nombre,
                                apellido,
                                correo,
                                password,
                                estado
                            )
                            VALUES
                            (
                                2,
                                ?,
                                '',
                                ?,
                                ?,
                                'ACTIVO'
                            )";

                    $stmt = $conexion->prepare($sql);

                    $stmt->execute([
                        $nombre,
                        $email,
                        $password
                    ]);


                    /*
                     * Regresar al login.
                     */

                    header("Location: iniciar%20sesion.php?registro=exitoso");
                    exit;
                }

            } catch (PDOException $e) {

                $errorRegistro = "❌ No fue posible crear la cuenta.";
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

    <title>Iniciar Sesión - Estrella Dulce</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">

    <link href="https://fonts.googleapis.com/css2?family=Fredoka:wght@400;500;600;700&family=Work+Sans:wght@400;500;600&display=swap" rel="stylesheet">

    <style>

        :root {
            --primary: #da5466;
            --primary-dark: #b8152c;
            --white: #ffffff;
            --gray-100: #f8f9fa;
            --gray-200: #e9ecef;
            --gray-300: #dee2e6;
            --gray-400: #ced4da;
            --gray-600: #6c757d;
            --gray-700: #495057;
            --gray-800: #343a40;
            --gray-900: #212529;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: linear-gradient(
                135deg,
                #f8f9fa 0%,
                #e9ecef 100%
            );

            font-family:
                'Work Sans',
                -apple-system,
                BlinkMacSystemFont,
                'Segoe UI',
                Roboto,
                sans-serif;

            color: var(--gray-900);

            min-height: 100vh;

            display: flex;
            align-items: center;
            justify-content: center;

            padding: 1.5rem;
        }

        .login-header {
            position: fixed;

            top: 0;
            left: 0;
            right: 0;

            background: var(--primary);

            padding: 0.6rem 2rem;

            display: flex;
            align-items: center;

            gap: 1rem;

            box-shadow:
                0 2px 8px rgba(219, 26, 53, 0.3);

            z-index: 100;
        }

        .login-header__logo {
            width: 40px;
            height: 40px;

            object-fit: contain;

            border-radius: 50%;

            background: var(--white);

            padding: 3px;

            border: 2px solid var(--white);
        }

        .login-header__title {
            font-family: 'Fredoka', sans-serif;

            font-size: 1.3rem;

            font-weight: 600;

            color: var(--white);

            letter-spacing: -0.5px;

            margin: 0;
        }

        .login-header a {
            color: var(--white);

            text-decoration: none;

            margin-left: auto;

            font-weight: 500;

            font-size: 0.9rem;

            padding: 0.3rem 0.8rem;

            border-radius: 6px;

            transition:
                background 0.2s ease;
        }

        .login-header a:hover {
            background:
                rgba(255, 255, 255, 0.15);
        }

        .login-container {
            max-width: 440px;

            width: 100%;

            margin-top: 80px;
        }

        .login-card {
            background: var(--white);

            border-radius: 16px;

            padding: 2.5rem 2rem;

            box-shadow:
                0 10px 40px rgba(0, 0, 0, 0.08);

            border-top:
                4px solid var(--primary);
        }

        .login-card .logo-area {
            text-align: center;

            margin-bottom: 1.8rem;
        }

        .login-card .logo-icon {
            font-size: 2.8rem;

            display: block;

            margin-bottom: 0.3rem;
        }

        .login-card h2 {
            font-family: 'Fredoka', sans-serif;

            font-weight: 600;

            font-size: 1.6rem;

            color: var(--gray-900);

            margin: 0;
        }

        .login-card .subtitulo {
            color: var(--gray-600);

            font-size: 0.9rem;

            margin-top: 0.2rem;
        }

        .social-login {
            display: flex;

            flex-direction: column;

            gap: 0.8rem;

            margin-bottom: 1.5rem;
        }

        .btn-social {
            display: flex;

            align-items: center;

            justify-content: center;

            gap: 0.8rem;

            padding: 0.7rem;

            border:
                2px solid var(--gray-200);

            border-radius: 10px;

            background: var(--white);

            font-family:
                'Work Sans', sans-serif;

            font-weight: 500;

            font-size: 1rem;

            cursor: pointer;

            transition: all 0.2s ease;

            color: var(--gray-700);

            text-decoration: none;

            width: 100%;
        }

        .btn-social:hover {
            transform: translateY(-2px);

            box-shadow:
                0 4px 12px rgba(0, 0, 0, 0.08);
        }

        .btn-social .icono {
            font-size: 1.6rem;

            display: flex;

            align-items: center;

            justify-content: center;

            width: 32px;
            height: 32px;

            border-radius: 50%;

            flex-shrink: 0;

            overflow: hidden;
        }

        .btn-social--google .icono {
            background: #fff;

            border:
                1px solid var(--gray-200);
        }

        .btn-social--google:hover {
            border-color: #DB4437;

            background: #fef0ee;

            color: #DB4437;
        }

        .btn-social--instagram .icono {
            background:
                linear-gradient(
                    45deg,
                    #f09433,
                    #e6683c,
                    #dc2743,
                    #cc2366,
                    #bc1888
                );

            color: white;

            font-size: 1.2rem;
        }

        .btn-social--instagram:hover {
            border-color: #E4405F;

            background: #fdeef0;

            color: #E4405F;
        }

        .btn-social--facebook .icono {
            background: #1877F2;

            color: white;

            font-size: 1.2rem;
        }

        .btn-social--facebook:hover {
            border-color: #1877F2;

            background: #eef3fe;

            color: #1877F2;
        }

        .btn-social--email .icono {
            background: var(--primary);

            color: white;

            font-size: 1.2rem;
        }

        .btn-social--email:hover {
            border-color: var(--primary);

            background: #fdeef0;

            color: var(--primary);
        }

        .separador-o {
            display: flex;

            align-items: center;

            gap: 1rem;

            margin: 1rem 0;

            color: var(--gray-400);

            font-size: 0.85rem;
        }

        .separador-o::before,
        .separador-o::after {
            content: '';

            flex: 1;

            height: 1px;

            background: var(--gray-200);
        }

        .login-form-container {
            animation:
                fadeIn 0.4s ease;
        }

        @keyframes fadeIn {

            0% {
                opacity: 0;
                transform: translateY(10px);
            }

            100% {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .input-group {
            margin-bottom: 1rem;
        }

        .input-group label {
            display: block;

            font-weight: 500;

            font-size: 0.9rem;

            color: var(--gray-700);

            margin-bottom: 0.3rem;
        }

        .input-group input {
            width: 100%;

            padding: 0.7rem 1rem;

            border:
                1px solid var(--gray-300);

            border-radius: 8px;

            font-size: 1rem;

            font-family:
                'Work Sans', sans-serif;

            transition:
                border-color 0.2s ease,
                box-shadow 0.2s ease;

            background: var(--gray-100);
        }

        .input-group input:focus {
            outline: none;

            border-color:
                var(--primary);

            box-shadow:
                0 0 0 3px rgba(219, 26, 53, 0.15);

            background:
                var(--white);
        }

        .input-group input::placeholder {
            color: var(--gray-400);
        }

        .btn-submit {
            width: 100%;

            padding: 0.75rem;

            background:
                var(--primary);

            color:
                var(--white);

            border: none;

            border-radius: 8px;

            font-family:
                'Fredoka', sans-serif;

            font-weight: 600;

            font-size: 1.05rem;

            cursor: pointer;

            transition: all 0.2s ease;

            margin-top: 0.5rem;
        }

        .btn-submit:hover {
            background:
                var(--primary-dark);

            transform:
                translateY(-1px);

            box-shadow:
                0 4px 12px rgba(219, 26, 53, 0.3);
        }

        .btn-submit:active {
            transform: scale(0.98);
        }

        .error-msg {
            color: #dc3545;

            font-size: 0.85rem;

            text-align: center;

            margin-top: 0.5rem;

            min-height: 1.5rem;
        }

        .success-msg {
            color: #198754;

            font-size: 0.85rem;

            text-align: center;

            margin-top: 0.5rem;

            min-height: 1.5rem;
        }

        .register-link {
            text-align: center;

            margin-top: 1rem;

            font-size: 0.9rem;

            color: var(--gray-600);
        }

        .register-link a {
            color: var(--primary);

            font-weight: 600;

            text-decoration: none;

            transition: color 0.2s;
        }

        .register-link a:hover {
            color:
                var(--primary-dark);

            text-decoration: underline;
        }

        .volver-link {
            display: block;

            text-align: center;

            color: var(--gray-600);

            font-size: 0.85rem;

            margin-top: 0.8rem;

            cursor: pointer;

            text-decoration: none;

            transition: color 0.2s;
        }

        .volver-link:hover {
            color:
                var(--primary);
        }

        @media (max-width: 480px) {

            .login-card {
                padding: 1.8rem 1.2rem;
            }

            .login-header {
                padding: 0.5rem 1rem;
            }

            .login-header__title {
                font-size: 1rem;
            }

            .btn-social {
                font-size: 0.9rem;

                padding: 0.6rem;
            }
        }

    </style>
</head>

<body>

    <!-- HEADER PEQUEÑO -->

    <header class="login-header">

        <img
            src="../imagen/ChatGPT Image 5 nov 2025, 10_50_14 p.m..png"
            alt="Logo"
            class="login-header__logo"
        >

        <span class="login-header__title">
            Estrella Dulce
        </span>

        <a href="../index.php">
            🏠 Tienda
        </a>

    </header>


    <!-- CONTENIDO -->

    <div class="login-container">

        <div class="login-card">

            <div class="logo-area">

                <span class="logo-icon">
                    ✨
                </span>

                <h2>
                    Bienvenido/a
                </h2>

                <p class="subtitulo">
                    Inicia sesión o crea tu cuenta
                </p>

            </div>


            <!-- BOTONES SOCIALES -->

            <div class="social-login" id="socialButtons">

                <button
                    class="btn-social btn-social--google"
                    onclick="mostrarLogin('Google')"
                >

                    <span class="icono">

                        <svg
                            viewBox="0 0 48 48"
                            width="20"
                            height="20"
                            xmlns="http://www.w3.org/2000/svg"
                            aria-hidden="true"
                        >

                            <path
                                fill="#FFC107"
                                d="M43.6 20.5H42V20H24v8h11.3C33.8 32.9 29.3 36 24 36c-6.6 0-12-5.4-12-12s5.4-12 12-12c3.1 0 5.8 1.1 8 3l6-6C34.4 5.1 29.5 3 24 3 12.4 3 3 12.4 3 24s9.4 21 21 21 21-9.4 21-21c0-1.4-.1-2.4-.4-3.5z"
                            />

                            <path
                                fill="#FF3D00"
                                d="M6.3 14.7l6.6 4.8C14.5 15.9 18.9 13 24 13c3.1 0 5.8 1.1 8 3l6-6C34.4 5.1 29.5 3 24 3 16.3 3 9.6 7.3 6.3 14.7z"
                            />

                            <path
                                fill="#4CAF50"
                                d="M24 45c5.4 0 10.3-2.1 14-5.5l-6.5-5.4C29.4 35.9 26.8 37 24 37c-5.3 0-9.8-3.4-11.4-8.1l-6.5 5C9.4 40.6 16.1 45 24 45z"
                            />

                            <path
                                fill="#1976D2"
                                d="M43.6 20.5H42V20H24v8h11.3c-1 2.9-5.3 6.9-5.3 6.9l6.5 5.4C39.9 37.4 43 31.4 43 24c0-1.4-.1-2.4-.4-3.5z"
                            />

                        </svg>

                    </span>

                    Continuar con Google

                </button>


                <button
                    class="btn-social btn-social--instagram"
                    onclick="mostrarLogin('Instagram')"
                >

                    <span class="icono">

                        <svg
                            viewBox="0 0 24 24"
                            width="18"
                            height="18"
                            fill="none"
                            xmlns="http://www.w3.org/2000/svg"
                            aria-hidden="true"
                        >

                            <rect
                                x="2"
                                y="2"
                                width="20"
                                height="20"
                                rx="6"
                                stroke="white"
                                stroke-width="2"
                            />

                            <circle
                                cx="12"
                                cy="12"
                                r="4.5"
                                stroke="white"
                                stroke-width="2"
                            />

                            <circle
                                cx="17.5"
                                cy="6.5"
                                r="1.2"
                                fill="white"
                            />

                        </svg>

                    </span>

                    Continuar con Instagram

                </button>


                <button
                    class="btn-social btn-social--facebook"
                    onclick="mostrarLogin('Facebook')"
                >

                    <span class="icono">

                        <svg
                            viewBox="0 0 24 24"
                            width="20"
                            height="20"
                            fill="white"
                            xmlns="http://www.w3.org/2000/svg"
                            aria-hidden="true"
                        >

                            <path
                                d="M22 12c0-5.52-4.48-10-10-10S2 6.48 2 12c0 4.99 3.66 9.13 8.44 9.88v-6.99H7.9V12h2.54V9.8c0-2.5 1.49-3.89 3.77-3.89 1.09 0 2.23.2 2.23.2v2.45h-1.26c-1.24 0-1.63.77-1.63 1.56V12h2.78l-.45 2.89h-2.33v6.99C18.34 21.13 22 16.99 22 12z"
                            />

                        </svg>

                    </span>

                    Continuar con Facebook

                </button>


                <button
                    class="btn-social btn-social--email"
                    onclick="mostrarLogin('Correo')"
                >

                    <span class="icono">
                        ✉
                    </span>

                    Continuar con Correo Electrónico

                </button>

            </div>


            <div class="separador-o">

                <span>
                    o
                </span>

            </div>


            <!-- FORMULARIO DE LOGIN -->

            <div
                class="login-form-container"
                id="loginFormContainer"
                style="display:none;"
            >

                <form
                    id="loginForm"
                    method="POST"
                    action=""
                >

                    <input
                        type="hidden"
                        name="accion"
                        value="login"
                    >


                    <div class="input-group">

                        <label for="email">
                            Correo Electrónico
                        </label>

                        <input
                            type="email"
                            id="email"
                            name="email"
                            placeholder="tu@email.com"
                            required
                        >

                    </div>


                    <div class="input-group">

                        <label for="password">
                            Contraseña
                        </label>

                        <input
                            type="password"
                            id="password"
                            name="password"
                            placeholder="••••••••"
                            required
                        >

                    </div>


                    <?php if ($errorLogin !== ""): ?>

                        <p
                            id="errorMessage"
                            class="error-msg"
                        >
                            <?php echo htmlspecialchars($errorLogin); ?>
                        </p>

                    <?php elseif ($successLogin !== ""): ?>

                        <p
                            id="errorMessage"
                            class="success-msg"
                        >
                            <?php echo htmlspecialchars($successLogin); ?>
                        </p>

                    <?php else: ?>

                        <p
                            id="errorMessage"
                            class="error-msg"
                        ></p>

                    <?php endif; ?>


                    <button
                        type="submit"
                        class="btn-submit"
                        id="btnLogin"
                    >
                        Iniciar Sesión
                    </button>

                </form>


                <div class="register-link">

                    ¿No tienes cuenta?

                    <a
                        href="#"
                        id="showRegister"
                    >
                        Regístrate aquí
                    </a>

                </div>


                <a
                    class="volver-link"
                    onclick="volverARedes()"
                >
                    ← Volver a opciones de inicio
                </a>

            </div>


            <!-- FORMULARIO DE REGISTRO -->

            <div
                class="login-form-container"
                id="registerFormContainer"
                style="display:none;"
            >

                <form
                    id="registerForm"
                    method="POST"
                    action=""
                >

                    <input
                        type="hidden"
                        name="accion"
                        value="registro"
                    >


                    <div class="input-group">

                        <label for="regNombre">
                            Nombre completo
                        </label>

                        <input
                            type="text"
                            id="regNombre"
                            name="nombre"
                            placeholder="Tu nombre"
                            required
                        >

                    </div>


                    <div class="input-group">

                        <label for="regEmail">
                            Correo Electrónico
                        </label>

                        <input
                            type="email"
                            id="regEmail"
                            name="email"
                            placeholder="tu@email.com"
                            required
                        >

                    </div>


                    <div class="input-group">

                        <label for="regPassword">
                            Contraseña
                        </label>

                        <input
                            type="password"
                            id="regPassword"
                            name="password"
                            placeholder="Mínimo 6 caracteres"
                            required
                            minlength="6"
                        >

                    </div>


                    <div class="input-group">

                        <label for="regPassword2">
                            Confirmar Contraseña
                        </label>

                        <input
                            type="password"
                            id="regPassword2"
                            name="password2"
                            placeholder="Repite tu contraseña"
                            required
                        >

                    </div>


                    <?php if ($errorRegistro !== ""): ?>

                        <p
                            id="registerError"
                            class="error-msg"
                        >
                            <?php echo htmlspecialchars($errorRegistro); ?>
                        </p>

                    <?php else: ?>

                        <p
                            id="registerError"
                            class="error-msg"
                        ></p>

                    <?php endif; ?>


                    <button
                        type="submit"
                        class="btn-submit"
                    >
                        Crear Cuenta
                    </button>

                </form>


                <div class="register-link">

                    ¿Ya tienes cuenta?

                    <a
                        href="#"
                        id="showLogin"
                    >
                        Inicia sesión aquí
                    </a>

                </div>


                <a
                    class="volver-link"
                    onclick="volverARedes()"
                >
                    ← Volver a opciones de inicio
                </a>

            </div>

        </div>

    </div>


    <script>

        // ============================================
        // MOSTRAR FORMULARIO DE LOGIN
        // ============================================

        function mostrarLogin(providerName) {

            document.getElementById(
                'socialButtons'
            ).style.display = 'none';

            document.querySelector(
                '.separador-o'
            ).style.display = 'none';

            document.getElementById(
                'loginFormContainer'
            ).style.display = 'block';

            document.getElementById(
                'registerFormContainer'
            ).style.display = 'none';

            document.getElementById(
                'errorMessage'
            ).textContent = '';

            document.getElementById(
                'registerError'
            ).textContent = '';

            setTimeout(() => {

                document.getElementById(
                    'email'
                ).focus();

            }, 300);
        }


        // ============================================
        // VOLVER A OPCIONES
        // ============================================

        function volverARedes() {

            document.getElementById(
                'socialButtons'
            ).style.display = 'flex';

            document.querySelector(
                '.separador-o'
            ).style.display = 'flex';

            document.getElementById(
                'loginFormContainer'
            ).style.display = 'none';

            document.getElementById(
                'registerFormContainer'
            ).style.display = 'none';

            document.getElementById(
                'errorMessage'
            ).textContent = '';

            document.getElementById(
                'registerError'
            ).textContent = '';
        }


        // ============================================
        // MOSTRAR REGISTRO
        // ============================================

        document.getElementById(
            'showRegister'
        ).addEventListener(
            'click',
            function(e) {

                e.preventDefault();

                document.getElementById(
                    'loginFormContainer'
                ).style.display = 'none';

                document.getElementById(
                    'registerFormContainer'
                ).style.display = 'block';

                document.getElementById(
                    'socialButtons'
                ).style.display = 'none';

                document.querySelector(
                    '.separador-o'
                ).style.display = 'none';

                document.getElementById(
                    'registerError'
                ).textContent = '';

            }
        );


        // ============================================
        // MOSTRAR LOGIN
        // ============================================

        document.getElementById(
            'showLogin'
        ).addEventListener(
            'click',
            function(e) {

                e.preventDefault();

                document.getElementById(
                    'loginFormContainer'
                ).style.display = 'block';

                document.getElementById(
                    'registerFormContainer'
                ).style.display = 'none';

                document.getElementById(
                    'socialButtons'
                ).style.display = 'none';

                document.querySelector(
                    '.separador-o'
                ).style.display = 'none';

                document.getElementById(
                    'errorMessage'
                ).textContent = '';

            }
        );


        // ============================================
        // MOSTRAR LOGIN AUTOMÁTICAMENTE
        // ============================================

        <?php if (
            $errorLogin !== "" ||
            $successLogin !== ""
        ): ?>

            document.getElementById(
                'socialButtons'
            ).style.display = 'none';

            document.querySelector(
                '.separador-o'
            ).style.display = 'none';

            document.getElementById(
                'loginFormContainer'
            ).style.display = 'block';

            document.getElementById(
                'registerFormContainer'
            ).style.display = 'none';

        <?php endif; ?>


        // ============================================
        // MOSTRAR REGISTRO SI HUBO ERROR
        // ============================================

        <?php if ($errorRegistro !== ""): ?>

            document.getElementById(
                'socialButtons'
            ).style.display = 'none';

            document.querySelector(
                '.separador-o'
            ).style.display = 'none';

            document.getElementById(
                'loginFormContainer'
            ).style.display = 'none';

            document.getElementById(
                'registerFormContainer'
            ).style.display = 'block';

        <?php endif; ?>

    </script>

</body>
</html>