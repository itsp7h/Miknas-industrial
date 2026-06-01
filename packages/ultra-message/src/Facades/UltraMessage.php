<?php

namespace PromoSeven\UltraMessage\Facades;

use Illuminate\Support\Facades\Facade;
use PromoSeven\UltraMessage\UltraMessageClient;
use PromoSeven\UltraMessage\UltraMessageFake;

/**
 * @method static array sendText(string $to, string $message, ?string $replyId = null)
 * @method static array sendImage(string $to, string $imageUrl, string $caption = '')
 * @method static array sendDocument(string $to, string $fileUrl, string $filename, string $caption = '')
 * @method static array sendAudio(string $to, string $audioUrl)
 * @method static array sendVoice(string $to, string $audioUrl)
 * @method static array sendVideo(string $to, string $videoUrl, string $caption = '')
 * @method static array sendSticker(string $to, string $stickerUrl)
 * @method static array sendContact(string $to, string $contactId)
 * @method static array sendLocation(string $to, float $lat, float $lng, string $address = '')
 * @method static array sendReaction(string $to, string $messageId, string $emoji)
 * @method static array deleteMessage(string $messageId)
 * @method static array getInstanceStatus()
 * @method static array getChats()
 * @method static array getContacts()
 * @method static array getGroups()
 *
 * @see \PromoSeven\UltraMessage\UltraMessageClient
 */
class UltraMessage extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return UltraMessageClient::class;
    }

    public static function fake(): UltraMessageFake
    {
        $fake = new UltraMessageFake();
        static::swap($fake);
        return $fake;
    }

    public static function configUsing(callable $resolver): void
    {
        app()->instance('ultra-message.config-resolver', $resolver);
        app()->forgetInstance(UltraMessageClient::class);
    }
}
