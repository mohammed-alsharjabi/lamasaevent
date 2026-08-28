<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class DeployStaticFrontendRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (! config('publishing.deployment.enabled')) {
            return false;
        }

        $secret = config('publishing.deployment.webhook_secret');
        $timestamp = $this->header('X-Lamasaevent-Timestamp');
        $signature = $this->header('X-Lamasaevent-Signature');

        if (
            ! is_string($secret)
            || $secret === ''
            || ! is_string($timestamp)
            || ! ctype_digit($timestamp)
            || ! is_string($signature)
            || ! preg_match('/\A[0-9a-f]{64}\z/', $signature)
        ) {
            return false;
        }

        $maxAge = max(
            30,
            (int) config('publishing.deployment.max_signature_age', 300),
        );

        if (abs(now()->timestamp - (int) $timestamp) > $maxAge) {
            return false;
        }

        $expected = hash_hmac(
            'sha256',
            $timestamp.'.'.$this->getContent(),
            $secret,
        );

        return hash_equals($expected, $signature);
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'commit' => ['required', 'string', 'regex:/\A[0-9a-f]{40}\z/'],
        ];
    }
}
