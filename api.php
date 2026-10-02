<?php
declare(strict_types=1);

session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'secure' => !empty($_SERVER['HTTPS'])]);
session_start();
require __DIR__ . '/config.php';

header('X-Content-Type-Options: nosniff');

function salida($datos, int $codigo = 200): void {
    http_response_code($codigo);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit;
}
function falla(string $mensaje, int $codigo): void { salida(['error' => $mensaje], $codigo); }

$m = $_SERVER['REQUEST_METHOD'];
$r = '/' . trim((string)($_GET['r'] ?? ''), '/');
$in = json_decode((string)file_get_contents('php://input'), true);
$in = is_array($in) ? $in : [];
$u = $_SESSION['u'] ?? null;

if ($m !== 'GET' && ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') !== 'sirbe') falla('Solicitud no válida', 403);

function exige(array $roles = []): void {
    global $u;
    if (!$u) falla('Inicia sesión para continuar', 401);
    if ($roles && !in_array($u['rol'], $roles, true)) falla('Tu rol no permite esta acción', 403);
}
function texto($v): ?string { $v = trim((string)($v ?? '')); return $v === '' ? null : $v; }

const ESTADOS = ['bueno', 'regular', 'malo', 'en_reparacion', 'baja'];
const SEL = 'SELECT b.*, a.nombre AS area, c.nombre AS categoria, c.grupo
             FROM bienes b LEFT JOIN areas a ON a.id = b.area_id LEFT JOIN categorias c ON c.id = b.categoria_id';
$escribe = ['admin', 'encargado'];

try {
    $db = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (Throwable $e) {
    error_log('SIRBE conexión: ' . $e->getMessage());
    falla('No se pudo conectar a la base de datos. Revisa config.php', 500);
}

try {
    // ---------- Sesión ----------
    if ($r === '/auth/login' && $m === 'POST') {
        $s = $db->prepare('SELECT * FROM usuarios WHERE email = ?');
        $s->execute([strtolower((string)($in['email'] ?? ''))]);
        $x = $s->fetch();
        if (!$x || !password_verify((string)($in['password'] ?? ''), $x['password_hash'])) {
            sleep(1);
            falla('Correo o contraseña incorrectos', 401);
        }
        session_regenerate_id(true);
        $_SESSION['u'] = ['id' => (int)$x['id'], 'nombre' => $x['nombre'], 'rol' => $x['rol']];
        salida(['token' => 'sesion', 'user' => $_SESSION['u']]);
    }
    if ($r === '/auth/logout' && $m === 'POST') { session_destroy(); salida(['ok' => true]); }

    // ---------- Catálogos ----------
    if ($r === '/areas' && $m === 'GET') { exige(); salida($db->query('SELECT * FROM areas ORDER BY nombre')->fetchAll()); }
    if ($r === '/categorias' && $m === 'GET') { exige(); salida($db->query('SELECT * FROM categorias ORDER BY nombre')->fetchAll()); }

    // ---------- Bienes ----------
    if ($r === '/bienes' && $m === 'GET') {
        exige();
        $donde = []; $vals = [];
        if ($q = texto($_GET['q'] ?? null)) {
            $donde[] = '(b.descripcion LIKE ? OR b.codigo LIKE ? OR b.responsable LIKE ?)';
            array_push($vals, "%$q%", "%$q%", "%$q%");
        }
        if ($a = texto($_GET['area'] ?? null)) { $donde[] = 'b.area_id = ?'; $vals[] = (int)$a; }
        if ($e = texto($_GET['estado'] ?? null)) { $donde[] = 'b.estado = ?'; $vals[] = $e; }
        $w = $donde ? 'WHERE ' . implode(' AND ', $donde) : '';
        $pagina = max((int)($_GET['page'] ?? 1), 1);
        $c = $db->prepare("SELECT COUNT(*) FROM bienes b $w"); $c->execute($vals);
        $total = (int)$c->fetchColumn();
        $desde = ($pagina - 1) * 20;
        $s = $db->prepare(SEL . " $w ORDER BY b.id DESC LIMIT 20 OFFSET $desde"); $s->execute($vals);
        salida(['total' => $total, 'page' => $pagina, 'items' => $s->fetchAll()]);
    }

    if ($r === '/bienes' && $m === 'POST') {
        exige($escribe);
        $desc = texto($in['descripcion'] ?? null); $area = (int)($in['area_id'] ?? 0);
        $estado = $in['estado'] ?? 'bueno';
        if (!$desc || !$area) falla('Descripción y área son obligatorias', 400);
        if (!in_array($estado, ESTADOS, true) || $estado === 'baja') falla('Estado no válido', 400);
        $db->beginTransaction();
        try {
            $db->prepare('INSERT INTO bienes (codigo, descripcion, categoria_id, area_id, responsable, estado, valor, fecha_adquisicion)
                          VALUES (?,?,?,?,?,?,?,?)')
               ->execute(['T' . bin2hex(random_bytes(8)), $desc, ($in['categoria_id'] ?? '') ?: null, $area,
                          texto($in['responsable'] ?? null), $estado, ($in['valor'] ?? '') !== '' ? $in['valor'] : null,
                          texto($in['fecha_adquisicion'] ?? null)]);
            $id = (int)$db->lastInsertId();
            $db->prepare("UPDATE bienes SET codigo = CONCAT('BM-', LPAD(id, 5, '0')) WHERE id = ?")->execute([$id]);
            $db->prepare("INSERT INTO movimientos (bien_id, tipo, area_destino, usuario_id, observacion) VALUES (?, 'alta', ?, ?, 'Registro inicial')")
               ->execute([$id, $area, $u['id']]);
            $db->commit();
        } catch (Throwable $e) { $db->rollBack(); throw $e; }
        salida(['id' => $id], 201);
    }

    if (preg_match('#^/bienes/(\d+)$#', $r, $mt)) {
        $id = (int)$mt[1];
        $s = $db->prepare(SEL . ' WHERE b.id = ?'); $s->execute([$id]);
        $bien = $s->fetch();
        if (!$bien) falla('Bien no encontrado', 404);

        if ($m === 'GET') {
            exige();
            $h = $db->prepare('SELECT m.*, ao.nombre AS origen, ad.nombre AS destino, u.nombre AS usuario
                               FROM movimientos m
                               LEFT JOIN areas ao ON ao.id = m.area_origen
                               LEFT JOIN areas ad ON ad.id = m.area_destino
                               LEFT JOIN usuarios u ON u.id = m.usuario_id
                               WHERE m.bien_id = ? ORDER BY m.fecha DESC, m.id DESC');
            $h->execute([$id]);
            $bien['movimientos'] = $h->fetchAll();
            salida($bien);
        }
        if ($m === 'PUT') {
            exige($escribe);
            if ($bien['estado'] === 'baja') falla('El bien ya fue dado de baja', 409);
            $estado = $in['estado'] ?? $bien['estado'];
            if (!in_array($estado, ESTADOS, true) || $estado === 'baja') falla('Estado no válido', 400);
            $db->prepare('UPDATE bienes SET descripcion = ?, categoria_id = ?, responsable = ?, estado = ?, valor = ?, fecha_adquisicion = ? WHERE id = ?')
               ->execute([texto($in['descripcion'] ?? null) ?? $bien['descripcion'], ($in['categoria_id'] ?? '') ?: null,
                          texto($in['responsable'] ?? null), $estado, ($in['valor'] ?? '') !== '' ? $in['valor'] : null,
                          texto($in['fecha_adquisicion'] ?? null), $id]);
            if ($estado === 'en_reparacion' && $bien['estado'] !== 'en_reparacion') {
                $db->prepare("INSERT INTO movimientos (bien_id, tipo, usuario_id, observacion) VALUES (?, 'mantenimiento', ?, 'Enviado a reparación')")
                   ->execute([$id, $u['id']]);
            }
            salida(['ok' => true]);
        }
    }

    if (preg_match('#^/bienes/(\d+)/traslado$#', $r, $mt) && $m === 'POST') {
        exige($escribe);
        $id = (int)$mt[1]; $destino = (int)($in['area_destino'] ?? 0);
        $db->beginTransaction();
        try {
            $s = $db->prepare('SELECT area_id, estado FROM bienes WHERE id = ? FOR UPDATE'); $s->execute([$id]);
            $cur = $s->fetch();
            if (!$cur || $cur['estado'] === 'baja') { $db->rollBack(); falla('Bien no disponible', 404); }
            if (!$destino || $destino === (int)$cur['area_id']) { $db->rollBack(); falla('Elige un área distinta a la actual', 400); }
            $db->prepare('UPDATE bienes SET area_id = ?, responsable = COALESCE(?, responsable) WHERE id = ?')
               ->execute([$destino, texto($in['responsable'] ?? null), $id]);
            $db->prepare("INSERT INTO movimientos (bien_id, tipo, area_origen, area_destino, usuario_id, observacion) VALUES (?, 'traslado', ?, ?, ?, ?)")
               ->execute([$id, $cur['area_id'], $destino, $u['id'], texto($in['observacion'] ?? null)]);
            $db->commit();
        } catch (Throwable $e) { if ($db->inTransaction()) $db->rollBack(); throw $e; }
        salida(['ok' => true]);
    }

    if (preg_match('#^/bienes/(\d+)/baja$#', $r, $mt) && $m === 'POST') {
        exige(['admin']);
        $id = (int)$mt[1]; $motivo = texto($in['observacion'] ?? null);
        if (!$motivo) falla('Indica el motivo de la baja', 400);
        $s = $db->prepare("UPDATE bienes SET estado = 'baja' WHERE id = ? AND estado <> 'baja'"); $s->execute([$id]);
        if (!$s->rowCount()) falla('Bien no encontrado o ya dado de baja', 404);
        $db->prepare("INSERT INTO movimientos (bien_id, tipo, usuario_id, observacion) VALUES (?, 'baja', ?, ?)")->execute([$id, $u['id'], $motivo]);
        salida(['ok' => true]);
    }

    // ---------- Reportes ----------
    if ($r === '/reportes/resumen' && $m === 'GET') {
        exige();
        $estado = $db->query('SELECT estado, COUNT(*) AS n FROM bienes GROUP BY estado ORDER BY estado')->fetchAll();
        $area = $db->query("SELECT a.nombre, COUNT(b.id) AS n FROM areas a
                            LEFT JOIN bienes b ON b.area_id = a.id AND b.estado <> 'baja' GROUP BY a.id, a.nombre ORDER BY n DESC")->fetchAll();
        $act = $db->query("SELECT COUNT(*) AS n, COALESCE(SUM(valor), 0) AS valor FROM bienes WHERE estado <> 'baja'")->fetch();
        salida(['por_estado' => $estado, 'por_area' => $area, 'activos' => $act]);
    }

    if ($r === '/reportes/inventario.csv' && $m === 'GET') {
        exige();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="inventario.csv"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['Código', 'Descripción', 'Categoría', 'Área', 'Responsable', 'Estado', 'Valor', 'Fecha adquisición']);
        foreach ($db->query(SEL . ' ORDER BY b.codigo') as $b) {
            fputcsv($out, [$b['codigo'], $b['descripcion'], $b['categoria'], $b['area'], $b['responsable'], $b['estado'], $b['valor'], $b['fecha_adquisicion']]);
        }
        exit;
    }

    falla('Ruta no encontrada', 404);
} catch (Throwable $e) {
    error_log('SIRBE: ' . $e->getMessage());
    falla('Error interno del servidor', 500);
}
