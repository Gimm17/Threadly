<?php
// Quick check script
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$configs = \App\Models\AiModelConfig::withoutGlobalScopes()
    ->where('feature', 'image_generation')
    ->get(['id', 'workspace_id', 'model_id', 'is_active']);

echo json_encode($configs->toArray(), JSON_PRETTY_PRINT) . "\n";
