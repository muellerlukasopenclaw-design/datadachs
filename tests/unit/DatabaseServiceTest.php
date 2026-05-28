<?php
/**
 * DataDachs – Tests für DatabaseService
 */

namespace DataDachs\Tests;

use DataDachs\Service\DatabaseService;
use PHPUnit\Framework\TestCase;

class DatabaseServiceTest extends TestCase
{
    private string $testDbPath;
    private DatabaseService $service;

    protected function setUp(): void
    {
        $this->testDbPath = __DIR__ . '/../../storage/test.db';
        $this->service = new DatabaseService();
        
        // Test-DB erstellen
        if (file_exists($this->testDbPath)) {
            unlink($this->testDbPath);
        }
        
        $this->service->connect('sqlite:' . $this->testDbPath);
        
        // Test-Tabelle erstellen
        $pdo = $this->service->getPdo();
        $pdo->exec("CREATE TABLE users (
            id INTEGER PRIMARY KEY,
            firstname TEXT,
            lastname TEXT,
            email TEXT,
            age INTEGER
        )");
        
        $pdo->exec("INSERT INTO users (firstname, lastname, email, age) VALUES 
            ('Max', 'Müller', 'max@example.com', 30),
            ('Anna', 'Schmidt', 'anna@example.com', 25),
            ('Tom', 'Weber', 'tom@example.com', 35)");
    }

    protected function tearDown(): void
    {
        $this->service->disconnect();
        if (file_exists($this->testDbPath)) {
            unlink($this->testDbPath);
        }
    }

    public function testConnect(): void
    {
        $this->assertTrue($this->service->isConnected());
    }

    public function testGetTables(): void
    {
        $tables = $this->service->getTables();
        $this->assertContains('users', $tables);
    }

    public function testGetColumns(): void
    {
        $columns = $this->service->getColumns('users');
        $names = array_column($columns, 'name');
        
        $this->assertContains('id', $names);
        $this->assertContains('firstname', $names);
        $this->assertContains('lastname', $names);
        $this->assertContains('email', $names);
        $this->assertContains('age', $names);
    }

    public function testCountRows(): void
    {
        $this->assertEquals(3, $this->service->countRows('users'));
    }

    public function testFetchBatch(): void
    {
        $rows = $this->service->fetchBatch('users', ['firstname', 'lastname'], 0, 2);
        $this->assertCount(2, $rows);
        $this->assertEquals('Max', $rows[0]['firstname']);
    }

    public function testUpdateRow(): void
    {
        $result = $this->service->updateRow('users', ['firstname' => 'Maximilian'], ['id' => 1]);
        $this->assertTrue($result);
        
        $rows = $this->service->fetchBatch('users', ['firstname'], 0, 1);
        $this->assertEquals('Maximilian', $rows[0]['firstname']);
    }

    public function testGetPrimaryKeys(): void
    {
        $pks = $this->service->getPrimaryKeys('users');
        $this->assertContains('id', $pks);
    }

    public function testDisconnect(): void
    {
        $this->service->disconnect();
        $this->assertFalse($this->service->isConnected());
    }
}
