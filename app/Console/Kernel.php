<?php
namespace App\Console;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
class Kernel extends ConsoleKernel {
    protected $commands=[\App\Console\Commands\McpModelCheck::class, \App\Console\Commands\McpPrune::class, \App\Console\Commands\McpEvaluate::class, \App\Console\Commands\McpReadiness::class];
    protected function schedule(\Illuminate\Console\Scheduling\Schedule $schedule){$schedule->command('mcp:prune')->dailyAt('02:15')->withoutOverlapping();}
    protected function commands(){}
}
