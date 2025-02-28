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
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Exception;
use Flowmailer\API\Enum\MessageType;
use Flowmailer\API\Flowmailer;
use Flowmailer\API\Model\SubmitMessage;
use perfectwebteam\spotlertransactional\models\CustomResponse;
use Flowmailer\API\Collection\HeaderCollection;
use Flowmailer\API\Model\Header;

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

    private string $clientKey;

    private string $clientSecret;

    /**
     * @param string $accountId
     * @param string $clientKey
     * @param string $clientSecret
     * @param HttpClientInterface|null $client
     * @param EventDispatcherInterface|null $dispatcher
     * @param LoggerInterface|null $logger
     */
    public function __construct(string $accountId, string $clientKey, string $clientSecret, HttpClientInterface $client = null, EventDispatcherInterface $dispatcher = null, LoggerInterface $logger = null)
    {
        $this->accountId = $accountId;
        $this->clientKey = $clientKey;
        $this->clientSecret = $clientSecret;

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
     * @param Flowmailer $flowmailer
     * @param string $subject
     * @param string $to
     * @param string $from
     * @param string $htmlBody
     * @param string $textBody
     * @param HeaderCollection $headers
     * @return string e.g. "20250101..." (Message ID)
     */
    private function flowmailerSendMail(Flowmailer $flowmailer, string $subject, string $to, string $from, string $htmlBody, string $textBody, HeaderCollection $headers): bool
    {
        $submitMessage = (new SubmitMessage())
            ->setMessageType(MessageType::EMAIL)
            ->setSubject($subject)
            ->setSenderAddress($from)
            ->setRecipientAddress($to)
            ->setHtml($htmlBody)
            ->setText($textBody)
            ->setHeaders($headers);

        try {
            $result = $flowmailer->submitMessage($submitMessage);
        } catch (Exception $exception) {
            throw new Exception('Could not send mail due to: ' . $exception->getMessage());
        }

        $responseBody = $result->getResponseBody();

        if (!is_string($responseBody)) {
            throw new Exception('Could not send email as response did not return Message ID.');
        }

        return true;
    }

    /**
     * @param SentMessage $sentMessage
     * @param Email $email
     * @param Envelope $envelope
     * @return ResponseInterface
     */
    protected function doSendApi(SentMessage $sentMessage, Email $email, Envelope $envelope): ResponseInterface
    {
        $flowmailer = Flowmailer::init($this->accountId, $this->clientKey, $this->clientSecret);
        $payload = $this->getPayload($email, $envelope);

        $this->flowmailerSendMail($flowmailer, $payload['subject'], $payload['to'], $payload['from'], $payload['html'], $payload['text'], $payload['allHeaders']);

        // Return success
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
        $allHeaders = $this->getAllHeaders($email, $envelope, $recipients);

        $to = '';
        foreach ($allHeaders as $header) {
            if ($header->getName() !== 'To') continue;

            $to = $header->getValue();
            break;
        }

        if (empty($to)) {
            throw new Exception('Could not find "To:" in headers.');
        }

        $payload = [
            'subject' => $email->getSubject(),
            'from' => $envelope->getSender()->getAddress(),
            'to' => $to,
            'html' => $email->getHtmlBody(),
            'text' => $email->getTextBody(),
            'attachments' => $this->getAttachments($email),
            'allHeaders' => $allHeaders,
        ];

        return $payload;
    }

    /**
     * @param Email $email
     * @param Envelope $envelope
     * @param array $recipients
     * @return HeaderCollection
     */
    private function getAllHeaders(Email $email, Envelope $envelope, array $recipients): HeaderCollection
    {
        $allHeaders = new HeaderCollection();

        foreach ($email->getHeaders()->all() as $name => $header) {
            $allHeaders->add(new Header($header->getName(), $header->getBodyAsString()));
        }

        return $allHeaders;
    }

    /**
     * @param Email $email
     * @return array
     */
    private function getAttachments(Email $email): array
    {
        $attachments = [];

        $emailAttachments = $email->getAttachments();

        if (count($emailAttachments) <= 0) {
            return [];
        }

        foreach ($emailAttachments as $emailAttachment) {
            $headers = $emailAttachment->getPreparedHeaders();
            $disposition = $headers->getHeaderBody('Content-Disposition');

            $attributes = [
                'content' => $emailAttachment->bodyToString(),
                'contentType' => $headers->get('Content-Type')->getBody(),
            ];

            if ($name = $headers->getHeaderParameter('Content-Disposition', 'name')) {
                $attributes['filename'] = basename($name);
            }

            if ('inline' === $disposition) {
                $attributes['disposition'] = 'inline';
            } else {
                $attributes['disposition'] = 'attachment';
            }

            $attachments[] = $attributes;
        }

        return $attachments;
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

            $recipients[$type][] = $recipient->getAddress();
        }

        return $recipients;
    }
}
