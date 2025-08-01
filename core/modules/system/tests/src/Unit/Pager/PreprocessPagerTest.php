<?php

declare(strict_types=1);

namespace Drupal\Tests\system\Unit\Pager;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Pager\PagerPreprocess;
use Drupal\Core\Template\AttributeString;
use Drupal\Tests\UnitTestCase;

/**
 * Tests pager preprocessing.
 *
 * @group system
 *
 * @coversDefaultClass \Drupal\Core\Pager\PagerPreprocess
 */
class PreprocessPagerTest extends UnitTestCase {

  /**
   * Pager preprocess instance.
   */
  protected PagerPreprocess $pagerPreprocess;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $pager_manager = $this->getMockBuilder('Drupal\Core\Pager\PagerManager')
      ->disableOriginalConstructor()
      ->getMock();
    $pager = $this->getMockBuilder('Drupal\Core\Pager\Pager')
      ->disableOriginalConstructor()
      ->getMock();
    $url_generator = $this->getMockBuilder('Drupal\Core\Routing\UrlGenerator')
      ->disableOriginalConstructor()
      ->getMock();

    $pager->method('getTotalPages')->willReturn(2);
    $pager->method('getCurrentPage')->willReturn(1);

    $url_generator->method('generateFromRoute')->willReturn('');

    $pager_manager->method('getPager')->willReturn($pager);
    $pager_manager->method('getUpdatedParameters')->willReturn('');

    $this->pagerPreprocess = new PagerPreprocess($pager_manager);

    $container = new ContainerBuilder();
    $container->set('url_generator', $url_generator);
    \Drupal::setContainer($container);
  }

  /**
   * Tests when an empty #quantity is passed.
   *
   * @covers ::preprocessPager
   */
  public function testQuantityNotSet(): void {
    $variables = [
      'pager' => [
        '#element' => '',
        '#parameters' => [],
        '#quantity' => '',
        '#route_name' => '',
        '#tags' => '',
      ],
    ];
    $this->pagerPreprocess->preprocessPager($variables);

    $this->assertEquals(['first', 'previous'], array_keys($variables['items']));
  }

  /**
   * Tests when a #quantity value is passed.
   *
   * @covers ::preprocessPager
   */
  public function testQuantitySet(): void {
    $variables = [
      'pager' => [
        '#element' => '2',
        '#parameters' => [],
        '#quantity' => '2',
        '#route_name' => '',
        '#tags' => '',
      ],
    ];
    $this->pagerPreprocess->preprocessPager($variables);

    $this->assertEquals(['first', 'previous', 'pages'], array_keys($variables['items']));
    /** @var \Drupal\Core\Template\AttributeString $attribute */
    $attribute = $variables['items']['pages']['2']['attributes']->offsetGet('aria-current');
    $this->assertInstanceOf(AttributeString::class, $attribute);
    $this->assertEquals('page', $attribute->value());
  }

  /**
   * Tests when an empty #pagination_heading_level value is passed.
   *
   * @covers ::preprocessPager
   */
  public function testEmptyPaginationHeadingLevelSet(): void {
    $variables = [
      'pager' => [
        '#element' => '2',
        '#pagination_heading_level' => '',
        '#parameters' => [],
        '#quantity' => '2',
        '#route_name' => '',
        '#tags' => '',
      ],
    ];
    $this->pagerPreprocess->preprocessPager($variables);

    $this->assertEquals('h4', $variables['pagination_heading_level']);
  }

  /**
   * Tests when no #pagination_heading_level is passed.
   *
   * @covers ::preprocessPager
   */
  public function testPaginationHeadingLevelNotSet(): void {
    $variables = [
      'pager' => [
        '#element' => '',
        '#parameters' => [],
        '#quantity' => '',
        '#route_name' => '',
        '#tags' => '',
      ],
    ];
    $this->pagerPreprocess->preprocessPager($variables);

    $this->assertEquals('h4', $variables['pagination_heading_level']);
  }

  /**
   * Tests when a #pagination_heading_level value is passed.
   *
   * @covers ::preprocessPager
   */
  public function testPaginationHeadingLevelSet(): void {
    $variables = [
      'pager' => [
        '#element' => '2',
        '#pagination_heading_level' => 'h5',
        '#parameters' => [],
        '#quantity' => '2',
        '#route_name' => '',
        '#tags' => '',
      ],
    ];
    $this->pagerPreprocess->preprocessPager($variables);

    $this->assertEquals('h5', $variables['pagination_heading_level']);
  }

  /**
   * Test with an invalid #pagination_heading_level.
   *
   * @covers ::preprocessPager
   */
  public function testPaginationHeadingLevelInvalid(): void {
    $variables = [
      'pager' => [
        '#element' => '2',
        '#pagination_heading_level' => 'not-a-heading-element',
        '#parameters' => [],
        '#quantity' => '2',
        '#route_name' => '',
        '#tags' => '',
      ],
    ];
    $this->pagerPreprocess->preprocessPager($variables);

    $this->assertEquals('h4', $variables['pagination_heading_level']);
  }

}
