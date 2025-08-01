<?php

declare(strict_types=1);

namespace Drupal\FunctionalTests\Routing;

use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests Default Format.
 */
#[Group('routing')]
class DefaultFormatTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['system', 'default_format_test'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  public function testFoo(): void {
    $this->drupalGet('/default_format_test/human');
    $this->assertSame('format:html', $this->getSession()->getPage()->getContent());
    $this->assertSession()->responseHeaderEquals('X-Drupal-Cache', 'MISS');
    $this->drupalGet('/default_format_test/human');
    $this->assertSame('format:html', $this->getSession()->getPage()->getContent());
    $this->assertSession()->responseHeaderEquals('X-Drupal-Cache', 'HIT');

    $this->drupalGet('/default_format_test/machine');
    $this->assertSame('format:json', $this->getSession()->getPage()->getContent());
    $this->assertSession()->responseHeaderEquals('X-Drupal-Cache', 'MISS');
    $this->drupalGet('/default_format_test/machine');
    $this->assertSame('format:json', $this->getSession()->getPage()->getContent());
    $this->assertSession()->responseHeaderEquals('X-Drupal-Cache', 'HIT');
  }

  public function testMultipleRoutesWithSameSingleFormat(): void {
    $this->drupalGet('/default_format_test/machine');
    $this->assertSame('format:json', $this->getSession()->getPage()->getContent());
  }

}
