<?php

namespace PromoSeven\UltraMessage\Tests;

use Illuminate\Support\Facades\Http;
use PromoSeven\UltraMessage\UltraMessageClient;
use PromoSeven\UltraMessage\UltraMessageException;

class UltraMessageClientTest extends TestCase
{
    private UltraMessageClient $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = new UltraMessageClient([
            'instance_id' => 'instance123',
            'token'       => 'test-token',
            'timeout'     => 30,
            'enabled'     => true,
        ]);
    }

    public function test_send_text_posts_correct_payload(): void
    {
        Http::fake([
            'api.ultramsg.com/*' => Http::response(['sent' => 'true', 'id' => 'msg1'], 200),
        ]);

        $result = $this->client->sendText('+971501234567', 'Hello World');

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'instance123/messages/chat')
                && $request['to'] === '+971501234567'
                && $request['body'] === 'Hello World'
                && $request['token'] === 'test-token';
        });

        $this->assertEquals(['sent' => 'true', 'id' => 'msg1'], $result);
    }

    public function test_send_text_throws_on_api_error(): void
    {
        Http::fake([
            'api.ultramsg.com/*' => Http::response(['error' => 'invalid token'], 200),
        ]);

        $this->expectException(UltraMessageException::class);
        $this->expectExceptionMessage('invalid token');

        $this->client->sendText('+971501234567', 'Hello');
    }

    public function test_send_text_throws_on_http_failure(): void
    {
        Http::fake([
            'api.ultramsg.com/*' => Http::response([], 500),
        ]);

        $this->expectException(UltraMessageException::class);

        $this->client->sendText('+971501234567', 'Hello');
    }

    public function test_send_returns_early_when_disabled(): void
    {
        Http::fake();

        $client = new UltraMessageClient([
            'instance_id' => 'instance123',
            'token'       => 'test-token',
            'timeout'     => 30,
            'enabled'     => false,
        ]);

        $result = $client->sendText('+971501234567', 'Hello');

        Http::assertNothingSent();
        $this->assertEquals([], $result);
    }

    public function test_send_image_posts_correct_payload(): void
    {
        Http::fake([
            'api.ultramsg.com/*' => Http::response(['sent' => 'true'], 200),
        ]);

        $this->client->sendImage('+971501234567', 'https://example.com/img.jpg', 'Caption');

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'instance123/messages/image')
                && $request['image'] === 'https://example.com/img.jpg'
                && $request['caption'] === 'Caption';
        });
    }

    public function test_send_document_posts_correct_payload(): void
    {
        Http::fake([
            'api.ultramsg.com/*' => Http::response(['sent' => 'true'], 200),
        ]);

        $this->client->sendDocument('+971501234567', 'https://example.com/file.pdf', 'invoice.pdf', 'Your invoice');

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'instance123/messages/document')
                && $request['document'] === 'https://example.com/file.pdf'
                && $request['filename'] === 'invoice.pdf'
                && $request['caption'] === 'Your invoice';
        });
    }

    public function test_send_location_posts_correct_payload(): void
    {
        Http::fake([
            'api.ultramsg.com/*' => Http::response(['sent' => 'true'], 200),
        ]);

        $this->client->sendLocation('+971501234567', 25.197197, 55.2721877, 'Dubai, UAE');

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'instance123/messages/location')
                && $request['lat'] == 25.197197
                && $request['lng'] == 55.2721877
                && $request['address'] === 'Dubai, UAE';
        });
    }
}
