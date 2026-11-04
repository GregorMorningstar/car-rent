<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Rules\Unique;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, ValidationRule|Password|Unique|string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)],
            'password' => ['required', 'confirmed', Password::defaults()],
            'phone' => ['required', 'string', 'max:20', 'regex:/^\+?[0-9 \-]{9,19}$/'],
            'street' => ['required', 'string', 'max:150'],
            'building_number' => ['required', 'string', 'max:10'],
            'apartment_number' => ['nullable', 'string', 'max:10'],
            'postal_code' => ['required', 'string', 'regex:/^\d{2}-\d{3}$/'],
            'city' => ['required', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone.regex' => 'Podaj poprawny numer telefonu, np. +48 600 100 200.',
            'postal_code.regex' => 'Kod pocztowy musi mieć format 00-000.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'imię i nazwisko',
            'email' => 'e-mail',
            'password' => 'hasło',
            'phone' => 'telefon',
            'street' => 'ulica',
            'building_number' => 'numer budynku',
            'apartment_number' => 'numer lokalu',
            'postal_code' => 'kod pocztowy',
            'city' => 'miejscowość',
        ];
    }
}
