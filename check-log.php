<?php

echo 'Trait LogsActivity: '.(trait_exists(\Spatie\Activitylog\Models\Concerns\LogsActivity::class) ? 'CO' : 'KHONG').PHP_EOL;
echo 'Class LogOptions: '.(class_exists(\Spatie\Activitylog\Support\LogOptions::class) ? 'CO' : 'KHONG').PHP_EOL;
echo 'Model Tour dung trait: '.(in_array(\Spatie\Activitylog\Models\Concerns\LogsActivity::class, class_uses_recursive(\Modules\Tour\Models\Tour::class)) ? 'CO' : 'KHONG').PHP_EOL;
echo 'So ban ghi trong activity_log: '.\Spatie\Activitylog\Models\Activity::count().PHP_EOL;