<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\StageResource;
use App\Models\PipelineStage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class StageController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return StageResource::collection(PipelineStage::orderBy('position')->get());
    }

    /** Sostituisce la configurazione delle fasi (solo admin). Le fasi omesse vengono eliminate se vuote. */
    public function sync(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('manage', PipelineStage::class);

        $data = $request->validate([
            'stages' => ['required', 'array', 'min:2', 'max:20'],
            'stages.*.id' => ['nullable', 'integer', 'exists:pipeline_stages,id'],
            'stages.*.name' => ['required', 'string', 'max:60'],
            'stages.*.probability' => ['required', 'integer', 'between:0,100'],
            'stages.*.color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'stages.*.is_won' => ['boolean'],
            'stages.*.is_lost' => ['boolean'],
        ]);

        DB::transaction(function () use ($data) {
            $keep = [];
            foreach (array_values($data['stages']) as $position => $item) {
                $stage = isset($item['id']) ? PipelineStage::findOrFail($item['id']) : new PipelineStage;
                $stage->fill([...Arr::except($item, 'id'), 'position' => $position])->save();
                $keep[] = $stage->id;
            }

            $removed = PipelineStage::whereNotIn('id', $keep)->get();
            foreach ($removed as $stage) {
                if ($stage->deals()->withTrashed()->exists()) {
                    throw ValidationException::withMessages(['stages' => "La fase \"{$stage->name}\" contiene opportunità: spostale prima di eliminarla."]);
                }
                $stage->delete();
            }
        });

        return $this->index();
    }
}
