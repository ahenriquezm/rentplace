<?php
/**
 * assets.php
 * asset('index.css') -> "index.css?v=1760000000"
 * Agrega la fecha de modificación del archivo a la URL, para que el navegador
 * descargue la versión nueva cada vez que se sube un CSS o JS actualizado
 * (sin esto, puede seguir usando una copia antigua guardada en caché).
 */
function asset(string $archivo): string
{
    $ruta = __DIR__ . '/' . $archivo;
    $version = is_file($ruta) ? '?v=' . filemtime($ruta) : '';
    return htmlspecialchars($archivo . $version, ENT_QUOTES);
}
