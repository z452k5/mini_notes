<?php
declare(strict_types=1);
namespace App\Notes\Infrastructure;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
#[AsCommand(name: 'app:migrate', description: 'Create the notes schema idempotently')]
final class MigrateCommand extends Command
{
    public function __construct(private readonly Connection $db) { parent::__construct(); }
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->db->executeStatement(file_get_contents(dirname(__DIR__, 3).'/migrations/001_create_notes.sql'));
        $output->writeln('Schema ready.'); return Command::SUCCESS;
    }
}
