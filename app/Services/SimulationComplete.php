<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

/**
 * Control-flow signal thrown once a simulated challenge run (or the whole simulation batch)
 * has captured its outcome, to force the surrounding database transaction to roll back. The
 * simulator only wants the result, never the 150 throwaway games it played to get there.
 */
final class SimulationComplete extends RuntimeException {}
