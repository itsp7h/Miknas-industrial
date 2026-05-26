<?php

namespace App\Http\Controllers;

use App\Models\MailAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PromoSeven\AzureMailer\Graph\TokenManager;

class MailAccountController extends Controller
{
    public function index(): JsonResponse
    {
        $accounts = MailAccount::orderBy('name')->get()->map(fn($a) => $this->accountData($a));
        return response()->json(['accounts' => $accounts]);
    }

    public function show(MailAccount $mailAccount): JsonResponse
    {
        return response()->json([
            'account' => array_merge($this->accountData($mailAccount), ['config' => $mailAccount->config]),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data    = $this->validated($request);
        $account = MailAccount::create($data);
        return response()->json(['success' => true, 'account' => $this->accountData($account)], 201);
    }

    public function update(Request $request, MailAccount $mailAccount): JsonResponse
    {
        $data = $this->validated($request, $mailAccount->id);
        $mailAccount->update($data);
        return response()->json(['success' => true, 'account' => $this->accountData($mailAccount->fresh())]);
    }

    public function destroy(MailAccount $mailAccount): JsonResponse
    {
        $mailAccount->delete();
        return response()->json(['success' => true]);
    }

    public function testConnection(MailAccount $mailAccount): JsonResponse
    {
        try {
            if ($mailAccount->type === 'azure') {
                $config = array_merge($mailAccount->config, ['from_address' => $mailAccount->from_address]);
                (new TokenManager($config))->getToken();
            } else {
                $cfg    = $mailAccount->config;
                $host   = $cfg['host'] ?? '';
                $port   = (int) ($cfg['port'] ?? 587);
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
        $request->validate(['to' => ['required', 'email', 'max:255']]);
        try {
            $mailer = new \Illuminate\Mail\Mailer(
                $mailAccount->name,
                app('view'),
                $mailAccount->buildTransport(),
                app('events')
            );
            $mailer->raw(
                'This is a test email from SteelERP. Your mail account "' . $mailAccount->label . '" is working correctly.',
                function ($message) use ($request, $mailAccount) {
                    $message->to($request->to)
                            ->from($mailAccount->from_address, $mailAccount->from_name ?: 'SteelERP')
                            ->subject('Test Email from SteelERP');
                }
            );
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('sendTestEmail failed', [
                'account' => $mailAccount->name,
                'from'    => $mailAccount->from_address,
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function toggleEnabled(MailAccount $mailAccount): JsonResponse
    {
        $mailAccount->update(['enabled' => ! $mailAccount->enabled]);
        return response()->json(['success' => true, 'enabled' => $mailAccount->fresh()->enabled]);
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        $nameUnique = 'unique:mail_accounts,name' . ($ignoreId ? ",{$ignoreId}" : '');
        $rules = [
            'name'         => ['required', 'string', 'max:100', 'regex:/^[a-z0-9\-]+$/', $nameUnique],
            'label'        => ['required', 'string', 'max:150'],
            'type'         => ['required', 'in:azure,smtp'],
            'from_address' => ['required', 'email', 'max:255'],
            'from_name'    => ['nullable', 'string', 'max:150'],
            'enabled'      => ['boolean'],
        ];

        if ($request->input('type') === 'azure') {
            $rules['config.tenant_id']     = ['required', 'string', 'max:100'];
            $rules['config.client_id']     = ['required', 'string', 'max:100'];
            $rules['config.client_secret'] = ['required', 'string', 'max:500'];
        } else {
            $rules['config.host']       = ['required', 'string', 'max:255'];
            $rules['config.port']       = ['required', 'integer', 'min:1', 'max:65535'];
            $rules['config.encryption'] = ['required', 'in:tls,ssl,none'];
            $rules['config.username']   = ['nullable', 'string', 'max:255'];
            $rules['config.password']   = ['nullable', 'string', 'max:500'];
        }

        $v = $request->validate($rules);
        return [
            'name'         => $v['name'],
            'label'        => $v['label'],
            'type'         => $v['type'],
            'from_address' => $v['from_address'],
            'from_name'    => $v['from_name'] ?? null,
            'config'       => $v['config'],
            'enabled'      => $v['enabled'] ?? true,
        ];
    }

    private function accountData(MailAccount $account): array
    {
        return [
            'id'           => $account->id,
            'name'         => $account->name,
            'label'        => $account->label,
            'type'         => $account->type,
            'from_address' => $account->from_address,
            'from_name'    => $account->from_name,
            'enabled'      => $account->enabled,
        ];
    }
}
