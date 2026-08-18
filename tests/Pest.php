<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

// Las pruebas unitarias también arrancan la aplicación —necesitan `config()`,
// el motor de cálculo y las constantes legales—, pero no tocan la base de
// datos, así que no cargan `RefreshDatabase`.
pest()->extend(TestCase::class)->in('Unit');
