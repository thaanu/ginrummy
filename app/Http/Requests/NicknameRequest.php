<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Nicknames are the only free text a player can supply, so they are trimmed,
 * stripped of control characters and length limited before they reach the
 * database or another player's screen.
 */
class NicknameRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'nickname' => ['required', 'string', 'min:2', 'max:20', 'regex:/^[\p{L}\p{N}][\p{L}\p{N} \'\-_]*$/u'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nickname.regex' => 'Nicknames may only contain letters, numbers, spaces, hyphens, apostrophes and underscores.',
        ];
    }

    public function nickname(): string
    {
        return (string) $this->string('nickname');
    }

    protected function prepareForValidation(): void
    {
        $nickname = $this->input('nickname');

        if (is_string($nickname)) {
            $this->merge([
                'nickname' => preg_replace('/\s+/u', ' ', trim($nickname)) ?? '',
            ]);
        }
    }
}
