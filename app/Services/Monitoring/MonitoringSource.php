<?php

namespace App\Services\Monitoring;

use App\Http\Middleware\EnsureNfcSimulator;
use Illuminate\Http\Request;

class MonitoringSource
{
    public static function for(Request $request): string
    {
        return EnsureNfcSimulator::available($request) ? 'simulated' : 'physical';
    }
}
