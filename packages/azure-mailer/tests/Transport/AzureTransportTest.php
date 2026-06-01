<?php

declare(strict_types=1);

namespace PromoSeven\AzureMailer\Tests\Transport;

use PromoSeven\AzureMailer\Graph\GraphClient;
use PromoSeven\AzureMailer\Tests\TestCase;
use PromoSeven\AzureMailer\Transport\AzureTransport;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mime\Email;

class AzureTransportTest extends TestCase
{
    public function test_sends_html_body(): void
    {
        $captured = [];
        $client   = $this->createMock(GraphClient::class);
        $client->expects($this->once())
            ->method('send')
            ->willReturnCallback(function (array $payload) use (&$captured) {
                $captured = $payload;
            });

        $email = (new Email())
            ->from('from@example.com')
            ->to('to@example.com')
            ->subject('Hello')
            ->html('<p>World</p>');

        $transport = new AzureTransport($client, ['save_to_sent_items' => false]);
        $transport->send($email, Envelope::create($email));

        $this->assertSame('Hello', $captured['message']['subject']);
        $this->assertSame('HTML', $captured['message']['body']['contentType']);
        $this->assertSame('<p>World</p>', $captured['message']['body']['content']);
        $this->assertSame('to@example.com', $captured['message']['toRecipients'][0]['emailAddress']['address']);
        $this->assertFalse($captured['saveToSentItems']);
    }

    public function test_falls_back_to_text_body_when_no_html(): void
    {
        $captured = [];
        $client   = $this->createMock(GraphClient::class);
        $client->method('send')->willReturnCallback(function ($p) use (&$captured) { $captured = $p; });

        $email = (new Email())
            ->from('from@example.com')
            ->to('to@example.com')
            ->subject('Text only')
            ->text('Plain text content');

        $transport = new AzureTransport($client, ['save_to_sent_items' => false]);
        $transport->send($email, Envelope::create($email));

        $this->assertSame('Text', $captured['message']['body']['contentType']);
        $this->assertSame('Plain text content', $captured['message']['body']['content']);
    }

    public function test_maps_cc_bcc_and_reply_to(): void
    {
        $captured = [];
        $client   = $this->createMock(GraphClient::class);
        $client->method('send')->willReturnCallback(function ($p) use (&$captured) { $captured = $p; });

        $email = (new Email())
            ->from('from@example.com')
            ->to('to@example.com')
            ->cc('cc@example.com')
            ->bcc('bcc@example.com')
            ->replyTo('reply@example.com')
            ->subject('Recipients test')
            ->html('<p>hi</p>');

        $transport = new AzureTransport($client, ['save_to_sent_items' => false]);
        $transport->send($email, Envelope::create($email));

        $this->assertSame('cc@example.com', $captured['message']['ccRecipients'][0]['emailAddress']['address']);
        $this->assertSame('bcc@example.com', $captured['message']['bccRecipients'][0]['emailAddress']['address']);
        $this->assertSame('reply@example.com', $captured['message']['replyTo'][0]['emailAddress']['address']);
    }

    public function test_encodes_attachments_as_base64(): void
    {
        $captured = [];
        $client   = $this->createMock(GraphClient::class);
        $client->method('send')->willReturnCallback(function ($p) use (&$captured) { $captured = $p; });

        $email = (new Email())
            ->from('from@example.com')
            ->to('to@example.com')
            ->subject('With attachment')
            ->html('<p>see attachment</p>')
            ->attach('PDF content here', 'report.pdf', 'application/pdf');

        $transport = new AzureTransport($client, ['save_to_sent_items' => false]);
        $transport->send($email, Envelope::create($email));

        $attachment = $captured['message']['attachments'][0];
        $this->assertSame('#microsoft.graph.fileAttachment', $attachment['@odata.type']);
        $this->assertSame('report.pdf', $attachment['name']);
        $this->assertSame(base64_encode('PDF content here'), $attachment['contentBytes']);
        $this->assertSame('application/pdf', $attachment['contentType']);
    }

    public function test_save_to_sent_items_is_configurable(): void
    {
        $captured = [];
        $client   = $this->createMock(GraphClient::class);
        $client->method('send')->willReturnCallback(function ($p) use (&$captured) { $captured = $p; });

        $email = (new Email())
            ->from('from@example.com')
            ->to('to@example.com')
            ->subject('Save it')
            ->html('<p>keep</p>');

        $transport = new AzureTransport($client, ['save_to_sent_items' => true]);
        $transport->send($email, Envelope::create($email));

        $this->assertTrue($captured['saveToSentItems']);
    }

    public function test_to_string_returns_azure(): void
    {
        $client    = $this->createMock(GraphClient::class);
        $transport = new AzureTransport($client, []);

        $this->assertSame('azure', (string) $transport);
    }
}
