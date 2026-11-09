<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class CampusCatalog
{
    public static function options(): array
    {
        return ['programs' => DB::table('campus_programs')->orderBy('name')->get()->toArray(), 'departments' => DB::table('campus_departments')->orderBy('name')->pluck('name')->all(), 'semesters' => config('campus.semesters')];
    }
}
