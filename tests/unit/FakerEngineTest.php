<?php
/**
 * DataDachs – Faker Engine Tests
 */

namespace DataDachs\Tests;

use PHPUnit\Framework\TestCase;
use DataDachs\Service\FakerEngine;

class FakerEngineTest extends TestCase
{
    public function testConsistency(): void
    {
        $faker = new FakerEngine();
        
        $fake1 = $faker->fake('email', 'max@example.com');
        $fake2 = $faker->fake('email', 'max@example.com');
        
        $this->assertEquals($fake1, $fake2);
    }
    
    public function testSafeEmailDomain(): void
    {
        $faker = new FakerEngine();
        $fake = $faker->fake('email', 'test@example.com');
        
        $this->assertStringContainsString('@example.', $fake);
        $this->assertStringNotContainsString('@gmail.com', $fake);
        $this->assertStringNotContainsString('@yahoo.com', $fake);
    }
    
    public function testDifferentOriginalsDifferentFakes(): void
    {
        $faker = new FakerEngine();
        
        $fake1 = $faker->fake('first_name', 'Max');
        $fake2 = $faker->fake('first_name', 'Anna');
        
        $this->assertNotEquals($fake1, $fake2);
    }
    
    public function testDeterministicSeed(): void
    {
        $faker1 = new FakerEngine('seed123');
        $faker2 = new FakerEngine('seed123');
        
        $fake1 = $faker1->fake('email', 'test@example.com');
        $fake2 = $faker2->fake('email', 'test@example.com');
        
        $this->assertEquals($fake1, $fake2);
    }
    
    public function testClearMapping(): void
    {
        $faker = new FakerEngine();
        $faker->fake('email', 'test@example.com');
        
        $this->assertNotEmpty($faker->getMapping());
        
        $faker->clearMapping();
        $this->assertEmpty($faker->getMapping());
    }
    
    public function testIbanFormat(): void
    {
        $faker = new FakerEngine();
        $fake = $faker->fake('iban', 'DE123456789');
        
        $this->assertMatchesRegularExpression('/^[A-Z]{2}\d{2}[A-Z0-9]{4}\d{7}([A-Z0-9]?){0,16}$/', $fake);
    }
    
    public function testPhoneNumber(): void
    {
        $faker = new FakerEngine();
        $fake = $faker->fake('phone', '+49 211 123456');
        
        $this->assertNotEmpty($fake);
        $this->assertIsString($fake);
    }
}
