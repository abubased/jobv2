<?php

namespace App\Modules\Payment\Services;

use Illuminate\Contracts\Session\Session;

/**
 * Wraps the framework session behind the module's own namespaced key so it
 * never reads or writes state the host application (or another package) owns.
 */
class PaymentSession
{
    private string $key;

    public function __construct(private Session $session)
    {
        $this->key = config('payment.session_prefix', 'apay_payment');
    }

    public function put(array $data): void
    {
        $this->session->put($this->key, array_merge($this->all(), $data));
    }

    public function get(string $field, mixed $default = null): mixed
    {
        return $this->session->get("{$this->key}.{$field}", $default);
    }

    public function all(): array
    {
        return $this->session->get($this->key, []);
    }

    public function exists(): bool
    {
        return $this->session->has($this->key);
    }

    public function forget(): void
    {
        $this->session->forget($this->key);
    }

    public function setStep(string $step): void
    {
        $this->put(['step' => $step]);
    }

    public function step(): ?string
    {
        return $this->get('step');
    }
}
