<?php

declare(strict_types=1);

namespace Drupal\Tests\migrate\Unit;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\KeyValueStore\KeyValueStoreInterface;
use Drupal\migrate\Event\MigrateRollbackEvent;
use Drupal\migrate\MigrateException;
use Drupal\migrate\MigrateExecutable;
use Drupal\migrate\MigrateSkipRowException;
use Drupal\migrate\Plugin\migrate\source\SourcePluginBase;
use Drupal\migrate\Plugin\MigrateIdMapInterface;
use Drupal\migrate\Plugin\MigrateSourceInterface;
use Drupal\migrate\Row;

/**
 * @coversDefaultClass \Drupal\migrate\Plugin\migrate\source\SourcePluginBase
 * @group migrate
 */
class MigrateSourceTest extends MigrateTestCase {

  /**
   * Override the migration config.
   *
   * @var array
   */
  protected $defaultMigrationConfiguration = [
    'id' => 'test_migration',
    'source' => [],
  ];

  /**
   * Test row data.
   *
   * @var array
   */
  protected $row = ['test_sourceid1' => '1', 'timestamp' => 500];

  /**
   * Test source ids.
   *
   * @var array
   */
  protected $sourceIds = ['test_sourceid1' => 'test_sourceid1'];

  /**
   * The migration entity.
   *
   * @var \Drupal\migrate\Plugin\MigrationInterface
   */
  protected $migration;

  /**
   * The migrate executable.
   *
   * @var \Drupal\migrate\MigrateExecutable
   */
  protected $executable;

  /**
   * Gets the source plugin to test.
   *
   * @param array $configuration
   *   (optional) The source configuration. Defaults to an empty array.
   * @param array $migrate_config
   *   (optional) The migration configuration to be used in
   *   parent::getMigration(). Defaults to an empty array.
   * @param int $status
   *   (optional) The default status for the new rows to be imported. Defaults
   *   to MigrateIdMapInterface::STATUS_NEEDS_UPDATE.
   * @param int $high_water_value
   *   (optional) The high water mark to start from, if set.
   *
   * @return \Drupal\migrate\Plugin\MigrateSourceInterface
   *   A mocked source plugin.
   */
  protected function getSource($configuration = [], $migrate_config = [], $status = MigrateIdMapInterface::STATUS_NEEDS_UPDATE, $high_water_value = NULL) {
    $container = new ContainerBuilder();
    \Drupal::setContainer($container);

    $key_value = $this->createMock(KeyValueStoreInterface::class);

    $key_value_factory = $this->createMock(KeyValueFactoryInterface::class);
    $key_value_factory
      ->method('get')
      ->with('migrate:high_water')
      ->willReturn($key_value);
    $container->set('keyvalue', $key_value_factory);

    $container->set('cache.migrate', $this->createMock(CacheBackendInterface::class));

    $this->migrationConfiguration = $this->defaultMigrationConfiguration + $migrate_config;
    $this->migration = parent::getMigration();
    $this->executable = $this->getMigrateExecutable($this->migration);

    // Update the idMap for Source so the default is that the row has already
    // been imported. This allows us to use the highwater mark to decide on the
    // outcome of whether we choose to import the row.
    $id_map_array = ['original_hash' => '', 'hash' => '', 'source_row_status' => $status];
    $this->idMap
      ->expects($this->any())
      ->method('getRowBySource')
      ->willReturn($id_map_array);

    $constructor_args = [$configuration, 'd6_action', [], $this->migration];
    $methods = ['getModuleHandler', 'fields', 'getIds', '__toString', 'prepareRow', 'initializeIterator'];
    $source_plugin = $this->getMockBuilder(SourcePluginBase::class)
      ->onlyMethods($methods)
      ->setConstructorArgs($constructor_args)
      ->getMock();

    $source_plugin
      ->method('fields')
      ->willReturn([]);
    $source_plugin
      ->method('getIds')
      ->willReturn([]);
    $source_plugin
      ->method('__toString')
      ->willReturn('');
    $source_plugin
      ->method('prepareRow')
      ->willReturn(empty($migrate_config['prepare_row_false']));

    $rows = [$this->row];
    if (isset($configuration['high_water_property']) && isset($high_water_value)) {
      $property = $configuration['high_water_property']['name'];
      $rows = array_filter($rows, function (array $row) use ($property, $high_water_value) {
        return $row[$property] >= $high_water_value;
      });
    }
    $iterator = new \ArrayIterator($rows);

    $source_plugin
      ->method('initializeIterator')
      ->willReturn($iterator);

    $module_handler = $this->createMock(ModuleHandlerInterface::class);
    $source_plugin
      ->method('getModuleHandler')
      ->willReturn($module_handler);

    $this->migration
      ->method('getSourcePlugin')
      ->willReturn($source_plugin);

    return $source_plugin;
  }

  /**
   * @covers ::__construct
   */
  public function testHighwaterTrackChangesIncompatible(): void {
    $source_config = ['track_changes' => TRUE, 'high_water_property' => ['name' => 'something']];
    $this->expectException(MigrateException::class);
    $this->getSource($source_config);
  }

  /**
   * Tests that the source count is correct.
   *
   * @covers ::count
   */
  public function testCount(): void {
    // Mock the cache to validate set() receives appropriate arguments.
    $container = new ContainerBuilder();
    $cache = $this->createMock(CacheBackendInterface::class);
    $cache->expects($this->any())->method('set')
      ->with($this->isType('string'), $this->isType('int'), $this->isType('int'));
    $container->set('cache.migrate', $cache);
    \Drupal::setContainer($container);

    // Test that the basic count works.
    $source = $this->getSource();
    $this->assertEquals(1, $source->count());

    // Test caching the count works.
    $source = $this->getSource(['cache_counts' => TRUE]);
    $this->assertEquals(1, $source->count());

    // Test the skip argument.
    $source = $this->getSource(['skip_count' => TRUE]);
    $this->assertEquals(MigrateSourceInterface::NOT_COUNTABLE, $source->count());

    $this->migrationConfiguration['id'] = 'test_migration';
    $migration = $this->getMigration();
    $source = new StubSourceGeneratorPlugin([], '', [], $migration);

    // Test the skipCount property's default value.
    $this->assertEquals(MigrateSourceInterface::NOT_COUNTABLE, $source->count());

    // Test the count value using a generator.
    $source = new StubSourceGeneratorPlugin(['skip_count' => FALSE], '', [], $migration);
    $this->assertEquals(3, $source->count());
  }

  /**
   * Tests that the key can be set for the count cache.
   *
   * @covers ::count
   */
  public function testCountCacheKey(): void {
    // Mock the cache to validate set() receives appropriate arguments.
    $container = new ContainerBuilder();
    $cache = $this->createMock(CacheBackendInterface::class);
    $cache->expects($this->any())->method('set')
      ->with('test_key', $this->isType('int'), $this->isType('int'));
    $container->set('cache.migrate', $cache);
    \Drupal::setContainer($container);

    // Test caching the count with a configured key works.
    $source = $this->getSource(['cache_counts' => TRUE, 'cache_key' => 'test_key']);
    $this->assertEquals(1, $source->count());
  }

  /**
   * Tests that we don't get a row if prepareRow() is false.
   */
  public function testPrepareRowFalse(): void {
    $source = $this->getSource([], ['prepare_row_false' => TRUE]);

    $source->rewind();
    $this->assertNull($source->current(), 'No row is available when prepareRow() is false.');
  }

  /**
   * Tests that $row->needsUpdate() works as expected.
   */
  public function testNextNeedsUpdate(): void {
    $source = $this->getSource();

    // $row->needsUpdate() === TRUE so we get a row.
    $source->rewind();
    $this->assertTrue(is_a($source->current(), 'Drupal\migrate\Row'), '$row->needsUpdate() is TRUE so we got a row.');

    // Test that we don't get a row when the incoming row is marked as imported.
    $source = $this->getSource([], [], MigrateIdMapInterface::STATUS_IMPORTED);
    $source->rewind();
    $this->assertNull($source->current(), 'Row was already imported, should be NULL');
  }

  /**
   * Tests that an outdated highwater mark does not cause a row to be imported.
   */
  public function testOutdatedHighwater(): void {
    $configuration = [
      'high_water_property' => [
        'name' => 'timestamp',
      ],
    ];
    $source = $this->getSource($configuration, [], MigrateIdMapInterface::STATUS_IMPORTED, $this->row['timestamp'] + 1);

    // The current highwater mark is now higher than the row timestamp so no row
    // is expected.
    $source->rewind();
    $this->assertNull($source->current(), 'Original highwater mark is higher than incoming row timestamp.');
  }

  /**
   * Tests that a highwater mark newer than our saved one imports a row.
   *
   * @throws \Exception
   */
  public function testNewHighwater(): void {
    $configuration = [
      'high_water_property' => [
        'name' => 'timestamp',
      ],
    ];
    // Set a highwater property field for source. Now we should have a row
    // because the row timestamp is greater than the current highwater mark.
    $source = $this->getSource($configuration, [], MigrateIdMapInterface::STATUS_IMPORTED, $this->row['timestamp'] - 1);

    $source->rewind();
    $this->assertInstanceOf(Row::class, $source->current());
  }

  /**
   * Tests basic row preparation.
   *
   * @covers ::prepareRow
   */
  public function testPrepareRow(): void {
    $this->migrationConfiguration['id'] = 'test_migration';

    // Get a new migration with an id.
    $migration = $this->getMigration();
    $source = new StubSourcePlugin([], '', [], $migration);
    $row = new Row();

    $module_handler = $this->prophesize(ModuleHandlerInterface::class);
    $module_handler->invokeAll('migrate_prepare_row', [$row, $source, $migration])
      ->willReturn([TRUE, TRUE])
      ->shouldBeCalled();
    $module_handler->invokeAll('migrate_' . $migration->id() . '_prepare_row', [$row, $source, $migration])
      ->willReturn([TRUE, TRUE])
      ->shouldBeCalled();
    $source->setModuleHandler($module_handler->reveal());

    // Ensure we don't log this to the mapping table.
    $this->idMap->expects($this->never())
      ->method('saveIdMapping');

    $this->assertTrue($source->prepareRow($row));

    // Track_changes...
    $source = new StubSourcePlugin(['track_changes' => TRUE], '', [], $migration);
    $row2 = $this->prophesize(Row::class);
    $row2->rehash()
      ->shouldBeCalled();
    $module_handler->invokeAll('migrate_prepare_row', [$row2, $source, $migration])
      ->willReturn([TRUE, TRUE])
      ->shouldBeCalled();
    $module_handler->invokeAll('migrate_' . $migration->id() . '_prepare_row', [$row2, $source, $migration])
      ->willReturn([TRUE, TRUE])
      ->shouldBeCalled();
    $source->setModuleHandler($module_handler->reveal());
    $this->assertTrue($source->prepareRow($row2->reveal()));
  }

  /**
   * Tests that global prepare hooks can skip rows.
   *
   * @covers ::prepareRow
   */
  public function testPrepareRowGlobalPrepareSkip(): void {
    $this->migrationConfiguration['id'] = 'test_migration';

    $migration = $this->getMigration();
    $source = new StubSourcePlugin([], '', [], $migration);
    $row = new Row();

    $module_handler = $this->prophesize(ModuleHandlerInterface::class);
    // Return a failure from a prepare row hook.
    $module_handler->invokeAll('migrate_prepare_row', [$row, $source, $migration])
      ->willReturn([TRUE, FALSE, TRUE])
      ->shouldBeCalled();
    $module_handler->invokeAll('migrate_' . $migration->id() . '_prepare_row', [$row, $source, $migration])
      ->willReturn([TRUE, TRUE])
      ->shouldBeCalled();
    $source->setModuleHandler($module_handler->reveal());

    $this->idMap->expects($this->once())
      ->method('saveIdMapping')
      ->with($row, [], MigrateIdMapInterface::STATUS_IGNORED);

    $this->assertFalse($source->prepareRow($row));
  }

  /**
   * Tests that migrate specific prepare hooks can skip rows.
   *
   * @covers ::prepareRow
   */
  public function testPrepareRowMigratePrepareSkip(): void {
    $this->migrationConfiguration['id'] = 'test_migration';

    $migration = $this->getMigration();
    $source = new StubSourcePlugin([], '', [], $migration);
    $row = new Row();

    $module_handler = $this->prophesize(ModuleHandlerInterface::class);
    // Return a failure from a prepare row hook.
    $module_handler->invokeAll('migrate_prepare_row', [$row, $source, $migration])
      ->willReturn([TRUE, TRUE])
      ->shouldBeCalled();
    $module_handler->invokeAll('migrate_' . $migration->id() . '_prepare_row', [$row, $source, $migration])
      ->willReturn([TRUE, FALSE, TRUE])
      ->shouldBeCalled();
    $source->setModuleHandler($module_handler->reveal());

    $this->idMap->expects($this->once())
      ->method('saveIdMapping')
      ->with($row, [], MigrateIdMapInterface::STATUS_IGNORED);

    $this->assertFalse($source->prepareRow($row));
  }

  /**
   * Tests that a skip exception during prepare hooks correctly skips.
   *
   * @covers ::prepareRow
   */
  public function testPrepareRowPrepareException(): void {
    $this->migrationConfiguration['id'] = 'test_migration';

    $migration = $this->getMigration();
    $source = new StubSourcePlugin([], '', [], $migration);
    $row = new Row();

    $module_handler = $this->prophesize(ModuleHandlerInterface::class);
    // Return a failure from a prepare row hook.
    $module_handler->invokeAll('migrate_prepare_row', [$row, $source, $migration])
      ->willReturn([TRUE, TRUE])
      ->shouldBeCalled();
    $module_handler->invokeAll('migrate_' . $migration->id() . '_prepare_row', [$row, $source, $migration])
      ->willThrow(new MigrateSkipRowException())
      ->shouldBeCalled();
    $source->setModuleHandler($module_handler->reveal());

    // This will only be called on the first prepare because the second
    // explicitly avoids it.
    $this->idMap->expects($this->once())
      ->method('saveIdMapping')
      ->with($row, [], MigrateIdMapInterface::STATUS_IGNORED);
    $this->assertFalse($source->prepareRow($row));

    // Throw an exception the second time that avoids mapping.
    $e = new MigrateSkipRowException('', FALSE);
    $module_handler->invokeAll('migrate_' . $migration->id() . '_prepare_row', [$row, $source, $migration])
      ->willThrow($e)
      ->shouldBeCalled();
    $this->assertFalse($source->prepareRow($row));
  }

  /**
   * Tests that default values are preserved for several source methods.
   */
  public function testDefaultPropertiesValues(): void {
    $this->migrationConfiguration['id'] = 'test_migration';
    $migration = $this->getMigration();
    $source = new StubSourceGeneratorPlugin([], '', [], $migration);

    // Test the default value of the skipCount Value.
    $this->assertTrue($source->getSkipCount());
    $this->assertTrue($source->getCacheCounts());
    $this->assertTrue($source->getTrackChanges());
  }

  /**
   * Gets a mock executable for the test.
   *
   * @param \Drupal\migrate\Plugin\MigrationInterface $migration
   *   The migration entity.
   *
   * @return \Drupal\migrate\MigrateExecutable
   *   The migrate executable.
   */
  protected function getMigrateExecutable($migration) {
    /** @var \Drupal\migrate\MigrateMessageInterface $message */
    $message = $this->createMock('Drupal\migrate\MigrateMessageInterface');
    /** @var \Symfony\Contracts\EventDispatcher\EventDispatcherInterface $event_dispatcher */
    $event_dispatcher = $this->createMock('Symfony\Contracts\EventDispatcher\EventDispatcherInterface');
    return new MigrateExecutable($migration, $message, $event_dispatcher);
  }

  /**
   * @covers ::preRollback
   */
  public function testPreRollback(): void {
    $this->migrationConfiguration['id'] = 'test_migration';
    $plugin_id = 'test_migration';
    $migration = $this->getMigration();

    // Verify that preRollback() sets the high water mark to NULL.
    $key_value = $this->createMock(KeyValueStoreInterface::class);
    $key_value->expects($this->once())
      ->method('set')
      ->with($plugin_id, NULL);
    $key_value_factory = $this->createMock(KeyValueFactoryInterface::class);
    $key_value_factory->expects($this->once())
      ->method('get')
      ->with('migrate:high_water')
      ->willReturn($key_value);
    $container = new ContainerBuilder();
    $container->set('keyvalue', $key_value_factory);
    \Drupal::setContainer($container);

    $source = new StubSourceGeneratorPlugin([], $plugin_id, [], $migration);
    $source->preRollback(new MigrateRollbackEvent($migration));
  }

}

/**
 * Defines a stubbed source plugin with a generator as iterator.
 *
 * This stub overwrites the $skipCount, $cacheCounts, and $trackChanges
 * properties.
 */
class StubSourceGeneratorPlugin extends StubSourcePlugin {

  /**
   * {@inheritdoc}
   */
  protected $skipCount = TRUE;

  /**
   * {@inheritdoc}
   */
  protected $cacheCounts = TRUE;

  /**
   * {@inheritdoc}
   */
  protected $trackChanges = TRUE;

  /**
   * Return the skipCount value.
   */
  public function getSkipCount() {
    return $this->skipCount;
  }

  /**
   * Return the cacheCounts value.
   */
  public function getCacheCounts() {
    return $this->cacheCounts;
  }

  /**
   * Return the trackChanges value.
   */
  public function getTrackChanges() {
    return $this->trackChanges;
  }

  /**
   * {@inheritdoc}
   */
  protected function initializeIterator(): \Generator {
    yield 'foo';
    yield 'bar';
    yield 'iggy';
  }

}
