<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;

/**
 * Class Model
 * @package App\Models
 *
 * @method static static create(array $attributes)
 * @see Builder::create()
 */
class Model extends \Illuminate\Database\Eloquent\Model
{
    protected $guarded = [];
}
