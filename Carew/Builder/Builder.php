<?php

namespace Carew\Builder;

use Carew\Model\Document;
use Symfony\Component\Filesystem\Filesystem;
use Twig_Environment;

class Builder
{
    private $filesystem;
    private $webDir;
    private $twig;

    public function __construct(Twig_Environment $twig, $webDir, Filesystem $filesystem = null)
    {
        $this->filesystem = $filesystem ?: new Filesystem();
        $this->webDir     = $webDir;
        $this->twig       = $twig;
    }

    public function buildDocument(Document $document)
    {
        if (false === $document->getLayout()) {
            $rendered = $document->body;
        } else {
            $rendered = $this->twig->render($document->getLayout().'.html.twig', array(
                'document'     => $document,
                'relativeRoot' => $document->getRootPath(),
                'currentPath'  => $document->getPath(),
            ));
        }

        $target = $this->webDir.'/'.$document->getPath();
        $this->filesystem->mkdir(dirname($target));
        file_put_contents($target, $rendered);
    }
}
