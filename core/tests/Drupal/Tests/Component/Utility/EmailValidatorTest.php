<?php

declare(strict_types=1);

namespace Drupal\Tests\Component\Utility;

use Drupal\Component\Utility\EmailValidator;
use Egulias\EmailValidator\Validation\RFCValidation;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the EmailValidator utility class.
 */
#[CoversClass(EmailValidator::class)]
#[Group('Utility')]
class EmailValidatorTest extends TestCase {

  /**
   * @legacy-covers ::isValid
   */
  public function testIsValid(): void {
    // Note that \Drupal\Component\Utility\EmailValidator wraps
    // \Egulias\EmailValidator\EmailValidator so we don't do anything more than
    // test that the wrapping works since the dependency has its own test
    // coverage.
    $validator = new EmailValidator();
    $this->assertTrue($validator->isValid('example@example.com'));
    $this->assertFalse($validator->isValid('example@example.com@'));
    $this->assertFalse($validator->isValid('example@example .com'));
  }

  /**
   * @legacy-covers ::isValid
   */
  public function testIsValidException(): void {
    $validator = new EmailValidator();
    $this->expectException(\BadMethodCallException::class);
    $this->expectExceptionMessage('Calling \Drupal\Component\Utility\EmailValidator::isValid() with the second argument is not supported. See https://www.drupal.org/node/2997196');
    $validator->isValid('example@example.com', (new RFCValidation()));
  }

}
