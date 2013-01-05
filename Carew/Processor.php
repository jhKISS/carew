<?php

namespace Carew;

use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\GenericEvent;
use Symfony\Component\Finder\Finder;

class Processor
{
    private $eventDispatcher;

    public function __construct(EventDispatcherInterface $eventDispatcher)
    {
        $this->eventDispatcher = $eventDispatcher;
    }

    public function process($dir, $filenamePattern = '.md', array $extraEvents = array(), $allowEmptyHeader = false)
    {
        if (!is_dir($dir)) {
            return array();
        }

        $documents = array();
        $finder = new Finder();
        foreach ($finder->in($dir)->files()->name($filenamePattern) as $file) {
            $document = new Document($file);

            $event = new GenericEvent($document, array('allowEmptyHeader' => $allowEmptyHeader));

            try {
                $this->eventDispatcher->dispatch(Events::DOCUMENT, $event);
                foreach ($extraEvents as $eventName) {
                    $this->eventDispatcher->dispatch($eventName, $event);
                }

                $document = $event->getSubject();
            } catch (\Exception $e) {
                throw new \LogicException(sprintf('Could not process: "%s". Error: "%s"', (string) $file, $e->getMessage()));
            }

            $documents[$document->getPath()] = $document;
        }

        return $documents;
    }

    public function processTags($tags, $baseDir)
    {
        $documents = array();
        $finder = new Finder();

        foreach ($finder->in($baseDir.'/layouts/')->files()->name('tags.*.twig') as $file) {
            $file = $file->getBasename();

            preg_match('#tags\.(.+?)\.twig$#', $file, $match);
            $format = $match[1];

            foreach ($tags as $tag => $posts) {
                $document = new Document();
                $document->setLayout((string) $file);
                $document->setPath(sprintf('tags/%s.%s', $tag, $format));
                $document->setTitle('Tags: '.$tag);
                $document->setVars(array(
                    'tag'   => $tag,
                    'posts' => $posts,
                ));

                $documents[$document->getPath()] = $document;
            }
        }

        return $documents;
    }

    public function processIndex($pages, $baseDir)
    {
        $documents = array();
        $finder = new Finder();

        foreach ($finder->in($baseDir.'/layouts/')->files()->name('index.*.twig') as $file) {
            $file = $file->getBasename();

            preg_match('#index\.(.+?)\.twig$#', $file, $match);
            $format = $match[1];

            $document = new Document();
            $document->setLayout((string) $file);
            $document->setPath(sprintf('index.%s', $format));
            $document->setTitle(false);
            $document->setVars(array('pages' => $pages));

            $documents[$document->getPath()] = $document;
        }

        return $documents;
    }

    public function sortByDate($documents)
    {
        uasort($documents, function ($a, $b) {
            $aMetadatas = $a->getMetadatas();
            $bMetadatas = $b->getMetadatas();
            if ($aMetadatas['date'] == $bMetadatas['date']) {
                return 0;
            }

            return ($aMetadatas['date'] > $bMetadatas['date']) ? -1 : 1;
        });

        return $documents;
    }

    public function buildCollection($documents, $key)
    {
        $collection = array();
        foreach ($documents as $document) {
            $metadatas = $document->getMetadatas();
            if (isset($metadatas[$key]) && is_array($metadatas[$key])) {
                foreach ($metadatas[$key] as $item) {
                    if (!array_key_exists($item, $collection)) {
                        $collection[$item] = array();
                    }

                    $collection[$item][] = $document;
                }
            }
        }

        return $collection;
    }
}