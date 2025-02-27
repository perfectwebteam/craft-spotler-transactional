<?php

/**
 * Spotler Transactional plugin for Craft CMS
 *
 * @link      https://perfectwebteam.com
 * @copyright Copyright (c) 2025 Perfect Web Team
 */

namespace perfectwebteam\spotlertransactional\mail;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractApiTransport;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Exception;
use Flowmailer\API\Enum\MessageType;
use Flowmailer\API\Exception\ApiException;
use Flowmailer\API\Flowmailer;
use Flowmailer\API\Model\SubmitMessage;
use perfectwebteam\spotlertransactional\models\CustomResponse;

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
    private string $accountId;

    private string $key;

    private string $secret;

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

        parent::__construct($client, $dispatcher, $logger);
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        return '';
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
        $payload = $this->getPayload($email, $envelope);
        $sendMailToRecipient = function ($flowmailer, $subject, $recipientAddress, $senderAddress, $html, $text) {
            $submitMessage = (new SubmitMessage())
                ->setMessageType(MessageType::EMAIL)
                ->setSubject($subject)
                ->setRecipientAddress($recipientAddress)
                ->setSenderAddress($senderAddress)
                ->setHtml($html)
                ->setText($text);

            try {
                $result = $flowmailer->submitMessage($submitMessage);
            } catch (ApiException $exception) {
                throw new Exception('Could not send mail due to: ' . $exception->getErrors());
            }

            return $result->getResponseBody(); // Returns e.g. 20250101...
        };

        $allRecipientsAddresses = [];
        foreach (['to', 'cc', 'bcc'] as $type) {
            $allRecipientsAddresses = array_merge($allRecipientsAddresses, array_column($payload['recipients'][$type], 'address'));
        }

        $flowmailer = Flowmailer::init($this->accountId, $this->key, $this->secret);

        $errorDuringSending = false;

        foreach ($allRecipientsAddresses as $recipientAddress) {
            $response = $sendMailToRecipient($flowmailer, $payload['subject'], $recipientAddress, $payload['senderAddress'], $payload['html'], $payload['text']);
            if (!$errorDuringSending && !is_string($response)) {
                $errorDuringSending = true;
            }
        }

        if ($errorDuringSending) {
            if (count($allRecipientsAddresses) > 1) {
                $message = 'One or multiple emails failed to send.';
            } else {
                $message = 'Could not send email.';
            }
            
            throw new Exception($message);
        }

        return new CustomResponse(200, ['content-type' => ['application/json']], '{}');
    }

    /**
     * @param Email $email
     * @param Envelope $envelope
     * @return array
     */
    private function getPayload(Email $email, Envelope $envelope): array
    {
        $recipients = $this->getRecipients($email, $envelope);
        $payload = [
            'html' => $email->getHtmlBody(),
            'text' => $email->getTextBody(),
            'subject' => $email->getSubject(),
            'senderAddress' => $envelope->getSender()->getAddress(),
            'recipients' => $recipients
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
        $recipients = [
            'to' => [],
            'cc' => [],
            'bcc' => [],
        ];

        foreach ($envelope->getRecipients() as $recipient) {
            $type = 'to';

            if (\in_array($recipient, $email->getBcc(), true)) {
                $type = 'bcc';
            } elseif (\in_array($recipient, $email->getCc(), true)) {
                $type = 'cc';
            }

            $recipients[$type][] = [
                'address' => $recipient->getAddress(),
                'name' => $recipient->getName(),
            ];
        }

        return $recipients;
    }
}