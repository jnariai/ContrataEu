<?php

namespace App\ValueObjects;

use App\Enums\DocumentType;
use Illuminate\Support\Str;
use InvalidArgumentException;
use JsonSerializable;
use Stringable;

/**
 * An object key in the files bucket. Single source of truth for the layout:
 *
 *     users/{user_id}/master/{document_type}/{ulid}.{ext}                            uploaded originals
 *     users/{user_id}/applications/{application_id}/v{version}/{document_type}.pdf     rendered PDFs
 *
 * Everything sits under `users/{user_id}/`, so deleting an account can also wipe that prefix as a safety net.
 * File names never come from the user.
 */
final readonly class StoragePath implements JsonSerializable, Stringable
{
    private function __construct(public string $value) {}

    public static function forMasterOriginal(int $userId, DocumentType $type, string $extension): self
    {
        $extension = strtolower($extension);

        if (preg_match('/^[a-z0-9]{1,8}$/', $extension) !== 1) {
            throw new InvalidArgumentException("Invalid file extension [{$extension}].");
        }

        return new self(self::userDirectory($userId)."/master/{$type->value}/".Str::ulid()->toBase32().'.'.$extension);
    }

    public static function forDocumentPdf(int $userId, int $applicationId, int $version, DocumentType $type): self
    {
        if (! $type->hasPdf()) {
            throw new InvalidArgumentException("[{$type->value}] documents have no PDF.");
        }

        return new self(self::userDirectory($userId)."/applications/{$applicationId}/v{$version}/{$type->value}.pdf");
    }

    public static function userDirectory(int $userId): string
    {
        if ($userId < 1) {
            throw new InvalidArgumentException("Invalid user id [{$userId}].");
        }

        return "users/{$userId}";
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }

    public function jsonSerialize(): string
    {
        return $this->value;
    }
}
