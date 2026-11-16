<?php

namespace App\Observers;

use App\Models\Building;
use App\Support\BuildingAudit;
use Illuminate\Database\Eloquent\Model;

class BuildingStructureObserver
{
    public function created(Model $m): void
    {
        $this->record($m, 'created');
    }

    public function updated(Model $m): void
    {
        $this->record($m, 'updated');
    }

    public function deleting(Model $m): void
    {
        if ($m instanceof Building && ! $m->isForceDeleting()) {
            return;
        }
        throw new \LogicException('Archive structures instead of deleting history.');
    }

    private function record(Model $m, string $action): void
    {
        if (BuildingAudit::muted()) {
            return;
        }
        $ids = $m instanceof Building ? [$m->id] : array_unique(array_filter([$m->building_id, $m->getRawOriginal('building_id')]));
        foreach ($ids as $id) {
            $b = Building::find($id);
            if (! $b) {
                continue;
            }BuildingAudit::record($b, $action, strtolower(class_basename($m)), $m->uuid, $action === 'created' ? null : $m->getRawOriginal(), $m->attributesToArray(), 'manual', ! ($m instanceof Building && $action === 'created'));
        }
    }
}
