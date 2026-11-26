<?php

namespace App\Auth;

use Illuminate\Support\Arr;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\User;

/**
 * "Sign in with nikniq": a standard OAuth 2 authorisation-code client for
 * nikniq.com. Endpoints default to Laravel Passport's layout and can be
 * changed in config/services.php (nikniq.*) if the site uses another.
 */
class NikniqProvider extends AbstractProvider
{
    protected $scopeSeparator = ' ';

    protected $usesPKCE = true;

    private function endpoint(string $key): string
    {
        $cfg = config('services.nikniq');

        return rtrim($cfg['url'], '/').'/'.ltrim($cfg[$key], '/');
    }

    protected function getAuthUrl($state): string
    {
        return $this->buildAuthUrlFromBase($this->endpoint('authorize_path'), $state);
    }

    protected function getTokenUrl(): string
    {
        return $this->endpoint('token_path');
    }

    protected function getUserByToken($token): array
    {
        $response = $this->getHttpClient()->get($this->endpoint('user_path'), [
            'headers' => ['Accept' => 'application/json', 'Authorization' => 'Bearer '.$token],
        ]);
        $body = json_decode((string) $response->getBody(), true) ?: [];

        // Accept both a bare user object and one wrapped in "data".
        return isset($body['data']) && is_array($body['data']) ? $body['data'] : $body;
    }

    protected function mapUserToObject(array $user): User
    {
        return (new User)->setRaw($user)->map([
            'id' => (string) Arr::get($user, 'id', Arr::get($user, 'sub')),
            'nickname' => Arr::get($user, 'username', Arr::get($user, 'nickname')),
            'name' => Arr::get($user, 'name'),
            'email' => Arr::get($user, 'email'),
            'avatar' => Arr::get($user, 'avatar', Arr::get($user, 'picture', Arr::get($user, 'profile_photo_url'))),
        ]);
    }
}
