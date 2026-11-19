<?php

namespace App\Auth;

use App\Http\Controllers\authentications\AuthController;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Contracts\Session\Session;

/**
 * Provider auth tanpa database: user dipulihkan dari profil endpoint SITAMA
 * yang disimpan AuthController di session saat login.
 */
class SitamaUserProvider implements UserProvider
{
  public function __construct(private Session $session)
  {
  }

  public function retrieveById($identifier): ?Authenticatable
  {
    $atribut = $this->session->get(AuthController::KUNCI_PROFIL);

    if (!is_array($atribut) || (string) ($atribut['id'] ?? '') !== (string) $identifier) {
      return null;
    }

    return new SitamaUser($atribut);
  }

  public function retrieveByToken($identifier, $token): ?Authenticatable
  {
    return null;
  }

  public function updateRememberToken(Authenticatable $user, $token): void
  {
  }

  public function retrieveByCredentials(array $credentials): ?Authenticatable
  {
    return null;
  }

  public function validateCredentials(Authenticatable $user, array $credentials): bool
  {
    return false;
  }

  public function rehashPasswordIfRequired(Authenticatable $user, array $credentials, bool $force = false): void
  {
  }
}
