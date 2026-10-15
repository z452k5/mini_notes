<?php
declare(strict_types=1);
namespace App\Tests\Functional;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
final class NotesApiTest extends WebTestCase
{
    public function testFullCrudLifecycle(): void
    {
        $client = self::createClient();
        $client->jsonRequest('POST', '/api/notes', ['title'=>'  PHPUnit заметка  ', 'content'=>'Тест 📝']);
        self::assertResponseStatusCodeSame(201);
        $created = json_decode($client->getResponse()->getContent(), true);
        $url = '/api/notes/'.$created['id'];
        self::assertResponseHeaderSame('Location', $url);
        try {
            self::assertSame('PHPUnit заметка', $created['title']);
            $client->request('GET', $url); self::assertResponseIsSuccessful();
            self::assertSame($created, json_decode($client->getResponse()->getContent(), true));
            $client->request('GET', '/api/notes?limit=100&offset=0'); self::assertResponseIsSuccessful();
            $page = json_decode($client->getResponse()->getContent(), true);
            self::assertContains($created['id'], array_column($page['data'], 'id'));
            $client->jsonRequest('PUT', $url, ['title'=>'Обновлена', 'content'=>'']); self::assertResponseIsSuccessful();
            $updated = json_decode($client->getResponse()->getContent(), true);
            self::assertSame('Обновлена', $updated['title']); self::assertSame('', $updated['content']);
            self::assertSame($created['createdAt'], $updated['createdAt']);
            $client->jsonRequest('PUT', $url, ['title'=>' ', 'content'=>'']); self::assertResponseStatusCodeSame(422);
            $client->jsonRequest('PUT', $url, ['title'=>str_repeat('я',51), 'content'=>str_repeat('📝',201)]);
            self::assertResponseStatusCodeSame(422);
            $fields = json_decode($client->getResponse()->getContent(), true)['error']['fields'];
            self::assertSame(['Заголовок должен содержать не более 50 символов.'], $fields['title']);
            self::assertSame(['Текст должен содержать не более 200 символов.'], $fields['content']);
            $client->request('GET', $url); self::assertSame('Обновлена', json_decode($client->getResponse()->getContent(), true)['title']);
        } finally { $client->request('DELETE', $url); }
        self::assertResponseStatusCodeSame(204); self::assertSame('', $client->getResponse()->getContent());
        $client->request('GET', $url); self::assertResponseStatusCodeSame(404);
        $client->request('DELETE', $url); self::assertResponseStatusCodeSame(404);
    }
    public function testAcceptsExactLimitsAndReturnsFieldErrorsOnOverflow(): void
    {
        $client = self::createClient();
        $client->jsonRequest('POST', '/api/notes', ['title'=>str_repeat('я',50), 'content'=>str_repeat('📝',200)]);
        self::assertResponseStatusCodeSame(201);
        $note = json_decode($client->getResponse()->getContent(), true);
        try {
            self::assertSame(str_repeat('я',50), $note['title']);
            self::assertSame(str_repeat('📝',200), $note['content']);
            $client->jsonRequest('POST', '/api/notes', ['title'=>str_repeat('я',51), 'content'=>str_repeat('📝',201)]);
            self::assertResponseStatusCodeSame(422);
            $fields = json_decode($client->getResponse()->getContent(), true)['error']['fields'];
            self::assertArrayHasKey('title', $fields); self::assertArrayHasKey('content', $fields);
        } finally { $client->request('DELETE', '/api/notes/'.$note['id']); }
    }
    #[DataProvider('invalidRequests')]
    public function testValidation(string $method, string $url, string $body, string $contentType, int $status): void
    {
        $client = self::createClient();
        $client->request($method, $url, [], [], ['CONTENT_TYPE'=>$contentType], $body);
        self::assertResponseStatusCodeSame($status);
        self::assertResponseHeaderSame('Content-Type', 'application/json');
        $error = json_decode($client->getResponse()->getContent(), true);
        self::assertSame($status, $error['error']['status']); self::assertNotEmpty($error['error']['message']);
    }
    public static function invalidRequests(): iterable
    {
        yield 'malformed JSON' => ['POST','/api/notes','{','application/json',400];
        yield 'array JSON' => ['POST','/api/notes','[]','application/json',400];
        yield 'wrong content type' => ['POST','/api/notes','{}','text/plain',415];
        yield 'missing fields' => ['POST','/api/notes','{}','application/json',422];
        yield 'blank title' => ['POST','/api/notes','{"title":" ","content":""}','application/json',422];
        yield 'array title' => ['POST','/api/notes','{"title":[],"content":""}','application/json',422];
        yield 'numeric title' => ['POST','/api/notes','{"title":10,"content":""}','application/json',422];
        yield 'null content' => ['POST','/api/notes','{"title":"t","content":null}','application/json',422];
        yield 'extra field' => ['POST','/api/notes','{"title":"t","content":"","admin":true}','application/json',422];
        yield 'long title' => ['POST','/api/notes',json_encode(['title'=>str_repeat('я',51),'content'=>'']),'application/json',422];
        yield 'long body' => ['POST','/api/notes',json_encode(['title'=>'t','content'=>str_repeat('я',201)]),'application/json',422];
        yield 'invalid limit' => ['GET','/api/notes?limit=0','','application/json',400];
        yield 'negative offset' => ['GET','/api/notes?offset=-1','','application/json',400];
        yield 'array offset' => ['GET','/api/notes?offset[]=0','','application/json',400];
        yield 'unknown query' => ['GET','/api/notes?foo=bar','','application/json',400];
        foreach (['GET','PUT','DELETE'] as $method) {
            yield "invalid id $method" => [$method,'/api/notes/not-uuid','{"title":"t","content":""}','application/json',400];
            yield "missing note $method" => [$method,'/api/notes/00000000-0000-4000-8000-000000000000','{"title":"t","content":""}','application/json',404];
        }
        yield 'method not allowed' => ['PATCH','/api/notes','','application/json',405];
        yield 'unknown route' => ['GET','/api/unknown','','application/json',404];
    }
}
