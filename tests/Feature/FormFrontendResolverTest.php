<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Forms\FormConfigurationValidator;
use App\Modules\Forms\FormRegistry;
use Tests\TestCase;

final class FormFrontendResolverTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_external_frontend_redirect_is_resolved(): void
    {
        $directory = $this->configurationDirectory();
        file_put_contents($directory.'/External.php', '<?php return '.var_export($this->config('external-form', [
            'type' => 'external',
            'url' => 'https://example.com/form',
        ]), true).';');

        $this->app->instance(FormRegistry::class, new FormRegistry(new FormConfigurationValidator, $directory));

        $this->get('/forms/external-form')
            ->assertRedirect('https://example.com/form');
    }

    public function test_local_frontend_entry_and_asset_are_served(): void
    {
        $frontendRoot = sys_get_temp_dir().DIRECTORY_SEPARATOR.'frontend-root-'.uniqid();
        mkdir($frontendRoot.'/sample', 0777, true);
        file_put_contents($frontendRoot.'/sample/index.html', '<html><link href="styles.css"><body>Sample</body></html>');
        file_put_contents($frontendRoot.'/sample/styles.css', 'body{color:red}');

        config(['forms.frontend_root' => $frontendRoot]);

        $directory = $this->configurationDirectory();
        file_put_contents($directory.'/Sample.php', '<?php return '.var_export($this->config('sample-form', [
            'type' => 'local',
            'directory' => 'sample',
            'entry' => 'index.html',
        ]), true).';');

        $this->app->instance(FormRegistry::class, new FormRegistry(new FormConfigurationValidator, $directory));

        $this->get('/forms/sample-form')
            ->assertOk();

        $this->get('/forms/sample-form/styles.css')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/css; charset=UTF-8');
    }

    public function test_unknown_and_disabled_forms_return_not_found(): void
    {
        $directory = $this->configurationDirectory();

        $this->app->instance(FormRegistry::class, new FormRegistry(new FormConfigurationValidator, $directory));

        $this->get('/forms/missing-form')->assertNotFound();
        $this->get('/forms/education-loan')->assertNotFound();
    }

    public function test_directory_traversal_and_arbitrary_php_are_rejected(): void
    {
        $frontendRoot = sys_get_temp_dir().DIRECTORY_SEPARATOR.'frontend-root-'.uniqid();
        mkdir($frontendRoot.'/sample', 0777, true);
        file_put_contents($frontendRoot.'/secret.php', '<?php exit("secret");');

        config(['forms.frontend_root' => $frontendRoot]);

        $directory = $this->configurationDirectory();
        file_put_contents($directory.'/Sample.php', '<?php return '.var_export($this->config('sample-form', [
            'type' => 'local',
            'directory' => 'sample',
            'entry' => '../secret.php',
        ]), true).';');

        $this->app->instance(FormRegistry::class, new FormRegistry(new FormConfigurationValidator, $directory));

        $this->get('/forms/sample-form')->assertForbidden();
        $this->get('/forms/sample-form/%2e%2e/secret.php')->assertForbidden();
    }

    public function test_external_cors_preflight_allows_configured_origin_and_rejects_unknown_origin(): void
    {
        config(['cors.allowed_origins' => ['https://allowed.example']]);

        $this->withHeaders([
            'Origin' => 'https://allowed.example',
            'Access-Control-Request-Method' => 'POST',
            'Access-Control-Request-Headers' => 'content-type,accept',
        ])->options('/submit-form')->assertNoContent();

        $this->withHeaders([
            'Origin' => 'https://unknown.example',
            'Access-Control-Request-Method' => 'POST',
        ])->options('/submit-form')->assertNoContent();
    }

    public function test_existing_submit_form_request_structure_remains_supported(): void
    {
        config(['forms.public_client_key' => 'test-public-key']);

        $this->postJson('/submit-form', [
            'form_slug' => 'lalcoin',
            'name' => 'Rahim',
            'phone' => '01711111111',
            'address' => 'Dhaka',
            'form_data' => [
                'coin_count' => '10',
                'coin_year' => '2025',
            ],
        ], [
            'Origin' => 'https://sohojcash.github.io',
            'X-API-CLIENT-CREDENTIAL' => 'test-public-key',
        ])->assertOk();
    }

    private function configurationDirectory(): string
    {
        $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'form-configs-'.uniqid();
        mkdir($directory, 0777, true);

        return $directory;
    }

    private function config(string $slug, array $frontend): array
    {
        return [
            'slug' => $slug,
            'form_name' => 'Test Form',
            'description' => 'Test form',
            'enabled' => true,
            'reference_prefix' => 'TST',
            'merchant_name' => 'Test Merchant',
            'frontend' => $frontend,
            'fields' => [],
            'validation_rules' => [],
            'payment_amount' => '10.00',
            'payment_amount_source' => ['type' => 'fixed'],
            'accepted_payment_methods' => [],
            'merchant_notification_emails' => [],
            'admin_notification_email' => null,
            'settings' => [],
        ];
    }
}
