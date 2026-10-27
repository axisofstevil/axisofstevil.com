<?php

use App\Services;

/** @var $container \Illuminate\Container\Container */
/** @var $events \TightenCo\Jigsaw\Events\EventBus */
$events->beforeBuild([
    Services\Assets::class,
    Services\RemoteData::class,
]);

$events->afterBuild([
    Services\AuditFile::class,
    Services\Assets::class,
]);
