<?php
declare(strict_types=1);

namespace App\Instrumentation;

use App\Instrumentation\Mysqli\MysqliInstrumentation;
use App\Instrumentation\Pdo\PdoInstrumentation;

MysqliInstrumentation::register();
PdoInstrumentation::register();
