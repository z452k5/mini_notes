<?php
declare(strict_types=1);
namespace App\Notes\Http;
use App\Notes\Application\NoteService;
use App\Notes\Domain\Note;
use App\Shared\Http\ValidationException;
use Symfony\Component\HttpFoundation\{JsonResponse, Request, Response};
use Symfony\Component\HttpKernel\Exception\{BadRequestHttpException, UnsupportedMediaTypeHttpException};
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;
#[Route('/api/notes')]
#[\Symfony\Component\HttpKernel\Attribute\AsController]
final readonly class NoteController
{
    public function __construct(private NoteService $notes, private ValidatorInterface $validator) {}
    #[Route('', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        if (array_diff(array_keys($request->query->all()), ['limit','offset'])) {
            throw new BadRequestHttpException('Допустимые параметры: limit, offset.');
        }
        $limit = $this->integer($request->query->all()['limit'] ?? '100', 1, 100, 'limit');
        $offset = $this->integer($request->query->all()['offset'] ?? '0', 0, 1000000, 'offset');
        return new JsonResponse($this->notes->list($limit, $offset));
    }
    #[Route('', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = $this->payload($request); $note = $this->notes->create($data['title'], $data['content']);
        return new JsonResponse($note->toArray(), 201, ['Location'=>'/api/notes/'.$note->id]);
    }
    #[Route('/{id}', methods: ['GET'])]
    public function get(string $id): JsonResponse
    { $this->id($id); return new JsonResponse($this->notes->get($id)->toArray()); }
    #[Route('/{id}', methods: ['PUT'])]
    public function update(string $id, Request $request): JsonResponse
    {
        $this->id($id); $data = $this->payload($request);
        return new JsonResponse($this->notes->update($id, $data['title'], $data['content'])->toArray());
    }
    #[Route('/{id}', methods: ['DELETE'])]
    public function delete(string $id): Response
    { $this->id($id); $this->notes->delete($id); return new Response('', 204); }
    private function id(string $id): void
    {
        if (count($this->validator->validate($id, new Assert\Uuid(versions: [4])))) {
            throw new BadRequestHttpException('id должен быть UUID v4.');
        }
    }
    private function integer(mixed $value, int $min, int $max, string $field): int
    {
        if (!is_string($value) || !preg_match('/^(0|[1-9][0-9]*)$/D', $value) || strlen($value) > 7 || (int)$value < $min || (int)$value > $max) {
            throw new BadRequestHttpException("$field должен быть целым числом от $min до $max.");
        }
        return (int)$value;
    }
    private function payload(Request $request): array
    {
        if (strtolower(trim(explode(';', $request->headers->get('Content-Type', ''))[0])) !== 'application/json') {
            throw new UnsupportedMediaTypeHttpException('Требуется Content-Type: application/json.');
        }
        try { $object = json_decode($request->getContent(), false, 512, JSON_THROW_ON_ERROR); }
        catch (\JsonException) { throw new BadRequestHttpException('Некорректный JSON.'); }
        if (!$object instanceof \stdClass) { throw new BadRequestHttpException('Тело запроса должно быть JSON-объектом.'); }
        $data = (array)$object;
        $errors = $this->validator->validate($data, new Assert\Collection(fields: [
            'title'=>new Assert\Sequentially([new Assert\Type('string', message: 'Заголовок должен быть строкой.'), new Assert\NotBlank(message: 'Введите заголовок.', normalizer: 'trim'), new Assert\Length(max: Note::TITLE_MAX_LENGTH, maxMessage: 'Заголовок должен содержать не более {{ limit }} символов.')]),
            'content'=>new Assert\Sequentially([new Assert\Type('string', message: 'Текст должен быть строкой.'), new Assert\NotNull(message: 'Текст должен быть строкой.'), new Assert\Length(max: Note::CONTENT_MAX_LENGTH, maxMessage: 'Текст должен содержать не более {{ limit }} символов.')]),
        ], missingFieldsMessage: 'Обязательное поле отсутствует.', extraFieldsMessage: 'Неизвестное поле.'));
        if (count($errors)) {
            $fields = [];
            foreach ($errors as $error) { $fields[trim($error->getPropertyPath(), '[]')][] = $error->getMessage(); }
            throw new ValidationException($fields);
        }
        return $data;
    }
}
