<?php
declare(strict_types=1);
namespace App\Notes\Application;
use App\Notes\Domain\{Note, NoteRepository};
final readonly class NoteService
{
    public function __construct(private NoteRepository $notes) {}
    public function list(int $limit, int $offset): array
    {
        return ['data'=>array_map(fn (Note $note) => $note->toArray(), $this->notes->all($limit, $offset)),
            'total'=>$this->notes->count(), 'limit'=>$limit, 'offset'=>$offset];
    }
    public function get(string $id): Note { return $this->notes->find($id) ?? throw new NoteNotFound('Заметка не найдена.'); }
    public function create(string $title, string $content): Note
    { $note = Note::create($title, $content); $this->notes->save($note); return $note; }
    public function update(string $id, string $title, string $content): Note
    { $note = $this->get($id); $note->update($title, $content); $this->notes->save($note); return $note; }
    public function delete(string $id): void { $this->notes->delete($this->get($id)); }
}
