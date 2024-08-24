<?php
declare(strict_types=1);

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\Context\Propagation\ArrayAccessGetterSetter;
use Psr\Log\LogLevel;

require __DIR__ . '/../vendor/autoload.php';

$logger = new Logger('app');
$logger->pushHandler(new StreamHandler('php://stdout', LogLevel::INFO));
$logger->pushHandler(
    new OpenTelemetry\Contrib\Logs\Monolog\Handler(
        Globals::loggerProvider(),
        LogLevel::INFO,
    )
);

$tracer = Globals::tracerProvider()
    ->getTracer(
        'trace',
        '0.0.1',
        'https://opentelemetry.io/schemas/1.24.0',
    );
$span = $tracer->spanBuilder('trace.php')
    ->setParent(Globals::propagator()->extract(getallheaders(), ArrayAccessGetterSetter::getInstance()))
    ->startSpan();
$scope = $span->activate();

try {
    func1($tracer, $logger, random_int(1, 100));
    func2($tracer, $logger);
} catch (Throwable $e) {
    $span->recordException($e)->setStatus(StatusCode::STATUS_ERROR);
    $logger->error($e->getMessage());
    throw $e;
} finally {
    $scope->detach();
    $span->end();
}

function func1(TracerInterface $tracer, Logger $logger, int $i): void
{
    $span = $tracer->spanBuilder('func1')->startSpan();

    if (random_int(1, 5) === 1) {
        throw new Exception('error in func1');
    }

    $result = random_int(1, 100);
    $span->setAttribute('result', $result);
    $span->end();

    $logger->info('logging from func1');
}

function func2(TracerInterface $tracer, Logger $logger): void
{
    $span = $tracer->spanBuilder('func2')->startSpan();

    if (random_int(1, 5) === 1) {
        throw new Exception('error in func2');
    }

    $result = random_int(1, 100);
    $span->setAttribute('result', $result);
    $span->end();

    $logger->info('logging from func2');
}

echo 'done';
