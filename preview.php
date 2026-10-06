<?php
/**
 * preview.php
 * Puerta de acceso para que el equipo (desarrollo, diseño, marketing) pueda ver
 * el sitio real (index.php y el resto) mientras MODO_COMINGSOON está activo
 * en control_lanzamiento.php.
 *
 * Flujo:
 *   1. Entras a preview.php
 *   2. Ingresas la clave (la misma definida en PREVIEW_TOKEN, dentro de control_lanzamiento.php)
 *   3. Si es correcta, queda autorizado en tu sesión y te redirige a index.php
 *   4. Mientras dure la sesión del navegador, ves el sitio real en vez de comingsoon.php
 */
require_once __DIR__ . '/control_lanzamiento.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $clave = $_POST['clave'] ?? '';

    if (hash_equals(PREVIEW_TOKEN, $clave)) {
        $_SESSION['preview_autorizado'] = true;
        header('Location: index.php');
        exit;
    }

    $error = 'Clave incorrecta.';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Vista previa — Rentplace</title>
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@700;800&family=Manrope:wght@500;600;700&display=swap" rel="stylesheet">
<style>
  :root{ --ink:#23272E; --primary:#4A7182; --primary-dark:#395A69; --line:#E3E7EA; --muted:#6B7280; }
  *{ box-sizing:border-box; }
  body{ margin:0; min-height:100vh; display:flex; align-items:center; justify-content:center; background:#FAFAF9; font-family:'Manrope',sans-serif; color:var(--ink); }
  .card{ width:100%; max-width:360px; background:#fff; border:1px solid var(--line); border-radius:20px; padding:36px 32px; box-shadow:0 20px 50px -30px rgba(35,39,46,0.25); }
  .brand{ display:flex; align-items:center; gap:9px; margin-bottom:22px; }
  .brand span{ font-family:'Sora',sans-serif; font-weight:800; font-size:19px; }
  h1{ font-family:'Sora',sans-serif; font-size:18px; margin:0 0 6px; }
  p.sub{ font-size:13.5px; color:var(--muted); margin:0 0 22px; line-height:1.5; }
  input{ width:100%; padding:13px 14px; border:1px solid var(--line); border-radius:11px; font-size:14.5px; font-family:'Manrope'; margin-bottom:14px; }
  input:focus{ outline:none; border-color:var(--primary); }
  button{ width:100%; background:var(--primary); color:#fff; border:none; padding:13px; border-radius:11px; font-size:14.5px; font-weight:700; cursor:pointer; font-family:'Manrope'; }
  button:hover{ background:var(--primary-dark); }
  .error{ background:#FBEAEA; color:#B23B3B; font-size:13px; font-weight:600; padding:10px 12px; border-radius:10px; margin-bottom:14px; }
</style>
</head>
<body>
  <div class="card">
    <div class="brand">
      <svg width="28" height="28" viewBox="0 0 34 34"><path d="M17 2 C10 2 5 7 5 14 C5 22 17 32 17 32 C17 32 29 22 29 14 C29 7 24 2 17 2 Z" fill="#4A7182"/><circle cx="17" cy="14" r="5" fill="#C98A5C"/></svg>
      <span>Rentplace</span>
    </div>
    <h1>Vista previa del equipo</h1>
    <p class="sub">El sitio público está en modo "muy pronto". Ingresa la clave de vista previa para ver el sitio real.</p>

    <?php if ($error): ?>
      <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="post">
      <input type="password" name="clave" placeholder="Clave de vista previa" autofocus required>
      <button type="submit">Entrar</button>
    </form>
  </div>
</body>
</html>