<?php

use App\Gateways\Prism\PublicEndpoint;

it('only lets calls go to public HTTPS servers', function (string $url, ?string $address): void {
    fakeDns(['api.orcarouter.ai' => ['104.18.20.30'], 'sneaky.example.com' => ['104.18.20.30', '10.0.0.7'], 'metadata.example.com' => ['169.254.169.254']]);

    expect(app(PublicEndpoint::class)->addressFor($url))->toBe($address);
})->with([
    'public host' => ['https://api.orcarouter.ai/v1', '104.18.20.30'],
    'public IP' => ['https://8.8.8.8/v1', '8.8.8.8'],
    'plain http' => ['http://api.orcarouter.ai/v1', null],
    'credentials in the URL' => ['https://user:secret@api.orcarouter.ai/v1', null],
    'unknown host' => ['https://nowhere.example.com/v1', null],
    'host with any private address' => ['https://sneaky.example.com/v1', null],
    'host pointing at cloud metadata' => ['https://metadata.example.com/v1', null],
    'loopback' => ['https://127.0.0.1/v1', null],
    'private network' => ['https://192.168.1.10/v1', null],
    'carrier-grade NAT' => ['https://100.64.0.1/v1', null],
    'IPv6 loopback' => ['https://[::1]/v1', null],
    'IPv6 unique local' => ['https://[fd00::1]/v1', null],
]);

it('pins the call to the checked address and never follows redirects', function (): void {
    $options = app(PublicEndpoint::class)->pinnedClientOptions('https://api.orcarouter.ai/v1', '104.18.20.30');

    expect($options)->toBe(['allow_redirects' => false, 'curl' => [CURLOPT_RESOLVE => ['api.orcarouter.ai:443:104.18.20.30']]]);
});
