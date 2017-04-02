<?php

$conf['api'] = [
    'userInfo' =>[
        'sign' => 'required|string|size:32',
        'time' => 'required|integer|integer',
    ],
];

return $conf;