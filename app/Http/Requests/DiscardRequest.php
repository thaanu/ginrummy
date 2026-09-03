<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\GinRummy\Card;
use App\Domain\GinRummy\Deck;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DiscardRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'card' => ['required', 'string', Rule::in($this->everyCardCode())],
        ];
    }

    public function card(): Card
    {
        return Card::fromCode((string) $this->string('card'));
    }

    /**
     * Only a code that names a real card is accepted; whether the player is
     * actually holding it is decided by the engine.
     *
     * @return list<string>
     */
    private function everyCardCode(): array
    {
        return Card::toCodes(Deck::standard()->cards());
    }
}
