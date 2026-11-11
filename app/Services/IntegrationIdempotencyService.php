<?php

namespace App\Services;

use App\Models\IntegrationIdempotencyKey;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IntegrationIdempotencyService
{
    public function replay(Request $request, int $tenantId, string $source): ?JsonResponse
    {
        $key = trim((string) $request->header('Idempotency-Key'));

        if ($key === '') {
            return null;
        }

        $record = IntegrationIdempotencyKey::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('source', $source)
            ->where('idempotency_key', $key)
            ->first();

        if (! $record || $record->response_body === null) {
            return null;
        }

        return response()->json($record->response_body, $record->response_status ?? 200);
    }

    public function remember(
        Request $request,
        int $tenantId,
        string $source,
        string $eventType,
        string $resourceType,
        int $resourceId,
        JsonResponse $response
    ): void {
        $key = trim((string) $request->header('Idempotency-Key'));

        if ($key === '') {
            return;
        }

        IntegrationIdempotencyKey::withoutGlobalScopes()->updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'source' => $source,
                'idempotency_key' => $key,
            ],
            [
                'event_type' => $eventType,
                'resource_type' => $resourceType,
                'resource_id' => $resourceId,
                'response_body' => $response->getData(true),
                'response_status' => $response->getStatusCode(),
            ]
        );
    }
}
