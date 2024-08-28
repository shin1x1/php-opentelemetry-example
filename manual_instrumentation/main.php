<?php
declare(strict_types=1);

use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\API\Trace\TracerInterface;

require __DIR__ . '/vendor/autoload.php';

$tracer = Globals::tracerProvider()->getTracer('trace');
$span = $tracer->spanBuilder('root')->startSpan();
$scope = $span->activate();

try {
    func1($tracer);
    echo "done\n";
} catch (Throwable $e) {
    $span->recordException($e)->setStatus(StatusCode::STATUS_ERROR);
    throw $e;
} finally {
    $scope->detach();
    $span->end();
}

function func1(TracerInterface $tracer): void
{
    $span = $tracer->spanBuilder('func1')->startSpan();

    if (random_int(1, 5) === 1) {
        throw new Exception('error in func1');
    }

    $result = random_int(1, 100);
    usleep(1000 * $result);
    $span->setAttribute('result', $result);
    $span->end();
}
