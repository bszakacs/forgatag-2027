<?php

namespace App\Policies;

/**
 * Titkos beállítások: MINDEN TILTVA (az alap Policy). Felülete nincs; a kezelés egyetlen útja az App\Support\Titkok,
 * ami maga ellenőrzi a jogot (UserRole::canManageSecrets — csak szuperadmin).
 */
class SecretSettingPolicy extends Policy {}
