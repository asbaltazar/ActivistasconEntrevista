<?php

declare(strict_types=1);

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('/', 'Home::index');

$routes->options('(:any)', static function () {
    return service('response')->setStatusCode(204);
});

$registerV1Routes = static function (RouteCollection $routes): void {
    // Autenticación y catálogos/servicios de apoyo
    $routes->post('auth/login', 'AuthController::login');
    $routes->get('catalogs', 'CatalogsController::index');
    $routes->post('ocr/ine', 'CatalogsController::ocrIne');
    $routes->post('geocode', 'CatalogsController::geocode');

    // Rutas autenticadas (Activista y Admin)
    $routes->group('', ['filter' => 'auth'], static function (RouteCollection $routes) {
        $routes->post('auth/logout', 'AuthController::logout');
        $routes->post('auth/password', 'AuthController::changePassword');

        $routes->get('me', 'AuthController::me');
        $routes->get('me/progress', 'AuthController::progress');
        $routes->get('me/talents', 'SyncController::myTalents');
        $routes->put('me/talents/(:segment)', 'SyncController::updateMyTalent/$1');

        $routes->post('sync/talents', 'SyncController::syncTalents');
        $routes->post('talents/(:segment)/files', 'SyncController::uploadFiles/$1');
    });

    // Rutas del Panel de Control (superadmin y consulta)
    $routes->group('admin', ['filter' => 'auth:superadmin,consulta'], static function (RouteCollection $routes) {
        // Activistas (RF-P1)
        $routes->get('activists', 'AdminActivistsController::index');
        $routes->post('activists', 'AdminActivistsController::create');
        $routes->put('activists/(:num)', 'AdminActivistsController::update/$1');
        $routes->delete('activists/(:num)', 'AdminActivistsController::delete/$1');
        $routes->post('activists/(:num)/reset-password', 'AdminActivistsController::resetPassword/$1');
        $routes->post('activists/(:num)/revoke-devices', 'AdminActivistsController::revokeDevices/$1');

        // Talentos (RF-P2)
        $routes->get('talents', 'AdminTalentsController::index');
        $routes->get('talents/(:num)', 'AdminTalentsController::show/$1');
        $routes->put('talents/(:num)', 'AdminTalentsController::update/$1');
        $routes->delete('talents/(:num)', 'AdminTalentsController::delete/$1');
        $routes->post('talents/merge', 'AdminTalentsController::merge');

        // Archivos autenticados (RF-P2 / RNF Imágenes fuera de public_html)
        $routes->get('files/(:num)', 'AdminTalentsController::serveFile/$1');

        // Dashboard, Mapas y Reportes (RF-P3, RF-P4, RF-P5)
        $routes->get('dashboard', 'AdminReportsController::dashboard');
        $routes->get('reports/(:segment)', 'AdminReportsController::reports/$1');
        $routes->get('map/points', 'AdminReportsController::mapPoints');

        // Exportaciones (RF-P6)
        $routes->get('exports', 'AdminReportsController::listExports');
        $routes->post('exports', 'AdminReportsController::createExport');
        $routes->get('exports/(:num)', 'AdminReportsController::downloadExport/$1');

        // Auditoría y Configuración (RF-P9, RF-P10)
        $routes->get('audit', 'AdminReportsController::audit');
        $routes->get('settings', 'AdminReportsController::getSettings');
        $routes->put('settings', 'AdminReportsController::updateSettings');
    });
};

$routes->group('api/v1', ['namespace' => 'App\Controllers\Api'], $registerV1Routes);
$routes->group('v1', ['namespace' => 'App\Controllers\Api'], $registerV1Routes);
