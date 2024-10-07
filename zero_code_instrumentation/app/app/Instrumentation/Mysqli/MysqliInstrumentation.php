<?php
declare(strict_types=1);

namespace App\Instrumentation\Mysqli;

use App\Instrumentation\Util\TraceUtil;
use mysqli;
use mysqli_stmt;
use OpenTelemetry\API\Instrumentation\CachedInstrumentation;
use Throwable;
use WeakMap;
use function OpenTelemetry\Instrumentation\hook;

final class MysqliInstrumentation
{
    public static function register(): void
    {
        $instrumentation = new CachedInstrumentation(
            'com.shin1x1.php-otel-auto-instrumentaion.mysqli',
            null,
            'https://opentelemetry.io/schemas/1.24.0'
        );
        $preparedStatements = new WeakMap();

        // connect
        hook(
            mysqli::class,
            '__construct',
            pre: static function (?object $object, array $params, string $class, string $function, ?string $filename, ?int $lineno) use ($instrumentation) {
                TraceUtil::start($instrumentation, $class, $function, $filename, $lineno);
            },
            post: static function (?object $object, array $params, mixed $statement, ?Throwable $exception) {
                TraceUtil::end($exception);
            }
        );
        hook(
            null,
            'mysqli_connect',
            pre: static function (?object $object, array $params, ?string $class, string $function, ?string $filename, ?int $lineno) use ($instrumentation) {
                TraceUtil::start($instrumentation, $class, $function, $filename, $lineno);
            },
            post: static function (?object $object, array $params, mixed $statement, ?Throwable $exception) {
                TraceUtil::end($exception);
            }
        );

        // query
        foreach (['query', 'real_query'] as $method) {
            hook(
                mysqli::class,
                $method,
                pre: static function (?object $object, array $params, string $class, string $function, ?string $filename, ?int $lineno) use ($instrumentation) {
                    $sql = $params[0] ?? '';
                    TraceUtil::startWithSql($instrumentation, $sql, [], $class, $function, $filename, $lineno);
                },
                post: static function (?object $object, array $params, mixed $statement, ?Throwable $exception) {
                    TraceUtil::end($exception);
                }
            );
            hook(
                null,
                self::getFunctionName($method),
                pre: static function (?object $object, array $params, ?string $class, string $function, ?string $filename, ?int $lineno) use ($instrumentation) {
                    $sql = $params[1] ?? '';
                    TraceUtil::startWithSql($instrumentation, $sql, [], $class, $function, $filename, $lineno);
                },
                post: static function (?object $object, array $params, mixed $statement, ?Throwable $exception) {
                    TraceUtil::end($exception);
                }
            );
        }

        // prepare
        hook(
            mysqli::class,
            'prepare',
            post: static function (mysqli $mysqi, array $params, mysqli_stmt $mysqli_stmt, ?Throwable $exception) use ($preparedStatements) {
                $preparedStatements[$mysqli_stmt] = $params[0];
            }
        );
        hook(
            mysqli_stmt::class,
            'prepare',
            post: static function (mysqli_stmt $mysqli_stmt, array $params) use ($preparedStatements) {
                $preparedStatements[$mysqli_stmt] = $params[0];
            }
        );
        hook(
            null,
            self::getFunctionName('prepare'),
            post: static function (?object $object, array $params, mysqli_stmt $mysqli_stmt, ?Throwable $exception) use ($preparedStatements) {
                $preparedStatements[$mysqli_stmt] = $params[1];
            }
        );

        hook(
            mysqli_stmt::class,
            'execute',
            pre: static function (mysqli_stmt $mysqli_stmt, array $params, string $class, string $function, ?string $filename, ?int $lineno) use ($instrumentation, $preparedStatements) {
                $preparedStatement = $preparedStatements[$mysqli_stmt] ?? '';
                $boundParameters = $params[0] ?? [];
                TraceUtil::startWithSql($instrumentation, $preparedStatement, $boundParameters, $class, $function, $filename, $lineno);
            },
            post: static function (?object $object, array $params, mixed $statement, ?Throwable $exception) {
                TraceUtil::end($exception);
            }
        );
        hook(
            null,
            self::getFunctionName('stmt_execute'),
            pre: static function (mysqli_stmt $mysqli_stmt, array $params, string $class, string $function, ?string $filename, ?int $lineno) use ($instrumentation, $preparedStatements) {
                $preparedStatement = $preparedStatements[$mysqli_stmt] ?? '';
                $boundParameters = $params[1] ?? [];
                TraceUtil::startWithSql($instrumentation, $preparedStatement, $boundParameters, $class, $function, $filename, $lineno);
            },
            post: static function (?object $object, array $params, mixed $statement, ?Throwable $exception) {
                TraceUtil::end($exception);
            }
        );
    }

    private static function getFunctionName(string $method): string
    {
        return 'mysqli_' . $method;
    }
}
