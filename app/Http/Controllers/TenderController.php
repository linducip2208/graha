<?php

namespace App\Http\Controllers;

use App\Models\Competitor;
use App\Models\Customer;
use App\Models\Project;
use App\Models\Tender;
use App\Services\NumberSequenceService;
use App\Services\TenderIntelligenceService;
use App\Services\TenderService;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Http\Request;

class TenderController extends Controller
{
    public function index(Request $r, CurrentCompany $current, TenderService $service, TenderIntelligenceService $intelligence)
    {
        $view = $r->query('view', 'table');
        $companyId = $current->id();

        // Saved view via query string: filter status dapat dibagikan sebagai URL.
        $listQuery = Tender::where('company_id', $companyId);
        if ($status = $r->query('status')) {
            $listQuery->where('status', $status);
        }

        return view('tenders.index', ['tenders' => $listQuery->with(['customer', 'outcome'])->latest()->paginate(20), 'customers' => Customer::where('company_id', $companyId)->orderBy('name')->get(), 'metrics' => $service->metrics($companyId, now()->year), 'intel' => $intelligence->stats($companyId), 'competitors' => Competitor::where('company_id', $companyId)->orderBy('name')->get(), 'recentTenders' => Tender::where('company_id', $companyId)->orderByDesc('id')->limit(20)->get(),
            'kanban' => $view === 'kanban' ? $this->kanban($companyId) : null,
            'activeStatus' => $r->query('status'),
        ]);
    }

    /** Papan kanban pipeline tender per status. */
    private function kanban(int $companyId): array
    {
        $tenders = Tender::where('company_id', $companyId)->with('customer:id,name')->orderByDesc('id')->get();
        $columns = [];

        foreach (['preparation' => 'Persiapan', 'bidding' => 'Bidding', 'won' => 'Menang', 'lost' => 'Kalah'] as $status => $label) {
            $columns[] = ['label' => $label, 'items' => $tenders->where('status', $status)->map(fn ($t) => [
                'title' => $t->project_name,
                'subtitle' => $t->customer?->name,
                'meta' => $t->bid_value ? 'Rp '.number_format((float) $t->bid_value / 1_000_000, 0).' jt' : $t->number,
                'href' => '/admin/tenders/'.$t->id,
            ])->values()];
        }

        return $columns;
    }

    /** Workspace detail tender: estimasi, peserta, kompetitor, outcome, lessons. */
    public function show(Request $r, Tender $tender, CurrentCompany $current)
    {
        abort_unless($tender->company_id === $current->id(), 404);
        $tab = $r->query('tab', 'overview');

        return view('tenders.show', [
            'tender' => $tender->load(['customer', 'outcome', 'participants', 'estimate.items']),
            'activeTab' => $tab,
            'competitors' => Competitor::where('company_id', $current->id())->orderBy('name')->get(),
            'project' => Project::where('source_tender_id', $tender->id)->first(),
        ]);
    }

    public function storeCompetitor(Request $r, CurrentCompany $current, TenderIntelligenceService $intelligence)
    {
        $data = $r->validate(['code' => ['required', 'max:30'], 'name' => ['required', 'max:200'], 'notes' => ['nullable', 'max:500']]);
        abort_unless(Competitor::where('company_id', $current->id())->where('code', $data['code'])->doesntExist(), 422, 'Kode kompetitor sudah dipakai.');
        $intelligence->registerCompetitor($current->id(), $data);

        return back()->with('status', 'Kompetitor terdaftar.');
    }

    public function storeParticipant(Request $r, CurrentCompany $current, TenderIntelligenceService $intelligence)
    {
        $data = $r->validate(['tender_id' => ['required', 'integer'], 'competitor_id' => ['nullable', 'integer'], 'name' => ['required', 'max:200'], 'bid_value' => ['nullable', 'decimal:0,2'], 'rank' => ['nullable', 'integer', 'min:1'], 'is_winner' => ['nullable', 'boolean']]);
        $tender = Tender::where('company_id', $current->id())->findOrFail($data['tender_id']);
        if (! empty($data['competitor_id'])) {
            $data['name'] = Competitor::where('company_id', $current->id())->find($data['competitor_id'])->name;
        }
        unset($data['company_id']);
        $intelligence->addParticipant($tender, collect($data)->except('tender_id')->merge(['tender_id' => $tender->id])->all(), $r->user());

        return back()->with('status', 'Peserta tender dicatat.');
    }

    public function storeCustomer(Request $r, CurrentCompany $current)
    {
        $data = $r->validate(['code' => ['required', 'max:30', 'unique:customers,code,NULL,id,company_id,'.$current->id()], 'name' => ['required', 'max:200'], 'payment_term_days' => ['nullable', 'integer', 'between:0,365']]);
        Customer::create([...$data, 'payment_term_days' => $data['payment_term_days'] ?? 30, 'company_id' => $current->id()]);

        return back()->with('status', 'Pelanggan ditambahkan.');
    }

    public function store(Request $r, CurrentCompany $current, NumberSequenceService $numbers)
    {
        $data = $r->validate(['customer_id' => ['required', 'integer', 'exists:customers,id'], 'project_name' => ['required', 'max:200'], 'location' => ['nullable', 'max:200'], 'bid_value' => ['nullable', 'decimal:0,2', 'min:0']]);
        abort_unless(Customer::where('company_id', $current->id())->whereKey($data['customer_id'])->exists(), 422);
        Tender::create([...$data, 'company_id' => $current->id(), 'number' => $numbers->next($current->id(), 'tender'), 'year' => now()->year, 'status' => 'preparation', 'created_by' => $r->user()->id]);

        return back()->with('status', 'Tender ditambahkan.');
    }

    public function outcome(Request $r, Tender $tender, CurrentCompany $current, TenderService $service)
    {
        abort_unless($tender->company_id === $current->id(), 404);
        $data = $r->validate(['outcome' => ['required', 'in:won,lost'], 'announced_at' => ['required', 'date'], 'contract_value' => ['nullable', 'decimal:0,2', 'min:0'], 'winner_name' => ['nullable', 'max:200'], 'winning_bid_value' => ['nullable', 'decimal:0,2', 'min:0'], 'primary_reason' => ['nullable', 'max:200'], 'lesson_learned' => ['nullable', 'max:2000']]);
        $outcome = $data['outcome'];
        unset($data['outcome']);
        $service->recordOutcome($tender, $r->user(), $outcome, $data);

        return back()->with('status', 'Hasil tender dicatat.');
    }

    public function convert(Tender $tender, CurrentCompany $current, TenderService $service, Request $r)
    {
        abort_unless($tender->company_id === $current->id(), 404);
        $service->convertWonToProject($tender, $r->user());

        return back()->with('status', 'Tender dikonversi menjadi proyek.');
    }
}
