<?php

namespace App\Services;

use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProjectClosingService
{
    public function __construct(private AuditTrail $audit) {}

    public function close(Project $project, User $actor): Project
    {
        return DB::transaction(function () use ($project, $actor) {
            $project = Project::lockForUpdate()->findOrFail($project->id);
            $unfinished = $project->boredPiles()->whereNotIn('status', ['completed', 'rejected'])->count();
            throw_if($unfinished > 0, ValidationException::withMessages(['project' => "Masih ada {$unfinished} titik belum selesai."]));
            $project->update(['status' => 'closed', 'closed_at' => now()]);
            $this->audit->record($project->company_id, $actor->id, 'project.closed', $project);

            return $project;
        }, 3);
    }
}
