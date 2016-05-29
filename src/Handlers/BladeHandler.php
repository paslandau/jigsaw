<?php namespace TightenCo\Jigsaw\Handlers;

use Illuminate\Contracts\View\Factory;
use TightenCo\Jigsaw\ProcessedFile;

class BladeHandler
{
    private $viewFactory;

    public function __construct(Factory $viewFactory)
    {
        $this->viewFactory = $viewFactory;
    }

    public function canHandle($file)
    {
        return ends_with($file->getFilename(), '.blade.php');
    }

    public function handle($file, $data)
    {
        $filename = $this->resolveFilename($file);
        return new ProcessedFile($filename, $file->getRelativePath(), $this->render($file, $data));
    }

    public function render($file, $data)
    {
        return $this->viewFactory->file($file->getRealPath(), $data)->render();
    }

    public function resolveFilename($file){
        $filename = $file->getBasename('.blade.php');
        if(ends_with($filename,".xml")){
            return $filename;
        }
        return  $filename . '.html';
    }

    public function getMeta($file){
        return [
        ]; // TODO: is there a way to get all variables exposed by a blade template?
    }
}
