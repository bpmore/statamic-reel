<?php

declare(strict_types=1);

namespace Bpmore\Reel;

use Bpmore\Reel\Http\Controllers\AssetController;
use Bpmore\Reel\Http\Controllers\CaptionsController;
use Bpmore\Reel\Media\MediaFinder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Statamic\Facades\Permission;
use Statamic\Facades\User;
use Statamic\Facades\Utility;
use Statamic\Providers\AddonServiceProvider;

/**
 * The addon: a config file, two listeners, two tags, a command, and a page
 * under Tools with an editor on it.
 *
 * The VTT engine in `Vtt/` and the assessment in `Compliance/` know nothing
 * of this file or of Statamic. Everything here hands them values and reads
 * what comes back.
 *
 * There is no licence check here and there must never be one. Statamic
 * resolves the licence and shows its own banner.
 */
class ServiceProvider extends AddonServiceProvider
{
    public const PACKAGE = Settings::PACKAGE;

    public const EDIT_PERMISSION = 'edit reel captions';

    protected $config = true;

    protected $commands = [
        Commands\Audit::class,
    ];

    protected $tags = [
        Tags\Reel::class,
    ];

    /**
     * A plain script, not a Vite entry point. Statamic ships the Vue build
     * that compiles templates at runtime, so the editor needs no npm and no
     * build step. See the script itself.
     */
    protected $scripts = [
        __DIR__.'/../resources/js/reel-editor.js',
    ];

    protected $viewNamespace = 'reel';

    public function bootAddon()
    {
        // `@plain($x)` for any value that reaches a view Vue will compile.
        Blade::directive('plain', fn ($expression) => "<?php echo \\Bpmore\\Reel\\Support\\VueSafe::text({$expression}); ?>");

        // Seeing the page is Statamic's own "access reel utility". Writing a
        // caption file back to the asset container is separate: it is the
        // one write path in the addon.
        Permission::extend(function () {
            Permission::group('reel', 'Reel', function () {
                Permission::register(self::EDIT_PERMISSION)
                    ->label('Edit caption files')
                    ->description('Save changes to WebVTT caption files from the Reel editor. The file in the asset container is overwritten.');
            });
        });

        Utility::extend(fn () => Utility::register(
            Utility::make('reel')
                ->title('Reel')
                ->navTitle('Reel')
                ->icon('media-computer-screen-tv')
                ->description('Every video and audio asset, whether it has captions and a transcript, and an editor for the caption files.')
                ->view('reel::utilities.index', fn (Request $request) => $this->indexData($request))
                ->routes(function ($router) {
                    $router->get('assets/{id}', [AssetController::class, 'show'])->where('id', '.+')->name('asset');
                    $router->get('captions/{id}', [CaptionsController::class, 'show'])->where('id', '.+')->name('captions.show');
                    $router->post('captions/{id}', [CaptionsController::class, 'store'])->where('id', '.+')->name('captions.store');
                    $router->post('inspect', [CaptionsController::class, 'inspect'])->name('captions.inspect');
                })
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function indexData(Request $request): array
    {
        $rows = [];

        foreach ($this->app->make(MediaFinder::class)->all() as $media) {
            $assessment = $media->assess();
            $track = $media->captionTrack();

            $rows[] = [
                'id' => $media->id(),
                'path' => $media->asset->path(),
                'container' => $media->asset->containerHandle(),
                'kind' => $media->kind(),
                'verdict' => $assessment->verdict,
                'problems' => $assessment->problems,
                'captions' => $track?->path(),
                'captionsHow' => $track?->how,
                'chosenMissing' => $media->chosenTrackIsMissing(),
                'errors' => count($media->inspection()?->errors ?? []),
                'warnings' => count($media->inspection()?->warnings ?? []),
                'machine' => $media->inspection()?->machine->label,
                'transcript' => $media->hasTranscript(),
                'audioDescription' => $media->audioDescription(),
                'url' => cp_route('utilities.reel.asset', ['id' => $media->id()]),
                'editUrl' => $media->asset->editUrl(),
            ];
        }

        $counts = array_count_values(array_column($rows, 'verdict'));

        return [
            'rows' => $rows,
            'counts' => $counts + ['fail' => 0, 'warn' => 0, 'manual' => 0, 'pass' => 0],
            'standard' => $this->app->make(Settings::class)->thresholds()->name(),
            'gate' => $this->app->make(Settings::class)->gate(),
            'canEdit' => (bool) User::current()?->can(self::EDIT_PERMISSION),
        ];
    }

    public function register()
    {
        parent::register();

        // Resolved lazily: the config file merges during boot, after this.
        $this->app->singleton(Settings::class, fn () => Settings::fromConfig());
        $this->app->bind(MediaFinder::class, fn ($app) => new MediaFinder($app->make(Settings::class)));
    }
}
