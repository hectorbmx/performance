<?php

namespace App\Providers;

use App\Enums\TipScope;
use App\Enums\TipStatus;
use App\Models\CoachSubscription;
use App\Models\Tip;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::composer('layouts.sidebar-admin', function ($view) {
            $today = now()->toDateString();

            $unpaidCount = 0;
            $graceCount = 0;
            if (Schema::hasTable('coach_subscriptions')) {
                $unpaidCount = CoachSubscription::where('billing_status', 'unpaid')
                    ->whereNull('deleted_at')
                    ->count();

                $graceCount = CoachSubscription::where('billing_status', 'unpaid')
                    ->whereDate('grace_until', '>=', $today)
                    ->whereNull('deleted_at')
                    ->count();
            }

            $pendingTipsCount = Schema::hasTable('tips')
                ? Tip::where('scope', TipScope::TENANT->value)
                    ->where('status', TipStatus::PENDING_APPROVAL->value)
                    ->count()
                : 0;

            $view->with([
                'sidebarUnpaidCount' => $unpaidCount,
                'sidebarGraceCount' => $graceCount,
                'sidebarPendingTipsCount' => $pendingTipsCount,
            ]);
        });
    }
}
