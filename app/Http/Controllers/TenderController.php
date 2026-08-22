<?php

namespace App\Http\Controllers;

use App\Models\Competitor;
use App\Models\Customer;
use App\Models\Tender;
use App\Services\NumberSequenceService;
use App\Services\TenderIntelligenceService;
use App\Services\TenderService;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Http\Request;

class TenderController extends Controller
{
    public function index(CurrentCompany $current, TenderService $service, TenderIntelligenceService $intelligence)
    {
        return view('tenders.index', ['tenders' => Tender::where('company_id', $current->id())->with(['customer', 'outcome'])->latest()->paginate(20), 'customers' => Customer::where('company_id', $current->id())->orderBy('name')->get(), 'metrics' => $service->metrics($current->id(), now()->year), 'intel' => $intelligence->stats($current->id()), 'competitors' => Competitor::where('company_id', $current->id())->orderBy('name')->get(), 'recentTenders' => Tender::where('company_id', $current->id())->orderByDesc('id')->limit(20)->get()]);
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
