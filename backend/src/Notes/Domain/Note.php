<?php
declare(strict_types=1);
namespace App\Notes\Domain;
final class Note
{
    public function __construct(
        public readonly string $id,
        private string $title,
        private string $content,
        public readonly \DateTimeImmutable $createdAt,
        private \DateTimeImmutable $updatedAt,
    ) { $this->assertValid($title, $content); }

    public static function create(string $title, string $content): self
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $hex = bin2hex(random_bytes(16));
        $id = substr($hex, 0, 8).'-'.substr($hex, 8, 4).'-4'.substr($hex, 13, 3).'-'.dechex((hexdec($hex[16]) & 3) | 8).substr($hex, 17, 3).'-'.substr($hex, 20);
        return new self($id, trim($title), trim($content), $now, $now);
    }
    public function update(string $title, string $content): void
    {
        $title = trim($title); $content = trim($content);
        $this->assertValid($title, $content);
        $this->title = $title; $this->content = $content;
        $this->updatedAt = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }
    private function assertValid(string $title, string $content): void
    {
        if (trim($title) === '' || mb_strlen($title) > 200 || mb_strlen($content) > 10000) {
            throw new \DomainException('Заголовок: от 1 до 200 символов. Текст: до 10000 символов.');
        }
    }
    public function toArray(): array
    {
        return ['id'=>$this->id, 'title'=>$this->title, 'content'=>$this->content,
            'createdAt'=>$this->createdAt->format('Y-m-d\TH:i:s.u\Z'), 'updatedAt'=>$this->updatedAt->format('Y-m-d\TH:i:s.u\Z')];
    }
}
