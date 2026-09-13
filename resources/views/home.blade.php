@extends('layouts/contentLayoutMaster')

@section('title', __('Personal page'))

@section('content')
<section class="dashboard-analytics">
  <div class="row mb-2">
    <div class="col-12 col-lg-8">
      <h2 class="mb-0">{{ __('Personal page') }}</h2>
      <p class="text-muted mb-0">{{ __('Welcome back, :name', ['name' => auth()->user()->name]) }}</p>
    </div>
    <div class="col-12 col-lg-4 mt-1 mt-lg-0">
      @can('viewAny', App\Models\ValuationRequest::class)
        <form method="get" action="{{ route('dashboard.valuation-requests.quick-search') }}" class="d-flex gap-1">
          <input
            type="text"
            name="q"
            class="form-control"
            placeholder="{{ __('Quick search by valuation number') }}"
            value="{{ request('q') }}"
            autocomplete="off"
          >
          <button class="btn btn-primary text-nowrap" type="submit">{{ __('Quick search') }}</button>
        </form>
      @endcan
    </div>
  </div>

  {{-- Legacy personal-page KPIs --}}
  <div class="row match-height mb-2">
    <div class="col-lg-4 col-md-6 col-12">
      <div class="card text-white bg-primary">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-start">
            <div>
              <h2 class="text-white mb-0">
                {{ number_format($widgets['approved_count']) }}
                <small class="fs-6 fw-normal">{{ __('valuation unit') }}</small>
              </h2>
              <p class="card-text mb-0">{{ __('Approved valuations') }}</p>
            </div>
            <i data-feather="check-circle" class="font-large-1"></i>
          </div>
        </div>
      </div>
    </div>
    <div class="col-lg-4 col-md-6 col-12">
      <div class="card text-white bg-danger">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-start">
            <div>
              <h2 class="text-white mb-0">
                {{ number_format((float) $widgets['total_valued_area'], 0) }}
                <small class="fs-6 fw-normal">{{ __('m²') }}</small>
              </h2>
              <p class="card-text mb-0">{{ __('Total valued area') }}</p>
            </div>
            <i data-feather="layers" class="font-large-1"></i>
          </div>
        </div>
      </div>
    </div>
    <div class="col-lg-4 col-md-6 col-12">
      <div class="card text-white bg-success">
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-start">
            <div>
              <h2 class="text-white mb-0">
                {{ $widgets['average_turnaround_hours'] !== null ? number_format((float) $widgets['average_turnaround_hours'], 0) : '0' }}
                <small class="fs-6 fw-normal">{{ __('hours short') }}</small>
              </h2>
              <p class="card-text mb-0">{{ __('Average turnaround (hours)') }}</p>
            </div>
            <i data-feather="clock" class="font-large-1"></i>
          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- Quick actions (old personal page buttons) --}}
  <div class="row mb-2">
    <div class="col-12">
      <div class="btn-group w-100 flex-wrap" role="group">
        @can('create', App\Models\ValuationRequest::class)
          <a href="{{ route('dashboard.valuation-requests.create') }}" class="btn btn-primary btn-lg">{{ __('New valuation') }}</a>
        @endcan
        @can('viewAny', App\Models\ValuationRequest::class)
          <a href="{{ route('dashboard.valuation-requests.index') }}" class="btn btn-danger btn-lg">{{ __('Valuations board') }}</a>
        @endcan
        @can('viewAny', App\Models\User::class)
          <a href="{{ route('dashboard.users.index') }}" class="btn btn-success btn-lg">{{ __('Users board') }}</a>
        @endcan
        @can('viewLogs', App\Models\ValuationRequest::class)
          <a href="{{ route('dashboard.valuation-requests.activity-logs') }}" class="btn btn-secondary btn-lg">{{ __('Events board') }}</a>
        @endcan
      </div>
    </div>
  </div>

  {{-- Latest valuations --}}
  @can('viewAny', App\Models\ValuationRequest::class)
    <div class="card mb-2">
      <div class="card-header">
        <h4 class="card-title mb-0">{{ __('Latest valuations') }}</h4>
      </div>
      <div class="table-responsive">
        <table class="table table-striped table-hover mb-0 text-center">
          <thead>
            <tr>
              <th>{{ __('Reference') }}</th>
              <th>{{ __('City') }}</th>
              <th>{{ __('Neighborhood') }}</th>
              <th>{{ __('Customer name') }}</th>
              <th>{{ __('Valuation date') }}</th>
              <th>{{ __('State') }}</th>
              <th>{{ __('More') }}</th>
            </tr>
          </thead>
          <tbody>
            @forelse ($latestValuations as $item)
              @php
                $location = $item->property?->location;
                $cityName = $location?->city?->name_ar ?: ($location?->city?->name_en ?? '—');
                $neighborhoodName = $location?->neighborhood?->name_ar ?: ($location?->neighborhood?->name_en ?? '—');
                $valuationDate = $item->started_at
                  ?? $item->property?->evaluation_date
                  ?? $item->created_at;
              @endphp
              <tr>
                <td>{{ $item->reference ?? '—' }}</td>
                <td>{{ $cityName }}</td>
                <td>{{ $neighborhoodName }}</td>
                <td>{{ $item->property?->customer_name ?? '—' }}</td>
                <td>{{ optional($valuationDate)->format('Y/m/d') ?? '—' }}</td>
                <td>
                  <span class="badge bg-light-primary">{{ $item->stateLabel() }}</span>
                </td>
                <td>
                  <a href="{{ route('dashboard.valuation-requests.show', $item) }}" class="btn btn-sm btn-outline-success" title="{{ __('View') }}">
                    <i data-feather="info"></i>
                  </a>
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="7" class="text-center text-danger fw-bold py-2">{{ __('No valuations under evaluation') }}</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  @endcan

  {{-- Users board snippet --}}
  @if ($recentUsers !== null)
    <div class="card mb-2">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h4 class="card-title mb-0">{{ __('Users board') }}</h4>
        <a href="{{ route('dashboard.users.index') }}" class="btn btn-sm btn-outline-primary">{{ __('View all') }}</a>
      </div>
      <div class="table-responsive">
        <table class="table table-striped table-hover mb-0 text-center">
          <thead>
            <tr>
              <th>{{ __('Employee name') }}</th>
              <th>{{ __('Email') }}</th>
              <th>{{ __('Username') }}</th>
              <th>{{ __('Job title') }}</th>
              <th>{{ __('More') }}</th>
            </tr>
          </thead>
          <tbody>
            @forelse ($recentUsers as $member)
              <tr>
                <td>{{ $member->name }}</td>
                <td>{{ $member->email }}</td>
                <td>{{ $member->username }}</td>
                <td>{{ $member->roles->pluck('name')->map(fn ($role) => __($role))->implode(' / ') ?: '—' }}</td>
                <td>
                  <a href="{{ route('dashboard.users.show', $member) }}" class="btn btn-sm btn-outline-success" title="{{ __('View') }}">
                    <i data-feather="info"></i>
                  </a>
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="5" class="text-center text-danger fw-bold py-2">{{ __('No users yet') }}</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  @endif

  {{-- Latest events --}}
  @if ($recentLogs !== null)
    <div class="card mb-2">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h4 class="card-title mb-0">{{ __('Latest events') }}</h4>
        <a href="{{ route('dashboard.valuation-requests.activity-logs') }}" class="btn btn-sm btn-outline-primary">{{ __('Events board') }}</a>
      </div>
      <div class="table-responsive">
        <table class="table table-striped table-hover mb-0 text-center">
          <thead>
            <tr>
              <th>{{ __('Type') }}</th>
              <th>{{ __('Description') }}</th>
              <th>{{ __('Reference') }}</th>
              <th>{{ __('Subject') }}</th>
              <th>{{ __('By') }}</th>
              <th>{{ __('Event time') }}</th>
            </tr>
          </thead>
          <tbody>
            @forelse ($recentLogs as $log)
              @php
                $subject = $log->subject;
                $reference = $subject instanceof \App\Models\ValuationRequest
                  ? ($subject->reference ?? $subject->id)
                  : ($log->subject_id ?? '—');
              @endphp
              <tr>
                <td>
                  <span class="badge bg-light-info">{{ data_get($log->properties, 'event') ?? $log->event ?? '—' }}</span>
                </td>
                <td>{{ $log->description }}</td>
                <td>{{ $reference }}</td>
                <td>
                  @if ($log->subject_id)
                    <a href="{{ route('dashboard.valuation-requests.show', $log->subject_id) }}">#{{ $log->subject_id }}</a>
                  @else
                    —
                  @endif
                </td>
                <td>{{ $log->causer?->name ?? '—' }}</td>
                <td>{{ optional($log->created_at)->format('Y/m/d - h:i A') ?? '—' }}</td>
              </tr>
            @empty
              <tr>
                <td colspan="6" class="text-center text-danger fw-bold py-2">{{ __('No events yet') }}</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  @endif

  {{-- Extra migration / ops metrics (kept below legacy layout) --}}
  <div class="row match-height">
    <div class="col-12 mb-1">
      <h4 class="mb-0">{{ __('Additional metrics') }}</h4>
    </div>

    <div class="col-lg-3 col-sm-6 col-12">
      <div class="card">
        <div class="card-body">
          <h4 class="fw-bolder mb-0">{{ number_format($widgets['total_requests']) }}</h4>
          <p class="card-text">{{ __('Valuation requests') }}</p>
        </div>
      </div>
    </div>
    <div class="col-lg-3 col-sm-6 col-12">
      <div class="card">
        <div class="card-body">
          <h4 class="fw-bolder mb-0">{{ number_format($widgets['linked_properties']) }}</h4>
          <p class="card-text">{{ __('Linked properties') }}</p>
        </div>
      </div>
    </div>
    <div class="col-lg-3 col-sm-6 col-12">
      <div class="card">
        <div class="card-body">
          <h4 class="fw-bolder mb-0">{{ number_format($widgets['qima_uploaded']) }}</h4>
          <p class="card-text">{{ __('Uploaded to Qima') }}</p>
        </div>
      </div>
    </div>
    <div class="col-lg-3 col-sm-6 col-12">
      <div class="card">
        <div class="card-body">
          <h4 class="fw-bolder mb-0">{{ number_format($widgets['pending_evaluation']) }}</h4>
          <p class="card-text">{{ __('Pending evaluation') }}</p>
        </div>
      </div>
    </div>

    @isset($widgets['my_assigned'])
      <div class="col-lg-3 col-sm-6 col-12">
        <div class="card">
          <div class="card-body">
            <h4 class="fw-bolder mb-0">{{ number_format($widgets['my_assigned']) }}</h4>
            <p class="card-text">{{ __('My assigned requests') }}</p>
          </div>
        </div>
      </div>
    @endisset

    @isset($widgets['my_coordinated'])
      <div class="col-lg-3 col-sm-6 col-12">
        <div class="card">
          <div class="card-body">
            <h4 class="fw-bolder mb-0">{{ number_format($widgets['my_coordinated']) }}</h4>
            <p class="card-text">{{ __('My coordinated requests') }}</p>
          </div>
        </div>
      </div>
    @endisset

    @can('valuation_request.view')
      <div class="col-lg-3 col-sm-6 col-12">
        <div class="card">
          <div class="card-body">
            <h4 class="fw-bolder mb-0">{{ number_format($widgets['quarantine']) }}</h4>
            <p class="card-text">{{ __('Import quarantine rows') }}</p>
          </div>
        </div>
      </div>
      <div class="col-lg-3 col-sm-6 col-12">
        <div class="card border-warning">
          <div class="card-body">
            <h4 class="fw-bolder mb-0">
              {{ number_format($widgets['pictures_missing']) }}
              <small class="text-muted">/ {{ number_format($widgets['pictures_total']) }}</small>
            </h4>
            <p class="card-text">{{ __('Pictures missing on disk') }}</p>
            <p class="small text-muted mb-0">{{ __('Run legacy:verify-pictures after mounting LEGACY_PICTURE_PATHS') }}</p>
          </div>
        </div>
      </div>
    @endcan
  </div>
</section>
@endsection
