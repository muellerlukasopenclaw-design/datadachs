<?php
/**
 * DataDachs – Tests für PreserveRuleService
 */

namespace DataDachs\Tests;

use DataDachs\Service\PreserveRuleService;
use PHPUnit\Framework\TestCase;

class PreserveRuleServiceTest extends TestCase
{
    public function testEmptyRules(): void
    {
        $service = new PreserveRuleService();
        $this->assertFalse($service->shouldPreserve('admin'));
        $this->assertFalse($service->shouldPreserve('anything'));
    }

    public function testExactMatch(): void
    {
        $service = new PreserveRuleService('admin,root,system');
        $this->assertTrue($service->shouldPreserve('admin'));
        $this->assertTrue($service->shouldPreserve('root'));
        $this->assertTrue($service->shouldPreserve('system'));
        $this->assertFalse($service->shouldPreserve('other'));
    }

    public function testCaseInsensitive(): void
    {
        $service = new PreserveRuleService('Admin,ROOT', false);
        $this->assertTrue($service->shouldPreserve('admin'));
        $this->assertTrue($service->shouldPreserve('ADMIN'));
        $this->assertTrue($service->shouldPreserve('Root'));
    }

    public function testCaseSensitive(): void
    {
        $service = new PreserveRuleService('Admin', true);
        $this->assertTrue($service->shouldPreserve('Admin'));
        $this->assertFalse($service->shouldPreserve('admin'));
        $this->assertFalse($service->shouldPreserve('ADMIN'));
    }

    public function testSubstringMatch(): void
    {
        $service = new PreserveRuleService('admin');
        $this->assertTrue($service->shouldPreserve('admin_user'));
        $this->assertTrue($service->shouldPreserve('super_admin'));
    }

    public function testJsonRules(): void
    {
        $service = new PreserveRuleService('["admin","root","system"]');
        $this->assertTrue($service->shouldPreserve('admin'));
        $this->assertTrue($service->shouldPreserve('root'));
        $this->assertFalse($service->shouldPreserve('other'));
    }

    public function testNewlineSeparated(): void
    {
        $service = new PreserveRuleService("admin\nroot\nsystem");
        $this->assertTrue($service->shouldPreserve('admin'));
        $this->assertTrue($service->shouldPreserve('root'));
        $this->assertTrue($service->shouldPreserve('system'));
    }

    public function testAddAndRemoveRule(): void
    {
        $service = new PreserveRuleService();
        $service->addRule('test');
        $this->assertTrue($service->shouldPreserve('test'));
        
        $service->removeRule('test');
        $this->assertFalse($service->shouldPreserve('test'));
    }

    public function testDefaultRules(): void
    {
        $service = PreserveRuleService::withDefaults();
        $this->assertTrue($service->shouldPreserve('admin'));
        $this->assertTrue($service->shouldPreserve('root'));
        $this->assertTrue($service->shouldPreserve('system'));
        $this->assertTrue($service->shouldPreserve('guest'));
        $this->assertTrue($service->shouldPreserve('noreply'));
    }

    public function testEmailPreserve(): void
    {
        $service = new PreserveRuleService('test@example.com,admin@company.local');
        $this->assertTrue($service->shouldPreserve('test@example.com'));
        $this->assertTrue($service->shouldPreserve('admin@company.local'));
        $this->assertFalse($service->shouldPreserve('other@example.com'));
    }
}
