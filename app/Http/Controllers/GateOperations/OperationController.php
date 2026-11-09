<?php

namespace App\Http\Controllers\GateOperations;

use App\Http\Requests\GateOperationRequest;
use App\Models\Gate;
use App\Models\ScanAttempt;
use App\Services\GateOperations\AutomaticPassages;
use App\Services\GateOperations\GateOperations;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class OperationController
{
    private function source(Request $request): string
    {
        return $request->routeIs('lab.*') ? 'simulated' : 'physical';
    }

    public function scan(GateOperationRequest $request, Gate $gate, AutomaticPassages $operations): JsonResponse
    {
        return response()->json($operations->scan($request->user(), $gate, $request->validated(), $request->session()->getId(), $this->source($request)));
    }

    public function recover(Request $request, Gate $gate, string $requestId, AutomaticPassages $operations): JsonResponse
    {
        return response()->json($operations->recover($request->user(), $gate, $requestId, $request->session()->getId(), $this->source($request)));
    }

    public function review(GateOperationRequest $request, Gate $gate, ScanAttempt $attempt, GateOperations $operations): JsonResponse
    {
        abort(410, 'Manual confirmation has been retired. Valid NFC scans record automatically.');
    }

    public function photo(Request $request, Gate $gate, ScanAttempt $attempt, AutomaticPassages $operations): BinaryFileResponse
    {
        return response()->file($operations->photo($request->user(), $gate, $attempt, $request->session()->getId(), $this->source($request)), ['Content-Type' => 'image/jpeg', 'Content-Disposition' => 'inline; filename="verification-photo.jpg"']);
    }
}
