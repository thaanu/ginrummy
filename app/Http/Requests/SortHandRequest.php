<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\GinRummy\Card;
use App\Domain\GinRummy\Deck;
use App\Domain\GinRummy\GinRummyEngine;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SortHandRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'hand' => ['required', 'array', 'min:1', 'max:'.(GinRummyEngine::HAND_SIZE + 1)],
            'hand.*' => ['required', 'string', 'distinct', Rule::in(Card::toCodes(Deck::standard()->cards()))],
        ];
    }

    /**
     * The order is only a request; the engine confirms it is the same set of
     * cards the player actually holds.
     *
     * @return list<Card>
     */
    public function order(): array
    {
        /** @var list<string> $codes */
        $codes = array_values($this->validated()['hand']);

        return Card::fromCodes($codes);
    }
}
