<?php

namespace App\Casts;

use App\Datas\GenerationReport;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Stores a GenerationReport as JSON and reads it back. Only maps; GenerationReport::fromArray() checks the shape.
 *
 * @implements CastsAttributes<GenerationReport|null, GenerationReport|null>
 */
class GenerationReportCast implements CastsAttributes
{
    /** @param array<string, mixed> $attributes */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?GenerationReport
    {
        if ($value === null) {
            return null;
        }

        $data = json_decode((string) $value, true, flags: JSON_THROW_ON_ERROR);

        return is_array($data) ? GenerationReport::fromArray($data) : null;
    }

    /** @param array<string, mixed> $attributes */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $value === null ? null : json_encode($value->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }
}
