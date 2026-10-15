<?php
declare(strict_types=1);
namespace App\Notes\Http;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
#[\Symfony\Component\HttpKernel\Attribute\AsController]
final class HealthController
{
    #[Route('/api/health', methods: ['GET'])]
    public function __invoke(Connection $db): JsonResponse
    { $db->executeQuery('SELECT 1 FROM notes LIMIT 1'); return new JsonResponse(['status'=>'ok']); }
}
