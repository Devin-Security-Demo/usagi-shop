<?php

declare(strict_types=1);

namespace Usagi;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use RuntimeException;

final class ShippingClient
{
    private Client $http;

    public function __construct(private string $endpoint)
    {
        $this->http = new Client([
            'timeout'         => 3.0,
            'connect_timeout' => 2.0,
            'headers'         => ['Accept' => 'application/json', 'Host' => 'localhost'],
        ]);
    }

    /** @return array{zip:string,region:string,region_label:string,fee:int} */
    public function quote(string $zip, int $subtotal): array
    {
        try {
            $response = $this->http->request('GET', $this->endpoint, [
                'query' => ['zip' => $zip, 'subtotal' => $subtotal],
            ]);
        } catch (GuzzleException $e) {
            throw new RuntimeException('送料を取得できませんでした。', 0, $e);
        }
        $data = json_decode((string) $response->getBody(), true);
        if (!is_array($data) || !isset($data['fee'])) {
            throw new RuntimeException('送料を取得できませんでした。');
        }
        return $data;
    }
}
