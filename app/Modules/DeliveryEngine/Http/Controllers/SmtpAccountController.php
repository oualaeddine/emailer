<?php

namespace App\Modules\DeliveryEngine\Http\Controllers;

use App\Domain\Enums\PermissionName;
use App\Http\Controllers\Controller;
use App\Modules\DeliveryEngine\Http\Requests\StoreSmtpAccountRequest;
use App\Modules\DeliveryEngine\Http\Requests\UpdateSmtpAccountRequest;
use App\Modules\DeliveryEngine\Http\Resources\SmtpAccountResource;
use App\Modules\DeliveryEngine\Models\SmtpAccount;
use App\Modules\DeliveryEngine\Services\SmtpAccountService;
use App\Modules\DeliveryEngine\Services\SmtpConnectionTesterContract;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * docs/29-api-specification.md §29.6 — /api/v1/smtp-accounts.
 */
class SmtpAccountController extends Controller
{
    public function __construct(private readonly SmtpAccountService $accounts)
    {
    }

    public function index(): AnonymousResourceCollection
    {
        Gate::authorize(PermissionName::SmtpView->value);

        return SmtpAccountResource::collection($this->accounts->all());
    }

    public function store(StoreSmtpAccountRequest $request): SmtpAccountResource
    {
        $data = $request->validated();
        $data['password_encrypted'] = $data['password'];
        unset($data['password']);

        return new SmtpAccountResource($this->accounts->create($data));
    }

    public function update(UpdateSmtpAccountRequest $request, SmtpAccount $account): SmtpAccountResource
    {
        $data = $request->validated();

        if (array_key_exists('password', $data)) {
            $data['password_encrypted'] = $data['password'] ?? '';
            unset($data['password']);
        }

        return new SmtpAccountResource($this->accounts->update($account, $data));
    }

    public function destroy(SmtpAccount $account): Response
    {
        Gate::authorize(PermissionName::SmtpManageCredentials->value);

        abort_unless(
            $this->accounts->canBeDeleted($account),
            409,
            'Ce compte SMTP a déjà été utilisé pour des envois ; désactivez-le à la place.',
        );

        $account->delete();

        return response()->noContent();
    }

    public function test(Request $request, SmtpAccount $account, SmtpConnectionTesterContract $tester): array
    {
        Gate::authorize(PermissionName::SmtpTest->value);

        $testEmail = $request->input('test_email');

        $result = $testEmail !== null
            ? $tester->sendTestEmail($account, $testEmail)
            : $tester->testConnection($account);

        $account->update(['last_tested_at' => now()]);

        return ['success' => $result->success, 'raw_response' => $result->rawResponse];
    }

    public function testConfiguration(Request $request, SmtpConnectionTesterContract $tester): array
    {
        Gate::authorize(PermissionName::SmtpTest->value);

        $data = $request->validate([
            'host' => ['required', 'string', 'max:255'],
            'port' => ['required', 'integer', 'min:1', 'max:65535'],
            'encryption' => ['required', new \Illuminate\Validation\Rules\Enum(\App\Domain\Enums\SmtpEncryption::class)],
            'username' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
            'from_email' => ['required', 'email', 'max:191'],
            'from_name' => ['nullable', 'string', 'max:150'],
            'name' => ['nullable', 'string', 'max:150'],
            'provider' => ['nullable', 'string', 'max:50'],
            'test_email' => ['nullable', 'email'],
        ]);

        $account = new SmtpAccount();
        $account->name = $data['name'] ?? 'Configuration test';
        $account->provider = $data['provider'] ?? 'custom';
        $account->host = $data['host'];
        $account->port = (int) $data['port'];
        $account->encryption = $data['encryption'];
        $account->username = $data['username'];
        $account->password_encrypted = $data['password'];
        $account->from_email = $data['from_email'];
        $account->from_name = $data['from_name'] ?? null;

        $testEmail = $data['test_email'] ?? null;

        $result = $testEmail !== null
            ? $tester->sendTestEmail($account, $testEmail)
            : $tester->testConnection($account);

        return ['success' => $result->success, 'raw_response' => $result->rawResponse];
    }
}
