<?php

namespace Spatie\Async;

use Exception;
use PHPUnit\Framework\TestCase;
use Spatie\Async\Tests\MyClass;

class PoolTest extends TestCase
{
    protected function setUp()
    {
        parent::setUp();
    }

    /** @test */
    public function it_can_run_processes_in_parallel()
    {
        $pool = Pool::create();

        $startTime = microtime(true);

        for ($i = 0; $i < 5; $i++) {
            $pool->add(function () {
                usleep(1000);
            });
        }

        $pool->wait();

        $endTime = microtime(true);

        $executionTime = $endTime - $startTime;

        $this->assertTrue($executionTime < 0.2, "Execution time was {$executionTime}, expected less than 0.2.");
    }

    /** @test */
    public function it_can_handle_success()
    {
        $pool = Pool::create();

        $counter = 0;

        for ($i = 0; $i < 5; $i++) {
            $pool->add(function () {
                return 2;
            })->then(function (int $output) use (&$counter) {
                $counter += $output;
            });
        }

        $pool->wait();

        $this->assertEquals(10, $counter);
    }

    /** @test */
    public function it_can_handle_timeout()
    {
        $pool = Pool::create()
            ->timeout(1);

        $counter = 0;

        for ($i = 0; $i < 5; $i++) {
            $pool->add(function () {
                sleep(2);
            })->timeout(function () use (&$counter) {
                $counter += 1;
            });
        }

        $pool->wait();

        $this->assertEquals(5, $counter);
    }

    /** @test */
    public function it_can_handle_exceptions()
    {
        $pool = Pool::create();

        for ($i = 0; $i < 5; $i++) {
            $pool->add(function () {
                throw new Exception('test');
            })->catch(function (Exception $e) {
                $this->assertEquals('test', $e->getMessage());
            });
        }

        $pool->wait();

        $this->assertCount(5, $pool->getFailed());
    }

    /** @test */
    public function it_can_handle_a_maximum_of_concurrent_processes()
    {
        $pool = Pool::create()
            ->concurrency(1);

        $startTime = microtime(true);

        for ($i = 0; $i < 5; $i++) {
            $pool->add(function () {
                usleep(1000);
            });
        }

        $pool->wait();

        $endTime = microtime(true);

        $executionTime = $endTime - $startTime;

        $this->assertTrue($executionTime >= 0.2, "Execution time was {$executionTime}, expected more than 0.2.");
        $this->assertCount(5, $pool->getFinished());
    }

    /** @test */
    public function it_works_with_helper_functions()
    {
        $pool = Pool::create();

        $counter = 0;

        for ($i = 0; $i < 5; $i++) {
            $pool[] = async(function () {
                usleep(random_int(10, 1000));

                return 2;
            })->then(function (int $output) use (&$counter) {
                $counter += $output;
            });
        }

        await($pool);

        $this->assertEquals(10, $counter);
    }

    /** @test */
    public function it_can_use_a_class_from_the_parent_process()
    {
        $pool = Pool::create();

        /** @var MyClass $result */
        $result = null;

        $pool[] = async(function () {
            $class = new MyClass();

            $class->property = true;

            return $class;
        })->then(function (MyClass $class) use (&$result) {
            $result = $class;
        });

        await($pool);

        $this->assertInstanceOf(MyClass::class, $result);
        $this->assertTrue($result->property);
    }

    /** @test */
    public function it_returns_all_the_output_as_an_array()
    {
        $pool = Pool::create();

        /** @var MyClass $result */
        $result = null;

        for ($i = 0; $i < 5; $i++) {
            $pool[] = async(function () {
                return 2;
            });
        }

        $result = await($pool);

        $this->assertCount(5, $result);
        $this->assertEquals(10, array_sum($result));
    }
};
