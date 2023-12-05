<?php

declare(strict_types=1);

namespace Tests\Baldinof\RoadRunnerBundle\Reboot;

use Baldinof\RoadRunnerBundle\Event\ForceKernelRebootEvent;
use Baldinof\RoadRunnerBundle\Reboot\OnExceptionRebootStrategy;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\KernelInterface;

class OnExceptionRebootStrategyTest extends TestCase
{
    use ProphecyTrait;

    private $strategy;
    private $dispatcher;
    private $logger;

    public function setUp(): void
    {
        $this->logger = $this->prophesize(LoggerInterface::class);

        $this->strategy = new OnExceptionRebootStrategy([AllowedException::class], $this->logger->reveal());

        $this->dispatcher = new EventDispatcher();
        $this->dispatcher->addSubscriber($this->strategy);
    }

    public function test_it_does_not_reboot_by_default()
    {
        $this->assertFalse($this->strategy->shouldReboot());
    }

    public function test_it_reboot_on_unexpected_exception()
    {
        $this->dispatchException(new \RuntimeException());

        $this->assertTrue($this->strategy->shouldReboot());
    }

    public function test_it_does_not_reboot_on_allowed_exception()
    {
        $this->dispatchException(new AllowedException());

        $this->assertFalse($this->strategy->shouldReboot());
    }

    public function test_it_does_not_reboot_on_allowed_exception_child()
    {
        $this->dispatchException(new ChildAllowedException());

        $this->assertFalse($this->strategy->shouldReboot());
    }

    public function test_clear_reset_the_state()
    {
        $this->dispatchException(new \RuntimeException());
        $this->strategy->clear();

        $this->assertFalse($this->strategy->shouldReboot());

        $this->dispatcher->dispatch(new ForceKernelRebootEvent('something bad happened'));
        $this->strategy->clear();

        $this->assertFalse($this->strategy->shouldReboot());
    }

    public function test_it_can_be_notified_to_force_reboot()
    {
        $this->dispatcher->dispatch(new ForceKernelRebootEvent('something bad happened'));

        $this->assertTrue($this->strategy->shouldReboot());

        $this->logger->debug(Argument::containingString('something bad happened'))->shouldBeCalled();
    }

    private function dispatchException(\Exception $e)
    {
        $event = new ExceptionEvent($this->prophesize(KernelInterface::class)->reveal(), new Request(), KernelInterface::MAIN_REQUEST, $e);
        $this->dispatcher->dispatch($event, KernelEvents::EXCEPTION);
    }
}

class AllowedException extends \RuntimeException
{
}
class ChildAllowedException extends AllowedException
{
}
