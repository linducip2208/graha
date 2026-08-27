<?php

namespace App\Support;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Department;
use App\Models\Project;
use App\Models\ProjectUserAccess;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Centralized access scope resolution.
 *
 * Permission = apa yang boleh dilakukan (Role → Permission, existing).
 * Scope      = data mana yang boleh diakses (layer ini).
 *
 * Scope per membership (company_user.data_scope):
 *   all_company → seluruh project perusahaan
 *   branch      → hanya project pada branch terkait
 *   department  → hanya project pada branch+department terkait
 *   projects    → hanya project di project_user_access (assignment eksplisit)
 */
class AccessScopeService
{
    /** Membership aktif user pada sebuah company. */
    public function membership(User $user, int $companyId): ?object
    {
        return DB::table('company_user')
            ->where('user_id', $user->id)
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Query Project yang accessible untuk user — pakai ini di index/search/
     * dashboard/report/export agar scope konsisten.
     */
    public function applyToProjectQuery(Builder $query, User $user, int $companyId): Builder
    {
        $query->where('company_id', $companyId);

        $membership = $this->membership($user, $companyId);
        if (! $membership) {
            return $query->whereRaw('1 = 0');
        }

        switch ($membership->data_scope ?? 'all_company') {
            case 'branch':
                if ($membership->scope_branch_id) {
                    $query->where('branch_id', $membership->scope_branch_id);
                }
                break;

            case 'department':
                if ($membership->scope_branch_id) {
                    $query->where('branch_id', $membership->scope_branch_id);
                }
                // Department tidak memetakan langsung ke project; dibatasi branch + assignment eksplisit.
                break;

            case 'projects':
                $ids = ProjectUserAccess::where('user_id', $user->id)
                    ->where('company_id', $companyId)
                    ->whereIn('project_id', Project::where('company_id', $companyId)->select('id'))
                    ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', today()))
                    ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', today()))
                    ->pluck('project_id')
                    ->all();

                $query->whereIn('id', $ids ?: [0]);
                break;
        }

        return $query;
    }

    /**
     * Bolehkah user membuka satu project tertentu?
     */
    public function canAccessProject(User $user, Project $project): bool
    {
        $allowed = $this->applyToProjectQuery(Project::query(), $user, (int) $project->company_id);

        return $allowed->whereKey($project->getKey())->exists();
    }

    /**
     * Guard untuk route model binding project — 404 agar tidak bocor informasi.
     */
    public function authorizeProject(User $user, Project $project): void
    {
        abort_unless($this->canAccessProject($user, $project), 404);
    }

    /**
     * Deskripsi scope untuk UI "Effective Access".
     */
    public function describe(User $user, int $companyId): array
    {
        $membership = $this->membership($user, $companyId);

        if (! $membership) {
            return ['type' => 'none', 'label' => 'Bukan member perusahaan ini'];
        }

        return match ($membership->data_scope ?? 'all_company') {
            'branch' => [
                'type' => 'branch',
                'label' => 'Branch: '.(Branch::find($membership->scope_branch_id)?->name ?? '-'),
            ],
            'department' => [
                'type' => 'department',
                'label' => 'Department: '.(Department::find($membership->scope_department_id)?->name ?? '-')
                    .' @ '.(Branch::find($membership->scope_branch_id)?->name ?? '-'),
            ],
            'projects' => [
                'type' => 'projects',
                'label' => 'Project tertentu ('.ProjectUserAccess::where('company_id', $companyId)->where('user_id', $user->id)->count().' project)',
            ],
            default => ['type' => 'all_company', 'label' => 'Seluruh perusahaan'],
        };
    }

    /**
     * Project IDs yang accessible (untuk filter query child entity).
     * null = tanpa batasan project (all/branch/department scope memakai filter lain).
     */
    public function accessibleProjectIds(User $user, int $companyId): ?array
    {
        $membership = $this->membership($user, $companyId);

        if (! $membership) {
            return [];
        }
        if (($membership->data_scope ?? 'all_company') === 'all_company') {
            return null;
        }

        return $this->applyToProjectQuery(Project::query(), $user, $companyId)
            ->pluck('id')
            ->all();
    }

    /**
     * Terapkan scope project ke query child entity yang punya kolom project_id.
     */
    public function applyToChildQuery(Builder $query, User $user, int $companyId): Builder
    {
        $ids = $this->accessibleProjectIds($user, $companyId);

        return $ids === null ? $query : $query->whereIn('project_id', $ids ?: [0]);
    }
}
