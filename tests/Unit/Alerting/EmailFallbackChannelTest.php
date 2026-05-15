<?php

namespace Padosoft\AiActCompliance\Tests\Unit\Alerting;

use Illuminate\Support\Facades\Mail;
use Padosoft\AiActCompliance\Alerting\Channels\EmailFallbackChannel;
use Padosoft\AiActCompliance\Alerting\Contracts\AlertPayload;
use Padosoft\AiActCompliance\Tests\TestCase;

class EmailFallbackChannelTest extends TestCase
{
    public function test_happy_path_actually_calls_mail_raw(): void
    {
        // Copilot review on PR #3 caught that the previous shape of
        // this test only checked `Mail::mailer()` was not null —
        // trivially true under Mail::fake() even if the channel
        // silently swallowed the call. We now assert
        // `Mail::raw()` is invoked explicitly so a regression that
        // stops calling raw() fails the suite loudly.
        $rawCalled = false;
        $capturedSubject = null;
        Mail::shouldReceive('raw')
            ->once()
            ->andReturnUsing(function (string $body, callable $configure) use (&$rawCalled, &$capturedSubject) {
                $rawCalled = true;
                $message = new class {
                    public ?string $subject = null;

                    public function to(string $email): self
                    {
                        return $this;
                    }

                    public function subject(string $subject): self
                    {
                        $this->subject = $subject;

                        return $this;
                    }
                };
                $configure($message);
                $capturedSubject = $message->subject;
            });

        $payload = new AlertPayload(
            severity: 'high',
            title: 'Bias drift on demographic_parity',
            body: 'Disparity 0.18 on cohort language=it.',
            tenantId: 'tenant-a',
            evidenceUrl: 'https://example.test/bias',
            metricName: 'demographic_parity',
            cohort: 'language=it',
            articles: ['AI Act Art. 10'],
        );

        $result = (new EmailFallbackChannel())->send($payload, 'dpo@example.test');

        self::assertTrue($result->ok);
        self::assertTrue($rawCalled);
        self::assertSame('[HIGH] Bias drift on demographic_parity', $capturedSubject);
    }

    public function test_smtp_failure_is_classified_as_transient(): void
    {
        Mail::shouldReceive('raw')
            ->once()
            ->andThrow(new \RuntimeException('SMTP queue full'));

        $payload = new AlertPayload(
            severity: 'critical',
            title: 'Bias drift',
            body: 'body',
            tenantId: null,
            evidenceUrl: null,
            metricName: null,
            cohort: null,
        );

        $result = (new EmailFallbackChannel())->send($payload, 'dpo@example.test');

        self::assertFalse($result->ok);
        self::assertTrue($result->transient);
        self::assertStringContainsString('SMTP', $result->errorMessage);
    }

    public function test_payload_body_renders_metadata_lines(): void
    {
        Mail::fake();

        // Spy on the raw() call to inspect the body content.
        $capturedBody = null;
        Mail::shouldReceive('raw')
            ->once()
            ->andReturnUsing(function (string $body) use (&$capturedBody) {
                $capturedBody = $body;
            });

        $payload = new AlertPayload(
            severity: 'medium',
            title: 'T',
            body: 'B',
            tenantId: 'tenant-a',
            evidenceUrl: 'https://example.test/x',
            metricName: 'calibration',
            cohort: 'gender=f',
            articles: ['AI Act Art. 15'],
        );

        (new EmailFallbackChannel())->send($payload, 'dpo@example.test');

        self::assertNotNull($capturedBody);
        self::assertStringContainsString('Severity: medium', $capturedBody);
        self::assertStringContainsString('Metric: calibration', $capturedBody);
        self::assertStringContainsString('Cohort: gender=f', $capturedBody);
        self::assertStringContainsString('Tenant: tenant-a', $capturedBody);
        self::assertStringContainsString('Articles: AI Act Art. 15', $capturedBody);
        self::assertStringContainsString('Evidence dashboard: https://example.test/x', $capturedBody);
    }
}
