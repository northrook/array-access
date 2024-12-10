<?php

declare(strict_types=1);

namespace Northrook;

use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Support\{FileInfo, Time};
use Symfony\Component\VarExporter\Exception\ExceptionInterface;
use Symfony\Component\VarExporter\VarExporter;
use Throwable;
use RuntimeException;

/**
 * @template TKey of array-key
 * @template TValue as mixed|array<TKey,TValue>
 *
 * @extends ArrayAccessor<TKey,TValue>
 */
class ArrayStore extends ArrayAccessor
{
    protected readonly FileInfo $storagePath;

    protected bool $locked = false;

    protected readonly ?string $storedHash;

    protected readonly ?int $timestampCreated;

    public readonly string $name;

    /**
     * @param string           $storagePath Location to store the array data file
     * @param null|string      $name        [optional] If no name is provided, one will be generated from an extending class, or $storagePath basename
     * @param bool             $readonly    [false] Prevents updating this instance of the ArrayStore
     * @param bool             $autosave    [true]  Should changes be saved to disk on __destruct?
     * @param ?LoggerInterface $logger      [optional] PSR-3 Logger
     */
    public function __construct(
        string                              $storagePath,
        ?string                             $name = null,
        protected bool                      $readonly = false,
        protected bool                      $autosave = true,
        protected readonly ?LoggerInterface $logger = null,
    ) {
        $this->storagePath = new FileInfo( $storagePath );
        $this->name        = $this->generateStoreName( $name );
        $this->loadDataStore();
    }

    public function __destruct()
    {
        if ( $this->autosave && ! empty( $this->array ) ) {
            $this->save();
        }
    }

    /**
     * @param bool $set
     *
     * @return $this
     */
    final public function autosave( bool $set = true ) : self
    {
        $this->autosave = $set;
        return $this;
    }

    /**
     * @param bool $set
     *
     * @return $this
     */
    final public function readonly( bool $set = true ) : self
    {
        $this->readonly = $set;
        return $this;
    }

    /**
     * @param array<TKey, TValue>|ArrayAccessor<TKey, TValue>|string $array
     * @param bool                                                   $override
     *
     * @return ArrayAccessor<TKey, TValue>
     */
    final public function setDefault(
        array|ArrayAccessor|string $array,
        bool                       $override = false,
    ) : ArrayAccessor {
        if ( ! empty( $this->array ) && ! $override ) {
            return $this;
        }

        return $this->arrayValue( $array, true );
    }

    /**
     * @return $this
     */
    final protected function loadDataStore() : self
    {
        if ( ! $this->storagePath->exists() || ! empty( $this->array ) ) {
            $this->logger?->info(
                'No data to load, {reason}.',
                ['reason' => ! empty( $this->array ) ? 'array already set' : 'storagePath not found'],
            );
            return $this;
        }

        try {
            $dataStore = include (string) $this->storagePath;

            if ( $dataStore['name'] !== $this->name ) {
                $message = $this::class.": Object name '{$this->name}' does not match '{$dataStore['name']}'.";
                throw new InvalidArgumentException( $message );
            }

            $this->storedHash = $dataStore['hash'];
            $this->array      = $dataStore['data'];

            unset( $dataStore );
        }
        catch ( Throwable $error ) {
            $this->logger?->emergency( $error->getMessage(), ['exception' => $error] );
        }

        return $this;
    }

    final public function save() : bool
    {
        // Prevent changes to the $data until we're done saving
        $this->locked = true;

        $hash = \hash( algo : 'xxh3', data : \serialize( $this->array ) );

        if ( ( $this->storedHash ?? null ) === $hash ) {
            $this->logger?->info(
                'No need to save {name}.',
                ['name' => $this::class."::{$this->name}"],
            );
            return false;
        }

        $dataStore = $this->createDataStore( $this->array, $hash );

        $this->storagePath->save( $dataStore );

        $this->updateOPCache();

        return true;
    }

    /**
     * @param array<array-key, mixed> $data
     * @param string                  $hash
     *
     * @return string
     */
    private function createDataStore( array $data, string $hash ) : string
    {
        $generated = new Time();
        $generator = static::class;

        try {
            $dataStore = VarExporter::export(
                [
                    'name'      => $this->name,
                    'path'      => $this->storagePath->getPathname(),
                    'generator' => $this::class,
                    'generated' => $generated->datetime,
                    'timestamp' => $generated->unixTimestamp,
                    'type'      => \gettype( $data ),
                    'hash'      => $hash,
                    'data'      => $data,
                ],
            );
        }
        catch ( ExceptionInterface $exception ) {
            throw new InvalidArgumentException( "Unable to export the {$this->name} dataStore.", 500, $exception );
        }

        return <<<PHP
            <?php // {$generated->unixTimestamp}
            
            /*---------------------------------------------------------------------
            
               Name      : {$this->name}
               Generated : {$generated->datetime}
               Hash      : {$hash}
            
               This file is generated by {$generator}.
            
               Do not edit it manually.
            
            ---------------------------------------------------------------------*/
            
            return {$dataStore};
            PHP;
    }

    final protected function updateOPCache() : void
    {
        $path = (string) $this->storagePath;

        try {
            if ( ! \ini_get( 'opcache.enable' ) ) {
                $this->logger?->critical(
                    'Unable to use OPCache for {className} -> {file}. OPcache is disabled.',
                    [
                        'className' => $this->name,
                        'file'      => $path,
                    ],
                );

                if ( ! $this->logger ) {
                    throw new RuntimeException( "Unable to use OPCache for {$this->name} -> {$path}." );
                }
                return;
            }
            \opcache_invalidate( $path, true );
            \opcache_compile_file( $path );
        }
        catch ( Throwable $exception ) {
            $this->logger?->error( $exception->getMessage(), ['file' => $path] );
            return;
        }

        $this->logger?->notice(
            "{compiler} recompiled file '{file}' successfully.",
            [
                'compiler' => 'OPCache',
                'file'     => $path,
            ],
        );
    }

    /**
     * Returns a name for this {@see ArrayStore}.
     *
     * - Returns `$name` if set
     * - else `className` if extended
     * - `storagePath->basename` otherwise
     *
     * @param null|string $name
     *
     * @return string
     */
    private function generateStoreName( ?string $name ) : string
    {
        $name ??= $this::class;
        if ( $name === static::class ) {
            return \strchr( $this->storagePath->getBasename(), '.', true ) ?: $name;
        }
        return $name;
    }
}
