<?php

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/** T-19: Articles I, II and III checked on the source code. */
final class LayerRulesTest extends TestCase
{
    /** @return array<string, string> file => contents */
    private static function sources(string $dir): array
    {
        $out = [];
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, 2).'/'.$dir)) as $f) {
            if ($f->isFile() && $f->getExtension() === 'php') {
                $out[$f->getPathname()] = (string) file_get_contents($f->getPathname());
            }
        }

        return $out;
    }

    /** @return list<string> */
    public static function violations(array $sources, string $forbiddenPattern): array
    {
        $bad = [];
        foreach ($sources as $file => $code) {
            if (preg_match_all('/^use\s+([^;]+);/m', $code, $m)) {
                foreach ($m[1] as $import) {
                    if (preg_match($forbiddenPattern, $import) === 1) {
                        $bad[] = basename($file).' → '.$import;
                    }
                }
            }
        }

        return $bad;
    }

    public function test_article_i_domain_depends_on_nothing(): void
    {
        $this->assertSame([], self::violations(self::sources('app/Domain'), '/^(App\\\\(Application|Adapters|Bootstrap)|Illuminate|Symfony|Carbon|Firebase)/'));
    }

    public function test_article_ii_application_does_not_import_adapters_or_framework(): void
    {
        $this->assertSame([], self::violations(self::sources('app/Application'), '/^(App\\\\(Adapters|Bootstrap)|Illuminate|Symfony|Carbon|Firebase)/'));
    }

    public function test_article_iii_adapters_are_only_instantiated_in_the_composition_root(): void
    {
        $bad = [];
        foreach (self::sources('app') as $file => $code) {
            if (str_contains($file, '/app/Bootstrap/') || str_contains($file, '/app/Adapters/')) {
                continue;
            }
            if (preg_match('/new\s+\\\\?(App\\\\Adapters\\\\|Db\w+Repository|Argon2PasswordHasher|JwtTokenIssuer|DiskCoverStorage)/', $code) === 1) {
                $bad[] = basename($file);
            }
        }
        $this->assertSame([], $bad);
    }

    public function test_the_check_detects_a_forbidden_import(): void
    {
        $fake = ['Fake.php' => "<?php\nnamespace App\\Domain;\nuse Illuminate\\Support\\Facades\\DB;\n"];
        $this->assertSame(['Fake.php → Illuminate\\Support\\Facades\\DB'], self::violations($fake, '/^Illuminate/'));
    }
}
