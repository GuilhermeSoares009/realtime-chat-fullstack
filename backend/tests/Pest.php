<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The TestCase is the foundation for all feature and unit tests in
| your application. It provides the necessary bootstrapping and helper methods.
*/

// Aplica o `Tests\TestCase` para que os métodos HTTP (postJson, getJson, etc.)
// e as Facades (Hash, Config, etc.) sejam carregados corretamente em todos os testes.
uses(Tests\TestCase::class)->in(__DIR__); 


/*
|--------------------------------------------------------------------------
| Database Handling
|--------------------------------------------------------------------------
|
| These traits are essential to fix the "Call to a member function connection() on null" 
| error and ensure tests run on a clean database.
*/

// Aplica o RefreshDatabase a todos os testes de feature.
// Isso migra e limpa o banco de dados antes de cada teste.
uses(RefreshDatabase::class)
    ->in('Feature');

// Aplica o LazilyRefreshDatabase a testes de unidade que interagem com o DB (Modelos).
// Isso é mais eficiente para Unit Tests que tocam o banco.
uses(LazilyRefreshDatabase::class)
    ->in('Unit/Models');