<?php
declare(strict_types=1);

namespace App\Instrumentation\Pdo;

use OpenTelemetry\API\Instrumentation\CachedInstrumentation;
use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\API\Trace\SpanBuilderInterface;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\Context\Context;
use OpenTelemetry\SemConv\TraceAttributes;
use PDO;
use PDOStatement;
use Throwable;
use function OpenTelemetry\Instrumentation\hook;

final class PdoInstrumentation
{
    public static function register(): void
    {
        $instrumentation = new CachedInstrumentation(
            'com.shin1x1.php-otel-auto-instrumentaion.pdo',
            null,
            'https://opentelemetry.io/schemas/1.24.0'
        );
        $boundParameters = new BoundParameters();

        hook(
            PDO::class,
            '__construct',
            pre: static function (PDO $pdo, array $params, string $class, string $function, ?string $filename, ?int $lineno) use ($instrumentation) {
                $builder = self::makeBuilder($instrumentation, 'PDO::__construct', $function, $class, $filename, $lineno)
                    ->setSpanKind(SpanKind::KIND_CLIENT);
                if ($class === PDO::class) {
                    $builder
                        ->setAttribute(TraceAttributes::DB_CONNECTION_STRING, $params[0] ?? 'unknown')
                        ->setAttribute(TraceAttributes::DB_USER, $params[1] ?? 'unknown');
                }
                $parent = Context::getCurrent();
                $span = $builder->startSpan();
                Context::storage()->attach($span->storeInContext($parent));
            },
            post: static function (PDO $pdo, array $params, mixed $statement, ?Throwable $exception) {
                $scope = Context::storage()->scope();
                if (!$scope) {
                    return;
                }
                self::end($exception);
            }
        );

        hook(
            PDOStatement::class,
            'bindValue',
            pre: static function (PDOStatement $statement, array $params, string $class, string $function, ?string $filename, ?int $lineno) use ($instrumentation, $boundParameters) {
                $boundParameters->add($params[0], $params[1]);
            },
        );

        hook(
            PDOStatement::class,
            'bindParam',
            pre: static function (PDOStatement $statement, array $params, string $class, string $function, ?string $filename, ?int $lineno) use ($instrumentation, $boundParameters) {
                $boundParameters->add($params[0], $params[1]);
            },
        );

        foreach (['query', 'exec'] as $function) {
            hook(
                PDO::class,
                $function,
                pre: static function (PDO $pdo, array $params, string $class, string $function, ?string $filename, ?int $lineno) use ($instrumentation, $boundParameters) {
                    $sql = $params[0] ?? '';
                    static::startWithSql($instrumentation, $sql, $function, $class, $filename, $lineno);
                },
                post: static function (PDO $pdo, array $params, mixed $retval, ?Throwable $exception) {
                    self::end($exception);
                }
            );
        }

        foreach (['beginTransaction', 'commit', 'rollback'] as $function) {
            hook(
                PDO::class,
                $function,
                pre: static function (PDO $pdo, array $params, string $class, string $function, ?string $filename, ?int $lineno) use ($instrumentation, $boundParameters) {
                    static::start($instrumentation, $function, $class, $filename, $lineno);
                },
                post: static function (PDO $pdo, array $params, mixed $retval, ?Throwable $exception) {
                    self::end($exception);
                }
            );
        }

        foreach (['execute', 'fetch', 'fetchAll', 'fetchColumn'] as $function) {
            hook(
                PDOStatement::class,
                $function,
                pre: static function (PDOStatement $statement, array $params, string $class, string $function, ?string $filename, ?int $lineno) use ($instrumentation, $boundParameters) {
                    self::startWithSql(
                        $instrumentation,
                        $statement->queryString . ' ' . json_encode($boundParameters),
                        $function,
                        $class,
                        $filename,
                        $lineno
                    );
                },
                post: static function (PDOStatement $statement, array $params, mixed $retval, ?Throwable $exception) {
                    self::end($exception);
                }
            );
        }
    }

    private static function makeBuilder(
        CachedInstrumentation $instrumentation,
        string                $name,
        string                $function,
        string                $class,
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

    private static function start(CachedInstrumentation $instrumentation, string $function, string $class, ?string $filename, ?int $lineno): void
    {
        $builder = self::makeBuilder($instrumentation, $class . '::' . $function, $function, $class, $filename, $lineno)
            ->setSpanKind(SpanKind::KIND_CLIENT);
        $span = $builder->startSpan();

        Context::storage()->attach($span->storeInContext(Context::getCurrent()));
    }

    private static function startWithSql(CachedInstrumentation $instrumentation, string $sql, string $function, string $class, ?string $filename, ?int $lineno): void
    {
        $titleSql = mb_strlen($sql) > 20 ? mb_substr($sql, 0, 20) . '...' : $sql;

        $builder = self::makeBuilder($instrumentation, $class . '::' . $function . ' ' . $titleSql, $function, $class, $filename, $lineno)
            ->setSpanKind(SpanKind::KIND_CLIENT)
            ->setAttribute(TraceAttributes::DB_STATEMENT, $sql);
        $span = $builder->startSpan();

        Context::storage()->attach($span->storeInContext(Context::getCurrent()));
    }

    private static function end(?Throwable $exception): void
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
}

