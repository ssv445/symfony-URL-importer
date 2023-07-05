<?php

namespace App\Controller;

use App\Entity\URL;
use Doctrine\ORM\EntityManagerInterface;
use League\Uri\Uri;
use League\Uri\UriModifier;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;


class URLImportController extends AbstractController
{
    /**
     * @Route("/", name="url_import_form", methods={"GET"})
     */
    public function importForm()
    {
        return $this->render('url/import_form.html.twig');
    }

    /**
     * @Route("/", name="url_import", methods={"POST"})
     */
    public function import(Request $request, EntityManagerInterface $entityManager)
    {
        $start = microtime(true);
        $uploadedFile = $request->files->get('csv_file');
        $file = $uploadedFile->openFile();

        $addedUrls = 0;

        $lines = [];
        $count = 0;
        foreach ($file as $line) {
            $lines[] = trim($line);
            $count++;

            if ($count == 100 || $file->eof()) {
                $addedUrls += $this->batchImport($lines, $entityManager);

                $count = 0;
                $lines = [];
            }
        }
        $end = microtime(true);

        return $this->render('url/import_result.html.twig', [
            'addedUrls' => $addedUrls,
            'timeTaken' => ($end - $start) / 1000
        ]);
    }

    protected function batchImport($URLs, $entityManager)
    {
        $notImported = [];
        $toBeInsertUrls = [];
        foreach ($URLs as $url) {
            if (filter_var($url, FILTER_VALIDATE_URL)) {
                $url = $this->normalizeUrl($url);
                $hash = md5($url);

                if (!isset($toBeInsertUrls[$hash])) {
                    $existingUrl = $entityManager->getRepository(Url::class)->findOneBy(['hash' => $hash]);

                    if (!$existingUrl) {
                        $toBeInsertUrls[$hash] = $url;
                        $newUrl = new URL();
                        $newUrl->setUrl($url);
                        $newUrl->setHash($hash);
                        $entityManager->persist($newUrl);
                    }
                }
            }
        }

        $entityManager->flush();
        $entityManager->clear();

        return count($toBeInsertUrls);
    }

    protected function normalizeUrl($url)
    {
        $url = trim($url);

        //normalizes URL
        $uri = Uri::createFromString($url);
        $newUri = UriModifier::sortQuery($uri);

        //TODO: Normalize path
        //TODO: Normalize #framgement or Remove it

        return (string)$newUri;
    }
}
