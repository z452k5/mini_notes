<?php
declare(strict_types=1);
namespace App\Notes\Domain;
final class Note
{
    public const TITLE_MAX_LENGTH = 50;
    public const CONTENT_MAX_LENGTH = 200;

    private function __construct(
        public readonly string $id,
        private string $title,
        private string $content,
        public readonly \DateTimeImmutable $createdAt,
        private \DateTimeImmutable $updatedAt,
    ) {}

    public static function create(string $title, string $content): self
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $hex = bin2hex(random_bytes(16));
        $id = substr($hex, 0, 8).'-'.substr($hex, 8, 4).'-4'.substr($hex, 13, 3).'-'.dechex((hexdec($hex[16]) & 3) | 8).substr($hex, 17, 3).'-'.substr($hex, 20);
        $note = new self($id, trim($title), trim($content), $now, $now);
        $note->assertValid($title, $content);
        return $note;
    }
    /** Restore stored notes without applying new write limits to historical data. */
    public static function restore(string $id, string $title, string $content, \DateTimeImmutable $createdAt, \DateTimeImmutable $updatedAt): self
    { return new self($id, $title, $content, $createdAt, $updatedAt); }

    public function update(string $title, string $content): void
    {
        $title = trim($title); $content = trim($content);
        $this->assertValid($title, $content);
        $this->title = $title; $this->content = $content;
        $this->updatedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }
    private function assertValid(string $title, string $content): void
    {
        if (trim($title) === '' || mb_strlen($title) > self::TITLE_MAX_LENGTH || mb_strlen($content) > self::CONTENT_MAX_LENGTH) {
            throw new \DomainException('Заголовок: от 1 до 50 символов. Текст: до 200 символов.');
        }
    }
    public function toArray(): array
    {
        return ['id'=>$this->id, 'title'=>$this->title, 'content'=>$this->content,
            'createdAt'=>$this->createdAt->format('Y-m-d\TH:i:s.u\Z'), 'updatedAt'=>$this->updatedAt->format('Y-m-d\TH:i:s.u\Z')];
    }
}
