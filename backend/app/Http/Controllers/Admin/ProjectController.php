<?php

namespace App\Http\Controllers\Admin;

use App\Models\Project;
use App\Services\ProjectAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProjectController extends AdminController
{
    public const TIMEZONES = [
        'Asia/Jakarta' => 'WIB (Asia/Jakarta)',
        'Asia/Makassar' => 'WITA (Asia/Makassar)',
        'Asia/Jayapura' => 'WIT (Asia/Jayapura)',
    ];

    public function index(): View
    {
        $this->superAdminOnly();

        $projects = Project::query()
            ->withCount([
                'employees as active_employees_count' => fn ($q) => $q->where('project_employee.start_date', '<=', now())
                    ->where(fn ($w) => $w->whereNull('project_employee.end_date')->orWhere('project_employee.end_date', '>=', now())),
                'locations',
                'shifts',
            ])
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();

        return view('projects.index', [
            'page' => 'projects',
            'title' => 'Daftar Proyek',
            'projects' => $projects,
            'timezones' => self::TIMEZONES,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->superAdminOnly();
        $data = $this->validated($request);

        $project = DB::transaction(function () use ($data) {
            $project = Project::create($data);
            // Shift default agar proyek baru langsung bisa dipakai
            $project->shifts()->create([
                'name' => 'Jam Kerja Standard',
                'start_time' => '08:00',
                'end_time' => '17:00',
                'is_default' => true,
            ]);

            return $project;
        });

        session(['project_id' => $project->id]);

        return $this->saved('Proyek dibuat. Lanjutkan dengan menambah titik lokasi & karyawan.', ['redirect' => route('locations.index')]);
    }

    public function update(Request $request, Project $project): JsonResponse
    {
        $this->superAdminOnly();
        $project->update($this->validated($request, $project));

        return $this->saved('Proyek diperbarui.');
    }

    public function switch(Project $project, ProjectAccess $access): RedirectResponse
    {
        abort_unless($access->canView(auth()->user(), $project), 403);
        session(['project_id' => $project->id]);

        $back = url()->previous();

        return redirect()->to(str_contains($back, '/attendances/') ? route('dashboard') : $back);
    }

    public static function rules(?Project $project = null): array
    {
        return [
            'code' => ['required', 'string', 'max:30', 'alpha_dash', Rule::unique('projects', 'code')->ignore($project)],
            'name' => ['required', 'string', 'max:255'],
            'client_name' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:1000'],
            'contract_start_date' => ['nullable', 'date'],
            'contract_end_date' => ['nullable', 'date', 'after_or_equal:contract_start_date'],
            'timezone' => ['required', Rule::in(array_keys(self::TIMEZONES))],
            'is_active' => ['boolean'],
        ];
    }

    private function validated(Request $request, ?Project $project = null): array
    {
        return $request->validate(self::rules($project), [], [
            'code' => 'kode proyek', 'name' => 'nama proyek', 'contract_start_date' => 'tanggal mulai kontrak',
            'contract_end_date' => 'tanggal akhir kontrak', 'timezone' => 'zona waktu',
        ]);
    }
}
