<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\Temporal\Interceptors;

use Symfony\Contracts\Service\ResetInterface;
use Temporal\Client\Workflow\WorkflowExecutionDescription;
use Temporal\DataConverter\ValuesInterface;
use Temporal\Interceptor\WorkflowClient\CancelInput;
use Temporal\Interceptor\WorkflowClient\DescribeInput;
use Temporal\Interceptor\WorkflowClient\GetResultInput;
use Temporal\Interceptor\WorkflowClient\QueryInput;
use Temporal\Interceptor\WorkflowClient\SignalInput;
use Temporal\Interceptor\WorkflowClient\SignalWithStartInput;
use Temporal\Interceptor\WorkflowClient\StartInput;
use Temporal\Interceptor\WorkflowClient\StartUpdateOutput;
use Temporal\Interceptor\WorkflowClient\TerminateInput;
use Temporal\Interceptor\WorkflowClient\UpdateInput;
use Temporal\Interceptor\WorkflowClient\UpdateWithStartInput;
use Temporal\Interceptor\WorkflowClient\UpdateWithStartOutput;
use Temporal\Interceptor\WorkflowClientCallsInterceptor;
use Temporal\Workflow\WorkflowExecution;

class CollectingClientInterceptor implements WorkflowClientCallsInterceptor, ResetInterface
{
    private array $interactions = [];

    public function __construct()
    {
    }

    public function getInteractions(): array
    {
        return $this->interactions;
    }

    public function start(StartInput $input, callable $next): WorkflowExecution
    {
        $this->interaction('Start', $input,
            workflowType: $input->workflowType,
            workflowId: $input->workflowId,
        );

        return $next($input);
    }

    public function signal(SignalInput $input, callable $next): void
    {
        $this->interaction('Signal', $input,
            workflowType: $input->workflowType,
            workflowId: $input->workflowExecution->getID(),
            runId: $input->workflowExecution->getRunID(),
            detail: $input->signalName,
        );

        $next($input);
    }

    public function update(UpdateInput $input, callable $next): StartUpdateOutput
    {
        $this->interaction('Update', $input,
            workflowType: $input->workflowType,
            workflowId: $input->workflowExecution->getID(),
            runId: $input->workflowExecution->getRunID(),
            detail: $input->updateName,
        );

        return $next($input);
    }

    public function signalWithStart(SignalWithStartInput $input, callable $next): WorkflowExecution
    {
        $this->interaction('SignalWithStart', $input,
            workflowType: $input->workflowStartInput->workflowType,
            workflowId: $input->workflowStartInput->workflowId,
            detail: $input->signalName,
        );

        return $next($input);
    }

    public function updateWithStart(UpdateWithStartInput $input, callable $next): UpdateWithStartOutput
    {
        $this->interaction('UpdateWithStart', $input,
            workflowType: $input->workflowStartInput->workflowType,
            workflowId: $input->workflowStartInput->workflowId,
            detail: $input->updateInput->updateName,
        );

        return $next($input);
    }

    public function getResult(GetResultInput $input, callable $next): ?ValuesInterface
    {
        $this->interaction('GetResult', $input,
            workflowType: $input->workflowType,
            workflowId: $input->workflowExecution->getID(),
            runId: $input->workflowExecution->getRunID(),
        );

        return $next($input);
    }

    public function query(QueryInput $input, callable $next): ?ValuesInterface
    {
        $this->interaction('Query', $input,
            workflowType: $input->workflowType,
            workflowId: $input->workflowExecution->getID(),
            runId: $input->workflowExecution->getRunID(),
            detail: $input->queryType,
        );

        return $next($input);
    }

    public function cancel(CancelInput $input, callable $next): void
    {
        $this->interaction('Cancel', $input,
            workflowId: $input->workflowExecution->getID(),
            runId: $input->workflowExecution->getRunID(),
        );

        $next($input);
    }

    public function terminate(TerminateInput $input, callable $next): void
    {
        $this->interaction('Terminate', $input,
            workflowId: $input->workflowExecution->getID(),
            runId: $input->workflowExecution->getRunID(),
            detail: $input->reason,
        );

        $next($input);
    }

    public function describe(DescribeInput $input, callable $next): WorkflowExecutionDescription
    {
        $this->interaction('Describe', $input,
            workflowId: $input->workflowExecution->getID(),
            runId: $input->workflowExecution->getRunID(),
        );

        return $next($input);
    }

    public function reset(): void
    {
        $this->interactions = [];
    }

    private function interaction(
        string $type,
        object $input,
        ?string $workflowType = null,
        ?string $workflowId = null,
        ?string $runId = null,
        ?string $detail = null,
    ): void {
        $data = (array) $input; // only one level of information is shown in the profiler when storing "input" directly
        $data['_type'] = $input::class;
        $this->interactions[] = compact('type', 'workflowType', 'workflowId', 'runId', 'detail', 'data');
    }
}
