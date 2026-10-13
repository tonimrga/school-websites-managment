<?php

namespace Tests\Fixtures\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Tests\Fixtures\Policies\NotePolicy;

/**
 * Placeholder content model for the tenant isolation suite. It stands in for
 * articles, documents, categories and pages, which all use the same trait
 * and policy base.
 */
#[Fillable(['title', 'sort_order'])]
#[UsePolicy(NotePolicy::class)]
class Note extends Model
{
    use BelongsToSchool;
}
