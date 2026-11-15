<?php

namespace App\Http\Controllers\Api;

use App\Actions\SaveTemplate;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Template;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** OneSignal-compatible templates API. */
class TemplateController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $project = $this->project($request);
        $limit = min(50, max(1, (int) $request->query('limit', 50)));
        $offset = max(0, (int) $request->query('offset', 0));
        $query = $project->templates()->orderBy('name');

        return response()->json([
            'total_count' => (clone $query)->count(),
            'offset' => $offset,
            'limit' => $limit,
            'templates' => $query->skip($offset)->take($limit)->get()->map(fn (Template $template) => $this->present($template))->all(),
        ]);
    }

    public function store(Request $request, SaveTemplate $save): JsonResponse
    {
        $template = $save->handle($this->project($request), $request->all());

        return response()->json($this->present($template));
    }

    public function show(Request $request, string $id): JsonResponse
    {
        return response()->json($this->present($this->template($request, $id)));
    }

    public function update(Request $request, string $id, SaveTemplate $save): JsonResponse
    {
        $template = $save->handle($this->project($request), $request->all(), $this->template($request, $id));

        return response()->json($this->present($template));
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $this->template($request, $id)->delete();

        return response()->json(['success' => true]);
    }

    protected function project(Request $request): Project
    {
        return $request->attributes->get('project');
    }

    protected function template(Request $request, string $id): Template
    {
        $template = $this->project($request)->templates()->find($id);
        abort_unless($template, 404, 'Template not found');

        return $template;
    }

    /** @return array<string, mixed> */
    protected function present(Template $template): array
    {
        return [
            'id' => $template->id,
            'app_id' => $this->projectOf($template)->app_id,
            'name' => $template->name,
            'headings' => $template->headings,
            'subtitle' => $template->subtitle,
            'contents' => $template->contents,
            ...($template->options ?? []),
            'created_at' => $template->created_at?->getTimestamp(),
            'updated_at' => $template->updated_at?->getTimestamp(),
        ];
    }

    protected function projectOf(Template $template): Project
    {
        return $template->relationLoaded('project') ? $template->project : $template->project()->first();
    }
}
