<?php

namespace Padosoft\AiActCompliance\Tests\Unit;

use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\RateLimiter;
use Padosoft\AiActCompliance\Cybersecurity\PerUserRateLimitMiddleware;
use Padosoft\AiActCompliance\Cybersecurity\SessionAnomalyDetectionMiddleware;
use Padosoft\AiActCompliance\Tests\Fixtures\TestUser;
use Padosoft\AiActCompliance\Tests\TestCase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

class CybersecurityMiddlewareTest extends TestCase
{
    public function test_rate_limit_is_scoped_per_route_and_requester(): void
    {
        RateLimiter::clear('ai-act:assistant:guest:127.0.0.1');
        RateLimiter::clear('ai-act:reports:guest:127.0.0.1');

        $middleware = new PerUserRateLimitMiddleware();

        $assistantRequest = Request::create('/assistant', server: ['REMOTE_ADDR' => '127.0.0.1']);
        $assistantRequest->setRouteResolver(fn () => new class
        {
            public function uri(): string
            {
                return 'assistant';
            }
        });

        $reportsRequest = Request::create('/reports', server: ['REMOTE_ADDR' => '127.0.0.1']);
        $reportsRequest->setRouteResolver(fn () => new class
        {
            public function uri(): string
            {
                return 'reports';
            }
        });

        $middleware->handle($assistantRequest, fn () => response('ok'), 1);
        $response = $middleware->handle($reportsRequest, fn () => response('ok'), 1);

        self::assertSame('ok', $response->getContent());
    }

    public function test_rate_limit_returns_retry_after_for_exhausted_buckets(): void
    {
        RateLimiter::clear('ai-act:assistant:user-1:127.0.0.1');

        $middleware = new PerUserRateLimitMiddleware();
        $request = Request::create('/assistant', server: ['REMOTE_ADDR' => '127.0.0.1']);
        $request->setUserResolver(fn () => new TestUser('user-1'));
        $request->setRouteResolver(fn () => new class
        {
            public function uri(): string
            {
                return 'assistant';
            }
        });

        $middleware->handle($request, fn () => response('ok'), 1);

        try {
            $middleware->handle($request, fn () => response('ok'), 1);
            self::fail('Expected the second request to be rate limited.');
        } catch (TooManyRequestsHttpException $exception) {
            self::assertGreaterThanOrEqual(1, $exception->getHeaders()['Retry-After'] ?? 0);
        }
    }

    public function test_session_anomaly_detection_tracks_the_initial_fingerprint(): void
    {
        $middleware = new SessionAnomalyDetectionMiddleware();
        $session = new Store('test', new ArraySessionHandler(120));
        $session->start();

        $request = Request::create('/secure', server: [
            'REMOTE_ADDR' => '127.0.0.1',
            'HTTP_USER_AGENT' => 'Test Agent',
        ]);
        $request->setLaravelSession($session);

        $response = $middleware->handle($request, fn () => response('ok'));

        self::assertSame('127.0.0.1', $session->get('ai-act-compliance.session-fingerprint.ip'));
        self::assertSame('Test Agent', $session->get('ai-act-compliance.session-fingerprint.user_agent'));
        self::assertSame('ok', $response->getContent());
    }

    public function test_session_anomaly_detection_rejects_a_changed_fingerprint(): void
    {
        $middleware = new SessionAnomalyDetectionMiddleware();
        $session = new Store('test', new ArraySessionHandler(120));
        $session->start();
        $session->put('ai-act-compliance.session-fingerprint.ip', '127.0.0.1');
        $session->put('ai-act-compliance.session-fingerprint.user_agent', 'Trusted Agent');

        $request = Request::create('/secure', server: [
            'REMOTE_ADDR' => '127.0.0.2',
            'HTTP_USER_AGENT' => 'Trusted Agent',
        ]);
        $request->setLaravelSession($session);

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Session anomaly detected.');

        $middleware->handle($request, fn () => response('ok'));
    }
}
