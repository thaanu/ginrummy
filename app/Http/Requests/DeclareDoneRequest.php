<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\GinRummy\Card;
use App\Domain\GinRummy\Deck;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DeclareDoneRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'card' => ['nullable', 'string', Rule::in(Card::toCodes(Deck::standard()->cards()))],
        ];
    }

    /**
     * The card the player is putting down, when they still hold eleven.
     */
    public function card(): ?Card
    {
        $code = $this->string('card')->toString();

        return $code === '' ? null : Card::fromCode($code);
    }
}
