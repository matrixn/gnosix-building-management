<?php

namespace App\Models\Evidence;

use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
    protected $table = 'evidence_documents';

    protected $guarded = [];

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return ['archived_at' => 'datetime'];
    }

    public function versions()
    {
        return $this->hasMany(Version::class, 'document_id');
    }
}
