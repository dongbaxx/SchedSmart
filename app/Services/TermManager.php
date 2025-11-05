<?php

namespace App\Services;

use App\Models\AcademicYear;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class TermManager
{
    /**
     * Activate a term by id, making sure only one is active
     */
    public function activate(int $id): AcademicYear
    {
        $target = AcademicYear::find($id);
        if (! $target) {
            throw (new ModelNotFoundException())->setModel(AcademicYear::class, [$id]);
        }

        DB::transaction(function () use ($id) {
            AcademicYear::query()->update(['is_active' => false]);
            AcademicYear::whereKey($id)->update(['is_active' => true]);
        });

        return AcademicYear::findOrFail($id);
    }

    /**
     * Ensure exactly one active term exists (defaults to latest if none).
     */
    public function ensureSingleActive(): ?AcademicYear
    {
        $activeCount = AcademicYear::where('is_active', true)->count();

        if ($activeCount === 1) {
            return AcademicYear::active()->first();
        }

        if ($activeCount === 0) {
            $latest = AcademicYear::latest('id')->first();
            if ($latest) {
                return $this->activate($latest->id);
            }
            return null;
        }

        $latestActive = AcademicYear::where('is_active', true)->latest('id')->first();
        DB::transaction(function () use ($latestActive) {
            AcademicYear::query()->update(['is_active' => false]);
            if ($latestActive) {
                AcademicYear::whereKey($latestActive->id)->update(['is_active' => true]);
            }
        });

        return AcademicYear::active()->first();
    }
}
