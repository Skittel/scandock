<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->addPlaceholder('pid', \App\Libraries\PublicId::PATTERN);

$routes->get('/', 'Home::index');
$routes->get('branding/logo', 'Branding::logo', ['filter' => 'tenant:any']);

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

// Personal settings.
$routes->post('account/theme', 'Account::theme', ['filter' => ['tenant', 'auth']]);

// Scans of the signed-in user.
$routes->group('scans', ['filter' => ['tenant', 'auth']], static function ($routes) {
    $routes->get('/', 'Scans::index');
    $routes->post('collapsed', 'Scans::saveCollapsed');
    $routes->post('view', 'Scans::saveView');
    $routes->post('delete', 'Scans::deleteSelected');
    $routes->get('status', 'Scans::status');
    $routes->get('(:pid)/view', 'Scans::view/$1');
    $routes->get('(:pid)/download', 'Scans::download/$1');
    $routes->get('(:pid)/thumbnail', 'Scans::thumbnail/$1');
    $routes->post('(:pid)/delete', 'Scans::delete/$1');
});

// Administration.
$routes->group('admin', ['filter' => ['tenant', 'auth', 'admin'], 'namespace' => 'App\Controllers\Admin'], static function ($routes) {
    $routes->get('ftp', 'FtpAccounts::index');
    $routes->post('ftp', 'FtpAccounts::create');
    $routes->get('ftp/(:pid)', 'FtpAccounts::show/$1');
    $routes->post('ftp/(:pid)/password', 'FtpAccounts::regeneratePassword/$1');
    $routes->get('ftp/(:pid)/password', 'FtpAccounts::revealPassword/$1');
    $routes->post('ftp/(:pid)/toggle', 'FtpAccounts::toggle/$1');
    $routes->post('ftp/(:pid)/visibility', 'FtpAccounts::visibility/$1');
    $routes->get('directory', 'Directory::search');
    $routes->post('ftp/(:pid)/delete', 'FtpAccounts::delete/$1');
    $routes->post('ftp/(:pid)/members', 'FtpAccounts::addMember/$1');
    $routes->post('ftp/(:pid)/members/(:pid)/delete', 'FtpAccounts::removeMember/$1/$2');
    $routes->get('settings', 'Settings::index');
    $routes->post('settings', 'Settings::save');
    $routes->post('settings/secret', 'Settings::renewSecret');
    $routes->post('settings/branding', 'Settings::saveBranding');
    $routes->post('settings/logo/delete', 'Settings::deleteLogo');
});
