<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * Text the AI wrote that the user may edit: `content` is what they see, `original_content` the AI's version kept
 * for "revert", and `edited_at` marks a difference between the two.
 *
 * @mixin Model
 *
 * @property string $content
 * @property string $original_content
 */
trait KeepsOriginalContent
{
    /** Saving the AI's own text again counts as not edited. */
    public function edit(string $content): void
    {
        $this->content = $content;
        $this->edited_at = $content === $this->original_content ? null : now();
        $this->save();
    }

    public function revert(): void
    {
        $this->edit($this->original_content);
    }

    public function isEdited(): bool
    {
        return $this->edited_at !== null;
    }
}
