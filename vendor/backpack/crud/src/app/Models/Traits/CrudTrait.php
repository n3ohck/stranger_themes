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

    public function dateUtc($date): Carbon
    {
        $dt = $value instanceof Carbon
            ? $value
            : Carbon::parse($date, config('app.display_timezone', 'America/Chihuahua'));

        $date = $dt->clone()->utc();
        return $date;
    }
}
