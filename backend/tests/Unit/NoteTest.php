<?php
declare(strict_types=1);
namespace App\Tests\Unit;
use App\Notes\Domain\Note;
use PHPUnit\Framework\TestCase;
final class NoteTest extends TestCase
{
    public function testCreatesTrimmedNoteWithUuidAndUtcDates(): void
    {
        $note = Note::create('  Привет  ', '  Мысли  ');
        $data = $note->toArray();
        self::assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $note->id);
        self::assertSame('Привет', $data['title']); self::assertSame('Мысли', $data['content']);
        self::assertSame($data['createdAt'], $data['updatedAt']);
    }
    public function testUpdatePreservesIdAndCreationTime(): void
    {
        $note = Note::create('Первая', 'Текст'); $before = $note->toArray();
        $note->update('  Вторая  ', ''); $after = $note->toArray();
        self::assertSame($before['id'], $after['id']); self::assertSame($before['createdAt'], $after['createdAt']);
        self::assertSame('Вторая', $after['title']); self::assertSame('', $after['content']);
        self::assertGreaterThanOrEqual($before['updatedAt'], $after['updatedAt']);
    }
    public function testRejectsBlankTitle(): void
    { $this->expectException(\DomainException::class); Note::create('   ', ''); }
    public function testUnicodeLimitUsesCharacters(): void
    {
        self::assertSame(str_repeat('я', 200), Note::create(str_repeat('я', 200), '')->toArray()['title']);
        $this->expectException(\DomainException::class); Note::create(str_repeat('я', 201), '');
    }
    public function testRejectsLongContent(): void
    { $this->expectException(\DomainException::class); Note::create('Название', str_repeat('я', 10001)); }
    public function testInvalidUpdateLeavesStateUnchanged(): void
    {
        $note = Note::create('До', 'Текст'); $before = $note->toArray();
        try { $note->update('', 'Изменение'); self::fail('Expected domain exception'); } catch (\DomainException) {}
        self::assertSame($before, $note->toArray());
    }
}
