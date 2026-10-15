<?php
declare(strict_types=1);
namespace App\Notes\Domain;
interface NoteRepository
{
    public function find(string $id): ?Note;
    /** @return list<Note> */
    public function all(int $limit, int $offset): array;
    public function count(): int;
    public function save(Note $note): void;
    public function delete(Note $note): void;
}
