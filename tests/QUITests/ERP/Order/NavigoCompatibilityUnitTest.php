<?php

namespace QUITests\ERP\Order;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class NavigoCompatibilityUnitTest extends TestCase
{
    public function testCheckoutRoutingWithTheInstalledNavigoVersion(): void
    {
        $node = (new ExecutableFinder())->find('node');

        if ($node === null) {
            self::markTestSkipped('Node.js is required for the checkout routing tests.');
        }

        $navigo = getenv('QUIQQER_ORDER_TEST_NAVIGO')
            ?: OPT_DIR . 'bin/quiqqer-asset/navigo/navigo/lib/navigo.js';
        self::assertFileExists($navigo);
        $Process = new Process([
            $node,
            dirname(__DIR__, 3) . '/javascript/OrderProcessRouting.test.cjs',
            $navigo
        ], timeout: 20);
        $Process->run();

        self::assertTrue($Process->isSuccessful(), $Process->getOutput() . $Process->getErrorOutput());
    }
}
