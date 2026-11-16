<?php

namespace App\Evidence;

use App\Models\Evidence\Version;

interface Extractor
{
    public function extract(Version $version): array;
}
