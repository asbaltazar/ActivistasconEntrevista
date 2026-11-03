<?php

/**
 * Front Controller para Hostinger en: public_html/activistas/api/index.php
 * Conecta con la carpeta hermana public_html/activistas/backend/
 */

use CodeIgniter\Boot;
use Config\Paths;

$minPhpVersion = '8.2';
if (version_compare(PHP_VERSION, $minPhpVersion, '<')) {
    header('HTTP/1.1 503 Service Unavailable.', true, 503);
    echo "Your PHP version must be {$minPhpVersion} or higher. Current version: " . PHP_VERSION;
    exit(1);
}

define('FCPATH', __DIR__ . DIRECTORY_SEPARATOR);

if (getcwd() . DIRECTORY_SEPARATOR !== FCPATH) {
    chdir(FCPATH);
}

// api/ y backend/ están al mismo nivel dentro de public_html/activistas/
require FCPATH . '../backend/app/Config/Paths.php';

$paths = new Paths();

require $paths->systemDirectory . '/Boot.php';

exit(Boot::bootWeb($paths));
