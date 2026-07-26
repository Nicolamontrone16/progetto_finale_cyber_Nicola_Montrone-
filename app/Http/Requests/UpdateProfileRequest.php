<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

class UpdateProfileRequest extends FormRequest
{
    private const SENSITIVE_FIELDS = [
        'is_admin',
        'is_writer',
        'is_revisor',
        'isAdmin',
        'admin',
        'role',
        'roles',
        'ruolo',
        'permissions',
        'email_verified_at',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
            'password' => ['nullable', 'string', Password::default(), 'confirmed'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $rejectedFields = array_values(array_intersect(
                array_keys($this->all()),
                self::SENSITIVE_FIELDS
            ));

            if ($rejectedFields === []) {
                return;
            }

            Log::warning('Mass assignment attempt blocked', [
                'event' => 'mass_assignment_attempt_blocked',
                'actor_user_id' => $this->user()?->id,
                'rejected_fields' => $rejectedFields,
                'ip_address' => $this->ip(),
                'route' => $this->route()?->getName() ?? $this->path(),
                'result' => 'blocked',
            ]);

            $validator->errors()->add(
                'profile',
                'La richiesta contiene campi non consentiti.'
            );
        });
    }
}
