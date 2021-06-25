<?php

namespace DDTrace\Tests\Unit\Integrations;

use DDTrace\Integrations\Drupal\DrupalCommon;
use DDTrace\Tests\Common\BaseTestCase;

final class DrupalCommonTest extends BaseTestCase
{
    public function testRouteNormalization()
    {
        self::assertEquals(DrupalCommon::normalizeRoute(''), '/');
        self::assertEquals(DrupalCommon::normalizeRoute('/'), '/');
        self::assertEquals(DrupalCommon::normalizeRoute('/en'), '/{lang}');
        self::assertEquals(DrupalCommon::normalizeRoute('/en/articles'), '/{lang}/articles');
        self::assertEquals(DrupalCommon::normalizeRoute('/en/articles/bar'), '/{lang}/articles/?');
    }
}
