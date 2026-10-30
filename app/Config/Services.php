<?php

namespace Config;

use CodeIgniter\Config\BaseService;

/**
 * Services Configuration file.
 *
 * Services are simply other classes/libraries that the system uses
 * to do its job. This is used by CodeIgniter to allow the core of the
 * framework to be swapped out easily without affecting the usage within
 * the rest of your application.
 *
 * This file holds any application-specific services, or service overrides
 * that you might need. An example has been included with the general
 * method format you should use for your service methods. For more examples,
 * see the core Services file at system/Config/Services.php.
 */
class Services extends BaseService
{
    /*
     * public static function example($getShared = true)
     * {
     *     if ($getShared) {
     *         return static::getSharedInstance('example');
     *     }
     *
     *     return new \CodeIgniter\Example();
     * }
     */

    public static function tenancy(bool $getShared = true): \App\Libraries\Tenancy
    {
        if ($getShared) {
            return static::getSharedInstance('tenancy');
        }

        return new \App\Libraries\Tenancy(config(ScanKai::class));
    }

    public static function entra(bool $getShared = true): \App\Libraries\EntraOidc
    {
        if ($getShared) {
            return static::getSharedInstance('entra');
        }

        return new \App\Libraries\EntraOidc();
    }

    public static function ftpAccountManager(bool $getShared = true): \App\Libraries\FtpAccountManager
    {
        if ($getShared) {
            return static::getSharedInstance('ftpAccountManager');
        }

        return new \App\Libraries\FtpAccountManager(config(ScanKai::class));
    }

    public static function scanStore(bool $getShared = true): \App\Libraries\ScanStore
    {
        if ($getShared) {
            return static::getSharedInstance('scanStore');
        }

        return new \App\Libraries\ScanStore(config(ScanKai::class));
    }

    public static function directory(bool $getShared = true): \App\Libraries\DirectoryClient
    {
        if ($getShared) {
            return static::getSharedInstance('directory');
        }

        return new \App\Libraries\DirectoryClient();
    }

    public static function scanAccess(bool $getShared = true): \App\Libraries\ScanAccess
    {
        if ($getShared) {
            return static::getSharedInstance('scanAccess');
        }

        return new \App\Libraries\ScanAccess();
    }
}
