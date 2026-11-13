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

namespace Waldhacker\Hcaptcha\Validation;

use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use TYPO3\CMS\Core\EventDispatcher\EventDispatcher;
use TYPO3\CMS\Core\Http\RequestFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use TYPO3\CMS\Extbase\Validation\Validator\AbstractValidator;
use Waldhacker\Hcaptcha\Event\TranslateErrorMessageEvent;
use Waldhacker\Hcaptcha\Service\ConfigurationService;

class HcaptchaValidator extends AbstractValidator implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * siteverify error codes that point to a wrong configuration rather than to the visitor.
     * They fail every submission, so they are logged; visitor errors stay silent.
     */
    private const CONFIGURATION_ERROR_CODES = [
        'missing-input-secret',
        'invalid-input-secret',
        'sitekey-secret-mismatch',
        'not-using-dummy-passcode',
    ];

    protected $acceptsEmptyValues = false;

    /**
     * @var ConfigurationService|null
     */
    private $configurationService;

    /**
     * @var RequestFactory|null
     */
    private $requestFactory;

    /**
     * Validate the captcha value from the request and add an error if not valid
     *
     * @param mixed $value The value
     */
    protected function isValid($value): void
    {
        $response = $this->validateHcaptcha();

        if (empty($response) || (bool)($response['success'] ?? false) === false) {
            if (empty($response['error-codes'])) {
                $this->addError(
                    $this->translateErrorMessage(
                        'error_hcaptcha_generic',
                        'hcaptcha'
                    ),
                    1637268462
                );
            } else {
                foreach ($response['error-codes'] as $errorCode) {
                    $this->addError(
                        $this->translateErrorMessage(
                            'error_hcaptcha_' . $errorCode,
                            'hcaptcha'
                        ),
                        1566209403
                    );
                }
            }
        }
    }

    /**
     * @return array
     */
    private function validateHcaptcha(): array
    {
        /** @var ServerRequestInterface $request */
        $request = $GLOBALS['TYPO3_REQUEST'];
        /** @var array $parsedBody */
        $parsedBody = $request->getParsedBody();
        $hcaptchaFormFieldValue = $parsedBody['h-captcha-response'] ?? null;
        if ($hcaptchaFormFieldValue === null) {
            return ['success' => false, 'error-codes' => ['invalid-post-form']];
        }

        $ip = '';
        $normalizedParams = $request->getAttribute('normalizedParams');
        if ($normalizedParams) {
            $ip = $normalizedParams->getRemoteAddress();
        }

        // Send the secret in the request body, never in the URL: request URLs end up in exception
        // messages, proxy logs and monitoring, which would expose the secret.
        try {
            $response = $this->getRequestFactory()->request(
                $this->getConfigurationService()->getVerificationServer(),
                'POST',
                [
                    'form_params' => [
                        'secret' => $this->getConfigurationService()->getPrivateKey(),
                        'response' => $hcaptchaFormFieldValue,
                        'remoteip' => $ip,
                    ],
                ]
            );
        } catch (GuzzleException $exception) {
            // A failed request must not break the form; it fails the validation instead. Log neither
            // the exception message nor the request: both carry request details.
            if ($this->logger !== null) {
                $failedResponse = $exception instanceof RequestException ? $exception->getResponse() : null;
                $this->logger->error(
                    'hCaptcha siteverify request failed',
                    [
                        'exceptionClass' => get_class($exception),
                        'code' => $exception->getCode(),
                        'status' => $failedResponse !== null ? $failedResponse->getStatusCode() : null,
                    ]
                );
            }
            return [];
        }

        $body = (string)$response->getBody();
        $responseArray = json_decode($body, true);
        if (!is_array($responseArray)) {
            return [];
        }
        $this->logConfigurationErrors($responseArray['error-codes'] ?? []);
        return $responseArray;
    }

    /**
     * @param mixed $errorCodes
     */
    private function logConfigurationErrors($errorCodes): void
    {
        if ($this->logger === null || !is_array($errorCodes)) {
            return;
        }
        $configurationErrors = array_values(array_intersect($errorCodes, self::CONFIGURATION_ERROR_CODES));
        if ($configurationErrors !== []) {
            $this->logger->error(
                'hCaptcha siteverify rejected the configuration, check site key and secret',
                ['error-codes' => $configurationErrors]
            );
        }
    }

    /**
     * @codeCoverageIgnore
     */
    protected function translateErrorMessage(string $translateKey, string $extensionName = '', array $arguments = []): string
    {
        $event = new TranslateErrorMessageEvent($translateKey);
        GeneralUtility::makeInstance(EventDispatcher::class)->dispatch($event);

        $message = $event->getMessage();
        if (!empty($message)) {
            return $message;
        }

        return LocalizationUtility::translate(
            $translateKey,
            $extensionName,
            $arguments
        ) ?? 'Validating the captcha failed.';
    }

    private function getConfigurationService(): ConfigurationService
    {
        if (!($this->configurationService instanceof ConfigurationService)) {
            /** @var ConfigurationService $configurationService */
            $configurationService = GeneralUtility::makeInstance(ConfigurationService::class);
            $this->configurationService = $configurationService;
        }
        return $this->configurationService;
    }

    private function getRequestFactory(): RequestFactory
    {
        if (!($this->requestFactory instanceof RequestFactory)) {
            /** @var RequestFactory $requestFactory */
            $requestFactory = GeneralUtility::makeInstance(RequestFactory::class);
            $this->requestFactory = $requestFactory;
        }
        return $this->requestFactory;
    }
}
