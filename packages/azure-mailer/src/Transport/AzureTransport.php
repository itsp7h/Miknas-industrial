<?php

declare(strict_types=1);

namespace PromoSeven\AzureMailer\Transport;

use PromoSeven\AzureMailer\Graph\GraphClient;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;

class AzureTransport extends AbstractTransport
{
    public function __construct(
        private readonly GraphClient $client,
        private readonly array $config = []
    ) {
        parent::__construct();
    }

    protected function doSend(SentMessage $message): void
    {
        $email = $message->getOriginalMessage();

        if (! $email instanceof Email) {
            return;
        }

        $this->client->send($this->buildPayload($email));
    }

    public function __toString(): string
    {
        return 'azure';
    }

    private function buildPayload(Email $email): array
    {
        return [
            'message' => [
                'subject' => $email->getSubject() ?? '',
                'body' => [
                    'contentType' => $email->getHtmlBody() !== null ? 'HTML' : 'Text',
                    'content' => $email->getHtmlBody() ?? $email->getTextBody() ?? '',
                ],
                'toRecipients' => $this->mapAddresses($email->getTo()),
                'ccRecipients' => $this->mapAddresses($email->getCc()),
                'bccRecipients' => $this->mapAddresses($email->getBcc()),
                'replyTo' => $this->mapAddresses($email->getReplyTo()),
                'attachments' => $this->mapAttachments($email),
            ],
            'saveToSentItems' => (bool) ($this->config['save_to_sent_items'] ?? false),
        ];
    }

    private function mapAddresses(array $addresses): array
    {
        return array_map(fn ($addr) => [
            'emailAddress' => [
                'address' => $addr->getAddress(),
                'name' => $addr->getName() ?? '',
            ],
        ], $addresses);
    }

    private function mapAttachments(Email $email): array
    {
        $result = [];

        foreach ($email->getAttachments() as $attachment) {
            if (! $attachment instanceof DataPart) {
                continue;
            }

            $result[] = [
                '@odata.type' => '#microsoft.graph.fileAttachment',
                'name' => $attachment->getFilename() ?? 'attachment',
                'contentType' => $attachment->getMediaType().'/'.$attachment->getMediaSubtype(),
                'contentBytes' => base64_encode($attachment->getBody()),
            ];
        }

        return $result;
    }
}
