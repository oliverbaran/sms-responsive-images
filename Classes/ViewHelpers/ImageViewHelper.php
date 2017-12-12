<?php

namespace SMS\SmsResponsiveImages\ViewHelpers;

use TYPO3\CMS\Core\Imaging\ImageManipulation\CropVariantCollection;
use TYPO3\CMS\Core\Resource\Exception\ResourceDoesNotExistException;
use TYPO3\CMS\Fluid\Core\ViewHelper\TagBuilder;
use SMS\SmsResponsiveImages\Utility\ResponsiveImagesUtility;

class ImageViewHelper extends \TYPO3\CMS\Fluid\ViewHelpers\ImageViewHelper
{
    /**
     * @var TYPO3\CMS\Core\Resource\FileInterface the fallback image
     */
    private $fallbackImage;

    /**
     * Initialize arguments.
     */
    public function initializeArguments()
    {
        parent::initializeArguments();
        $this->registerArgument('srcset', 'mixed',
            'Image sizes that should be rendered.', false);
        $this->registerArgument(
            'sizes', 'string', 'Sizes query for responsive image.', false,
            '(min-width: %1$dpx) %1$dpx, 100vw'
        );
        $this->registerArgument('breakpoints', 'array',
            'Image breakpoints from responsive design.', false);
        $this->registerArgument('picturefill', 'bool',
            'Use rendering suggested by picturefill.js', false, true);
        $this->registerArgument('lazyload', 'bool', 'Use lazyloading attribute',
            false, false);
        $this->registerArgument('ratioBox', 'mixed',
            'if set render a ratioBox with the given ratio arround the image',
            false, false);
    }

    /**
     * Resizes a given image (if required) and renders the respective img tag
     *
     * @see https://docs.typo3.org/typo3cms/TyposcriptReference/ContentObjects/Image/
     *
     * @throws \TYPO3\CMS\Fluid\Core\ViewHelper\Exception
     * @return string Rendered tag
     */
    public function render()
    {
        if ((is_null($this->arguments['src']) && is_null($this->arguments['image']))
            || (!is_null($this->arguments['src']) && !is_null($this->arguments['image']))
        ) {
            throw new \TYPO3\CMS\Fluid\Core\ViewHelper\Exception(
            'You must either specify a string src or a File object.', 1382284106
            );
        }

        if ($this->arguments['lazyload']) {
            $this->arguments['class'] .= ' lazyload';
            $this->tag->addAttribute('class',
                trim($this->tag->getAttribute('class').' lazyload'));
        }

        // Fall back to TYPO3 default if no responsive image feature was selected
        if (!$this->arguments['breakpoints'] && !$this->arguments['srcset']) {
            $tag = parent::render();
            if ($this->arguments['lazyload'] && FALSE === strpos($tag,
                    'data-src')) {
                $tag = str_replace(' src=', ' data-src=', $tag);
            }
            return $tag;
        }
        try {
            // Get FAL image object
            $image = $this->imageService->getImage(
                $this->arguments['src'], $this->arguments['image'],
                $this->arguments['treatIdAsReference']
            );

            // Determine cropping settings
            $cropString = $this->arguments['crop'];
            if ($cropString === null && $image->hasProperty('crop') && $image->getProperty('crop')) {
                $cropString = $image->getProperty('crop');
            }
            $cropVariantCollection = CropVariantCollection::create((string) $cropString);

            $cropVariant = $this->arguments['cropVariant'] ?: 'default';
            $cropArea    = $cropVariantCollection->getCropArea($cropVariant);
            $focusArea   = $cropVariantCollection->getFocusArea($cropVariant);

            // Generate fallback image
            $processingInstructions = [
                'width' => $this->arguments['width'],
                'minWidth' => $this->arguments['minWidth'],
                'maxWidth' => $this->arguments['maxWidth'],
                'crop' => $cropArea->isEmpty() ? null : $cropArea->makeAbsoluteBasedOnFile($image),
            ];
            if ($this->arguments['height']) {
                $processingInstructions['height'] = $this->arguments['height'];
            }
            if ($this->arguments['minHeight']) {
                $processingInstructions['minHeight'] = $this->arguments['minHeight'];
            }
            if ($this->arguments['maxHeight']) {
                $processingInstructions['maxHeight'] = $this->arguments['maxHeight'];
            }
            $this->fallbackImage = $this->imageService->applyProcessingInstructions($image,
                $processingInstructions);

            if ($this->arguments['breakpoints']) {
                // Generate picture tag
                $this->tag = $this->getResponsiveImagesUtility()->createPictureTag(
                    $image, $this->fallbackImage,
                    $this->arguments['breakpoints'], $cropVariantCollection,
                    $focusArea, null, $this->tag,
                    $this->arguments['picturefill'],
                    $this->arguments['absolute'], $this->arguments['lazyload']
                );
            } else {
                // Generate img tag with srcset
                $this->tag = $this->getResponsiveImagesUtility()->createImageTagWithSrcset(
                    $image, $this->fallbackImage, $this->arguments['srcset'],
                    $cropArea, $focusArea, $this->arguments['sizes'],
                    $this->tag, $this->arguments['picturefill'],
                    $this->arguments['absolute'], $this->arguments['lazyload']
                );
            }
        } catch (ResourceDoesNotExistException $e) {
            // thrown if file does not exist
        } catch (\UnexpectedValueException $e) {
            // thrown if a file has been replaced with a folder
        } catch (\RuntimeException $e) {
            // RuntimeException thrown if a file is outside of a storage
        } catch (\InvalidArgumentException $e) {
            // thrown if file storage does not exist
        }

        if ($this->arguments['ratioBox']) {
            return $this->addRatioBox()->render();
        }
        return $this->tag->render();
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

    /**
     * Return the Tag Builder
     *
     * @return TYPO3\CMS\Fluid\Core\ViewHelper\TagBuilder
     */
    protected function getTagBuilder()
    {
        return $this->objectManager->get(TagBuilder::class, 'div');
    }

    /**
     * Adds a ratioBox arround the image tag
     *
     * @return TYPO3\CMS\Fluid\Core\ViewHelper\TagBuilder
     */
    protected function addRatioBox()
    {
        $box = $this->getTagBuilder();
        $box->addAttribute('class', 'ratio-box');
        $box->addAttribute('style', 'padding-bottom: '.$this->getBoxRatio().'%');
        $box->setContent($this->tag->render());
        return $box;
    }

    /**
     *
     * @return float the box ratio
     */
    protected function getBoxRatio()
    {
        if ('auto' == $this->arguments['ratioBox']) {
            return $this->getFallbackImageRatio() * 100;
        } else {
            return $this->arguments['ratioBox'];
        }
    }

    /**
     *
     * @return float the ratio of the fallback image
     */
    protected function getFallbackImageRatio()
    {
        return intval($this->fallbackImage->getProperty('height')) / intval($this->fallbackImage->getProperty('width'));
    }
}