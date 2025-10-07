<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Queue;

/*
|--------------------------------------------------------------------------
| Test Case & Queue Faking
|--------------------------------------------------------------------------
|
| 
| 
*/

uses(Tests\TestCase::class)->in(__DIR__);

Queue::fake();


/*
|--------------------------------------------------------------------------
| Database Handling
|--------------------------------------------------------------------------
|
| 
*/
uses(RefreshDatabase::class)
    ->in('Feature');

uses(LazilyRefreshDatabase::class)
    ->in('Unit/Models');
