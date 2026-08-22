<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Http\Request;

class AuditController extends Controller
{
    public function index(Request $request, CurrentCompany $current)
    {
        $companyId = $current->id();
        $filters = $request->validate(['event' => ['nullable', 'max:80'], 'actor_id' => ['nullable', 'integer'], 'from' => ['nullable', 'date'], 'until' => ['nullable', 'date']]);

        $query = AuditLog::query()->where('company_id', $companyId)->with('actor')->orderByDesc('created_at');
        if (! empty($filters['event'])) {
            $query->where('event', 'like', '%'.$filters['event'].'%');
        }
        if (! empty($filters['actor_id'])) {
            $query->where('actor_id', (int) $filters['actor_id']);
        }
        if (! empty($filters['from'])) {
            $query->whereDate('created_at', '>=', $filters['from']);
        }
        if (! empty($filters['until'])) {
            $query->whereDate('created_at', '<=', $filters['until']);
        }
        $logs = $query->paginate(50)->withQueryString();

        return view('audit.index', [
            'logs' => $logs,
            'filters' => $filters,
            'actors' => User::whereIn('id', AuditLog::where('company_id', $companyId)->select('actor_id'))->get(),
            'eventSummary' => AuditLog::where('company_id', $companyId)->selectRaw('event, COUNT(*) as total')->groupBy('event')->orderByDesc('total')->limit(12)->pluck('total', 'event'),
        ]);
    }
}
