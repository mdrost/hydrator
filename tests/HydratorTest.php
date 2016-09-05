<?php
declare(strict_types=1);

namespace ApiClients\Tests\Foundation\Hydrator;

use ApiClients\Foundation\Hydrator\Factory;
use ApiClients\Foundation\Hydrator\Options;
use ApiClients\Tests\Foundation\Hydrator\Resources\Async\Resource as AsyncResource;
use ApiClients\Tests\Foundation\Hydrator\Resources\Async\SubResource as AsyncSubResource;
use ApiClients\Tests\Foundation\Hydrator\Resources\Sync\Resource as SyncResource;
use Doctrine\Common\Cache\FilesystemCache;

class HydratorTest extends TestCase
{
    public function testBuildAsyncFromSync()
    {
        $hydrator = Factory::create([
            Options::NAMESPACE => 'ApiClients\Tests\Foundation\Hydrator\Resources',
            Options::NAMESPACE_SUFFIX => 'Async',
            Options::RESOURCE_CACHE_DIR => $this->getTmpDir(),
            Options::RESOURCE_NAMESPACE => $this->getRandomNameSpace(),
        ]);
        $syncRepository = $this->hydrate(
            SyncResource::class,
            $this->getJson(),
            'Async'
        );
        $asyncRepository = $hydrator->buildAsyncFromSync('Resource', $syncRepository);
        $this->assertInstanceOf(AsyncResource::class, $asyncRepository);
        $this->assertSame(1, $asyncRepository->id());
        $this->assertSame('Wyrihaximus/php-travis-client', $asyncRepository->slug());
        $this->assertInstanceOf(AsyncSubResource::class, $asyncRepository->sub());
        $this->assertSame(1, $asyncRepository->sub()->id());
        $this->assertSame('Wyrihaximus/php-travis-client', $asyncRepository->sub()->slug());
        $this->assertSame(3, count($asyncRepository->subs()));
        for ($i = 0; $i < count($asyncRepository->subs()); $i++) {
            $this->assertInstanceOf(AsyncSubResource::class, $asyncRepository->subs()[$i]);
            $this->assertSame($i + 1, $asyncRepository->subs()[$i]->id());
            $this->assertSame('Wyrihaximus/php-travis-client', $asyncRepository->subs()[$i]->slug());
        }
    }

    public function testSetGeneratedClassesTargetDir()
    {
        $tmpDir = $this->getTmpDir();
        $hydrator = Factory::create([
            Options::NAMESPACE => 'ApiClients\Tests\Foundation\Hydrator\Resources',
            Options::NAMESPACE_SUFFIX => 'Async',
            Options::RESOURCE_CACHE_DIR => $tmpDir,
            Options::RESOURCE_NAMESPACE => $this->getRandomNameSpace(),
        ]);
        $hydrator->hydrate(
            'Resource',
            $this->getJson()
        );
        $files = $this->getFilesInDirectory($tmpDir);
        $this->assertSame(2, count($files));
    }

    public function testExtract()
    {
        $json = $this->getJson();
        $tmpDir = $this->getTmpDir();
        $hydrator = Factory::create([
            Options::NAMESPACE => 'ApiClients\Tests\Foundation\Hydrator\Resources',
            Options::NAMESPACE_SUFFIX => 'Async',
            Options::RESOURCE_CACHE_DIR => $tmpDir,
            Options::RESOURCE_NAMESPACE => $this->getRandomNameSpace(),
        ]);
        $repository = $hydrator->hydrate(
            'Resource',
            $json
        );
        $this->assertEquals($json, $hydrator->extract('Resource', $repository));
    }

    public function testAnnotationCache()
    {

        $json = $this->getJson();
        $tmpDir = $this->getTmpDir();
        $hydrator = Factory::create([
            Options::NAMESPACE => 'ApiClients\Tests\Foundation\Hydrator\Resources',
            Options::NAMESPACE_SUFFIX => 'Async',
            Options::ANNOTATION_CACHE => new FilesystemCache(
                $tmpDir
            ),
            Options::RESOURCE_NAMESPACE => $this->getRandomNameSpace(),

        ]);
        $files = $this->getFilesInDirectory($tmpDir);
        $this->assertSame(0, count($files));
        $hydrator->hydrate(
            'Resource',
            $json
        );
        $files = $this->getFilesInDirectory($tmpDir);
        $this->assertSame(4, count($files));
        $hydrator->hydrate(
            'Resource',
            $json
        );
        $files = $this->getFilesInDirectory($tmpDir);
        $this->assertSame(4, count($files));
    }
}
