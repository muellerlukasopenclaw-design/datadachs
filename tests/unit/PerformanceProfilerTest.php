<?php
/**
 * DataDachs – Tests für PerformanceProfiler
 */

namespace DataDachs\Tests;

use DataDachs\Service\PerformanceProfiler;
use PHPUnit\Framework\TestCase;

class PerformanceProfilerTest extends TestCase
{
    public function testDisabled(): void
    {
        $profiler = new PerformanceProfiler(false);
        $profiler->start('test');
        usleep(1000);
        $result = $profiler->stop('test');
        
        $this->assertEquals(0, $result['duration']);
        $this->assertEquals(0, $result['memory']);
    }

    public function testBasicTiming(): void
    {
        $profiler = new PerformanceProfiler(true);
        $profiler->start('test');
        usleep(5000); // 5ms
        $result = $profiler->stop('test');
        
        $this->assertGreaterThan(0, $result['duration_ms']);
        $this->assertArrayHasKey('memory_mb', $result);
    }

    public function testMultipleTimers(): void
    {
        $profiler = new PerformanceProfiler(true);
        
        $profiler->start('timer1');
        usleep(1000);
        $profiler->stop('timer1');
        
        $profiler->start('timer2');
        usleep(1000);
        $profiler->stop('timer2');
        
        $results = $profiler->getResults();
        $this->assertCount(2, $results);
    }

    public function testTotalDuration(): void
    {
        $profiler = new PerformanceProfiler(true);
        
        $profiler->start('a');
        usleep(1000);
        $profiler->stop('a');
        
        $profiler->start('b');
        usleep(1000);
        $profiler->stop('b');
        
        $total = $profiler->getTotalDuration();
        $this->assertGreaterThan(0, $total);
    }

    public function testFormatResults(): void
    {
        $profiler = new PerformanceProfiler(true);
        $profiler->start('test');
        usleep(1000);
        $profiler->stop('test');
        
        $formatted = $profiler->formatResults();
        $this->assertStringContainsString('Performance Report', $formatted);
        $this->assertStringContainsString('test', $formatted);
    }

    public function testReset(): void
    {
        $profiler = new PerformanceProfiler(true);
        $profiler->start('test');
        $profiler->stop('test');
        
        $profiler->reset();
        $this->assertEmpty($profiler->getResults());
    }
}
