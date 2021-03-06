<?php
namespace DevGroup\ExtensionsManager\tests;

use DevGroup\ExtensionsManager\helpers\ExtensionFileWriter;
use testsHelper\TestConfigCleaner;
use Yii;
use yii\console\Application;

class ExtensionFileWriterTest extends \PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $config = include __DIR__ . '/../../testapp/config/console.php';
        new Application($config);
        Yii::$app->cache->flush();
        Yii::setAlias('@vendor', __DIR__ . '/../../testapp/vendor');
        parent::setUp();
    }

    public function tearDown()
    {
        parent::tearDown();
        if (Yii::$app && Yii::$app->has('session', true)) {
            Yii::$app->session->close();
        }
        Yii::$app = null;
        TestConfigCleaner::cleanExtensions();
        TestConfigCleaner::cleanTestConfigs();
    }

    public function checkFile()
    {
        $a = [];
        $fn = __DIR__ . '/../../testapp/config/extensions.php';
        if (false === file_exists($fn)) {
            $this->markTestSkipped();
        } else {
            $a = include $fn;
        }
        $this->assertNotEmpty($a);
        return $a;
    }

    public function testUpdateConfig()
    {
        $a = $this->checkFile();
        ExtensionFileWriter::updateConfig();
        $this->assertEquals(4, count($a));
    }

    public function testUpdateWithAddition()
    {
        TestConfigCleaner::removeExtFile();
        copy(__DIR__ . '/../../data/less-extensions.php', __DIR__ . '/../../testapp/config/extensions.php');
        ExtensionFileWriter::updateConfig();
        $a = $this->checkFile();
        $this->assertEquals(4, count($a));
    }

    public function testUpdateWithDeletion()
    {
        TestConfigCleaner::removeExtFile();
        copy(__DIR__ . '/../../data/more-extensions.php', __DIR__ . '/../../testapp/config/extensions.php');
        ExtensionFileWriter::updateConfig();
        $a = $this->checkFile();
        $this->assertEquals(4, count($a));
    }
}
