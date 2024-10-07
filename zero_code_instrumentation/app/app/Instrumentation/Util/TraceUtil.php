<?php
declare(strict_types=1);

namespace App\Instrumentation\Util;

use OpenTelemetry\API\Instrumentation\CachedInstrumentation;
use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\API\Trace\SpanBuilderInterface;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\Context\Context;
use OpenTelemetry\SemConv\TraceAttributes;
use Throwable;

final class TraceUtil
{
    public static function makeBuilder(
        CachedInstrumentation $instrumentation,
        string                $name,
        ?string               $class,
        string                $function,
        ?string               $filename,
        ?int                  $lineno
    ): SpanBuilderInterface
    {
        /** @psalm-suppress ArgumentTypeCoercion */
        return $instrumentation->tracer()
            ->spanBuilder($name)
            ->setAttribute(TraceAttributes::CODE_FUNCTION, $function)
            ->setAttribute(TraceAttributes::CODE_NAMESPACE, $class)
            ->setAttribute(TraceAttributes::CODE_FILEPATH, $filename)
            ->setAttribute(TraceAttributes::CODE_LINENO, $lineno);
    }

    public static function start(
        CachedInstrumentation $instrumentation,
        ?string               $class,
        string                $function,
        ?string               $filename,
        ?int                  $lineno,
    ): SpanInterface
    {
        $builder = TraceUtil::makeBuilder(
            $instrumentation,
            self::createTitle($class, $function),
            $class,
            $function,
            $filename,
            $lineno
        )
            ->setSpanKind(SpanKind::KIND_CLIENT);
        $parent = Context::getCurrent();
        $span = $builder->startSpan();
        Context::storage()->attach($span->storeInContext($parent));

        return $span;
    }

    public static function startWithSql(
        CachedInstrumentation $instrumentation,
        string                $sql,
        array                 $params,
        ?string               $class,
        string                $function,
        ?string               $filename,
        ?int                  $lineno,
    ): SpanInterface
    {
        if (count($params) > 0) {
            $sql = sprintf('%s %s', $sql, json_encode($params));
        }
        $titleSql = mb_strlen($sql) > 20 ? mb_substr($sql, 0, 20) . '...' : $sql;

        return self::start($instrumentation, $class, $function, $filename, $lineno)
            ->updateName(self::createTitle($class, $function) . ' ' . $titleSql)
            ->setAttribute(TraceAttributes::DB_STATEMENT, $sql);
    }

    public static function end(?Throwable $exception): void
    {
        $scope = Context::storage()->scope();
        if (!$scope) {
            return;
        }
        $scope->detach();
        $span = Span::fromContext($scope->context());
        if ($exception) {
            $span->recordException($exception, [TraceAttributes::EXCEPTION_ESCAPED => true]);
            $span->setStatus(StatusCode::STATUS_ERROR, $exception->getMessage());
        }

        $span->end();
    }

    private static function createTitle(?string $class, string $method): string
    {
        return $class === null ? $method : $class . '::' . $method;
    }
}
