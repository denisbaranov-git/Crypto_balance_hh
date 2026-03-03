<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class SupportedNetwork implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
         if(!in_array($value, array_keys(config('networks')))){
             $fail('The selected network is not supported. Supported networks: ' . implode(', ', array_keys(config('networks'))));
         }
    }
}
