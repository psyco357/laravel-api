<?php

namespace App\Support;

use App\Models\MstConnection;
use Illuminate\Config\Repository;
use Illuminate\Database\DatabaseManager;

class TenantConnectionManager
{
    protected DatabaseManager $db;
    protected Repository $config;

    public function __construct(DatabaseManager $db, Repository $config)
    {
        $this->db = $db;
        $this->config = $config;
    }
    public function centralConnectionName(): string
    {
        return $this->config->get('database.central_connection_name', 'central');
    }

    public function tenantConnectionName(): string
    {
        return $this->config->get('database.tenant_connection_name', 'tenant');
    }

    protected function buildTenantConfig(MstConnection $connection): array
    {
        $template = $this->config->get(
            sprintf('database.connections.%s', $this->tenantConnectionName()),
            []
        );

        return array_merge($template, [
            'driver' => $connection->driver,
            'host' => $connection->host,
            'port' => (string) $connection->port,
            'database' => $connection->db_name,
            'username' => $connection->username,
            'password' => $connection->password,
        ]);
    }

    public function useCentralConnection(): void
    {
        $this->db->setDefaultConnection($this->centralConnectionName());
    }

    public function useTenantConnection(MstConnection $connection): void
    {
        $this->config->set(
            sprintf('database.connections.%s', $this->tenantConnectionName()),
            $this->buildTenantConfig($connection),
        );

        $this->db->purge($this->tenantConnectionName());
        $this->db->setDefaultConnection($this->tenantConnectionName());
        $this->db->reconnect($this->tenantConnectionName());
    }
}
