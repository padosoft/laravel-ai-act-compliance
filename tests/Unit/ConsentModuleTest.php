<?php

namespace Padosoft\AiActCompliance\Tests\Unit;

use Illuminate\Http\Request;
use Padosoft\AiActCompliance\Consent\Models\ConsentRecord;
use Padosoft\AiActCompliance\Consent\RequireConsentMiddleware;
use Padosoft\AiActCompliance\Consent\Services\ConsentService;
use Padosoft\AiActCompliance\Tests\Fixtures\TestUser;
use Padosoft\AiActCompliance\Tests\TestCase;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ConsentModuleTest extends TestCase
{
    public function test_consent_service_grants_and_revokes_consent_with_timestamps(): void
    {
        $service = new ConsentService();

        $granted = $service->grant('user-1', 'assistant-chat')->fresh();

        self::assertTrue($granted->granted);
        self::assertNotNull($granted->granted_at);
        self::assertNull($granted->revoked_at);

        $revoked = $service->revoke('user-1', 'assistant-chat')->fresh();

        self::assertFalse($revoked->granted);
        self::assertNotNull($revoked->revoked_at);
    }

    public function test_consent_middleware_allows_requests_when_no_feature_is_required(): void
    {
        $response = (new RequireConsentMiddleware())->handle(
            Request::create('/consent-free'),
            fn () => response('ok')
        );

        self::assertSame('ok', $response->getContent());
    }

    public function test_consent_middleware_requires_authentication_for_feature_protected_requests(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Authentication required.');

        (new RequireConsentMiddleware())->handle(
            Request::create('/protected'),
            fn () => response('ok'),
            'assistant-chat'
        );
    }

    public function test_consent_middleware_allows_requests_with_an_active_consent_record(): void
    {
        ConsentRecord::query()->create([
            'user_id' => 'user-1',
            'feature' => 'assistant-chat',
            'granted' => true,
            'granted_at' => now(),
        ]);

        $request = Request::create('/protected');
        $request->setUserResolver(fn () => new TestUser('user-1'));

        $response = (new RequireConsentMiddleware())->handle(
            $request,
            fn () => response('ok'),
            'assistant-chat'
        );

        self::assertSame('ok', $response->getContent());
    }

    public function test_consent_middleware_rejects_revoked_consents(): void
    {
        ConsentRecord::query()->create([
            'user_id' => 'user-1',
            'feature' => 'assistant-chat',
            'granted' => true,
            'granted_at' => now(),
            'revoked_at' => now(),
        ]);

        $request = Request::create('/protected');
        $request->setUserResolver(fn () => new TestUser('user-1'));

        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Consent required for this feature.');

        (new RequireConsentMiddleware())->handle(
            $request,
            fn () => response('ok'),
            'assistant-chat'
        );
    }
}
