<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->get('/', 'Home::index');

// Onboarding via start link (initial setup or re-issued link for an active tenant).
$routes->group('setup', ['filter' => 'tenant:any'], static function ($routes) {
    $routes->get('(:segment)', 'Setup::show/$1');
    $routes->post('(:segment)', 'Setup::save/$1');
    $routes->post('(:segment)/test', 'Setup::test/$1');
});

// Entra ID sign-in.
$routes->group('auth', ['filter' => 'tenant:any'], static function ($routes) {
    $routes->get('login', 'Auth::login');
    $routes->get('callback', 'Auth::callback');
    $routes->post('logout', 'Auth::logout');
});

// Scans of the signed-in user.
$routes->group('scans', ['filter' => ['tenant', 'auth']], static function ($routes) {
    $routes->get('/', 'Scans::index');
    $routes->get('(:num)/view', 'Scans::view/$1');
    $routes->get('(:num)/download', 'Scans::download/$1');
    $routes->get('(:num)/thumbnail', 'Scans::thumbnail/$1');
    $routes->post('(:num)/delete', 'Scans::delete/$1');
});

// Administration.
$routes->group('admin', ['filter' => ['tenant', 'auth', 'admin'], 'namespace' => 'App\Controllers\Admin'], static function ($routes) {
    $routes->get('ftp', 'FtpAccounts::index');
    $routes->post('ftp', 'FtpAccounts::create');
    $routes->get('ftp/(:num)', 'FtpAccounts::show/$1');
    $routes->post('ftp/(:num)/password', 'FtpAccounts::regeneratePassword/$1');
    $routes->post('ftp/(:num)/toggle', 'FtpAccounts::toggle/$1');
    $routes->post('ftp/(:num)/delete', 'FtpAccounts::delete/$1');
    $routes->post('ftp/(:num)/members', 'FtpAccounts::addMember/$1');
    $routes->post('ftp/(:num)/members/(:num)/delete', 'FtpAccounts::removeMember/$1/$2');
    $routes->get('settings', 'Settings::index');
    $routes->post('settings', 'Settings::save');
    $routes->post('settings/secret', 'Settings::renewSecret');
});
