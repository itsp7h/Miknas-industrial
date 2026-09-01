<?php

namespace App\Http\Controllers\Api\Settings;

use App\Http\Controllers\Controller;
use App\Models\MailAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Mail\Mailer;
use Illuminate\Support\Facades\Log;
use PromoSeven\AzureMailer\Graph\TokenManager;

class MailAccountController extends Controller
{
    /** Config keys that must never travel to the browser. */
    private const SECRET_KEYS = ['client_secret', 'password'];

    public function index(): JsonResponse
    {
        return response()->json([
            'data' => MailAccount::orderBy('label')->get()->map(fn (MailAccount $account) => $this->summary($account))->values(),
        ]);
    }

    /**
     * The Blade page's show endpoint returned the whole config, Azure client
     * secret and SMTP password included, so opening the edit form shipped live
     * credentials to the browser. They are stripped here and the form treats a
     * blank secret as "unchanged".
     */
    public function show(MailAccount $mailAccount): JsonResponse
    {
        $config = $mailAccount->config ?? [];

        return response()->json([
            'data' => $this->summary($mailAccount) + [
                'config' => collect($config)->except(self::SECRET_KEYS)->all(),
                'secrets_set' => collect(self::SECRET_KEYS)
                    ->mapWithKeys(fn ($key) => [$key => ($config[$key] ?? '') !== ''])
                    ->all(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $account = MailAccount::create($this->validated($request));

        return response()->json(['data' => $this->summary($account)], 201);
    }

    public function update(Request $request, MailAccount $mailAccount): JsonResponse
    {
        $mailAccount->update($this->validated($request, $mailAccount));

        return response()->json(['data' => $this->summary($mailAccount->fresh())]);
    }

    public function destroy(MailAccount $mailAccount): JsonResponse
    {
        $mailAccount->delete();

        return response()->json(['deleted' => true]);
    }

    public function toggleEnabled(MailAccount $mailAccount): JsonResponse
    {
        $mailAccount->update(['enabled' => ! $mailAccount->enabled]);

        return response()->json(['data' => $this->summary($mailAccount->fresh())]);
    }

    public function testConnection(MailAccount $mailAccount): JsonResponse
    {
        try {
            if ($mailAccount->type === 'azure') {
                $config = array_merge($mailAccount->config, ['from_address' => $mailAccount->from_address]);
                (new TokenManager($config))->getToken();
            } else {
                $config = $mailAccount->config;
                $host = $config['host'] ?? '';
                $port = (int) ($config['port'] ?? 587);
                $socket = @fsockopen($host, $port, $errno, $errstr, 5);

                if (! $socket) {
                    throw new \RuntimeException("Cannot connect to {$host}:{$port} — {$errstr}");
                }

                fclose($socket);
            }

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function sendTestEmail(Request $request, MailAccount $mailAccount): JsonResponse
    {
        $data = $request->validate(['to' => ['required', 'email', 'max:255']]);

        try {
            $mailer = new Mailer($mailAccount->name, app('view'), $mailAccount->buildTransport(), app('events'));
            $mailer->raw(
                'This is a test email from SteelERP. Your mail account "'.$mailAccount->label.'" is working correctly.',
                function ($message) use ($data, $mailAccount) {
                    $message->to($data['to'])
                        ->from($mailAccount->from_address, $mailAccount->from_name ?: 'SteelERP')
                        ->subject('Test Email from SteelERP');
                }
            );

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            Log::error('sendTestEmail failed', [
                'account' => $mailAccount->name,
                'from' => $mailAccount->from_address,
                'message' => $e->getMessage(),
            ]);

            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    private function validated(Request $request, ?MailAccount $existing = null): array
    {
        $nameUnique = 'unique:mail_accounts,name'.($existing ? ",{$existing->id}" : '');

        $rules = [
            'name' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9\-]+$/', $nameUnique],
            'label' => ['required', 'string', 'max:150'],
            'type' => ['required', 'in:azure,smtp'],
            'from_address' => ['required', 'email', 'max:255'],
            'from_name' => ['nullable', 'string', 'max:150'],
            'enabled' => ['boolean'],
        ];

        if ($request->input('type') === 'azure') {
            $rules['config.tenant_id'] = ['required', 'string', 'max:100'];
            $rules['config.client_id'] = ['required', 'string', 'max:100'];
            // Required on create; on edit a blank field keeps the stored one.
            $rules['config.client_secret'] = [$this->secretRule($existing, 'client_secret'), 'string', 'max:500'];
        } else {
            $rules['config.host'] = ['required', 'string', 'max:255'];
            $rules['config.port'] = ['required', 'integer', 'min:1', 'max:65535'];
            $rules['config.encryption'] = ['required', 'in:tls,ssl,none'];
            $rules['config.username'] = ['nullable', 'string', 'max:255'];
            $rules['config.password'] = ['nullable', 'string', 'max:500'];
        }

        $validated = $request->validate($rules);
        $config = $validated['config'];

        // A secret left blank on edit means "unchanged", so carry the stored one
        // forward rather than wiping it.
        foreach (self::SECRET_KEYS as $key) {
            if (($config[$key] ?? '') === '' && $existing) {
                $stored = $existing->config[$key] ?? null;
                if ($stored !== null) {
                    $config[$key] = $stored;
                } else {
                    unset($config[$key]);
                }
            }
        }

        return [
            'name' => $validated['name'],
            'label' => $validated['label'],
            'type' => $validated['type'],
            'from_address' => $validated['from_address'],
            'from_name' => $validated['from_name'] ?? null,
            'config' => $config,
            'enabled' => $validated['enabled'] ?? true,
        ];
    }

    private function secretRule(?MailAccount $existing, string $key): string
    {
        return ($existing && ($existing->config[$key] ?? '') !== '') ? 'nullable' : 'required';
    }

    private function summary(MailAccount $account): array
    {
        return [
            'id' => $account->id,
            'name' => $account->name,
            'label' => $account->label,
            'type' => $account->type,
            'from_address' => $account->from_address,
            'from_name' => $account->from_name,
            'enabled' => $account->enabled,
        ];
    }
}
