<?php

return [
    'visible' => array_values(array_filter(array_map('trim', explode(',', env('VISIBLE_MODULES', 'manufacturing,accounting'))))),
];
