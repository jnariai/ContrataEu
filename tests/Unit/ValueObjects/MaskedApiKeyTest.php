<?php

use App\ValueObjects\MaskedApiKey;

it('never exposes more than the last 4 characters of a key', function (): void {
    $masked = MaskedApiKey::fromPlain('sk-ant-api03-SECRETSECRET-a1b2');

    expect($masked->hint)->toBe('a1b2')
        ->and($masked->display())->toBe('••••a1b2')
        ->and(json_encode($masked))->not->toContain('SECRET');
});

it('refuses to mask a key so short the hint would reveal most of it', function (): void {
    MaskedApiKey::fromPlain('abc1234');
})->throws(InvalidArgumentException::class);
