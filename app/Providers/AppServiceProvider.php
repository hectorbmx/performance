<?php

namespace App\Providers;
use App\Models\CoachSubscription;
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
        //
        View::composer('layouts.sidebar', function ($view) {
    $today = now()->toDateString();

    $unpaidCount = CoachSubscription::where('billing_status', 'unpaid')
        ->whereNull('deleted_at')
        ->count();

    $graceCount = CoachSubscription::where('billing_status', 'unpaid')
        ->whereDate('grace_until', '>=', $today)
        ->whereNull('deleted_at')
        ->count();

    $view->with([
        'sidebarUnpaidCount' => $unpaidCount,
        'sidebarGraceCount' => $graceCount,
    ]);
});
    }
}
