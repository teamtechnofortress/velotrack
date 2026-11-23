<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Per-user storage for vehicles, entries, reminders and inspections.
 * JSON keys are camelCase (as the Android app stores them); columns are snake_case.
 * Every query is scoped to the authenticated user.
 */
class DataController extends Controller
{
    /** type => [table, camelCase field => [validation rule, kind]] */
    private const SPEC = [
        'vehicles' => ['vehicles', [
            'name' => ['required|string|max:150', 's'], 'make' => ['nullable|string|max:100', 's'],
            'model' => ['nullable|string|max:100', 's'], 'year' => ['nullable|string|max:4', 's'],
            'plate' => ['nullable|string|max:40', 's'], 'fuel' => ['nullable|string|max:30', 's'],
            'type' => ['nullable|string|max:30', 's'], 'vin' => ['nullable|string|max:40', 's'],
        ]],
        'entries' => ['entries', [
            'vehicleId' => ['required|integer', 'i'], 'kind' => ['required|string|max:30', 's'],
            'title' => ['required|string|max:150', 's'], 'amount' => ['required|numeric', 'f'],
            'date' => ['required|string|max:10', 's'], 'quantity' => ['nullable|numeric', 'f'],
            'unit' => ['nullable|string|max:10', 's'], 'unitPrice' => ['nullable|numeric', 'f'],
            'notes' => ['nullable|string|max:2000', 's'],
        ]],
        'reminders' => ['reminders', [
            'vehicleId' => ['required|integer', 'i'], 'title' => ['required|string|max:150', 's'],
            'due' => ['required|string|max:10', 's'], 'done' => ['nullable|boolean', 'b'],
        ]],
        'inspections' => ['inspections', [
            'vehicleId' => ['required|integer', 'i'], 'date' => ['required|string|max:10', 's'],
            'tyre' => ['nullable|boolean', 'b'], 'brakes' => ['nullable|boolean', 'b'],
            'lights' => ['nullable|boolean', 'b'], 'battery' => ['nullable|boolean', 'b'],
            'notes' => ['nullable|string|max:2000', 's'],
        ]],
    ];
    private const MAX_ROWS = 20000;

    public function index(Request $request)
    {
        $out = [];
        foreach (self::SPEC as $type => [$table, $fields]) {
            $out[$type] = DB::table($table)->where('user_id', $request->user()->id)->orderBy('client_id')->get()
                ->map(function ($row) use ($fields) {
                    $item = ['id' => (int) $row->client_id];
                    foreach ($fields as $key => [, $kind]) {
                        $value = $row->{Str::snake($key)};
                        $item[$key] = match (true) {
                            $value === null => $kind === 's' ? '' : null,
                            $kind === 'i' => (int) $value,
                            $kind === 'f' => (float) $value,
                            $kind === 'b' => (bool) $value,
                            default => $value,
                        };
                    }
                    return $item;
                })->values();
        }
        return response()->json($out);
    }

    /** Batch upsert + delete. Safe to repeat (idempotent), so the app can retry after a failure. */
    public function sync(Request $request)
    {
        $rules = ['deleted' => ['nullable', 'array']];
        foreach (self::SPEC as $type => [, $fields]) {
            $rules[$type] = ['nullable', 'array', 'max:'.self::MAX_ROWS];
            $rules["$type.*.id"] = ['required', 'integer', 'min:1'];
            foreach ($fields as $key => [$rule]) $rules["$type.*.$key"] = explode('|', $rule);
            $rules["deleted.$type"] = ['nullable', 'array', 'max:'.self::MAX_ROWS];
            $rules["deleted.$type.*"] = ['integer'];
        }
        $data = $request->validate($rules);
        $userId = $request->user()->id;
        $now = now();

        DB::transaction(function () use ($data, $userId, $now) {
            foreach (self::SPEC as $type => [$table, $fields]) {
                $rows = [];
                foreach ($data[$type] ?? [] as $item) {
                    $row = ['user_id' => $userId, 'client_id' => $item['id'], 'created_at' => $now, 'updated_at' => $now];
                    foreach ($fields as $key => [, $kind]) {
                        $value = $item[$key] ?? null;
                        $row[Str::snake($key)] = $kind === 'b' ? ($value === null ? true : (bool) $value)
                            : ($kind === 's' && $value === null && !str_contains($fields[$key][0], 'nullable') ? '' : $value);
                    }
                    $rows[$item['id']] = $row; // last write for a duplicate id wins
                }
                foreach (array_chunk(array_values($rows), 500) as $chunk) {
                    DB::table($table)->upsert($chunk, ['user_id', 'client_id'],
                        array_merge(array_map(fn ($k) => Str::snake($k), array_keys($fields)), ['updated_at']));
                }
            }
            foreach (self::SPEC as $type => [$table]) {
                $ids = $data['deleted'][$type] ?? [];
                if (!$ids) continue;
                if ($type === 'vehicles') {
                    foreach (['entries', 'reminders', 'inspections'] as $child) {
                        DB::table($child)->where('user_id', $userId)->whereIn('vehicle_id', $ids)->delete();
                    }
                    DB::table('vehicles')->where('user_id', $userId)->whereIn('client_id', $ids)->delete();
                } else {
                    DB::table($table)->where('user_id', $userId)->whereIn('client_id', $ids)->delete();
                }
            }
        });
        return response()->json(['message' => 'Synced']);
    }
}
