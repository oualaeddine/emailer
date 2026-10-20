<?php

namespace App\Modules\Recipients\Views\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Recipients\Views\Http\Requests\ColumnPreferencesRequest;
use App\Modules\Recipients\Views\Support\ContactColumns;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * docs/45 §45.5.4 — PATCH /me/column-preferences (`users.column_preferences`).
 */
class ColumnPreferenceController extends Controller
{
    public function __invoke(ColumnPreferencesRequest $request): JsonResponse
    {
        $columns = ContactColumns::normalize((array) $request->input('columns'));
        $user = $request->user();

        $stored = json_decode((string) DB::table('users')->where('id', $user->id)->value('column_preferences'), true) ?: [];
        $stored[$request->input('page')] = $columns;
        DB::table('users')->where('id', $user->id)->update(['column_preferences' => json_encode($stored)]);

        return response()->json(['data' => ['page' => $request->input('page'), 'columns' => $columns]]);
    }
}
