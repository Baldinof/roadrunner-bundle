<?php

declare(strict_types=1);

namespace Tests\Baldinof\RoadRunnerBundle\EventListener;

use Baldinof\RoadRunnerBundle\Integration\Symfony\StreamedResponseListener;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Spiral\RoadRunner\Environment\Mode;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\EventListener\StreamedResponseListener as SymfonyStreamedResponseListener;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

class StreamedResponseListenerTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @testWith [ true, false ]
     *           [ false, true ]
     */
    public function test_it_calls_the_decorated_listener_if_needed(bool $rrEnabled, bool $responseShouldBeSent): void
    {
        $listener = new StreamedResponseListener(new SymfonyStreamedResponseListener(), $rrEnabled ? Mode::MODE_HTTP : null);

        $responseSent = false;

        $listener->onKernelResponse($this->responseEvent(function () use (&$responseSent) {
            $responseSent = true;
        }));

        $this->assertEquals($responseShouldBeSent, $responseSent);
    }

    public function test_it_registers_listener_with_the_same_priority()
    {
        $providedEvents = StreamedResponseListener::getSubscribedEvents();
        $symfonyProvidedEvents = SymfonyStreamedResponseListener::getSubscribedEvents();

        $this->assertEquals(
            $symfonyProvidedEvents[KernelEvents::RESPONSE][1],
            $providedEvents[KernelEvents::RESPONSE][1]
        );
    }

    private function responseEvent(callable $streamedResponseCallback): ResponseEvent
    {
        return new ResponseEvent(
            $this->prophesize(HttpKernelInterface::class)->reveal(),
            Request::create('http://example.org'),
            HttpKernelInterface::MASTER_REQUEST,
            new StreamedResponse($streamedResponseCallback)
        );
    }
}
