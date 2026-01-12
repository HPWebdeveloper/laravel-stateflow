<?php

declare(strict_types=1);

use Hpwebdeveloper\LaravelStateflow\Testing\PestExpectations;
use Hpwebdeveloper\LaravelStateflow\Tests\TestCase;

uses(TestCase::class)->in('Feature');

// Register StateFlow Pest expectations
PestExpectations::register();
