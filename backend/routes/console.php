<?php

use App\Services\AssessmentRecoveryService;
use App\Services\MonitoringDispatcherService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('monitoring:dispatch', function (
    MonitoringDispatcherService $dispatcher
) {
    $result = $dispatcher->dispatchDue();

    $this->info(sprintf(
        'Monitoring evaluated=%d dispatched=%d overlap_skipped=%d blocked=%d failed=%d',
        $result['evaluated'],
        $result['dispatched'],
        $result['overlap_skipped'],
        $result['target_blocked'],
        $result['failed'],
    ));
})->purpose('Dispatch due authorized monitoring assessments');

Schedule::command('monitoring:dispatch')
    ->everyMinute()
    ->withoutOverlapping(10);

Artisan::command('assessments:recover', function (
    AssessmentRecoveryService $recovery
) {
    $result =
        $recovery->reconcileAbandonedRunning();

    $this->info(sprintf(
        'Assessment recovery evaluated=%d recovered=%d skipped=%d threshold=%ds',
        $result['evaluated'],
        $result['recovered'],
        $result['skipped'],
        $result['abandoned_after_seconds'],
    ));
})->purpose(
    'Reconcile abandoned scanner assessment executions'
);

Schedule::command('assessments:recover')
    ->everyMinute()
    ->withoutOverlapping(10);
