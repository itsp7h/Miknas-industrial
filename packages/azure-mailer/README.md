# promoseven/azure-mailer

Laravel mail transport for Microsoft 365 via the Azure AD Graph API.
Replaces SMTP with a Client Credentials OAuth2 flow — drop-in compatible with
Laravel's `Mail` facade, Mailables, Notifications, and queued mail.

## Requirements

- PHP 8.2+
- Laravel 11 or 12
- An Azure AD App Registration with `Mail.Send` application permission

## Installation

```bash
composer require promoseven/azure-mailer
```

## Azure AD Setup

1. Go to **Azure Portal → App Registrations → New registration**
2. Note the **Tenant ID**, **Client ID**
3. Under **Certificates & secrets**, create a new **Client secret**
4. Under **API permissions**, add **Microsoft Graph → Application permissions → Mail.Send**
5. Click **Grant admin consent**

## Configuration

Add to `config/mail.php` under `mailers`:

```php
'azure' => [
    'transport'     => 'azure',
    'tenant_id'     => env('AZURE_TENANT_ID'),
    'client_id'     => env('AZURE_CLIENT_ID'),
    'client_secret' => env('AZURE_CLIENT_SECRET'),
    'from_address'  => env('AZURE_MAIL_FROM_ADDRESS'),
],
```

Set `.env`:

```env
MAIL_MAILER=azure

AZURE_TENANT_ID=your-tenant-id
AZURE_CLIENT_ID=your-client-id
AZURE_CLIENT_SECRET=your-client-secret
AZURE_MAIL_FROM_ADDRESS=noreply@yourdomain.com
```

The `from_address` must be a mailbox in your Microsoft 365 tenant.

## Advanced config (optional)

Publish the config file to override defaults:

```bash
php artisan vendor:publish --tag=azure-mailer-config
```

This creates `config/azure-mailer.php`:

```php
return [
    'save_to_sent_items' => false,  // set true to keep copies in Sent folder
    'timeout'            => 30,     // HTTP timeout in seconds
    'graph_api_version'  => 'v1.0', // or 'beta'
];
```

## Usage

No changes needed — use Laravel mail exactly as before:

```php
Mail::to('user@example.com')->send(new OrderConfirmation($order));
```

## License

MIT
