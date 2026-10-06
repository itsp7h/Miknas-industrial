<?php

namespace App\Http\Controllers\Api\Settings;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProjectSettingResource;
use App\Models\Settings\Company;
use App\Models\Settings\Location;
use App\Models\Settings\ProjectSetting;
use App\Services\ProjectImportService;
use App\Services\ProjectTemplateGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProjectController extends Controller
{
    public function index()
    {
        $projects = ProjectSetting::with([
            'company',
            'locations' => fn ($query) => $query->orderBy('name'),
        ])->orderBy('name')->get();

        return ProjectSettingResource::collection($projects)->additional([
            // The company list feeds the "Company" pickers on this page, so it
            // travels with the projects rather than needing a second request.
            'companies' => Company::orderBy('name')->get(['id', 'name']),
            'meta' => [
                'total_projects' => $projects->count(),
                'active_projects' => $projects->where('is_active', true)->count(),
                'total_locations' => $projects->sum(fn ($project) => $project->locations->count()),
                'total_companies' => Company::count(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $project = ProjectSetting::create($request->validate([
            'name' => 'required|string|max:255|unique:settings_projects,name',
            'company_id' => 'nullable|exists:settings_companies,id',
        ]) + ['is_active' => true]);

        return (new ProjectSettingResource($this->loaded($project)))->response()->setStatusCode(201);
    }

    public function update(Request $request, ProjectSetting $project)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255|unique:settings_projects,name,'.$project->id,
            'company_id' => 'nullable|exists:settings_companies,id',
        ]);

        $project->update([
            'name' => $data['name'],
            // A project can genuinely be moved back to no company, so an
            // explicit null has to survive rather than falling back to the
            // current value the way the Blade controller did.
            'company_id' => $data['company_id'] ?? null,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return new ProjectSettingResource($this->loaded($project));
    }

    /**
     * settings_locations.project_id is `on delete set null`, so the Blade page's
     * delete left every location behind with no project — invisible everywhere
     * and impossible to reach. A location belongs to its project, so they go too.
     */
    public function destroy(ProjectSetting $project)
    {
        $id = $project->id;

        DB::transaction(function () use ($project) {
            $project->locations()->delete();
            $project->delete();
        });

        return response()->json(['deleted' => true, 'id' => $id]);
    }

    /**
     * What a project has been charged: every consumable line of a confirmed
     * GRN that names it. A consumable never reaches stock, so this is the one
     * place it is accounted for.
     *
     * The rate is the LPO line's, read raw: grn_items.unit_cost is cast to two
     * decimals and the GRN form copies it through that cast, so a 0.006 BHD
     * rate was saved there as 0.010. The LPO line still holds the 0.006.
     */
    public function costs(ProjectSetting $project): JsonResponse
    {
        $lines = DB::table('grn_items')
            ->join('goods_receipt_notes', 'goods_receipt_notes.id', '=', 'grn_items.goods_receipt_note_id')
            ->leftJoin('purchase_orders', 'purchase_orders.id', '=', 'goods_receipt_notes.purchase_order_id')
            ->leftJoin('purchase_order_items', 'purchase_order_items.id', '=', 'grn_items.purchase_order_item_id')
            ->leftJoin('items', 'items.id', '=', 'grn_items.item_id')
            ->leftJoin('suppliers', 'suppliers.id', '=', 'goods_receipt_notes.supplier_id')
            ->where('grn_items.project_id', $project->id)
            ->where('grn_items.type', 'consumable')
            ->where('goods_receipt_notes.status', 'confirmed')
            ->orderByDesc('goods_receipt_notes.received_date')
            ->orderByDesc('grn_items.id')
            ->get([
                'grn_items.id',
                'goods_receipt_notes.id as grn_id',
                'goods_receipt_notes.grn_number',
                'goods_receipt_notes.received_date',
                'purchase_orders.id as purchase_order_id',
                'purchase_orders.po_number',
                'suppliers.name as supplier_name',
                'items.item_code',
                'items.item_name',
                'items.unit_of_measure',
                'grn_items.quantity_received',
                'grn_items.unit_cost',
                'purchase_order_items.rate',
            ])
            ->map(function ($line) {
                $rate = (float) ($line->rate ?? $line->unit_cost ?? 0);
                $quantity = (float) $line->quantity_received;

                return [
                    'id' => $line->id,
                    'grn_id' => $line->grn_id,
                    'grn_number' => $line->grn_number,
                    'received_date' => $line->received_date ? substr($line->received_date, 0, 10) : null,
                    'purchase_order_id' => $line->purchase_order_id,
                    'po_number' => $line->po_number,
                    'supplier_name' => $line->supplier_name,
                    'item_code' => $line->item_code,
                    'item_name' => $line->item_name,
                    'unit_of_measure' => $line->unit_of_measure,
                    'quantity' => $quantity,
                    'rate' => $rate,
                    'amount' => round($quantity * $rate, 3),
                ];
            });

        return response()->json([
            'data' => [
                'id' => $project->id,
                'name' => $project->name,
                'company_name' => $project->company?->name,
                'is_active' => (bool) $project->is_active,
            ],
            'lines' => $lines->values(),
            'meta' => [
                'total' => round($lines->sum('amount'), 3),
                'line_count' => $lines->count(),
                'grn_count' => $lines->pluck('grn_id')->unique()->count(),
            ],
        ]);
    }

    public function storeLocation(Request $request, ProjectSetting $project)
    {
        $project->locations()->create($this->validatedLocation($request) + ['is_active' => true]);

        return (new ProjectSettingResource($this->loaded($project)))->response()->setStatusCode(201);
    }

    public function updateLocation(Request $request, ProjectSetting $project, Location $location)
    {
        $this->abortUnlessOwned($project, $location);

        $location->update(
            $this->validatedLocation($request) + ['is_active' => $request->boolean('is_active', true)]
        );

        return new ProjectSettingResource($this->loaded($project));
    }

    public function destroyLocation(ProjectSetting $project, Location $location)
    {
        $this->abortUnlessOwned($project, $location);

        $location->delete();

        return new ProjectSettingResource($this->loaded($project));
    }

    public function import(Request $request): JsonResponse
    {
        $request->validate(['file' => 'required|file|mimes:xlsx,xls|max:10240']);

        try {
            $stats = app(ProjectImportService::class)->import($request->file('file')->getPathname());
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => $this->importMessage($stats), 'stats' => $stats]);
    }

    public function downloadTemplate(): BinaryFileResponse
    {
        $path = app(ProjectTemplateGenerator::class)->write(storage_path('app/projects_template.xlsx'));

        return response()->download($path, 'projects_template.xlsx');
    }

    private function importMessage(array $stats): string
    {
        $parts = array_filter([
            $stats['projects_created'] ? "{$stats['projects_created']} project(s)" : null,
            $stats['locations_created'] ? "{$stats['locations_created']} location(s)" : null,
            $stats['departments_created'] ? "{$stats['departments_created']} department(s)" : null,
            $stats['companies_created'] ? "{$stats['companies_created']} new company(s)" : null,
        ]);

        if ($parts) {
            return 'Imported: '.implode(', ', $parts)
                .($stats['skipped'] ? " — {$stats['skipped']} row(s) skipped" : '');
        }

        return 'Nothing new to import'.($stats['skipped'] ? " ({$stats['skipped']} rows already exist)" : '');
    }

    private function validatedLocation(Request $request): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'nullable|string|max:500',
            'road' => 'nullable|string|max:255',
            'block' => 'nullable|string|max:20',
            'city' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:255',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
        ]);

        return [
            'name' => $data['name'],
            'address' => $data['address'] ?? null,
            'road' => $data['road'] ?? null,
            'block' => $data['block'] ?? null,
            'city' => $data['city'] ?? null,
            'country' => $data['country'] ?? null,
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
        ];
    }

    private function abortUnlessOwned(ProjectSetting $project, Location $location): void
    {
        abort_unless($location->project_id === $project->id, 404);
    }

    /** Location writes return the whole project so the card re-renders in one go. */
    private function loaded(ProjectSetting $project): ProjectSetting
    {
        return $project->load(['company', 'locations' => fn ($query) => $query->orderBy('name')]);
    }
}
