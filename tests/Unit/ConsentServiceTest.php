<?php

namespace Padosoft\AiActCompliance\Tests\Unit;

use Padosoft\AiActCompliance\Consent\Models\ConsentRecord;
use Padosoft\AiActCompliance\Consent\Services\ConsentService;
use Padosoft\AiActCompliance\Tests\TestCase;

class ConsentServiceTest extends TestCase
{
    public function test_grant_creates_a_record_with_granted_true(): void
    {
        $record = (new ConsentService())->grant('user-1', 'chat_use')->fresh();

        self::assertNotNull($record);
        self::assertSame('user-1', $record->user_id);
        self::assertSame('chat_use', $record->feature);
        self::assertTrue((bool) $record->granted);
        self::assertNotNull($record->granted_at);
        self::assertNull($record->revoked_at);
    }

    public function test_grant_is_idempotent_per_user_feature(): void
    {
        $service = new ConsentService();
        $first = $service->grant('user-1', 'kb_ingest')->fresh();
        $second = $service->grant('user-1', 'kb_ingest')->fresh();

        self::assertSame($first->id, $second->id);
        self::assertSame(1, ConsentRecord::query()->count());
    }

    public function test_revoke_flips_granted_off_and_records_revoked_at(): void
    {
        $service = new ConsentService();
        $service->grant('user-2', 'marketing');
        $revoked = $service->revoke('user-2', 'marketing')->fresh();

        self::assertFalse((bool) $revoked->granted);
        self::assertNotNull($revoked->revoked_at);
    }

    public function test_revoke_on_a_never_granted_feature_creates_an_explicit_revocation(): void
    {
        $revoked = (new ConsentService())->revoke('user-3', 'biometric_voice')->fresh();

        self::assertNotNull($revoked);
        self::assertFalse((bool) $revoked->granted);
        self::assertNotNull($revoked->revoked_at);
    }

    public function test_grant_after_revoke_restores_the_consent(): void
    {
        $service = new ConsentService();
        $service->grant('user-4', 'profile_enrich');
        $service->revoke('user-4', 'profile_enrich');
        $restored = $service->grant('user-4', 'profile_enrich')->fresh();

        self::assertTrue((bool) $restored->granted);
        self::assertNull($restored->revoked_at);
        // Same row — updateOrCreate keeps the primary key
        self::assertSame(1, ConsentRecord::query()
            ->where('user_id', 'user-4')
            ->where('feature', 'profile_enrich')
            ->count());
    }

    public function test_consent_is_scoped_per_feature(): void
    {
        $service = new ConsentService();
        $service->grant('user-5', 'chat_use');
        $service->revoke('user-5', 'marketing');

        $chat = ConsentRecord::query()->where('user_id', 'user-5')->where('feature', 'chat_use')->first();
        $marketing = ConsentRecord::query()->where('user_id', 'user-5')->where('feature', 'marketing')->first();

        self::assertTrue((bool) $chat->granted);
        self::assertFalse((bool) $marketing->granted);
    }

    public function test_consent_is_scoped_per_user(): void
    {
        $service = new ConsentService();
        $service->grant('user-a', 'chat_use');
        $service->revoke('user-b', 'chat_use');

        self::assertTrue((bool) ConsentRecord::query()
            ->where('user_id', 'user-a')->where('feature', 'chat_use')->first()->granted);
        self::assertFalse((bool) ConsentRecord::query()
            ->where('user_id', 'user-b')->where('feature', 'chat_use')->first()->granted);
    }
}
