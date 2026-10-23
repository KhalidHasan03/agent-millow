<?php

declare(strict_types=1);

namespace Tests\Unit\Llm;

use App\Providers\Llm\Adapters\FakeProvider;
use App\Providers\Llm\Contracts\ChatMessage;
use App\Providers\Llm\Contracts\ChatRequest;
use App\Providers\Llm\Contracts\StreamEvent;
use App\Providers\Llm\Exceptions\ProviderException;
use PHPUnit\Framework\TestCase;

final class FakeProviderTest extends TestCase
{
    private FakeProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->provider = new FakeProvider;
    }

    public function test_it_reports_a_stable_name_and_streaming_support(): void
    {
        $this->assertSame('fake', $this->provider->name());
        $this->assertTrue($this->provider->supportsStreaming());
    }

    public function test_completion_is_deterministic_and_derives_from_input(): void
    {
        $request = new ChatRequest('fake-model', [new ChatMessage('user', 'hello world')]);

        $result = $this->provider->complete($request);

        $this->assertSame('[fake:fake-model] hello world', $result->content);
        $this->assertSame('stop', $result->finishReason);
        $this->assertSame('fake', $result->provider);
        $this->assertGreaterThan(0, $result->usage->total());
    }

    public function test_it_rejects_an_empty_request(): void
    {
        $this->expectException(ProviderException::class);

        $this->provider->complete(new ChatRequest('fake-model', []));
    }

    public function test_streaming_emits_deltas_then_done_and_reassembles_to_completion(): void
    {
        $request = new ChatRequest('fake-model', [new ChatMessage('user', 'streaming test')]);

        $buffer = '';
        $done = false;

        foreach ($this->provider->stream($request) as $event) {
            $this->assertInstanceOf(StreamEvent::class, $event);

            if ($event->type === StreamEvent::TYPE_DELTA) {
                $buffer .= $event->text;
            }

            if ($event->type === StreamEvent::TYPE_DONE) {
                $done = true;
                $this->assertNotNull($event->usage);
            }
        }

        $this->assertTrue($done, 'The stream must end with a done event.');
        $this->assertSame('[fake:fake-model] streaming test', $buffer);
    }
}
