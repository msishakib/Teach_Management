<?php

use App\Models\Media;
use Spatie\ImageOptimizer\Optimizers\Avifenc;
use Spatie\ImageOptimizer\Optimizers\Cwebp;
use Spatie\ImageOptimizer\Optimizers\Gifsicle;
use Spatie\ImageOptimizer\Optimizers\Jpegoptim;
use Spatie\ImageOptimizer\Optimizers\Optipng;
use Spatie\ImageOptimizer\Optimizers\Pngquant;
use Spatie\ImageOptimizer\Optimizers\Svgo;
use Spatie\MediaLibrary\Conversions\ImageGenerators\Avif;
use Spatie\MediaLibrary\Conversions\ImageGenerators\Image;
use Spatie\MediaLibrary\Conversions\ImageGenerators\Pdf;
use Spatie\MediaLibrary\Conversions\ImageGenerators\Svg;
use Spatie\MediaLibrary\Conversions\ImageGenerators\Video;
use Spatie\MediaLibrary\Conversions\ImageGenerators\Webp;
use Spatie\MediaLibrary\Conversions\Jobs\PerformConversionsJob;
use Spatie\MediaLibrary\Downloaders\DefaultDownloader;
use Spatie\MediaLibrary\MediaCollections\FileAdder;
use Spatie\MediaLibrary\MediaCollections\Models\Observers\MediaObserver;
use Spatie\MediaLibrary\ResponsiveImages\Jobs\GenerateResponsiveImagesJob;
use Spatie\MediaLibrary\ResponsiveImages\TinyPlaceholderGenerator\Blurred;
use Spatie\MediaLibrary\ResponsiveImages\WidthCalculator\FileSizeOptimizedWidthCalculator;
use Spatie\MediaLibrary\Support\FileNamer\DefaultFileNamer;
use Spatie\MediaLibrary\Support\FileRemover\DefaultFileRemover;
use Spatie\MediaLibrary\Support\PathGenerator\DefaultPathGenerator;
use Spatie\MediaLibrary\Support\UrlGenerator\DefaultUrlGenerator;

return [

    /*
     * The disk on which to store added files and derived images by default.
     */
    'disk_name' => env('MEDIA_DISK', 'public'),

    /*
     * The disk on which to store conversions and responsive images when no disk
     * is specified on the media collection.
     */
    'conversions_disk_name' => env('MEDIA_CONVERSIONS_DISK', null),

    /*
     * The maximum file size of an item in bytes.
     */
    'max_file_size' => 1024 * 1024 * 10, // 10MB

    /*
     * Uploads whose file name contains any of these extensions will be rejected.
     */
    'disallowed_extensions' => FileAdder::$defaultDisallowedExtensions,

    /*
     * When set, only uploads whose final extension is in the list are accepted.
     */
    'allowed_extensions' => null,

    /*
     * This queue connection will be used to generate derived and responsive images.
     */
    'queue_connection_name' => env('QUEUE_CONNECTION', 'sync'),

    /*
     * This queue will be used to generate derived and responsive images.
     */
    'queue_name' => env('MEDIA_QUEUE', ''),

    /*
     * By default all conversions will be performed on a queue.
     */
    'queue_conversions_by_default' => env('QUEUE_CONVERSIONS_BY_DEFAULT', true),

    /*
     * Should database transactions be run after database commits?
     */
    'queue_conversions_after_database_commit' => env('QUEUE_CONVERSIONS_AFTER_DB_COMMIT', true),

    /*
     * The fully qualified class name of the media model.
     */
    'media_model' => Media::class,

    /*
     * The fully qualified class name of the media observer.
     */
    'media_observer' => MediaObserver::class,

    /*
     * When enabled, media collections will be serialised using the default
     * laravel model serialization behaviour.
     */
    'use_default_collection_serialization' => false,

    /*
     * The fully qualified class name of the model used for temporary uploads.
     */
    'temporary_upload_model' => null,

    /*
     * When enabled, Media Library Pro will only process temporary uploads that were uploaded
     * in the same session.
     */
    'enable_temporary_uploads_session_affinity' => true,

    /*
     * When enabled, Media Library pro will generate thumbnails for uploaded file.
     */
    'generate_thumbnails_for_temporary_uploads' => true,

    /*
     * This is the class that is responsible for naming generated files.
     */
    'file_namer' => DefaultFileNamer::class,

    /*
     * The class that contains the strategy for determining a media file's path.
     */
    'path_generator' => DefaultPathGenerator::class,

    /*
     * The class that contains the strategy for determining how to remove files.
     */
    'file_remover_class' => DefaultFileRemover::class,

    /*
     * Here you can specify which path generator should be used for the given class.
     */
    'custom_path_generators' => [
        // Model::class => PathGenerator::class
        // or
        // 'model_morph_alias' => PathGenerator::class
    ],

    /*
     * When urls to files get generated, this class will be called.
     */
    'url_generator' => DefaultUrlGenerator::class,

    /*
     * Moves media on updating to keep path consistent.
     */
    'moves_media_on_update' => false,

    /*
     * Whether to activate versioning when urls to files get generated.
     */
    'version_urls' => false,

    /*
     * The media library will try to optimize all converted images by removing
     * metadata and applying a little bit of compression.
     */
    'image_optimizers' => [
        Jpegoptim::class => [
            '-m85',
            '--force',
            '--strip-all',
            '--all-progressive',
        ],
        Pngquant::class => [
            '--force',
        ],
        Optipng::class => [
            '-i0',
            '-o2',
            '-quiet',
        ],
        Svgo::class => [
            '--disable=cleanupIDs',
        ],
        Gifsicle::class => [
            '-b',
            '-O3',
        ],
        Cwebp::class => [
            '-m 6',
            '-pass 10',
            '-mt',
            '-q 90',
        ],
        Avifenc::class => [
            '-a cq-level=23',
            '-j all',
            '--min 0',
            '--max 63',
            '--minalpha 0',
            '--maxalpha 63',
            '-a end-usage=q',
            '-a tune=ssim',
        ],
    ],

    /*
     * These generators will be used to create an image of media files.
     */
    'image_generators' => [
        Image::class,
        Webp::class,
        Avif::class,
        Pdf::class,
        Svg::class,
        Video::class,
    ],

    /*
     * The path where to store temporary files while performing image conversions.
     */
    'temporary_directory_path' => null,

    /*
     * The engine that should perform the image conversions.
     */
    'image_driver' => env('IMAGE_DRIVER', 'gd'),

    /*
     * FFMPEG & FFProbe binaries paths, only used if you try to generate video
     * thumbnails and have installed the php-ffmpeg/php-ffmpeg composer dependency.
     */
    'ffmpeg_path' => env('FFMPEG_PATH', '/usr/bin/ffmpeg'),
    'ffprobe_path' => env('FFPROBE_PATH', '/usr/bin/ffprobe'),

    'ffmpeg_timeout' => env('FFMPEG_TIMEOUT', 900),

    'ffmpeg_threads' => env('FFMPEG_THREADS', 0),

    /*
     * Here you can override the class names of the jobs used by this package.
     */
    'jobs' => [
        'perform_conversions' => PerformConversionsJob::class,
        'generate_responsive_images' => GenerateResponsiveImagesJob::class,
    ],

    /*
     * When using the addMediaFromUrl method you may want to replace the default downloader.
     */
    'media_downloader' => DefaultDownloader::class,

    /*
     * When using the addMediaFromUrl method the SSL is verified by default.
     */
    'media_downloader_ssl' => env('MEDIA_DOWNLOADER_SSL', true),

    /*
     * The default lifetime in minutes for temporary urls.
     */
    'temporary_url_default_lifetime' => env('MEDIA_TEMPORARY_URL_DEFAULT_LIFETIME', 5),

    'remote' => [
        'extra_headers' => [
            'CacheControl' => 'max-age=604800',
        ],
    ],

    'responsive_images' => [
        'width_calculator' => FileSizeOptimizedWidthCalculator::class,
        'use_tiny_placeholders' => true,
        'tiny_placeholder_generator' => Blurred::class,
    ],

    'enable_vapor_uploads' => env('ENABLE_MEDIA_LIBRARY_VAPOR_UPLOADS', false),

    'default_loading_attribute_value' => null,

    'prefix' => env('MEDIA_PREFIX', ''),

    'force_lazy_loading' => env('FORCE_MEDIA_LIBRARY_LAZY_LOADING', true),
];
