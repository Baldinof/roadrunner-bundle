<?php

declare(strict_types=1);

namespace Tests\Baldinof\RoadRunnerBundle\Integration\Blackfire;

use Baldinof\RoadRunnerBundle\Integration\Blackfire\BlackfireMiddleware;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\Baldinof\RoadRunnerBundle\Utils\CallableHttpKernel;

final class BlackfireMiddlewareTest extends TestCase
{
    public function testItIgnoresRequestsWithoutAProfileQuery(): void
    {
        $response = new Response('ok');
        $request = Request::create('/graphql', 'POST');

        $result = (new BlackfireMiddleware())
            ->process($request, new CallableHttpKernel(static fn (): Response => $response))
            ->current();

        self::assertSame($response, $result);
    }

    public function testItNamesTheTransactionEvenWhenTheProbeCannotBeEnabled(): void
    {
        if (!property_exists(\BlackfireProbe::class, 'transactionName')) {
            self::markTestSkipped('The blackfire extension provides the probe; the naming check needs the SDK stub.');
        }

        $request = Request::create('/graphql', 'POST', [], [], [], [
            'HTTP_X_BLACKFIRE_QUERY' => 'query',
        ]);

        $result = (new BlackfireMiddleware())
            ->process($request, new CallableHttpKernel(static fn (): Response => new Response('ok')))
            ->current();

        // The name is set before enable(), which fails without the extension.
        self::assertSame('POST /graphql', self::transactionName());
        self::assertSame('ok', $result->getContent());
    }

    private static function transactionName(): mixed
    {
        $property = new \ReflectionProperty(\BlackfireProbe::class, 'transactionName');
        $property->setAccessible(true);

        return $property->getValue();
    }
}
