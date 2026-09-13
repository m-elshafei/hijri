<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Actions\Valuation\GetApprovedValuationsCountAction;
use App\Actions\Valuation\GetAverageTurnaroundHoursAction;
use App\Actions\Valuation\GetTotalValuedAreaAction;
use App\Http\Controllers\Controller;
use App\Models\ImportQuarantine;
use App\Models\Property;
use App\Models\PropertyPicture;
use App\Models\User;
use App\Models\ValuationRequest;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Spatie\Activitylog\Models\Activity;

class HomeController extends Controller
{
    public function index(
        GetApprovedValuationsCountAction $approvedCount,
        GetTotalValuedAreaAction $valuedArea,
        GetAverageTurnaroundHoursAction $turnaround,
    ): Renderable {
        $user = Auth::user();
        abort_unless($user !== null && $user->can('dashboard.view'), 403);

        $widgets = [
            'total_requests' => ValuationRequest::query()->count(),
            'linked_properties' => Property::query()->whereNotNull('valuation_request_id')->count(),
            'qima_uploaded' => ValuationRequest::query()->where('uploaded_on_qima', true)->count(),
            'pending_evaluation' => ValuationRequest::query()
                ->whereNull('evaluated_at')
                ->where('uploaded_on_qima', false)
                ->count(),
            'quarantine' => ImportQuarantine::query()->count(),
            'pictures_missing' => PropertyPicture::query()->where('file_exists', false)->count(),
            'pictures_total' => PropertyPicture::query()->count(),
            'approved_count' => $approvedCount->execute()['approved_count'],
            'total_valued_area' => $valuedArea->execute()['total_valued_area'],
            'average_turnaround_hours' => $turnaround->execute()['average_turnaround_hours'],
        ];

        if ($user->hasRole('evaluator')) {
            $widgets['my_assigned'] = ValuationRequest::query()
                ->where('evaluator_user_id', $user->id)
                ->count();
        }

        if ($user->hasRole('coordinator')) {
            $widgets['my_coordinated'] = ValuationRequest::query()
                ->where('coordinator_user_id', $user->id)
                ->count();
        }

        $latestValuations = $this->latestValuationsQuery($user)
            ->with(['property.location.city', 'property.location.neighborhood'])
            ->orderByDesc('reference')
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        $recentUsers = null;
        if ($user->can('viewAny', User::class)) {
            $recentUsers = User::query()
                ->with('roles')
                ->whereKeyNot($user->id)
                ->latest('id')
                ->limit(5)
                ->get();
        }

        $recentLogs = null;
        if ($user->can('viewLogs', ValuationRequest::class)) {
            $recentLogs = Activity::query()
                ->where('log_name', 'valuation')
                ->with(['causer', 'subject'])
                ->latest('id')
                ->limit(10)
                ->get();
        }

        return view('home', [
            'widgets' => $widgets,
            'roles' => $user->getRoleNames(),
            'latestValuations' => $latestValuations,
            'recentUsers' => $recentUsers,
            'recentLogs' => $recentLogs,
        ]);
    }

    public function underMaintenance(): View
    {
        return view('page-maintenance');
    }

    public function pageComingSoon(): View
    {
        return view('page-coming-soon');
    }

    /**
     * @return Builder<ValuationRequest>
     */
    private function latestValuationsQuery(User $user): Builder
    {
        $query = ValuationRequest::query();

        if ($user->hasAnyRole(['super-admin', 'admin', 'manager'])) {
            return $query;
        }

        if ($user->hasRole('coordinator') && ! $user->hasRole('evaluator')) {
            return $query->where(function (Builder $builder) use ($user): void {
                $builder->where('coordinator_user_id', $user->id)
                    ->orWhere('sub_user_id', $user->id);
            });
        }

        if ($user->hasRole('evaluator') && ! $user->hasRole('coordinator')) {
            return $query->where(function (Builder $builder) use ($user): void {
                $builder->where('evaluator_user_id', $user->id)
                    ->orWhere('fellow_user_id', $user->id);
            });
        }

        if ($user->hasRole('coordinator') && $user->hasRole('evaluator')) {
            return $query->where(function (Builder $builder) use ($user): void {
                $builder->where('coordinator_user_id', $user->id)
                    ->orWhere('evaluator_user_id', $user->id)
                    ->orWhere('sub_user_id', $user->id)
                    ->orWhere('fellow_user_id', $user->id);
            });
        }

        return $query->whereRaw('1 = 0');
    }
}
