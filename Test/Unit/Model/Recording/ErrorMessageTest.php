<?php
/**
 * Copyright (c) 2026. Volodymyr Hryvinskyi. All rights reserved.
 * Author: Volodymyr Hryvinskyi <volodymyr@hryvinskyi.com>
 * GitHub: https://github.com/hryvinskyi
 */

declare(strict_types=1);

namespace Hryvinskyi\ApiLogger\Test\Unit\Model\Recording;

use Hryvinskyi\ApiLogger\Model\Recording\ErrorMessage;
use Hryvinskyi\ApiLogger\Test\Unit\Fixture\ActivityLogSanitizer;
use PHPUnit\Framework\TestCase;

/**
 * Magento's REST error body rendered into the text a person reads
 */
class ErrorMessageTest extends TestCase
{
    use ActivityLogSanitizer;

    /**
     * @var ErrorMessage
     */
    private ErrorMessage $errorMessage;

    protected function setUp(): void
    {
        $this->errorMessage = new ErrorMessage($this->activityLogSanitizer());
    }

    public function testNamedParametersReplaceTheirPlaceholders(): void
    {
        self::assertSame(
            "The consumer isn't authorized to access Magento_Catalog::products.",
            $this->errorMessage->fromBody(self::body(
                "The consumer isn't authorized to access %resources.",
                ['resources' => 'Magento_Catalog::products']
            ))
        );
    }

    public function testListedParametersReplacePlaceholdersNumberedFromOne(): void
    {
        self::assertSame(
            'The "sku" value 24-MB01 is not valid.',
            $this->errorMessage->fromBody(self::body('The "%1" value %2 is not valid.', ['sku', '24-MB01']))
        );
    }

    public function testAPlaceholderWithoutAParameterStaysInTheText(): void
    {
        self::assertSame(
            'No such entity with sku = %fieldValue',
            $this->errorMessage->fromBody(self::body('No such entity with %fieldName = %fieldValue', [
                'fieldName' => 'sku',
            ]))
        );
    }

    public function testANonScalarParameterLeavesItsPlaceholder(): void
    {
        self::assertSame(
            'Value %value for %field, %count times, empty "%empty"',
            $this->errorMessage->fromBody(self::body('Value %value for %field, %count times, empty "%empty"', [
                'value' => ['a', 'b'],
                'field' => ['nested' => 'object'],
                'count' => null,
                'empty' => null,
            ]))
        );
    }

    public function testScalarParametersOfEveryKindAreWrittenAsText(): void
    {
        self::assertSame(
            'Qty 3, price 9.5, active 1, deleted .',
            $this->errorMessage->fromBody(self::body('Qty %qty, price %price, active %active, deleted %deleted.', [
                'qty' => 3,
                'price' => 9.5,
                'active' => true,
                'deleted' => false,
            ]))
        );
    }

    public function testAMessageWithoutParametersIsReturnedAsItIs(): void
    {
        self::assertSame(
            'Request does not match any route.',
            $this->errorMessage->fromBody('{"message":"Request does not match any route."}')
        );
        self::assertSame(
            'Keeps %1 when parameters are not a list',
            $this->errorMessage->fromBody('{"message":"Keeps %1 when parameters are not a list","parameters":"x"}')
        );
    }

    public function testATenthListedParameterIsNotReadAsTheFirstFollowedByZero(): void
    {
        $values = ['one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'ten'];

        self::assertSame(
            'first one, tenth ten',
            $this->errorMessage->fromBody(self::body('first %1, tenth %10', $values))
        );
    }

    public function testTheLongerOfTwoOverlappingNamesWins(): void
    {
        self::assertSame(
            'One Magento_Catalog::products of Magento_Catalog::products, Magento_Sales::sales',
            $this->errorMessage->fromBody(self::body('One %resource of %resources', [
                'resource' => 'Magento_Catalog::products',
                'resources' => 'Magento_Catalog::products, Magento_Sales::sales',
            ]))
        );
    }

    public function testAParameterNamedAsASecretKeepsItsPlaceholder(): void
    {
        self::assertSame(
            'Token %token of jane refused, password %new_password short',
            $this->errorMessage->fromBody(self::body(
                'Token %token of %customer refused, password %new_password short',
                [
                    'token' => 'k9x2plq7rvzt3mw8',
                    'customer' => 'jane',
                    'new_password' => 'Plain-Text-Pa55',
                ]
            ))
        );
    }

    public function testTheValueOfAFieldNamedAsASecretKeepsItsPlaceholder(): void
    {
        self::assertSame(
            'Invalid value of "%value" provided for the password field.',
            $this->errorMessage->fromBody(self::body('Invalid value of "%value" provided for the %fieldName field.', [
                'fieldName' => 'password',
                'value' => 'Plain-Text-Pa55',
            ]))
        );
        self::assertSame(
            'No such entity with rp_token = %fieldValue',
            $this->errorMessage->fromBody(self::body('No such entity with %fieldName = %fieldValue', [
                'fieldName' => 'rp_token',
                'fieldValue' => 'k9x2plq7rvzt3mw8',
            ]))
        );
    }

    public function testTheValueOfAFieldThatIsNoSecretIsFilledIn(): void
    {
        self::assertSame(
            'Invalid value of "abc" provided for the qty field.',
            $this->errorMessage->fromBody(self::body('Invalid value of "%value" provided for the %fieldName field.', [
                'fieldName' => 'qty',
                'value' => 'abc',
            ]))
        );
    }

    public function testABodyThatIsNotJsonHasNoMessage(): void
    {
        self::assertNull($this->errorMessage->fromBody('<html>Service Unavailable</html>'));
        self::assertNull($this->errorMessage->fromBody('"just a string"'));
    }

    public function testAnEmptyOrMissingMessageIsNoMessage(): void
    {
        self::assertNull($this->errorMessage->fromBody('{"message":"","parameters":{"a":"b"}}'));
        self::assertNull($this->errorMessage->fromBody('{"parameters":{"a":"b"}}'));
        self::assertNull($this->errorMessage->fromBody('{"message":42}'));
    }

    public function testANullOrEmptyBodyHasNoMessage(): void
    {
        self::assertNull($this->errorMessage->fromBody(null));
        self::assertNull($this->errorMessage->fromBody(''));
    }

    /**
     * A Magento REST error body
     *
     * @param string $message
     * @param array<int|string, mixed> $parameters
     * @return string
     */
    private static function body(string $message, array $parameters): string
    {
        return (string) json_encode(['message' => $message, 'parameters' => $parameters]);
    }
}
