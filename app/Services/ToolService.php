<?php

namespace App\Services;

use App\Models\Project;
use App\Models\Tool;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ToolService
{
    public function __construct(private AuditTrail $audit) {}

    public function checkOut(Tool $tool, User $toUser, ?int $projectId, ?Carbon $expectedReturn, string $notes, User $actor): Tool
    {
        return DB::transaction(function () use ($tool, $toUser, $projectId, $expectedReturn, $notes, $actor) {
            $tool = Tool::lockForUpdate()->findOrFail($tool->id);
            throw_unless($actor->companies()->whereKey($tool->company_id)->where('company_user.is_active', true)->exists(), ValidationException::withMessages(['company' => 'Anda bukan anggota aktif perusahaan ini.']));
            throw_unless($tool->status === 'available', ValidationException::withMessages(['status' => "Alat sedang {$tool->status}, tidak dapat dipinjam."]));
            throw_unless($toUser->companies()->whereKey($tool->company_id)->where('company_user.is_active', true)->exists(), ValidationException::withMessages(['user' => 'Penerima bukan anggota perusahaan ini.']));
            if ($projectId !== null) {
                throw_unless(Project::where('company_id', $tool->company_id)->whereKey($projectId)->exists(), ValidationException::withMessages(['project' => 'Proyek tidak valid.']));
            }
            $tool->update(['status' => 'checked_out', 'checked_out_to' => $toUser->id, 'checked_out_at' => now(), 'expected_return_at' => $expectedReturn]);
            $tool->movements()->create(['type' => 'check_out', 'user_id' => $toUser->id, 'project_id' => $projectId, 'occurred_at' => now(), 'expected_return_at' => $expectedReturn, 'notes' => $notes, 'recorded_by' => $actor->id]);
            $this->audit->record($tool->company_id, $actor->id, 'inventory.tool_checked_out', $tool);

            return $tool->refresh();
        }, 3);
    }

    public function checkIn(Tool $tool, string $conditionNote, User $actor): Tool
    {
        return DB::transaction(function () use ($tool, $conditionNote, $actor) {
            $tool = Tool::lockForUpdate()->findOrFail($tool->id);
            throw_unless($tool->status === 'checked_out', ValidationException::withMessages(['status' => 'Alat tidak sedang dipinjam.']));
            $holderId = (int) $tool->checked_out_to;
            $tool->update(['status' => 'available', 'checked_out_to' => null, 'checked_out_at' => null, 'expected_return_at' => null]);
            $tool->movements()->create(['type' => 'check_in', 'user_id' => $holderId, 'occurred_at' => now(), 'notes' => $conditionNote, 'recorded_by' => $actor->id]);
            $this->audit->record($tool->company_id, $actor->id, 'inventory.tool_checked_in', $tool);

            return $tool->refresh();
        }, 3);
    }

    public function markLost(Tool $tool, string $notes, User $actor): Tool
    {
        return DB::transaction(function () use ($tool, $notes, $actor) {
            $tool = Tool::lockForUpdate()->findOrFail($tool->id);
            throw_unless(in_array($tool->status, ['available', 'checked_out'], true), ValidationException::withMessages(['status' => 'Status alat sudah final.']));
            $holderId = $tool->checked_out_to;
            $tool->update(['status' => 'lost', 'checked_out_to' => null, 'checked_out_at' => null]);
            $tool->movements()->create(['type' => 'lost_reported', 'user_id' => $holderId ?? $actor->id, 'occurred_at' => now(), 'notes' => $notes, 'recorded_by' => $actor->id]);
            $this->audit->record($tool->company_id, $actor->id, 'inventory.tool_lost', $tool);

            return $tool->refresh();
        }, 3);
    }
}
