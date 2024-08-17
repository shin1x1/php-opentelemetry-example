<?php
declare(strict_types=1);

use OpenTelemetry\API\Globals;

require __DIR__ . '/../vendor/autoload.php';

$tracer = Globals::tracerProvider()
    ->getTracer(
        'trace',
        '0.0.1',
        'https://opentelemetry.io/schemas/1.24.0',
    );
$span = $tracer->spanBuilder('span1')->startSpan();

// 何か処理を行う

$span->end();

echo 'done';
