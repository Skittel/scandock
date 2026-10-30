<?php

namespace App\Controllers;

use App\Models\TenantModel;
use App\Models\UserModel;
use CodeIgniter\Controller;
use CodeIgniter\HTTP\CLIRequest;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * Common helpers for all controllers: current tenant, current user and
 * rendering inside the layout.
 */
abstract class BaseController extends Controller
{
    /**
     * @var CLIRequest|IncomingRequest
     */
    protected $request;

    private ?array $currentUser = null;

    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        $this->helpers = ['form', 'url', 'number'];

        parent::initController($request, $response, $logger);
    }

    protected function tenant(): ?array
    {
        return service('tenancy')->current();
    }

    protected function currentUser(): ?array
    {
        if ($this->currentUser === null && session('user_id')) {
            $this->currentUser = model(UserModel::class)->find(session('user_id'));
        }

        return $this->currentUser;
    }

    protected function isAdmin(): bool
    {
        $tenant = $this->tenant();
        $user   = $this->currentUser();

        return $tenant !== null && $user !== null && service('scanAccess')->isAdmin($tenant, $user);
    }

    /**
     * Renders a view with the data every page needs.
     */
    protected function render(string $view, array $data = []): string
    {
        $tenant  = $this->tenant();
        $isAdmin = $this->isAdmin();

        $user = $this->currentUser();

        return view($view, $data + [
            'tenant'          => $tenant,
            'user'            => $user,
            'isAdmin'         => $isAdmin,
            'secretDaysLeft'  => $isAdmin && $tenant !== null ? TenantModel::secretDaysLeft($tenant) : null,
            'ftpBlocked'      => self::ftpBlocked(),
            'groupsStale'     => $user !== null && UserModel::groupsStale($user),
            'everyoneIsAdmin' => $isAdmin && $tenant !== null && empty($tenant['admin_group_id']),
        ]);
    }

    /**
     * Details of the receive block set by the disk guard, or null.
     */
    protected static function ftpBlocked(): ?array
    {
        $file = config('ScanDock')->ftpBlockedFile;
        if (! is_file($file)) {
            return null;
        }

        return json_decode((string) file_get_contents($file), true) ?: ['since' => date('c', filemtime($file))];
    }
}
