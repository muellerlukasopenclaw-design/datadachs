<?php
/**
 * DataDachs – Tests für DbPseudonymizer
 */

namespace DataDachs\Tests;

use DataDachs\Service\DatabaseService;
use DataDachs\Service\DbPseudonymizer;
use DataDachs\Service\PiiDetector;
use DataDachs\Service\FakerEngine;
use DataDachs\Service\PreserveRuleService;
use PHPUnit\Framework\TestCase;

class DbPseudonymizerTest extends TestCase
{
    private string $testDbPath;
    private DatabaseService $dbService;
    private DbPseudonymizer $pseudonymizer;

    protected function setUp(): void
    {
        $this->testDbPath = __DIR__ . '/../../storage/test_pseudo.db';
        
        if (file_exists($this->testDbPath)) {
            unlink($this->testDbPath);
        }
        
        $this->dbService = new DatabaseService();
        $this->dbService->connect('sqlite:' . $this->testDbPath);
        
        $pdo = $this->dbService->getPdo();
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
        
        $detector = new PiiDetector();
        $faker = new FakerEngine();
        $preserve = new PreserveRuleService();
        
        $this->pseudonymizer = new DbPseudonymizer(
            $this->dbService,
            $detector,
            $faker,
            $preserve,
            100
        );
    }

    protected function tearDown(): void
    {
        $this->dbService->disconnect();
        if (file_exists($this->testDbPath)) {
            unlink($this->testDbPath);
        }
    }

    public function testAnalyzeSchema(): void
    {
        $analysis = $this->pseudonymizer->analyzeSchema();
        
        $this->assertArrayHasKey('users', $analysis);
        $this->assertArrayHasKey('detected', $analysis['users']);
        
        $detected = $analysis['users']['detected'];
        $this->assertArrayHasKey('firstname', $detected);
        $this->assertArrayHasKey('lastname', $detected);
        $this->assertArrayHasKey('email', $detected);
        
        $this->assertEquals('first_name', $detected['firstname']['type']);
        $this->assertEquals('last_name', $detected['lastname']['type']);
        $this->assertEquals('email', $detected['email']['type']);
    }

    public function testPseudonymizeTable(): void
    {
        $rules = [
            'firstname' => ['action' => 'pseudonymize', 'type' => 'first_name'],
            'lastname' => ['action' => 'pseudonymize', 'type' => 'last_name'],
            'email' => ['action' => 'pseudonymize', 'type' => 'email'],
        ];
        
        $result = $this->pseudonymizer->pseudonymizeTable('users', $rules);
        
        $this->assertEquals(3, $result['processed']);
        $this->assertEquals(3, $result['updated']);
        $this->assertEmpty($result['errors']);
        
        // Prüfen ob Werte geändert wurden
        $rows = $this->dbService->fetchBatch('users', ['firstname', 'lastname', 'email'], 0, 3);
        
        $this->assertNotEquals('Max', $rows[0]['firstname']);
        $this->assertNotEquals('Müller', $rows[0]['lastname']);
        $this->assertNotEquals('max@example.com', $rows[0]['email']);
        
        // Konsistenz: gleicher Original-Wert = gleicher Fake-Wert
        // (wir können das nicht direkt testen ohne Mapping, aber zumindest prüfen dass es nicht NULL ist)
        $this->assertNotNull($rows[0]['firstname']);
        $this->assertNotNull($rows[0]['lastname']);
    }

    public function testPseudonymizeWithKeepRule(): void
    {
        $rules = [
            'firstname' => ['action' => 'keep', 'type' => 'first_name'],
            'lastname' => ['action' => 'pseudonymize', 'type' => 'last_name'],
        ];
        
        $result = $this->pseudonymizer->pseudonymizeTable('users', $rules);
        
        $rows = $this->dbService->fetchBatch('users', ['firstname', 'lastname'], 0, 3);
        
        // firstname sollte unverändert sein
        $this->assertEquals('Max', $rows[0]['firstname']);
        // lastname sollte geändert sein
        $this->assertNotEquals('Müller', $rows[0]['lastname']);
    }

    public function testPseudonymizePreservesNull(): void
    {
        // Zeile mit NULL einfügen
        $pdo = $this->dbService->getPdo();
        $pdo->exec("INSERT INTO users (firstname, lastname, email, age) VALUES 
            (NULL, 'Test', 'test@example.com', 20)");
        
        $rules = [
            'firstname' => ['action' => 'pseudonymize', 'type' => 'first_name'],
            'lastname' => ['action' => 'pseudonymize', 'type' => 'last_name'],
        ];
        
        $this->pseudonymizer->pseudonymizeTable('users', $rules);
        
        $rows = $this->dbService->fetchBatch('users', ['firstname', 'lastname'], 3, 1);
        
        // NULL sollte erhalten bleiben
        $this->assertNull($rows[0]['firstname']);
        // lastname sollte geändert sein (Test → Fake)
        $this->assertNotEquals('Test', $rows[0]['lastname'], 'lastname sollte pseudonymisiert sein');
    }

    public function testExportAsSql(): void
    {
        $rules = [
            'firstname' => ['action' => 'pseudonymize', 'type' => 'first_name'],
        ];
        
        $sql = $this->pseudonymizer->exportAsSql('users', $rules);
        
        $this->assertStringContainsString('DataDachs Export', $sql);
        $this->assertStringContainsString('INSERT INTO users', $sql);
        $this->assertStringContainsString('Max', $sql); // Original-Werte im Export
    }
}
