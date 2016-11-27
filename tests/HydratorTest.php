<?php
declare(strict_types=1);

namespace ApiClients\Tests\Foundation\Hydrator;

use ApiClients\Foundation\Hydrator\Factory;
use ApiClients\Foundation\Hydrator\Options;
use ApiClients\Tests\Foundation\Hydrator\Resources\Async\EmptySubResource as AsyncEmptySubResource;
use ApiClients\Tests\Foundation\Hydrator\Resources\Async\Resource as AsyncResource;
use ApiClients\Tests\Foundation\Hydrator\Resources\Async\SubResource as AsyncSubResource;
use ApiClients\Tests\Foundation\Hydrator\Resources\Sync\Resource as SyncResource;
use ApiClients\Tools\CommandBus\CommandBus;
use DI\ContainerBuilder;
use Doctrine\Common\Cache\FilesystemCache;
use React\EventLoop\Factory as LoopFactory;
use React\EventLoop\LoopInterface;
use TypeError;

class HydratorTest extends TestCase
{
    public function testBuildAsyncFromSync()
    {
        $loop = LoopFactory::create();
        $container = ContainerBuilder::buildDevContainer();
        $container->set(LoopInterface::class, $loop);
        $container->set(CommandBus::class, $this->createCommandBus($loop));
        $hydrator = Factory::create($container, [
            Options::NAMESPACE => 'ApiClients\Tests\Foundation\Hydrator\Resources',
            Options::NAMESPACE_SUFFIX => 'Async',
            Options::RESOURCE_CACHE_DIR => $this->getTmpDir(),
            Options::RESOURCE_NAMESPACE => $this->getRandomNameSpace(),
        ]);
        $syncRepository = $hydrator->hydrateFQCN(
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
        $this->assertSame(4, count($asyncRepository->subs()));
        for ($i = 0; $i < 3; $i++) {
            $this->assertInstanceOf(AsyncSubResource::class, $asyncRepository->subs()[$i]);
            $this->assertSame($i + 1, $asyncRepository->subs()[$i]->id());
            $this->assertSame('Wyrihaximus/php-travis-client', $asyncRepository->subs()[$i]->slug());
        }

        $this->assertInstanceOf(AsyncEmptySubResource::class, $asyncRepository->subs()[3]);;
    }

    public function testSetGeneratedClassesTargetDir()
    {
        $loop = LoopFactory::create();
        $tmpDir = $this->getTmpDir();
        $container = ContainerBuilder::buildDevContainer();
        $container->set(LoopInterface::class, $loop);
        $container->set(CommandBus::class, $this->createCommandBus($loop));
        $hydrator = Factory::create($container, [
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
        $this->assertSame(3, count($files));
    }

    public function testExtract()
    {
        $loop = LoopFactory::create();
        $json = $this->getJson();
        $tmpDir = $this->getTmpDir();
        $container = ContainerBuilder::buildDevContainer();
        $container->set(LoopInterface::class, $loop);
        $container->set(CommandBus::class, $this->createCommandBus($loop));
        $hydrator = Factory::create($container, [
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
        $loop = LoopFactory::create();
        $json = $this->getJson();
        $tmpDir = $this->getTmpDir();
        $annotationCache = $tmpDir . 'annotation' . DIRECTORY_SEPARATOR;
        mkdir($annotationCache);
        $resourceCache = $tmpDir . 'resource' . DIRECTORY_SEPARATOR;
        mkdir($resourceCache);
        $container = ContainerBuilder::buildDevContainer();
        $container->set(LoopInterface::class, $loop);
        $container->set(CommandBus::class, $this->createCommandBus($loop));
        $hydrator = Factory::create($container, [
            Options::NAMESPACE => 'ApiClients\Tests\Foundation\Hydrator\Resources',
            Options::NAMESPACE_SUFFIX => 'Async',
            Options::RESOURCE_CACHE_DIR => $resourceCache,
            Options::ANNOTATION_CACHE => new FilesystemCache(
                $annotationCache
            ),
            Options::RESOURCE_NAMESPACE => $this->getRandomNameSpace(),

        ]);
        $files = $this->getFilesInDirectory($annotationCache);
        $this->assertSame(0, count($files));
        $hydrator->hydrate(
            'Resource',
            $json
        );
        $files = $this->getFilesInDirectory($annotationCache);
        $this->assertSame(7, count($files));
        $hydrator->hydrate(
            'Resource',
            $json
        );
        $files = $this->getFilesInDirectory($annotationCache);
        $this->assertSame(7, count($files));
    }

    public function testPreheat()
    {
        $tmpDir = $this->getTmpDir();
        $container = ContainerBuilder::buildDevContainer();
        $hydrator = Factory::create($container, [
            Options::NAMESPACE => 'ApiClients\Tests\Foundation\Hydrator\Resources',
            Options::NAMESPACE_SUFFIX => 'Async',
            Options::RESOURCE_CACHE_DIR => $tmpDir,
            Options::RESOURCE_NAMESPACE => $this->getRandomNameSpace(),
        ]);

        $classCount = count(get_declared_classes());
        $hydrator->preheat(
            __DIR__ . DIRECTORY_SEPARATOR . 'Resources' . DIRECTORY_SEPARATOR,
            'ApiClients\Tests\Foundation\Hydrator\Resources'
        );
        $this->assertFalse($classCount === count(get_declared_classes()));
    }

    public function testPreheatFactory()
    {
        $tmpDir = $this->getTmpDir();
        $container = ContainerBuilder::buildDevContainer();
        Factory::create($container, [
            Options::NAMESPACE => 'ApiClients\Tests\Foundation\Hydrator\Resources',
            Options::NAMESPACE_SUFFIX => 'Async',
            Options::RESOURCE_CACHE_DIR => $tmpDir,
            Options::RESOURCE_NAMESPACE => $this->getRandomNameSpace(),
        ]);

        $classCount = count(get_declared_classes());

        $container = ContainerBuilder::buildDevContainer();
        Factory::create($container, [
            Options::NAMESPACE => 'ApiClients\Tests\Foundation\Hydrator\Resources',
            Options::NAMESPACE_DIR => __DIR__ . DIRECTORY_SEPARATOR . 'Resources' . DIRECTORY_SEPARATOR,
            Options::NAMESPACE_SUFFIX => 'Async',
            Options::RESOURCE_CACHE_DIR => $tmpDir,
            Options::RESOURCE_NAMESPACE => $this->getRandomNameSpace(),
        ]);

        $this->assertFalse($classCount === count(get_declared_classes()));
    }

    /**
     * @expectedException TypeError
     */
    public function testHydrateEmptyValue()
    {
        $json = [
            'id' => 1,
            'sub' => [
                'id' => 1,
                'slug' => 'Wyrihaximus/php-travis-client',
            ],
            'subs' => [
                [
                    'id' => 1,
                    'slug' => 'Wyrihaximus/php-travis-client',
                ],
                [
                    'id' => 2,
                    'slug' => 'Wyrihaximus/php-travis-client',
                ],
                [
                    'id' => 3,
                    'slug' => 'Wyrihaximus/php-travis-client',
                ],
                [],
            ],
        ];

        $loop = LoopFactory::create();
        $container = ContainerBuilder::buildDevContainer();
        $container->set(CommandBus::class, $this->createCommandBus($loop));
        $hydrator = Factory::create($container, [
            Options::NAMESPACE => 'ApiClients\Tests\Foundation\Hydrator\Resources',
            Options::NAMESPACE_SUFFIX => 'Async',
            Options::RESOURCE_CACHE_DIR => $this->getTmpDir(),
            Options::RESOURCE_NAMESPACE => $this->getRandomNameSpace(),
        ]);
        $syncRepository = $hydrator->hydrateFQCN(
            SyncResource::class,
            $json,
            'Async'
        );

        $syncRepository->slug();
    }

    public function testExtractEmptyValue()
    {
        $json = [
            'id' => 1,
            'sub' => [
                'id' => 1,
                'slug' => 'Wyrihaximus/php-travis-client',
            ],
            'subs' => [
                [
                    'id' => 1,
                    'slug' => 'Wyrihaximus/php-travis-client',
                ],
                [
                    'id' => 2,
                    'slug' => 'Wyrihaximus/php-travis-client',
                ],
                [
                    'id' => 3,
                    'slug' => 'Wyrihaximus/php-travis-client',
                ],
                [],
            ],
        ];

        $loop = LoopFactory::create();
        $container = ContainerBuilder::buildDevContainer();
        $container->set(CommandBus::class, $this->createCommandBus($loop));
        $hydrator = Factory::create($container, [
            Options::NAMESPACE => 'ApiClients\Tests\Foundation\Hydrator\Resources',
            Options::NAMESPACE_SUFFIX => 'Async',
            Options::RESOURCE_CACHE_DIR => $this->getTmpDir(),
            Options::RESOURCE_NAMESPACE => $this->getRandomNameSpace(),
        ]);
        $syncRepository = $hydrator->hydrateFQCN(
            SyncResource::class,
            $json,
            'Async'
        );

        $json['slog'] = null;
        $this->assertEquals($json, $hydrator->extractFQCN(SyncResource::class, $syncRepository));
    }
}
