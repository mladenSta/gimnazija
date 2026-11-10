<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * 301 redirect, created automatically when a post/page slug changes.
 *
 * @property int $id
 * @property string $from_path
 * @property string $to_path
 * @property int $status_code
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['from_path', 'to_path', 'status_code'])]
class Redirect extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['status_code' => 'integer'];
    }
}
