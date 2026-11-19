<?php

namespace App\Auth;

/**
 * Nama role lokal yang dipakai middleware role:, menu, dan SitamaUser.
 * (Pengganti konstanta App\Models\Roles yang sudah tidak ada.)
 */
final class Role
{
  public const ADMIN = 'admin';
  public const OPERATOR = 'operator';
  public const PRODI = 'prodi';
  public const ALUMNI = 'alumni';
}
