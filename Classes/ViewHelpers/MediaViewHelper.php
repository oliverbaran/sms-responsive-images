<?php

namespace SMS\SmsResponsiveImages\ViewHelpers;

use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\Imaging\ImageManipulation\CropVariantCollection;
use TYPO3\CMS\Core\Resource\FileReference;
use TYPO3\CMS\Core\Imaging\ImageManipulation\Area;
use SMS\SmsResponsiveImages\Utility\ResponsiveImagesUtility;

class MediaViewHelper extends \TYPO3\CMS\Fluid\ViewHelpers\MediaViewHelper
{
    /**
     * Initialize arguments.
     */
    public function initializeArguments()
    {
        parent::initializeArguments();
        $this->registerArgument('srcset', 'mixed', 'Image sizes that should be rendered.', false);
        $this->registerArgument(
            'sizes',
            'string',
            'Sizes query for responsive image.',
            false,
            '(min-width: %1$dpx) %1$dpx, 100vw'
        );
        $this->registerArgument('breakpoints', 'array', 'Image breakpoints from responsive design.', false);
        $this->registerArgument('picturefill', 'bool', 'Use rendering suggested by picturefill.js', false, true);
        $this->registerArgument('lazyload', 'bool', 'Use lazyloading attribute', false, false);
    }

    /**
     * Render a given media file
     *
     * @return string Rendered tag
     * @throws \UnexpectedValueException
     */
    public function render()
    {
        if($this->arguments['lazyload']){
            $this->arguments['class'] .= ' lazyload';
            $this->tag->addAttribute('class', trim($this->tag->getAttribute('class') . ' lazyload'));
        }

        $tag = parent::render();
        
        if($this->arguments['lazyload'] && FALSE === strpos($tag, 'data-src')){
            $tag = str_replace(' src=', ' data-src=', $tag);
        }

        return $tag;
    }

    /**
     * Render img tag
     *
     * @param  FileInterface $image
     * @param  string        $width
     * @param  string        $height
     *
     * @return string                 Rendered img tag
     */
    protected function renderImage(FileInterface $image, $width, $height)
    {
        if($this->arguments['lazyload']){
            $this->arguments['class'] .= ' lazyload';
            $this->tag->addAttribute('class', trim($this->tag->getAttribute('class') . ' lazyload'));
        }

        if ($this->arguments['breakpoints']) {
            return $this->renderPicture($image, $width, $height, $this->arguments['lazyload']);
        } elseif ($this->arguments['srcset']) {
            return $this->renderImageSrcset($image, $width, $height, $this->arguments['lazyload']);
        } else {
            return parent::renderImage($image, $width, $height);
        }
    }

    /**
     * Render picture tag
     *
     * @param  FileInterface $image
     * @param  string        $width
     * @param  string        $height
     * @param  boolean       $lazyload
     *
     * @return string                 Rendered picture tag
     */
    protected function renderPicture(FileInterface $image, $width, $height, $lazyload)
    {
        // Get crop variants
        $cropString = $image instanceof FileReference ? $image->getProperty('crop') : '';
        $cropVariantCollection = CropVariantCollection::create((string) $cropString);

        $cropVariant = $this->arguments['cropVariant'] ?: 'default';
        $cropArea = $cropVariantCollection->getCropArea($cropVariant);
        $focusArea = $cropVariantCollection->getFocusArea($cropVariant);

        // Generate fallback image
        $fallbackImage = $this->generateFallbackImage($image, $width, $cropArea);

        // Generate picture tag
        $this->tag = $this->getResponsiveImagesUtility()->createPictureTag(
            $image,
            $fallbackImage,
            $this->arguments['breakpoints'],
            $cropVariantCollection,
            $focusArea,
            null,
            $this->tag,
            $this->arguments['picturefill'],
            $lazyload
        );

        return $this->tag->render();
    }

    /**
     * Render img tag with srcset/sizes attributes
     *
     * @param  FileInterface $image
     * @param  string        $width
     * @param  string        $height
     * @param  boolean       $lazyload
     *
     * @return string                 Rendered img tag
     */
    protected function renderImageSrcset(FileInterface $image, $width, $height, $lazyload)
    {
        // Get crop variants
        $cropString = $image instanceof FileReference ? $image->getProperty('crop') : '';
        $cropVariantCollection = CropVariantCollection::create((string) $cropString);

        $cropVariant = $this->arguments['cropVariant'] ?: 'default';
        $cropArea = $cropVariantCollection->getCropArea($cropVariant);
        $focusArea = $cropVariantCollection->getFocusArea($cropVariant);

        // Generate fallback image
        $fallbackImage = $this->generateFallbackImage($image, $width, $cropArea);

        // Generate image tag
        $this->tag = $this->getResponsiveImagesUtility()->createImageTagWithSrcset(
            $image,
            $fallbackImage,
            $this->arguments['srcset'],
            $cropArea,
            $focusArea,
            $this->arguments['sizes'],
            $this->tag,
            $this->arguments['picturefill'],
            $lazyload
        );

        return $this->tag->render();
    }

    /**
     * Generates a fallback image for picture and srcset markup
     *
     * @param  FileInterface $image
     * @param  string        $width
     * @param  Area          $cropArea
     *
     * @return FileInterface
     */
    protected function generateFallbackImage(FileInterface $image, $width, Area $cropArea)
    {
        $processingInstructions = [
            'width' => $width,
            'crop' => $cropArea->isEmpty() ? null : $cropArea->makeAbsoluteBasedOnFile($image),
        ];
        $imageService = $this->getImageService();
        $fallbackImage = $imageService->applyProcessingInstructions($image, $processingInstructions);

        return $fallbackImage;
    }

    /**
     * Returns an instance of the responsive images utility
     * This fixes an issue with DI after clearing the cache
     *
     * @return ResponsiveImagesUtility
     */
    protected function getResponsiveImagesUtility()
    {
        return $this->objectManager->get(ResponsiveImagesUtility::class);
    }
}
