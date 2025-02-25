<?php
/**
 * Spotler Transactional plugin for Craft CMS
 *
 * @link      https://perfectwebteam.com
 * @copyright Copyright (c) 2025 Perfect Web Team
 */

namespace perfectwebteam\spotlertransactional\mail;

use Craft;
use perfectwebteam\spotlertransactional\SpotlerTransactional;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\HttpTransportException;
use Symfony\Component\Mailer\Header\MetadataHeader;
use Symfony\Component\Mailer\Header\TagHeader;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractApiTransport;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Exception;

/**
 * Spotler Transactional Transport
 * Based on https://github.com/symfony/mailchimp-mailer
 *
 * @author    Perfect Web Team
 * @package   Spotler Transactional
 * @since     1.0.0
 */
class SpotlerTransactionalTransport extends AbstractApiTransport
{
    private string $apiUrl = '';

    private bool $simulate = true; // Set to false to stop simulation

    private string $accountId;

    private string $key;

    private string $secret;

    private function setApiUrl(string $accountId): void
    {
        $this->apiUrl = 'https://api.flowmailer.net/' . $accountId . '/messages';

        if ($this->simulate) {
            $this->apiUrl .= '/simulate';
        }
    }

    /**
     * @param string $key
     * @param HttpClientInterface|null $client
     * @param EventDispatcherInterface|null $dispatcher
     * @param LoggerInterface|null $logger
     */
    public function __construct(string $accountId, string $key, string $secret, HttpClientInterface $client = null, EventDispatcherInterface $dispatcher = null, LoggerInterface $logger = null)
    {
        $this->accountId = $accountId;
        $this->key = $key;
        $this->secret = $secret;

        $this->setApiUrl($accountId);

        parent::__construct($client, $dispatcher, $logger);
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        return $this->apiUrl;
    }

    /**
     * @param SentMessage $sentMessage
     * @param Email $email
     * @param Envelope $envelope
     * @return ResponseInterface
     * @throws TransportExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     */
    protected function doSendApi(SentMessage $sentMessage, Email $email, Envelope $envelope): ResponseInterface
    {
        $getAccessToken = $this->getAccessToken();
        try {
            $statusCode = $response->getStatusCode();
            $result = $response->toArray(false);
        } catch (DecodingExceptionInterface $e) {
            throw new HttpTransportException('Unable to send an email: ' . $response->getContent(false) . sprintf(' (code %d).', $statusCode), $response);
        } catch (TransportExceptionInterface $e) {
            throw new HttpTransportException('Could not reach the remote Mandrill server.', $response, 0, $e);
        }

        if (200 !== $statusCode) {
            if ('error' === ($result['status'] ?? false)) {
                throw new HttpTransportException('Unable to send an email: ' . $result['message'] . sprintf(' (code %d).', $result['code']), $response);
            }

            throw new HttpTransportException(sprintf('Unable to send an email (code %d).', $result['code']), $response);
        }

        $firstRecipient = reset($result);
        $sentMessage->setMessageId($firstRecipient['_id']);

        return $response;
    }

    private function getAccessToken(): string
    {
        $options = [
            'http' => [
                'ignore_errors' => true,
                'method' => 'POST',
                'header' => [
                    'Accept: application/vnd.flowmailer.v1.12+json',
                    'Content-Type: application/x-www-form-urlencoded',
                ],
                'content' => http_build_query([
                        'client_id' => $this->key,
                        'client_secret' => $this->secret,
                        'grant_type' => 'client_credentials',
                        'scope' => 'api',
                    ]),
            ],
        ];
        
        $context  = stream_context_create($options);
        $response = file_get_contents(
            'https://login.flowmailer.net/oauth/token',
            false,
            $context
        );
        $response   = json_decode($response);
        $statuscode = (int) substr($http_response_header[0], 9, 3);

        if ($statuscode !== 200) {
            throw new Exception('Could not authorize at Spotler. Error: ' . $response->error_description);
        }

        if (!isset($response->token_type) || $response->token_type !== 'bearer') {
            throw new Exception('Could not retrieve bearer token.');
        }

        if ($response->expires_in <= 0) {
            throw new Exception('Access token has expired.');
        }
        
        return $response->access_token;
    }

    /**
     * @param Email $email
     * @param Envelope $envelope
     * @return array
     */
    private function getPayload(Email $email, Envelope $envelope): array
    {
        $payload = [
            'messageType' => 'EMAIL',
            'html' => $email->getHtmlBody(),
            'text' => $email->getTextBody(),
            'subject' => $email->getSubject(),
            'headerFromName' => ('' !== $envelope->getSender()->getName() ? $envelope->getSender()->getName() : ''),
            'headerFromAddress' => $envelope->getSender()->getAddress(),
            'headerToAddress' => $this->getRecipients($email, $envelope),
        ];

        return $payload;
    }

    /**
     * @param Email $email
     * @param Envelope $envelope
     * @return array
     */
    protected function getRecipients(Email $email, Envelope $envelope): array
    {
        $recipients = [];

        foreach ($envelope->getRecipients() as $recipient) {
            $type = 'to';

            if (\in_array($recipient, $email->getBcc(), true)) {
                $type = 'bcc';
            } elseif (\in_array($recipient, $email->getCc(), true)) {
                $type = 'cc';
            }

            $recipientPayload = [
                'email' => $recipient->getAddress(),
                'type' => $type,
            ];

            if ('' !== $recipient->getName()) {
                $recipientPayload['name'] = $recipient->getName();
            }

            $recipients[] = $recipientPayload;
        }

        return $recipients;
    }
}