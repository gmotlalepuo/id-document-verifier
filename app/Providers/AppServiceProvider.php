<?php
namespace App\Providers;
use App\Mcp\Data\RequestLabRepository;
use App\Mcp\Data\SupabaseRequestLabRepository;
use App\Mcp\Execution\BerzudaExecutionClient;
use App\Mcp\Execution\RequestLabExecutor;
use App\Mcp\Model\ModelClient;
use App\Mcp\Model\OllamaModelClient;
use Illuminate\Support\ServiceProvider;
class AppServiceProvider extends ServiceProvider {
    public function register() {
        $this->app->bind(RequestLabRepository::class, SupabaseRequestLabRepository::class);
        $this->app->bind(ModelClient::class, OllamaModelClient::class);
        $this->app->bind(RequestLabExecutor::class, BerzudaExecutionClient::class);
    }
    public function boot() {}
}
