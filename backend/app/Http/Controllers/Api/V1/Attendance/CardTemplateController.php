<?php

namespace App\Http\Controllers\Api\V1\Attendance;

use App\Http\Controllers\Api\ApiController;
use App\Infrastructure\Persistence\Eloquent\Attendance\CardTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CardTemplateController extends ApiController
{
    /**
     * Get the active card layout (custom if saved, default otherwise).
     */
    public function show(Request $request, string $type): JsonResponse
    {
        $this->validateType($type);

        $result = CardTemplate::activeLayoutFor($request->user()->tenant_id, $type);

        return $this->success($result);
    }

    /**
     * Save a custom card layout for this tenant + type.
     */
    public function update(Request $request, string $type): JsonResponse
    {
        $this->validateType($type);

        $request->validate([
            'layout_json' => ['required', 'array'],
            'layout_json.elements' => ['required', 'array'],
        ]);

        // $request->validate() di atas hanya memastikan struktur, bukan sumber data:
        // validated() memangkas sibling key (cardBackground/headerBackground) yang
        // tidak punya rule eksplisit sendiri, karena hanya 'layout_json.elements'
        // yang divalidasi lewat dot-notation — jadi ambil payload mentah di sini.
        $template = CardTemplate::updateOrCreate(
            ['tenant_id' => $request->user()->tenant_id, 'type' => $type],
            ['layout_json' => $request->input('layout_json')]
        );

        return $this->success([
            'layout' => $template->layout_json,
            'is_custom' => true,
        ], 'Template kartu berhasil disimpan');
    }

    /**
     * Remove the custom layout, reverting to the default.
     */
    public function reset(Request $request, string $type): JsonResponse
    {
        $this->validateType($type);

        CardTemplate::where('tenant_id', $request->user()->tenant_id)
            ->where('type', $type)
            ->delete();

        return $this->success([
            'layout' => CardTemplate::defaultLayout(),
            'is_custom' => false,
        ], 'Template kartu dikembalikan ke default');
    }

    private function validateType(string $type): void
    {
        abort_unless(in_array($type, ['student', 'teacher'], true), 404);
    }
}
