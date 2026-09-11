<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;

final class ResetAccountPassword extends ResetPassword
{
    protected function resetUrl($notifiable): string
    {
        $locale = in_array($notifiable->preferred_locale, ['ar', 'en', 'fr'], true)
            ? $notifiable->preferred_locale : 'ar';

        return route('password.reset', [
            'locale' => $locale,
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);
    }
}
