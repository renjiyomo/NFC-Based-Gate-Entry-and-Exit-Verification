<?php

namespace App\Http\Controllers\Management;

use App\Models\Gate;
use App\Models\User;
use App\Support\DirectoryData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChoiceController
{
    public function __invoke(Request $request, string $kind): JsonResponse
    {
        $data = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'page' => ['nullable', 'integer', 'min:1', 'max:100000'], 'selected' => ['nullable', 'integer', 'min:1'], 'active' => ['nullable', 'boolean']]);
        $query = $kind === 'guards' ? User::where('role', 'guard')->where('status', 'active')->select(['id', 'name', 'email']) : Gate::select(['id', 'name', 'code']);
        if ($kind === 'gates' && $request->boolean('active')) {
            $query->where('status', 'active');
        }
        $selected = isset($data['selected']) ? (clone $query)->find($data['selected']) : null;
        if (! empty($data['q'])) {
            $like = DirectoryData::like($data['q']);
            $query->where(fn ($q) => $q->whereRaw("name LIKE ? ESCAPE '!'", [$like])->orWhereRaw(($kind === 'guards' ? 'email' : 'code')." LIKE ? ESCAPE '!'", [$like]));
        }
        $results = $query->orderBy('name')->orderBy('id')->paginate(25);

        return response()->json(['data' => $results->items(), 'selected' => $selected, 'has_more' => $results->hasMorePages()]);
    }
}
