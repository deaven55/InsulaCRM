<?php

namespace Tests\Feature\Api;

use App\Models\Lead;
use Tests\TestCase;

class IntegrationContractTest extends TestCase
{
    private array $headers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createTenantWithAdmin([
            'name' => 'Integration Contract Company',
            'slug' => 'integration-contract-company',
            'email' => 'integration-contract@test.com',
            'api_key' => 'test-api-key-integration',
            'api_enabled' => true,
        ]);

        $this->headers = ['X-API-Key' => 'test-api-key-integration'];
    }

    public function test_external_identity_is_idempotent_across_different_request_keys(): void
    {
        $payload = [
            'first_name' => 'External',
            'last_name' => 'Identity',
            'phone' => '555-0701',
            'external_id' => 'parcel-123',
            'external_source' => 'whole-stack',
            'source' => 'whole-stack',
        ];

        $first = $this->postJson('/api/v1/leads', $payload, $this->headers);

        $first->assertStatus(201)
            ->assertJson([
                'success' => true,
                'external_id' => 'parcel-123',
                'external_source' => 'whole-stack',
            ]);

        $second = $this->postJson('/api/v1/leads', [
            ...$payload,
            'phone' => '555-0702',
        ], [
            ...$this->headers,
            'Idempotency-Key' => 'different-replay-key',
        ]);

        $second->assertStatus(200)
            ->assertJson([
                'success' => true,
                'duplicate' => true,
                'lead_id' => $first->json('lead_id'),
                'external_id' => 'parcel-123',
                'external_source' => 'whole-stack',
            ]);

        $this->assertSame(1, Lead::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)
            ->where('external_id', 'parcel-123')
            ->where('external_source', 'whole-stack')
            ->count());
    }

    public function test_idempotency_key_replays_original_response(): void
    {
        $payload = [
            'first_name' => 'Replay',
            'last_name' => 'Safe',
            'phone' => '555-0710',
            'external_id' => 'parcel-456',
            'external_source' => 'whole-stack',
            'source' => 'whole-stack',
        ];

        $headers = [
            ...$this->headers,
            'Idempotency-Key' => 'lead-created-parcel-456-v1',
        ];

        $first = $this->postJson('/api/v1/leads', $payload, $headers);
        $first->assertStatus(201);

        $second = $this->postJson('/api/v1/leads', [
            ...$payload,
            'first_name' => 'Should',
            'last_name' => 'Not Create',
            'phone' => '555-0799',
        ], $headers);

        $second->assertStatus(201)
            ->assertJson($first->json());

        $this->assertSame(1, Lead::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)
            ->where('external_id', 'parcel-456')
            ->count());
    }

    public function test_external_identity_is_scoped_to_tenant(): void
    {
        $this->postJson('/api/v1/leads', [
            'first_name' => 'Tenant',
            'last_name' => 'One',
            'external_id' => 'shared-parcel-id',
            'external_source' => 'whole-stack',
        ], $this->headers)->assertStatus(201);

        $firstTenantId = $this->tenant->id;

        $otherTenant = $this->createTenantWithAdmin([
            'name' => 'Integration Contract Company Two',
            'slug' => 'integration-contract-company-two',
            'email' => 'integration-contract-two@test.com',
            'api_key' => 'test-api-key-integration-other',
            'api_enabled' => true,
        ]);

        $response = $this->postJson('/api/v1/leads', [
            'first_name' => 'Tenant',
            'last_name' => 'Two',
            'external_id' => 'shared-parcel-id',
            'external_source' => 'whole-stack',
        ], [
            'X-API-Key' => 'test-api-key-integration-other',
        ]);

        $response->assertStatus(201);

        $this->assertSame(2, Lead::withoutGlobalScopes()
            ->where('external_id', 'shared-parcel-id')
            ->where('external_source', 'whole-stack')
            ->count());

        $this->assertNotSame($firstTenantId, $otherTenant->id);
    }
}
