<?php namespace TightenCo\Jigsaw;

use Illuminate\Contracts\View\Factory;
use Illuminate\Support\Str;
use Symfony\Component\Finder\SplFileInfo;
use TightenCo\Jigsaw\Filesystem;
use TightenCo\Jigsaw\Handlers\DefaultHandler;

class Jigsaw
{
    public $test = "TEST";

    private $files;
    private $cachePath;
    /**
     * @var DefaultHandler[] // I know... we should interface this..
     */
    private $handlers = [];
    private $options = [
        'pretty' => true
    ];
    private $meta = [];

    public function __construct(Filesystem $files, $cachePath)
    {
        $this->files = $files;
        $this->cachePath = $cachePath;
    }

    public function registerHandler($handler)
    {
        $this->handlers[] = $handler;
    }

    public function build($source, $dest, $config = [])
    {
        $this->prepareDirectories([$this->cachePath, $dest]);
        $this->buildSite($source, $dest, $config);
        $this->cleanup();
    }

    public function setOption($option, $value)
    {
        $this->options[$option] = $value;
    }

    public function getMeta(){
        return $this->meta;
    }

    private function prepareDirectories($directories)
    {
        foreach ($directories as $directory) {
            $this->prepareDirectory($directory, true);
        }
    }

    private function prepareDirectory($directory, $clean = false)
    {
        if (!$this->files->isDirectory($directory)) {
            $this->files->makeDirectory($directory, 0755, true);
        }

        if ($clean) {
            $this->files->cleanDirectory($directory);
        }
    }

    private function getFiles($source)
    {
        return collect($this->files->allFiles($source))->filter(function ($file) {
            return !$this->shouldIgnore($file);
        });
    }

    /**
     * @param SplFileInfo $file
     * @param $dest
     * @return string[]
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    private function getFileMetaInfo($file, $dest){
        $handler = $this->getHandler($file);
        $meta = $handler->getMeta($file);

        $filename = $handler->resolveFilename($file);
        $meta["path"] = $file->getRelativePathname();
        $meta["target-path"] = $this->getPrettyRelativePathname(new ProcessedFile($filename, $file->getRelativePath(), ""));
        if(!array_key_exists("last_modified",$meta)){
            $meta["last_modified"] = $file->getMTime();
        }
        return $meta;
    }

    private function buildSite($source, $dest, $config)
    {
        $this->meta = []; // clean meta info
        $this
            ->getFiles($source)
            // gather meta info before building the site so we can access all infos in the blade templates
            ->each(function ($file) use ($dest, $config, &$meta) {
                /**
                 * @var SplFileInfo $file
                 */
                $meta = $this->getFileMetaInfo($file, $dest);
                $this->meta[$meta["path"]] = $meta;
            })
            ->each(function ($file) use ($dest, $config) {
                $this->buildFile($file, $dest, $config);
            });
    }

    private function cleanup()
    {
        $this->files->deleteDirectory($this->cachePath);
    }

    private function shouldIgnore($file)
    {
        return preg_match('/(^_|\/_)/', $file->getRelativePathname()) === 1;
    }

    private function buildFile($file, $dest, $config)
    {
        $file = $this->handle($file, $config);
        $path = $this->getTargetPath($file, $dest);
        $this->files->put($path, $file->contents());
    }

    private function getTargetPath($file, $dest){
        $directory = $this->getDirectory($file);
        $this->prepareDirectory("{$dest}/{$directory}");
        $path = "{$dest}/{$this->getRelativePathname($file)}";
        return $path;
    }

    private function handle($file, $config)
    {
        return $this->getHandler($file)->handle($file, $config + ["jigsaw" => $this]);
    }

    private function getDirectory($file)
    {
        if ($this->options['pretty']) {
            return $this->getPrettyDirectory($file);
        }

        return $file->relativePath();
    }

    private function getPrettyDirectory($file)
    {
        if ($file->extension() === 'html' && $file->name() !== 'index.html') {
            return "{$file->relativePath()}/{$file->basename()}";
        }

        return $file->relativePath();
    }

    private function getRelativePathname($file)
    {
        if ($this->options['pretty']) {
            return $this->getPrettyRelativePathname($file);
        }

        return $file->relativePathname();
    }

    private function getPrettyRelativePathname($file)
    {
        if ($file->extension() === 'html' && $file->name() !== 'index.html') {
            return $this->getPrettyDirectory($file) . '/index.html';
        }

        return $file->relativePathname();
    }

    private function getHandler($file)
    {
        foreach ($this->handlers as $handler) {
            if ($handler->canHandle($file)) {
                return $handler;
            }
        }
    }
}
