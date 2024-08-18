<?php
declare(strict_types=1);

use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Trace\TracerInterface;

require __DIR__ . '/../vendor/autoload.php';

$tracer = Globals::tracerProvider()
    ->getTracer(
        'trace',
        '0.0.1',
        'https://opentelemetry.io/schemas/1.24.0',
    );
$span = $tracer->spanBuilder('span1')->startSpan();
$scope = $span->activate();

try {
    func1($tracer);
    func2($tracer);
} finally {
    $scope->detach();
    $span->end();
}

function func1(TracerInterface $tracer): void
{
    $span = $tracer->spanBuilder('func1')->startSpan();

    $result = random_int(1, 100);
    $span->setAttribute('result', $result);
    $span->end();
}

function func2(TracerInterface $tracer): void
{
    $span = $tracer->spanBuilder('func2')->startSpan();

    $result = random_int(1, 100);
    $span->setAttribute('result', $result);
    $span->end();
}

echo 'done';
