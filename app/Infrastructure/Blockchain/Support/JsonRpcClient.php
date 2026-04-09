<?php

namespace App\Infrastructure\Blockchain\Support;

use Illuminate\Support\Facades\Http;

final class JsonRpcClient
{
    public function __construct(
        private readonly string $rpcUrl,
        private readonly ?string $apiKey = null,
    ) {}

    public function call(string $method, array $params = []): mixed
    {
        $payload = [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => $method,
            'params' => $params,
        ];

        $request = Http::timeout(20)->retry(2, 200)->acceptJson();

        if ($this->apiKey) {
            $request = $request->withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
            ]);
        }

        $response = $request->post($this->rpcUrl, $payload);

        if (! $response->successful()) {
            throw new \RuntimeException("RPC request failed: {$method} => {$response->status()}");
        }

        $json = $response->json();

        if (isset($json['error'])) {
            throw new \RuntimeException("RPC error for {$method}: " . json_encode($json['error']));
        }

        return $json['result'] ?? null;
    }
}
