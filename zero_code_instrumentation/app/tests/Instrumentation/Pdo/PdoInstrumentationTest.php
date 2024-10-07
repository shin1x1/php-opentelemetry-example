<?php

namespace Tests\Instrumentation\Pdo;

use ArrayObject;
use OpenTelemetry\API\Instrumentation\Configurator;
use OpenTelemetry\Context\ScopeInterface;
use OpenTelemetry\SDK\Trace\ImmutableSpan;
use OpenTelemetry\SDK\Trace\SpanExporter\InMemoryExporter;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;
use OpenTelemetry\SemConv\TraceAttributes;
use PDO;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class PdoInstrumentationTest extends TestCase
{
    private const DSN = 'sqlite::memory:';

    private ScopeInterface $scope;
    /** @var ArrayObject<int, ImmutableSpan> */
    private ArrayObject $storage;

    #[Test]
    public function pdo_construct(): void
    {
        // Arrange & Act
        self::createDB();

        // Assert
        $this->assertCount(1, $this->storage);

        $span = $this->storage[0];
        $this->assertSame('PDO::__construct', $span->getName());
        $this->assertSame(self::DSN, $span->getAttributes()->get(TraceAttributes::DB_CONNECTION_STRING));
    }

    #[Test]
    public function pdo_query(): void
    {
        // Arrange
        $pdo = self::createDB();
        $sql = 'SELECT 1';

        // Act
        $pdo->query($sql);

        // Assert
        $this->assertCount(2, $this->storage);

        $span = $this->storage[1];
        $this->assertSame('PDO::query ' . $sql, $span->getName());
        $this->assertSame($sql, $span->getAttributes()->get(TraceAttributes::DB_STATEMENT));
    }

    #[Test]
    public function pdo_exec(): void
    {
        // Arrange
        $pdo = self::createDB();
        $sql = $this->getFixtureSQL();

        // Act
        $pdo->exec($sql);

        // Assert
        $this->assertCount(2, $this->storage);

        $span = $this->storage[1];
        $this->assertSame('PDO::exec CREATE TABLE users (...', $span->getName());
        $this->assertSame($sql, $span->getAttributes()->get(TraceAttributes::DB_STATEMENT));
    }

    #[Test]
    public function pdo_prepare_and_execute(): void
    {
        // Arrange
        $pdo = self::createDB();
        $pdo->exec($this->getFixtureSQL());
        $sql = 'SELECT * FROM users WHERE name = :name';

        // Act
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':name' => 'Alice']);

        // Assert
        $this->assertCount(3, $this->storage);

        $span = $this->storage[2];
        $this->assertSame('PDOStatement::execute SELECT * FROM users ...', $span->getName());
        $this->assertSame($sql . ' ' . json_encode(['Alice']), $span->getAttributes()->get(TraceAttributes::DB_STATEMENT));
    }

    #[Test]
    public function pdo_prepare_and_bindValue_execute(): void
    {
        // Arrange
        $pdo = self::createDB();
        $pdo->exec($this->getFixtureSQL());
        $sql = 'SELECT * FROM users WHERE name = :name';

        // Act
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':name', 'Bob');
        $stmt->execute();

        // Assert
        $this->assertCount(3, $this->storage);

        $span = $this->storage[2];
        $this->assertSame('PDOStatement::execute SELECT * FROM users ...', $span->getName());
        $this->assertSame($sql . ' ' . json_encode(['Bob']), $span->getAttributes()->get(TraceAttributes::DB_STATEMENT));
    }

    #[Test]
    public function pdo_beginTransaction(): void
    {
        // Arrange
        $pdo = self::createDB();

        // Act
        $pdo->beginTransaction();

        // Assert
        $this->assertCount(2, $this->storage);

        $span = $this->storage[1];
        $this->assertSame('PDO::beginTransaction', $span->getName());
    }

    #[Test]
    public function pdo_commit(): void
    {
        // Arrange
        $pdo = self::createDB();
        $pdo->beginTransaction();

        // Act
        $pdo->commit();

        // Assert
        $this->assertCount(3, $this->storage);

        $span = $this->storage[2];
        $this->assertSame('PDO::commit', $span->getName());
    }

    #[Test]
    public function pdo_rollback(): void
    {
        // Arrange
        $pdo = self::createDB();
        $pdo->beginTransaction();

        // Act
        $pdo->rollback();

        // Assert
        $this->assertCount(3, $this->storage);

        $span = $this->storage[2];
        $this->assertSame('PDO::rollBack', $span->getName());
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
        $this->scope->detach();
    }

    private function createDb(): PDO
    {
        return new PDO(self::DSN);
    }

    private function getFixtureSQL(): string
    {
        return <<<SQL
        CREATE TABLE users (
            id INTEGER PRIMARY KEY,
            name TEXT NOT NULL,
            points INTEGER NOT NULL
        );
        INSERT INTO users (name, points) VALUES ('Alice', 100);
        INSERT INTO users (name, points) VALUES ('Bob', 200);

        SQL;
    }
}
