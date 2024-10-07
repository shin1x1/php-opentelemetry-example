<?php

namespace Tests\Instrumentation\Mysqli;

use ArrayObject;
use OpenTelemetry\API\Instrumentation\Configurator;
use OpenTelemetry\SDK\Trace\SpanExporter\InMemoryExporter;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;
use OpenTelemetry\SemConv\TraceAttributes;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class MysqliInstrumentationTest extends TestCase
{
    private const HOST = 'mysql';
    private const USER = 'user';
    private const PASS = 'pass';
    private const DB_NAME = 'app';


    #[Test]
    public function mysqi_construct(): void
    {
        // Arrange & Act
        self::createMysqli();

        // Assert
        $this->assertCount(1, $this->storage);

        $span = $this->storage[0];
        $this->assertSame('mysqli::__construct', $span->getName());
    }

    #[Test]
    public function mysqi_connect(): void
    {
        // Arrange & Act
        mysqli_connect(self::HOST, self::USER, self::PASS, self::DB_NAME);

        // Assert
        $this->assertCount(1, $this->storage);

        $span = $this->storage[0];
        $this->assertSame('mysqli_connect', $span->getName());
    }

    #[Test]
    public function mysqli_method_query(): void
    {
        // Arrange
        $mysqli = self::createMysqli();
        $sql = 'SELECT 1';

        // Act
        $mysqli->query($sql);

        // Assert
        $this->assertCount(2, $this->storage);

        $span = $this->storage[1];
        $this->assertSame('mysqli::query ' . $sql, $span->getName());
        $this->assertSame($sql, $span->getAttributes()->get(TraceAttributes::DB_STATEMENT));
    }

    #[Test]
    public function mysqli_query(): void
    {
        // Arrange
        $mysqli = self::createMysqli();
        $sql = 'SELECT 1';

        // Act
        mysqli_query($mysqli, $sql);

        // Assert
        $this->assertCount(2, $this->storage);

        $span = $this->storage[1];
        $this->assertSame('mysqli_query ' . $sql, $span->getName());
        $this->assertSame($sql, $span->getAttributes()->get(TraceAttributes::DB_STATEMENT));
    }

    #[Test]
    public function mysqli_method_prepare_and_execute(): void
    {
        // Arrange
        $mysqli = self::createMysqli();
        $mysqli->query($this->getFixtureSQL());
        $sql = 'SELECT * FROM users WHERE name = ?';

        // Act
        $stmt = $mysqli->prepare($sql);
        $stmt->execute(['Alice']);

        // Assert
        $this->assertCount(3, $this->storage);

        $span = $this->storage[2];
        $this->assertSame('mysqli_stmt::execute SELECT * FROM users ...', $span->getName());
        $this->assertSame($sql . ' ' . json_encode(['Alice']), $span->getAttributes()->get(TraceAttributes::DB_STATEMENT));
    }

    #[Test]
    public function mysqli_prepare_and_execute(): void
    {
        // Arrange
        $mysqli = self::createMysqli();
        $mysqli->query($this->getFixtureSQL());
        $sql = 'SELECT * FROM users WHERE name = ?';

        // Act
        $stmt = mysqli_prepare($mysqli, $sql);
        $stmt->execute(['Alice']);

        // Assert
        $this->assertCount(3, $this->storage);

        $span = $this->storage[2];
        $this->assertSame('mysqli_stmt::execute SELECT * FROM users ...', $span->getName());
        $this->assertSame($sql . ' ' . json_encode(['Alice']), $span->getAttributes()->get(TraceAttributes::DB_STATEMENT));
    }

    public function setUp(): void
    {
        $this->storage = new ArrayObject();
        $tracerProvider = new TracerProvider(
            new SimpleSpanProcessor(
                new InMemoryExporter($this->storage)
            )
        );

        $this->scope = Configurator::create()
            ->withTracerProvider($tracerProvider)
            ->activate();
    }

    public function tearDown(): void
    {
        $this->createMysqli()->query('DROP TABLE IF EXISTS users');
        $this->scope->detach();
    }

    private function createMysqli(): \mysqli
    {
        return new \mysqli(
            self::HOST,
            self::USER,
            self::PASS,
            self::DB_NAME,
        );
    }

    private function getFixtureSQL(): string
    {
        return <<<SQL
        CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            points INT NOT NULL
        );

        SQL;
    }
}
