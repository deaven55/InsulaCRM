@extends('layouts.app')

@section('title', 'Wholesale OS')
@section('page-title', 'Wholesale OS')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <div class="text-secondary">Acquisition → underwriting → compliance → disposition</div>
        <h2 class="mb-0">Deal Command Center</h2>
    </div>
    <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary">CRM Dashboard</a>
</div>

<form method="GET" action="{{ route('wholesale.dashboard') }}" class="card mb-4">
    <div class="card-body">
        <div class="row g-2">
            <div class="col-md-2">
                <label class="form-label">State</label>
                <input name="state" maxlength="2" value="{{ $filters['state'] }}" class="form-control" placeholder="NC">
            </div>
            <div class="col-md-3">
                <label class="form-label">County</label>
                <input name="county" value="{{ $filters['county'] }}" class="form-control" placeholder="Catawba">
            </div>
            <div class="col-md-3">
                <label class="form-label">City</label>
                <input name="city" value="{{ $filters['city'] }}" class="form-control" placeholder="Hickory">
            </div>
            <div class="col-md-2">
                <label class="form-label">Grade</label>
                <select name="ranking_grade" class="form-select">
                    <option value="">Any grade</option>
                    @foreach(['A','B','C','D'] as $grade)
                        <option value="{{ $grade }}" @selected($filters['ranking_grade'] === $grade)>{{ $grade }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Decision</label>
                <select name="decision" class="form-select">
                    <option value="">Any decision</option>
                    @foreach(['BUY','NEGOTIATE','PASS','REVIEW'] as $decision)
                        <option value="{{ $decision }}" @selected($filters['decision'] === $decision)>{{ $decision }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Stage</label>
                <input name="stage" value="{{ $filters['stage'] }}" class="form-control" placeholder="active">
            </div>
            <div class="col-md-3">
                <label class="form-label">Sort</label>
                <select name="sort" class="form-select">
                    @foreach(['priority' => 'Priority','newest' => 'Newest','closing_soon' => 'Closing soon','stage_age' => 'Stage age'] as $value => $label)
                        <option value="{{ $value }}" @selected($filters['sort'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">ARV</label>
                <select name="has_arv" class="form-select">
                    <option value="">Any</option>
                    <option value="true" @selected($filters['has_arv'] === 'true')>Has ARV</option>
                    <option value="false" @selected($filters['has_arv'] === 'false')>Missing ARV</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Buyer match</label>
                <select name="has_buyer_match" class="form-select">
                    <option value="">Any</option>
                    <option value="true" @selected($filters['has_buyer_match'] === 'true')>Matched</option>
                    <option value="false" @selected($filters['has_buyer_match'] === 'false')>Unmatched</option>
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button class="btn btn-primary w-100">Search queue</button>
            </div>
        </div>
    </div>
</form>

<div class="row g-3 mb-4">
    @foreach([
        ['label'=>'Deals','value'=>$summary['total']],
        ['label'=>'Ranked','value'=>$summary['ranked']],
        ['label'=>'Ready for offer','value'=>$summary['ready_for_offer']],
        ['label'=>'Ready for disposition','value'=>$summary['ready_for_disposition']],
        ['label'=>'Compliance review','value'=>$summary['review']],
    ] as $kpi)
    <div class="col-6 col-xl">
        <div class="card h-100">
            <div class="card-body">
                <div class="text-secondary small">{{ $kpi['label'] }}</div>
                <div class="h2 mb-0">{{ number_format($kpi['value']) }}</div>
            </div>
        </div>
    </div>
    @endforeach
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table table-vcenter card-table">
            <thead>
                <tr>
                    <th>Priority</th>
                    <th>Property</th>
                    <th>Seller</th>
                    <th>Underwriting</th>
                    <th>Buyer demand</th>
                    <th>Compliance</th>
                    <th>Next work</th>
                </tr>
            </thead>
            <tbody>
            @forelse($items as $item)
                @php
                    $score = $item['ranking']['score'];
                    $grade = $item['ranking']['grade'];
                    $compliance = $item['compliance']['status'] ?? 'REVIEW';
                    $missing = $item['work_queue']['missing'];
                @endphp
                <tr>
                    <td style="min-width:110px">
                        <div class="fw-bold">{{ $score !== null ? number_format($score, 0) : '—' }}</div>
                        <span class="badge {{ $grade === 'A' ? 'bg-green-lt' : ($grade === 'B' ? 'bg-blue-lt' : ($grade === 'C' ? 'bg-yellow-lt' : 'bg-secondary-lt')) }}">
                            {{ $grade ?? 'UNRANKED' }}
                        </span>
                    </td>
                    <td style="min-width:240px">
                        <div class="fw-semibold">{{ $item['property']['address'] ?: 'Address pending' }}</div>
                        <div class="text-secondary small">
                            {{ $item['property']['city'] }}{{ $item['property']['city'] ? ', ' : '' }}{{ $item['property']['state'] }}
                            {{ $item['property']['county'] ? ' · '.$item['property']['county'].' County' : '' }}
                        </div>
                        <div class="text-secondary small">{{ $item['property']['property_type'] ?: 'Property type pending' }}</div>
                    </td>
                    <td style="min-width:180px">
                        <div>{{ $item['seller']['name'] ?: 'Seller pending' }}</div>
                        <div class="text-secondary small">{{ $item['seller']['phone'] ?: 'No phone' }}</div>
                        @if($item['seller']['do_not_contact'])
                            <span class="badge bg-red-lt">DNC</span>
                        @endif
                    </td>
                    <td style="min-width:210px">
                        <div class="small">ARV <strong>{{ $item['underwriting']['arv'] !== null ? '$'.number_format((float)$item['underwriting']['arv']) : '—' }}</strong></div>
                        <div class="small">Repairs <strong>{{ $item['underwriting']['repair_estimate'] !== null ? '$'.number_format((float)$item['underwriting']['repair_estimate']) : '—' }}</strong></div>
                        <div class="small">Offer <strong>{{ $item['underwriting']['offer_target'] !== null ? '$'.number_format((float)$item['underwriting']['offer_target']) : '—' }}</strong></div>
                        <span class="badge bg-{{ $item['underwriting']['decision'] === 'BUY' ? 'green' : ($item['underwriting']['decision'] === 'NEGOTIATE' ? 'yellow' : 'secondary') }}-lt">
                            {{ $item['underwriting']['decision'] ?: 'REVIEW' }}
                        </span>
                    </td>
                    <td>
                        <span class="badge bg-blue-lt">{{ $item['buyer_demand']['active_match_count'] }} active</span>
                    </td>
                    <td>
                        <span class="badge bg-yellow-lt">{{ $compliance }}</span>
                    </td>
                    <td style="min-width:180px">
                        @if(count($missing))
                            <div class="small text-secondary">{{ implode(', ', $missing) }}</div>
                        @else
                            <span class="badge bg-green-lt">No missing work</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-secondary py-5">No deals match these filters.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if($paginator->hasPages())
        <div class="card-footer d-flex justify-content-between align-items-center">
            <div class="text-secondary small">
                Showing {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} of {{ $paginator->total() }}
            </div>
            {{ $paginator->withQueryString()->links() }}
        </div>
    @endif
</div>
@endsection
