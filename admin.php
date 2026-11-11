<?php
/**
 * API de administración - Estrella Dulce
 * Debe estar en la MISMA carpeta que admin.php y conexion.php.
 */

session_start();

$esPeticionAjax = isset($_GET['accion']) && $_GET['accion'] !== '';
$esAdmin = (
    (isset($_SESSION['id_usuario']) && isset($_SESSION['rol']) && strtoupper((string)$_SESSION['rol']) === 'ADMINISTRADOR')
    || filter_var($_SESSION['isAdminLoggedIn'] ?? false, FILTER_VALIDATE_BOOLEAN)
);

if (!$esAdmin) {
    if ($esPeticionAjax) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'Tu sesión no está autorizada. Inicia sesión como administrador.'], JSON_UNESCAPED_UNICODE);
    } else {
        header('Location: vistas/iniciar%20sesi%C3%B3n.php');
    }
    exit;
}

function responder(array $datos, int $codigo = 200): void {
    http_response_code($codigo);
    echo json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function entradaJson(): array {
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') return [];
    $data = json_decode($raw, true);
    if (!is_array($data)) responder(['ok' => false, 'error' => 'El contenido enviado no es JSON válido.'], 400);
    return $data;
}

function categoriaId(PDO $conexion, string $nombre): int {
    $nombre = trim($nombre);
    if ($nombre === '') $nombre = 'Otros';

    $stmt = $conexion->prepare('SELECT id_categoria FROM categorias WHERE nombre = ? LIMIT 1');
    $stmt->execute([$nombre]);
    $id = $stmt->fetchColumn();
    if ($id !== false) return (int)$id;

    // La categoría del formulario es una etiqueta. Si aún no existe en la BD, se crea.
    $stmt = $conexion->prepare("INSERT INTO categorias (nombre, descripcion, estado) VALUES (?, ?, 'ACTIVA')");
    $stmt->execute([$nombre, 'Categoría creada desde el panel de administración.']);
    return (int)$conexion->lastInsertId();
}

if ($esPeticionAjax) {
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
try {
    require_once __DIR__ . '/conexion.php';
    if (!isset($conexion) || !($conexion instanceof PDO)) {
        throw new RuntimeException('No se encontró una conexión PDO válida en conexion.php.');
    }

    $accion = $_GET['accion'] ?? '';
    $metodo = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $datos = $metodo === 'POST' ? entradaJson() : [];

    switch ($accion) {
        case 'debug': {
            $postres = (int)$conexion->query('SELECT COUNT(*) FROM postres')->fetchColumn();
            $pedidos = (int)$conexion->query('SELECT COUNT(*) FROM pedidos')->fetchColumn();
            $dir = __DIR__;
            responder([
                'ok' => true,
                'php_version' => PHP_VERSION,
                'data_dir' => $dir,
                'data_dir_existe' => is_dir($dir),
                'data_dir_escribe' => is_writable($dir),
                'postres_count' => $postres,
                'pedidos_count' => $pedidos
            ]);
        }

        case 'listar_postres': {
            $sql = "SELECT p.id_postre AS id, p.nombre, p.precio, p.descripcion,
                           p.stock, p.imagen, c.nombre AS categoria
                    FROM postres p
                    LEFT JOIN categorias c ON c.id_categoria = p.id_categoria
                    ORDER BY p.id_postre DESC";
            $postres = $conexion->query($sql)->fetchAll(PDO::FETCH_ASSOC);
            foreach ($postres as &$p) {
                $p['id'] = (string)$p['id'];
                $p['precio'] = (float)$p['precio'];
                $p['stock'] = (int)$p['stock'];
                $p['imagen'] = (string)($p['imagen'] ?? '');
                $p['descripcion'] = (string)($p['descripcion'] ?? '');
                $p['categoria'] = (string)($p['categoria'] ?? 'Otros');
            }
            unset($p);
            responder(['ok' => true, 'postres' => $postres]);
        }

        case 'guardar_postre': {
            if ($metodo !== 'POST') responder(['ok' => false, 'error' => 'Método no permitido.'], 405);

            $id = (int)($datos['id'] ?? 0);
            $nombre = trim((string)($datos['nombre'] ?? ''));
            $precioRaw = $datos['precio'] ?? null;
            $categoria = trim((string)($datos['categoria'] ?? 'Otros'));
            $imagen = trim((string)($datos['imagen'] ?? ''));
            $descripcion = trim((string)($datos['descripcion'] ?? ''));
            $stock = filter_var($datos['stock'] ?? 0, FILTER_VALIDATE_INT);

            if ($nombre === '') responder(['ok' => false, 'error' => 'Escribe el nombre del postre.'], 422);
            if (!is_numeric($precioRaw) || (float)$precioRaw < 0) responder(['ok' => false, 'error' => 'El precio debe ser un número igual o mayor que cero.'], 422);
            if ($imagen === '') responder(['ok' => false, 'error' => 'Escribe la ruta o el nombre de la imagen.'], 422);
            if ($descripcion === '') responder(['ok' => false, 'error' => 'Escribe la descripción del postre.'], 422);
            if ($stock === false || $stock < 0) responder(['ok' => false, 'error' => 'El stock debe ser un número entero igual o mayor que cero.'], 422);

            $idCategoria = categoriaId($conexion, $categoria);
            $precio = (float)$precioRaw;
            $estado = $stock > 0 ? 'DISPONIBLE' : 'AGOTADO';

            if ($id > 0) {
                $stmt = $conexion->prepare(
                    'UPDATE postres
                     SET id_categoria = ?, nombre = ?, descripcion = ?, precio = ?, stock = ?, imagen = ?, estado = ?
                     WHERE id_postre = ?'
                );
                $stmt->execute([$idCategoria, $nombre, $descripcion, $precio, $stock, $imagen, $estado, $id]);
                if ($stmt->rowCount() === 0) {
                    $check = $conexion->prepare('SELECT id_postre FROM postres WHERE id_postre = ?');
                    $check->execute([$id]);
                    if (!$check->fetchColumn()) responder(['ok' => false, 'error' => 'No se encontró el postre que intentas editar.'], 404);
                }
                responder(['ok' => true, 'mensaje' => 'Postre actualizado correctamente.']);
            }

            $stmt = $conexion->prepare(
                "INSERT INTO postres (id_categoria, nombre, descripcion, precio, stock, imagen, ingredientes, estado, destacado)
                 VALUES (?, ?, ?, ?, ?, ?, '', ?, 0)"
            );
            $stmt->execute([$idCategoria, $nombre, $descripcion, $precio, $stock, $imagen, $estado]);
            responder(['ok' => true, 'id' => (int)$conexion->lastInsertId(), 'mensaje' => 'Postre guardado correctamente.']);
        }

        case 'eliminar_postre': {
            if ($metodo !== 'POST') responder(['ok' => false, 'error' => 'Método no permitido.'], 405);
            $id = (int)($datos['id'] ?? 0);
            if ($id <= 0) responder(['ok' => false, 'error' => 'Identificador de postre inválido.'], 422);
            $stmt = $conexion->prepare('DELETE FROM postres WHERE id_postre = ?');
            $stmt->execute([$id]);
            if ($stmt->rowCount() === 0) responder(['ok' => false, 'error' => 'El postre no existe o ya fue eliminado.'], 404);
            responder(['ok' => true, 'mensaje' => 'Postre eliminado correctamente.']);
        }

        case 'ajustar_stock': {
            if ($metodo !== 'POST') responder(['ok' => false, 'error' => 'Método no permitido.'], 405);
            $id = (int)($datos['id'] ?? 0);
            if ($id <= 0) responder(['ok' => false, 'error' => 'Identificador de postre inválido.'], 422);
            $conexion->beginTransaction();
            $stmt = $conexion->prepare('SELECT stock FROM postres WHERE id_postre = ? FOR UPDATE');
            $stmt->execute([$id]);
            $actual = $stmt->fetchColumn();
            if ($actual === false) {
                $conexion->rollBack();
                responder(['ok' => false, 'error' => 'No se encontró el postre.'], 404);
            }
            if (array_key_exists('stock', $datos)) {
                $nuevo = filter_var($datos['stock'], FILTER_VALIDATE_INT);
                if ($nuevo === false || $nuevo < 0) {
                    $conexion->rollBack();
                    responder(['ok' => false, 'error' => 'El stock debe ser un entero igual o mayor que cero.'], 422);
                }
            } else {
                $delta = (int)($datos['delta'] ?? 0);
                $nuevo = max(0, (int)$actual + $delta);
            }
            $estado = $nuevo > 0 ? 'DISPONIBLE' : 'AGOTADO';
            $upd = $conexion->prepare('UPDATE postres SET stock = ?, estado = ? WHERE id_postre = ?');
            $upd->execute([$nuevo, $estado, $id]);
            $conexion->commit();
            responder(['ok' => true, 'stock' => $nuevo]);
        }

        case 'listar_pedidos': {
            $sql = "SELECT pe.id_pedido, pe.total, pe.estado, pe.fecha_pedido,
                           pe.observaciones, CONCAT(u.nombre, ' ', COALESCE(u.apellido, '')) AS cliente
                    FROM pedidos pe
                    INNER JOIN usuarios u ON u.id_usuario = pe.id_usuario
                    ORDER BY pe.id_pedido ASC";
            $rows = $conexion->query($sql)->fetchAll(PDO::FETCH_ASSOC);
            $det = $conexion->prepare(
                'SELECT d.id_postre, d.cantidad, d.precio_unitario, d.subtotal, p.nombre
                 FROM detalle_pedidos d INNER JOIN postres p ON p.id_postre = d.id_postre
                 WHERE d.id_pedido = ?'
            );
            $pedidos = [];
            foreach ($rows as $row) {
                $det->execute([(int)$row['id_pedido']]);
                $items = [];
                foreach ($det->fetchAll(PDO::FETCH_ASSOC) as $it) {
                    $items[] = [
                        'id' => (string)$it['id_postre'],
                        'nombre' => $it['nombre'],
                        'cantidad' => (int)$it['cantidad'],
                        'precio' => (float)$it['precio_unitario'],
                        'subtotal' => (float)$it['subtotal']
                    ];
                }
                $observaciones = (string)($row['observaciones'] ?? '');
                $cajero = 'No especificado';
                if (preg_match('/Cajero:\s*(.+)$/i', $observaciones, $m)) $cajero = trim($m[1]);
                $pedidos[] = [
                    'id' => (string)$row['id_pedido'],
                    'numero' => 'FE-' . str_pad((string)$row['id_pedido'], 6, '0', STR_PAD_LEFT),
                    'fecha' => $row['fecha_pedido'],
                    'cliente' => trim($row['cliente']),
                    'cajero' => $cajero,
                    'total' => (float)$row['total'],
                    'items' => $items
                ];
            }
            responder(['ok' => true, 'pedidos' => $pedidos]);
        }

        case 'crear_pedido': {
            if ($metodo !== 'POST') responder(['ok' => false, 'error' => 'Método no permitido.'], 405);
            $cliente = trim((string)($datos['cliente'] ?? ''));
            $cajero = trim((string)($datos['cajero'] ?? ''));
            $items = $datos['items'] ?? [];
            if ($cliente === '' || $cajero === '' || !is_array($items) || count($items) === 0) {
                responder(['ok' => false, 'error' => 'Indica el cliente, el cajero y al menos un producto.'], 422);
            }
            // La tabla pedidos del proyecto relaciona cada pedido con un usuario registrado.
            $stmtUser = $conexion->prepare(
                "SELECT id_usuario, telefono FROM usuarios
                 WHERE estado = 'ACTIVO'
                   AND TRIM(CONCAT(nombre, ' ', COALESCE(apellido, ''))) = ?
                 LIMIT 1"
            );
            $stmtUser->execute([$cliente]);
            $usuario = $stmtUser->fetch(PDO::FETCH_ASSOC);
            if (!$usuario) responder(['ok' => false, 'error' => 'No existe un cliente activo con ese nombre en la tabla usuarios. Registra el cliente primero o escribe su nombre completo tal como aparece.'], 422);

            try {
                $conexion->beginTransaction();
                $total = 0.0;
                $validos = [];
                $buscar = $conexion->prepare('SELECT id_postre, nombre, precio, stock, estado FROM postres WHERE id_postre = ? FOR UPDATE');
                foreach ($items as $item) {
                    $idPostre = (int)($item['postreId'] ?? $item['id'] ?? 0);
                    $cantidad = (int)($item['cantidad'] ?? 0);
                    if ($idPostre <= 0 || $cantidad <= 0) continue;
                    $buscar->execute([$idPostre]);
                    $p = $buscar->fetch(PDO::FETCH_ASSOC);
                    if (!$p) throw new RuntimeException('Uno de los postres seleccionados no existe.');
                    if ($p['estado'] !== 'DISPONIBLE' || (int)$p['stock'] < $cantidad) {
                        throw new RuntimeException('Stock insuficiente o producto no disponible: ' . $p['nombre']);
                    }
                    $precio = (float)$p['precio'];
                    $subtotal = round($precio * $cantidad, 2);
                    $total += $subtotal;
                    $validos[] = ['id' => $idPostre, 'cantidad' => $cantidad, 'precio' => $precio, 'subtotal' => $subtotal, 'nombre' => $p['nombre']];
                }
                if (!$validos) throw new RuntimeException('Agrega al menos un producto válido al pedido.');

                $insPedido = $conexion->prepare(
                    "INSERT INTO pedidos (id_usuario, total, estado, direccion_entrega, telefono_contacto, observaciones, fecha_pedido)
                     VALUES (?, ?, 'ENTREGADO', 'Compra en caja', ?, ?, NOW())"
                );
                $insPedido->execute([(int)$usuario['id_usuario'], $total, (string)($usuario['telefono'] ?? ''), 'Pedido tomado en caja. Cajero: ' . $cajero]);
                $idPedido = (int)$conexion->lastInsertId();
                $insDetalle = $conexion->prepare('INSERT INTO detalle_pedidos (id_pedido, id_postre, cantidad, precio_unitario, subtotal) VALUES (?, ?, ?, ?, ?)');
                $updStock = $conexion->prepare("UPDATE postres SET stock = stock - ?, estado = CASE WHEN stock - ? <= 0 THEN 'AGOTADO' ELSE 'DISPONIBLE' END WHERE id_postre = ? AND stock >= ?");
                foreach ($validos as $it) {
                    $insDetalle->execute([$idPedido, $it['id'], $it['cantidad'], $it['precio'], $it['subtotal']]);
                    $updStock->execute([$it['cantidad'], $it['cantidad'], $it['id'], $it['cantidad']]);
                    if ($updStock->rowCount() !== 1) throw new RuntimeException('No se pudo descontar el inventario de ' . $it['nombre']);
                }
                $conexion->commit();
                responder(['ok' => true, 'pedido' => [
                    'id' => (string)$idPedido,
                    'numero' => 'FE-' . str_pad((string)$idPedido, 6, '0', STR_PAD_LEFT),
                    'fecha' => date('d/m/Y, h:i a'),
                    'cliente' => $cliente,
                    'cajero' => $cajero,
                    'total' => $total,
                    'items' => array_map(fn($it) => ['nombre' => $it['nombre'], 'cantidad' => $it['cantidad'], 'precio' => $it['precio']], $validos)
                ]]);
            } catch (Throwable $e) {
                if ($conexion->inTransaction()) $conexion->rollBack();
                throw $e;
            }
        }

        case 'eliminar_pedido': {
            if ($metodo !== 'POST') responder(['ok' => false, 'error' => 'Método no permitido.'], 405);
            $id = (int)($datos['id'] ?? 0);
            if ($id <= 0) responder(['ok' => false, 'error' => 'Identificador de pedido inválido.'], 422);
            try {
                $conexion->beginTransaction();
                $stmt = $conexion->prepare('DELETE FROM detalle_pedidos WHERE id_pedido = ?');
                $stmt->execute([$id]);
                $stmt = $conexion->prepare('DELETE FROM pedidos WHERE id_pedido = ?');
                $stmt->execute([$id]);
                if ($stmt->rowCount() === 0) {
                    $conexion->rollBack();
                    responder(['ok' => false, 'error' => 'No se encontró el pedido.'], 404);
                }
                $conexion->commit();
                responder(['ok' => true, 'mensaje' => 'Pedido eliminado.']);
            } catch (Throwable $e) {
                if ($conexion->inTransaction()) $conexion->rollBack();
                throw $e;
            }
        }

        default:
            responder(['ok' => false, 'error' => 'Acción no reconocida: ' . $accion], 404);
    }
} catch (Throwable $e) {
    if (isset($conexion) && $conexion instanceof PDO && $conexion->inTransaction()) $conexion->rollBack();
    error_log('[Estrella Dulce API] ' . $e->getMessage());
    $mensaje = $e instanceof PDOException
        ? 'Error de base de datos. Revisa que las tablas y columnas de estrella_dulce coincidan con el proyecto.'
        : $e->getMessage();
    responder(['ok' => false, 'error' => $mensaje], 500);
}
}

if (!isset($_SESSION['id_usuario']) || !isset($_SESSION['rol']) || $_SESSION['rol'] !== 'ADMINISTRADOR') {
    header('Location: ../vistas/iniciar%20sesion.php');
    exit;
}

/* La interfaz y el CRUD visual viven en este mismo archivo. */
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Panel Administrador - Estrella Dulce</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Fredoka:wght@400;500;600;700&family=Work+Sans:wght@400;500;600&display=swap" rel="stylesheet">

  <style>
    :root {
      --ed-accent: rgb(219, 26, 53);
      --ed-accent-dark: #9E0F26;
      --ed-cream: #FFF8F0;
      --ed-plum: #3A0D14;
      --ed-gold: #E0A526;
      --ed-ink: #4A3B36;
    }
    body { background: var(--ed-cream); font-family: 'Work Sans', sans-serif; color: var(--ed-ink); }
    .ed-header { background: var(--ed-accent); display: flex; align-items: center; justify-content: space-between; padding: 18px 32px; }
    .ed-header__brand { display: flex; align-items: center; gap: 14px; text-decoration: none; color: #fff; }
    .ed-header__logo { width: 42px; height: 42px; border-radius: 50%; object-fit: cover; background: #fff; padding: 4px; border: 2px solid #fff; }
    .ed-header__title { font-family: 'Fredoka', sans-serif; font-weight: 600; font-size: 1.4rem; margin: 0; color: #fff; }
    .ed-nav a { color: #fff; text-decoration: none; font-weight: 500; padding: .3rem .8rem; border-radius: 6px; transition: background .2s; }
    .ed-nav a:hover { background: rgba(255,255,255,.15); }

    h2.panel-title { font-family: 'Fredoka', sans-serif; font-weight: 600; color: var(--ed-plum); }
    h2.panel-title .estrella { color: var(--ed-accent); }

    .ed-dashboard-eyebrow { font-size: .78rem; text-transform: uppercase; letter-spacing: .14em; color: var(--ed-accent); font-weight: 600; margin-bottom: 4px; }
    .ed-dashboard-eyebrow::before { content: "✦"; margin-right: 6px; }

    .ed-stat { position: relative; display: flex; align-items: center; gap: 14px; border-radius: 16px; padding: 20px 22px; background: #fff; border: 1px solid #F0E2D8; box-shadow: 0 4px 14px rgba(58,13,20,.06); overflow: hidden; transition: transform .18s, box-shadow .18s; }
    .ed-stat:hover { transform: translateY(-3px); box-shadow: 0 10px 24px rgba(58,13,20,.12); }
    .ed-stat::before { content: ""; position: absolute; top: 0; left: 0; right: 0; height: 4px; background: var(--ed-accent); }
    .ed-stat--gold::before { background: var(--ed-gold); }
    .ed-stat--plum::before { background: var(--ed-plum); }
    .ed-stat__icon { flex-shrink: 0; width: 46px; height: 46px; display: flex; align-items: center; justify-content: center; border-radius: 50%; font-size: 1.3rem; background: rgba(219,26,53,.1); }
    .ed-stat--gold .ed-stat__icon { background: rgba(224,165,38,.16); }
    .ed-stat--plum .ed-stat__icon { background: rgba(58,13,20,.08); }
    .ed-stat__label { font-size: .78rem; text-transform: uppercase; letter-spacing: .06em; color: #9C8A80; margin-bottom: 2px; }
    .ed-stat__value { font-family: 'Fredoka', sans-serif; font-weight: 600; font-size: 1.8rem; color: var(--ed-plum); line-height: 1.1; }
    .ed-stat__value.money { color: var(--ed-accent); }

    .btn-recibo { background: var(--ed-accent); border: none; color: #fff; font-weight: 600; padding: 10px 22px; border-radius: 999px; box-shadow: 0 4px 12px rgba(219,26,53,.3); transition: transform .15s, box-shadow .15s; }
    .btn-recibo:hover { background: var(--ed-accent-dark); color: #fff; transform: translateY(-1px); box-shadow: 0 6px 16px rgba(219,26,53,.4); }
    .btn-acceso { background: #fff; border: 1.5px solid #F0E2D8; color: var(--ed-plum); font-weight: 600; padding: 9px 18px; border-radius: 999px; transition: all .15s; }
    .btn-acceso:hover { border-color: var(--ed-accent); color: var(--ed-accent); transform: translateY(-1px); box-shadow: 0 6px 16px rgba(219,26,53,.15); }

    .nav-tabs .nav-link { position: relative; font-weight: 500; color: #9C8A80; border: none; transition: color .2s; }
    .nav-tabs .nav-link::after { content: ""; position: absolute; left: 0; right: 0; bottom: -1px; height: 3px; background: var(--ed-accent); transform: scaleX(0); transition: transform .25s; }
    .nav-tabs .nav-link.active { color: var(--ed-accent); background: transparent; }
    .nav-tabs .nav-link.active::after { transform: scaleX(1); }

    .stock-input { width: 78px; display: inline-block; }
    .badge-stock { font-weight: 600; padding: 6px 10px; border-radius: 999px; }
    .badge-stock--ok { background: rgba(40,167,69,.12); color: #1e7e34; }
    .badge-stock--bajo { background: rgba(224,165,38,.18); color: #8a6408; }
    .badge-stock--agotado { background: rgba(219,26,53,.12); color: var(--ed-accent-dark); }

    .disponible-card { background: #fff; border: 1px solid #F0E2D8; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 14px rgba(58,13,20,.06); transition: transform .18s, box-shadow .18s; height: 100%; }
    .disponible-card:hover { transform: translateY(-3px); box-shadow: 0 10px 24px rgba(58,13,20,.12); }
    .disponible-card img { width: 100%; height: 150px; object-fit: cover; }
    .disponible-card__body { padding: 14px 16px; }
    .disponible-card__nombre { font-family: 'Fredoka', sans-serif; font-weight: 600; color: var(--ed-plum); margin-bottom: 2px; }
    .disponible-card__precio { color: var(--ed-accent); font-weight: 700; }

    .recibo-ticket-wrap { max-width: 340px; margin: 0 auto; }
    .recibo-caja { background: #fffdf9; padding: 28px 26px 34px; border-radius: 12px; border: 1px solid #e9ecef; }
    .recibo-estrella { text-align: center; color: var(--ed-gold); font-size: 1.1rem; letter-spacing: .3em; margin-bottom: 6px; }
    .recibo-caja h2 { font-family: 'Fredoka', sans-serif; font-weight: 600; color: var(--ed-accent); text-align: center; margin: 0 0 2px; font-size: 1.5rem; }
    .recibo-caja .subtitulo { text-align: center; font-size: .75rem; letter-spacing: .1em; text-transform: uppercase; color: #B09A8C; margin-bottom: 16px; }
    .recibo-perforado { border: none; border-top: 2px dashed #E7D3C5; margin: 14px 0; }
    .recibo-linea { display: flex; justify-content: space-between; margin: 8px 0; font-size: .85rem; color: var(--ed-ink); }
    .recibo-linea span:first-child { color: #A08F84; }
    .recibo-total { display: flex; justify-content: space-between; align-items: baseline; margin-top: 14px; }
    .recibo-total span:first-child { font-size: .85rem; letter-spacing: .08em; color: var(--ed-plum); font-weight: 700; }
    .recibo-total span:last-child { font-size: 1.5rem; font-weight: 700; color: var(--ed-accent); }
    .recibo-footer { text-align: center; font-size: .72rem; color: #B09A8C; margin-top: 20px; font-style: italic; }

    @media (max-width: 768px) {
      .ed-header { flex-direction: column; gap: .5rem; padding: 1rem; }
      .ed-header__title { font-size: 1.1rem; }
      .container { padding: 0 .5rem; }
    }
    @media print {
      body * { visibility: hidden !important; }
      #recibo, #recibo * { visibility: visible !important; }
      #recibo { position: absolute; left: 0; top: 0; width: 100%; }
    }

    /* =========================================
   DISEÑO RESPONSIVE - ESTRELLA DULCE
========================================= */

* {
    box-sizing: border-box;
}

html {
    width: 100%;
    overflow-x: hidden;
}

body {
    min-width: 320px;
    overflow-x: hidden;
}

.container {
    width: 100%;
    max-width: 1200px;
}

/* ENCABEZADO */

.ed-header {
    width: 100%;
    min-height: 78px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
    flex-wrap: wrap;
}

.ed-header__brand {
    min-width: 0;
}

.ed-header__logo {
    width: 48px;
    height: 48px;
    flex-shrink: 0;
    object-fit: contain;
    border-radius: 50%;
    background: #fff;
    padding: 4px;
}

.ed-header__title {
    font-size: clamp(1rem, 2.5vw, 1.4rem);
}

/* TARJETAS DEL RESUMEN */

.ed-stat {
    width: 100%;
    min-height: 95px;
}

.ed-stat__value {
    font-size: clamp(1.3rem, 3vw, 1.8rem);
    overflow-wrap: anywhere;
}

/* BOTONES DE ACCESO */

.btn-acceso,
.btn-recibo {
    white-space: normal;
    max-width: 100%;
}

/* PESTAÑAS */

.nav-tabs {
    display: flex;
    flex-wrap: nowrap;
    overflow-x: auto;
    overflow-y: hidden;
    white-space: nowrap;
    scrollbar-width: thin;
}

.nav-tabs .nav-item {
    flex: 0 0 auto;
}

.nav-tabs .nav-link {
    white-space: nowrap;
}

/* TABLAS */

.table-responsive {
    width: 100%;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}

.table {
    min-width: 650px;
    vertical-align: middle;
}

.table td,
.table th {
    white-space: normal;
}

/* IMÁGENES DE PRODUCTOS */

.disponible-card {
    height: 100%;
}

.disponible-card img {
    width: 100%;
    height: 170px;
    object-fit: cover;
}

/* MODALES */

.modal-dialog {
    width: auto;
    max-width: calc(100% - 20px);
    margin: 10px auto;
}

.modal-content {
    border-radius: 14px;
}

.modal-body {
    overflow-x: auto;
}

/* =========================================
   TABLETS
========================================= */

@media (max-width: 991px) {

    .container {
        margin-top: 25px !important;
        padding-left: 18px;
        padding-right: 18px;
    }

    .ed-stat {
        padding: 16px;
    }

    .ed-header {
        padding: 14px 20px;
    }

    .d-flex.justify-content-end {
        justify-content: flex-start !important;
    }
}

/* =========================================
   CELULARES
========================================= */

@media (max-width: 576px) {

    .ed-header {
        flex-direction: column;
        justify-content: center;
        text-align: center;
        padding: 15px 12px;
        gap: 12px;
    }

    .ed-header__brand {
        flex-direction: column;
        justify-content: center;
        gap: 8px;
    }

    .ed-header__logo {
        width: 58px;
        height: 58px;
    }

    .ed-header__title {
        font-size: 1.15rem;
        text-align: center;
    }

    .ed-nav {
        width: 100%;
        text-align: center;
    }

    .container {
        margin-top: 22px !important;
        padding-left: 12px;
        padding-right: 12px;
    }

    h2.panel-title {
        font-size: 1.45rem;
        text-align: center;
    }

    .ed-dashboard-eyebrow {
        text-align: center;
        margin-bottom: 12px;
    }

    .ed-stat {
        padding: 16px;
        min-height: 85px;
    }

    .d-flex.flex-wrap.justify-content-end {
        display: grid !important;
        grid-template-columns: 1fr 1fr;
        gap: 8px !important;
    }

    .d-flex.flex-wrap.justify-content-end button {
        width: 100%;
        font-size: .82rem;
        padding: 10px 8px;
    }

    .nav-tabs {
        margin-bottom: 18px !important;
    }

    .nav-tabs .nav-link {
        font-size: .82rem;
        padding: 10px 12px;
    }

    .tab-pane h4 {
        font-size: 1.1rem;
    }

    .tab-pane .d-flex.justify-content-between {
        align-items: flex-start !important;
        gap: 10px;
        flex-wrap: wrap;
    }

    .tab-pane .btn {
        font-size: .85rem;
    }

    .disponible-card img {
        height: 190px;
    }

    .modal-dialog {
        margin: 8px auto;
        max-width: calc(100% - 16px);
    }

    .modal-body {
        padding: 14px;
    }

    .recibo-caja {
        padding: 20px 15px;
    }

    .recibo-total span:last-child {
        font-size: 1.2rem;
    }
}

/* =========================================
   CELULARES MUY PEQUEÑOS
========================================= */

@media (max-width: 360px) {

    .ed-header__title {
        font-size: 1rem;
    }

    .ed-stat__value {
        font-size: 1.25rem;
    }

    .d-flex.flex-wrap.justify-content-end {
        grid-template-columns: 1fr;
    }

    .nav-tabs .nav-link {
        font-size: .75rem;
        padding: 9px;
    }
}

/* IMPRESIÓN DE FACTURA */

@media print {

    body * {
        visibility: hidden !important;
    }

    #recibo,
    #recibo * {
        visibility: visible !important;
    }

    #recibo {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
    }
}
  </style>
</head>
<body>

<header class="ed-header">
  <a href="./admin.php" class="ed-header__brand">
    <img src="./imagen/Logo_Estrella_Dulce-removebg-preview.png" alt="Logo Estrella Dulce" class="ed-header__logo">
    <h1 class="ed-header__title">Estrella Dulce (Administración)</h1>
  </a>
  <nav class="ed-nav">
    <a href="./index.php">🏠 Volver a la Tienda</a>
  </nav>
</header>

<div class="container my-5">
  <h2 class="mb-4 panel-title">Panel de Control <span class="estrella">General</span></h2>

  <div class="ed-dashboard">
    <div class="ed-dashboard-eyebrow">Resumen general</div>
    <div class="row mb-4 g-3">
      <div class="col-md-4">
        <div class="ed-stat">
          <span class="ed-stat__icon">💰</span>
          <div class="ed-stat__body">
            <div class="ed-stat__label">Total Ventas</div>
            <div class="ed-stat__value money" id="totalVentas">$0</div>
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="ed-stat ed-stat--gold">
          <span class="ed-stat__icon">🧾</span>
          <div class="ed-stat__body">
            <div class="ed-stat__label">Pedidos Totales</div>
            <div class="ed-stat__value" id="totalPedidos">0</div>
          </div>
        </div>
      </div>
      <div class="col-md-4">
        <div class="ed-stat ed-stat--plum">
          <span class="ed-stat__icon">🍰</span>
          <div class="ed-stat__body">
            <div class="ed-stat__label">Postres Registrados</div>
            <div class="ed-stat__value" id="totalProductos">0</div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="d-flex flex-wrap justify-content-end gap-2 mb-4">
    <button class="btn btn-acceso" onclick="irAPestana('inventario-tab')">📦 Inventario</button>
    <button class="btn btn-acceso" onclick="irAPestana('disponibles-tab')">🛒 Productos Disponibles</button>
    <button class="btn btn-acceso" onclick="irAPestana('reportes-tab')">📊 Reportes de Caja</button>
    <button class="btn btn-recibo" onclick="abrirTomarPedido()">🧾 Tomar Pedido</button>
  </div>

  <ul class="nav nav-tabs mb-4" id="adminTab" role="tablist">
    <li class="nav-item"><button class="nav-link active" id="postres-tab" data-bs-toggle="tab" data-bs-target="#postres-pane" type="button">Gestión de Postres</button></li>
    <li class="nav-item"><button class="nav-link" id="pedidos-tab" data-bs-toggle="tab" data-bs-target="#pedidos-pane" type="button">Gestión de Pedidos</button></li>
    <li class="nav-item"><button class="nav-link" id="inventario-tab" data-bs-toggle="tab" data-bs-target="#inventario-pane" type="button">Inventario</button></li>
    <li class="nav-item"><button class="nav-link" id="disponibles-tab" data-bs-toggle="tab" data-bs-target="#disponibles-pane" type="button">Productos Disponibles</button></li>
    <li class="nav-item"><button class="nav-link" id="reportes-tab" data-bs-toggle="tab" data-bs-target="#reportes-pane" type="button">Reportes de Caja</button></li>
  </ul>

  <div class="tab-content" id="adminTabContent">

    <div class="tab-pane fade show active" id="postres-pane" role="tabpanel">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h4>Catálogo de Postres</h4>
        <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modalPostre" onclick="limpiarFormulario()">+ Nuevo Postre</button>
      </div>
      <div class="table-responsive bg-white shadow-sm rounded p-3">
        <table class="table table-hover align-middle">
          <thead>
            <tr>
              <th>Imagen</th><th>Nombre</th><th>Categoría</th><th>Precio</th>
              <th>Descripción</th><th>Stock</th><th>Acciones</th>
            </tr>
          </thead>
          <tbody id="tablaPostres"></tbody>
        </table>
      </div>
    </div>

    <div class="tab-pane fade" id="pedidos-pane" role="tabpanel">
      <h4 class="mb-3">Listado de Pedidos</h4>
      <div class="table-responsive bg-white shadow-sm rounded p-3">
        <table class="table table-hover align-middle">
          <thead>
            <tr>
              <th># Pedido</th><th>Cliente</th><th>Productos</th>
              <th>Total</th><th>Estado</th><th>Acciones</th>
            </tr>
          </thead>
          <tbody id="tablaPedidos"></tbody>
        </table>
      </div>
    </div>

    <div class="tab-pane fade" id="inventario-pane" role="tabpanel">
      <h4 class="mb-3">Inventario de Postres</h4>
      <div class="table-responsive bg-white shadow-sm rounded p-3">
        <table class="table table-hover align-middle">
          <thead>
            <tr>
              <th>Postre</th><th>Precio</th><th>Stock actual</th>
              <th>Estado</th><th>Ajustar</th>
            </tr>
          </thead>
          <tbody id="tablaInventario"></tbody>
        </table>
      </div>
    </div>

    <div class="tab-pane fade" id="disponibles-pane" role="tabpanel">
      <h4 class="mb-3">Productos Disponibles</h4>
      <div class="row g-3" id="gridDisponibles"></div>
    </div>

    <div class="tab-pane fade" id="reportes-pane" role="tabpanel">
      <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <h4 class="mb-0">Pedidos Tomados por Cajero</h4>
        <div class="d-flex flex-wrap gap-2">
          <select class="form-select filtro-reportes" id="filtroCajero" onchange="renderizarReportes()">
            <option value="">Todos los cajeros</option>
          </select>
          <button class="btn btn-success" onclick="abrirTomarPedido()">+ Registrar Pedido</button>
        </div>
      </div>
      <div class="table-responsive bg-white shadow-sm rounded p-3">
        <table class="table table-hover align-middle">
          <thead>
            <tr>
              <th>Fecha</th><th>Cliente</th><th>Cajero</th>
              <th>Productos</th><th>Total</th><th>Acciones</th>
            </tr>
          </thead>
          <tbody id="tablaReportes"></tbody>
        </table>
      </div>
    </div>

  </div>
</div>

<!-- MODAL POSTRE -->
<div class="modal fade" id="modalPostre" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalPostreTitulo">Agregar Postre</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form id="formPostre">
          <input type="hidden" id="postreId" />
          <div class="mb-3">
            <label for="nombrePostre" class="form-label">Nombre del Postre</label>
            <input type="text" class="form-control" id="nombrePostre" required />
          </div>
          <div class="mb-3">
            <label for="precioPostre" class="form-label">Precio ($)</label>
            <input type="number" class="form-control" id="precioPostre" min="0" step="0.01" required />
          </div>
          <div class="mb-3">
            <label for="categoriaPostre" class="form-label">Categoría</label>
            <select class="form-select" id="categoriaPostre" required>
              <option value="Tortas">Tortas</option>
              <option value="Postres Fríos">Postres Fríos</option>
              <option value="Postres Calientes">Postres Calientes</option>
              <option value="Panadería">Panadería</option>
              <option value="Bebidas">Bebidas</option>
              <option value="Otros">Otros</option>
            </select>
          </div>
          <div class="mb-3">
            <label for="imagenPostre" class="form-label">Ruta de Imagen</label>
            <input type="text" class="form-control" id="imagenPostre" placeholder="../imagen/postre.jpg" required />
          </div>
          <div class="mb-3">
            <label for="descripcionPostre" class="form-label">Descripción</label>
            <textarea class="form-control" id="descripcionPostre" rows="3" required></textarea>
          </div>
          <div class="mb-3">
            <label for="stockPostre" class="form-label">Stock disponible</label>
            <input type="number" class="form-control" id="stockPostre" min="0" value="0" required />
          </div>
          <button type="submit" class="btn btn-primary w-100">Guardar Producto</button>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- MODAL TOMAR PEDIDO -->
<div class="modal fade" id="modalTomarPedido" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Tomar Pedido del Cliente</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" onclick="limpiarCarrito()"></button>
      </div>
      <div class="modal-body">
        <div class="row g-3 mb-3">
          <div class="col-md-6">
            <label for="clienteCarrito" class="form-label">Nombre del Cliente</label>
            <input type="text" class="form-control" id="clienteCarrito" required />
          </div>
          <div class="col-md-6">
            <label for="cajeroCarrito" class="form-label">Cajero</label>
            <input type="text" class="form-control" id="cajeroCarrito" placeholder="ej: Laura Gómez" required />
          </div>
        </div>
        <hr class="recibo-perforado">
        <div class="row g-2 align-items-end mb-3">
          <div class="col-md-6">
            <label for="productoCarrito" class="form-label">Producto</label>
            <select class="form-select" id="productoCarrito"></select>
          </div>
          <div class="col-md-3">
            <label for="cantidadCarrito" class="form-label">Cantidad</label>
            <input type="number" class="form-control" id="cantidadCarrito" min="1" value="1" />
          </div>
          <div class="col-md-3">
            <button type="button" class="btn btn-success w-100" onclick="agregarItemCarrito()">+ Agregar</button>
          </div>
        </div>
        <div class="table-responsive">
          <table class="table table-sm align-middle">
            <thead>
              <tr>
                <th>Producto</th><th>Categoría</th><th>Precio</th>
                <th>Cant.</th><th>Subtotal</th><th></th>
              </tr>
            </thead>
            <tbody id="tablaCarrito">
              <tr><td colspan="6" class="text-center text-muted py-3">Aún no has agregado productos al pedido.</td></tr>
            </tbody>
          </table>
        </div>
        <div class="recibo-total px-1">
          <span>TOTAL PEDIDO</span>
          <span id="totalCarrito">$0</span>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" onclick="limpiarCarrito()">Cancelar</button>
        <button type="button" class="btn btn-recibo" onclick="confirmarPedido()">✅ Confirmar y Generar Factura</button>
      </div>
    </div>
  </div>
</div>

<!-- MODAL FACTURA -->
<div class="modal fade" id="modalFactura" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Factura Electrónica</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div id="recibo">
          <div class="recibo-ticket-wrap">
            <div class="recibo-caja">
              <div class="recibo-estrella">✦ ✦ ✦</div>
              <h2>Estrella Dulce</h2>
              <div class="subtitulo">Factura electrónica de venta</div>
              <hr class="recibo-perforado">
              <div class="recibo-linea"><span>N° Factura</span><span id="reciboNumero"></span></div>
              <div class="recibo-linea"><span>Fecha</span><span id="reciboFecha"></span></div>
              <div class="recibo-linea"><span>Cliente</span><span id="reciboCliente"></span></div>
              <div class="recibo-linea"><span>Atendido por</span><span id="reciboCajero"></span></div>
              <hr class="recibo-perforado">
              <div id="reciboItems"></div>
              <hr class="recibo-perforado">
              <div class="recibo-total"><span>TOTAL</span><span id="reciboTotal"></span></div>
              <div class="recibo-footer">¡Gracias por su compra! — Estrella Dulce</div>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
        <button type="button" class="btn btn-recibo" onclick="imprimirRecibo()">🖨️ Imprimir</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
// ==========================================================
//  ESTADO GLOBAL
// ==========================================================
let postresGlobal = [];
let pedidosGlobal  = [];
let carritoPedido  = [];
// Las acciones AJAX se procesan en este mismo admin.php.
const API = new URL('admin.php', window.location.href).toString();

// ==========================================================
//  HELPERS DE COMUNICACIÓN CON PHP
// ==========================================================
async function leerRespuestaAPI(respuesta) {
  const texto = await respuesta.text();
  let datos;

  try {
    datos = JSON.parse(texto);
  } catch (_) {
    const detalle = texto.replace(/<[^>]*>/g, ' ').replace(/\\s+/g, ' ').trim().slice(0, 220);
    if (respuesta.status === 404) {
      throw new Error('No se encontró admin.php (HTTP 404). Abre el proyecto desde XAMPP/Apache, no desde Live Server (puerto 5500).');
    }
    throw new Error(`El servidor no devolvió JSON válido (HTTP ${respuesta.status}). ${detalle}`);
  }

  if (!respuesta.ok || datos.ok === false) {
    throw new Error(datos.error || `Error del servidor (HTTP ${respuesta.status}).`);
  }
  return datos;
}

async function apiGet(accion) {
  const respuesta = await fetch(`${API}?accion=${encodeURIComponent(accion)}`, {
    method: 'GET',
    headers: { 'Accept': 'application/json' },
    cache: 'no-store'
  });
  return leerRespuestaAPI(respuesta);
}

async function apiPost(accion, datos) {
  const respuesta = await fetch(`${API}?accion=${encodeURIComponent(accion)}`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json; charset=UTF-8',
      'Accept': 'application/json'
    },
    body: JSON.stringify(datos)
  });
  return leerRespuestaAPI(respuesta);
}

function mostrarErrorAPI(error) {
  console.error(error);
  alert(error && error.message ? error.message : 'No fue posible comunicarse con el servidor.');
}
function rutaImagenPostre(valor) {
  const ruta = String(valor || '').trim().replace(/\\/g, '/');
  if (!ruta) return 'imagen/postre_cafe.jpg';

  // Si la BD guarda solo "postre_cafe.jpg", las imágenes viven en /imagen.
  // Si ya guarda una ruta o URL, se respeta para evitar duplicar carpetas.
  if (/^(https?:|data:|blob:|\/)/i.test(ruta)) return ruta;
  if (/^(\.\/)?imagen\//i.test(ruta)) return ruta.replace(/^\.\//, '');
  return 'imagen/' + ruta.split('/').map(parte => encodeURIComponent(parte)).join('/');
}

function formatoPrecio(v) {
  const n = Number(v) || 0;
  return '$' + n.toLocaleString('es-CO');
}
function estadoStock(stock) {
  const c = Number(stock) || 0;
  if (c <= 0) return { texto: 'Agotado',     clase: 'badge-stock--agotado' };
  if (c <= 5) return { texto: 'Stock bajo',  clase: 'badge-stock--bajo' };
  return               { texto: 'Disponible', clase: 'badge-stock--ok' };
}
function irAPestana(idBoton) {
  const boton = document.getElementById(idBoton);
  if (boton) bootstrap.Tab.getOrCreateInstance(boton).show();
}

// ==========================================================
//  CARGA Y RENDER
// ==========================================================
async function cargarTodo() {
  try {
    const [rp, rped] = await Promise.all([
      apiGet('listar_postres'),
      apiGet('listar_pedidos')
    ]);
    postresGlobal = Array.isArray(rp.postres) ? rp.postres : [];
    pedidosGlobal = Array.isArray(rped.pedidos) ? rped.pedidos : [];
    renderizarTodo();
  } catch (error) {
    mostrarErrorAPI(error);
  }
}

function renderizarTodo() {
  renderizarPostres();
  renderizarPedidos();
  renderizarInventario();
  renderizarDisponibles();
  renderizarReportes();
  actualizarDashboard();
}


function renderizarPedidos() {
  const tbody = document.getElementById('tablaPedidos');
  if (!tbody) return;
  tbody.innerHTML = '';
  if (!pedidosGlobal.length) {
    tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">Aún no hay pedidos registrados.</td></tr>';
    return;
  }
  pedidosGlobal.slice().reverse().forEach(p => {
    const fila = document.createElement('tr');
    const productos = Array.isArray(p.items) ? p.items.map(it => `${it.nombre} x${it.cantidad}`).join(', ') : '';
    fila.innerHTML = `
      <td>${p.numero || p.id}</td>
      <td>${p.cliente || ''}</td>
      <td>${productos}</td>
      <td>${formatoPrecio(p.total)}</td>
      <td><span class="badge bg-success">Registrado</span></td>
      <td><button class="btn btn-sm btn-outline-danger" onclick="eliminarPedidoCaja('${p.id}')">Eliminar</button></td>`;
    tbody.appendChild(fila);
  });
}

function actualizarDashboard() {
  const totalVentas = pedidosGlobal.reduce((a, p) => a + (Number(p.total) || 0), 0);
  document.getElementById('totalVentas').textContent    = formatoPrecio(totalVentas);
  document.getElementById('totalPedidos').textContent   = pedidosGlobal.length;
  document.getElementById('totalProductos').textContent = postresGlobal.length;
}

function renderizarPostres() {
  const tbody = document.getElementById('tablaPostres');
  tbody.innerHTML = '';
  if (postresGlobal.length === 0) {
    tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">Aún no hay postres registrados.</td></tr>';
    return;
  }
  postresGlobal.forEach(p => {
    const fila = document.createElement('tr');
    fila.innerHTML = `
      <td><img src="${rutaImagenPostre(p.imagen)}" alt="${p.nombre}" style="width:60px;height:60px;object-fit:cover;border-radius:8px;" onerror="this.onerror=null;this.src='imagen/postre_cafe.jpg'"></td>
      <td>${p.nombre}</td>
      <td>${p.categoria || 'Otros'}</td>
      <td>${formatoPrecio(p.precio)}</td>
      <td>${p.descripcion}</td>
      <td>${Number(p.stock) || 0}</td>
      <td>
        <button class="btn btn-sm btn-outline-primary me-1" onclick="editarPostre('${p.id}')">Editar</button>
        <button class="btn btn-sm btn-outline-danger" onclick="eliminarPostre('${p.id}')">Eliminar</button>
      </td>`;
    tbody.appendChild(fila);
  });
}

function renderizarInventario() {
  const tbody = document.getElementById('tablaInventario');
  tbody.innerHTML = '';
  if (postresGlobal.length === 0) {
    tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">No hay postres en el catálogo.</td></tr>';
    return;
  }
  postresGlobal.forEach(p => {
    const estado = estadoStock(p.stock);
    const fila = document.createElement('tr');
    fila.innerHTML = `
      <td>${p.nombre}</td>
      <td>${formatoPrecio(p.precio)}</td>
      <td><input type="number" min="0" class="form-control form-control-sm stock-input" id="stockInv_${p.id}" value="${Number(p.stock) || 0}"></td>
      <td><span class="badge-stock ${estado.clase}">${estado.texto}</span></td>
      <td>
        <button class="btn btn-sm btn-outline-secondary me-1" onclick="ajustarStock('${p.id}', -1)">−</button>
        <button class="btn btn-sm btn-outline-secondary me-1" onclick="ajustarStock('${p.id}', 1)">+</button>
        <button class="btn btn-sm btn-primary" onclick="guardarStockManual('${p.id}')">Guardar</button>
      </td>`;
    tbody.appendChild(fila);
  });
}

function renderizarDisponibles() {
  const grid = document.getElementById('gridDisponibles');
  grid.innerHTML = '';
  const disponibles = postresGlobal.filter(p => (Number(p.stock) || 0) > 0);
  if (disponibles.length === 0) {
    grid.innerHTML = '<div class="col-12"><p class="text-center text-muted py-4">No hay postres con stock disponible.</p></div>';
    return;
  }
  disponibles.forEach(p => {
    const col = document.createElement('div');
    col.className = 'col-sm-6 col-md-4 col-lg-3';
    col.innerHTML = `
      <div class="disponible-card">
        <img src="${rutaImagenPostre(p.imagen)}" alt="${p.nombre}" onerror="this.onerror=null;this.src='imagen/postre_cafe.jpg'">
        <div class="disponible-card__body">
          <div class="disponible-card__nombre">${p.nombre}</div>
          <div class="disponible-card__precio">${formatoPrecio(p.precio)}</div>
          <div class="mt-2"><span class="badge-stock badge-stock--ok">Stock: ${Number(p.stock) || 0}</span></div>
        </div>
      </div>`;
    grid.appendChild(col);
  });
}

function poblarFiltroCajeros() {
  const select = document.getElementById('filtroCajero');
  const valorActual = select.value;
  const cajeros = [...new Set(pedidosGlobal.map(p => p.cajero))].sort();
  select.innerHTML = '<option value="">Todos los cajeros</option>';
  cajeros.forEach(c => {
    const o = document.createElement('option');
    o.value = c; o.textContent = c;
    select.appendChild(o);
  });
  if (valorActual) select.value = valorActual;
}

function renderizarReportes() {
  poblarFiltroCajeros();
  const filtro = document.getElementById('filtroCajero').value;
  const tbody  = document.getElementById('tablaReportes');
  tbody.innerHTML = '';
  const filtrados = filtro ? pedidosGlobal.filter(p => p.cajero === filtro) : pedidosGlobal;
  if (filtrados.length === 0) {
    tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">Aún no hay pedidos registrados por caja.</td></tr>';
    return;
  }
  filtrados.slice().reverse().forEach(p => {
    const resumen = p.items.map(it => it.nombre + ' x' + it.cantidad).join(', ');
    const fila = document.createElement('tr');
    fila.innerHTML = `
      <td>${p.fecha}</td>
      <td>${p.cliente}</td>
      <td>${p.cajero}</td>
      <td>${resumen}</td>
      <td>${formatoPrecio(p.total)}</td>
      <td><button class="btn btn-sm btn-outline-danger" onclick="eliminarPedidoCaja('${p.id}')">Eliminar</button></td>`;
    tbody.appendChild(fila);
  });
}

// ==========================================================
//  CRUD POSTRES
// ==========================================================
function limpiarFormulario() {
  document.getElementById('formPostre').reset();
  document.getElementById('postreId').value = '';
  document.getElementById('stockPostre').value = 0;
  document.getElementById('modalPostreTitulo').textContent = 'Agregar Postre';
}

function editarPostre(id) {
  const p = postresGlobal.find(x => String(x.id) === String(id));
  if (!p) return;
  document.getElementById('postreId').value         = p.id;
  document.getElementById('nombrePostre').value     = p.nombre;
  document.getElementById('precioPostre').value     = p.precio;
  document.getElementById('categoriaPostre').value  = p.categoria || 'Otros';
  document.getElementById('imagenPostre').value     = p.imagen;
  document.getElementById('descripcionPostre').value= p.descripcion;
  document.getElementById('stockPostre').value      = Number(p.stock) || 0;
  document.getElementById('modalPostreTitulo').textContent = 'Editar Postre';
  new bootstrap.Modal(document.getElementById('modalPostre')).show();
}

async function eliminarPostre(id) {
  if (!confirm('¿Seguro que quieres eliminar este postre?')) return;
  await apiPost('eliminar_postre', { id });
  await cargarTodo();
}

document.getElementById('formPostre').addEventListener('submit', async function (e) {
  e.preventDefault();

  const formulario = this;
  const botonGuardar = formulario.querySelector('button[type="submit"]');
  const textoOriginal = botonGuardar.textContent;

  const datos = {
    id:          document.getElementById('postreId').value.trim(),
    nombre:      document.getElementById('nombrePostre').value.trim(),
    precio:      document.getElementById('precioPostre').value,
    categoria:   document.getElementById('categoriaPostre').value,
    imagen:      document.getElementById('imagenPostre').value.trim(),
    descripcion: document.getElementById('descripcionPostre').value.trim(),
    stock:       document.getElementById('stockPostre').value
  };

  if (!datos.nombre || datos.precio === '' || !datos.categoria ||
      !datos.imagen || !datos.descripcion || datos.stock === '') {
    alert('Completa todos los campos del postre.');
    return;
  }

  if (!Number.isFinite(Number(datos.precio)) || Number(datos.precio) < 0) {
    alert('Escribe un precio válido, igual o mayor que cero.');
    return;
  }

  if (!Number.isInteger(Number(datos.stock)) || Number(datos.stock) < 0) {
    alert('El stock debe ser un número entero igual o mayor que cero.');
    return;
  }

  botonGuardar.disabled = true;
  botonGuardar.textContent = 'Guardando...';

  try {
    const res = await apiPost('guardar_postre', datos);

    const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('modalPostre'));
    modal.hide();
    limpiarFormulario();

    await cargarTodo();
    alert(res.mensaje || (datos.id ? 'Postre actualizado correctamente.' : 'Postre guardado correctamente.'));
  } catch (error) {
    mostrarErrorAPI(error);
  } finally {
    botonGuardar.disabled = false;
    botonGuardar.textContent = textoOriginal;
  }
});

// ==========================================================
//  STOCK
// ==========================================================
async function ajustarStock(id, delta) {
  await apiPost('ajustar_stock', { id, delta });
  await cargarTodo();
}
async function guardarStockManual(id) {
  const input = document.getElementById('stockInv_' + id);
  if (!input) return;
  const stock = Math.max(0, Number(input.value) || 0);
  await apiPost('ajustar_stock', { id, stock });
  await cargarTodo();
}

// ==========================================================
//  PEDIDO
// ==========================================================
function abrirTomarPedido() {
  carritoPedido = [];
  document.getElementById('clienteCarrito').value  = '';
  document.getElementById('cajeroCarrito').value   = '';
  document.getElementById('cantidadCarrito').value = 1;
  poblarSelectPostres();
  renderizarCarrito();
  new bootstrap.Modal(document.getElementById('modalTomarPedido')).show();
}

function poblarSelectPostres() {
  const select = document.getElementById('productoCarrito');
  const valorActual = select.value;
  select.innerHTML = '';
  const disponibles = postresGlobal.filter(p => (Number(p.stock) || 0) > 0);
  if (disponibles.length === 0) {
    select.innerHTML = '<option value="">No hay postres con stock</option>';
    return;
  }
  disponibles.forEach(p => {
    const o = document.createElement('option');
    o.value = p.id;
    o.textContent = `${p.nombre} — ${p.categoria || 'Otros'} — ${formatoPrecio(p.precio)}`;
    select.appendChild(o);
  });
  if (valorActual) select.value = valorActual;
}

function limpiarCarrito() {
  carritoPedido = [];
  renderizarCarrito();
}

function agregarItemCarrito() {
  const postreId = document.getElementById('productoCarrito').value;
  const cantidad = Math.max(1, Number(document.getElementById('cantidadCarrito').value) || 1);
  if (!postreId) return;
  const postre = postresGlobal.find(p => String(p.id) === String(postreId));
  if (!postre) return;
  const existente = carritoPedido.find(i => String(i.postreId) === String(postreId));
  if (existente) existente.cantidad += cantidad;
  else carritoPedido.push({
    postreId: postre.id,
    nombre:   postre.nombre,
    categoria:postre.categoria || 'Otros',
    precio:   Number(postre.precio) || 0,
    cantidad
  });
  document.getElementById('cantidadCarrito').value = 1;
  renderizarCarrito();
}

function quitarItemCarrito(postreId) {
  carritoPedido = carritoPedido.filter(i => String(i.postreId) !== String(postreId));
  renderizarCarrito();
}

function renderizarCarrito() {
  const tbody = document.getElementById('tablaCarrito');
  const totalEl = document.getElementById('totalCarrito');
  tbody.innerHTML = '';
  if (carritoPedido.length === 0) {
    tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-3">Aún no has agregado productos al pedido.</td></tr>';
    totalEl.textContent = formatoPrecio(0);
    return;
  }
  let total = 0;
  carritoPedido.forEach(item => {
    const subtotal = item.precio * item.cantidad;
    total += subtotal;
    const fila = document.createElement('tr');
    fila.innerHTML = `
      <td>${item.nombre}</td>
      <td>${item.categoria}</td>
      <td>${formatoPrecio(item.precio)}</td>
      <td>${item.cantidad}</td>
      <td>${formatoPrecio(subtotal)}</td>
      <td><button class="btn btn-sm btn-outline-danger" onclick="quitarItemCarrito('${item.postreId}')">✕</button></td>`;
    tbody.appendChild(fila);
  });
  totalEl.textContent = formatoPrecio(total);
}

async function confirmarPedido() {
  const cliente = document.getElementById('clienteCarrito').value.trim();
  const cajero  = document.getElementById('cajeroCarrito').value.trim();
  if (!cliente || !cajero) { alert('Escribe el cliente y el cajero.'); return; }
  if (carritoPedido.length === 0) { alert('Agrega al menos un producto.'); return; }

  const res = await apiPost('crear_pedido', { cliente, cajero, items: carritoPedido });
  if (!res.ok) { alert(res.error || 'Error al crear pedido'); return; }

  const pedido = res.pedido;

  // Rellenar factura
  document.getElementById('reciboNumero').textContent  = pedido.numero;
  document.getElementById('reciboFecha').textContent   = pedido.fecha;
  document.getElementById('reciboCliente').textContent = pedido.cliente;
  document.getElementById('reciboCajero').textContent  = pedido.cajero;
  document.getElementById('reciboTotal').textContent   = formatoPrecio(pedido.total);

  const cont = document.getElementById('reciboItems');
  cont.innerHTML = '';
  pedido.items.forEach(it => {
    const div = document.createElement('div');
    div.className = 'recibo-linea';
    div.innerHTML = `<span>${it.nombre} x${it.cantidad}</span><span>${formatoPrecio(it.precio * it.cantidad)}</span>`;
    cont.appendChild(div);
  });

  bootstrap.Modal.getInstance(document.getElementById('modalTomarPedido')).hide();
  limpiarCarrito();
  await cargarTodo();

  setTimeout(() => {
    new bootstrap.Modal(document.getElementById('modalFactura')).show();
  }, 300);
}

async function eliminarPedidoCaja(id) {
  if (!confirm('¿Seguro que quieres eliminar este pedido del reporte?')) return;
  await apiPost('eliminar_pedido', { id });
  await cargarTodo();
}

function imprimirRecibo() { window.print(); }

// ==========================================================
//  INICIO
// ==========================================================
document.addEventListener('DOMContentLoaded', cargarTodo);
</script>

</body>
</html>
