<?php
use PHPUnit\Framework\TestCase;

final class VersionTest extends TestCase
{
    public function test_chps_version_defined_in_file()
    {
        $path = __DIR__ . '/../curedhosting-plugin-suite.php';
        $this->assertFileExists($path, 'Plugin bootstrap file must exist');

        $contents = file_get_contents($path);
        $this->assertNotFalse($contents, 'Failed reading plugin file');

        $matched = preg_match("/define\(\'CHPS_VERSION\',\s*\'([0-9]+\.[0-9]+\.[0-9]+)\'\)*/", $contents, $m);
        $this->assertEquals(1, $matched, 'CHPS_VERSION define not found or malformed');
        $this->assertMatchesRegularExpression('/^[0-9]+\.[0-9]+\.[0-9]+$/', $m[1]);
    }
}
