<?php

namespace App\Rules;

use App\Models\UpdateRequest;
use Illuminate\Contracts\Validation\Rule;

class UserEmailNotInPendingSignupRequest implements Rule
{
    /**
     * @var string|null
     */
    protected $message;

    /**
     * Create a new rule instance.
     *
     * @param \App\Models\User|null $excludedUser
     * @param string|null $message
     */
    public function __construct(?string $message = null)
    {
        $this->message = $message;
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param string $attribute
     * @param mixed $email
     * @return bool
     */
    public function passes($attribute, $email)
    {
        if (!is_string($email)) {
            return false;
        }

        return UpdateRequest::query()
            ->where('updateable_type', UpdateRequest::NEW_TYPE_ORGANISATION_SIGN_UP_FORM)
            ->where('data->user->email', $email)
            ->doesntExist();
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return $this->message ?? 'This email address exists for a user pending approval.';
    }
}
