<?php

namespace App\ValueObjects;

use InvalidArgumentException;
use JsonSerializable;

/**
 * What the UI may know about an API key: its last 4 characters. Built once from the plain key at save time
 * (stored as `ai_credentials.key_hint`), so showing a key never needs decrypting it.
 */
final readonly class MaskedApiKey implements JsonSerializable
{
    public const int VISIBLE_CHARACTERS = 4;

    private function __construct(public string $hint) {}

    public static function fromPlain(string $plain): self
    {
        $plain = trim($plain);

        if (mb_strlen($plain) < self::VISIBLE_CHARACTERS * 2) {
            throw new InvalidArgumentException('An API key is too short to be masked safely.');
        }

        return new self(mb_substr($plain, -self::VISIBLE_CHARACTERS));
    }

    public static function fromHint(string $hint): self
    {
        if (mb_strlen($hint) !== self::VISIBLE_CHARACTERS) {
            throw new InvalidArgumentException('A key hint has exactly '.self::VISIBLE_CHARACTERS.' characters.');
        }

        return new self($hint);
    }

    public function display(): string
    {
        return '••••'.$this->hint;
    }

    public function jsonSerialize(): string
    {
        return $this->display();
    }
}
