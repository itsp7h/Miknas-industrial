<?php

namespace App\Http\Controllers\Api\Settings;

use App\Http\Controllers\Controller;
use App\Http\Resources\CompanyResource;
use App\Models\Settings\Company;
use App\Models\Settings\Department;
use App\Support\ImageDataUrl;
use Illuminate\Http\Request;

class CompanyController extends Controller
{
    public function index()
    {
        $companies = Company::with(['departments' => fn ($query) => $query->orderBy('name')])
            ->withCount('projects')
            ->orderBy('name')
            ->get();

        return CompanyResource::collection($companies)->additional([
            'meta' => [
                'total_companies' => $companies->count(),
                'total_departments' => $companies->sum(fn ($company) => $company->departments->count()),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $company = Company::create($request->validate([
            'name' => 'required|string|max:255|unique:settings_companies,name',
        ]) + ['is_active' => true]);

        return (new CompanyResource($this->loaded($company)))->response()->setStatusCode(201);
    }

    public function update(Request $request, Company $company)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255|unique:settings_companies,name,'.$company->id,
        ]);

        $company->update($data + ['is_active' => $request->boolean('is_active', true)]);

        return new CompanyResource($this->loaded($company));
    }

    /**
     * settings_projects.company_id is `on delete set null`, so deleting a company
     * that still owns projects silently orphaned every one of them — they stayed
     * in the list with no company at all. The Blade page did this without a word.
     * Departments do cascade, which is intended: they belong to the company.
     */
    public function destroy(Company $company)
    {
        $projectCount = $company->projects()->count();

        abort_if(
            $projectCount > 0,
            422,
            "{$projectCount} project(s) still belong to this company. Move or delete them first."
        );

        $id = $company->id;
        $company->delete();

        return response()->json(['deleted' => true, 'id' => $id]);
    }

    /**
     * Upload or replace a company's logo or stamp. `{image}` is `logo` or
     * `stamp`; the route constrains it, and Company::IMAGES maps it to a column.
     */
    public function updateImage(Request $request, Company $company, string $image)
    {
        $label = ucfirst($image);
        $data = $request->validate([
            'image' => ['required', 'string', function ($attribute, $value, $fail) use ($label) {
                if (! ImageDataUrl::isValid($value)) {
                    $fail("The {$label} must be a PNG or JPEG image under 512 KB.");
                }
            }],
        ]);

        $company->forceFill([Company::IMAGES[$image] => $data['image']])->save();

        return (new CompanyResource($this->loaded($company)))
            ->additional(['message' => "{$label} saved for {$company->name}."]);
    }

    public function destroyImage(Company $company, string $image)
    {
        $company->forceFill([Company::IMAGES[$image] => null])->save();

        return (new CompanyResource($this->loaded($company)))
            ->additional(['message' => ucfirst($image)." removed from {$company->name}."]);
    }

    public function storeDepartment(Request $request, Company $company)
    {
        $company->departments()->create($request->validate([
            // Two departments of the same name inside one company are
            // indistinguishable in every picker that lists them.
            'name' => 'required|string|max:255|unique:settings_departments,name,NULL,id,company_id,'.$company->id,
        ]) + ['is_active' => true]);

        return (new CompanyResource($this->loaded($company)))->response()->setStatusCode(201);
    }

    public function updateDepartment(Request $request, Company $company, Department $department)
    {
        $this->abortUnlessOwned($company, $department);

        $data = $request->validate([
            'name' => 'required|string|max:255|unique:settings_departments,name,'.$department->id.',id,company_id,'.$company->id,
        ]);

        $department->update($data + ['is_active' => $request->boolean('is_active', true)]);

        return new CompanyResource($this->loaded($company));
    }

    public function destroyDepartment(Company $company, Department $department)
    {
        $this->abortUnlessOwned($company, $department);

        $department->delete();

        return new CompanyResource($this->loaded($company));
    }

    /**
     * Both ids come from the URL, so a mismatched pair would otherwise let a
     * department be edited through a company it does not belong to.
     */
    private function abortUnlessOwned(Company $company, Department $department): void
    {
        abort_unless($department->company_id === $company->id, 404);
    }

    /** Department writes return the whole company so the card re-renders in one go. */
    private function loaded(Company $company): Company
    {
        return $company->load(['departments' => fn ($query) => $query->orderBy('name')])
            ->loadCount('projects');
    }
}
