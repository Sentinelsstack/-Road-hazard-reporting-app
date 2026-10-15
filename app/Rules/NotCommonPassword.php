<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class NotCommonPassword implements ValidationRule
{
    private const COMMON_PASSWORDS = [
        '123456', 'password', '12345678', 'qwerty', '123456789',
        '12345', '1234', '111111', '1234567', 'sunshine',
        'qwerty123', 'iloveyou', 'princess', 'admin', 'welcome',
        '666666', 'abc123', 'football', '123123', 'monkey',
        '654321', '!@#$%^&*', 'charlie', 'aaaaaa', 'donald',
        'password1', 'qwerty12345', '1234567890', 'letmein', 'password123456',
        'whatever', 'dragon', 'baseball', 'trustno1', 'harley',
        'master', 'michael', 'ashley', 'bailey', 'shadow',
        '123123123', 'superman', 'qazwsx', 'maggie',
        'jennifer', 'chester', 'thomas', 'george', 'jessica',
        '12345678910', 'zxcvbnm', 'passw0rd', 'starwars',
        'qwertyuiop', 'hunter', 'ranger', 'ginger', 'robert',
        'summer', 'buster', 'amanda', 'joshua', 'matthew',
        'cookie', 'andrew', 'tigger', 'asshole', '121212',
        'flower', 'passwd', 'oliver', 'jordan', 'butterfly',
        'justin', 'daniel', 'l0ve', 'loving', 'heather',
        'thunder', 'killer', 'semperfi', 'sebastian', 'redskins',
        'james', 'samantha', 'scorpio', 'fender', 'brandy',
        'cowboys', 'chicago', 'ncc1701', 'happy', 'sophie',
        'midnight', 'phoenix', 'silver', 'winter',
        'trooper', 'steelers', 'packers', 'raiders', 'cheese',
        'yellow', 'sierra', 'alexis', 'mandy',
        'marine', 'wilson', 'tinkle', 'spider', 'nathan',
        'phillies', 'gateway', 'miller', 'computer', 'cameron',
        'corvette', 'merlin', 'rabbit', 'dakota', 'panther',
        'pioneer', 'mustang', 'jeep', 'michigan', 'crystal',
        'nascar', 'dallas', 'argentina', 'jamaica', 'italia',
        'brazil', 'mexico', 'charlotte', 'atlanta', 'orlando',
        'seattle', 'denver', 'austin', 'boston',
        'detroit', 'memphis', 'tuesday', 'october',
        'november', 'december', 'friday123', 'monday123',
    ];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (in_array(strtolower($value), self::COMMON_PASSWORDS)) {
            $fail('This password is too common and easily guessable. Please choose a more unique password.');
        }
    }
}
