<?php

/**
 * Factory for statistics queue processor
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2023.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Service
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */

namespace FinnaConsole\Command\Util;

use Finna\Statistics\Driver\Redis as RedisDriver;
use Laminas\ServiceManager\Exception\ServiceNotCreatedException;
use Laminas\ServiceManager\Exception\ServiceNotFoundException;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerExceptionInterface as ContainerException;
use Psr\Container\ContainerInterface;

/**
 * Factory for statistics queue processor
 *
 * @category VuFind
 * @package  Service
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class ProcessStatsQueueFactory implements FactoryInterface
{
    /**
     * Create an object
     *
     * @param ContainerInterface $container     Service manager
     * @param string             $requestedName Service being created
     * @param null|array         $options       Extra options (optional)
     *
     * @return object
     *
     * @throws ServiceNotFoundException if unable to resolve the service.
     * @throws ServiceNotCreatedException if an exception is raised when
     * creating a service.
     * @throws ContainerException if any other error occurs
     */
    public function __invoke(
        ContainerInterface $container,
        $requestedName,
        array $options = null
    ) {
        $config = $container->get(\VuFind\Config\PluginManager::class)
            ->get('config')->Statistics ?? null;
        $tableManager = $container->get(\VuFind\Db\Table\PluginManager::class);
        return new $requestedName(
            $container->get(\Finna\Statistics\Driver\PluginManager::class)
                ->get('Database'),
            $this->getConnection($config),
            $config->redis_key_prefix ?? RedisDriver::DEFAULT_KEY_PREFIX,
            ...($options ?? [])
        );
    }

    /**
     * Given a configuration, build the client object.
     *
     * @param \Laminas\Config\Config $config Session configuration
     *
     * @return \Credis_Client
     */
    protected function getConnection(\Laminas\Config\Config $config)
    {
        // Set defaults if nothing set in config file.
        $host = $config->redis_host ?? 'localhost';
        $port = $config->redis_port ?? 6379;
        $timeout = $config->redis_connection_timeout ?? 0.5;
        $password = $config->redis_auth ?? null;
        $username = $config->redis_user ?? null;
        $redisDb = $config->redis_db ?? 0;

        // Create Credis client, the connection is established lazily
        $client = new \Credis_Client(
            $host,
            $port,
            $timeout,
            '',
            $redisDb,
            $password,
            $username
        );
        if ((bool)($config->redis_standalone ?? true)) {
            $client->forceStandalone();
        }
        return $client;
    }
}
