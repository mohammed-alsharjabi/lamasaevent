<?php

namespace Tests\Feature;

use App\Services\StaticFrontendDeploymentService;
use Tests\TestCase;

class DeployStaticFrontendWebhookTest extends TestCase
{
    public function test_a_valid_signed_webhook_deploys_the_requested_commit(): void
    {
        config([
            'publishing.deployment.enabled' => true,
            'publishing.deployment.webhook_secret' => 'test-webhook-secret',
            'publishing.deployment.max_signature_age' => 300,
        ]);

        $commit = str_repeat('a', 40);
        $timestamp = (string) now()->timestamp;
        $body = json_encode(['commit' => $commit], JSON_THROW_ON_ERROR);
        $signature = hash_hmac(
            'sha256',
            $timestamp.'.'.$body,
            'test-webhook-secret',
        );

        $this->mock(StaticFrontendDeploymentService::class)
            ->shouldReceive('deploy')
            ->once()
            ->with($commit)
            ->andReturn([
                'commit' => $commit,
                'deployed_at' => now()->toIso8601String(),
            ]);

        $response = $this->call(
            'POST',
            '/api/v1/deploy-static',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_X_LAMASAEVENT_TIMESTAMP' => $timestamp,
                'HTTP_X_LAMASAEVENT_SIGNATURE' => $signature,
            ],
            $body,
        );

        $response
            ->assertOk()
            ->assertJsonPath('status', 'deployed')
            ->assertJsonPath('commit', $commit);
    }

    public function test_an_invalid_signature_is_rejected(): void
    {
        config([
            'publishing.deployment.enabled' => true,
            'publishing.deployment.webhook_secret' => 'test-webhook-secret',
        ]);

        $this->withHeaders([
            'X-Lamasaevent-Timestamp' => (string) now()->timestamp,
            'X-Lamasaevent-Signature' => str_repeat('0', 64),
        ])->postJson('/api/v1/deploy-static', [
            'commit' => str_repeat('a', 40),
        ])->assertForbidden();
    }

    public function test_an_expired_signature_is_rejected(): void
    {
        config([
            'publishing.deployment.enabled' => true,
            'publishing.deployment.webhook_secret' => 'test-webhook-secret',
            'publishing.deployment.max_signature_age' => 300,
        ]);

        $commit = str_repeat('a', 40);
        $timestamp = (string) now()->subMinutes(10)->timestamp;
        $body = json_encode(['commit' => $commit], JSON_THROW_ON_ERROR);
        $signature = hash_hmac(
            'sha256',
            $timestamp.'.'.$body,
            'test-webhook-secret',
        );

        $this->call(
            'POST',
            '/api/v1/deploy-static',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_X_LAMASAEVENT_TIMESTAMP' => $timestamp,
                'HTTP_X_LAMASAEVENT_SIGNATURE' => $signature,
            ],
            $body,
        )->assertForbidden();
    }
}
