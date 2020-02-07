<?php

namespace Tests\Baldinof\RoadRunnerBundle\Worker;

use Baldinof\RoadRunnerBundle\Event\WorkerStopEvent;
use Baldinof\RoadRunnerBundle\Http\KernelHandler;
use Baldinof\RoadRunnerBundle\Http\MiddlewareStack;
use Baldinof\RoadRunnerBundle\Worker\Configuration;
use Baldinof\RoadRunnerBundle\Worker\Worker;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\NullLogger;
use Spiral\RoadRunner\PSR7Client;
use Spiral\RoadRunner\Worker as RoadrunnerWorker;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\HttpKernel\TerminableInterface;

class WorkerTest extends TestCase
{
    private $worker;
    private $requests;

    /**
     * @var EventDispatcher
     */
    private $eventDispatcher;

    public function setUp(): void
    {
        $this->requests = $requests = new \SplStack();

        $this->roadrunnerWorker = $this->prophesize(RoadrunnerWorker::class);

        $this->psrClient = $this->prophesize(PSR7Client::class);
        $this->psrClient->acceptRequest()->will(function () use ($requests) {
            return $requests->isEmpty() ? null : $requests->pop();
        });

        $this->psrClient->getWorker()->willReturn($this->roadrunnerWorker->reveal());

        $psrFactory = new Psr17Factory();

        $this->eventDispatcher = new EventDispatcher();
        $this->kernel = $this->prophesize(KernelInterface::class)
            ->willImplement(KernelInterface::class)
            ->willImplement(TerminableInterface::class);

        $this->kernel->isDebug()->willReturn(false);
        $this->kernel->boot()->willReturn(null);

        $this->stack = new MiddlewareStack(new KernelHandler($this->kernel->reveal(), new PsrHttpFactory($psrFactory, $psrFactory, $psrFactory, $psrFactory), new HttpFoundationFactory()));

        $this->worker = new Worker(
            $this->kernel->reveal(),
            $this->eventDispatcher,
            new Configuration(false),
            $this->stack,
            new NullLogger(),
            $this->psrClient->reveal()
        );
    }

    public function test_it_throws_on_non_rebootable_kernel()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The worker is configured to reboot the kernel, but the passed kernel does not implement Symfony\Component\HttpKernel\RebootableInterface');

        new Worker(
            $this->prophesize(KernelInterface::class)->reveal(),
            new EventDispatcher(),
            new Configuration(true),
            $this->stack,
            new NullLogger(),
            $this->prophesize(PSR7Client::class)->reveal()
        );
    }

    public function test_it_setup_trusted_proxies_and_hosts()
    {
        $_ENV['TRUSTED_PROXIES'] = '10.0.0.1,10.0.0.2';
        $_ENV['TRUSTED_HOSTS'] = 'example.org,example.com';

        $this->worker->start();

        $this->assertSame(['10.0.0.1', '10.0.0.2'], Request::getTrustedProxies());
        $this->assertSame(['{example.org}i', '{example.com}i'], Request::getTrustedHosts());
        $this->assertSame(Request::HEADER_X_FORWARDED_ALL ^ Request::HEADER_X_FORWARDED_HOST, Request::getTrustedHeaderSet());
    }

    /**
     * @testWith [ "Baldinof\\RoadRunnerBundle\\Event\\WorkerStartEvent" ]
     *           [ "Baldinof\\RoadRunnerBundle\\Event\\WorkerStopEvent" ]
     */
    public function test_it_dispatches_start_and_stop_events($eventName)
    {
        $called = false;
        $this->eventDispatcher->addListener($eventName, function () use (&$called) {
            $called = true;
        });

        $this->worker->start();

        $this->assertTrue($called);
    }

    public function test_it_calls_the_kernel()
    {
        $this->requests->push(new ServerRequest('GET', 'http://example.org/'));

        $response = new Response('hello');

        $this->kernel->handle(Argument::any())
            ->shouldBeCalled()
            ->will(function (array $args) use ($response) {
                $request = $args[0];

                Assert::assertInstanceOf(Request::class, $request);
                Assert::assertSame('http://example.org/', $request->getUri());
                Assert::assertSame('GET', $request->getMethod());

                return $response;
            });

        $this->kernel->terminate(Argument::any(), $response)->shouldBeCalled();

        $this->psrClient->respond(Argument::any())
            ->shouldBeCalled()
            ->will(function (array $args) {
                $psrResponse = $args[0];

                Assert::assertInstanceOf(ResponseInterface::class, $psrResponse);
                Assert::assertSame('hello', (string) $psrResponse->getBody());
            });

        $this->worker->start();
    }

    public function test_an_error_stops_the_worker()
    {
        $this->requests->push(new ServerRequest('GET', 'http://example.org/'));
        $this->requests->push(new ServerRequest('GET', 'http://example.org/'));

        $this->kernel->handle(Argument::any())
            ->shouldBeCalled()
            ->will(function () {
                throw new \RuntimeException('error');
            });

        $called = false;
        $this->eventDispatcher->addListener(WorkerStopEvent::class, function () use (&$called) {
            $called = true;
        });

        $this->roadrunnerWorker->error('Internal server error')->shouldBeCalled();

        $this->worker->start();

        $this->assertTrue($called, WorkerStopEvent::class.' has not been dispatched');

        $this->assertCount(1, $this->requests);
    }

    public function test_an_error_in_debug_mode_shows_the_trace_and_stops_the_worker()
    {
        $this->requests->push(new ServerRequest('GET', 'http://example.org/'));
        $this->requests->push(new ServerRequest('GET', 'http://example.org/'));

        $this->kernel->isDebug()->willReturn(true);

        $this->kernel->handle(Argument::any())
            ->shouldBeCalled()
            ->will(function () {
                throw new \RuntimeException('error in debug');
            });

        $called = false;
        $this->eventDispatcher->addListener(WorkerStopEvent::class, function () use (&$called) {
            $called = true;
        });

        $this->roadrunnerWorker->error(Argument::type('string'))
            ->shouldBeCalled()
            ->will(function (array $args) {
                Assert::assertStringContainsString('error in debug', $args[0]);
            });

        $this->worker->start();

        $this->assertTrue($called, WorkerStopEvent::class.' has not been dispatched');

        $this->assertCount(1, $this->requests);
    }
}
