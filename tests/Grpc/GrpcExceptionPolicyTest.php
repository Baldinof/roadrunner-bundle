<?php

declare(strict_types=1);

namespace Tests\Baldinof\RoadRunnerBundle\Grpc;

use Baldinof\RoadRunnerBundle\Grpc\GrpcExceptionPolicy;
use PHPUnit\Framework\TestCase;

class GrpcExceptionPolicyTest extends TestCase
{
    public function test_it_escalates_exceptions_and_errors_by_default(): void
    {
        $policy = new GrpcExceptionPolicy();

        $this->assertTrue($policy->shouldEscalate(new \RuntimeException()));
        $this->assertTrue($policy->shouldEscalate(new \Error()));
    }

    public function test_it_matches_classes_and_subclasses(): void
    {
        $policy = new GrpcExceptionPolicy([\RuntimeException::class]);

        $this->assertFalse($policy->shouldEscalate(new \RuntimeException()));
        $this->assertFalse($policy->shouldEscalate(new \UnexpectedValueException()));
        $this->assertTrue($policy->shouldEscalate(new \LogicException()));
        $this->assertTrue($policy->shouldEscalate(new \Error()));
    }

    public function test_it_matches_interfaces_and_checks_all_entries(): void
    {
        $policy = new GrpcExceptionPolicy([\LogicException::class, ExpectedFailure::class]);
        $exception = new class extends \RuntimeException implements ExpectedFailure {};

        $this->assertFalse($policy->shouldEscalate($exception));
        $this->assertTrue($policy->shouldEscalate(new \RuntimeException()));
    }
}

interface ExpectedFailure
{
}
