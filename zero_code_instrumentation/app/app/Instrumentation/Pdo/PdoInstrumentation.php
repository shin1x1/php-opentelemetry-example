<?php
declare(strict_types=1);

namespace App\Instrumentation\Pdo;

use App\Instrumentation\Util\TraceUtil;
use OpenTelemetry\API\Instrumentation\CachedInstrumentation;
use OpenTelemetry\SemConv\TraceAttributes;
use PDO;
use PDOStatement;
use Throwable;
use WeakMap;
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
        $boundParameters = new WeakMap();

        hook(
            PDO::class,
            '__construct',
            pre: static function (PDO $pdo, array $params, string $class, string $function, ?string $filename, ?int $lineno) use ($instrumentation) {
                $span = TraceUtil::start(
                    $instrumentation,
                    $class,
                    $function,
                    $filename,
                    $lineno,
                );
                if ($class === PDO::class) {
                    $span
                        ->setAttribute(TraceAttributes::DB_CONNECTION_STRING, $params[0] ?? 'unknown')
                        ->setAttribute(TraceAttributes::DB_USER, $params[1] ?? 'unknown');
                }
            },
            post: static function (PDO $pdo, array $params, mixed $statement, ?Throwable $exception) {
                TraceUtil::end($exception);
            }
        );

        hook(
            PDO::class,
            'prepare',
            post: static function (PDO $pdo, array $params, PDOStatement $statement) use ($instrumentation, $boundParameters) {
                $boundParameters[$statement] = new BoundParameters();
            },
        );

        hook(
            PDOStatement::class,
            'bindValue',
            post: static function (PDOStatement $statement, array $params) use ($instrumentation, $boundParameters) {
                $boundParameters[$statement]->add($params[0], $params[1]);
            },
        );

        hook(
            PDOStatement::class,
            'bindParam',
            post: static function (PDOStatement $statement, array $params) use ($instrumentation, $boundParameters) {
                $boundParameters[$statement]->add($params[0], $params[1]);
            },
        );

        foreach (['query', 'exec'] as $function) {
            hook(
                PDO::class,
                $function,
                pre: static function (PDO $pdo, array $params, string $class, string $function, ?string $filename, ?int $lineno) use ($instrumentation, $boundParameters) {
                    $sql = $params[0] ?? '';
                    TraceUtil::startWithSql($instrumentation, $sql, [], $class, $function, $filename, $lineno);
                },
                post: static function (PDO $pdo, array $params, mixed $retval, ?Throwable $exception) {
                    TraceUtil::end($exception);
                }
            );
        }

        foreach (['beginTransaction', 'commit', 'rollback'] as $function) {
            hook(
                PDO::class,
                $function,
                pre: static function (PDO $pdo, array $params, string $class, string $function, ?string $filename, ?int $lineno) use ($instrumentation, $boundParameters) {
                    TraceUtil::start($instrumentation, $class, $function, $filename, $lineno);
                },
                post: static function (PDO $pdo, array $params, mixed $retval, ?Throwable $exception) {
                    TraceUtil::end($exception);
                }
            );
        }

        hook(
            PDOStatement::class,
            'execute',
            pre: static function (PDOStatement $statement, array $params, string $class, string $function, ?string $filename, ?int $lineno) use ($instrumentation, $boundParameters) {
                if (count($params) > 0) {
                    $boundParameter = array_values($params[0]);
                } else {
                    $boundParameter = $boundParameters[$statement]?->toArray() ?? [];
                }

                TraceUtil::startWithSql(
                    $instrumentation,
                    $statement->queryString,
                    $boundParameter,
                    $class,
                    $function,
                    $filename,
                    $lineno
                );
            },
            post: static function (PDOStatement $statement, array $params, mixed $retval, ?Throwable $exception) {
                TraceUtil::end($exception);
            }
        );
    }
}

