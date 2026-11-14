<?php
return [
 'name'=>env('APP_NAME','Bangladesh Voter App'),'env'=>env('APP_ENV','production'),'debug'=>(bool)env('APP_DEBUG',false),'url'=>env('APP_URL','http://localhost'),'timezone'=>env('APP_TIMEZONE','Asia/Dhaka'),'locale'=>'bn','fallback_locale'=>'en','faker_locale'=>'bn_BD','cipher'=>'AES-256-CBC','key'=>env('APP_KEY'),'previous_keys'=>array_filter(explode(',',env('APP_PREVIOUS_KEYS',''))),'maintenance'=>['driver'=>'file','store'=>'database'],
 'providers'=>Illuminate\Support\ServiceProvider::defaultProviders()->merge([App\Providers\AppServiceProvider::class])->toArray(),
 'aliases'=>Illuminate\Support\Facades\Facade::defaultAliases()->toArray(),
];