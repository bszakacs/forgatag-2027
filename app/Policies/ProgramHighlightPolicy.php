<?php

namespace App\Policies;

/**
 * A napi kiemelés nem önálló lap: a program lapjának „Napi kiemelés” gombja kezeli (a jóváhagyó joga,
 * UserRole::canApprovePrograms). Közvetlen felülete nincs — minden tiltva (az alap Policy).
 */
class ProgramHighlightPolicy extends Policy {}
