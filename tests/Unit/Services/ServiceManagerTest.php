<?php declare(strict_types = 1);

namespace Tests\OriNette\DI\Unit\Services;

use Nette\DI\MissingServiceException;
use OriNette\DI\Boot\ManualConfigurator;
use Orisai\Exceptions\Logic\InvalidArgument;
use PHPUnit\Framework\TestCase;
use stdClass;
use Tests\OriNette\DI\Doubles\TestingServiceManager;
use Tests\OriNette\DI\Doubles\TestService;
use function dirname;

final class ServiceManagerTest extends TestCase
{

	public function test(): void
	{
		$configurator = new ManualConfigurator(dirname(__DIR__, 3));
		$configurator->setDebugMode(true);
		$configurator->addConfig(__DIR__ . '/serviceManager.neon');

		$container = $configurator->createContainer();

		$manager = $container->getByType(TestingServiceManager::class);

		self::assertSame(
			[
				'missing',
				'test',
			],
			$manager->getKeys(),
		);

		// Has service
		self::assertTrue($manager->hasService('missing'));
		self::assertTrue($manager->hasService('test'));
		self::assertFalse($manager->hasService('nonexistent'));

		// Get service name
		self::assertSame('service.test', $manager->getServiceName('test'));
		self::assertSame('service.missing', $manager->getServiceName('missing'));

		$e = null;
		try {
			$manager->getServiceName('nonexistent');
		} catch (InvalidArgument $e) {
			// Handled below
		}

		self::assertNotNull($e);
		self::assertSame(<<<'MSG'
Context: Trying to call
         Tests\OriNette\DI\Doubles\TestingServiceManager->getServiceName().
Problem: Given key 'nonexistent' has no service associated.
Solution: Call it only with key which exists in service map.
MSG, $e->getMessage());

		// Get service
		$test = $container->getService('service.test');
		self::assertInstanceOf(TestService::class, $test);

		self::assertSame($test, $manager->getService('test'));
		self::assertNull($manager->getService('nonexistent'));

		$e = null;
		try {
			$manager->getService('missing');
		} catch (MissingServiceException $e) {
			// Handled below
		}

		self::assertNotNull($e);

		// Get typed service
		self::assertSame($test, $manager->getTypedService('test', TestService::class));
		self::assertNull($manager->getTypedService('nonexistent', TestService::class));

		$e = null;
		try {
			$manager->getTypedService('test', stdClass::class);
		} catch (InvalidArgument $e) {
			// Handled below
		}

		self::assertNotNull($e);
		self::assertSame($testInvalidMessage = <<<'MSG'
Context: Service 'service.test' returns instance of
         Tests\OriNette\DI\Doubles\TestService.
Problem: Tests\OriNette\DI\Doubles\TestingServiceManager supports only instances
         of stdClass.
Solution: Remove service from TestingServiceManager or make the service return
          supported object type.
MSG, $e->getMessage());

		// Get typed service or throw
		self::assertSame($test, $manager->getTypedServiceOrThrow('test', TestService::class));

		$e = null;
		try {
			$manager->getTypedServiceOrThrow('test', stdClass::class);
		} catch (InvalidArgument $e) {
			// Handled below
		}

		self::assertNotNull($e);
		self::assertSame($testInvalidMessage, $e->getMessage());

		$e = null;
		try {
			$manager->getTypedServiceOrThrow('nonexistent', TestService::class);
		} catch (InvalidArgument $e) {
			// Handled below
		}

		self::assertNotNull($e);
		self::assertSame($nonexistentMissingMessage = <<<'MSG'
Context: Trying to get service by key 'nonexistent' from
         Tests\OriNette\DI\Doubles\TestingServiceManager.
Problem: No service is registered under that key but service of type
         Tests\OriNette\DI\Doubles\TestService is required.
Solution: Add service with key 'nonexistent' to TestingServiceManager.
MSG, $e->getMessage());

		// Missing service
		$e = null;
		try {
			$manager->throwMissingService('nonexistent', TestService::class);
		} catch (InvalidArgument $e) {
			// Handled below
		}

		self::assertNotNull($e);
		self::assertSame($nonexistentMissingMessage, $e->getMessage());

		// Invalid service type
		$e = null;
		try {
			$manager->throwInvalidServiceType('test', stdClass::class, $test);
		} catch (InvalidArgument $e) {
			// Handled below
		}

		self::assertNotNull($e);
		self::assertSame($testInvalidMessage, $e->getMessage());
	}

}
