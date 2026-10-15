<?php
declare(strict_types=1);
namespace App\Notes\Infrastructure;
use App\Notes\Domain\{Note, NoteRepository};
use Doctrine\DBAL\{Connection, ParameterType};
final readonly class MysqlNoteRepository implements NoteRepository
{
    public function __construct(private Connection $db) {}
    public function find(string $id): ?Note
    { $row = $this->db->fetchAssociative('SELECT * FROM notes WHERE id = ?', [$id]); return $row ? $this->hydrate($row) : null; }
    public function all(int $limit, int $offset): array
    {
        return array_map($this->hydrate(...), $this->db->fetchAllAssociative(
            'SELECT * FROM notes ORDER BY updated_at DESC, id DESC LIMIT ? OFFSET ?',
            [$limit, $offset], [ParameterType::INTEGER, ParameterType::INTEGER]));
    }
    public function count(): int { return (int) $this->db->fetchOne('SELECT COUNT(*) FROM notes'); }
    public function save(Note $note): void
    {
        $data = $note->toArray();
        $this->db->executeStatement('INSERT INTO notes (id,title,content,created_at,updated_at) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE title=VALUES(title),content=VALUES(content),updated_at=VALUES(updated_at)',
            [$note->id, $data['title'], $data['content'], $this->date($data['createdAt']), $this->date($data['updatedAt'])]);
    }
    public function delete(Note $note): void { $this->db->delete('notes', ['id'=>$note->id]); }
    private function date(string $date): string { return (new \DateTimeImmutable($date))->format('Y-m-d H:i:s.u'); }
    private function hydrate(array $row): Note
    { return Note::restore($row['id'], $row['title'], $row['content'], new \DateTimeImmutable($row['created_at'], new \DateTimeZone('UTC')), new \DateTimeImmutable($row['updated_at'], new \DateTimeZone('UTC'))); }
}
