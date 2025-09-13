<?php

namespace Backpack\CRUD\app\Models\Traits;

use Carbon\Carbon;

trait CrudTrait
{
    use HasIdentifiableAttribute;
    use HasEnumFields;
    use HasRelationshipFields;
    use HasUploadFields;
    use HasFakeFields;
    use HasTranslatableFields;

    public static function hasCrudTrait()
    {
        return true;
    }

    protected function toUtcForQuery(string $input, bool $isEnd = false): Carbon
    {
        $displayTz = config('app.display_timezone', 'America/Chihuahua');
        $dt = Carbon::parse($input, $displayTz);

        $onlyDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', trim($input)) === 1;
        if ($onlyDate) {
            $dt = $isEnd ? $dt->endOfDay() : $dt->startOfDay();
        }

        // Devuelve en UTC para usar en la query
        return $dt->clone()->setTimezone('UTC');
    }
}
