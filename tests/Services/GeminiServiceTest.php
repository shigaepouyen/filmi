<?php
namespace App\Tests\Services;

use App\Services\GeminiService;
use App\Services\LlmException;
use PHPUnit\Framework\TestCase;

class GeminiServiceTest extends TestCase
{
    private function reply(string $text): string
    {
        return json_encode(['candidates' => [['content' => ['parts' => [['text' => $text]]]]]]);
    }

    public function testNotConfiguredWithoutKeyAndNeverCallsTheTransport(): void
    {
        $service = new GeminiService('', 'm', fn () => throw new \LogicException('appel inattendu'));

        $this->assertFalse($service->isConfigured());
        $this->expectException(LlmException::class);
        $service->generateJson('s', 'u', []);
    }

    public function testKeyTravelsInTheHeaderNotInTheUrlAndSchemaIsSent(): void
    {
        $seen = [];
        $service = new GeminiService('cle-secrete-123', 'gemini-test', function (string $url, array $headers, string $body) use (&$seen): string {
            $seen = compact('url', 'headers', 'body');
            return $this->reply('{"suggestions":[]}');
        });

        $result = $service->generateJson('système', 'utilisateur', ['type' => 'object']);

        $this->assertSame(['suggestions' => []], $result);
        $this->assertStringNotContainsString('cle-secrete-123', $seen['url']);
        $this->assertStringContainsString('gemini-test:generateContent', $seen['url']);
        $this->assertContains('x-goog-api-key: cle-secrete-123', $seen['headers']);
        $body = json_decode($seen['body'], true);
        $this->assertSame('application/json', $body['generationConfig']['responseMimeType']);
        $this->assertSame(['type' => 'object'], $body['generationConfig']['responseSchema']);
        $this->assertSame('utilisateur', $body['contents'][0]['parts'][0]['text']);
    }

    public function testTransportFailureNeverLeaksTheKey(): void
    {
        $service = new GeminiService('cle-secrete-123', 'm', fn () => throw new \RuntimeException('echec sur cle-secrete-123'));

        try {
            $service->generateJson('s', 'u', []);
            $this->fail('exception attendue');
        } catch (LlmException $e) {
            $this->assertStringNotContainsString('cle-secrete-123', $e->getMessage());
        }
    }

    public function testUnusableAnswersRaiseAClearError(): void
    {
        foreach (['pas du json', json_encode(['candidates' => []]), $this->reply('pas du json non plus'), json_encode(['promptFeedback' => ['blockReason' => 'SAFETY']])] as $raw) {
            $service = new GeminiService('k', 'm', fn () => $raw);
            try {
                $service->generateJson('s', 'u', []);
                $this->fail('exception attendue pour : ' . $raw);
            } catch (LlmException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testFallsBackToTheSecondModelWhenTheFirstIsOverloaded(): void
    {
        $urls = [];
        $service = new GeminiService('k', 'principal', function (string $url) use (&$urls): string {
            $urls[] = $url;
            if (count($urls) === 1) {
                throw new LlmException('Gemini a répondu 503.', 503);
            }
            return $this->reply('{"suggestions":[]}');
        }, 'repli');

        $this->assertSame(['suggestions' => []], $service->generateJson('s', 'u', []));
        $this->assertCount(2, $urls);
        $this->assertStringContainsString('repli:generateContent', $urls[1]);
    }

    public function testDoesNotRetryOnARefusal(): void
    {
        $calls = 0;
        $service = new GeminiService('k', 'principal', function () use (&$calls): string {
            $calls++;
            throw new LlmException('Gemini a refusé la requête (403).');
        }, 'repli');

        try {
            $service->generateJson('s', 'u', []);
            $this->fail('exception attendue');
        } catch (LlmException) {
            $this->assertSame(1, $calls);
        }
    }
}
