<?php
use Monolog\Handler\StreamHandler;
return ['default'=>env('LOG_CHANNEL','stderr'),'deprecations'=>['channel'=>env('LOG_DEPRECATIONS_CHANNEL','null'),'trace'=>false],'channels'=>[
 'stderr'=>['driver'=>'monolog','level'=>env('LOG_LEVEL','info'),'handler'=>StreamHandler::class,'with'=>['stream'=>'php://stderr'],'processors'=>[Monolog\Processor\PsrLogMessageProcessor::class]],
 'single'=>['driver'=>'single','path'=>storage_path('logs/laravel.log'),'level'=>env('LOG_LEVEL','debug'),'replace_placeholders'=>true],
 'null'=>['driver'=>'monolog','handler'=>Monolog\Handler\NullHandler::class],
]];