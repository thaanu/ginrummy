<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\GinRummy\Enums\DrawSource;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DrawRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'source' => ['required', Rule::enum(DrawSource::class)],
        ];
    }

    public function source(): DrawSource
    {
        return DrawSource::from((string) $this->string('source'));
    }
}
