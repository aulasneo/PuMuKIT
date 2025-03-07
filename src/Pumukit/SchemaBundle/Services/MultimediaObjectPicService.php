<?php

declare(strict_types=1);

namespace Pumukit\SchemaBundle\Services;

use Doctrine\ODM\MongoDB\DocumentManager;
use MongoDB\BSON\ObjectId;
use Pumukit\SchemaBundle\Document\MultimediaObject;
use Pumukit\SchemaBundle\Document\Pic;
use Pumukit\SchemaBundle\Document\Series;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\File\Exception\FileNotFoundException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class MultimediaObjectPicService
{
    private $dm;
    private $repo;
    private $dispatcher;
    private $targetPath;
    private $targetUrl;
    private $forceDeleteOnDisk;

    public function __construct(DocumentManager $documentManager, PicEventDispatcherService $dispatcher, $targetPath, $targetUrl, $forceDeleteOnDisk = true)
    {
        $this->dm = $documentManager;
        $this->dispatcher = $dispatcher;
        $this->targetPath = realpath($targetPath);
        if (!$this->targetPath) {
            throw new \InvalidArgumentException("The path '".$targetPath."' for storing Pics does not exist.");
        }
        $this->targetUrl = $targetUrl;
        $this->repo = $this->dm->getRepository(MultimediaObject::class);
        $this->forceDeleteOnDisk = $forceDeleteOnDisk;
    }

    /**
     * Returns the target path for an object.
     *
     * @return string
     */
    public function getTargetPath(MultimediaObject $multimediaObject)
    {
        return $this->targetPath.'/series/'.$multimediaObject->getSeries()->getId().'/video/'.$multimediaObject->getId();
    }

    /**
     * Returns the target url for an object.
     *
     * @return string
     */
    public function getTargetUrl(MultimediaObject $multimediaObject)
    {
        return $this->targetUrl.'/series/'.$multimediaObject->getSeries()->getId().'/video/'.$multimediaObject->getId();
    }

    /**
     * Get pics from series or multimedia object.
     *
     * @param MultimediaObject $multimediaObject
     *
     * @return mixed
     *
     * @throws \Doctrine\ODM\MongoDB\MongoDBException
     */
    public function getRecommendedPics($multimediaObject)
    {
        return $this->repo->findDistinctUrlPics();
    }

    /**
     * Set a pic from an url into the multimediaObject.
     *
     * @param string $picUrl
     * @param bool   $flush
     * @param bool   $isEventPoster
     *
     * @return MultimediaObject
     */
    public function addPicUrl(MultimediaObject $multimediaObject, $picUrl, $flush = true, $isEventPoster = false)
    {
        $pic = new Pic();
        if (!is_string($picUrl)) {
            $picUrl = $picUrl->getUrl();
        }
        $pic->setUrl($picUrl);
        if ($isEventPoster) {
            $pic = $this->updatePosterTag($multimediaObject, $pic);
        }

        $multimediaObject->addPic($pic);
        $this->dm->persist($multimediaObject);
        if ($flush) {
            $this->dm->flush();
        }

        $this->dispatcher->dispatchCreate($multimediaObject, $pic);

        return $multimediaObject;
    }

    /**
     * Set a pic from an url into the multimediaObject.
     *
     * @param bool $isEventPoster
     *
     * @return MultimediaObject
     *
     * @throws \Exception
     */
    public function addPicFile(MultimediaObject $multimediaObject, UploadedFile $picFile, $isEventPoster = false)
    {
        if (UPLOAD_ERR_OK != $picFile->getError()) {
            throw new \Exception($picFile->getErrorMessage());
        }

        if (!is_file($picFile->getPathname())) {
            throw new FileNotFoundException($picFile->getPathname());
        }

        if (file_exists($this->getTargetPath($multimediaObject).'/'.$picFile->getClientOriginalName())) {
            $i = random_int(0, 15);
            $name = $picFile->getClientOriginalName().$i;
        } else {
            $name = $picFile->getClientOriginalName();
        }

        $path = $picFile->move($this->getTargetPath($multimediaObject), $name);

        $pic = new Pic();
        $pic->setUrl(str_replace($this->targetPath, $this->targetUrl, $path->getPathname()));

        if (!is_string($path)) {
            $path = $path->getPathname();
        }
        $pic->setPath($path);

        if ($isEventPoster) {
            $pic = $this->updatePosterTag($multimediaObject, $pic);
        }

        $multimediaObject->addPic($pic);
        $this->dm->persist($multimediaObject);
        $this->dm->flush();

        $this->dispatcher->dispatchCreate($multimediaObject, $pic);

        return $multimediaObject;
    }

    /**
     * Set a pic from a memory string.
     *
     * @param string $pic
     * @param string $format
     *
     * @return MultimediaObject
     */
    public function addPicMem(MultimediaObject $multimediaObject, $pic, $format = 'png')
    {
        $absCurrentDir = $this->getTargetPath($multimediaObject);

        $fs = new Filesystem();
        $fs->mkdir($absCurrentDir);

        $mongoId = new ObjectId();

        $fileName = $mongoId.'.'.$format;
        $path = $absCurrentDir.'/'.$fileName;
        while (file_exists($path)) {
            $mongoId = new ObjectId();
            $fileName = $mongoId.'.'.$format;
            $path = $absCurrentDir.'/'.$fileName;
        }

        file_put_contents($path, $pic);

        $pic = new Pic();
        $pic->setUrl(str_replace($this->targetPath, $this->targetUrl, $path));
        $pic->setPath($path);

        $multimediaObject->addPic($pic);
        $this->dm->persist($multimediaObject);
        $this->dm->flush();

        $this->dispatcher->dispatchCreate($multimediaObject, $pic);

        return $multimediaObject;
    }

    /**
     * Remove Pic from Multimedia Object.
     *
     * @param \MongoId|string $picId
     *
     * @return MultimediaObject
     *
     * @throws \Exception
     */
    public function removePicFromMultimediaObject(MultimediaObject $multimediaObject, $picId)
    {
        $pic = $multimediaObject->getPicById($picId);
        $picPath = $pic->getPath();
        $picUrl = $pic->getUrl();

        $multimediaObject->removePicById($picId);
        $this->dm->persist($multimediaObject);
        $this->dm->flush();

        if ($this->forceDeleteOnDisk && $picPath) {
            $otherPics = $this->repo->findBy(['pics.path' => $picPath]);
            $otherPicsUrl = $this->repo->findBy(['pics.url' => $picUrl]);
            $seriesPicsUrl = $this->dm->getRepository(Series::class)->findBy(['pics.url' => $picUrl]);
            $seriesPicsPath = $this->dm->getRepository(Series::class)->findBy(['pics.path' => $picPath]);
            $totalPicsUses = (is_countable($otherPics) ? count($otherPics) : 0) + (is_countable($otherPicsUrl) ? count($otherPicsUrl) : 0) + count($seriesPicsUrl) + count($seriesPicsPath);
            if (0 === $totalPicsUses) {
                $this->deleteFileOnDisk($picPath, $multimediaObject);
            }
        }

        $this->dispatcher->dispatchDelete($multimediaObject, $pic);

        return $multimediaObject;
    }

    /**
     * @throws \Exception
     */
    private function deleteFileOnDisk(string $path, MultimediaObject $multimediaObject)
    {
        $dirname = pathinfo($path, PATHINFO_DIRNAME);

        try {
            $deleted = unlink($path);
            if (!$deleted) {
                throw new \Exception("Error deleting file '".$path."' on disk");
            }
            if (0 < strpos($dirname, (string) $multimediaObject->getId())) {
                $finder = new Finder();
                $finder->files()->in($dirname);
                if (0 === $finder->count()) {
                    $dirDeleted = rmdir($dirname);
                    if (!$dirDeleted) {
                        throw new \Exception("Error deleting directory '".$dirname."'on disk");
                    }
                }
            }
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }

    /**
     * @return Pic
     */
    private function updatePosterTag(MultimediaObject $multimediaObject, Pic $pic)
    {
        foreach ($multimediaObject->getPicsWithTag('poster') as $posterPic) {
            $multimediaObject->removePic($posterPic);
        }
        $pic->addTag('poster');

        return $pic;
    }
}
