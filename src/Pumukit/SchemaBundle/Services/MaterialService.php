<?php

declare(strict_types=1);

namespace Pumukit\SchemaBundle\Services;

use Doctrine\ODM\MongoDB\DocumentManager;
use Pumukit\SchemaBundle\Document\Material;
use Pumukit\SchemaBundle\Document\MultimediaObject;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpFoundation\File\Exception\FileNotFoundException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class MaterialService
{
    private $dm;
    private $dispatcher;
    private $targetPath;
    private $targetUrl;
    private $forceDeleteOnDisk;
    private $locales;

    public function __construct(DocumentManager $documentManager, MaterialEventDispatcherService $dispatcher, $targetPath, $targetUrl, $forceDeleteOnDisk = true, $locales = ['en'])
    {
        $this->dm = $documentManager;
        $this->dispatcher = $dispatcher;
        $this->targetPath = realpath($targetPath);
        if (!$this->targetPath) {
            throw new \InvalidArgumentException("The path '".$targetPath."' for storing Materials does not exist.");
        }
        $this->targetUrl = $targetUrl;
        $this->forceDeleteOnDisk = $forceDeleteOnDisk;
        $this->locales = $locales;
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
     * Update Material in Multimedia Object.
     *
     * @return MultimediaObject
     */
    public function updateMaterialInMultimediaObject(MultimediaObject $multimediaObject, Material $material)
    {
        $this->dm->persist($multimediaObject);
        $this->dm->flush();

        $this->dispatcher->dispatchUpdate($multimediaObject, $material);

        return $multimediaObject;
    }

    /**
     * Set a material from an url into the multimediaObject.
     *
     * @param string $url
     * @param array  $formData
     *
     * @return MultimediaObject
     */
    public function addMaterialUrl(MultimediaObject $multimediaObject, $url, $formData)
    {
        $material = new Material();
        $material = $this->saveFormData($material, $formData);

        $material->setUrl($url);

        $multimediaObject->addMaterial($material);
        $this->dm->persist($multimediaObject);
        $this->dm->flush();

        $this->dispatcher->dispatchCreate($multimediaObject, $material);

        return $multimediaObject;
    }

    /**
     * Add a material from a file into the multimediaObject.
     *
     * @param array $formData
     *
     * @return MultimediaObject
     *
     * @throws \Exception
     */
    public function addMaterialFile(MultimediaObject $multimediaObject, UploadedFile $materialFile, $formData)
    {
        $i18nName = [];
        if (UPLOAD_ERR_OK !== $materialFile->getError()) {
            throw new \Exception($materialFile->getErrorMessage());
        }

        if (!is_file($materialFile->getPathname())) {
            throw new FileNotFoundException($materialFile->getPathname());
        }

        $material = new Material();

        if (!isset($formData['i18n_name'])) {
            $fileInfo = pathinfo($materialFile->getClientOriginalName());
            $i18nName['en'] = $fileInfo['filename'];
            foreach ($this->locales as $locale) {
                $i18nName[$locale] = $fileInfo['filename'];
            }
            $formData['i18n_name'] = $i18nName;
        }

        $material = $this->saveFormData($material, $formData);

        $path = $materialFile->move($this->targetPath.'/'.$multimediaObject->getId(), $materialFile->getClientOriginalName());

        foreach ($this->locales as $locale) {
            $i18nFileName = $formData['i18n_name'][$locale] ?? $formData['i18n_name'];
            $material->setName($i18nFileName, $locale);
        }
        $material->setSize($path->getSize());

        $material->setPath($path->getPathname());
        $material->setUrl(str_replace($this->targetPath, $this->targetUrl, $path->getPathname()));

        $multimediaObject->addMaterial($material);
        $this->dm->persist($multimediaObject);
        $this->dm->flush();

        $this->dispatcher->dispatchCreate($multimediaObject, $material);

        return $multimediaObject;
    }

    public function updateMaterialFile(MultimediaObject $multimediaObject, UploadedFile $materialFile, Material $material, $formData)
    {
        if (UPLOAD_ERR_OK != $materialFile->getError()) {
            throw new \Exception($materialFile->getErrorMessage());
        }

        if (!is_file($materialFile->getPathname())) {
            throw new FileNotFoundException($materialFile->getPathname());
        }

        $materialOldPath = $material->getPath();

        $material = $this->saveFormData($material, $formData);

        $path = $materialFile->move($this->targetPath.'/'.$multimediaObject->getId(), $materialFile->getClientOriginalName());

        $material->setSize($materialFile->getSize());
        $material->setName($materialFile->getClientOriginalName());

        $material->setPath($path->getPathname());
        $material->setUrl(str_replace($this->targetPath, $this->targetUrl, $path->getPathname()));

        $this->dm->persist($multimediaObject);
        $this->dm->flush();

        $this->deleteFileOnDisk($materialOldPath);

        $this->dispatcher->dispatchUpdate($multimediaObject, $material);

        return $multimediaObject;
    }

    /**
     * Remove Material from Multimedia Object.
     *
     * @param \MongoId|string $materialId
     *
     * @return MultimediaObject
     *
     * @throws \Exception
     */
    public function removeMaterialFromMultimediaObject(MultimediaObject $multimediaObject, $materialId)
    {
        $material = $multimediaObject->getMaterialById($materialId);
        $materialPath = $material->getPath();

        $multimediaObject->removeMaterialById($materialId);
        $this->dm->persist($multimediaObject);
        $this->dm->flush();

        if ($this->forceDeleteOnDisk && $materialPath) {
            $mmobjRepo = $this->dm->getRepository(MultimediaObject::class);
            $otherMaterials = $mmobjRepo->findBy(['materials.path' => $materialPath]);
            if (0 == count($otherMaterials)) {
                $this->deleteFileOnDisk($materialPath);
            }
        }

        $this->dispatcher->dispatchDelete($multimediaObject, $material);

        return $multimediaObject;
    }

    /**
     * Up Material in Multimedia Object.
     *
     * @param \MongoId|string $materialId
     *
     * @return MultimediaObject
     */
    public function upMaterialInMultimediaObject(MultimediaObject $multimediaObject, $materialId)
    {
        $multimediaObject->upMaterialById($materialId);
        $this->dm->persist($multimediaObject);
        $this->dm->flush();

        return $multimediaObject;
    }

    /**
     * Down Material in Multimedia Object.
     *
     * @param \MongoId|string $materialId
     *
     * @return MultimediaObject
     */
    public function downMaterialInMultimediaObject(MultimediaObject $multimediaObject, $materialId)
    {
        $multimediaObject->downMaterialById($materialId);
        $this->dm->persist($multimediaObject);
        $this->dm->flush();

        return $multimediaObject;
    }

    /**
     * Get VTT captions.
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getCaptions(MultimediaObject $multimediaObject)
    {
        $mimeTypeCaptions = CaptionService::$mimeTypeCaptions;

        return $multimediaObject->getMaterials()->filter(function (Material $material) use ($mimeTypeCaptions) {
            return in_array($material->getMimeType(), $mimeTypeCaptions);
        });
    }

    /**
     * Save form data of Material.
     *
     * @param array $formData
     *
     * @return Material
     */
    private function saveFormData(Material $material, $formData)
    {
        if (array_key_exists('i18n_name', $formData)) {
            $material->setI18nName($formData['i18n_name']);
        }
        if (array_key_exists('hide', $formData)) {
            $material->setHide((bool) $formData['hide']);
        }
        if (array_key_exists('language', $formData)) {
            $material->setLanguage($formData['language']);
        }
        if (array_key_exists('mime_type', $formData)) {
            $material->setMimeType($formData['mime_type']);
        }

        return $material;
    }

    /**
     * @param string $path
     *
     * @throws \Exception
     */
    private function deleteFileOnDisk($path)
    {
        $dirname = pathinfo($path, PATHINFO_DIRNAME);

        try {
            $deleted = unlink($path);
            if (!$deleted) {
                throw new \Exception("Error deleting file '".$path."' on disk");
            }
            $finder = new Finder();
            $finder->files()->in($dirname);
            if (0 === $finder->count()) {
                $dirDeleted = rmdir($dirname);
                if (!$dirDeleted) {
                    throw new \Exception("Error deleting directory '".$dirname."'on disk");
                }
            }
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }
}
