<?php

namespace App\Auth;


use Illuminate\Contracts\Auth\Authenticatable;

/**
 * User session hasil login endpoint SITAMA. Tidak disimpan di tabel users;
 * datanya diambil dari profil endpoint yang tersimpan di session.
 */
class SitamaUser implements Authenticatable
{
  /**
   * Role endpoint => [daftar role lokal (dipakai middleware role: & menu), route dasbor].
   * pustik juga mendapat role operator agar menu & aksesnya sama dengan adminpenerimaan.
   */
  public const PETA_ROLE = [
    'pustik' => [[Role::ADMIN, Role::OPERATOR], 'admin.dashboard'],
    'adminpenerimaan' => [[Role::OPERATOR], 'operator.dashboard'],
  ];

  public function __construct(private array $atribut)
  {
  }

  /**
   * Bangun user dari balasan auth/login: data.user (profil) dan data.role.
   */
  public static function dariEndpoint(string $username, array $remote, array $roleEndpoint): self
  {
    $username = $remote['username'] ?? $username;

    $nama = $remote['name']
      ?? trim(($remote['first_name'] ?? '') . ' ' . ($remote['last_name'] ?? ''))
      ?: $username;

    return new self([
      'id' => (string) ($remote['id'] ?? $username),
      'name' => $nama,
      'username' => $username,
      'email' => $remote['email'] ?? null,
      'is_active' => true,
      'prodi' => null,
      'mahasiswa_npm' => null,
      // Hanya role yang dikenal aplikasi, urut sesuai balasan endpoint.
      'roles' => array_values(array_filter(
        array_map('strval', $roleEndpoint),
        fn ($r) => isset(self::PETA_ROLE[$r])
      )),
    ]);
  }

  public function toArray(): array
  {
    return $this->atribut;
  }

  public function __get(string $kunci): mixed
  {
    return $this->atribut[$kunci] ?? null;
  }

  public function __isset(string $kunci): bool
  {
    return isset($this->atribut[$kunci]);
  }

  /**
   * Role lokal hasil pemetaan role endpoint.
   */
  public function roleLokal(): array
  {
    return array_values(array_unique(array_merge(
      ...array_map(fn ($r) => self::PETA_ROLE[$r][0], $this->atribut['roles'] ?? [])
    )));
  }

  /**
   * Route dasbor untuk role endpoint pertama; null bila tidak ada yang dikenal.
   */
  public function dashboardRoute(): ?string
  {
    $role = $this->atribut['roles'][0] ?? null;

    return $role ? self::PETA_ROLE[$role][1] : null;
  }

  /**
   * Role lokal plus role endpoint asli (mis. 'adminpenerimaan'), agar menu
   * & middleware bisa membatasi per role endpoint bila perlu.
   */
  private function semuaRole(): array
  {
    return array_merge($this->roleLokal(), $this->atribut['roles'] ?? []);
  }

  public function hasRole($role): bool
  {
    return in_array($role, $this->semuaRole(), true);
  }

  public function hasAnyRole($roles): bool
  {
    return (bool) array_intersect((array) $roles, $this->semuaRole());
  }

  public function isAdmin(): bool
  {
    return $this->hasRole(Role::ADMIN);
  }

  public function isOperator(): bool
  {
    return $this->hasRole(Role::OPERATOR);
  }

  public function isProdi(): bool
  {
    return false;
  }

  public function isAlumni(): bool
  {
    return false;
  }

  public function homeRoute(): string
  {
    return route($this->dashboardRoute() ?? 'operator.login');
  }

  public function loginRoute(): string
  {
    return route('operator.login');
  }

  public function getAuthIdentifierName(): string
  {
    return 'id';
  }

  public function getAuthIdentifier(): mixed
  {
    return $this->atribut['id'];
  }

  public function getAuthPasswordName(): string
  {
    return 'password';
  }

  public function getAuthPassword(): string
  {
    return '';
  }

  public function getRememberToken(): ?string
  {
    return null;
  }

  public function setRememberToken($value): void
  {
  }

  public function getRememberTokenName(): string
  {
    return '';
  }
}
