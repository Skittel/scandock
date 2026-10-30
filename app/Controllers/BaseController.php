<?php

namespace App\Controllers;

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
        return view($view, $data + [
            'tenant'  => $this->tenant(),
            'user'    => $this->currentUser(),
            'isAdmin' => $this->isAdmin(),
        ]);
    }
}
