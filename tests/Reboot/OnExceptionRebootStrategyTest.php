<?php

namespace Tests\Baldinof\RoadRunnerBundle\Reboot;

use Baldinof\RoadRunnerBundle\Reboot\OnExceptionRebootStrategy;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Log\Test\TestLogger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelInterface;

class OnExceptionRebootStrategyTest extends TestCase
{
    use ProphecyTrait;

    private $strategy;

    public function setUp(): void
    {
        $this->strategy = new OnExceptionRebootStrategy([AllowedException::class], new TestLogger());
    }

    public function test_it_does_not_reboot_by_default()
    {
        $this->assertFalse($this->strategy->shouldReboot());
    }

    public function test_it_reboot_on_unexpected_exception()
    {
        $this->strategy->onException($this->exceptionEvent(new \RuntimeException()));

        $this->assertTrue($this->strategy->shouldReboot());
    }

    public function test_it_does_not_reboot_on_allowed_exception()
    {
        $this->strategy->onException($this->exceptionEvent(new AllowedException()));

        $this->assertFalse($this->strategy->shouldReboot());
    }

    public function test_it_does_not_reboot_on_allowed_exception_child()
    {
        $this->strategy->onException($this->exceptionEvent(new ChildAllowedException()));

        $this->assertFalse($this->strategy->shouldReboot());
    }

    public function test_clear_reset_the_state()
    {
        $this->strategy->onException($this->exceptionEvent(new \RuntimeException()));
        $this->strategy->clear();

        $this->assertFalse($this->strategy->shouldReboot());
    }

    private function exceptionEvent(\Exception $e)
    {
        return new ExceptionEvent($this->prophesize(KernelInterface::class)->reveal(), new Request(), KernelInterface::MASTER_REQUEST, $e);
    }
}

class AllowedException extends \RuntimeException
{
}
class ChildAllowedException extends AllowedException
{
}
