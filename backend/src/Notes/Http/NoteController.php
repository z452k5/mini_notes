<?php
declare(strict_types=1);
namespace App\Notes\Http;
use App\Notes\Application\NoteService;
use Symfony\Component\HttpFoundation\{JsonResponse, Request, Response};
use Symfony\Component\HttpKernel\Exception\{BadRequestHttpException, UnprocessableEntityHttpException, UnsupportedMediaTypeHttpException};
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
        $errors = $this->validator->validate($data, new Assert\Collection([
            'title'=>new Assert\Sequentially([new Assert\Type('string'), new Assert\NotBlank(normalizer: 'trim'), new Assert\Length(max: 200)]),
            'content'=>[new Assert\Type('string'), new Assert\NotNull(), new Assert\Length(max: 10000)],
        ]));
        if (count($errors)) {
            $messages = [];
            foreach ($errors as $error) { $messages[] = $error->getPropertyPath().': '.$error->getMessage(); }
            throw new UnprocessableEntityHttpException(implode(' ', $messages));
        }
        return $data;
    }
}
