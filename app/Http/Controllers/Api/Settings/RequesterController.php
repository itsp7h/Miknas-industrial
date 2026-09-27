<?php

namespace App\Http\Controllers\Api\Settings;

use App\Events\RequesterDeleted;
use App\Events\RequesterSaved;
use App\Http\Controllers\Controller;
use App\Http\Resources\RequesterResource;
use App\Models\Settings\Company;
use App\Models\Settings\Requester;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * System → Requested By: the people the MPR form's Requested By offers, each
 * mapped to the companies they raise requests for.
 */
class RequesterController extends Controller
{
    public function index()
    {
        $requesters = Requester::with('companies')->orderBy('name')->get();

        return RequesterResource::collection($requesters)->additional([
            'meta' => [
                // What the company checkboxes offer.
                'companies' => Company::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $requester = DB::transaction(function () use ($data) {
            $requester = Requester::create(['name' => $data['name']]);
            $requester->companies()->sync($data['company_ids']);

            return $requester;
        });

        event(new RequesterSaved($requester));

        return (new RequesterResource($requester->load('companies')))
            ->additional(['message' => "{$requester->name} added."])
            ->response()->setStatusCode(201);
    }

    public function update(Request $request, Requester $requester)
    {
        $data = $this->validated($request, $requester);

        DB::transaction(function () use ($requester, $data) {
            $requester->update(['name' => $data['name']]);
            $requester->companies()->sync($data['company_ids']);
        });

        event(new RequesterSaved($requester));

        return (new RequesterResource($requester->load('companies')))
            ->additional(['message' => "{$requester->name} saved."]);
    }

    /**
     * Requests store the name itself, so deleting a requester never touches a
     * request already raised; it only stops the form offering them.
     */
    public function destroy(Requester $requester)
    {
        $id = $requester->id;
        $name = $requester->name;
        $requester->delete();

        event(new RequesterDeleted($id));

        return response()->json(['deleted' => true, 'id' => $id, 'message' => "{$name} removed."]);
    }

    private function validated(Request $request, ?Requester $existing = null): array
    {
        $request->merge(['name' => trim((string) $request->input('name'))]);

        return $request->validate([
            'name' => 'required|string|max:255|unique:requesters,name'.($existing ? ','.$existing->id : ''),
            // At least one: a requester in no company would be offered nowhere.
            'company_ids' => 'required|array|min:1',
            'company_ids.*' => 'integer|distinct|exists:settings_companies,id',
        ], [
            'company_ids.required' => 'Choose at least one company.',
            'company_ids.min' => 'Choose at least one company.',
        ]);
    }
}
