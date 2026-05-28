<?php
/**
 * DataDachs – SQL Parser Tests
 */

namespace DataDachs\Tests;

use PHPUnit\Framework\TestCase;
use DataDachs\Parser\SqlParser;
use DataDachs\Service\PiiDetector;
use DataDachs\Service\FakerEngine;

class SqlParserTest extends TestCase
{
    private SqlParser $parser;
    private FakerEngine $faker;
    
    protected function setUp(): void
    {
        $detector = new PiiDetector();
        $this->faker = new FakerEngine();
        $this->parser = new SqlParser($detector, $this->faker);
    }
    
    public function testAnalyzeSimpleInsert(): void
    {
        $sql = "INSERT INTO users (id, firstname, lastname, email) VALUES (1, 'Max', 'Müller', 'max@example.com');";
        $result = $this->parser->analyze($sql);
        
        $this->assertArrayHasKey('users', $result);
        $this->assertTrue($result['users']['detected']['firstname']['detected']);
        $this->assertTrue($result['users']['detected']['lastname']['detected']);
        $this->assertTrue($result['users']['detected']['email']['detected']);
    }
    
    public function testPseudonymizePreservesStructure(): void
    {
        $sql = "INSERT INTO users (id, firstname, lastname, email) VALUES (1, 'Max', 'Müller', 'max@example.com');";
        $rules = [
            'firstname' => ['action' => 'pseudonymize', 'type' => 'first_name', 'faker_method' => 'firstName'],
            'lastname' => ['action' => 'pseudonymize', 'type' => 'last_name', 'faker_method' => 'lastName'],
            'email' => ['action' => 'pseudonymize', 'type' => 'email', 'faker_method' => 'safeEmail'],
        ];
        
        $result = $this->parser->pseudonymize($sql, $rules);
        
        // Struktur erhalten
        $this->assertStringContainsString('INSERT INTO users', $result);
        $this->assertStringContainsString('(id, firstname, lastname, email)', $result);
        
        // Werte geändert
        $this->assertStringNotContainsString("'Max'", $result);
        $this->assertStringNotContainsString("'Müller'", $result);
        $this->assertStringNotContainsString('max@example.com', $result);
        
        // ID erhalten
        $this->assertStringContainsString('(1,', $result);
    }
    
    public function testMultiRowInsert(): void
    {
        $sql = "INSERT INTO users (firstname, lastname) VALUES ('Max', 'Müller'), ('Anna', 'Schmidt'), ('Tom', 'Weber');";
        $rules = [
            'firstname' => ['action' => 'pseudonymize', 'type' => 'first_name', 'faker_method' => 'firstName'],
            'lastname' => ['action' => 'pseudonymize', 'type' => 'last_name', 'faker_method' => 'lastName'],
        ];
        
        $result = $this->parser->pseudonymize($sql, $rules);
        
        // Alle 3 Zeilen verarbeitet
        $this->assertStringNotContainsString("'Max'", $result);
        $this->assertStringNotContainsString("'Anna'", $result);
        $this->assertStringNotContainsString("'Tom'", $result);
    }
    
    public function testNullPreserved(): void
    {
        $sql = "INSERT INTO users (firstname, lastname, email) VALUES ('Max', NULL, 'max@example.com');";
        $rules = [
            'firstname' => ['action' => 'pseudonymize', 'type' => 'first_name', 'faker_method' => 'firstName'],
            'email' => ['action' => 'pseudonymize', 'type' => 'email', 'faker_method' => 'safeEmail'],
        ];
        
        $result = $this->parser->pseudonymize($sql, $rules);
        $this->assertStringContainsString('NULL', $result);
    }
    
    public function testNumericPreserved(): void
    {
        $sql = "INSERT INTO users (id, age) VALUES (42, 33);";
        $rules = [];
        
        $result = $this->parser->pseudonymize($sql, $rules);
        $this->assertStringContainsString('(42, 33)', $result);
    }
    
    public function testConsistency(): void
    {
        $sql = "INSERT INTO users (email) VALUES ('max@example.com'), ('max@example.com');";
        $rules = [
            'email' => ['action' => 'pseudonymize', 'type' => 'email', 'faker_method' => 'safeEmail'],
        ];
        
        $result = $this->parser->pseudonymize($sql, $rules);
        
        // Gleiche Originalwerte → gleicher Fake-Wert
        preg_match_all("/'([^']+)'/", $result, $matches);
        $this->assertCount(2, $matches[1]);
        $this->assertEquals($matches[1][0], $matches[1][1]);
    }
    
    public function testGermanUmlauts(): void
    {
        $sql = "INSERT INTO users (firstname, lastname) VALUES ('Jürgen', 'Größer');";
        $rules = [
            'firstname' => ['action' => 'pseudonymize', 'type' => 'first_name', 'faker_method' => 'firstName'],
            'lastname' => ['action' => 'pseudonymize', 'type' => 'last_name', 'faker_method' => 'lastName'],
        ];
        
        $result = $this->parser->pseudonymize($sql, $rules);
        
        $this->assertStringNotContainsString("'Jürgen'", $result);
        $this->assertStringNotContainsString("'Größer'", $result);
    }
    
    public function testQuotedIdentifiers(): void
    {
        $sql = "INSERT INTO `users` (`firstname`, `lastname`) VALUES ('Max', 'Müller');";
        $result = $this->parser->analyze($sql);
        
        $this->assertArrayHasKey('users', $result);
        $this->assertContains('firstname', $result['users']['columns']);
    }
}
