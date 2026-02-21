<?php

declare(strict_types=1);

namespace Tests\Clockwork\Storage;

use Clockwork\Request\Request;
use Clockwork\Storage\FileStorage;
use Clockwork\Storage\StorageInterface;
use PHPUnit\Framework\TestCase;

class FileStorageTest extends TestCase
{
    private const STORAGE_ROOT_DIR = __DIR__ . '/storage';
    private const STORAGE_PERMISSIONS = 0700;
    private const STORAGE_EXPIRATION = 10;

    private string $storageDir;
    private FileStorage $storage;

    public function setUp(): void
    {
        parent::setUp();

        $this->storageDir = static::STORAGE_ROOT_DIR . '/' . uniqid();

        $this->storage = new FileStorage(
            $this->storageDir,
            static::STORAGE_PERMISSIONS,
            static::STORAGE_EXPIRATION
        );
    }

    public function tearDown(): void
    {
        if (file_exists($this->storageDir)) {
            static::rmdirRecursive($this->storageDir);
        }

        parent::tearDown();
    }

    public static function tearDownAfterClass(): void
    {
        if (file_exists(static::STORAGE_ROOT_DIR)) {
            static::rmdirRecursive(static::STORAGE_ROOT_DIR);
        }

        parent::tearDownAfterClass();
    }

    private static function rmdirRecursive(string $dir): void
    {
        if (!is_dir($dir)) {
            if (is_file($dir)) {
                unlink($dir);
                return;
            }
            return;
        }
        $iter = dir($dir);
        while (false !== ($entry = $iter->read())) {
            if (in_array($entry, ['..', '.'])) {
                continue;
            }
            static::rmdirRecursive($dir . '/' . $entry);
        }
        rmdir($dir);
    }

    public function testInterface(): void
    {
        static::assertInstanceOf(StorageInterface::class, $this->storage);
    }

    public function testStore(): void
    {
        $request = new Request([
            'id' => '12345',
            'version' => 1,
            'type' => 'request',
            'responseDuration' => -12345678.9,
            'updateToken' => 'abcd1234',
        ]);

        // ensure the request doesn't exist yet
        static::assertNull($this->storage->find($request->id));

        // store request
        $this->storage->store($request);

        // ensure it can be loaded by ID
        static::assertRequestEquals(
            $request,
            $this->storage->find($request->id)
        );

        // update request
        $request->addEmail('test', 'nobody@example.com');
        $this->storage->update($request);

        // ensure it can be loaded by ID
        static::assertRequestEquals(
            $request,
            $this->storage->find($request->id)
        );
    }

    public function testCleanup(): void
    {
        $now = microtime(true);

        $request1 = new Request([
            'id' => '12345',
            'time' => $now - static::STORAGE_EXPIRATION * 60 - 1,
        ]);
        $request2 = new Request([
            'id' => '67890',
            'time' => $now,
        ]);

        // store requests
        // Note: This randomly triggers a cleanup already, so it might be
        // that request 1 is gone even before we trigger one ourselves.
        $this->storage->store($request1);
        $this->storage->store($request2);

        // trigger cleanup
        $this->storage->cleanup(true);

        // ensure request 1 was purged
        static::assertNull($this->storage->find($request1->id));
        // ensure request 2 was kept
        static::assertNotNull($this->storage->find($request2->id));
    }

    public function testStore2(): void
    {
        $request1 = new Request([
            'id' => '12345',
        ]);
        $request2 = new Request([
            'id' => '67890',
        ]);

        // store requests
        $this->storage->store($request1);
        $this->storage->store($request2);

        // ensure both can be loaded
        $allRequests = $this->storage->all();
        static::assertContainsOnlyInstancesOf(Request::class, $allRequests);
        static::assertCount(2, $allRequests);

        // ensure both can be loaded by ID
        static::assertRequestEquals(
            $request1,
            $this->storage->find($request1->id)
        );
        static::assertRequestEquals(
            $request2,
            $this->storage->find($request2->id)
        );

        // ensure the next after the first is the second
        $next = $this->storage->next($request1->id);
        static::assertContainsOnlyInstancesOf(Request::class, $next);
        static::assertCount(1, $next);
        static::assertRequestEquals(
            $request2,
            $next[0]
        );

        // ensure the previous before the second is the first
        $previous = $this->storage->previous($request2->id);
        static::assertContainsOnlyInstancesOf(Request::class, $previous);
        static::assertCount(1, $previous);
        static::assertRequestEquals(
            $request1,
            $previous[0]
        );
    }

    /**
     * compare requests
     *
     * This ensures that a result is actually a request instance and with
     * the expected properties.
     */
    private static function assertRequestEquals(Request $expected, $actual): void
    {
        static::assertInstanceOf(Request::class, $actual);

        $expectedData = array_filter($expected->toArray());
        $actualData = array_filter($actual->toArray());

        static::assertEquals($expectedData, $actualData);
    }
}
