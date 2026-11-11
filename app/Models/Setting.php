<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Admin-entered setting. Read and write through App\Support\Settings.
 *
 * @property int $id
 * @property string $key
 * @property string|null $value encrypted (Crypt::encryptString)
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['key', 'value'])]
class Setting extends Model {}
