<?php
declare(strict_types=1);
namespace App\Shared\Infrastructure;
use Doctrine\DBAL\{Connection, DriverManager};
use Doctrine\DBAL\Tools\DsnParser;
final class ConnectionFactory
{
    public static function create(string $url): Connection
    {
        $params = (new DsnParser(['mysql'=>'pdo_mysql']))->parse($url);
        return DriverManager::getConnection($params);
    }
}
