<?php

declare(strict_types=1);

/*
 * This file is part of the hcaptcha extension for TYPO3
 * - (c) 2021 waldhacker UG (haftungsbeschränkt)
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

namespace Waldhacker\Hcaptcha\Tests\Unit\Validation;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\Http\RequestFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Waldhacker\Hcaptcha\Service\ConfigurationService;
use Waldhacker\Hcaptcha\Validation\HcaptchaValidator;

/**
 * @backupGlobals enabled
 * @coversDefaultClass \Waldhacker\Hcaptcha\Validation\HcaptchaValidator
 */
class HcaptchaValidatorTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @var ServerRequestInterface|ObjectProphecy
     */
    private $typo3request;

    protected function setUp(): void
    {
        parent::setUp();
        $this->typo3request = $this->prophesize(ServerRequestInterface::class);
        $GLOBALS['TYPO3_REQUEST'] = $this->typo3request->reveal();
    }

    protected function tearDown(): void
    {
        GeneralUtility::purgeInstances();
        parent::tearDown();
    }

    /**
     * @test
     * @covers ::validate
     * @covers ::isValid
     * @covers ::validateHcaptcha
     */
    public function validateReturnsErrorIfPostResponseFieldIsEmpty(): void
    {
        $subject = $this->getMockBuilder(HcaptchaValidator::class)
            ->onlyMethods(['translateErrorMessage'])
            ->getMock();

        $result = $subject->validate(1);
        $errors = $result->getErrors();

        self::assertCount(1, $errors);
        self::assertSame(1566209403, $errors[0]->getCode());
    }

    public static function validateReturnsErrorIfVerificationRequestReturnsErrorDataProvider(): \Generator
    {
        yield 'Unsuccessful response with error codes' => [
            'responseData' => [
                'success' => false,
                'error-codes' => ['invalid-input-secret'],
            ],
            'expectedErrorCode' => 1566209403,
        ];

        yield 'Unsuccessful response with empty error codes' => [
            'responseData' => [
                'success' => false,
                'error-codes' => [],
            ],
            'expectedErrorCode' => 1637268462,
        ];

        yield 'Unsuccessful response with missing error codes' => [
            'responseData' => [
                'success' => false,
            ],
            'expectedErrorCode' => 1637268462,
        ];

        yield 'Empty response' => [
            'responseData' => [],
            'expectedErrorCode' => 1637268462,
        ];
    }

    /**
     * @test
     * @dataProvider validateReturnsErrorIfVerificationRequestReturnsErrorDataProvider
     * @covers ::validate
     * @covers ::isValid
     * @covers ::validateHcaptcha
     * @covers ::getConfigurationService
     * @covers ::getRequestFactory
     */
    public function validateReturnsErrorIfVerificationRequestReturnsError(
        array $responseData,
        int $expectedErrorCode
    ): void {
        $subject = $this->getMockBuilder(HcaptchaValidator::class)
            ->onlyMethods(['translateErrorMessage'])
            ->getMock();

        $requestFactory = $this->prophesize(RequestFactory::class);
        GeneralUtility::addInstance(RequestFactory::class, $requestFactory->reveal());
        $normalizedParams = $this->prophesize(NormalizedParams::class);
        $configurationService = $this->prophesize(ConfigurationService::class);
        GeneralUtility::addInstance(ConfigurationService::class, $configurationService->reveal());
        $this->typo3request->getAttribute('normalizedParams')->willReturn($normalizedParams->reveal());

        $normalizedParams->getRemoteAddress()->willReturn('127.0.0.1');
        $this->typo3request->getParsedBody()->willReturn([
            'h-captcha-response' => 'verification-key-response',
        ]);

        $configurationService->getVerificationServer()->willReturn('https://example.com/siteverify');
        $configurationService->getPrivateKey()->willReturn('my_superb_key');

        $requestFactory->request(Argument::cetera())->willReturn(
            new Response(200, [], json_encode($responseData))
        );

        $result = $subject->validate(1);
        $errors = $result->getErrors();

        $requestFactory->request(
            'https://example.com/siteverify',
            'POST',
            [
                'form_params' => [
                    'secret' => 'my_superb_key',
                    'response' => 'verification-key-response',
                    'remoteip' => '127.0.0.1',
                ],
            ]
        )->shouldHaveBeenCalled();
        self::assertCount(1, $errors);
        self::assertSame($expectedErrorCode, $errors[0]->getCode());
    }

    /**
     * @test
     * @covers ::validate
     * @covers ::isValid
     * @covers ::validateHcaptcha
     * @covers ::getConfigurationService
     * @covers ::getRequestFactory
     */
    public function validateReturnsErrorAndLogsWithoutSecretIfVerificationRequestFails(): void
    {
        $subject = $this->getMockBuilder(HcaptchaValidator::class)
            ->onlyMethods(['translateErrorMessage'])
            ->getMock();
        $logger = $this->prophesize(LoggerInterface::class);
        $subject->setLogger($logger->reveal());

        $requestFactory = $this->prophesize(RequestFactory::class);
        GeneralUtility::addInstance(RequestFactory::class, $requestFactory->reveal());
        $normalizedParams = $this->prophesize(NormalizedParams::class);
        $configurationService = $this->prophesize(ConfigurationService::class);
        GeneralUtility::addInstance(ConfigurationService::class, $configurationService->reveal());
        $this->typo3request->getAttribute('normalizedParams')->willReturn($normalizedParams->reveal());

        $normalizedParams->getRemoteAddress()->willReturn('127.0.0.1');
        $this->typo3request->getParsedBody()->willReturn([
            'h-captcha-response' => 'verification-key-response',
        ]);

        $configurationService->getVerificationServer()->willReturn('https://example.com/siteverify');
        $configurationService->getPrivateKey()->willReturn('my_superb_key');

        $requestFactory->request(Argument::cetera())->willThrow(
            new ConnectException(
                'cURL error 6: Could not resolve host for https://example.com/siteverify?secret=my_superb_key',
                new Request('POST', 'https://example.com/siteverify?secret=my_superb_key')
            )
        );

        $result = $subject->validate(1);
        $errors = $result->getErrors();

        self::assertCount(1, $errors);
        self::assertSame(1637268462, $errors[0]->getCode());
        $logger->error(
            'hCaptcha siteverify request failed',
            [
                'exceptionClass' => ConnectException::class,
                'code' => 0,
                'status' => null,
            ]
        )->shouldHaveBeenCalledOnce();
    }

    public static function validateLogsOnlyConfigurationErrorsDataProvider(): \Generator
    {
        yield 'Configuration error is logged' => [
            'errorCodes' => ['sitekey-secret-mismatch'],
            'expectedLoggedCodes' => ['sitekey-secret-mismatch'],
        ];

        yield 'Only the configuration errors of a mixed answer are logged' => [
            'errorCodes' => ['invalid-input-response', 'invalid-input-secret'],
            'expectedLoggedCodes' => ['invalid-input-secret'],
        ];

        yield 'Visitor error is not logged' => [
            'errorCodes' => ['invalid-input-response'],
            'expectedLoggedCodes' => null,
        ];
    }

    /**
     * @test
     * @dataProvider validateLogsOnlyConfigurationErrorsDataProvider
     * @covers ::validate
     * @covers ::isValid
     * @covers ::validateHcaptcha
     * @covers ::logConfigurationErrors
     * @covers ::getConfigurationService
     * @covers ::getRequestFactory
     */
    public function validateLogsOnlyConfigurationErrors(array $errorCodes, ?array $expectedLoggedCodes): void
    {
        $subject = $this->getMockBuilder(HcaptchaValidator::class)
            ->onlyMethods(['translateErrorMessage'])
            ->getMock();
        $logger = $this->prophesize(LoggerInterface::class);
        $subject->setLogger($logger->reveal());

        $requestFactory = $this->prophesize(RequestFactory::class);
        GeneralUtility::addInstance(RequestFactory::class, $requestFactory->reveal());
        $normalizedParams = $this->prophesize(NormalizedParams::class);
        $configurationService = $this->prophesize(ConfigurationService::class);
        GeneralUtility::addInstance(ConfigurationService::class, $configurationService->reveal());
        $this->typo3request->getAttribute('normalizedParams')->willReturn($normalizedParams->reveal());

        $normalizedParams->getRemoteAddress()->willReturn('127.0.0.1');
        $this->typo3request->getParsedBody()->willReturn([
            'h-captcha-response' => 'verification-key-response',
        ]);

        $configurationService->getVerificationServer()->willReturn('https://example.com/siteverify');
        $configurationService->getPrivateKey()->willReturn('my_superb_key');

        $requestFactory->request(Argument::cetera())->willReturn(
            new Response(200, [], json_encode(['success' => false, 'error-codes' => $errorCodes]))
        );

        $subject->validate(1);

        if ($expectedLoggedCodes === null) {
            $logger->error(Argument::cetera())->shouldNotHaveBeenCalled();
        } else {
            $logger->error(
                'hCaptcha siteverify rejected the configuration, check site key and secret',
                ['error-codes' => $expectedLoggedCodes]
            )->shouldHaveBeenCalledOnce();
        }
    }
}
